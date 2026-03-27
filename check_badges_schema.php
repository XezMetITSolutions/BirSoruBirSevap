<?php
require_once 'database.php';
$conn = Database::getInstance()->getConnection();
$stmt = $conn->query("DESCRIBE user_badges");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($columns);
echo "</pre>";
