<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}
require_once 'QuestionLoader.php';

$bank = $_GET['bank'] ?? '';
$category = $_GET['category'] ?? '';
$count = $_GET['count'] ?? 10;

if (empty($bank) || empty($category)) {
    die(json_encode(['error' => 'Missing bank or category']));
}

$loader = new QuestionLoader();
$loader->loadQuestions();

$questions = $loader->getFilteredQuestions(['bank' => $bank, 'category' => $category]);
shuffle($questions);
$selected = array_slice($questions, 0, (int)$count);

// Mobile formatına normalize et (main.js'deki formata uyum sağla)
$mobileQuestions = array_map(function($q) {
    $rawAnswer = $q['answer'][0] ?? 'A';
    $correctIndex = 0;
    
    if (is_numeric($rawAnswer)) {
        $correctIndex = (int)$rawAnswer;
    } else {
        $map = ['A' => 0, 'B' => 1, 'C' => 2, 'D' => 3, 'E' => 4];
        $correctIndex = $map[strtoupper($rawAnswer)] ?? 0;
    }

    return [
        'id' => $q['id'],
        'text' => $q['question'],
        'options' => $q['options'],
        'correct' => $correctIndex
    ];
}, $selected);

echo json_encode($mobileQuestions);
