<?php
require_once 'QuestionLoader.php';

$loader = new QuestionLoader();
$loader->loadFromFiles(); // Force load from files to see the file-based count
$questions = $loader->getQuestions();

echo "Total questions from files: " . count($questions) . "\n";
?>
