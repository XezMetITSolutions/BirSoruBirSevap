<?php
require_once 'config.php';
require_once 'QuestionLoader.php';

session_start();
$loader = new QuestionLoader();
$loader->loadQuestions();

$banks = $_SESSION['banks'] ?? [];
echo "Bank Count: " . count($banks) . "\n";
foreach($banks as $i => $b) {
    echo "ID: $i, Name: $b\n";
}

if (in_array('İslami İlimler', $banks)) {
    echo "İslami İlimler is in the list.\n";
} else {
    echo "İslami İlimler is NOT in the list.\n";
}

$errors = $_SESSION['question_errors'] ?? [];
echo "Errors: " . count($errors) . "\n";
foreach($errors as $e) {
    echo "- $e\n";
}
?>
