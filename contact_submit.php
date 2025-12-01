<?php
// contact_submit.php - İletişim formu işlemcisi

require_once __DIR__ . '/config.php';

function redirect_with($params) {
	$qs = http_build_query($params);
	header('Location: contact.php?' . $qs);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	redirect_with(['error' => '1']);
}

$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';

// Basit doğrulama
$errors = [];
if ($name === '') { $errors[] = 'name'; }
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'email'; }
if ($subject === '') { $errors[] = 'subject'; }
if ($message === '') { $errors[] = 'message'; }

if (!empty($errors)) {
	redirect_with(['error' => '1']);
}

// Mesajı kaydet (JSON log)
$payload = [
	'name' => $name,
	'email' => $email,
	'subject' => $subject,
	'message' => $message,
	'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
	'ua' => $_SERVER['HTTP_USER_AGENT'] ?? '',
	'created_at' => date('c'),
];

$logDir = __DIR__ . '/data';
if (!is_dir($logDir)) {
	@mkdir($logDir, 0755, true);
}
$logFile = $logDir . '/contact_messages.json';

try {
	$existing = [];
	if (file_exists($logFile)) {
		$json = file_get_contents($logFile);
		$existing = json_decode($json, true) ?: [];
	}
	$existing[] = $payload;
	file_put_contents($logFile, json_encode($existing, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
} catch (Throwable $e) {
	// Yine de devam et, mail dene
}

// Mail gönder (sunucunuz mail() destekliyorsa)
$to = 'info@islamfederasyonu.at';
$subjectLine = 'İletişim Formu: ' . $subject;
$body = "Ad Soyad: {$name}\nE-posta: {$email}\nKonu: {$subject}\nIP: " . ($payload['ip']) . "\nUA: " . ($payload['ua']) . "\n---\nMesaj:\n{$message}\n";
$headers = 'From: no-reply@' . ($_SERVER['HTTP_HOST'] ?? 'site.local');

try {
	@mail($to, $subjectLine, $body, $headers);
} catch (Throwable $e) {
	// Sessiz geç
}

redirect_with(['success' => '1']);


