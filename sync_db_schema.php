<?php
require_once 'database.php';
$conn = Database::getInstance()->getConnection();

// user_badges tablosunu oluştur veya güncelle
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS user_badges (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL,
        badge_name VARCHAR(100) NOT NULL,
        earned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "user_badges tablosu kontrol edildi.<br>";
    
    // level sütunu var mı kontrol et
    $result = $conn->query("SHOW COLUMNS FROM user_badges LIKE 'level'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE user_badges ADD COLUMN level INT DEFAULT 1");
        echo "level sütunu eklendi.<br>";
    }
    
    // badge_name sütunu var mı kontrol et (eskiden kalma tablolarda eksik olabilir)
    $result = $conn->query("SHOW COLUMNS FROM user_badges LIKE 'badge_name'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE user_badges ADD COLUMN badge_name VARCHAR(100) NOT NULL AFTER username");
        echo "badge_name sütunu eklendi.<br>";
    }
} catch (Exception $e) {
    echo "Tablo güncelleme hatası: " . $e->getMessage() . "<br>";
}

// exam_results tablosunu oluştur veya güncelle
try {
    $conn->exec("CREATE TABLE IF NOT EXISTS exam_results (
        id INT AUTO_INCREMENT PRIMARY KEY,
        exam_id VARCHAR(50) NOT NULL,
        username VARCHAR(50) NOT NULL,
        score INT DEFAULT 0,
        total_questions INT DEFAULT 0,
        duration INT DEFAULT 0,
        answers LONGTEXT,
        detailed_results LONGTEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX(exam_id),
        INDEX(username)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "exam_results tablosu hazır.<br>";
    
    // total_questions sütunu var mı kontrol et
    $result = $conn->query("SHOW COLUMNS FROM exam_results LIKE 'total_questions'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE exam_results ADD COLUMN total_questions INT DEFAULT 0 AFTER score");
        echo "exam_results total_questions sütunu eklendi.<br>";
    }
} catch (Exception $e) {
    echo "exam_results güncelleme hatası: " . $e->getMessage() . "<br>";
}

// practice_results tablosu için 'score' sütununu kontrol et (bazen eksik olabiliyor)
try {
    $result = $conn->query("SHOW COLUMNS FROM practice_results LIKE 'score'");
    if ($result->rowCount() == 0) {
        $conn->exec("ALTER TABLE practice_results ADD COLUMN score INT DEFAULT 0 AFTER username");
        echo "practice_results score sütunu eklendi.<br>";
    }
} catch (Exception $e) {
    echo "practice_results güncelleme hatası: " . $e->getMessage() . "<br>";
}
