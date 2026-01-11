<?php
/**
 * Role ENUM'unu Güncelle
 * Veritabanındaki role sütununu VARCHAR'a çevir (ENUM kısıtlamasını kaldır)
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <title>Role Fix</title>
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #e2e8f0; padding: 40px; max-width: 800px; margin: 0 auto; }
        .success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; padding: 14px; margin: 10px 0; border-radius: 8px; }
        .error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 14px; margin: 10px 0; border-radius: 8px; }
        .info { background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; padding: 14px; margin: 10px 0; border-radius: 8px; }
        h1 { color: white; }
        code { background: rgba(0,0,0,0.3); padding: 2px 6px; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>🔧 Role Sütunu Düzeltme</h1>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Bağlantı hatası: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    echo "<div class='info'>✅ Veritabanı bağlantısı başarılı</div>";
    
    // Mevcut role sütunu tipini kontrol et
    $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
    if ($result && $row = $result->fetch_assoc()) {
        echo "<div class='info'>📊 Mevcut role tipi: <code>" . $row['Type'] . "</code></div>";
    }
    
    // Role sütununu VARCHAR'a çevir (tüm kısıtlamaları kaldır)
    echo "<div class='info'>🔄 Role sütunu VARCHAR'a dönüştürülüyor...</div>";
    
    $alterQuery = "ALTER TABLE users MODIFY COLUMN role VARCHAR(50) NOT NULL DEFAULT 'student'";
    
    if ($conn->query($alterQuery)) {
        echo "<div class='success'>✅ Role sütunu başarıyla VARCHAR(50) olarak güncellendi!</div>";
        
        // Yeni tipi göster
        $result = $conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($result && $row = $result->fetch_assoc()) {
            echo "<div class='success'>✅ Yeni tip: <code>" . $row['Type'] . "</code></div>";
        }
        
        echo "<div class='info'>
            <strong>✅ Artık tüm roller sorunsuz kaydedilebilir:</strong><br>
            • student<br>
            • teacher<br>
            • branch_leader<br>
            • region_leader<br>
            • admin<br>
            • superadmin
        </div>";
        
    } else {
        throw new Exception("Güncelleme hatası: " . $conn->error);
    }
    
    $conn->close();
    
    echo "<p style='margin-top: 30px;'>
        <a href='test_db_save.php' style='color: #3b82f6;'>→ Test Et</a> |
        <a href='admin/users.php' style='color: #3b82f6;'>→ Kullanıcı Yönetimi</a>
    </p>";
    
} catch (Exception $e) {
    echo "<div class='error'>❌ HATA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
