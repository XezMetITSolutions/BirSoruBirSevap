<?php
/**
 * Debug - Veritabanı Kontrol
 */

require_once 'database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "<h2>Veritabanı Kontrol</h2>";

// 1. Practice results tablosu var mı?
try {
    $sql = "SHOW TABLES LIKE 'practice_results'";
    $stmt = $conn->query($sql);
    $exists = $stmt->rowCount() > 0;
    echo "<p><strong>practice_results tablosu:</strong> " . ($exists ? "✅ Var" : "❌ Yok") . "</p>";
} catch (Exception $e) {
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
}

// 2. Tablo yapısını göster
try {
    $sql = "SHOW COLUMNS FROM practice_results";
    $stmt = $conn->query($sql);
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<h3>Tablo Yapısı:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Kolon</th><th>Tip</th><th>Null</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($col['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
}

// 3. Toplam kayıt sayısı
try {
    $sql = "SELECT COUNT(*) as total FROM practice_results";
    $stmt = $conn->query($sql);
    $total = $stmt->fetch()['total'];
    echo "<h3>Toplam Kayıt: $total</h3>";
} catch (Exception $e) {
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
}

// 4. burca.met1 kullanıcısının kayıtları
try {
    $sql = "SELECT * FROM practice_results WHERE username = 'burca.met1' ORDER BY created_at DESC LIMIT 5";
    $stmt = $conn->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>burca.met1 Kullanıcısının Son 5 Kaydı:</h3>";
    if (empty($results)) {
        echo "<p style='color: red;'>❌ Hiç kayıt bulunamadı!</p>";
    } else {
        echo "<p style='color: green;'>✅ " . count($results) . " kayıt bulundu</p>";
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Tarih</th><th>Toplam Soru</th><th>Doğru</th><th>Yanlış</th><th>Puan</th><th>Yüzde</th></tr>";
        foreach ($results as $r) {
            echo "<tr>";
            echo "<td>" . $r['id'] . "</td>";
            echo "<td>" . $r['created_at'] . "</td>";
            echo "<td>" . $r['total_questions'] . "</td>";
            echo "<td>" . $r['correct_answers'] . "</td>";
            echo "<td>" . $r['wrong_answers'] . "</td>";
            echo "<td>" . $r['score'] . "</td>";
            echo "<td>" . $r['percentage'] . "%</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
}

// 5. Tüm kullanıcıların kayıtları
try {
    $sql = "SELECT username, COUNT(*) as count FROM practice_results GROUP BY username";
    $stmt = $conn->query($sql);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Kullanıcı Bazında Kayıtlar:</h3>";
    if (empty($users)) {
        echo "<p style='color: red;'>❌ Hiç kayıt yok!</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Kullanıcı</th><th>Kayıt Sayısı</th></tr>";
        foreach ($users as $u) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($u['username']) . "</td>";
            echo "<td>" . $u['count'] . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p><strong>Hata:</strong> " . $e->getMessage() . "</p>";
}
?>
