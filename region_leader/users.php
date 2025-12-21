<?php
/**
 * Bölge Eğitim Başkanı - Kullanıcı Listesi (Sadece Görüntüleme)
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../admin/includes/locations.php';

$auth = Auth::getInstance();

// Bölge lideri kontrolü
if (!$auth->hasRole('region_leader')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$userRegion = $user['region'] ?? '';

if (empty($userRegion)) {
    die('Hata: Bölge bilgisi bulunamadı. Lütfen sistem yöneticisi ile iletişime geçin.');
}

// Şifre değiştirme kontrolü
if ($user && ($user['must_change_password'] ?? false)) {
    header('Location: ../change_password.php');
    exit;
}

// Bölgeye ait şubeleri al
$regionBranches = $regionConfig[$userRegion] ?? [];

// Sayfalama parametreleri
$itemsPerPage = isset($_GET['items_per_page']) ? max(10, intval($_GET['items_per_page'])) : 50;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$institutionFilter = isset($_GET['institution']) ? $_GET['institution'] : '';
$branchFilter = isset($_GET['branch']) ? $_GET['branch'] : '';

// Database bağlantısı
$db = Database::getInstance();
$conn = $db->getConnection();

// Kullanıcıları getir ve filtrele (sadece bölgesindeki şubelerden)
try {
    $branchPlaceholders = str_repeat('?,', count($regionBranches) - 1) . '?';
    $sql = "SELECT username, role, full_name, branch, class_section, email, phone, region, created_at, last_login 
            FROM users 
            WHERE branch IN ($branchPlaceholders)";
    
    $params = $regionBranches;
    
    // Rol filtresi
    if ($roleFilter) {
        $sql .= " AND role = ?";
        $params[] = $roleFilter;
    }
    
    // Şube filtresi
    if ($branchFilter) {
        $sql .= " AND branch = ?";
        $params[] = $branchFilter;
    }
    
    // Arama filtresi
    if ($searchTerm) {
        $sql .= " AND (full_name LIKE ? OR username LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $sql .= " ORDER BY branch, role, full_name";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtreleme (institution filter için)
    $filteredUsers = [];
    foreach ($allUsers as $userData) {
        $institution = $userData['branch'] ?? 'Belirtilmemiş';
        
        $user = [
            'username' => $userData['username'],
            'role' => $userData['role'],
            'name' => $userData['full_name'] ?? 'Bilinmiyor',
            'institution' => $institution,
            'region' => $userData['region'] ?? $userRegion,
            'class_section' => $userData['class_section'] ?? '',
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'] ?? '',
            'created_at' => $userData['created_at'] ?? 'Bilinmiyor',
            'last_login' => $userData['last_login'] ?? 'Hiç giriş yapmamış'
        ];
        
        // Kurum filtresi
        if ($institutionFilter && $user['institution'] !== $institutionFilter) {
            continue;
        }
        
        $filteredUsers[] = $user;
    }
    
} catch (Exception $e) {
    $filteredUsers = [];
    error_log("Users query error: " . $e->getMessage());
}

// Sayfalama hesaplamaları
$totalUsers = count($filteredUsers);
$totalPages = ceil($totalUsers / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

// Mevcut sayfa için kullanıcıları al
$users = array_slice($filteredUsers, $offset, $itemsPerPage);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcılar - <?php echo htmlspecialchars($userRegion); ?> Bölgesi</title>
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
                <h2>Kullanıcılar</h2>
                <p><?php echo htmlspecialchars($userRegion); ?> Bölgesi - Kullanıcı Listesi</p>
            </div>
        </div>

        <!-- Bölge Bilgisi -->
        <div class="glass-panel" style="padding: 20px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(139,92,246,0.15) 0%, rgba(139,92,246,0.05) 100%); border-left: 4px solid #8b5cf6;">
            <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
                <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139,92,246,0.2); display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-size: 1.5rem;">
                    <i class="fas fa-map-marked-alt"></i>
                </div>
                <div style="flex: 1;">
                    <div style="font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($userRegion); ?> Bölgesi
                    </div>
                    <div style="font-size: 0.9rem; color: var(--text-muted);">
                        <i class="fas fa-building"></i> <?php echo count($regionBranches); ?> Şube • 
                        <i class="fas fa-users"></i> <?php echo $totalUsers; ?> Kullanıcı
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(6,133,103,0.15) 0%, rgba(6,133,103,0.05) 100%); border-left: 4px solid var(--primary); transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(6,133,103,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalUsers; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Toplam Kullanıcı</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0.05) 100%); border-left: 4px solid #3b82f6; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(59,130,246,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.5rem;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'student')); ?>
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
                    <?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'teacher')); ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Eğitmen</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(139,92,246,0.15) 0%, rgba(139,92,246,0.05) 100%); border-left: 4px solid #8b5cf6; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(139,92,246,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(139,92,246,0.2); display: flex; align-items: center; justify-content: center; color: #8b5cf6; font-size: 1.5rem;">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo count($regionBranches); ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Şube</div>
            </div>
        </div>

        <!-- Modern Filters -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
            <form method="GET" id="filterForm" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="position: relative; flex: 1; min-width: 250px;">
                    <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 1;"></i>
                    <input type="text" name="search" placeholder="Kullanıcı adı veya isim ile ara..." value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           style="width: 100%; padding: 12px 16px 12px 44px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 0.95rem; transition: all 0.3s;"
                           onfocus="this.style.background='rgba(0,0,0,0.4)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(6,133,103,0.1)'"
                           onblur="this.style.background='rgba(0,0,0,0.3)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
                </div>
                
                <select name="role" onchange="this.form.submit()" class="modern-select" style="min-width: 140px;">
                    <option value="">🎭 Tüm Roller</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>👨‍🎓 Öğrenci</option>
                    <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>👨‍🏫 Eğitmen</option>
                </select>

                <select name="branch" onchange="this.form.submit()" class="modern-select" style="min-width: 180px;">
                    <option value="">🏢 Tüm Şubeler</option>
                    <?php foreach ($regionBranches as $branch): ?>
                        <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branchFilter === $branch ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($searchTerm || $roleFilter || $branchFilter): ?>
                    <a href="users.php" class="clean-btn" style="padding: 12px 18px;">
                        <i class="fas fa-times"></i> Temizle
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Modern Table -->
        <div class="glass-panel" style="overflow: hidden;">
            <div style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 2px;">
                            Kullanıcı Listesi
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Toplam <?php echo $totalUsers; ?> kullanıcı
                        </div>
                    </div>
                </div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <label style="font-size: 0.85rem; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-list-ol"></i> Sayfa başına:
                    </label>
                    <select onchange="changeItemsPerPage(this.value)" class="modern-select" style="padding: 10px 16px; min-width: 100px;">
                        <option value="25" <?php echo $itemsPerPage == 25 ? 'selected' : ''; ?>>25</option>
                        <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50</option>
                        <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100</option>
                    </select>
                </div>
            </div>

            <?php if (empty($users)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 30px;">
                    <div style="width: 120px; height: 120px; margin: 0 auto 24px; border-radius: 50%; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%); display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: rgba(255,255,255,0.15);">
                        <i class="fas fa-users-slash"></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 12px; font-size: 1.5rem; font-weight: 600;">Kullanıcı Bulunamadı</h3>
                    <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px;">Arama kriterlerinize uygun kullanıcı bulunmamaktadır.</p>
                    <?php if ($searchTerm || $roleFilter || $branchFilter): ?>
                        <a href="users.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-redo"></i> Filtreleri Temizle
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="overflow-x: auto; max-height: 70vh; overflow-y: auto;">
                    <table class="table users-table" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);">
                            <tr>
                                <th style="padding: 18px 24px;"><i class="fas fa-user" style="margin-right: 8px; opacity: 0.7;"></i>Kullanıcı</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-user-tag" style="margin-right: 8px; opacity: 0.7;"></i>Rol</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-map-marker-alt" style="margin-right: 8px; opacity: 0.7;"></i>Bölge</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-building" style="margin-right: 8px; opacity: 0.7;"></i>Şube</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-address-card" style="margin-right: 8px; opacity: 0.7;"></i>İletişim</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-calendar-alt" style="margin-right: 8px; opacity: 0.7;"></i>Kayıt Tarihi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $index => $user): ?>
                                <tr style="transition: all 0.2s ease; animation: fadeInRow 0.3s ease <?php echo $index * 0.02; ?>s both;">
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar" style="box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                                <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name" style="font-weight: 600; color: white;"><?php echo htmlspecialchars($user['name'] ?? 'Bilinmiyor'); ?></div>
                                                <div class="user-username" style="font-size: 0.85em; color: var(--text-muted);">@<?php echo htmlspecialchars($user['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="role-badge role-<?php echo $user['role']; ?>">
                                            <?php 
                                            $roleIcons = [
                                                'student' => '<i class="fas fa-user-graduate"></i>',
                                                'teacher' => '<i class="fas fa-chalkboard-teacher"></i>',
                                                'branch_leader' => '<i class="fas fa-building"></i>',
                                                'region_leader' => '<i class="fas fa-map-marked-alt"></i>',
                                                'superadmin' => '<i class="fas fa-crown"></i>'
                                            ];
                                            $roleNames = [
                                                'student' => 'Öğrenci',
                                                'teacher' => 'Eğitmen',
                                                'branch_leader' => 'Şube Eğitim Başkanı',
                                                'region_leader' => 'Bölge Eğitim Başkanı',
                                                'admin' => 'Admin',
                                                'superadmin' => 'Admin'
                                            ];
                                            echo ($roleIcons[$user['role']] ?? '') . ' ' . ($roleNames[$user['role']] ?? ucfirst($user['role']));
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($user['region'])): ?>
                                            <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(139,92,246,0.15); color: #a78bfa; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(139,92,246,0.2); font-weight: 500; font-size: 0.9rem;">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($user['region']); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span style="color: var(--text-muted); font-size: 0.9rem; font-style: italic;">Belirtilmemiş</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; color: #e2e8f0;">
                                            <?php echo htmlspecialchars($user['institution']); ?>
                                        </div>
                                        <?php if (!empty($user['class_section'])): ?>
                                            <div style="font-size: 0.8rem; color: #7f8c8d; margin-top: 4px; display: inline-block; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;">
                                                <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($user['class_section']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; flex-direction: column; gap: 4px;">
                                        <?php if (!empty($user['email'])): ?>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                                <i class="fas fa-envelope" style="width: 16px;"></i> <?php echo htmlspecialchars($user['email']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($user['phone'])): ?>
                                            <div style="font-size: 0.85rem; color: var(--text-muted);">
                                                <i class="fas fa-phone" style="width: 16px;"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                            </div>
                                        <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9rem; color: #e2e8f0;">
                                            <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;">
                                            <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                    
                <?php if ($totalPages > 1): ?>
                    <div class="pagination" style="padding: 24px; border-top: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; background: rgba(255,255,255,0.02);">
                        <div style="color: var(--text-muted); font-size: 0.9rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-info-circle"></i>
                            <span><?php echo $offset + 1; ?>-<?php echo min($offset + $itemsPerPage, $totalUsers); ?> / <?php echo $totalUsers; ?> kullanıcı gösteriliyor</span>
                        </div>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <button onclick="goToPage(1)" <?php echo $currentPage == 1 ? 'disabled' : ''; ?> 
                                    style="padding: 10px 16px; background: <?php echo $currentPage == 1 ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.08)'; ?>; color: <?php echo $currentPage == 1 ? 'var(--text-muted)' : '#fff'; ?>; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: <?php echo $currentPage == 1 ? 'not-allowed' : 'pointer'; ?>; transition: all 0.2s; font-weight: 500;"
                                    <?php if ($currentPage != 1): ?>onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'"<?php endif; ?>>
                                <i class="fas fa-angle-double-left"></i> İlk
                            </button>
                            <button onclick="goToPage(<?php echo $currentPage - 1; ?>)" <?php echo $currentPage == 1 ? 'disabled' : ''; ?>
                                    style="padding: 10px 16px; background: <?php echo $currentPage == 1 ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.08)'; ?>; color: <?php echo $currentPage == 1 ? 'var(--text-muted)' : '#fff'; ?>; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: <?php echo $currentPage == 1 ? 'not-allowed' : 'pointer'; ?>; transition: all 0.2s; font-weight: 500;"
                                    <?php if ($currentPage != 1): ?>onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'"<?php endif; ?>>
                                <i class="fas fa-angle-left"></i> Önceki
                            </button>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            if ($startPage > 1): ?>
                                <button onclick="goToPage(1)" style="padding: 10px 14px; background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: pointer; transition: all 0.2s; font-weight: 500;"
                                        onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'">
                                    1
                                </button>
                                <?php if ($startPage > 2): ?>
                                    <span style="color: var(--text-muted); padding: 0 8px;">...</span>
                                <?php endif; ?>
                            <?php endif;
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <button onclick="goToPage(<?php echo $i; ?>)" 
                                        style="padding: 10px 14px; background: <?php echo $i == $currentPage ? 'linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%)' : 'rgba(255,255,255,0.08)'; ?>; color: #fff; border: 1px solid <?php echo $i == $currentPage ? 'var(--primary)' : 'rgba(255,255,255,0.1)'; ?>; border-radius: 10px; cursor: pointer; transition: all 0.2s; font-weight: <?php echo $i == $currentPage ? '700' : '500'; ?>; box-shadow: <?php echo $i == $currentPage ? '0 4px 12px rgba(6,133,103,0.3)' : 'none'; ?>;"
                                        <?php if ($i != $currentPage): ?>onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'"<?php endif; ?>>
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; 
                            
                            if ($endPage < $totalPages): ?>
                                <?php if ($endPage < $totalPages - 1): ?>
                                    <span style="color: var(--text-muted); padding: 0 8px;">...</span>
                                <?php endif; ?>
                                <button onclick="goToPage(<?php echo $totalPages; ?>)" style="padding: 10px 14px; background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: pointer; transition: all 0.2s; font-weight: 500;"
                                        onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'">
                                    <?php echo $totalPages; ?>
                                </button>
                            <?php endif; ?>
                            
                            <button onclick="goToPage(<?php echo $currentPage + 1; ?>)" <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>
                                    style="padding: 10px 16px; background: <?php echo $currentPage == $totalPages ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.08)'; ?>; color: <?php echo $currentPage == $totalPages ? 'var(--text-muted)' : '#fff'; ?>; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: <?php echo $currentPage == $totalPages ? 'not-allowed' : 'pointer'; ?>; transition: all 0.2s; font-weight: 500;"
                                    <?php if ($currentPage != $totalPages): ?>onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'"<?php endif; ?>>
                                Sonraki <i class="fas fa-angle-right"></i>
                            </button>
                            <button onclick="goToPage(<?php echo $totalPages; ?>)" <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>
                                    style="padding: 10px 16px; background: <?php echo $currentPage == $totalPages ? 'rgba(255,255,255,0.05)' : 'rgba(255,255,255,0.08)'; ?>; color: <?php echo $currentPage == $totalPages ? 'var(--text-muted)' : '#fff'; ?>; border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: <?php echo $currentPage == $totalPages ? 'not-allowed' : 'pointer'; ?>; transition: all 0.2s; font-weight: 500;"
                                    <?php if ($currentPage != $totalPages): ?>onmouseover="this.style.background='rgba(255,255,255,0.12)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(255,255,255,0.08)'; this.style.transform='translateY(0)'"<?php endif; ?>>
                                Son <i class="fas fa-angle-double-right"></i>
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function goToPage(page) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        function changeItemsPerPage(itemsPerPage) {
            const url = new URL(window.location);
            url.searchParams.set('items_per_page', itemsPerPage);
            url.searchParams.set('page', 1);
            window.location.href = url.toString();
        }

        // Sayfa yüklendiğinde animasyonlar
        document.addEventListener('DOMContentLoaded', function() {
            const statCards = document.querySelectorAll('.glass-panel');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>


