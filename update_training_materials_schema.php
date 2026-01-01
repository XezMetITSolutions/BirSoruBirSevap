<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    $sql = "CREATE TABLE IF NOT EXISTS `training_materials` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL,
      `description` text,
      `file_path` varchar(255) NOT NULL,
      `file_type` varchar(50) DEFAULT NULL,
      `allowed_roles` text,
      `created_by` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $conn->exec($sql);
    echo "Table 'training_materials' created successfully or already exists.<br>";
    
    // Create uploads directory if not exists
    $uploadDir = __DIR__ . '/uploads/training';
    if (!file_exists($uploadDir)) {
        if (mkdir($uploadDir, 0777, true)) {
            echo "Directory 'uploads/training' created successfully.<br>";
        } else {
            echo "Failed to create directory 'uploads/training'. Check permissions.<br>";
        }
    } else {
        echo "Directory 'uploads/training' already exists.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
