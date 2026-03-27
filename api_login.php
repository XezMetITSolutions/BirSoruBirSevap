<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once 'config.php';
require_once 'database.php';
require_once 'auth.php';

$input = json_decode(file_get_contents('php_input'), true);
$username = $input['username'] ?? '';
$password = $input['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Kullanıcı adı ve şifre gereklidir.']);
    exit;
}

$db = Database::getInstance();
$user = $db->getUserByUsername($username);

if ($user && password_verify($password, $user['password'])) {
    // Giriş başarılı
    $db->updateLastLogin($username);
    
    // Hassas bilgileri (şifre gibi) temizleyip kullanıcıyı dön
    unset($user['password']);
    
    echo json_encode([
        'success' => true,
        'user' => [
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role'],
            'branch' => $user['branch'],
            'class_section' => $user['class_section'],
            'region' => $user['region'] ?? ''
        ]
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Hatalı kullanıcı adı veya şifre.']);
}
