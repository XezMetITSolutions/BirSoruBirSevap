<?php
/**
 * Eksik Rolleri Düzelt
 * Rol bilgisi olmayan kullanıcılara varsayılan rol ata
 */

require_once 'auth.php';

$auth = Auth::getInstance();
$allUsers = $auth->getAllUsers();

$fixed = 0;
$errors = [];

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <title>Rol Düzeltme</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; padding: 10px; margin: 5px 0; border-radius: 6px; }
        .error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 10px; margin: 5px 0; border-radius: 6px; }
        .info { background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; padding: 14px; margin: 12px 0; border-radius: 8px; }
        h1 { color: white; }
    </style>
</head>
<body>
    <h1>🔧 Eksik Rolleri Düzelt</h1>";

foreach ($allUsers as $username => $userData) {
    // Rol boş veya eksikse
    if (empty($userData['role']) || trim($userData['role']) === '') {
        $defaultRole = 'student'; // Varsayılan rol
        
        try {
            // Kullanıcıyı güncelle
            $result = $auth->saveUser(
                $username,
                $userData['password'] ?? 'iqra2025#',
                $defaultRole,
                $userData['full_name'] ?? $userData['name'] ?? $username,
                $userData['institution'] ?? $userData['branch'] ?? '',
                $userData['class_section'] ?? '',
                $userData['email'] ?? '',
                $userData['phone'] ?? '',
                $userData['region'] ?? 'Arlberg'
            );
            
            if ($result) {
                echo "<div class='success'>✅ <code>$username</code> → rol: <strong>$defaultRole</strong></div>";
                $fixed++;
            } else {
                throw new Exception("Güncelleme başarısız");
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ <code>$username</code>: " . $e->getMessage() . "</div>";
            $errors[] = $username;
        }
    }
}

echo "<div class='info' style='margin-top: 30px;'>
    <strong>📊 Özet:</strong><br>
    Düzeltilen: <strong>$fixed</strong><br>
    Hata: <strong>" . count($errors) . "</strong>
</div>";

if ($fixed == 0) {
    echo "<div class='info'>✅ Tüm kullanıcıların rolü zaten tanımlı!</div>";
}

echo "<p style='margin-top: 20px;'>
    <a href='debug_roles.php' style='color: #3b82f6;'>→ Rolleri Kontrol Et</a> |
    <a href='admin/users.php' style='color: #3b82f6;'>→ Kullanıcı Yönetimi</a>
</p>
</body>
</html>";
?>
