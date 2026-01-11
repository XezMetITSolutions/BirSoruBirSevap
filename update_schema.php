<?php
require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

// Güvenlik kontrolü
if (php_sapi_name() !== 'cli') {
    $auth = Auth::getInstance();
    if (!$auth->isLoggedIn() || !$auth->hasRole('superadmin')) {
        die('Erişim engellendi: Sadece Super Admin bu sayfaya erişebilir.');
    }
}

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "Updating exams table schema...\n";

    // Add columns to exams table
    // We use individual ALTER statements to handle "column already exists" gracefully
    $alterStatements = [
        "ADD COLUMN description TEXT AFTER title",
        "ADD COLUMN teacher_name VARCHAR(200) AFTER created_by",
        "ADD COLUMN teacher_institution VARCHAR(200) AFTER teacher_name",
        "ADD COLUMN class_section VARCHAR(100) AFTER teacher_institution",
        "ADD COLUMN questions LONGTEXT AFTER question_count", // JSON storage for questions
        "ADD COLUMN start_date DATETIME AFTER status",
        "ADD COLUMN end_date DATETIME AFTER start_date",
        "ADD COLUMN schedule_type VARCHAR(20) DEFAULT 'immediate' AFTER end_date",
        "ADD COLUMN scheduled_start DATETIME AFTER schedule_type",
        "ADD COLUMN categories TEXT AFTER questions" // To store selected categories
    ];

    foreach ($alterStatements as $sql) {
        try {
            $conn->exec("ALTER TABLE exams " . $sql);
            echo "Executed: ALTER TABLE exams " . $sql . "\n";
        } catch (PDOException $e) {
            // Check if error is "Duplicate column name" (Error 1060)
            if ($e->errorInfo[1] == 1060) {
                echo "Skipped (Column exists): " . $sql . "\n";
            } else {
                echo "Error executing '" . $sql . "': " . $e->getMessage() . "\n";
            }
        }
    }
    
    // Questions tablosunu oluştur
    $conn->exec("CREATE TABLE IF NOT EXISTS `questions` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `question_uid` varchar(100) NOT NULL,
      `bank` varchar(100) DEFAULT 'Genel',
      `category` varchar(100) DEFAULT 'Genel',
      `type` enum('mcq','short_answer','true_false') DEFAULT 'mcq',
      `question_text` text NOT NULL,
      `options` longtext,
      `answer` longtext,
      `explanation` text,
      `difficulty` int(11) DEFAULT 1,
      `points` int(11) DEFAULT 1,
      `media` longtext,
      `tags` longtext,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`),
      UNIQUE KEY `question_uid` (`question_uid`),
      KEY `idx_bank` (`bank`),
      KEY `idx_category` (`category`),
      KEY `idx_difficulty` (`difficulty`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "Questions table checked/created.\n";
    
    echo "Schema update completed successfully.\n";

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage() . "\n";
}
