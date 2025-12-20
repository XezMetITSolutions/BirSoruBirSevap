<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // 1. Check if 'region' column exists
    $check = $conn->query("SHOW COLUMNS FROM users LIKE 'region'");
    if ($check->rowCount() == 0) {
        echo "Adding 'region' column...\n";
        // Add after 'institution' or 'branch' (users table structure varies, assume 'role' exists)
        $conn->exec("ALTER TABLE users ADD COLUMN region VARCHAR(100) DEFAULT NULL AFTER role");
        echo "Column added successfully.\n";
    } else {
        echo "'region' column already exists.\n";
    }

    // 2. Update existing users to 'Arlberg' if region is empty
    echo "Migrating existing users to 'Arlberg'...\n";
    $sql = "UPDATE users SET region = 'Arlberg' WHERE region IS NULL OR region = ''";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    echo "Updated " . $stmt->rowCount() . " users.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
