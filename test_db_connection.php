<?php
/**
 * Veritabanı Bağlantı Testi
 * Bu dosyayı tarayıcıda açın: http://your-site.com/test_db_connection.php
 */

require_once 'config.php';
require_once 'database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Bağlantı Testi</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            margin: 10px 0;
        }
        h1 {
            color: #333;
        }
    </style>
</head>
<body>
    <h1>🔍 Veritabanı Bağlantı Testi</h1>
    
    <?php
    try {
        echo "<div class='info'>";
        echo "<strong>Test Ediliyor:</strong><br>";
        echo "Host: " . DB_HOST . "<br>";
        echo "Database: " . DB_NAME . "<br>";
        echo "User: " . DB_USER . "<br>";
        echo "</div>";
        
        // Database bağlantısı
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        echo "<div class='success'>";
        echo "✅ <strong>Bağlantı Başarılı!</strong><br>";
        echo "Veritabanına başarıyla bağlanıldı.";
        echo "</div>";
        
        // Tabloları kontrol et
        echo "<div class='info'>";
        echo "<strong>📊 Veritabanı Tabloları:</strong><br>";
        
        $stmt = $conn->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (count($tables) > 0) {
            echo "<ul>";
            foreach ($tables as $table) {
                echo "<li>$table</li>";
            }
            echo "</ul>";
        } else {
            echo "⚠️ Henüz tablo bulunamadı. SQL dosyasını import edin.";
        }
        echo "</div>";
        
        // Users tablosunu kontrol et
        if (in_array('users', $tables)) {
            $stmt = $conn->query("SELECT COUNT(*) as count FROM users");
            $result = $stmt->fetch();
            
            echo "<div class='info'>";
            echo "<strong>👥 Kullanıcı Sayısı:</strong> " . $result['count'];
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div class='error'>";
        echo "❌ <strong>Bağlantı Hatası!</strong><br>";
        echo "Hata: " . $e->getMessage();
        echo "</div>";
        
        echo "<div class='info'>";
        echo "<strong>🔧 Çözüm:</strong><br>";
        echo "1. phpMyAdmin'e giriş yapın<br>";
        echo "2. SQL sekmesine gidin<br>";
        echo "3. database_structure.sql dosyasını import edin<br>";
        echo "4. .env dosyası oluşturun veya config.php'yi kontrol edin<br>";
        echo "</div>";
    }
    ?>
    
    <hr>
    
    <div style="text-align: center; margin-top: 30px;">
        <button onclick="location.reload()">🔄 Tekrar Test Et</button>
        <button onclick="window.location.href='index.php'">🏠 Ana Sayfaya Dön</button>
    </div>
</body>
</html>

