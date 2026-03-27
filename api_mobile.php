<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}
require_once 'QuestionLoader.php';

$loader = new QuestionLoader();
$loader->loadQuestions();

$banks = $loader->getBanks();
$categories = $loader->getCategories();

// Kategorileri temizle (UI ile uyumlu olması için)
$cleanCategories = [];
foreach ($categories as $bank => $cats) {
    $cleanCategories[$bank] = array_map(function($cat) {
        $clean = preg_replace('/_json\.json$|\.json$|_questions\.json$|_sorulari\.json$/', '', $cat);
        $clean = preg_replace('/_(\d+)_(\d+)_json$/', '', $clean);
        $clean = str_replace('_', ' ', $clean);
        return ucwords($clean);
    }, $cats);
}

// Banka listesini mobile-app formatına uygun döndür
$mobileBanks = array_map(function($bank) use ($cleanCategories) {
    $icons = [
        'Temel Bilgiler 1' => '📖',
        'Temel Bilgiler 2' => '📚',
        'Temel Bilgiler 3' => '🖋️',
        'İslami İlimler' => '🕌'
    ];
    return [
        'id' => $bank,
        'title' => $bank,
        'icon' => $icons[$bank] ?? '✨',
        'count' => count($cleanCategories[$bank] ?? [])
    ];
}, $banks);

echo json_encode([
    'banks' => $mobileBanks,
    'categories' => $cleanCategories
]);
