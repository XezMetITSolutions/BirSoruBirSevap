<?php
/**
 * Kimlik Doğrulama Sistemi
 */

require_once 'config.php';
require_once 'database.php';

class Auth {
    private static $instance = null;
    private $db;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Kullanıcı girişi
     */
    public function login($username, $password, $role) {
        // Input sanitization
        $username = sanitize_input($username);
        
        $user = $this->db->getUserByUsername($username);
        
        if ($user && $user['role'] === $role) {
            // Hash'lenmiş şifre kontrolü
            if (password_verify($password, $user['password'])) {
                // Last login bilgisini güncelle
                $this->db->updateLastLogin($username);
                
                $_SESSION['user'] = [
                    'username' => $username,
                    'role' => $role,
                    'name' => $user['full_name'] ?? $user['name'] ?? '',
                    'institution' => $user['branch'] ?? $user['institution'] ?? '',
                    'branch' => $user['branch'] ?? $user['institution'] ?? '',
                    'class_section' => $user['class_section'] ?? '',
                    'login_time' => time(),
                    'must_change_password' => $user['must_change_password'] ?? false
                ];
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Kullanıcı çıkışı
     */
    public function logout() {
        unset($_SESSION['user']);
        session_destroy();
    }
    
    /**
     * Kullanıcı kontrolü
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['user'])) {
            return false;
        }
        
        // Session'ı her kontrol sırasında yenile (timeout'u sıfırla)
        $_SESSION['last_activity'] = time();
        $_SESSION['refresh_time'] = time();
        
        return true;
    }
    
    /**
     * Rol kontrolü
     */
    public function hasRole($role) {
        if (!isset($_SESSION['user'])) {
            return false;
        }
        
        // Session'ı her kontrol sırasında yenile (timeout'u sıfırla)
        $_SESSION['last_activity'] = time();
        $_SESSION['refresh_time'] = time();
        
        $userRole = $_SESSION['user']['role'];
        
        // Superadmin tüm rollere erişebilir
        if ($userRole === 'superadmin') {
            return true;
        }
        
        return $userRole === $role;
    }
    
    /**
     * Kullanıcı bilgileri
     */
    public function getUser() {
        if (!isset($_SESSION['user'])) {
            return null;
        }
        
        // Session'ı her çağrıda yenile (timeout'u sıfırla)
        $_SESSION['last_activity'] = time();
        $_SESSION['refresh_time'] = time();
        
        return $_SESSION['user'];
    }
    
    /**
     * Kullanıcı listesi (Veritabanından)
     */
    public function getUsers() {
        $users = $this->db->getAllUsers();
        $result = [];
        
        foreach ($users as $user) {
            $result[$user['username']] = $user;
        }
        
        return $result;
    }
    
    /**
     * Kullanıcı kaydet
     */
    public function saveUser($username, $password, $role, $name, $institution = '', $class_section = '', $email = '', $phone = '') {
        return $this->db->saveUser($username, $password, $role, $name, $institution, $class_section, $email, $phone);
    }
    
    /**
     * Kullanıcı sil
     */
    public function deleteUser($username) {
        return $this->db->deleteUser($username);
    }
    
    /**
     * Tüm kullanıcıları getir (admin için)
     */
    public function getAllUsers() {
        return $this->getUsers();
    }
    
    /**
     * Şifre değiştir
     */
    public function changePassword($username, $newPassword) {
        $result = $this->db->changePassword($username, $newPassword);
        
        if ($result) {
            // Session'ı güncelle
            if (isset($_SESSION['user']) && $_SESSION['user']['username'] === $username) {
                $_SESSION['user']['must_change_password'] = false;
            }
        }
        
        return $result;
    }
    
    /**
     * Yönlendirme
     */
    public function redirectToRole() {
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
        
        $role = $_SESSION['user']['role'];
        switch ($role) {
            case 'superadmin':
                header('Location: admin/dashboard.php');
                break;
            case 'admin':
                header('Location: admin/dashboard.php');
                break;
            case 'teacher':
                header('Location: teacher/dashboard.php');
                break;
            case 'student':
                header('Location: student/dashboard.php');
                break;
            default:
                header('Location: login.php');
        }
        exit;
    }
}
?>
