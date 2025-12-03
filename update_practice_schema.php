<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();

    echo "Updating practice_results table schema...\n";

    // Add bank column
    try {
        $conn->exec("ALTER TABLE practice_results ADD COLUMN bank VARCHAR(100) DEFAULT 'Genel' AFTER student_name");
        echo "Added 'bank' column.\n";
    } catch (PDOException $e) {
        echo "Column 'bank' might already exist or error: " . $e->getMessage() . "\n";
    }

    // Add category column
    try {
        $conn->exec("ALTER TABLE practice_results ADD COLUMN category VARCHAR(100) DEFAULT 'Genel' AFTER bank");
        echo "Added 'category' column.\n";
    } catch (PDOException $e) {
        echo "Column 'category' might already exist or error: " . $e->getMessage() . "\n";
    }

    // Add difficulty column
    try {
        $conn->exec("ALTER TABLE practice_results ADD COLUMN difficulty VARCHAR(50) DEFAULT 'Belirtilmemiş' AFTER category");
        echo "Added 'difficulty' column.\n";
    } catch (PDOException $e) {
        echo "Column 'difficulty' might already exist or error: " . $e->getMessage() . "\n";
    }

    // Add answers column
    try {
        $conn->exec("ALTER TABLE practice_results ADD COLUMN answers LONGTEXT AFTER time_taken");
        echo "Added 'answers' column.\n";
    } catch (PDOException $e) {
        echo "Column 'answers' might already exist or error: " . $e->getMessage() . "\n";
    }

    // Add detailed_results column
    try {
        $conn->exec("ALTER TABLE practice_results ADD COLUMN detailed_results LONGTEXT AFTER answers");
        echo "Added 'detailed_results' column.\n";
    } catch (PDOException $e) {
        echo "Column 'detailed_results' might already exist or error: " . $e->getMessage() . "\n";
    }

    echo "Schema update completed.\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
