<?php
/**
 * Süper Admin - Raporlar
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../QuestionLoader.php'; // QuestionLoader sınıfını dahil et

$auth = Auth::getInstance();
$db = Database::getInstance();
$conn = $db->getConnection();

// Admin kontrolü
if (!$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Kurum listesi
$institutions = [
    'IQRA Bludenz',
    'IQRA Bregenz', 
    'IQRA Dornbirn',
    'IQRA Feldkirch',
    'IQRA Hall in Tirol',
    'IQRA Innsbruck',
    'IQRA Jenbach',
    'IQRA Lustenau',
    'IQRA Radfeld',
    'IQRA Reutte',
    'IQRA Vomp',
    'IQRA Wörgl',
    'IQRA Zirl'
];

// Gerçek rapor verilerini yükle
$allUsers = $auth->getAllUsers();

// Kullanıcı istatistikleri
$totalUsers = count($allUsers);
$activeUsers = 0;
$institutionStats = [];

foreach ($allUsers as $u) {
    if ($u['role'] !== 'superadmin') {
        $institution = $u['branch'] ?? $u['institution'] ?? 'Bilinmiyor';
        if (!isset($institutionStats[$institution])) {
            $institutionStats[$institution] = ['users' => 0, 'exams' => 0, 'questions' => 0];
        }
        $institutionStats[$institution]['users']++;
        $activeUsers++;
    }
}

// Sınav sayısını veritabanından hesapla
try {
    $sql = "SELECT COUNT(*) FROM exams";
    $stmt = $conn->query($sql);
    $totalExams = $stmt->fetchColumn();
} catch (Exception $e) {
    $totalExams = 0;
}

// Soru sayısını hesapla
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();
$questions = $_SESSION['all_questions'] ?? [];
$totalQuestions = count($questions);

// Soru Bankası ve Kategori İstatistiklerini Hesapla
$bankStats = [];
$categoryStats = [];

foreach ($questions as $q) {
    $bank = $q['bank'] ?? 'Diğer';
    $category = $q['category'] ?? 'Genel';

    if (!isset($bankStats[$bank])) {
        $bankStats[$bank] = 0;
    }
    $bankStats[$bank]++;

    if (!isset($categoryStats[$category])) {
        $categoryStats[$category] = 0;
    }
    $categoryStats[$category]++;
}

// Sıralama
arsort($bankStats);
arsort($categoryStats);

// --- Gelişmiş Raporlar İçin Veri Çekme ---

// 1. Sınav Geçmişi (Şube ve Tarihe Göre)
$examHistory = [];
try {
    $sql = "SELECT 
                e.exam_id,
                e.title, 
                e.created_at, 
                e.class_section, 
                e.teacher_institution,
                (SELECT COUNT(*) FROM exam_results r WHERE r.exam_id = e.exam_id) as participant_count 
            FROM exams e 
            ORDER BY e.created_at DESC 
            LIMIT 50";
    $stmt = $conn->query($sql);
    $examHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Tablo yoksa veya hata varsa boş dizi
}

// 2. Alıştırma Katılım Oranları (Şube Bazlı)
// Mevcut institutionStats dizisine "active_practice_users" ekleyeceğiz
try {
    $sql = "SELECT 
                COALESCE(NULLIF(u.branch, ''), NULLIF(u.institution, ''), 'Bilinmiyor') as branch_name,
                COUNT(DISTINCT p.username) as active_count
            FROM practice_results p
            JOIN users u ON p.username = u.username
            WHERE u.role = 'student'
            GROUP BY branch_name";
    $stmt = $conn->query($sql);
    $practiceStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // [branch => count]
    
    // institutionStats ile birleştir
    foreach ($institutionStats as $inst => $stats) {
        $institutionStats[$inst]['active_practice'] = $practiceStats[$inst] ?? 0;
        $institutionStats[$inst]['participation_rate'] = $stats['users'] > 0 ? round(($institutionStats[$inst]['active_practice'] / $stats['users']) * 100, 1) : 0;
    }
} catch (Exception $e) {
    // Hata durumunda 0 varsay
    foreach ($institutionStats as $inst => $stats) {
        $institutionStats[$inst]['active_practice'] = 0;
        $institutionStats[$inst]['participation_rate'] = 0;
    }
}

// 3. Konu Başarı Analizi (En Güçlü/Zayıf)
$topicPerformance = [];
try {
    $sql = "SELECT 
                category, 
                AVG(percentage) as avg_score, 
                COUNT(*) as total_attempts 
            FROM practice_results 
            WHERE category IS NOT NULL AND category != ''
            GROUP BY category 
            ORDER BY avg_score DESC";
    $stmt = $conn->query($sql);
    $topicPerformance = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Hata durumunda boş
}

// 4. Zor Soru Analizi (En çok yanlış yapılanlar)
$hardQuestions = [];
try {
    // Son 500 alıştırma sonucunu çek
    $sql = "SELECT detailed_results FROM practice_results ORDER BY created_at DESC LIMIT 500";
    $stmt = $conn->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $questionStats = []; // [question_id => ['wrong' => 0, 'total' => 0]]
    
    foreach ($results as $json) {
        $details = json_decode($json, true);
        if (is_array($details)) {
            foreach ($details as $q) {
                if (isset($q['id'])) {
                    $qid = $q['id'];
                    if (!isset($questionStats[$qid])) {
                        $questionStats[$qid] = ['wrong' => 0, 'total' => 0];
                    }
                    $questionStats[$qid]['total']++;
                    
                    // is_correct kontrolü (bazen string "true"/"false" bazen boolean olabilir)
                    $isCorrect = false;
                    if (isset($q['is_correct'])) {
                        $isCorrect = filter_var($q['is_correct'], FILTER_VALIDATE_BOOLEAN);
                    } elseif (isset($q['user_answer']) && isset($q['correct_answer'])) {
                        $isCorrect = ($q['user_answer'] === $q['correct_answer']);
                    }
                    
                    if (!$isCorrect) {
                        $questionStats[$qid]['wrong']++;
                    }
                }
            }
        }
    }
    
    // Yanlış yapılma oranına göre sırala
    $hardQuestionIds = [];
    foreach ($questionStats as $qid => $stats) {
        if ($stats['total'] > 2) { // En az 3 kez sorulmuş olsun
            $wrongRate = $stats['wrong'] / $stats['total'];
            if ($wrongRate > 0.3) { // %30'dan fazla yanlış yapılmışsa
                $hardQuestionIds[$qid] = $wrongRate;
            }
        }
    }
    
    arsort($hardQuestionIds);
    $topHardIds = array_slice(array_keys($hardQuestionIds), 0, 10); // İlk 10
    
    // Soru metinlerini veritabanından çek
    if (!empty($topHardIds)) {
        $placeholders = implode(',', array_fill(0, count($topHardIds), '?'));
        $sql = "SELECT id, question, category, bank FROM questions WHERE id IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->execute($topHardIds);
        $questionsInfo = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Verileri birleştir
        foreach ($questionsInfo as $qInfo) {
            $qid = $qInfo['id'];
            $stats = $questionStats[$qid];
            $hardQuestions[] = [
                'question' => $qInfo['question'],
                'category' => $qInfo['category'],
                'bank' => $qInfo['bank'],
                'wrong_count' => $stats['wrong'],
                'total_count' => $stats['total'],
                'wrong_rate' => round(($stats['wrong'] / $stats['total']) * 100, 1)
            ];
        }
        
        // Tekrar oranına göre sırala (DB'den karışık gelebilir)
        usort($hardQuestions, function($a, $b) {
            return $b['wrong_rate'] <=> $a['wrong_rate'];
        });
    }
    
} catch (Exception $e) {
    // Hata yoksay
}

// 5. Risk Analizi (Erken Uyarı)
$riskyStudents = [];
try {
    // 15 gündür girmeyenler VEYA ortalaması %50 altı olanlar
    $sql = "SELECT 
                u.username, 
                u.full_name, 
                u.branch, 
                u.last_login,
                (SELECT AVG(percentage) FROM practice_results pr WHERE pr.username = u.username) as avg_score
            FROM users u
            WHERE u.role = 'student'
            HAVING (last_login < DATE_SUB(NOW(), INTERVAL 15 DAY) OR last_login IS NULL) 
               OR (avg_score IS NOT NULL AND avg_score < 50)
            ORDER BY last_login ASC
            LIMIT 50";
    $stmt = $conn->query($sql);
    $riskyStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Hata yoksay
}

// 6. Şube Karşılaştırması
$branchComparison = [];
try {
    $sql = "SELECT 
                COALESCE(NULLIF(u.branch, ''), NULLIF(u.institution, ''), 'Bilinmiyor') as branch_name,
                AVG(p.percentage) as avg_score,
                COUNT(p.id) as total_practices
            FROM practice_results p
            JOIN users u ON p.username = u.username
            WHERE u.role = 'student'
            GROUP BY branch_name
            ORDER BY avg_score DESC";
    $stmt = $conn->query($sql);
    $branchComparison = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Hata yoksay
}

$reportData = [
    'total_users' => $totalUsers,
    'active_users' => $activeUsers,
    'total_exams' => $totalExams,
    'total_questions' => $totalQuestions,
    'institution_stats' => $institutionStats,
    'bank_stats' => $bankStats,
    'category_stats' => $categoryStats,
    'exam_history' => $examHistory,
    'topic_performance' => $topicPerformance,
    'hard_questions' => $hardQuestions,
    'risky_students' => $riskyStudents,
    'branch_comparison' => $branchComparison
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Bir Soru Bir Sevap</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo img {
            height: 50px;
            width: auto;
        }

        .logo h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .logo p {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .breadcrumb {
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #089b76;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
            color: #089b76;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .report-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .report-section h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .data-table th,
        .data-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }

        .data-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .data-table tr:hover {
            background: #f8f9fa;
        }

        .btn {
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            margin: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-success {
            background: #27ae60;
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.8em;
            border-radius: 15px;
        }

        .export-options {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .progress-bar-container {
            flex: 1; 
            height: 8px; 
            background: #e0e0e0; 
            border-radius: 4px; 
            overflow: hidden; 
            width: 100px;
        }
        
        .progress-bar {
            height: 100%; 
            border-radius: 4px;
        }
        
        .badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .badge-danger {
            background: #ffebee;
            color: #c62828;
        }
        
        .badge-warning {
            background: #fff3e0;
            color: #ef6c00;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
            
            .data-table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Bir Soru Bir Sevap Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p>Süper Admin Paneli</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;">Süper Admin</div>
                </div>
                <a href="../logout.php" class="logout-btn">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > Raporlar
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-users"></i></div>
                <div class="stat-number"><?php echo $reportData['total_users']; ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-user-check"></i></div>
                <div class="stat-number"><?php echo $reportData['active_users']; ?></div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                <div class="stat-number"><?php echo $reportData['total_exams']; ?></div>
                <div class="stat-label">Toplam Sınav</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon"><i class="fas fa-question-circle"></i></div>
                <div class="stat-number"><?php echo $reportData['total_questions']; ?></div>
                <div class="stat-label">Toplam Soru</div>
            </div>
        </div>

        <div class="reports-grid">
            
            <!-- 1. Risk Analizi (Erken Uyarı) -->
            <div class="report-section" style="border-left: 5px solid #e74c3c;">
                <h2><i class="fas fa-exclamation-triangle" style="color: #e74c3c;"></i> Risk Analizi (Erken Uyarı Sistemi)</h2>
                <p style="margin-bottom: 15px; color: #666;">15 gündür giriş yapmayan veya başarı ortalaması %50'nin altında olan öğrenciler.</p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Öğrenci Adı</th>
                            <th>Şube</th>
                            <th>Son Giriş</th>
                            <th>Başarı Ortalaması</th>
                            <th>Risk Durumu</th>
                            <th>İşlem</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData['risky_students'])): ?>
                            <tr><td colspan="6" style="text-align: center; color: #27ae60;"><i class="fas fa-check-circle"></i> Harika! Riskli durumda öğrenci bulunmuyor.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData['risky_students'] as $student): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['branch']); ?></td>
                                    <td>
                                        <?php 
                                        if ($student['last_login']) {
                                            $daysAgo = floor((time() - strtotime($student['last_login'])) / (60 * 60 * 24));
                                            echo $daysAgo . " gün önce";
                                        } else {
                                            echo "Hiç girmedi";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($student['avg_score'] !== null) {
                                            echo "%" . round($student['avg_score'], 1);
                                        } else {
                                            echo "-";
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if (!$student['last_login'] || (isset($daysAgo) && $daysAgo > 15)) {
                                            echo '<span class="badge badge-danger">Devamsızlık</span> ';
                                        }
                                        if ($student['avg_score'] !== null && $student['avg_score'] < 50) {
                                            echo '<span class="badge badge-warning">Düşük Başarı</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <a href="student_progress.php?user=<?php echo urlencode($student['username']); ?>" class="btn btn-sm btn-success">Karne Görüntüle</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 2. Zor Soru Analizi -->
            <div class="report-section">
                <h2><i class="fas fa-bomb" style="color: #e67e22;"></i> Zor Soru Analizi (En Çok Yanlış Yapılanlar)</h2>
                <p style="margin-bottom: 15px; color: #666;">Öğrencilerin en çok hata yaptığı sorular (Son 500 alıştırma baz alınmıştır).</p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Soru</th>
                            <th>Konu / Banka</th>
                            <th>Yanlış / Toplam</th>
                            <th>Hata Oranı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData['hard_questions'])): ?>
                            <tr><td colspan="4" style="text-align: center; color: #7f8c8d;">Yeterli veri yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData['hard_questions'] as $q): ?>
                                <tr>
                                    <td style="max-width: 400px;">
                                        <div style="font-weight: bold; margin-bottom: 5px;"><?php echo htmlspecialchars(mb_substr($q['question'], 0, 100)) . '...'; ?></div>
                                    </td>
                                    <td>
                                        <div style="font-size: 0.9em;"><?php echo htmlspecialchars($q['category']); ?></div>
                                        <div style="font-size: 0.8em; color: #7f8c8d;"><?php echo htmlspecialchars($q['bank']); ?></div>
                                    </td>
                                    <td>
                                        <span style="color: #c62828; font-weight: bold;"><?php echo $q['wrong_count']; ?></span> / <?php echo $q['total_count']; ?>
                                    </td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="background: #c62828; width: <?php echo $q['wrong_rate']; ?>%;"></div>
                                            </div>
                                            <span style="font-weight: bold; color: #c62828;">%<?php echo $q['wrong_rate']; ?></span>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 3. Şube Karşılaştırması -->
            <div class="report-section">
                <h2><i class="fas fa-trophy" style="color: #f1c40f;"></i> Şube Başarı Sıralaması</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sıra</th>
                            <th>Şube / Kurum</th>
                            <th>Toplam Alıştırma</th>
                            <th>Başarı Ortalaması</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData['branch_comparison'])): ?>
                            <tr><td colspan="4" style="text-align: center; color: #7f8c8d;">Veri yok.</td></tr>
                        <?php else: ?>
                            <?php $rank = 1; foreach ($reportData['branch_comparison'] as $branch): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        if ($rank == 1) echo '<i class="fas fa-medal" style="color: #f1c40f;"></i> 1.';
                                        elseif ($rank == 2) echo '<i class="fas fa-medal" style="color: #bdc3c7;"></i> 2.';
                                        elseif ($rank == 3) echo '<i class="fas fa-medal" style="color: #cd7f32;"></i> 3.';
                                        else echo $rank . '.';
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($branch['branch_name']); ?></td>
                                    <td><?php echo $branch['total_practices']; ?></td>
                                    <td>
                                        <span style="font-weight: bold; color: #27ae60; font-size: 1.1em;">%<?php echo round($branch['avg_score'], 1); ?></span>
                                    </td>
                                </tr>
                            <?php $rank++; endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 4. Konu Başarı Analizi -->
            <div class="report-section">
                <h2><i class="fas fa-chart-pie"></i> Konu Başarı Analizi (Müfredat Takibi)</h2>
                <p style="margin-bottom: 15px; color: #666;">Öğrencilerin alıştırmalarda gösterdiği performans analizi.</p>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Konu / Kategori</th>
                            <th>Çözülen Soru</th>
                            <th>Başarı Ortalaması</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData['topic_performance'])): ?>
                            <tr><td colspan="4" style="text-align: center; color: #7f8c8d;">Henüz konu analizi için yeterli veri yok.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData['topic_performance'] as $topic): ?>
                                <?php 
                                $avg = round($topic['avg_score'], 1);
                                $color = $avg >= 80 ? '#27ae60' : ($avg >= 50 ? '#f39c12' : '#e74c3c');
                                $status = $avg >= 80 ? 'Çok İyi' : ($avg >= 50 ? 'Orta' : 'Geliştirilmeli');
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($topic['category']); ?></td>
                                    <td><?php echo $topic['total_attempts']; ?></td>
                                    <td>
                                        <div style="display: flex; align-items: center; gap: 10px;">
                                            <div class="progress-bar-container">
                                                <div class="progress-bar" style="background: <?php echo $color; ?>; width: <?php echo $avg; ?>%;"></div>
                                            </div>
                                            <span style="font-weight: bold; color: <?php echo $color; ?>">%<?php echo $avg; ?></span>
                                        </div>
                                    </td>
                                    <td>
                                        <span style="color: <?php echo $color; ?>; font-weight: bold;"><?php echo $status; ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 5. Sınav Geçmişi -->
            <div class="report-section">
                <h2><i class="fas fa-history"></i> Sınav Geçmişi ve Katılım</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Sınav Adı</th>
                            <th>Tarih</th>
                            <th>Şube / Sınıf</th>
                            <th>Katılımcı Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($reportData['exam_history'])): ?>
                            <tr><td colspan="4" style="text-align: center; color: #7f8c8d;">Henüz sınav kaydı bulunmamaktadır.</td></tr>
                        <?php else: ?>
                            <?php foreach ($reportData['exam_history'] as $exam): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($exam['title']); ?></td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($exam['created_at'])); ?></td>
                                    <td>
                                        <?php 
                                        $loc = [];
                                        if (!empty($exam['teacher_institution'])) $loc[] = $exam['teacher_institution'];
                                        if (!empty($exam['class_section'])) $loc[] = $exam['class_section'];
                                        echo htmlspecialchars(implode(' - ', $loc));
                                        ?>
                                    </td>
                                    <td>
                                        <span style="background: #e8f5e9; color: #2e7d32; padding: 4px 10px; border-radius: 12px; font-weight: bold;">
                                            <i class="fas fa-user-check"></i> <?php echo $exam['participant_count']; ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- 6. Şube ve Katılım Raporları -->
            <div class="report-section">
                <h2><i class="fas fa-building"></i> Şube ve Alıştırma Katılım Raporları</h2>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Şube / Kurum</th>
                            <th>Kayıtlı Öğrenci</th>
                            <th>Aktif Pratik Yapan</th>
                            <th>Katılım Oranı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['institution_stats'] as $institution => $stats): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($institution); ?></td>
                                <td><?php echo $stats['users']; ?></td>
                                <td><?php echo $stats['active_practice'] ?? 0; ?></td>
                                <td>
                                    <?php 
                                    $rate = $stats['participation_rate'] ?? 0;
                                    $color = $rate > 70 ? '#27ae60' : ($rate > 40 ? '#f39c12' : '#e74c3c');
                                    ?>
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="progress-bar-container">
                                            <div class="progress-bar" style="background: <?php echo $color; ?>; width: <?php echo $rate; ?>%;"></div>
                                        </div>
                                        <span style="font-weight: bold; color: <?php echo $color; ?>">%<?php echo $rate; ?></span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
        
        <div class="export-options">
            <button class="btn btn-success" onclick="window.print()"><i class="fas fa-print"></i> Raporu Yazdır</button>
        </div>
    </div>
</body>
</html>