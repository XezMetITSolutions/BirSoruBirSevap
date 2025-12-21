<?php
/**
 * SuperAdmin Debug
 */

require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance();

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <title>SuperAdmin Debug</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 40px; max-width: 900px; margin: 0 auto; }
        .info { background: rgba(59, 130, 246, 0.2); border: 1px solid #3b82f6; padding: 14px; margin: 10px 0; border-radius: 8px; }
        .success { background: rgba(16, 185, 129, 0.2); border: 1px solid #10b981; padding: 14px; margin: 10px 0; border-radius: 8px; }
        .error { background: rgba(239, 68, 68, 0.2); border: 1px solid #ef4444; padding: 14px; margin: 10px 0; border-radius: 8px; }
        pre { background: rgba(0,0,0,0.3); padding: 10px; border-radius: 6px; overflow-x: auto; white-space: pre-wrap; }
        h1 { color: white; }
        input, button { padding: 10px; margin: 5px; border-radius: 6px; }
        button { background: #068567; color: white; border: none; cursor: pointer; font-weight: 600; }
    </style>
</head>
<body>
    <h1>🔍 SuperAdmin Debug</h1>";

try {
    $conn = $db->getConnection();
    
    // SuperAdmin kullanıcısını bul
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = 'superadmin' OR role = 'superadmin'");
    $stmt->execute();
    $superadmins = $stmt->fetchAll();
    
    if (count($superadmins) == 0) {
        echo "<div class='error'>❌ SuperAdmin kullanıcısı bulunamadı!</div>";
        
        // Yeni superadmin oluştur
        if (isset($_POST['create_superadmin'])) {
            $newPass = 'admin123';
            $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, role, full_name, region, created_at) 
                                    VALUES ('superadmin', ?, 'superadmin', 'Super Admin', 'Arlberg', NOW())
                                    ON DUPLICATE KEY UPDATE password = ?, role = 'superadmin'");
            
            if ($stmt->execute([$hashedPass, $hashedPass])) {
                echo "<div class='success'>✅ SuperAdmin oluşturuldu!<br>Kullanıcı: <strong>superadmin</strong><br>Şifre: <strong>$newPass</strong></div>";
                header("Refresh:2");
            }
        } else {
            echo "<form method='POST'>
                <button type='submit' name='create_superadmin'>SuperAdmin Oluştur</button>
            </form>";
        }
    } else {
        foreach ($superadmins as $admin) {
            echo "<div class='info'>
                <strong>📊 SuperAdmin Bilgileri:</strong><br>
                Username: <strong>" . htmlspecialchars($admin['username']) . "</strong><br>
                Role: <strong>" . htmlspecialchars($admin['role']) . "</strong><br>
                Full Name: <strong>" . htmlspecialchars($admin['full_name'] ?? 'N/A') . "</strong><br>
                Password Hash: <code>" . substr($admin['password'], 0, 30) . "...</code>
            </div>";
            
            // Şifre testi
            if (isset($_POST['test_password'])) {
                $testPass = $_POST['password'];
                
                if (password_verify($testPass, $admin['password'])) {
                    echo "<div class='success'>✅ Şifre DOĞRU! Giriş yapabilmeniz gerek.</div>";
                    
                    // Login işlemini simüle et
                    echo "<div class='info'>
                        <strong>🔍 Login Test:</strong><br>
                        Hash algoritması: " . password_get_info($admin['password'])['algoName'] . "<br>
                        Options: <pre>" . print_r(password_get_info($admin['password']), true) . "</pre>
                    </div>";
                } else {
                    echo "<div class='error'>❌ Şifre YANLIŞ!</div>";
                }
            }
            
            // Şifre sıfırlama
            if (isset($_POST['reset_password'])) {
                $newPass = 'admin123';
                $hashedPass = password_hash($newPass, PASSWORD_DEFAULT);
                
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE username = ?");
                if ($stmt->execute([$hashedPass, $admin['username']])) {
                    echo "<div class='success'>✅ Şifre sıfırlandı!<br>Yeni Şifre: <strong>$newPass</strong></div>";
                    header("Refresh:2");
                }
            }
        }
        
        echo "<form method='POST' style='margin-top: 20px;'>
            <h3 style='color: white;'>Şifre Testi:</h3>
            <input type='text' name='password' placeholder='Şifreyi gir' required style='background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); color: white;'>
            <button type='submit' name='test_password'>Test Et</button>
        </form>";
        
        echo "<form method='POST' style='margin-top: 20px;'>
            <button type='submit' name='reset_password' style='background: #ef4444;'>Şifreyi Sıfırla (admin123)</button>
        </form>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ HATA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<p style='margin-top: 30px;'><a href='login.php' style='color: #3b82f6;'>← Login Sayfası</a></p>";
echo "</body></html>";
?>
