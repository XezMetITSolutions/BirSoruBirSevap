<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock the environment
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['username'] = $_GET['test_user'] ?? 'yakup'; // Default test user

echo "<h2>Testing api_student_stats.php</h2>";

include 'api_student_stats.php';
