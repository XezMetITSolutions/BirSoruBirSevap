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
require_once 'ExamManager.php';
require_once 'database.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    die(json_encode(['success' => false, 'error' => 'Kullanıcı adı eksik']));
}

$conn = Database::getInstance()->getConnection();
// Kullanıcıyı veritabanından çek (kurum/şube bilgisi için)
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı']));
}

$examManager = new ExamManager();
$allExams = $examManager->getExamsForStudent($username);

$activeExams = [];
$currentTime = time();

foreach ($allExams as $examId => $exam) {
    if (($exam['status'] ?? '') === 'active') {
        // Zaten tamamlanmış mı?
        $stmtCheck = $conn->prepare("SELECT id FROM exam_results WHERE exam_id = ? AND username = ?");
        $stmtCheck->execute([$examId, $username]);
        if ($stmtCheck->rowCount() > 0) continue;

        $activeExams[] = [
            'exam_id' => $examId,
            'title' => $exam['title'] ?? 'İsimsiz Sınav',
            'duration' => $exam['duration'] ?? 30,
            'questionCount' => count($exam['questions'] ?? [])
        ];
    }
}

echo json_encode(['success' => true, 'exams' => $activeExams]);
