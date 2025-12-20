<?php
/**
 * Veritabanı Bağlantı Dosyası
 */

require_once 'config.php';

class Database {
    private static $instance = null;
    private $connection;
    
    private $host;
    private $dbname;
    private $username;
    private $password;
    
    private function __construct() {
        // Environment variables veya config kullan
        $this->host = getenv('DB_HOST') ?: (defined('DB_HOST') ? DB_HOST : 'localhost');
        $this->dbname = getenv('DB_NAME') ?: (defined('DB_NAME') ? DB_NAME : '');
        $this->username = getenv('DB_USER') ?: (defined('DB_USER') ? DB_USER : '');
        $this->password = getenv('DB_PASS') ?: (defined('DB_PASS') ? DB_PASS : '');
        
        // Güvenlik: Boş bilgileri kontrol et
        if (empty($this->dbname) || empty($this->username)) {
            die("Veritabanı yapılandırması eksik! Lütfen .env dosyasını kontrol edin.");
        }
        
        try {
            $this->connection = new PDO(
                "mysql:host={$this->host};dbname={$this->dbname};charset=utf8mb4",
                $this->username,
                $this->password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false, // Connection pooling için false
                    PDO::ATTR_TIMEOUT => 10 // 10 saniye timeout
                ]
            );
        } catch (PDOException $e) {
            // Die yerine exception fırlat, böylece login.php'de yakalanabilir
            throw new Exception("Veritabanı bağlantı hatası: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Kullanıcı tablosunu oluştur
     */
    public function createUsersTable() {
        // Önce tabloyu kontrol et
        $checkSql = "SHOW TABLES LIKE 'users'";
        $result = $this->connection->query($checkSql);
        
        if ($result->rowCount() > 0) {
            // Tablo varsa, sütunları kontrol et
            $columnsSql = "SHOW COLUMNS FROM users";
            $columns = $this->connection->query($columnsSql)->fetchAll();
            $columnNames = array_column($columns, 'Field');
            
            // Eksik sütunları ekle (mevcut yapıya uygun)
            if (!in_array('class_section', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN class_section VARCHAR(50) DEFAULT '' AFTER branch");
            }
            if (!in_array('email', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN email VARCHAR(100) DEFAULT '' AFTER class_section");
            }
            if (!in_array('phone', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20) DEFAULT '' AFTER email");
            }
            if (!in_array('must_change_password', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN must_change_password BOOLEAN DEFAULT TRUE AFTER phone");
            }
            if (!in_array('last_login', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN last_login TIMESTAMP NULL AFTER created_at");
            }
            if (!in_array('password_changed_at', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN password_changed_at TIMESTAMP NULL AFTER last_login");
            }
            if (!in_array('updated_at', $columnNames)) {
                $this->connection->exec("ALTER TABLE users ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER password_changed_at");
            }
            
            return true;
        } else {
            // Tablo yoksa oluştur
            $sql = "CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                role ENUM('superadmin', 'admin', 'teacher', 'student') NOT NULL,
                full_name VARCHAR(200) NOT NULL,
                branch VARCHAR(100) DEFAULT '',
                class_section VARCHAR(50) DEFAULT '',
                email VARCHAR(100) DEFAULT '',
                phone VARCHAR(20) DEFAULT '',
                user_type VARCHAR(20) DEFAULT '',
                is_admin TINYINT(1) DEFAULT 0,
                is_superadmin TINYINT(1) DEFAULT 0,
                must_change_password BOOLEAN DEFAULT TRUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                last_login TIMESTAMP NULL,
                password_changed_at TIMESTAMP NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            try {
                $this->connection->exec($sql);
                return true;
            } catch (PDOException $e) {
                error_log("Tablo oluşturma hatası: " . $e->getMessage());
                return false;
            }
        }
    }
    
    /**
     * JSON verilerini veritabanına aktar
     */
    public function migrateFromJSON() {
        $jsonFile = 'data/users.json';
        if (!file_exists($jsonFile)) {
            return false;
        }
        
        $jsonData = file_get_contents($jsonFile);
        $users = json_decode($jsonData, true);
        
        if (!$users) {
            return false;
        }
        
        $migratedCount = 0;
        
        foreach ($users as $username => $userData) {
            try {
                $sql = "INSERT INTO users (username, password, role, name, institution, class_section, email, phone, must_change_password, created_at, last_login, password_changed_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                        ON DUPLICATE KEY UPDATE 
                        password = VALUES(password),
                        role = VALUES(role),
                        name = VALUES(name),
                        institution = VALUES(institution),
                        class_section = VALUES(class_section),
                        email = VALUES(email),
                        phone = VALUES(phone),
                        must_change_password = VALUES(must_change_password),
                        updated_at = CURRENT_TIMESTAMP";
                
                $stmt = $this->connection->prepare($sql);
                $stmt->execute([
                    $username,
                    $userData['password'],
                    $userData['role'],
                    $userData['full_name'] ?? $userData['name'] ?? 'Bilinmiyor',
                    $userData['branch'] ?? $userData['institution'] ?? '',
                    $userData['class_section'] ?? '',
                    $userData['email'] ?? '',
                    $userData['phone'] ?? '',
                    $userData['must_change_password'] ?? true,
                    $userData['created_at'] ?? date('Y-m-d H:i:s'),
                    $userData['last_login'] ?? null,
                    $userData['password_changed_at'] ?? null
                ]);
                
                $migratedCount++;
            } catch (PDOException $e) {
                error_log("Kullanıcı aktarma hatası ($username): " . $e->getMessage());
            }
        }
        
        return $migratedCount;
    }
    
    /**
     * Tüm kullanıcıları getir
     */
    public function getAllUsers() {
        $sql = "SELECT * FROM users ORDER BY created_at DESC";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }
    
    /**
     * Kullanıcı adına göre kullanıcı getir
     */
    public function getUserByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ?";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    /**
     * Kullanıcı ekle/güncelle
     */
    public function saveUser($username, $password, $role, $name, $institution = '', $class_section = '', $email = '', $phone = '') {
        $sql = "INSERT INTO users (username, password, role, full_name, branch, class_section, email, phone, user_type, is_admin, is_superadmin, must_change_password, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) 
                ON DUPLICATE KEY UPDATE 
                password = VALUES(password),
                role = VALUES(role),
                full_name = VALUES(full_name),
                branch = VALUES(branch),
                class_section = VALUES(class_section),
                email = VALUES(email),
                phone = VALUES(phone),
                user_type = VALUES(user_type),
                is_admin = VALUES(is_admin),
                is_superadmin = VALUES(is_superadmin),
                updated_at = CURRENT_TIMESTAMP";
        
        $isAdmin = ($role === 'admin' || $role === 'superadmin') ? 1 : 0;
        $isSuperadmin = ($role === 'superadmin') ? 1 : 0;
        
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $name,
            $institution,
            $class_section,
            $email,
            $phone,
            $role,
            $isAdmin,
            $isSuperadmin,
            true,
            date('Y-m-d H:i:s')
        ]);
    }
    
    /**
     * Kullanıcı sil
     */
    public function deleteUser($username) {
        $sql = "DELETE FROM users WHERE username = ?";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$username]);
    }
    
    /**
     * Şifre değiştir
     */
    public function changePassword($username, $newPassword) {
        $sql = "UPDATE users SET password = ?, must_change_password = FALSE, password_changed_at = CURRENT_TIMESTAMP WHERE username = ?";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([password_hash($newPassword, PASSWORD_DEFAULT), $username]);
    }
    
    /**
     * Son giriş tarihini güncelle
     */
    public function updateLastLogin($username) {
        $sql = "UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE username = ?";
        $stmt = $this->connection->prepare($sql);
        return $stmt->execute([$username]);
    }
}
?>
