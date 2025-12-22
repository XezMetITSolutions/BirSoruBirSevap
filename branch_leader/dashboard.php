<?php
/**
 * Eğitim Başkanı Dashboard
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../admin/includes/locations.php';

$auth = Auth::getInstance();

// Eğitim başkanı kontrolü
if (!$auth->hasRole('branch_leader')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$userBranch = $user['branch'] ?? $user['institution'] ?? '';

// Debug: Kullanıcı bilgisini kontrol et (geliştirme sırasında)
if (isset($_GET['debug'])) {
    echo "<pre style='background: #000; color: #0f0; padding: 20px; position: fixed; top: 10px; right: 10px; z-index: 9999; border: 2px solid #0f0;'>";
    echo "DEBUG INFO:\n";
    echo "Username: " . ($user['username'] ?? 'N/A') . "\n";
    echo "Name: " . ($user['name'] ?? 'N/A') . "\n";
    echo "Branch: " . ($user['branch'] ?? 'N/A') . "\n";
    echo "Institution: " . ($user['institution'] ?? 'N/A') . "\n";
    echo "\$userBranch: " . ($userBranch ?: 'EMPTY') . "\n";
    echo "</pre>";
}

if (empty($userBranch)) {
    die('Hata: Şube bilgisi bulunamadı. Lütfen sistem yöneticisi ile iletişime geçin.');
}

// Şifre değiştirme kontrolü
if ($user && ($user['must_change_password'] ?? false)) {
    header('Location: ../change_password.php');
    exit;
}

// Database bağlantısı
$db = Database::getInstance();
$conn = $db->getConnection();

// İstatistikler
try {
    // Debug mode için ekstra bilgi
    $debugMode = isset($_GET['debug']);
    
    // Şubedeki toplam kullanıcı sayısı
    $sql = "SELECT COUNT(*) as total FROM users WHERE branch = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userBranch]);
    $totalUsers = $stmt->fetch()['total'] ?? 0;
    
    if ($debugMode) {
        echo "<pre style='background: #000; color: #ff0; padding: 20px; position: fixed; bottom: 10px; right: 10px; z-index: 9999; border: 2px solid #ff0; max-height: 400px; overflow: auto;'>";
        echo "SQL DEBUG:\n";
        echo "Query: $sql\n";
        echo "Branch parameter: '" . $userBranch . "' (length: " . strlen($userBranch) . ")\n";
        echo "Total users result: $totalUsers\n\n";
    }
    
    // Şubedeki öğrenci sayısı
    $sql = "SELECT COUNT(*) as total FROM users WHERE branch = ? AND role = 'student'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userBranch]);
    $totalStudents = $stmt->fetch()['total'] ?? 0;
    
    if ($debugMode) {
        echo "Student Query: $sql\n";
        echo "Student result: $totalStudents\n\n";
        
        // Gerçek kullanıcıları göster
        $sql = "SELECT username, full_name, role, branch FROM users WHERE branch = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$userBranch]);
        $allBranchUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Actual users in this branch:\n";
        foreach ($allBranchUsers as $u) {
            echo "  - {$u['username']} ({$u['role']}) - Branch: '{$u['branch']}'\n";
        }
        echo "</pre>";
    }
    
    // Şubedeki eğitmen sayısı
    $sql = "SELECT COUNT(*) as total FROM users WHERE branch = ? AND role = 'teacher'";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userBranch]);
    $totalTeachers = $stmt->fetch()['total'] ?? 0;
    
    // Şubedeki toplam sınav sayısı (exam_results tablosundan)
    $sql = "SELECT COUNT(DISTINCT er.exam_id) as total 
            FROM exam_results er 
            INNER JOIN users u ON er.username COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci
            WHERE u.branch = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userBranch]);
    $totalExams = $stmt->fetch()['total'] ?? 0;
    
    // Şubedeki toplam alıştırma sayısı
    $sql = "SELECT COUNT(*) as total 
            FROM practice_results pr 
            INNER JOIN users u ON pr.username COLLATE utf8mb4_unicode_ci = u.username COLLATE utf8mb4_unicode_ci
            WHERE u.branch = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userBranch]);
    $totalPractices = $stmt->fetch()['total'] ?? 0;
    
    // Şubedeki kullanıcıları al
    $sql = "SELECT username, full_name, role, branch, class_section, email, phone, created_at 
            FROM users 
            WHERE branch = ? 
            ORDER BY role, full_name 
            LIMIT 50";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$userBranch]);
    $branchUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $totalUsers = 0;
    $totalStudents = 0;
    $totalTeachers = 0;
    $totalExams = 0;
    $totalPractices = 0;
    $branchUsers = [];
    
    $errorMsg = $e->getMessage();
    error_log("Dashboard error: " . $errorMsg);
    
    // Debug modunda hatayı göster
    if (isset($_GET['debug'])) {
        echo "<pre style='background: #f00; color: #fff; padding: 20px; position: fixed; top: 200px; left: 10px; z-index: 9999; border: 2px solid #fff; max-width: 500px;'>";
        echo "❌ CATCH BLOCK ERROR:\n";
        echo "Error message: " . htmlspecialchars($errorMsg) . "\n";
        echo "Error line: " . $e->getLine() . "\n";
        echo "Error file: " . basename($e->getFile()) . "\n";
        echo "\nStack trace:\n" . $e->getTraceAsString();
        echo "</pre>";
    }
}

// Final debug - HTML'den hemen önce değerleri kontrol et
if (isset($_GET['debug'])) {
    echo "<pre style='background: #f00; color: #fff; padding: 20px; position: fixed; top: 10px; left: 10px; z-index: 9999; border: 2px solid #fff;'>";
    echo "FINAL VALUES BEFORE HTML:\n";
    echo "totalUsers: " . ($totalUsers ?? 'UNDEFINED') . "\n";
    echo "totalStudents: " . ($totalStudents ?? 'UNDEFINED') . "\n";
    echo "totalTeachers: " . ($totalTeachers ?? 'UNDEFINED') . "\n";
    echo "totalExams: " . ($totalExams ?? 'UNDEFINED') . "\n";
    echo "totalPractices: " . ($totalPractices ?? 'UNDEFINED') . "\n";
    echo "</pre>";
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitim Başkanı Dashboard - Bir Soru Bir Sevap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../admin/css/admin-style.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="top-bar">
            <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </div>
            <div class="welcome-text">
                <h2>Hoş Geldiniz, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                <p><?php echo htmlspecialchars($userBranch); ?> Yönetim Paneli</p>
            </div>
        </div>

        <!-- Şube Bilgisi -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0.05) 100%); border-left: 4px solid #3b82f6;">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="width: 60px; height: 60px; border-radius: 12px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 2rem;">
                    <i class="fas fa-building"></i>
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0 0 8px 0; font-size: 1.5rem; font-weight: 700; color: #fff;">
                        <?php echo htmlspecialchars($userBranch); ?>
                    </h2>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; color: var(--text-muted);">
                        <span><i class="fas fa-users"></i> <strong style="color: #fff;"><?php echo $totalUsers; ?></strong> Toplam Kullanıcı</span>
                        <span><i class="fas fa-user-graduate"></i> <strong style="color: #fff;"><?php echo $totalStudents; ?></strong> Öğrenci</span>
                        <span><i class="fas fa-chalkboard-teacher"></i> <strong style="color: #fff;"><?php echo $totalTeachers; ?></strong> Eğitmen</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0.05) 100%); border-left: 4px solid #3b82f6; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(59,130,246,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.5rem;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalStudents; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Öğrenci</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(245,158,11,0.15) 0%, rgba(245,158,11,0.05) 100%); border-left: 4px solid #f59e0b; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(245,158,11,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.2); display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 1.5rem;">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalTeachers; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Eğitmen</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(52,152,219,0.15) 0%, rgba(52,152,219,0.05) 100%); border-left: 4px solid #3498db; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(52,152,219,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(52,152,219,0.2); display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 1.5rem;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalExams; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Sınav</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(6,133,103,0.15) 0%, rgba(6,133,103,0.05) 100%); border-left: 4px solid var(--primary); transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(6,133,103,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem;">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalPractices; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Alıştırma</div>
            </div>
        </div>

        <!-- Kullanıcılar Listesi -->
        <div class="glass-panel">
            <div style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center; color: #3b82f6;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 2px;">
                            Şubedeki Kullanıcılar
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Son 50 kullanıcı gösteriliyor
                        </div>
                    </div>
                </div>
                <a href="users.php" class="btn btn-primary" style="padding: 10px 20px;">
                    <i class="fas fa-list"></i> Tümünü Gör
                </a>
            </div>
            <div class="table-responsive" style="overflow-x: auto; max-height: 600px; overflow-y: auto;">
                <table class="table users-table" style="margin: 0;">
                    <thead style="position: sticky; top: 0; z-index: 10; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);">
                        <tr>
                            <th style="padding: 18px 24px;"><i class="fas fa-user" style="margin-right: 8px; opacity: 0.7;"></i>Kullanıcı</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-user-tag" style="margin-right: 8px; opacity: 0.7;"></i>Rol</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-calendar-alt" style="margin-right: 8px; opacity: 0.7;"></i>Kayıt Tarihi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($branchUsers)): ?>
                            <?php foreach ($branchUsers as $index => $branchUser): ?>
                                <tr style="transition: all 0.2s ease; animation: fadeInRow 0.3s ease <?php echo $index * 0.02; ?>s both;">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar" style="box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                                <?php echo strtoupper(substr($branchUser['full_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name" style="font-weight: 600; color: white;"><?php echo htmlspecialchars($branchUser['full_name'] ?? 'Bilinmiyor'); ?></div>
                                                <div class="user-username" style="font-size: 0.85em; color: var(--text-muted);">@<?php echo htmlspecialchars($branchUser['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $branchUser['role']; ?>">
                                            <?php 
                                            $roleIcons = [
                                                'student' => '<i class="fas fa-user-graduate"></i>',
                                                'teacher' => '<i class="fas fa-chalkboard-teacher"></i>',
                                                'branch_leader' => '<i class="fas fa-building"></i>',
                                                'region_leader' => '<i class="fas fa-map-marked-alt"></i>',
                                                'superadmin' => '<i class="fas fa-crown"></i>'
                                            ];
                                            echo ($roleIcons[$branchUser['role']] ?? '') . ' ' . ucfirst($branchUser['role']);
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9rem; color: #e2e8f0;">
                                            <?php echo date('d.m.Y', strtotime($branchUser['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;">
                                            <?php echo date('H:i', strtotime($branchUser['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center; padding: 60px 30px;">
                                    <div style="width: 80px; height: 80px; margin: 0 auto 16px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: rgba(255,255,255,0.2);">
                                        <i class="fas fa-users-slash"></i>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.95rem;">Kullanıcı bulunamadı</div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

