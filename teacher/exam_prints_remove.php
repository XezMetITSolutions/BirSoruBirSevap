<?php
require_once '../auth.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('teacher')) { http_response_code(403); exit; }

$file = $_POST['file'] ?? '';
if ($file === '') { http_response_code(400); exit; }

$abs = realpath(__DIR__ . '/../' . ltrim($file, '/'));
$base = realpath(__DIR__ . '/..');
if (!$abs || strpos($abs, $base) !== 0) { http_response_code(400); exit; }

// Dosyayı sil
@unlink($abs);

// Metadan çıkar
$metaPath = __DIR__ . '/../data/exam_prints.json';
$metaAll = file_exists($metaPath) ? (json_decode(file_get_contents($metaPath), true) ?: []) : [];
$metaAll = array_values(array_filter($metaAll, function($m) use ($file){ return ($m['file'] ?? '') !== $file; }));
@file_put_contents($metaPath, json_encode($metaAll, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo 'OK';
?>


