<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$examCode = $input['exam_code'] ?? '';
$username = $input['username'] ?? '';

if (empty($examCode) || empty($username)) {
    die(json_encode(['success' => false, 'error' => 'Geçersiz parametre']));
}

$examCode = strtoupper(trim($examCode));

require_once 'database.php';
$conn = Database::getInstance()->getConnection();

// Kullanıcıyı çek
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$username]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die(json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı']));
}

// Sınavı çek
$stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = ?");
$stmt->execute([$examCode]);
$exam = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$exam) {
    die(json_encode(['success' => false, 'error' => 'Geçersiz sınav kodu']));
}

if (($exam['status'] ?? '') !== 'active') {
    die(json_encode(['success' => false, 'error' => 'Bu sınav şu an aktif değil']));
}

// Kurum kontrolü
$studentInst = mb_strtolower(trim($user['institution'] ?? $user['branch'] ?? 'IQRA'), 'UTF-8');
$examInst = mb_strtolower(trim($exam['teacher_institution'] ?? ''), 'UTF-8');
$examSection = mb_strtolower(trim($exam['class_section'] ?? ''), 'UTF-8');

$canJoin = ($examSection !== '' && ($examSection === $studentInst)) ||
           ($examInst !== '' && ($examInst === $studentInst));
           
// Yakup hoca için test amaçlı geçici bypass (isterse)
// $canJoin = true; 

if (!$canJoin) {
    die(json_encode(['success' => false, 'error' => 'Bu sınav sizin kurumunuz için değil (Kurumunuz: '.$user['institution'].')']));
}

$action = $input['action'] ?? 'join';

if ($action === 'submit') {
    $score = $input['score'] ?? 0;
    $results = $input['results'] ?? [];
    
    // Sınav sonucunu kaydet
    $stmt = $conn->prepare("INSERT INTO exam_results (exam_id, username, score, completion_time, results) VALUES (?, ?, ?, NOW(), ?)");
    $stmt->execute([
        $examCode,
        $username,
        $score,
        json_encode($results)
    ]);
    
    die(json_encode(['success' => true, 'message' => 'Sınav sonucu kaydedildi']));
}

// ... join logic follows ...

// Soruları parse et ve Shuffle et
$questions = json_decode($exam['questions'], true) ?? [];
$correctMapped = array_map(function($q) {
    $optionsArr = $q['options'] ?? [];
    $rawAnswer = $q['answer'][0] ?? 'A';
    
    // Normalize options for mobile (text only)
    $mappedOptions = array_map(function($o) {
        return is_array($o) ? ($o['text'] ?? '') : (string)$o;
    }, $optionsArr);

    $correctIndex = 0;
    if (is_numeric($rawAnswer)) {
        $correctIndex = (int)$rawAnswer;
    } else {
        $map = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
        $correctIndex = $map[strtoupper((string)$rawAnswer)] ?? 0;
    }

    return [
        'id' => $q['id'] ?? uniqid(),
        'text' => $q['question'] ?? $q['text'] ?? '',
        'options' => $mappedOptions,
        'correct' => $correctIndex
    ];
}, $questions);

echo json_encode([
    'success' => true,
    'questions' => $correctMapped,
    'title' => $exam['title'] ?? 'Sınav',
    'duration' => (int)($exam['duration'] ?? 30)
]);
