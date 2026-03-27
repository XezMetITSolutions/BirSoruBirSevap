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
    
    // Eksik sütunları tek tek kontrol et
    $columns_to_check = [
        'username' => "VARCHAR(50) NOT NULL",
        'badge_name' => "VARCHAR(100) NOT NULL",
        'level' => "INT DEFAULT 1",
        'earned_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];
    
    foreach ($columns_to_check as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM user_badges LIKE '$col'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE user_badges ADD COLUMN $col $definition");
            echo "user_badges: $col sütunu eklendi.<br>";
        }
    }
} catch (Exception $e) {
    echo "user_badges güncelleme hatası: " . $e->getMessage() . "<br>";
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
    echo "exam_results tablosu kontrol edildi.<br>";
    
    // Eksik sütunları tek tek kontrol et
    $exam_columns = [
        'exam_id' => "VARCHAR(50) NOT NULL",
        'username' => "VARCHAR(50) NOT NULL",
        'score' => "INT DEFAULT 0",
        'total_questions' => "INT DEFAULT 0",
        'duration' => "INT DEFAULT 0",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP"
    ];
    
    foreach ($exam_columns as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM exam_results LIKE '$col'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE exam_results ADD COLUMN $col $definition");
            echo "exam_results: $col sütunu eklendi.<br>";
        }
    }
} catch (Exception $e) {
    echo "exam_results güncelleme hatası: " . $e->getMessage() . "<br>";
}

// practice_results tablosu kontrolü
try {
    echo "practice_results tablosu kontrol ediliyor...<br>";
    $practice_columns = [
        'score' => "INT DEFAULT 0",
        'total_questions' => "INT DEFAULT 0",
        'time_taken' => "INT DEFAULT 0"
    ];
    
    foreach ($practice_columns as $col => $definition) {
        $result = $conn->query("SHOW COLUMNS FROM practice_results LIKE '$col'");
        if ($result->rowCount() == 0) {
            $conn->exec("ALTER TABLE practice_results ADD COLUMN $col $definition");
            echo "practice_results: $col sütunu eklendi.<br>";
        }
    }
} catch (Exception $e) {
    echo "practice_results güncelleme hatası: " . $e->getMessage() . "<br>";
}
