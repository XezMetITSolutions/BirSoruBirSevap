<?php
require_once 'database.php';
$conn = Database::getInstance()->getConnection();
$stmt = $conn->query("DESCRIBE exams");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($columns);
echo "</pre>";

$stmt = $conn->query("SELECT * FROM exams LIMIT 5");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($rows);
