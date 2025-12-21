<?php
/**
 * Bölge Eğitim Başkanı - Sınavlar (Sadece Görüntüleme)
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

// Database bağlantısı
$db = Database::getInstance();
$conn = $db->getConnection();

// Sayfalama parametreleri
$itemsPerPage = isset($_GET['items_per_page']) ? max(10, intval($_GET['items_per_page'])) : 50;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$branchFilter = isset($_GET['branch']) ? $_GET['branch'] : '';

// Sınavları getir (bölgesindeki şubelerden)
try {
    $branchPlaceholders = str_repeat('?,', count($regionBranches) - 1) . '?';
    
    // Önce sınavları oluşturan öğretmenlerin şubelerini kontrol et
    $sql = "SELECT DISTINCT e.*, u.full_name as teacher_name, u.branch as teacher_branch
            FROM exams e
            INNER JOIN users u ON e.created_by = u.username
            WHERE u.branch IN ($branchPlaceholders)";
    
    $params = $regionBranches;
    
    // Durum filtresi
    if ($statusFilter) {
        $sql .= " AND e.status = ?";
        $params[] = $statusFilter;
    }
    
    // Şube filtresi
    if ($branchFilter) {
        $sql .= " AND u.branch = ?";
        $params[] = $branchFilter;
    }
    
    // Arama filtresi
    if ($searchTerm) {
        $sql .= " AND (e.title LIKE ? OR e.exam_id LIKE ?)";
        $searchParam = "%$searchTerm%";
        $params[] = $searchParam;
        $params[] = $searchParam;
    }
    
    $sql .= " ORDER BY e.created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $allExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Her sınav için katılımcı sayısını al
    foreach ($allExams as &$exam) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM exam_results WHERE exam_id = ?");
        $stmt->execute([$exam['exam_id']]);
        $exam['participant_count'] = $stmt->fetch()['count'] ?? 0;
    }
    
} catch (Exception $e) {
    $allExams = [];
    error_log("Exams query error: " . $e->getMessage());
}

// Sayfalama hesaplamaları
$totalExams = count($allExams);
$totalPages = ceil($totalExams / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

// Mevcut sayfa için sınavları al
$exams = array_slice($allExams, $offset, $itemsPerPage);

// İstatistikler
$totalActive = count(array_filter($allExams, fn($e) => $e['status'] === 'active'));
$totalCompleted = count(array_filter($allExams, fn($e) => $e['status'] === 'completed'));
$totalScheduled = count(array_filter($allExams, fn($e) => $e['status'] === 'scheduled'));
$totalParticipants = array_sum(array_column($allExams, 'participant_count'));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınavlar - <?php echo htmlspecialchars($userRegion); ?> Bölgesi</title>
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
                <h2>Sınavlar</h2>
                <p><?php echo htmlspecialchars($userRegion); ?> Bölgesi - Sınav Listesi</p>
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
                        <i class="fas fa-file-alt"></i> <?php echo $totalExams; ?> Sınav • 
                        <i class="fas fa-users"></i> <?php echo $totalParticipants; ?> Katılımcı
                    </div>
                </div>
            </div>
        </div>

        <!-- Modern Stats Cards -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(6,133,103,0.15) 0%, rgba(6,133,103,0.05) 100%); border-left: 4px solid var(--primary); transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(6,133,103,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalExams; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Toplam Sınav</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(34,197,94,0.15) 0%, rgba(34,197,94,0.05) 100%); border-left: 4px solid #22c55e; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(34,197,94,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(34,197,94,0.2); display: flex; align-items: center; justify-content: center; color: #22c55e; font-size: 1.5rem;">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalActive; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Aktif Sınav</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(52,152,219,0.15) 0%, rgba(52,152,219,0.05) 100%); border-left: 4px solid #3498db; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(52,152,219,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(52,152,219,0.2); display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 1.5rem;">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalCompleted; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Tamamlanan</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(245,158,11,0.15) 0%, rgba(245,158,11,0.05) 100%); border-left: 4px solid #f59e0b; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(245,158,11,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.2); display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 1.5rem;">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalParticipants; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Toplam Katılımcı</div>
            </div>
        </div>

        <!-- Modern Filters -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
            <form method="GET" id="filterForm" style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                <div style="position: relative; flex: 1; min-width: 250px;">
                    <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 1;"></i>
                    <input type="text" name="search" placeholder="Sınav adı veya ID ile ara..." value="<?php echo htmlspecialchars($searchTerm); ?>" 
                           style="width: 100%; padding: 12px 16px 12px 44px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 0.95rem; transition: all 0.3s;"
                           onfocus="this.style.background='rgba(0,0,0,0.4)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(6,133,103,0.1)'"
                           onblur="this.style.background='rgba(0,0,0,0.3)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
                </div>
                
                <select name="status" onchange="this.form.submit()" class="modern-select" style="min-width: 160px;">
                    <option value="">📋 Tüm Durumlar</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>✅ Aktif</option>
                    <option value="scheduled" <?php echo $statusFilter === 'scheduled' ? 'selected' : ''; ?>>📅 Planlanmış</option>
                    <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>✔️ Tamamlanan</option>
                </select>

                <select name="branch" onchange="this.form.submit()" class="modern-select" style="min-width: 180px;">
                    <option value="">🏢 Tüm Şubeler</option>
                    <?php foreach ($regionBranches as $branch): ?>
                        <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branchFilter === $branch ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($searchTerm || $statusFilter || $branchFilter): ?>
                    <a href="exams.php" class="clean-btn" style="padding: 12px 18px;">
                        <i class="fas fa-times"></i> Temizle
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Modern Table -->
        <div class="glass-panel" style="overflow: hidden;">
            <div style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(52,152,219,0.2); display: flex; align-items: center; justify-content: center; color: #3498db;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 2px;">
                            Sınav Listesi
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            Toplam <?php echo $totalExams; ?> sınav
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

            <?php if (empty($exams)): ?>
                <div class="empty-state" style="text-align: center; padding: 60px 30px;">
                    <div style="width: 120px; height: 120px; margin: 0 auto 24px; border-radius: 50%; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%); display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: rgba(255,255,255,0.15);">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <h3 style="color: white; margin-bottom: 12px; font-size: 1.5rem; font-weight: 600;">Sınav Bulunamadı</h3>
                    <p style="color: var(--text-muted); font-size: 0.95rem; margin-bottom: 24px;">Arama kriterlerinize uygun sınav bulunmamaktadır.</p>
                    <?php if ($searchTerm || $statusFilter || $branchFilter): ?>
                        <a href="exams.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fas fa-redo"></i> Filtreleri Temizle
                        </a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="table-responsive" style="overflow-x: auto; max-height: 70vh; overflow-y: auto;">
                    <table class="table users-table" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);">
                            <tr>
                                <th style="padding: 18px 24px;"><i class="fas fa-file-alt" style="margin-right: 8px; opacity: 0.7;"></i>Sınav</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-user-tie" style="margin-right: 8px; opacity: 0.7;"></i>Eğitmen</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-building" style="margin-right: 8px; opacity: 0.7;"></i>Şube</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-info-circle" style="margin-right: 8px; opacity: 0.7;"></i>Durum</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-users" style="margin-right: 8px; opacity: 0.7;"></i>Katılımcı</th>
                                <th style="padding: 18px 24px;"><i class="fas fa-calendar-alt" style="margin-right: 8px; opacity: 0.7;"></i>Tarih</th>
                                <th style="padding: 18px 24px; text-align: center;"><i class="fas fa-cog" style="margin-right: 8px; opacity: 0.7;"></i>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($exams as $index => $exam): ?>
                                <tr style="transition: all 0.2s ease; animation: fadeInRow 0.3s ease <?php echo $index * 0.02; ?>s both;">
                                    <td>
                                        <div style="font-weight: 600; color: #fff; margin-bottom: 4px;">
                                            <?php echo htmlspecialchars($exam['title'] ?? 'Başlıksız'); ?>
                                        </div>
                                        <div style="font-size: 0.85em; color: var(--text-muted); font-family: monospace;">
                                            ID: <?php echo htmlspecialchars($exam['exam_id']); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; color: #e2e8f0;">
                                            <?php echo htmlspecialchars($exam['teacher_name'] ?? 'Bilinmiyor'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; color: #e2e8f0;">
                                            <?php echo htmlspecialchars($exam['teacher_branch'] ?? 'Belirtilmemiş'); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status = $exam['status'] ?? 'unknown';
                                        $statusConfig = [
                                            'active' => ['label' => 'Aktif', 'color' => '#22c55e', 'icon' => 'check-circle'],
                                            'scheduled' => ['label' => 'Planlanmış', 'color' => '#3b82f6', 'icon' => 'calendar'],
                                            'completed' => ['label' => 'Tamamlanan', 'color' => '#8b5cf6', 'icon' => 'check-double'],
                                            'draft' => ['label' => 'Taslak', 'color' => '#f59e0b', 'icon' => 'edit']
                                        ];
                                        $statusInfo = $statusConfig[$status] ?? ['label' => 'Bilinmiyor', 'color' => '#6b7280', 'icon' => 'question'];
                                        ?>
                                        <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(<?php echo hexdec(substr($statusInfo['color'], 1, 2)); ?>, <?php echo hexdec(substr($statusInfo['color'], 3, 2)); ?>, <?php echo hexdec(substr($statusInfo['color'], 5, 2)); ?>, 0.15); color: <?php echo $statusInfo['color']; ?>; padding: 6px 12px; border-radius: 8px; border: 1px solid rgba(<?php echo hexdec(substr($statusInfo['color'], 1, 2)); ?>, <?php echo hexdec(substr($statusInfo['color'], 3, 2)); ?>, <?php echo hexdec(substr($statusInfo['color'], 5, 2)); ?>, 0.2); font-weight: 500; font-size: 0.9rem;">
                                            <i class="fas fa-<?php echo $statusInfo['icon']; ?>"></i>
                                            <span><?php echo $statusInfo['label']; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 600; color: #fff; font-size: 1.1rem;">
                                            <?php echo $exam['participant_count'] ?? 0; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9rem; color: #e2e8f0;">
                                            <?php echo date('d.m.Y', strtotime($exam['created_at'])); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;">
                                            <?php echo date('H:i', strtotime($exam['created_at'])); ?>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <a href="results.php?exam_id=<?php echo urlencode($exam['exam_id']); ?>" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem;">
                                            <i class="fas fa-chart-bar"></i> Sonuçlar
                                        </a>
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
                            <span><?php echo $offset + 1; ?>-<?php echo min($offset + $itemsPerPage, $totalExams); ?> / <?php echo $totalExams; ?> sınav gösteriliyor</span>
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

