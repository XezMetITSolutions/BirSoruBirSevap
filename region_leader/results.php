<?php
/**
 * Bölge Eğitim Başkanı - Sınav Sonuçları
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

$examId = $_GET['exam_id'] ?? '';
$branchFilter = $_GET['branch'] ?? '';

// Sınav bilgilerini al
$exam = null;
$results = [];

if (!empty($examId)) {
    try {
        $branchPlaceholders = str_repeat('?,', count($regionBranches) - 1) . '?';
        
        // Sınav bilgilerini al (bölgesindeki şubelerden)
        $sql = "SELECT e.*, u.full_name as teacher_name, u.branch as teacher_branch
                FROM exams e
                INNER JOIN users u ON e.created_by = u.username
                WHERE e.exam_id = ? AND u.branch IN ($branchPlaceholders)";
        
        $params = array_merge([$examId], $regionBranches);
        $stmt = $conn->prepare($sql);
        $stmt->execute($params);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exam) {
            // Sınav sonuçlarını al (bölgesindeki öğrencilerden)
            $sql = "SELECT er.*, u.full_name, u.branch, u.class_section
                    FROM exam_results er
                    INNER JOIN users u ON er.username = u.username
                    WHERE er.exam_id = ? AND u.branch IN ($branchPlaceholders)";
            
            $params = array_merge([$examId], $regionBranches);
            
            if ($branchFilter) {
                $sql .= " AND u.branch = ?";
                $params[] = $branchFilter;
            }
            
            $sql .= " ORDER BY er.score DESC, er.created_at DESC";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Exception $e) {
        error_log("Results query error: " . $e->getMessage());
    }
}

if (!$exam) {
    header('Location: exams.php');
    exit;
}

// İstatistikler
$totalParticipants = count($results);
$totalScore = array_sum(array_column($results, 'score'));
$averageScore = $totalParticipants > 0 ? round($totalScore / $totalParticipants, 1) : 0;
$maxScore = $totalParticipants > 0 ? max(array_column($results, 'score')) : 0;
$minScore = $totalParticipants > 0 ? min(array_column($results, 'score')) : 0;
$averagePercentage = $totalParticipants > 0 ? round(array_sum(array_column($results, 'percentage')) / $totalParticipants, 1) : 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Sonuçları - <?php echo htmlspecialchars($exam['title']); ?></title>
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
                <h2>Sınav Sonuçları</h2>
                <p><?php echo htmlspecialchars($exam['title']); ?></p>
            </div>
            <div class="actions">
                <a href="exams.php" class="action-btn">
                    <i class="fas fa-arrow-left"></i> Sınavlara Dön
                </a>
            </div>
        </div>

        <!-- Sınav Bilgisi -->
        <div class="glass-panel" style="padding: 24px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(52,152,219,0.15) 0%, rgba(52,152,219,0.05) 100%); border-left: 4px solid #3498db;">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="width: 60px; height: 60px; border-radius: 12px; background: rgba(52,152,219,0.2); display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 2rem;">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0 0 8px 0; font-size: 1.5rem; font-weight: 700; color: #fff;">
                        <?php echo htmlspecialchars($exam['title']); ?>
                    </h2>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; color: var(--text-muted); font-size: 0.9rem;">
                        <span><i class="fas fa-id-badge"></i> <strong style="color: #fff;">ID:</strong> <?php echo htmlspecialchars($exam['exam_id']); ?></span>
                        <span><i class="fas fa-chalkboard-teacher"></i> <strong style="color: #fff;">Eğitmen:</strong> <?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                        <span><i class="fas fa-building"></i> <strong style="color: #fff;">Şube:</strong> <?php echo htmlspecialchars($exam['teacher_branch']); ?></span>
                        <span><i class="fas fa-calendar-alt"></i> <strong style="color: #fff;">Tarih:</strong> <?php echo date('d.m.Y H:i', strtotime($exam['created_at'])); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0.05) 100%); border-left: 4px solid #3b82f6;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $totalParticipants; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Katılımcı</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(34,197,94,0.15) 0%, rgba(34,197,94,0.05) 100%); border-left: 4px solid #22c55e;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $averagePercentage; ?>%
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Ortalama Başarı</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(245,158,11,0.15) 0%, rgba(245,158,11,0.05) 100%); border-left: 4px solid #f59e0b;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $maxScore; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">En Yüksek Puan</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(239,68,68,0.15) 0%, rgba(239,68,68,0.05) 100%); border-left: 4px solid #ef4444;">
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $minScore; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">En Düşük Puan</div>
            </div>
        </div>

        <!-- Filters -->
        <?php if (count($regionBranches) > 1): ?>
        <div class="glass-panel" style="padding: 20px; margin-bottom: 30px;">
            <form method="GET" style="display: flex; gap: 12px; align-items: center;">
                <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($examId); ?>">
                <select name="branch" onchange="this.form.submit()" class="modern-select" style="min-width: 200px;">
                    <option value="">🏢 Tüm Şubeler</option>
                    <?php foreach ($regionBranches as $branch): ?>
                        <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branchFilter === $branch ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($branch); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($branchFilter): ?>
                    <a href="results.php?exam_id=<?php echo urlencode($examId); ?>" class="clean-btn" style="padding: 12px 18px;">
                        <i class="fas fa-times"></i> Temizle
                    </a>
                <?php endif; ?>
            </form>
        </div>
        <?php endif; ?>

        <!-- Results Table -->
        <div class="glass-panel">
            <div style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(34,197,94,0.2); display: flex; align-items: center; justify-content: center; color: #22c55e;">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: #fff; margin-bottom: 2px;">
                            Sonuçlar
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                            <?php echo $totalParticipants; ?> katılımcı
                        </div>
                    </div>
                </div>
            </div>
            <div class="table-responsive" style="overflow-x: auto; max-height: 600px; overflow-y: auto;">
                <table class="table users-table" style="margin: 0;">
                    <thead style="position: sticky; top: 0; z-index: 10; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);">
                        <tr>
                            <th style="padding: 18px 24px;"><i class="fas fa-trophy" style="margin-right: 8px; opacity: 0.7;"></i>Sıra</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-user" style="margin-right: 8px; opacity: 0.7;"></i>Öğrenci</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-building" style="margin-right: 8px; opacity: 0.7;"></i>Şube</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-star" style="margin-right: 8px; opacity: 0.7;"></i>Puan</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-percentage" style="margin-right: 8px; opacity: 0.7;"></i>Başarı</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-check-circle" style="margin-right: 8px; opacity: 0.7;"></i>Doğru</th>
                            <th style="padding: 18px 24px;"><i class="fas fa-calendar-alt" style="margin-right: 8px; opacity: 0.7;"></i>Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($results)): ?>
                            <?php foreach ($results as $index => $result): 
                                $percentage = (float)($result['percentage'] ?? 0);
                                $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                $rank = $index + 1;
                            ?>
                                <tr style="transition: all 0.2s ease; animation: fadeInRow 0.3s ease <?php echo $index * 0.02; ?>s both;">
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 8px;">
                                            <?php if ($rank <= 3): ?>
                                                <span style="font-size: 1.2rem;"><?php echo ['🥇', '🥈', '🥉'][$rank - 1]; ?></span>
                                            <?php endif; ?>
                                            <span style="font-weight: 700; color: #fff; font-size: 1.1rem;"><?php echo $rank; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-info">
                                            <div class="user-avatar" style="box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                                <?php echo strtoupper(substr($result['full_name'] ?? 'U', 0, 1)); ?>
                                            </div>
                                            <div class="user-details">
                                                <div class="user-name" style="font-weight: 600; color: white;"><?php echo htmlspecialchars($result['full_name'] ?? 'Bilinmiyor'); ?></div>
                                                <div class="user-username" style="font-size: 0.85em; color: var(--text-muted);">@<?php echo htmlspecialchars($result['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-weight: 500; color: #e2e8f0;">
                                            <?php echo htmlspecialchars($result['branch'] ?? 'Belirtilmemiş'); ?>
                                        </div>
                                        <?php if (!empty($result['class_section'])): ?>
                                            <div style="font-size: 0.8rem; color: #7f8c8d; margin-top: 4px; display: inline-block; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;">
                                                <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($result['class_section']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div style="font-weight: 700; color: #fff; font-size: 1.2rem;">
                                            <?php echo number_format($result['score'] ?? 0, 1); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $badgeClass; ?>" style="font-size: 1rem; padding: 8px 14px;">
                                            <?php echo number_format($percentage, 1); ?>%
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 8px; align-items: center;">
                                            <span class="badge badge-success"><?php echo (int)($result['correct_answers'] ?? 0); ?></span>
                                            <span style="color: var(--text-muted);">/</span>
                                            <span style="color: var(--text-muted);"><?php echo (int)($result['total_questions'] ?? 0); ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9rem; color: #e2e8f0;">
                                            <?php echo date('d.m.Y', strtotime($result['created_at'] ?? $result['submit_time'] ?? '-')); ?>
                                        </div>
                                        <div style="font-size: 0.8rem; color: #7f8c8d;">
                                            <?php echo date('H:i', strtotime($result['created_at'] ?? $result['submit_time'] ?? '-')); ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 60px 30px;">
                                    <div style="width: 80px; height: 80px; margin: 0 auto 16px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: rgba(255,255,255,0.2);">
                                        <i class="fas fa-inbox"></i>
                                    </div>
                                    <div style="color: var(--text-muted); font-size: 0.95rem;">Henüz sonuç bulunmamaktadır</div>
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

