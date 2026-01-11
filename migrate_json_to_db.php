<?php
/**
 * JSON Kullanıcılarını Veritabanına Aktarma
 * data/users.json dosyasındaki tüm kullanıcıları veritabanına yükler
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>JSON → Database Migration</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 40px;
            max-width: 900px;
            margin: 0 auto;
        }
        .container {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
        }
        h1 { color: white; margin-bottom: 20px; }
        .success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            padding: 10px 14px;
            border-radius: 8px;
            margin: 8px 0;
            color: #10b981;
            font-size: 13px;
        }
        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 10px 14px;
            border-radius: 8px;
            margin: 8px 0;
            color: #ef4444;
            font-size: 13px;
        }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            padding: 14px;
            border-radius: 8px;
            margin: 12px 0;
            color: #3b82f6;
        }
        .warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.4);
            padding: 14px;
            border-radius: 8px;
            margin: 12px 0;
            color: #f59e0b;
        }
        .stats {
            background: rgba(0, 0, 0, 0.3);
            padding: 20px;
            border-radius: 12px;
            margin: 20px 0;
        }
        .stat-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .stat-item:last-child { border-bottom: none; }
        code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        .btn {
            background: #068567;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px 10px 0;
            font-weight: 600;
            cursor: pointer;
        }
        .progress {
            background: rgba(255, 255, 255, 0.1);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin: 15px 0;
        }
        .progress-bar {
            background: linear-gradient(90deg, #068567, #0a9e78);
            height: 100%;
            transition: width 0.3s;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔄 JSON → Database Migration</h1>";

if (!isset($_GET['confirm'])) {
    // Önizleme Modu
    $jsonFile = 'data/users.json';
    
    if (!file_exists($jsonFile)) {
        echo "<div class='error'>❌ JSON dosyası bulunamadı: <code>$jsonFile</code></div>";
        echo "<p><a href='admin/dashboard.php' style='color: #3b82f6;'>← Geri Dön</a></p>";
        echo "</div></body></html>";
        exit;
    }
    
    $jsonData = file_get_contents($jsonFile);
    $users = json_decode($jsonData, true);
    
    if (!$users) {
        echo "<div class='error'>❌ JSON dosyası okunamadı veya geçersiz</div>";
        echo "</div></body></html>";
        exit;
    }
    
    $totalUsers = count($users);
    
    echo "<div class='info'>
        📂 JSON dosyası bulundu: <code>$jsonFile</code><br>
        👥 Toplam kullanıcı sayısı: <strong>$totalUsers</strong>
    </div>";
    
    // Rol dağılımı
    $roles = [];
    foreach ($users as $userData) {
        $role = $userData['role'] ?? 'unknown';
        $roles[$role] = ($roles[$role] ?? 0) + 1;
    }
    
    echo "<div class='stats'>
        <h3 style='margin: 0 0 15px 0; color: white;'>📊 Kullanıcı Dağılımı</h3>";
    
    foreach ($roles as $role => $count) {
        echo "<div class='stat-item'><strong>" . ucfirst($role) . ":</strong> $count kullanıcı</div>";
    }
    
    echo "</div>";
    
    echo "<div class='warning'>
        ⚠️ <strong>Önemli Uyarı:</strong><br>
        • Bu işlem JSON dosyasındaki TÜM kullanıcıları veritabanına aktaracaktır.<br>
        • Mevcut veritabanındaki kullanıcılar üzerine yazılabilir (username'e göre).<br>
        • İşlem geri alınamaz!
    </div>";
    
    echo "<form method='GET'>
        <input type='hidden' name='confirm' value='yes'>
        <button type='submit' class='btn'>✓ Onayla ve Aktar</button>
        <a href='admin/dashboard.php' class='btn' style='background: #64748b; text-decoration: none;'>İptal</a>
    </form>";
    
} else {
    // Migration İşlemi
    try {
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        
        if ($conn->connect_error) {
            throw new Exception("Veritabanı bağlantı hatası: " . $conn->connect_error);
        }
        
        $conn->set_charset("utf8mb4");
        
        echo "<div class='info'>✅ Veritabanı bağlantısı başarılı</div>";
        
        // JSON dosyasını oku
        $jsonFile = 'data/users.json';
        $jsonData = file_get_contents($jsonFile);
        $users = json_decode($jsonData, true);
        
        if (!$users) {
            throw new Exception("JSON dosyası okunamadı");
        }
        
        echo "<div class='info'>📂 " . count($users) . " kullanıcı bulundu</div>";
        
        $successCount = 0;
        $errorCount = 0;
        $updatedCount = 0;
        $errors = [];
        
        $totalUsers = count($users);
        $processed = 0;
        
        foreach ($users as $username => $userData) {
            $processed++;
            
            try {
                // Kullanıcı verilerini hazırla
                $fullName = $userData['full_name'] ?? $userData['name'] ?? $username;
                $password = $userData['password'] ?? 'iqra2025#';
                $role = $userData['role'] ?? 'student';
                $institution = $userData['institution'] ?? $userData['branch'] ?? '';
                $branch = $userData['branch'] ?? $userData['institution'] ?? '';
                $region = $userData['region'] ?? 'Arlberg';
                $classSection = $userData['class_section'] ?? '';
                $email = $userData['email'] ?? '';
                $phone = $userData['phone'] ?? '';
                $createdAt = $userData['created_at'] ?? date('Y-m-d H:i:s');
                $lastLogin = $userData['last_login'] ?? null;
                
                // Kullanıcı var mı kontrol et
                $checkStmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
                $checkStmt->bind_param("s", $username);
                $checkStmt->execute();
                $exists = $checkStmt->get_result()->num_rows > 0;
                $checkStmt->close();
                
                if ($exists) {
                    // Güncelle
                    $stmt = $conn->prepare("UPDATE users SET 
                        password = ?, full_name = ?, role = ?, institution = ?, branch = ?, 
                        region = ?, class_section = ?, email = ?, phone = ?, last_login = ?
                        WHERE username = ?");
                    
                    $stmt->bind_param("sssssssssss", 
                        $password, $fullName, $role, $institution, $branch, 
                        $region, $classSection, $email, $phone, $lastLogin, $username
                    );
                    
                    if ($stmt->execute()) {
                        $updatedCount++;
                        echo "<div class='success'>♻️ Güncellendi: <code>$username</code></div>";
                    } else {
                        throw new Exception("Update hatası: " . $stmt->error);
                    }
                    
                } else {
                    // Yeni ekle
                    $stmt = $conn->prepare("INSERT INTO users 
                        (username, password, full_name, role, institution, branch, region, class_section, email, phone, created_at, last_login) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
                    $stmt->bind_param("ssssssssssss", 
                        $username, $password, $fullName, $role, $institution, $branch, 
                        $region, $classSection, $email, $phone, $createdAt, $lastLogin
                    );
                    
                    if ($stmt->execute()) {
                        $successCount++;
                        echo "<div class='success'>✅ Eklendi: <code>$username</code></div>";
                    } else {
                        throw new Exception("Insert hatası: " . $stmt->error);
                    }
                }
                
                $stmt->close();
                
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "$username: " . $e->getMessage();
                echo "<div class='error'>❌ Hata: <code>$username</code> - " . $e->getMessage() . "</div>";
            }
        }
        
        echo "<div class='progress'>
            <div class='progress-bar' style='width: 100%;'></div>
        </div>";
        
        echo "<div class='stats'>
            <h3 style='margin: 0 0 15px 0; color: white;'>📊 Migration Özeti</h3>
            <div class='stat-item'><strong>Toplam İşlenen:</strong> $totalUsers</div>
            <div class='stat-item'><strong>Yeni Eklenen:</strong> $successCount</div>
            <div class='stat-item'><strong>Güncellenen:</strong> $updatedCount</div>
            <div class='stat-item'><strong>Hata:</strong> $errorCount</div>
        </div>";
        
        if ($errorCount > 0) {
            echo "<div class='error'>
                <strong>❌ Hatalar:</strong><br>";
            foreach ($errors as $error) {
                echo "• " . htmlspecialchars($error) . "<br>";
            }
            echo "</div>";
        }
        
        if ($successCount + $updatedCount > 0) {
            echo "<div class='success'>
                ✅ <strong>Tamamlandı!</strong> " . ($successCount + $updatedCount) . " kullanıcı başarıyla veritabanına aktarıldı.
            </div>";
        }
        
        echo "<div style='margin-top: 30px;'>
            <a href='admin/users.php' class='btn'>→ Kullanıcı Yönetimi</a>
            <a href='admin/dashboard.php' class='btn' style='background: #3b82f6;'>→ Dashboard</a>
        </div>";
        
        $conn->close();
        
    } catch (Exception $e) {
        echo "<div class='error'>
            ❌ <strong>Fatal Hata:</strong> " . htmlspecialchars($e->getMessage()) . "
        </div>";
    }
}

echo "</div></body></html>";
?>
