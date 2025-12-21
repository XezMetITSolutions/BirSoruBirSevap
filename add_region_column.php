<?php
/**
 * Veritabanına 'region' Sütunu Ekleme
 * Bu script users tablosuna region sütununu ekler
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Veritabanı Güncelleme</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 40px;
            max-width: 800px;
            margin: 0 auto;
        }
        .container {
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 30px;
        }
        h1 {
            color: white;
            margin-bottom: 20px;
        }
        .success {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            padding: 16px;
            border-radius: 8px;
            margin: 15px 0;
            color: #10b981;
        }
        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 16px;
            border-radius: 8px;
            margin: 15px 0;
            color: #ef4444;
        }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            padding: 16px;
            border-radius: 8px;
            margin: 15px 0;
            color: #3b82f6;
        }
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
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔧 Veritabanı Güncelleme</h1>";

try {
    // Direkt MySQLi bağlantısı oluştur
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Bağlantı hatası: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    echo "<div class='info'>
        ✅ Veritabanı bağlantısı başarılı: <code>" . DB_NAME . "</code>
    </div>";
    
    // Önce sütunun var olup olmadığını kontrol et
    $checkQuery = "SHOW COLUMNS FROM users LIKE 'region'";
    $result = $conn->query($checkQuery);
    
    if ($result && $result->num_rows > 0) {
        echo "<div class='info'>
            ℹ️ <strong>Bilgi:</strong> <code>region</code> sütunu zaten mevcut.
        </div>";
    } else {
        echo "<div class='info'>
            📝 <code>users</code> tablosuna <code>region</code> sütunu ekleniyor...
        </div>";
        
        // Region sütununu ekle
        $alterQuery = "ALTER TABLE users ADD COLUMN region VARCHAR(100) DEFAULT 'Arlberg' AFTER institution";
        
        if ($conn->query($alterQuery)) {
            echo "<div class='success'>
                ✅ <strong>Başarılı!</strong> <code>region</code> sütunu başarıyla eklendi.<br>
                <small>Varsayılan değer: 'Arlberg'</small>
            </div>";
            
            // Mevcut NULL değerleri Arlberg olarak güncelle
            $updateQuery = "UPDATE users SET region = 'Arlberg' WHERE region IS NULL OR region = ''";
            $conn->query($updateQuery);
            
            echo "<div class='success'>
                ✅ Mevcut kullanıcıların bölge bilgileri 'Arlberg' olarak güncellendi.
            </div>";
        } else {
            throw new Exception("Sütun eklenirken hata: " . $conn->error);
        }
    }
    
    // Tablo yapısını göster
    echo "<div class='info' style='margin-top: 30px;'>
        <strong>📊 Mevcut Tablo Yapısı:</strong><br><br>";
    
    $columnsQuery = "SHOW COLUMNS FROM users";
    $columns = $conn->query($columnsQuery);
    
    if ($columns) {
        echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>
            <tr style='background: rgba(0,0,0,0.3);'>
                <th style='padding: 8px; text-align: left; border: 1px solid rgba(255,255,255,0.1);'>Alan</th>
                <th style='padding: 8px; text-align: left; border: 1px solid rgba(255,255,255,0.1);'>Tip</th>
                <th style='padding: 8px; text-align: left; border: 1px solid rgba(255,255,255,0.1);'>Varsayılan</th>
            </tr>";
        
        while ($col = $columns->fetch_assoc()) {
            $highlight = $col['Field'] === 'region' ? "style='background: rgba(16, 185, 129, 0.1);'" : "";
            echo "<tr {$highlight}>
                <td style='padding: 8px; border: 1px solid rgba(255,255,255,0.05);'><code>{$col['Field']}</code></td>
                <td style='padding: 8px; border: 1px solid rgba(255,255,255,0.05);'>{$col['Type']}</td>
                <td style='padding: 8px; border: 1px solid rgba(255,255,255,0.05);'>" . ($col['Default'] ?? 'NULL') . "</td>
            </tr>";
        }
        
        echo "</table>";
    }
    
    echo "</div>";
    
    echo "<div style='margin-top: 30px;'>
        <a href='update_user_regions.php' class='btn'>→ Kullanıcı Bölgelerini Güncelle</a>
        <a href='admin/users.php' class='btn' style='background: #3b82f6; margin-left: 10px;'>→ Kullanıcı Yönetimi</a>
    </div>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>
        ❌ <strong>Hata:</strong> " . htmlspecialchars($e->getMessage()) . "
    </div>";
    
    echo "<p style='margin-top: 20px; color: #94a3b8;'>
        Muhtemelen sistem JSON dosyası tabanlı kullanıcı yönetimi kullanıyor.<br>
        Bu durumda veritabanına region sütunu eklemek gerekmeyebilir.
    </p>";
    
    echo "<p style='margin-top: 20px;'>
        <a href='admin/dashboard.php' style='color: #3b82f6;'>← Dashboard'a Dön</a>
    </p>";
}

echo "</div></body></html>";
?>
