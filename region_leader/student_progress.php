<?php
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

// Filtre parametreleri
$selectedUser = $_GET['user'] ?? '';
$selectedSection = $_GET['class_section'] ?? '';
$selectedBranch = $_GET['branch'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$minScore = $_GET['min_score'] ?? '';

// Tüm öğrencileri çek (sadece bölgesindeki şubelerden)
try {
    $branchPlaceholders = str_repeat('?,', count($regionBranches) - 1) . '?';
    $sql = "SELECT DISTINCT u.username, u.full_name, u.class_section, u.branch 
            FROM users u 
            WHERE u.role = 'student' AND u.branch IN ($branchPlaceholders)
            ORDER BY u.class_section, u.full_name";
    $stmt = $conn->prepare($sql);
    $stmt->execute($regionBranches);
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Şubeleri çek (sadece bölgesindeki)
    $sql = "SELECT DISTINCT class_section FROM users WHERE role = 'student' AND class_section != '' AND branch IN ($branchPlaceholders) ORDER BY class_section";
    $stmt = $conn->prepare($sql);
    $stmt->execute($regionBranches);
    $allSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Branşları çek (sadece bölgesindeki)
    $sql = "SELECT DISTINCT branch FROM users WHERE role = 'student' AND branch != '' AND branch IN ($branchPlaceholders) ORDER BY branch";
    $stmt = $conn->prepare($sql);
    $stmt->execute($regionBranches);
    $allBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $allStudents = [];
    $allSections = [];
    $allBranches = [];
    error_log("Student progress query error: " . $e->getMessage());
}

// Filtrelenmiş öğrenciler
$filteredStudents = $allStudents;
if ($selectedSection) {
    $filteredStudents = array_filter($filteredStudents, function($s) use ($selectedSection) {
        return $s['class_section'] === $selectedSection;
    });
}
if ($selectedBranch) {
    $filteredStudents = array_filter($filteredStudents, function($s) use ($selectedBranch) {
        return $s['branch'] === $selectedBranch;
    });
}

// İlk öğrenciyi seçmeyi kaldırdık - Kullanıcı kendi seçmeli
// if (!$selectedUser && !empty($filteredStudents)) {
//    $selectedUser = reset($filteredStudents)['username'];
// }

// Seçili öğrencinin bilgilerini çek
$selectedStudentInfo = null;
foreach ($allStudents as $student) {
    if ($student['username'] === $selectedUser) {
        $selectedStudentInfo = $student;
        break;
    }
}

// JSON dosyalarından veri okuma fonksiyonu (fallback)
function readJsonFile($path) {
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// Öğrenci bazlı sonuçları çek
$studentProgress = [
    'practice' => [],
    'exams' => []
];

$debugInfo = [];

if ($selectedUser) {
    try {
        // Alıştırma sonuçları - Veritabanından
        $sql = "SELECT * FROM practice_results WHERE username = :username";
        if ($startDate) $sql .= " AND DATE(created_at) >= :start_date";
        if ($endDate) $sql .= " AND DATE(created_at) <= :end_date";
        if ($minScore) $sql .= " AND percentage >= :min_score";
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $selectedUser);
        if ($startDate) $stmt->bindParam(':start_date', $startDate);
        if ($endDate) $stmt->bindParam(':end_date', $endDate);
        if ($minScore) $stmt->bindParam(':min_score', $minScore);
        $stmt->execute();
        $studentProgress['practice'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo['db_practice_count'] = count($studentProgress['practice']);
        
        // Sınav sonuçları - Veritabanından
        $sql = "SELECT * FROM exam_results WHERE username = :username";
        if ($startDate) $sql .= " AND DATE(created_at) >= :start_date";
        if ($endDate) $sql .= " AND DATE(created_at) <= :end_date";
        if ($minScore) $sql .= " AND percentage >= :min_score";
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $selectedUser);
        if ($startDate) $stmt->bindParam(':start_date', $startDate);
        if ($endDate) $stmt->bindParam(':end_date', $endDate);
        if ($minScore) $stmt->bindParam(':min_score', $minScore);
        $stmt->execute();
        $studentProgress['exams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo['db_exam_count'] = count($studentProgress['exams']);
        
        // JSON fallback kaldırıldı - Sadece veritabanı
        if (empty($studentProgress['practice']) && empty($studentProgress['exams'])) {
            $debugInfo['source'] = 'Database (Empty)';
        } else {
            $debugInfo['source'] = 'Database';
        }
        
    } catch (Exception $e) {
        $debugInfo['error'] = $e->getMessage();
        // Hata durumunda boş array
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Gelişimi - <?php echo htmlspecialchars($userRegion); ?> Bölgesi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="../admin/css/dark-theme.css">
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
                <h2>Öğrenci Gelişimi</h2>
                <p><?php echo htmlspecialchars($userRegion); ?> Bölgesi - Öğrenci performans ve istatistikleri</p>
            </div>
        </div>
        <?php
        // Calculate stats for selected user
        $pCount = count($studentProgress['practice']);
        $eCount = count($studentProgress['exams']);
        
        // Calculate averages
        $avgPractice = 0;
        $avgExam = 0;
        
        if ($pCount > 0) {
            $total = 0;
            foreach ($studentProgress['practice'] as $p) {
                $total += (float)($p['percentage'] ?? 0);
            }
            $avgPractice = $total / $pCount;
        }
        
        if ($eCount > 0) {
            $total = 0;
            foreach ($studentProgress['exams'] as $e) {
                $total += (float)($e['percentage'] ?? 0);
            }
            $avgExam = $total / $eCount;
        }
        
        $totalActivities = $pCount + $eCount;
        $overallAvg = $totalActivities > 0 ? (($avgPractice * $pCount) + ($avgExam * $eCount)) / $totalActivities : 0;
        ?>

        <!-- Debug Info (sadece geliştirme için) -->
        <?php if (!empty($debugInfo) && isset($_GET['debug'])): ?>
        <div class="card fade-in" style="background: linear-gradient(135deg, rgba(243, 156, 18, 0.1) 0%, rgba(230, 126, 34, 0.1) 100%); border: 2px solid #f39c12;">
            <h3 style="color: #f39c12; margin-bottom: 15px;"><i class="fas fa-bug"></i> Debug Bilgileri</h3>
            <div style="font-family: monospace; font-size: 0.9em; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 10px;">
                <div><strong>Veri Kaynağı:</strong> <?php echo htmlspecialchars($debugInfo['source'] ?? 'Unknown'); ?></div>
                <div><strong>Kullanıcı:</strong> <?php echo htmlspecialchars($selectedUser); ?></div>
                <?php if (isset($debugInfo['db_practice_count'])): ?>
                <div><strong>DB Alıştırma:</strong> <?php echo $debugInfo['db_practice_count']; ?></div>
                <div><strong>DB Sınav:</strong> <?php echo $debugInfo['db_exam_count']; ?></div>
                <?php endif; ?>
                <?php if (isset($debugInfo['json_practice_count'])): ?>
                <div><strong>JSON Alıştırma:</strong> <?php echo $debugInfo['json_practice_count']; ?></div>
                <div><strong>JSON Sınav:</strong> <?php echo $debugInfo['json_exam_count']; ?></div>
                
                <?php if (!empty($debugInfo['all_practice'])): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                    <strong style="font-size: 1.1em;">📝 Tüm Alıştırmalar:</strong>
                    <table style="width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 0.85em;">
                        <thead>
                            <tr style="background: #f0f0f0;">
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">#</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Tarih</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Toplam</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Doğru</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Yanlış</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Yüzde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debugInfo['all_practice'] as $p): ?>
                            <tr>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo $p['index']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($p['date']); ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center;"><?php echo $p['total_questions']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; color: #27ae60; font-weight: bold;"><?php echo $p['correct']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; color: #e74c3c; font-weight: bold;"><?php echo $p['wrong']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo $p['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($debugInfo['all_exams'])): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                    <strong style="font-size: 1.1em;">📋 Tüm Sınavlar:</strong>
                    <table style="width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 0.85em;">
                        <thead>
                            <tr style="background: #f0f0f0;">
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">#</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Sınav ID</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Tarih</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Toplam</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Doğru</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Yüzde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debugInfo['all_exams'] as $e): ?>
                            <tr>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo $e['index']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($e['exam_id']); ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($e['date']); ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center;"><?php echo $e['total_questions']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; color: #27ae60; font-weight: bold;"><?php echo $e['correct']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo $e['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isset($debugInfo['error'])): ?>
                <div style="color: #e74c3c;"><strong>Hata:</strong> <?php echo htmlspecialchars($debugInfo['error']); ?></div>
                <?php endif; ?>
            </div>
            <div style="margin-top: 10px; font-size: 0.85em; color: #7f8c8d;">
                <i class="fas fa-info-circle"></i> Debug modunu kapatmak için URL'den <code>?debug</code> parametresini kaldırın.
            </div>
        </div>
        <?php endif; ?>

        <!-- Modern Student Info Card -->
        <?php if ($selectedStudentInfo): ?>
        <div class="glass-panel fade-in" style="padding: 28px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(6,133,103,0.15) 0%, rgba(6,133,103,0.05) 100%); border-left: 4px solid var(--primary);">
            <div style="display: flex; align-items: center; gap: 24px; flex-wrap: wrap;">
                <div style="width: 72px; height: 72px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 2rem; font-weight: 700; box-shadow: 0 8px 24px rgba(6,133,103,0.4); flex-shrink: 0;">
                    <?php echo strtoupper(substr($selectedStudentInfo['full_name'], 0, 1)); ?>
                </div>
                <div style="flex: 1; min-width: 200px;">
                    <h2 style="margin: 0 0 8px 0; font-size: 1.6rem; font-weight: 700; color: #fff; display: flex; align-items: center; gap: 10px;">
                        <i class="fas fa-user-graduate" style="color: var(--primary);"></i>
                        <?php echo htmlspecialchars($selectedStudentInfo['full_name']); ?>
                    </h2>
                    <div style="display: flex; gap: 20px; flex-wrap: wrap; margin-top: 12px;">
                        <?php if ($selectedStudentInfo['branch']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); padding: 8px 14px; border-radius: 10px;">
                            <i class="fas fa-building" style="color: var(--primary);"></i>
                            <span style="color: var(--text-muted); font-size: 0.9rem;"><strong style="color: #fff;">Şube:</strong> <?php echo htmlspecialchars($selectedStudentInfo['branch']); ?></span>
                        </div>
                        <?php endif; ?>
                        <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); padding: 8px 14px; border-radius: 10px;">
                            <i class="fas fa-id-badge" style="color: var(--primary);"></i>
                            <span style="color: var(--text-muted); font-size: 0.9rem;"><strong style="color: #fff;">Kullanıcı:</strong> <?php echo htmlspecialchars($selectedStudentInfo['username']); ?></span>
                        </div>
                        <?php if ($selectedStudentInfo['class_section']): ?>
                        <div style="display: flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.08); padding: 8px 14px; border-radius: 10px;">
                            <i class="fas fa-layer-group" style="color: var(--primary);"></i>
                            <span style="color: var(--text-muted); font-size: 0.9rem;"><strong style="color: #fff;">Sınıf:</strong> <?php echo htmlspecialchars($selectedStudentInfo['class_section']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="glass-panel fade-in" style="text-align: center; padding: 60px 30px; margin-bottom: 30px;">
            <div style="width: 120px; height: 120px; margin: 0 auto 24px; border-radius: 50%; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%); display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: rgba(255,255,255,0.15);">
                <i class="fas fa-user-graduate"></i>
            </div>
            <h2 style="color: #fff; margin-bottom: 12px; font-size: 1.5rem; font-weight: 600;">Öğrenci Gelişimini Görüntüle</h2>
            <p style="color: var(--text-muted); max-width: 500px; margin: 0 auto; font-size: 0.95rem;">Detaylı istatistikleri ve performans verilerini görmek için lütfen aşağıdaki filtrelerden bir öğrenci seçiniz.</p>
        </div>
        <?php endif; ?>

        <!-- Modern Stats Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 30px;">
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(59,130,246,0.15) 0%, rgba(59,130,246,0.05) 100%); border-left: 4px solid #3b82f6; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(59,130,246,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(59,130,246,0.2); display: flex; align-items: center; justify-content: center; color: #3b82f6; font-size: 1.5rem;">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo count($filteredStudents); ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Toplam Öğrenci</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(6,133,103,0.15) 0%, rgba(6,133,103,0.05) 100%); border-left: 4px solid var(--primary); transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(6,133,103,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary); font-size: 1.5rem;">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $pCount; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Alıştırma Sayısı</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(52,152,219,0.15) 0%, rgba(52,152,219,0.05) 100%); border-left: 4px solid #3498db; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(52,152,219,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(52,152,219,0.2); display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 1.5rem;">
                        <i class="fas fa-file-alt"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo $eCount; ?>
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Sınav Sayısı</div>
            </div>
            <div class="glass-panel" style="padding: 24px; background: linear-gradient(135deg, rgba(245,158,11,0.15) 0%, rgba(245,158,11,0.05) 100%); border-left: 4px solid #f59e0b; transition: all 0.3s ease; cursor: default;" onmouseover="this.style.transform='translateY(-4px)'; this.style.boxShadow='0 8px 24px rgba(245,158,11,0.2)'" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                    <div style="width: 48px; height: 48px; border-radius: 12px; background: rgba(245,158,11,0.2); display: flex; align-items: center; justify-content: center; color: #f59e0b; font-size: 1.5rem;">
                        <i class="fas fa-trophy"></i>
                    </div>
                </div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #fff; margin-bottom: 4px; line-height: 1;">
                    <?php echo number_format($overallAvg, 1); ?>%
                </div>
                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Genel Ortalama</div>
            </div>
        </div>

        <!-- Modern Filters -->
        <div class="glass-panel fade-in" style="padding: 24px; margin-bottom: 30px; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px;">
                <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                    <i class="fas fa-filter"></i>
                </div>
                <h2 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #fff;">Filtreler</h2>
            </div>
            
            <!-- Search Box -->
            <div style="position: relative; margin-bottom: 20px;">
                <i class="fas fa-search" style="position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--text-muted); z-index: 1;"></i>
                <input type="text" id="studentSearch" placeholder="Öğrenci ara..." value="" 
                       style="width: 100%; padding: 12px 16px 12px 44px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 12px; color: #fff; font-size: 0.95rem; transition: all 0.3s;"
                       onfocus="this.style.background='rgba(0,0,0,0.4)'; this.style.borderColor='var(--primary)'; this.style.boxShadow='0 0 0 3px rgba(6,133,103,0.1)'"
                       onblur="this.style.background='rgba(0,0,0,0.3)'; this.style.borderColor='rgba(255,255,255,0.1)'; this.style.boxShadow='none'">
            </div>

            <form method="GET" id="filterForm">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 20px;">
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                            <i class="fas fa-building"></i> Şube
                        </label>
                        <select name="branch" id="branchSelect" onchange="this.form.submit()" class="modern-select" style="width: 100%;">
                            <option value="">🏢 Tüm Branşlar</option>
                            <?php foreach ($allBranches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branch===$selectedBranch?'selected':''; ?>>
                                    <?php echo htmlspecialchars($branch); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                            <i class="fas fa-user-graduate"></i> Öğrenci Seç
                        </label>
                        <select name="user" id="userSelect" onchange="this.form.submit()" class="modern-select" style="width: 100%;">
                            <option value="">👤 Öğrenci Seçin</option>
                            <?php if (!empty($filteredStudents)): ?>
                                <?php foreach ($filteredStudents as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['username']); ?>" 
                                            <?php echo $student['username']===$selectedUser?'selected':''; ?>>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                        <?php if ($student['class_section']): ?>
                                            (<?php echo htmlspecialchars($student['class_section']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                            <i class="fas fa-calendar-alt"></i> Başlangıç Tarihi
                        </label>
                        <input type="date" name="start_date" id="startDate" value="<?php echo htmlspecialchars($startDate); ?>" 
                               class="modern-select" style="width: 100%; padding: 12px 16px;">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                            <i class="fas fa-calendar-check"></i> Bitiş Tarihi
                        </label>
                        <input type="date" name="end_date" id="endDate" value="<?php echo htmlspecialchars($endDate); ?>" 
                               class="modern-select" style="width: 100%; padding: 12px 16px;">
                    </div>
                    
                    <div>
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: var(--text-muted); font-size: 0.9rem;">
                            <i class="fas fa-percentage"></i> Min. Başarı Oranı
                        </label>
                        <input type="number" name="min_score" id="minScore" placeholder="0" min="0" max="100" value="<?php echo htmlspecialchars($minScore); ?>" 
                               class="modern-select" style="width: 100%; padding: 12px 16px;">
                    </div>
                </div>
                
                <div style="display: flex; gap: 12px; margin-top: 20px;">
                    <button type="submit" class="btn btn-primary" style="padding: 12px 24px;">
                        <i class="fas fa-check"></i> Filtrele
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="resetFilters()" style="padding: 12px 24px;">
                        <i class="fas fa-redo"></i> Sıfırla
                    </button>
                </div>
            </form>
        </div>

        <!-- Modern Performance Chart -->
        <div class="glass-panel fade-in" style="padding: 24px; margin-bottom: 30px;">
            <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 1px solid rgba(255,255,255,0.1);">
                <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                    <i class="fas fa-chart-area"></i>
                </div>
                <h2 style="margin: 0; font-size: 1.2rem; font-weight: 700; color: #fff;">Performans Grafiği</h2>
            </div>
            <div class="chart-container" style="height: 350px; position: relative;">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Modern Tables Grid -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(500px, 1fr)); gap: 30px; margin-bottom: 30px;">
            <!-- Practice Table -->
            <div class="glass-panel fade-in" style="overflow: hidden;">
                <div style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(6,133,103,0.2); display: flex; align-items: center; justify-content: center; color: var(--primary);">
                            <i class="fas fa-dumbbell"></i>
                        </div>
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #fff;">Alıştırmalar</div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">Toplam <?php echo $pCount; ?> kayıt</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                    <table class="table users-table" id="practiceTable" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);">
                            <tr>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('practiceTable', 0)">
                                    <i class="fas fa-calendar-alt" style="margin-right: 8px; opacity: 0.7;"></i>Tarih <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('practiceTable', 1)">
                                    <i class="fas fa-question-circle" style="margin-right: 8px; opacity: 0.7;"></i>Soru <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('practiceTable', 2)">
                                    <i class="fas fa-check-circle" style="margin-right: 8px; opacity: 0.7;"></i>Doğru <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('practiceTable', 3)">
                                    <i class="fas fa-times-circle" style="margin-right: 8px; opacity: 0.7;"></i>Yanlış <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('practiceTable', 4)">
                                    <i class="fas fa-percentage" style="margin-right: 8px; opacity: 0.7;"></i>Başarı <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentProgress['practice'])): ?>
                                <?php foreach ($studentProgress['practice'] as $index => $row): 
                                    $percentage = (float)($row['percentage'] ?? 0);
                                    $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr style="transition: all 0.2s ease; animation: fadeInRow 0.3s ease <?php echo $index * 0.02; ?>s both;">
                                        <td style="padding: 16px 24px;"><?php echo date('d.m.Y H:i', strtotime($row['created_at'] ?? '-')); ?></td>
                                        <td style="padding: 16px 24px; font-weight: 500;"><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                        <td style="padding: 16px 24px;"><span class="badge badge-success"><?php echo (int)($row['correct_answers'] ?? 0); ?></span></td>
                                        <td style="padding: 16px 24px;"><span class="badge badge-danger"><?php echo (int)($row['wrong_answers'] ?? 0); ?></span></td>
                                        <td style="padding: 16px 24px;"><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px 30px;">
                                        <div style="width: 80px; height: 80px; margin: 0 auto 16px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: rgba(255,255,255,0.2);">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <div style="color: var(--text-muted); font-size: 0.95rem;">Kayıt bulunamadı</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Exam Table -->
            <div class="glass-panel fade-in" style="overflow: hidden;">
                <div style="padding: 24px; border-bottom: 1px solid rgba(255,255,255,0.1); display: flex; justify-content: space-between; align-items: center; background: linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(255,255,255,0.02) 100%);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <div style="width: 40px; height: 40px; border-radius: 12px; background: rgba(52,152,219,0.2); display: flex; align-items: center; justify-content: center; color: #3498db;">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <div style="font-size: 1.1rem; font-weight: 700; color: #fff;">Sınavlar</div>
                            <div style="font-size: 0.85rem; color: var(--text-muted);">Toplam <?php echo $eCount; ?> kayıt</div>
                        </div>
                    </div>
                </div>
                <div class="table-responsive" style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                    <table class="table users-table" id="examTable" style="margin: 0;">
                        <thead style="position: sticky; top: 0; z-index: 10; background: rgba(15,23,42,0.95); backdrop-filter: blur(20px);">
                            <tr>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('examTable', 0)">
                                    <i class="fas fa-calendar-alt" style="margin-right: 8px; opacity: 0.7;"></i>Tarih <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('examTable', 1)">
                                    <i class="fas fa-id-badge" style="margin-right: 8px; opacity: 0.7;"></i>Sınav ID <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('examTable', 2)">
                                    <i class="fas fa-list-ol" style="margin-right: 8px; opacity: 0.7;"></i>Toplam <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('examTable', 3)">
                                    <i class="fas fa-check-circle" style="margin-right: 8px; opacity: 0.7;"></i>Doğru <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                                <th style="padding: 18px 24px; cursor: pointer;" onclick="sortTable('examTable', 4)">
                                    <i class="fas fa-percentage" style="margin-right: 8px; opacity: 0.7;"></i>Başarı <i class="fas fa-sort" style="margin-left: 4px; opacity: 0.5;"></i>
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentProgress['exams'])): ?>
                                <?php foreach ($studentProgress['exams'] as $index => $row): 
                                    $percentage = (float)($row['percentage'] ?? 0);
                                    $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr style="transition: all 0.2s ease; animation: fadeInRow 0.3s ease <?php echo $index * 0.02; ?>s both;">
                                        <td style="padding: 16px 24px;"><?php echo date('d.m.Y H:i', strtotime($row['created_at'] ?? $row['submit_time'] ?? '-')); ?></td>
                                        <td style="padding: 16px 24px;"><span class="badge badge-info"><?php echo htmlspecialchars($row['exam_id'] ?? '-'); ?></span></td>
                                        <td style="padding: 16px 24px; font-weight: 500;"><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                        <td style="padding: 16px 24px;"><span class="badge badge-success"><?php echo (int)($row['correct_answers'] ?? 0); ?></span></td>
                                        <td style="padding: 16px 24px;"><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 60px 30px;">
                                        <div style="width: 80px; height: 80px; margin: 0 auto 16px; border-radius: 50%; background: rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: rgba(255,255,255,0.2);">
                                            <i class="fas fa-inbox"></i>
                                        </div>
                                        <div style="color: var(--text-muted); font-size: 0.95rem;">Kayıt bulunamadı</div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const body = document.body;
            
            // Load saved theme
            const savedTheme = localStorage.getItem('student_progress_theme') || 'dark';
            if (savedTheme === 'dark') {
                body.classList.add('dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i> Tema';
            } else {
                body.classList.remove('dark');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i> Tema';
            }
            
            themeToggle.addEventListener('click', function() {
                body.classList.toggle('dark');
                const isDark = body.classList.contains('dark');
                localStorage.setItem('student_progress_theme', isDark ? 'dark' : 'light');
                themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i> Tema' : '<i class="fas fa-moon"></i> Tema';
            });
        });

        // Student Search
        document.getElementById('studentSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const select = document.getElementById('userSelect');
            const options = select.options;
            
            for (let i = 0; i < options.length; i++) {
                const optionText = options[i].text.toLowerCase();
                if (optionText.includes(searchTerm)) {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        });

        // Reset Filters
        function resetFilters() {
            window.location.href = 'student_progress.php';
        }

        // Table Sorting
        function sortTable(tableId, column) {
            const table = document.getElementById(tableId);
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Skip if empty state
            if (rows.length === 1 && rows[0].cells.length === 1) return;
            
            const isAscending = table.dataset.sortOrder === 'asc';
            
            rows.sort((a, b) => {
                let aValue = a.cells[column].textContent.trim();
                let bValue = b.cells[column].textContent.trim();
                
                // Try to parse as number
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? aNum - bNum : bNum - aNum;
                }
                
                // String comparison
                return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            });
            
            // Update table
            rows.forEach(row => tbody.appendChild(row));
            
            // Toggle sort order
            table.dataset.sortOrder = isAscending ? 'desc' : 'asc';
        }

        // Performance Chart
        <?php
        // Prepare chart data
        $chartLabels = [];
        $practiceData = [];
        $examData = [];
        
        // Combine and sort all activities by date
        $allActivities = [];
        
        if (!empty($studentProgress['practice'])) {
            foreach ($studentProgress['practice'] as $p) {
                $date = $p['created_at'] ?? '';
                if ($date) {
                    $allActivities[] = ['date' => $date, 'type' => 'practice', 'percentage' => (float)($p['percentage'] ?? 0)];
                }
            }
        }
        
        if (!empty($studentProgress['exams'])) {
            foreach ($studentProgress['exams'] as $e) {
                $date = $e['created_at'] ?? $e['submit_time'] ?? '';
                if ($date) {
                    $allActivities[] = ['date' => $date, 'type' => 'exam', 'percentage' => (float)($e['percentage'] ?? 0)];
                }
            }
        }
        
        // Sort by date
        usort($allActivities, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        // Take last 10 activities
        $recentActivities = array_slice($allActivities, -10);
        
        foreach ($recentActivities as $activity) {
            $chartLabels[] = substr($activity['date'], 0, 10);
            if ($activity['type'] === 'practice') {
                $practiceData[] = $activity['percentage'];
                $examData[] = null;
            } else {
                $examData[] = $activity['percentage'];
                $practiceData[] = null;
            }
        }
        ?>
        
        const ctx = document.getElementById('performanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [
                        {
                            label: 'Alıştırma',
                            data: <?php echo json_encode($practiceData); ?>,
                            borderColor: '#068567',
                            backgroundColor: 'rgba(6, 133, 103, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Sınav',
                            data: <?php echo json_encode($examData); ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>





