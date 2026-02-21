<?php
/**
 * Sınav Sayısı Uyuşmazlığı Debug Sayfası
 */
require_once 'auth.php';
require_once 'config.php';
require_once 'database.php';

$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    die("Lütfen giriş yapın.");
}

$user = $auth->getUser();
$db = Database::getInstance();
$conn = $db->getConnection();

echo "<!DOCTYPE html><html><head><title>Exam Debug Info</title>";
echo "<style>
    body { font-family: sans-serif; background: #1a1a1a; color: #eee; padding: 20px; }
    .card { background: #2a2a2a; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 5px solid #068567; }
    table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    th, td { border: 1px solid #444; padding: 10px; text-align: left; }
    th { background: #333; }
    .match { color: #4ade80; font-weight: bold; }
    .mismatch { color: #f87171; font-weight: bold; }
    pre { background: #000; padding: 10px; overflow-x: auto; }
</style></head><body>";

echo "<h1>🔍 Sınav Sayısı Debug Paneli</h1>";

// 1. Kullanıcı Bilgileri
echo "<div class='card'>";
echo "<h3>1. Mevcut Oturum Bilgileri</h3>";
echo "<table>";
echo "<tr><th>Kullanıcı Adı</th><td>" . htmlspecialchars($user['username']) . "</td></tr>";
echo "<tr><th>Rol</th><td>" . htmlspecialchars($user['role']) . "</td></tr>";
echo "<tr><th>Kurum (Institution)</th><td>" . htmlspecialchars($user['institution']) . "</td></tr>";
echo "<tr><th>Şube (Branch)</th><td>" . htmlspecialchars($user['branch']) . "</td></tr>";
echo "<tr><th>Bölge (Region)</th><td>" . htmlspecialchars($user['region']) . "</td></tr>";
echo "</table>";
echo "</div>";

// 2. Dashboard Mantığı (Genel Sayı)
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM exams WHERE status = 'active'");
    $totalActive = $stmt->fetchColumn();
    echo "<div class='card'>";
    echo "<h3>2. Dashboard Mantığı (Genel Aktif Sınavlar)</h3>";
    echo "<p>Sorgu: <code>SELECT COUNT(*) FROM exams WHERE status = 'active'</code></p>";
    echo "<p>Sonuç: <span class='match'>$totalActive</span></p>";
    echo "</div>";
} catch (Exception $e) { echo "Hata: " . $e->getMessage(); }

// 3. Exams.php Mantığı (Filtreli Liste)
echo "<div class='card'>";
echo "<h3>3. Exam Listesi Mantığı (Filtreleme Analizi)</h3>";
try {
    $sql = "SELECT exam_id, title, class_section, status, created_by FROM exams";
    $stmt = $conn->query($sql);
    $allExams = $stmt->fetchAll();

    echo "<table><thead><tr>
        <th>Exam ID</th>
        <th>Başlık</th>
        <th>Sınıf/Şube (DB)</th>
        <th>Durum</th>
        <th>Dashboard'da Sayılır mı?</th>
        <th>Listede Görünür mü?</th>
    </tr></thead><tbody>";

    $visibleCount = 0;
    foreach ($allExams as $exam) {
        $isActive = ($exam['status'] === 'active');
        
        // teacher/exams.php içindeki olası filtreleme mantığı:
        // Genelde şube veya kurum eşleşmesine bakılır.
        $userBranch = $user['branch'] ?? $user['institution'] ?? '';
        $examBranch = $exam['class_section'] ?? '';
        
        $isVisible = ($examBranch === $userBranch || $user['role'] === 'superadmin' || empty($userBranch));
        if ($isVisible && $isActive) $visibleCount++;

        echo "<tr>";
        echo "<td>" . $exam['exam_id'] . "</td>";
        echo "<td>" . htmlspecialchars($exam['title']) . "</td>";
        echo "<td>" . htmlspecialchars($examBranch) . "</td>";
        echo "<td>" . $exam['status'] . "</td>";
        echo "<td>" . ($isActive ? "✅ Evet" : "❌ Hayır") . "</td>";
        echo "<td class='" . ($isVisible ? "match" : "mismatch") . "'>" . ($isVisible ? "👁️ Görünür" : "🚫 Gizli") . "</td>";
        echo "</tr>";
    }
    echo "</tbody></table>";
    echo "<p><strong>Toplam Görünür Sınav (Tahmini): $visibleCount</strong></p>";
} catch (Exception $e) { echo "Hata: " . $e->getMessage(); }
echo "</div>";

echo "</body></html>";
