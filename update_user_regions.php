<?php
/**
 * Tüm Kullanıcıları Arlberg Bölgesine Atama Script
 * Bu script tüm kullanıcıların region alanını "Arlberg" olarak günceller
 */

require_once 'auth.php';
require_once 'config.php';

$auth = Auth::getInstance();

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Bölge Güncelleme</title>
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
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
            color: #10b981;
        }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
            color: #3b82f6;
        }
        .warning {
            background: rgba(245, 158, 11, 0.2);
            border: 1px solid rgba(245, 158, 11, 0.4);
            padding: 12px;
            border-radius: 8px;
            margin: 10px 0;
            color: #f59e0b;
        }
        .stats {
            margin-top: 20px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.3);
            border-radius: 12px;
        }
        .stat-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }
        .stat-item:last-child {
            border-bottom: none;
        }
        .btn {
            background: #068567;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            margin-top: 20px;
        }
        .btn:hover {
            background: #055a4a;
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🌍 Kullanıcı Bölge Güncelleme</h1>";

// GET parametresi ile onay kontrolü
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'yes') {
    // Önizleme modu
    $allUsers = $auth->getAllUsers();
    $totalUsers = count($allUsers);
    $usersWithoutRegion = 0;
    $usersWithRegion = 0;
    
    foreach ($allUsers as $username => $userData) {
        if (empty($userData['region']) || $userData['region'] === '') {
            $usersWithoutRegion++;
        } else {
            $usersWithRegion++;
        }
    }
    
    echo "<div class='info'>
        <strong>📊 Mevcut Durum:</strong><br>
        Bu script tüm kullanıcıların bölge (region) alanını <strong>'Arlberg'</strong> olarak güncelleyecektir.
    </div>";
    
    echo "<div class='stats'>
        <div class='stat-item'><strong>Toplam Kullanıcı:</strong> {$totalUsers}</div>
        <div class='stat-item'><strong>Bölgesi Olmayan:</strong> {$usersWithoutRegion}</div>
        <div class='stat-item'><strong>Bölgesi Olan:</strong> {$usersWithRegion}</div>
    </div>";
    
    echo "<div class='warning' style='margin-top: 20px;'>
        ⚠️ <strong>Uyarı:</strong> Bu işlem geri alınamaz! Tüm kullanıcıların mevcut bölge bilgileri 'Arlberg' ile değiştirilecek.
    </div>";
    
    echo "<form method='GET'>
        <input type='hidden' name='confirm' value='yes'>
        <button type='submit' class='btn'>✓ Onayla ve Güncelle</button>
    </form>";
    
    echo "<p style='margin-top: 20px; color: #94a3b8; font-size: 14px;'>
        <a href='admin/dashboard.php' style='color: #3b82f6;'>← Geri Dön</a>
    </p>";
    
} else {
    // Güncelleme işlemi başlat
    echo "<div class='info'>⏳ Güncelleme başlatılıyor...</div>";
    
    $allUsers = $auth->getAllUsers();
    $updatedCount = 0;
    $errorCount = 0;
    $errors = [];
    
    foreach ($allUsers as $username => $userData) {
        try {
            // Mevcut kullanıcı bilgilerini koru, sadece region'u güncelle
            $result = $auth->saveUser(
                $username,
                $userData['password'] ?? 'iqra2025#', // Mevcut şifre
                $userData['role'] ?? 'student',
                $userData['full_name'] ?? $userData['name'] ?? $username,
                $userData['institution'] ?? $userData['branch'] ?? '',
                $userData['class_section'] ?? '',
                $userData['email'] ?? '',
                $userData['phone'] ?? '',
                'Arlberg' // Bölge her zaman Arlberg olarak güncelleniyor
            );
            
            if ($result) {
                $updatedCount++;
                echo "<div class='success' style='padding: 6px 12px; font-size: 13px;'>
                    ✓ {$username} → Arlberg
                </div>";
            } else {
                $errorCount++;
                $errors[] = $username;
            }
        } catch (Exception $e) {
            $errorCount++;
            $errors[] = $username . " (Hata: " . $e->getMessage() . ")";
        }
    }
    
    echo "<div class='stats' style='margin-top: 30px;'>
        <h3 style='margin-top: 0; color: white;'>📊 Güncelleme Özeti</h3>
        <div class='stat-item'><strong>Başarılı:</strong> {$updatedCount}</div>
        <div class='stat-item'><strong>Başarısız:</strong> {$errorCount}</div>
    </div>";
    
    if ($errorCount > 0) {
        echo "<div class='warning' style='margin-top: 20px;'>
            <strong>Hatalar:</strong><br>";
        foreach ($errors as $error) {
            echo "• {$error}<br>";
        }
        echo "</div>";
    }
    
    if ($updatedCount > 0) {
        echo "<div class='success' style='margin-top: 20px;'>
            ✅ <strong>Tamamlandı!</strong> {$updatedCount} kullanıcının bölgesi 'Arlberg' olarak güncellendi.
        </div>";
    }
    
    echo "<p style='margin-top: 30px;'>
        <a href='admin/users.php' class='btn' style='text-decoration: none; display: inline-block;'>
            → Kullanıcı Yönetimine Git
        </a>
    </p>";
}

echo "</div></body></html>";
?>
