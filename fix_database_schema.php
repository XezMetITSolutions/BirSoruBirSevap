<?php
/**
 * Veritabanı Şeması Onarım Scripti
 * Eksik tabloları ve sütunları kontrol eder ve oluşturur.
 */

require_once 'config.php';
require_once 'database.php';

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Veritabanı Şeması Onarımı</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    echo "<p>Veritabanı bağlantısı başarılı.</p>";
    
    // 1. USERS Tablosu
    echo "<h2>1. Users Tablosu Kontrolü</h2>";
    checkAndFixTable($conn, 'users', [
        "id INT AUTO_INCREMENT PRIMARY KEY",
        "username VARCHAR(50) UNIQUE NOT NULL",
        "password VARCHAR(255) NOT NULL",
        "role ENUM('superadmin', 'admin', 'teacher', 'student') NOT NULL",
        "full_name VARCHAR(200) NOT NULL",
        "branch VARCHAR(100) DEFAULT ''",
        "class_section VARCHAR(50) DEFAULT ''",
        "email VARCHAR(100) DEFAULT ''",
        "phone VARCHAR(20) DEFAULT ''",
        "user_type VARCHAR(20) DEFAULT ''",
        "is_admin TINYINT(1) DEFAULT 0",
        "is_superadmin TINYINT(1) DEFAULT 0",
        "must_change_password TINYINT(1) DEFAULT 1",
        "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "last_login TIMESTAMP NULL",
        "password_changed_at TIMESTAMP NULL",
        "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ], [
        "ADD COLUMN class_section VARCHAR(50) DEFAULT '' AFTER branch",
        "ADD COLUMN email VARCHAR(100) DEFAULT '' AFTER class_section",
        "ADD COLUMN phone VARCHAR(20) DEFAULT '' AFTER email",
        "ADD COLUMN must_change_password TINYINT(1) DEFAULT 1 AFTER is_superadmin",
        "ADD COLUMN last_login TIMESTAMP NULL AFTER created_at",
        "ADD COLUMN password_changed_at TIMESTAMP NULL AFTER last_login",
        "ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER password_changed_at"
    ]);
    
    // 2. EXAMS Tablosu
    echo "<h2>2. Exams Tablosu Kontrolü</h2>";
    checkAndFixTable($conn, 'exams', [
        "id INT AUTO_INCREMENT PRIMARY KEY",
        "exam_id VARCHAR(100) UNIQUE NOT NULL",
        "created_by VARCHAR(50) NOT NULL",
        "title VARCHAR(255) NOT NULL",
        "description TEXT",
        "teacher_name VARCHAR(200)",
        "teacher_institution VARCHAR(200)",
        "class_section VARCHAR(100)",
        "pin VARCHAR(10) DEFAULT NULL",
        "duration INT DEFAULT 30",
        "question_count INT DEFAULT 0",
        "questions LONGTEXT",
        "categories TEXT",
        "negative_marking TINYINT(1) DEFAULT 0",
        "shuffle_questions TINYINT(1) DEFAULT 1",
        "shuffle_options TINYINT(1) DEFAULT 1",
        "status ENUM('active','inactive','completed') DEFAULT 'active'",
        "start_date DATETIME",
        "end_date DATETIME",
        "schedule_type VARCHAR(20) DEFAULT 'immediate'",
        "scheduled_start DATETIME",
        "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        "updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP",
        "expires_at TIMESTAMP NULL"
    ], [
        "ADD COLUMN description TEXT AFTER title",
        "ADD COLUMN teacher_name VARCHAR(200) AFTER created_by",
        "ADD COLUMN teacher_institution VARCHAR(200) AFTER teacher_name",
        "ADD COLUMN class_section VARCHAR(100) AFTER teacher_institution",
        "ADD COLUMN questions LONGTEXT AFTER question_count",
        "ADD COLUMN categories TEXT AFTER questions",
        "ADD COLUMN start_date DATETIME AFTER status",
        "ADD COLUMN end_date DATETIME AFTER start_date",
        "ADD COLUMN schedule_type VARCHAR(20) DEFAULT 'immediate' AFTER end_date",
        "ADD COLUMN scheduled_start DATETIME AFTER schedule_type"
    ]);
    
    // 3. QUESTIONS Tablosu
    echo "<h2>3. Questions Tablosu Kontrolü</h2>";
    checkAndFixTable($conn, 'questions', [
        "id INT AUTO_INCREMENT PRIMARY KEY",
        "question_uid VARCHAR(100) UNIQUE NOT NULL",
        "bank VARCHAR(100) DEFAULT 'Genel'",
        "category VARCHAR(100) DEFAULT 'Genel'",
        "type ENUM('mcq','short_answer','true_false') DEFAULT 'mcq'",
        "question_text TEXT NOT NULL",
        "options LONGTEXT",
        "answer LONGTEXT",
        "explanation TEXT",
        "difficulty INT DEFAULT 1",
        "points INT DEFAULT 1",
        "media LONGTEXT",
        "tags LONGTEXT",
        "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ], []);
    
    // 4. EXAM_RESULTS Tablosu
    echo "<h2>4. Exam Results Tablosu Kontrolü</h2>";
    checkAndFixTable($conn, 'exam_results', [
        "id INT AUTO_INCREMENT PRIMARY KEY",
        "exam_id VARCHAR(100) NOT NULL",
        "username VARCHAR(50) NOT NULL",
        "student_name VARCHAR(200) NOT NULL",
        "total_questions INT DEFAULT 0",
        "correct_answers INT DEFAULT 0",
        "wrong_answers INT DEFAULT 0",
        "score DECIMAL(10,2) DEFAULT 0.00",
        "percentage DECIMAL(5,2) DEFAULT 0.00",
        "time_taken INT DEFAULT 0",
        "answers TEXT",
        "start_time TIMESTAMP NULL",
        "submit_time TIMESTAMP NULL",
        "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ], []);
    
    // 5. PRACTICE_RESULTS Tablosu
    echo "<h2>5. Practice Results Tablosu Kontrolü</h2>";
    checkAndFixTable($conn, 'practice_results', [
        "id INT AUTO_INCREMENT PRIMARY KEY",
        "username VARCHAR(50) NOT NULL",
        "student_name VARCHAR(200) DEFAULT NULL",
        "total_questions INT DEFAULT 0",
        "correct_answers INT DEFAULT 0",
        "wrong_answers INT DEFAULT 0",
        "score DECIMAL(10,2) DEFAULT 0.00",
        "percentage DECIMAL(5,2) DEFAULT 0.00",
        "time_taken INT DEFAULT 0",
        "created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ], []);

    echo "<br><hr><h3>Tüm işlemler tamamlandı!</h3>";
    echo "<a href='index.php'>Ana Sayfaya Dön</a>";

} catch (Exception $e) {
    die("<div style='color:red'>Kritik Hata: " . $e->getMessage() . "</div>");
}

/**
 * Tabloyu ve sütunları kontrol et/oluştur
 */
function checkAndFixTable($conn, $tableName, $createColumns, $alterStatements) {
    // Tablo var mı?
    $stmt = $conn->query("SHOW TABLES LIKE '$tableName'");
    if ($stmt->rowCount() == 0) {
        echo "Tablo '$tableName' bulunamadı. Oluşturuluyor...<br>";
        $sql = "CREATE TABLE $tableName (" . implode(", ", $createColumns) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        try {
            $conn->exec($sql);
            echo "<span style='color:green'>Tablo '$tableName' başarıyla oluşturuldu.</span><br>";
        } catch (PDOException $e) {
            echo "<span style='color:red'>Hata: " . $e->getMessage() . "</span><br>";
        }
    } else {
        echo "Tablo '$tableName' mevcut. Sütunlar kontrol ediliyor...<br>";
        
        // Sütunları kontrol et
        $stmt = $conn->query("SHOW COLUMNS FROM $tableName");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($alterStatements as $sql) {
            // SQL'den sütun adını çıkar (basit regex)
            if (preg_match('/ADD COLUMN\s+(\w+)/i', $sql, $matches)) {
                $columnName = $matches[1];
                if (!in_array($columnName, $existingColumns)) {
                    echo "Sütun '$columnName' eksik. Ekleniyor...<br>";
                    try {
                        $conn->exec("ALTER TABLE $tableName " . $sql);
                        echo "<span style='color:green'>Sütun '$columnName' eklendi.</span><br>";
                    } catch (PDOException $e) {
                        echo "<span style='color:red'>Hata: " . $e->getMessage() . "</span><br>";
                    }
                } else {
                    // echo "Sütun '$columnName' zaten var.<br>";
                }
            }
        }
        echo "<span style='color:green'>Tablo '$tableName' kontrolü tamamlandı.</span><br>";
    }
}
?>
