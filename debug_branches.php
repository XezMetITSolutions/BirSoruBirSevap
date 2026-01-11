<?php
/**
 * Debug Branch/Region Bilgileri
 */

require_once 'config.php';
require_once 'database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <title>Branch/Region Debug</title>
    <style>
        body { font-family: monospace; background: #0f172a; color: #e2e8f0; padding: 40px; max-width: 1200px; margin: 0 auto; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; border: 1px solid rgba(255,255,255,0.2); text-align: left; }
        th { background: rgba(6,133,103,0.2); color: #10b981; font-weight: bold; }
        tr:nth-child(even) { background: rgba(255,255,255,0.05); }
        .info { background: rgba(59,130,246,0.2); border: 1px solid #3b82f6; padding: 14px; margin: 10px 0; border-radius: 8px; }
        .warning { background: rgba(245,158,11,0.2); border: 1px solid #f59e0b; padding: 14px; margin: 10px 0; border-radius: 8px; }
        h1 { color: white; }
    </style>
</head>
<body>
    <h1>🔍 Branch/Region Debug</h1>";

try {
    // Tüm kullanıcıları çek
    $stmt = $conn->query("SELECT username, full_name, role, branch, region FROM users ORDER BY branch, role, username");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>📊 Toplam " . count($users) . " kullanıcı bulundu</div>";
    
    // Branch'lere göre grupla
    $branchGroups = [];
    $emptyBranch = [];
    
    foreach ($users as $user) {
        $branch = trim($user['branch']);
        if (empty($branch)) {
            $emptyBranch[] = $user;
        } else {
            if (!isset($branchGroups[$branch])) {
                $branchGroups[$branch] = [];
            }
            $branchGroups[$branch][] = $user;
        }
    }
    
    // Boş branch olan kullanıcılar
    if (!empty($emptyBranch)) {
        echo "<div class='warning'>⚠️ <strong>" . count($emptyBranch) . "</strong> kullanıcının branch bilgisi boş!</div>";
        echo "<table>
            <tr>
                <th>Username</th>
                <th>Ad Soyad</th>
                <th>Rol</th>
                <th>Branch</th>
                <th>Region</th>
            </tr>";
        foreach ($emptyBranch as $user) {
            echo "<tr>
                <td>" . htmlspecialchars($user['username']) . "</td>
                <td>" . htmlspecialchars($user['full_name']) . "</td>
                <td>" . htmlspecialchars($user['role']) . "</td>
                <td style='color: #ef4444;'><strong>BOŞ</strong></td>
                <td>" . htmlspecialchars($user['region'] ?? 'Boş') . "</td>
            </tr>";
        }
        echo "</table>";
    }
    
    // Branch'lere göre listele
    foreach ($branchGroups as $branch => $branchUsers) {
        echo "<h2 style='color: #10b981; margin-top: 30px;'>🏢 " . htmlspecialchars($branch) . " (" . count($branchUsers) . " kullanıcı)</h2>";
        
        $students = array_filter($branchUsers, fn($u) => $u['role'] === 'student');
        $teachers = array_filter($branchUsers, fn($u) => $u['role'] === 'teacher');
        $leaders = array_filter($branchUsers, fn($u) => in_array($u['role'], ['branch_leader', 'region_leader']));
        
        echo "<div class='info'>";
        echo "👨‍🎓 Öğrenci: <strong>" . count($students) . "</strong> | ";
        echo "👨‍🏫 Eğitmen: <strong>" . count($teachers) . "</strong> | ";
        echo "👨‍💼 Yönetici: <strong>" . count($leaders) . "</strong>";
        echo "</div>";
        
        echo "<table>
            <tr>
                <th>Username</th>
                <th>Ad Soyad</th>
                <th>Rol</th>
                <th>Region</th>
            </tr>";
        foreach ($branchUsers as $user) {
            $roleColor = $user['role'] === 'student' ? '#3b82f6' : ($user['role'] === 'teacher' ? '#f59e0b' : '#8b5cf6');
            echo "<tr>
                <td>" . htmlspecialchars($user['username']) . "</td>
                <td>" . htmlspecialchars($user['full_name']) . "</td>
                <td style='color: $roleColor; font-weight: bold;'>" . htmlspecialchars($user['role']) . "</td>
                <td>" . htmlspecialchars($user['region'] ?? 'Belirtilmemiş') . "</td>
            </tr>";
        }
        echo "</table>";
    }
    
    // Branch Leader kullanıcıları kontrol et
    echo "<h2 style='color: #ef4444; margin-top: 40px;'>👨‍💼 Branch Leader Kullanıcıları</h2>";
    $stmt = $conn->query("SELECT username, full_name, branch, region FROM users WHERE role = 'branch_leader' ORDER BY username");
    $branchLeaders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($branchLeaders)) {
        echo "<div class='warning'>⚠️ Hiç branch_leader kullanıcısı bulunamadı!</div>";
    } else {
        echo "<table>
            <tr>
                <th>Username</th>
                <th>Ad Soyad</th>
                <th>Branch</th>
                <th>Region</th>
                <th>Dashboard'da Göreceği Öğrenci Sayısı</th>
            </tr>";
        foreach ($branchLeaders as $leader) {
            $branch = trim($leader['branch']);
            if (!empty($branch)) {
                $stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE branch = ? AND role = 'student'");
                $stmt->execute([$branch]);
                $studentCount = $stmt->fetch()['count'];
            } else {
                $studentCount = 0;
            }
            
            $branchDisplay = !empty($branch) ? htmlspecialchars($branch) : '<span style="color: #ef4444;">BOŞ</span>';
            echo "<tr>
                <td>" . htmlspecialchars($leader['username']) . "</td>
                <td>" . htmlspecialchars($leader['full_name']) . "</td>
                <td>$branchDisplay</td>
                <td>" . htmlspecialchars($leader['region'] ?? 'Belirtilmemiş') . "</td>
                <td style='font-weight: bold; color: " . ($studentCount > 0 ? '#10b981' : '#ef4444') . "'>$studentCount</td>
            </tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "<div style='background: rgba(239,68,68,0.2); border: 1px solid #ef4444; padding: 14px; margin: 10px 0; border-radius: 8px;'>";
    echo "❌ Hata: " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<p style='margin-top: 30px;'><a href='admin/users.php' style='color: #3b82f6;'>← Admin Panel</a></p>";
echo "</body></html>";
?>
