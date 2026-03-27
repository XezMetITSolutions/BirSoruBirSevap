<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

$input = json_decode(file_get_contents('php://input'), true);

$username = $input['username'] ?? '';
$bank = $input['bank'] ?? 'Genel';
$category = $input['category'] ?? 'Genel';
$totalCount = (int)($input['totalCount'] ?? 0);
$correctCount = (int)($input['correctCount'] ?? 0);
$timeTaken = (int)($input['timeTaken'] ?? 0); // Saniye cinsinden
$studentName = $input['studentName'] ?? '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Kullanıcı adı gerekli.']);
    exit;
}

$wrongCount = $totalCount - $correctCount;
$score = $correctCount * 10; // Her doğru 10 puan (web standardı)
$percentage = ($totalCount > 0) ? ($correctCount / $totalCount) * 100 : 0;

try {
    $sql = "INSERT INTO practice_results (username, student_name, total_questions, correct_answers, wrong_answers, score, percentage, time_taken, bank, category) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $result = $stmt->execute([
        $username,
        $studentName,
        $totalCount,
        $correctCount,
        $wrongCount,
        $score,
        $percentage,
        $timeTaken,
        $bank,
        $category
    ]);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Alıştırma sonucu başarıyla kaydedildi.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Kaydetme hatası.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
