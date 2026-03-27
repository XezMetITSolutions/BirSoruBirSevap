<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once 'config.php';
require_once 'database.php';
require_once 'Badges.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    die(json_encode(['success' => false, 'error' => 'Kullanıcı adı eksik']));
}

$conn = Database::getInstance()->getConnection();

// Puanları topla (Alıştırma ve Sınav)
$stmt = $conn->prepare("SELECT SUM(score) as total FROM (
    SELECT score FROM practice_results WHERE username = ?
    UNION ALL
    SELECT score FROM exam_results WHERE username = ?
) as combined");
$stmt->execute([$username, $username]);
$scoreResult = $stmt->fetch(PDO::FETCH_ASSOC);
$totalScore = (int)($scoreResult['total'] ?? 0);

// Rozetleri say
$badgesManager = new Badges();
$userBadgesRaw = $badgesManager->loadUserBadges($username);
$userBadges = $userBadgesRaw[$username] ?? [];
$totalBadges = count($userBadges);

// Alıştırma sayısını al
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM practice_results WHERE username = ?");
$stmt->execute([$username]);
$practiceResult = $stmt->fetch(PDO::FETCH_ASSOC);
$totalPractices = (int)($practiceResult['count'] ?? 0);

echo json_encode([
    'success' => true,
    'stats' => [
        'score' => $totalScore,
        'badges' => $totalBadges,
        'practiceCount' => $totalPractices
    ]
]);
