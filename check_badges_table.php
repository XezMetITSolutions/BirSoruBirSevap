<?php
require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

try {
    $stmt = $conn->query("SHOW COLUMNS FROM user_badges LIKE 'level'");
    if ($stmt->rowCount() == 0) {
        echo "Level column missing. Adding now...\n";
        $conn->exec("ALTER TABLE user_badges ADD COLUMN level INT DEFAULT 1 AFTER badge_name");
        echo "Successfully added level column.\n";
    } else {
        echo "Level column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
