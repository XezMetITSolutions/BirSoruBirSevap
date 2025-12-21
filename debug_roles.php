<?php
/**
 * Debug: Kullanıcı Rollerini Kontrol Et
 */

require_once 'auth.php';

$auth = Auth::getInstance();
$allUsers = $auth->getAllUsers();

echo "<!DOCTYPE html>
<html lang='tr'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Debug: Roller</title>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            padding: 40px;
            max-width: 1000px;
            margin: 0 auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(30, 41, 59, 0.6);
            border-radius: 12px;
            overflow: hidden;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        th {
            background: rgba(0, 0, 0, 0.3);
            font-weight: 600;
        }
        .role-student { color: #60a5fa; }
        .role-teacher { color: #fbbf24; }
        .role-admin { color: #f87171; }
        h1 { color: white; margin-bottom: 20px; }
        .info {
            background: rgba(59, 130, 246, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.4);
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <h1>🔍 Kullanıcı Rolleri Debug</h1>
    <div class='info'>Toplam Kullanıcı: " . count($allUsers) . "</div>
    <table>
        <thead>
            <tr>
                <th>Kullanıcı Adı</th>
                <th>Ad Soyad</th>
                <th>Rol (DB)</th>
                <th>Rol Gösterimi</th>
            </tr>
        </thead>
        <tbody>";

$roleMap = [
    'student' => ['class' => 'role-student', 'name' => 'Öğrenci'],
    'teacher' => ['class' => 'role-teacher', 'name' => 'Eğitmen'],
    'branch_leader' => ['class' => 'role-admin', 'name' => 'Şube Eğitim Başkanı'],
    'region_leader' => ['class' => 'role-admin', 'name' => 'Bölge Eğitim Başkanı'],
    'admin' => ['class' => 'role-admin', 'name' => 'Admin'],
    'superadmin' => ['class' => 'role-admin', 'name' => 'Admin']
];

foreach ($allUsers as $username => $userData) {
    $currentRole = $userData['role'] ?? 'unknown';
    $roleInfo = $roleMap[$currentRole] ?? ['class' => 'role-student', 'name' => 'UNKNOWN'];
    
    echo "<tr>
        <td><code>$username</code></td>
        <td>" . htmlspecialchars($userData['full_name'] ?? $userData['name'] ?? 'N/A') . "</td>
        <td><strong>" . htmlspecialchars($currentRole) . "</strong></td>
        <td class='{$roleInfo['class']}'>{$roleInfo['name']}</td>
    </tr>";
}

echo "</tbody>
    </table>
    <p style='margin-top: 20px;'>
        <a href='admin/users.php' style='color: #3b82f6;'>← Kullanıcı Yönetimine Dön</a>
    </p>
</body>
</html>";
?>
