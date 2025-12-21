<?php
/**
 * Users Tablosu Tam Yapılandırma
 * Tüm gerekli sütunları ekler
 */

require_once 'config.php';

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Users Tablosu Yapılandırma</title>
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
            padding: 14px;
            border-radius: 8px;
            margin: 12px 0;
            color: #10b981;
        }
        .error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            padding: 14px;
            border-radius: 8px;
            margin: 12px 0;
            color: #ef4444;
        }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            padding: 14px;
            border-radius: 8px;
            margin: 12px 0;
            color: #3b82f6;
        }
        code {
            background: rgba(0, 0, 0, 0.3);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border: 1px solid rgba(255,255,255,0.1);
        }
        th {
            background: rgba(0,0,0,0.3);
            font-weight: 600;
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
        }
    </style>
</head>
<body>
    <div class='container'>";

echo "<h1>🔧 Users Tablosu Yapılandırma</h1>";

try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if ($conn->connect_error) {
        throw new Exception("Bağlantı hatası: " . $conn->connect_error);
    }
    
    $conn->set_charset("utf8mb4");
    
    echo "<div class='info'>✅ Veritabanı bağlantısı başarılı</div>";
    
    // Mevcut sütunları kontrol et
    $existingColumns = [];
    $result = $conn->query("SHOW COLUMNS FROM users");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $existingColumns[] = $row['Field'];
        }
    }
    
    echo "<div class='info'>📋 Mevcut sütunlar: " . implode(', ', $existingColumns) . "</div>";
    
    // Gerekli sütunlar ve özellikleri
    $requiredColumns = [
        'id' => "INT AUTO_INCREMENT PRIMARY KEY",
        'username' => "VARCHAR(100) NOT NULL UNIQUE",
        'password' => "VARCHAR(255) NOT NULL",
        'full_name' => "VARCHAR(200)",
        'role' => "VARCHAR(50) DEFAULT 'student'",
        'institution' => "VARCHAR(200)",
        'branch' => "VARCHAR(200)",
        'region' => "VARCHAR(100) DEFAULT 'Arlberg'",
        'class_section' => "VARCHAR(50)",
        'email' => "VARCHAR(200)",
        'phone' => "VARCHAR(50)",
        'created_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP",
        'last_login' => "TIMESTAMP NULL",
        'updated_at' => "TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
    ];
    
    $addedColumns = [];
    $skippedColumns = [];
    
    foreach ($requiredColumns as $column => $definition) {
        if (in_array($column, $existingColumns)) {
            $skippedColumns[] = $column;
            continue;
        }
        
        try {
            $alterQuery = "ALTER TABLE users ADD COLUMN $column $definition";
            if ($conn->query($alterQuery)) {
                $addedColumns[] = $column;
                echo "<div class='success'>✅ Sütun eklendi: <code>$column</code></div>";
            } else {
                throw new Exception($conn->error);
            }
        } catch (Exception $e) {
            echo "<div class='error'>❌ Sütun eklenemedi: <code>$column</code> - " . $e->getMessage() . "</div>";
        }
    }
    
    if (count($addedColumns) > 0) {
        echo "<div class='success'>
            <strong>✅ Toplam " . count($addedColumns) . " sütun başarıyla eklendi!</strong>
        </div>";
    }
    
    if (count($skippedColumns) > 0) {
        echo "<div class='info'>
            ℹ️ Zaten mevcut: " . implode(', ', $skippedColumns) . "
        </div>";
    }
    
    // Güncel tablo yapısını göster
    echo "<h2 style='margin-top: 30px; color: white;'>📊 Güncel Tablo Yapısı</h2>";
    
    $columns = $conn->query("SHOW COLUMNS FROM users");
    
    if ($columns) {
        echo "<table>
            <tr>
                <th>Sütun Adı</th>
                <th>Veri Tipi</th>
                <th>Null</th>
                <th>Varsayılan</th>
                <th>Extra</th>
            </tr>";
        
        while ($col = $columns->fetch_assoc()) {
            $isNew = in_array($col['Field'], $addedColumns);
            $style = $isNew ? "style='background: rgba(16, 185, 129, 0.1);'" : "";
            echo "<tr {$style}>
                <td><code>{$col['Field']}</code></td>
                <td>{$col['Type']}</td>
                <td>{$col['Null']}</td>
                <td>" . ($col['Default'] ?? 'NULL') . "</td>
                <td>{$col['Extra']}</td>
            </tr>";
        }
        
        echo "</table>";
    }
    
    echo "<div style='margin-top: 30px;'>
        <a href='migrate_json_to_db.php' class='btn'>→ JSON Kullanıcılarını Veritabanına Aktar</a>
        <a href='admin/users.php' class='btn' style='background: #3b82f6;'>→ Kullanıcı Yönetimi</a>
    </div>";
    
    $conn->close();
    
} catch (Exception $e) {
    echo "<div class='error'>
        ❌ <strong>Hata:</strong> " . htmlspecialchars($e->getMessage()) . "
    </div>";
}

echo "</div></body></html>";
?>
