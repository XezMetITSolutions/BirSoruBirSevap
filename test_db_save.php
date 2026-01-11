<?php
/**
 * DB Save Test - Rol kaydını test et
 */

require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance();

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <title>DB Save Test</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 40px; max-width: 1000px; margin: 0 auto; }
        .success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; padding: 10px; margin: 5px 0; border-radius: 6px; }
        .error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 10px; margin: 5px 0; border-radius: 6px; }
        .info { background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; padding: 10px; margin: 5px 0; border-radius: 6px; }
        pre { background: rgba(0,0,0,0.3); padding: 10px; border-radius: 6px; overflow-x: auto; }
    </style>
</head>
<body>
    <h1>🧪 Database Save Test</h1>";

// Test kullanıcısı oluştur
$testUsername = 'test_role_' . time();
$testRole = 'region_leader';

echo "<div class='info'>Test Kullanıcısı: <strong>$testUsername</strong><br>Rol: <strong>$testRole</strong></div>";

try {
    // Direkt saveUser çağır
    $result = $db->saveUser(
        $testUsername,
        'test123',
        $testRole,
        'Test Role User',
        'Test Institution',
        '9A',
        'test@test.com',
        '1234567890',
        'Arlberg'
    );
    
    if ($result) {
        echo "<div class='success'>✅ saveUser() başarılı</div>";
        
        // Şimdi veritabanından geri oku
        $conn = $db->getConnection();
        $stmt = $conn->prepare("SELECT username, role, full_name FROM users WHERE username = ?");
        $stmt->execute([$testUsername]);
        $user = $stmt->fetch();
        
        if ($user) {
            echo "<div class='success'>✅ Veritabanından okundu:</div>";
            echo "<pre>" . print_r($user, true) . "</pre>";
            
            if ($user['role'] === $testRole) {
                echo "<div class='success'>✅✅ ROL DOĞRU KAYDEDILDI!</div>";
            } else {
                echo "<div class='error'>❌ ROL YANLIŞ! Beklenen: <strong>$testRole</strong>, Bulunan: <strong>" . $user['role'] . "</strong></div>";
            }
        } else {
            echo "<div class='error'>❌ Kullanıcı veritabanında bulunamadı!</div>";
        }
        
        // Test kullanıcısını sil
        $stmt = $conn->prepare("DELETE FROM users WHERE username = ?");
        $stmt->execute([$testUsername]);
        echo "<div class='info'>🗑️ Test kullanıcısı silindi</div>";
        
    } else {
        echo "<div class='error'>❌ saveUser() başarısız!</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ HATA: " . $e->getMessage() . "</div>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

echo "<p style='margin-top: 30px;'><a href='admin/users.php' style='color: #3b82f6;'>← Geri Dön</a></p>";
echo "</body></html>";
?>
