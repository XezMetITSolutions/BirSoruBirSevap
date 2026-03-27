<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once 'auth.php';
require_once 'config.php';
require_once 'database.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    die(json_encode(['success' => false, 'error' => 'Kullanıcı adı eksik']));
}

$conn = Database::getInstance()->getConnection();

// Alıştırma Sonuçları
$stmt = $conn->prepare("SELECT id, 'practice' as type, bank, category, score, total_questions, time_taken as duration, created_at 
                       FROM practice_results 
                       WHERE username = ? 
                       ORDER BY created_at DESC 
                       LIMIT 50");
$stmt->execute([$username]);
$practiceResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sınav Sonuçları
$stmt = $conn->prepare("SELECT r.id, 'exam' as type, e.title as bank, 'Sınav' as category, r.score, r.total_questions, r.duration, r.created_at 
                       FROM exam_results r
                       JOIN exams e ON r.exam_id = e.exam_id
                       WHERE r.username = ? 
                       ORDER BY r.created_at DESC 
                       LIMIT 50");
// Not: exam_results tablosunda total_questions yoksa joinsiz çekip questionsı parse etmeliyiz.
// Şimdilik exam_results tablosunda total_questions olduğunu varsayıyorum (sync_db_schema.php'ye eklemiştik)
$stmt->execute([$username]);
$examResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İkisini birleştir ve tarihe göre sırala
$allResults = array_merge($practiceResults, $examResults);
usort($allResults, function($a, $b) {
    return strtotime($b['created_at']) <=> strtotime($a['created_at']);
});

echo json_encode([
    'success' => true,
    'results' => array_slice($allResults, 0, 50)
]);
