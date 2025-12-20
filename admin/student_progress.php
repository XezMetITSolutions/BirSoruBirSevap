<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('superadmin') && !$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

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

// Tüm öğrencileri çek
try {
    $sql = "SELECT DISTINCT u.username, u.full_name, u.class_section, u.branch 
            FROM users u 
            WHERE u.role = 'student' 
            ORDER BY u.class_section, u.full_name";
    $stmt = $conn->query($sql);
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Şubeleri çek
    $sql = "SELECT DISTINCT class_section FROM users WHERE role = 'student' AND class_section != '' ORDER BY class_section";
    $stmt = $conn->query($sql);
    $allSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Branşları çek
    $sql = "SELECT DISTINCT branch FROM users WHERE role = 'student' AND branch != '' ORDER BY branch";
    $stmt = $conn->query($sql);
    $allBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $allStudents = [];
    $allSections = [];
    $allBranches = [];
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
    <title>Öğrenci Gelişimi - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="css/admin-style.css">
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
                <p>Öğrenci performans ve istatistikleri</p>
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

        <!-- Student Info Card -->
        <?php if ($selectedStudentInfo): ?>
        <div class="card fade-in" style="background: linear-gradient(135deg, rgba(6, 133, 103, 0.1) 0%, rgba(52, 152, 219, 0.1) 100%);">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8em; font-weight: 700; box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);">
                    <?php echo strtoupper(substr($selectedStudentInfo['full_name'], 0, 1)); ?>
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0 0 5px 0; font-size: 1.5em;">
                        <i class="fas fa-user-graduate"></i> 
                        <?php echo htmlspecialchars($selectedStudentInfo['full_name']); ?>
                    </h2>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; color: #7f8c8d;">

                        <?php if ($selectedStudentInfo['branch']): ?>
                        <span><i class="fas fa-school"></i> <strong>Şube:</strong> <?php echo htmlspecialchars($selectedStudentInfo['branch']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-id-badge"></i> <strong>Kullanıcı Adı:</strong> <?php echo htmlspecialchars($selectedStudentInfo['username']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="card fade-in" style="text-align: center; padding: 50px;">
            <div style="font-size: 4em; color: #e1e8ed; margin-bottom: 20px;"><i class="fas fa-user-graduate"></i></div>
            <h2 style="color: var(--text-muted);">Öğrenci Gelişimini Görüntüle</h2>
            <p style="color: #95a5a6; max-width: 500px; margin: 0 auto;">Detaylı istatistikleri ve performans verilerini görmek için lütfen sol taraftaki filtrelerden veya arama kutusundan bir öğrenci seçiniz.</p>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                </div>
                <h3><?php echo count($filteredStudents); ?></h3>
                <p>Toplam Öğrenci</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-dumbbell"></i></div>
                </div>
                <h3><?php echo $pCount; ?></h3>
                <p>Alıştırma Sayısı</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                </div>
                <h3><?php echo $eCount; ?></h3>
                <p>Sınav Sayısı</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                </div>
                <h3><?php echo number_format($overallAvg, 1); ?>%</h3>
                <p>Genel Ortalama</p>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card fade-in">
            <h2><i class="fas fa-filter"></i> Filtreler</h2>
            
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="studentSearch" placeholder="Öğrenci ara..." value="">
            </div>

            <form method="GET" id="filterForm">
                <div class="filters">
                    <div class="filter-group">
                        <label><i class="fas fa-school"></i> Şube</label>
                        <select name="branch" id="branchSelect" onchange="this.form.submit()">
                            <option value="">Tüm Branşlar</option>
                            <?php foreach ($allBranches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branch===$selectedBranch?'selected':''; ?>>
                                    <?php echo htmlspecialchars($branch); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    

                    
                    <div class="filter-group">
                        <label><i class="fas fa-user"></i> Öğrenci Seç</label>
                        <select name="user" id="userSelect" onchange="this.form.submit()">
                            <?php if (empty($filteredStudents)): ?>
                                <option value="">Öğrenci bulunamadı</option>
                            <?php else: ?>
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
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Başlangıç Tarihi</label>
                        <input type="date" name="start_date" id="startDate" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-check"></i> Bitiş Tarihi</label>
                        <input type="date" name="end_date" id="endDate" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-percentage"></i> Min. Başarı Oranı</label>
                        <input type="number" name="min_score" id="minScore" placeholder="0" min="0" max="100" value="<?php echo htmlspecialchars($minScore); ?>">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-check"></i> Filtrele
                    </button>
                    <button type="button" class="filter-btn secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Sıfırla
                    </button>
                </div>
            </form>
        </div>

        <!-- Performance Chart -->
        <div class="card fade-in">
            <h2><i class="fas fa-chart-area"></i> Performans Grafiği</h2>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Tables Grid -->
        <div class="grid fade-in">
            <!-- Practice Table -->
            <div class="card">
                <h3><i class="fas fa-dumbbell"></i> Alıştırmalar (<?php echo $pCount; ?>)</h3>
                <div class="table-container">
                    <table class="table" id="practiceTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('practiceTable', 0)">Tarih <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 1)">Soru <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 2)">Doğru <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 3)">Yanlış <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 4)">Başarı <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentProgress['practice'])): ?>
                                <?php foreach ($studentProgress['practice'] as $row): 
                                    $percentage = (float)($row['percentage'] ?? 0);
                                    $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></td>
                                        <td><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                        <td><span class="badge badge-success"><?php echo (int)($row['correct_answers'] ?? 0); ?></span></td>
                                        <td><span class="badge badge-danger"><?php echo (int)($row['wrong_answers'] ?? 0); ?></span></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><br>Kayıt bulunamadı</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Exam Table -->
            <div class="card">
                <h3><i class="fas fa-file-alt"></i> Sınavlar (<?php echo $eCount; ?>)</h3>
                <div class="table-container">
                    <table class="table" id="examTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('examTable', 0)">Tarih <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 1)">Sınav ID <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 2)">Toplam <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 3)">Doğru <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 4)">Başarı <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentProgress['exams'])): ?>
                                <?php foreach ($studentProgress['exams'] as $row): 
                                    $percentage = (float)($row['percentage'] ?? 0);
                                    $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? $row['submit_time'] ?? '-'); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($row['exam_id'] ?? '-'); ?></span></td>
                                        <td><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                        <td><span class="badge badge-success"><?php echo (int)($row['correct_answers'] ?? 0); ?></span></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><br>Kayıt bulunamadı</td></tr>
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




