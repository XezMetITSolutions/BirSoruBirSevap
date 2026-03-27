<?php
header('Content-Type: application/json');
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
    return [
        'id' => $q['id'],
        'text' => $q['question'],
        'options' => $q['options'],
        'correct' => $q['answer'][0] ?? 0 // Genelde ilk cevap (A) bekleniyor (A=0)
    ];
}, $selected);

echo json_encode($mobileQuestions);
