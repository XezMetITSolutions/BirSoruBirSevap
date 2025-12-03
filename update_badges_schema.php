<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "Updating user_badges table schema...\n";

    // Add level column
    try {
        $conn->exec("ALTER TABLE user_badges ADD COLUMN level INT DEFAULT 1 AFTER badge_name");
        echo "Added 'level' column.\n";
    } catch (PDOException $e) {
        echo "Column 'level' might already exist or error: " . $e->getMessage() . "\n";
    }

    echo "Schema update completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
