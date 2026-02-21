<?php
require_once 'config.php';
require_once 'database.php';

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $practiceCount = $conn->query('SELECT COUNT(*) FROM practice_results')->fetchColumn();
    $examCount = $conn->query('SELECT COUNT(*) FROM exam_results')->fetchColumn();
    $studentCount = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'student'")->fetchColumn();
    
    echo "Total Students: $studentCount\n";
    echo "Total Practices: $practiceCount\n";
    echo "Total Exams: $examCount\n";
    
    // Check some sample records
    $sampleExp = $conn->query("SELECT username, created_at FROM exam_results LIMIT 5")->fetchAll();
    echo "\nSample Exams:\n";
    print_r($sampleExp);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
