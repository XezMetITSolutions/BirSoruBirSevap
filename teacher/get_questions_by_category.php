<?php
/**
 * Kategoriye göre soruları getir
 */

session_start();
require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$category = $_POST['category'] ?? '';

if (empty($category)) {
    echo json_encode(['success' => false, 'message' => 'Category parameter required']);
    exit;
}

try {
    // Soruları yükle
    $questionLoader = new QuestionLoader();
    $questionLoader->loadQuestions();
    
    $allQuestions = $_SESSION['all_questions'] ?? [];
    $filteredQuestions = [];
    
    // Kategoriye göre filtrele
    foreach ($allQuestions as $question) {
        $questionBank = $question['bank'] ?? '';
        $questionCategory = $question['category'] ?? '';
        
        // Kategori formatı: "bank|category"
        $parts = explode('|', $category);
        if (count($parts) === 2) {
            $targetBank = $parts[0];
            $targetCategory = $parts[1];
            
            // Banka ve kategori eşleşmesi
            if ($questionBank === $targetBank && $questionCategory === $targetCategory) {
                $filteredQuestions[] = [
                    'id' => $question['id'] ?? uniqid(),
                    'question' => $question['question'] ?? '',
                    'type' => $question['type'] ?? 'mcq',
                    'difficulty' => $question['difficulty'] ?? '1',
                    'options' => $question['options'] ?? [],
                    'correct_answer' => $question['correct_answer'] ?? '',
                    'explanation' => $question['explanation'] ?? ''
                ];
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'questions' => $filteredQuestions,
        'count' => count($filteredQuestions)
    ]);
    
} catch (Exception $e) {
    error_log("Error loading questions: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error loading questions: ' . $e->getMessage()
    ]);
}
?>
