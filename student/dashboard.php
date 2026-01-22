<?php
/**
 * Öğrenci Dashboard - Modern Tasarım
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Öğrenci kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('student') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Şifre değiştirme kontrolü
if ($user && ($user['must_change_password'] ?? false)) {
    header('Location: ../change_password.php');
    exit;
}

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];

// İstatistikler - gerçek veriler
$totalQuestions = count($questions);

// Veritabanı bağlantısı
require_once '../database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Veritabanı tablolarını güncelle (migration)
try {
    $db->updatePracticeResultsTable();
} catch (Exception $e) {
    error_log("Migration hatası: " . $e->getMessage());
}

// İstatistikler - gerçek veriler
$totalQuestions = count($questions);

// Bu hafta için sonuçları veritabanından çek
$weekStart = date('Y-m-d 00:00:00', strtotime('monday this week'));
$weekEnd = date('Y-m-d 23:59:59', strtotime('sunday this week'));

try {
    $stmt = $conn->prepare("SELECT * FROM practice_results WHERE username = :username AND created_at BETWEEN :start AND :end");
    $stmt->execute([
        ':username' => $user['username'],
        ':start' => $weekStart,
        ':end' => $weekEnd
    ]);
    $thisWeekResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $thisWeekResults = [];
    error_log("Dashboard practice error: " . $e->getMessage());
}

// İstatistikleri hesapla
$completedPractices = count($thisWeekResults);
$totalTimeSpent = 0;
$totalScore = 0;
$solvedQuestions = 0;

foreach ($thisWeekResults as $result) {
    // Süre saniye olarak geliyor
    $totalTimeSpent += $result['time_taken'] ?? 0;
    $totalScore += $result['score'] ?? 0;
    $solvedQuestions += $result['total_questions'] ?? 0;
}

$averageScore = $completedPractices > 0 ? round($totalScore / $completedPractices, 1) : 0;
$totalTimeSpentMinutes = (int)round(($totalTimeSpent) / 60, 0);

// Planlanan sınavları veritabanından çek
$scheduledExams = [];
try {
    $studentInstitution = $user['institution'] ?? $user['branch'] ?? '';
    $studentClass = $user['class_section'] ?? $studentInstitution;
    
    // Sınavları çek
    $sql = "SELECT * FROM exams WHERE status = 'active' AND (expires_at IS NULL OR expires_at > NOW())";
    $stmt = $conn->query($sql);
    $allExams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filtreleme (basitçe tüm aktif sınavları göster veya sınıf/şube kontrolü yap)
    // Şimdilik tüm aktif sınavları gösteriyoruz, ileride sınıf/şube filtresi eklenebilir
    foreach ($allExams as $exam) {
        $scheduledExams[$exam['exam_id']] = $exam;
    }
} catch (Exception $e) {
    error_log("Dashboard exams error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Paneli - Bir Soru Bir Sevap</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --primary-light: #089b76;
            --secondary: #f8f9fa;
            --dark: #2c3e50;
            --gray: #64748b;
            --white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 1.5rem 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo img {
            height: 3rem;
            width: auto;
        }

        .logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .logo p {
            opacity: 0.9;
            font-size: 0.875rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: .75rem;
        }

        /* Uzun kullanıcı adlarını taşırmadan kısalt */
        .user-info > div {
            max-width: 45vw;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-5px);
        }

        .welcome-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            text-align: center;
        }

        .welcome-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .welcome-subtitle {
            color: var(--gray);
            font-size: 1.125rem;
        }

        .main-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .primary-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .action-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
            position: relative;
            overflow: hidden;
        }

        .action-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .action-card.large {
            grid-column: span 2;
        }

        .action-card.primary {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
        }

        .action-card.success {
            background: linear-gradient(135deg, var(--success) 0%, #059669 100%);
            color: var(--white);
        }

        .action-card.warning {
            background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);
            color: var(--white);
        }

        .action-card.info {
            background: linear-gradient(135deg, var(--info) 0%, #2563eb 100%);
            color: var(--white);
        }

        .action-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .action-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.75rem;
        }

        .action-description {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.5;
            margin-bottom: 1.5rem;
        }

        .action-btn {
            background: rgba(255,255,255,0.2);
            color: inherit;
            padding: 0.75rem 1.5rem;
            border: 1px solid rgba(255,255,255,0.3);
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }

        .stats-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .stats-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .stats-grid {
            display: grid;
            gap: 1rem;
        }

        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--secondary);
            border-radius: 0.5rem;
        }

        .stat-label {
            font-weight: 500;
            color: var(--gray);
        }

        .stat-value {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }

        .badges-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-top: 2rem;
        }

        .badge-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 1rem;
        }

        .badge-card {
            background: var(--secondary);
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
        }

        .badge-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .quick-action {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .quick-action:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        /* Planlanan Sınavlar */
        .scheduled-exams-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .scheduled-exams-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .scheduled-exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .scheduled-exam-card {
            background: var(--white);
            border: 2px solid #e1e8ed;
            border-radius: 1rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .scheduled-exam-card.active {
            border-color: var(--success);
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(16, 185, 129, 0.02) 100%);
        }

        .scheduled-exam-card.upcoming {
            border-color: var(--warning);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(245, 158, 11, 0.02) 100%);
        }

        .scheduled-exam-card.scheduled {
            border-color: var(--info);
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .exam-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .exam-code {
            background: var(--primary);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 700;
            font-size: 0.875rem;
        }

        .exam-details {
            margin-bottom: 1.5rem;
        }

        .exam-info {
            display: flex;
            gap: 1rem;
            margin-bottom: 0.75rem;
            font-size: 0.875rem;
            color: var(--gray);
        }

        .exam-time {
            text-align: center;
        }

        .time-status {
            display: inline-block;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .time-status.active {
            background: var(--success);
            color: var(--white);
        }

        .time-status.upcoming {
            background: var(--warning);
            color: var(--white);
        }

        .time-status.scheduled {
            background: var(--info);
            color: var(--white);
        }

        .exam-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .exam-action-btn.active {
            background: var(--success);
            color: var(--white);
        }

        .exam-action-btn.active:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .exam-action-btn.disabled {
            background: #e5e7eb;
            color: #6b7280;
            cursor: not-allowed;
        }

        .quick-action-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 0.75rem;
        }

        .quick-action-title {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .quick-action-desc {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .progress-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-top: 2rem;
        }

        .progress-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .progress-item {
            margin-bottom: 1.5rem;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .progress-bar {
            width: 100%;
            height: 0.5rem;
            background: var(--secondary);
            border-radius: 0.25rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 0.25rem;
            transition: width 0.3s ease;
        }

        @media (max-width: 1024px) {
            .main-grid {
                grid-template-columns: 1fr;
            }
            
            .primary-actions {
                grid-template-columns: 1fr;
            }
            
            .action-card.large {
                grid-column: span 1;
            }
        }

        @media (max-width: 768px) {
            .header { padding: 1rem 0; }
            .header-content { padding: 0 1rem; flex-wrap: wrap; gap: .75rem; }
            .logo img { height: 2.25rem; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 2.1rem; height: 2.1rem; font-size: 1rem; }
            .logout-btn { padding: .4rem .75rem; border-radius: .75rem; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .header-content { flex-direction: row; }

            .container {
                padding: 1rem;
            }

            .welcome-section {
                padding: 1.5rem;
            }

            .welcome-title {
                font-size: 1.5rem;
            }

            .action-card {
                padding: 1.5rem;
            }

            .action-icon {
                font-size: 2.5rem;
            }

            .action-title {
                font-size: 1.25rem;
            }
        }

        @media (max-width: 420px) {
            .header { padding: .75rem 0; }
            .header-content { padding: 0 .75rem; }
            .logo img { height: 2rem; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 1.9rem; height: 1.9rem; font-size: .95rem; }
            .logout-btn { padding: .35rem .6rem; font-size: .85rem; }
            .user-info { gap: .5rem; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <a href="dashboard.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 1rem;">
                    <img src="../logo.png" alt="Logo">
                    <div>
                        <h1>Bir Soru Bir Sevap</h1>
                        <p>Öğrenci Paneli</p>
                    </div>
                </a>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.875rem; opacity: 0.8;">Öğrenci</div>
                </div>
                <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.15);">DE</button>
                <a href="../logout.php" class="logout-btn" id="btnLogout">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="../index.php" class="back-btn" id="btnBackHome">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>

        <div class="welcome-section">
            <h1 class="welcome-title">Hoş Geldiniz, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p class="welcome-subtitle">Alıştırma yapın, sınavlara katılın ve gelişiminizi takip edin</p>
        </div>

        <div class="main-grid">
            <div class="primary-actions">
                <!-- En çok kullanılan özellikler büyük kartlar -->
                <a href="practice_setup.php" class="action-card primary large">
                    <div class="action-icon">
                        <i class="fas fa-play-circle"></i>
                    </div>
                    <div class="action-title" id="actionPractice">Alıştırma Yap</div>
                    <div class="action-description" id="actionPracticeDesc">
                        Konulara göre alıştırma yapın, kendinizi test edin ve gelişiminizi görün
                    </div>
                    <div class="action-btn" id="btnPractice">
                        <i class="fas fa-arrow-right"></i>
                        Alıştırmaya Başla
                    </div>
                </a>

                <a href="exams.php" class="action-card success">
                    <div class="action-icon">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="action-title" id="actionExams">Sınavlarım</div>
                    <div class="action-description" id="actionExamsDesc">
                        Eğitmeninizin oluşturduğu sınavlara katılın
                    </div>
                    <div class="action-btn" id="btnViewExams">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnViewExamsText">Sınavları Gör</span>
                    </div>
                </a>

                <a href="results.php" class="action-card warning">
                    <div class="action-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="action-title" id="actionResults">Sonuçlarım</div>
                    <div class="action-description" id="actionResultsDesc">
                        Alıştırma ve sınav sonuçlarınızı görüntüleyin
                    </div>
                    <div class="action-btn" id="btnViewResults">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnViewResultsText">Sonuçları Gör</span>
                    </div>
                </a>

                <a href="../training_panel.php" class="action-card info">
                    <div class="action-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <div class="action-title" id="actionTraining">Eğitim Paneli</div>
                    <div class="action-description" id="actionTrainingDesc">
                        Eğitim materyallerine ve kaynaklara erişin
                    </div>
                    <div class="action-btn" id="btnViewTraining">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnViewTrainingText">Materyalleri Gör</span>
                    </div>
                </a>
            </div>

            <div class="stats-section">
                <h3 class="stats-title" id="statsTitle">📊 Bu Hafta İstatistikleriniz</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel1">Tamamlanan Alıştırma</span>
                        <span class="stat-value"><?php echo $completedPractices; ?></span>
        </div>
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel2">Ortalama Puan</span>
                        <span class="stat-value"><?php echo $averageScore; ?>%</span>
                </div>
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel3">Toplam Süre (dk)</span>
                        <span class="stat-value"><?php echo $totalTimeSpentMinutes; ?></span>
                        </div>
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel4">Çözülen Soru</span>
                        <span class="stat-value"><?php echo number_format($solvedQuestions); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planlanan Sınavlar -->
        <?php if (!empty($scheduledExams)): ?>
        <div class="scheduled-exams-section">
            <h3 class="scheduled-exams-title" id="scheduledExamsTitle">📅 Planlanan Sınavlar</h3>
            <div class="scheduled-exams-grid">
                <?php foreach ($scheduledExams as $code => $exam): ?>
                    <?php 
                    $scheduledTime = strtotime($exam['scheduled_start'] ?? '');
                    $now = time();
                    $timeDiff = $scheduledTime - $now;
                    $isActive = $timeDiff <= 0;
                    $isUpcoming = $timeDiff > 0 && $timeDiff <= 3600; // 1 saat içinde
                    ?>
                    <div class="scheduled-exam-card <?php echo $isActive ? 'active' : ($isUpcoming ? 'upcoming' : 'scheduled'); ?>">
                        <div class="exam-header">
                            <div class="exam-title"><?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?></div>
                            <div class="exam-code"><?php echo htmlspecialchars($code); ?></div>
                        </div>
                        <div class="exam-details">
                            <div class="exam-info">
                                <span class="exam-duration">⏱️ <?php echo htmlspecialchars($exam['duration'] ?? '30'); ?> dk</span>
                                <span class="exam-questions">📊 <?php echo count($exam['questions'] ?? []); ?> soru</span>
                            </div>
                            <div class="exam-time">
                                <?php if ($isActive): ?>
                                    <span class="time-status active" id="timeStatusActive">✅ Sınava Girebilirsiniz</span>
                                <?php elseif ($isUpcoming): ?>
                                    <span class="time-status upcoming" id="timeStatusUpcoming">⏰ <?php echo date('H:i', $scheduledTime); ?>'da başlayacak</span>
                                <?php else: ?>
                                    <span class="time-status scheduled" id="timeStatusScheduled">📅 <?php echo date('d.m.Y H:i', $scheduledTime); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($isActive): ?>
                            <a href="exam_join.php?code=<?php echo urlencode($code); ?>" class="exam-action-btn active" id="btnJoinScheduled">
                                <i class="fas fa-play"></i>
                                <span id="btnJoinScheduledText">Sınava Gir</span>
                            </a>
                        <?php else: ?>
                            <div class="exam-action-btn disabled" id="btnWaitScheduled">
                                <i class="fas fa-clock"></i>
                                <span id="btnWaitScheduledText">Bekleniyor</span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="quick-actions">
            <a href="practice.php" class="quick-action" id="qaPractice">
                <div class="quick-action-icon">
                    <i class="fas fa-brain"></i>
                </div>
                <div class="quick-action-title">Hızlı Alıştırma</div>
                <div class="quick-action-desc">Rastgele sorularla alıştırma yap</div>
            </a>

            <a href="progress.php" class="quick-action" id="qaProgress">
                <div class="quick-action-icon">
                    <i class="fas fa-arrow-trend-up"></i>
                </div>
                <div class="quick-action-title">Gelişim Takibi</div>
                <div class="quick-action-desc">İlerlemenizi detaylı görün</div>
            </a>

            <a href="badges.php" class="quick-action" id="qaBadges">
                <div class="quick-action-icon">
                    <i class="fas fa-medal"></i>
                </div>
                <div class="quick-action-title">Rozetler</div>
                <div class="quick-action-desc">Tüm rozetleri ve kriterleri gör</div>
            </a>

            <a href="profile.php" class="quick-action" id="qaProfile">
                <div class="quick-action-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="quick-action-title">Profil Ayarları</div>
                <div class="quick-action-desc">Hesap bilgilerinizi düzenleyin</div>
            </a>

            <a href="../change_password.php" class="quick-action" id="qaChangePwd">
                <div class="quick-action-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="quick-action-title">Şifre Değiştir</div>
                <div class="quick-action-desc">Güvenlik için şifrenizi güncelleyin</div>
            </a>
                            </div>

        <?php if ($completedPractices > 0): ?>
        <div class="progress-section">
            <h3 class="progress-title" id="progressTitle">🎯 Bu Hafta İlerlemeniz</h3>
            
            <div class="progress-item">
                <div class="progress-label">
                    <span id="progressLabel1">Alıştırma Tamamlama</span>
                    <span><?php echo $completedPractices; ?> <span id="practiceUnit">alıştırma</span></span>
                    </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(($completedPractices / 10) * 100, 100); ?>%"></div>
                </div>
                        </div>

            <div class="progress-item">
                <div class="progress-label">
                    <span id="progressLabel2">Ortalama Başarı</span>
                    <span><?php echo $averageScore; ?>%</span>
                        </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $averageScore; ?>%"></div>
                        </div>
                    </div>

            <div class="progress-item">
                <div class="progress-label">
                    <span id="progressLabel3">Çalışma Süresi</span>
                    <span><?php echo $totalTimeSpentMinutes; ?> <span id="timeUnit">dakika</span></span>
                </div>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo min(($totalTimeSpentMinutes / 300) * 100, 100); ?>%"></div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php
            // Rozetlerim
            require_once '../Badges.php';
            $badgesCore = new Badges();
            $userId = $user['username'] ?? $user['name'] ?? 'unknown';
            $allUserBadges = $badgesCore->loadUserBadges($userId);
            $myBadges = $allUserBadges[$userId] ?? [];
            $badgeDefs = $badgesCore->loadBadges();
            // defs'i key ile eşle
            $defsByKey = [];
            foreach ($badgeDefs as $def) { $defsByKey[$def['key']] = $def; }
        ?>
        <div class="badges-section">
            <h3 class="stats-title" id="badgesTitle">🏅 Rozetlerim</h3>
            <?php if (!empty($myBadges)): ?>
            <div class="badge-grid">
                <?php foreach ($myBadges as $key => $info): $def = $defsByKey[$key] ?? null; if (!$def) continue; ?>
                <div class="badge-card">
                    <div class="badge-icon"><i class="fas <?php echo htmlspecialchars($def['icon']); ?>"></i></div>
                    <div class="quick-action-title"><?php echo htmlspecialchars($def['name']); ?> (<span id="levelText">Seviye</span> <?php echo (int)$info['level']; ?>)</div>
                    <div class="quick-action-desc"><span id="earnedText">Kazanım:</span> <?php echo htmlspecialchars($info['awarded_at'] ?? '-'); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-award"></i>
                    <h3 id="noBadgesTitle">Henüz rozetiniz yok</h3>
                    <p id="noBadgesDesc">Alıştırma yaptıkça rozetler kazanın</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                panel:'Öğrenci Paneli', back:'Ana Sayfaya Dön', logout:'Çıkış', 
                practice:'Alıştırmaya Başla', exams:'Sınavları Gör', results:'Sonuçları Gör', 
                qPractice:'Hızlı Alıştırma', qProgress:'Gelişim Takibi', qBadges:'Rozetler', 
                qProfile:'Profil Ayarları', qPwd:'Şifre Değiştir', 
                subtitle:'Alıştırma yapın, sınavlara katılın ve gelişiminizi takip edin',
                welcome:'Hoş Geldiniz', role:'Öğrenci',
                actionPractice:'Alıştırma Yap', actionPracticeDesc:'Konulara göre alıştırma yapın, kendinizi test edin ve gelişiminizi görün',
                actionExams:'Sınavlarım', actionExamsDesc:'Eğitmeninizin oluşturduğu sınavlara katılın',
                actionResults:'Sonuçlarım', actionResultsDesc:'Alıştırma ve sınav sonuçlarınızı görüntüleyin',
                scheduledExamsTitle:'📅 Planlanan Sınavlar', timeStatusActive:'✅ Sınava Girebilirsiniz',
                timeStatusUpcoming:'⏰ başlayacak', timeStatusScheduled:'📅', btnJoinScheduledText:'Sınava Gir', btnWaitScheduledText:'Bekleniyor',
                btnViewExamsText:'Sınavları Gör', btnViewResultsText:'Sonuçları Gör',
                qPracticeDesc:'Rastgele sorularla alıştırma yap', qProgressDesc:'İlerlemenizi detaylı görün',
                qBadgesDesc:'Tüm rozetleri ve kriterleri gör', qProfileDesc:'Hesap bilgilerinizi düzenleyin',
                qPwdDesc:'Güvenlik için şifrenizi güncelleyin',
                statsTitle:'Bu Hafta İstatistikleriniz', completed:'Tamamlanan Alıştırma',
                average:'Ortalama Puan', totalTime:'Toplam Süre (dk)', solved:'Çözülen Soru',
                progressTitle:'Bu Hafta İlerlemeniz', practiceCompletion:'Alıştırma Tamamlama',
                averageSuccess:'Ortalama Başarı', studyTime:'Çalışma Süresi',
                practiceUnit:'alıştırma', minuteUnit:'dakika',
                badgesTitle:'Rozetlerim', level:'Seviye', earned:'Kazanım',
                noBadges:'Henüz rozetiniz yok', noBadgesDesc:'Alıştırma yaptıkça rozetler kazanın',
                levelText:'Seviye', earnedText:'Kazanım:'
            };
            const de = {
                panel:'Schüler-Panel', back:'Zur Startseite', logout:'Abmelden',
                practice:'Mit Übung beginnen', exams:'Prüfungen anzeigen', results:'Ergebnisse anzeigen',
                qPractice:'Schnellübung', qProgress:'Fortschritt verfolgen', qBadges:'Abzeichen',
                qProfile:'Profileinstellungen', qPwd:'Passwort ändern',
                subtitle:'Üben Sie, nehmen Sie an Prüfungen teil und verfolgen Sie Ihren Fortschritt',
                welcome:'Willkommen', role:'Schüler',
                actionPractice:'Übung machen', actionPracticeDesc:'Üben Sie nach Themen, testen Sie sich und sehen Sie Ihren Fortschritt',
                actionExams:'Meine Prüfungen', actionExamsDesc:'Nehmen Sie an Prüfungen teil, die Ihr Lehrer erstellt hat',
                actionResults:'Meine Ergebnisse', actionResultsDesc:'Zeigen Sie Ihre Übungs- und Prüfungsergebnisse an',
                scheduledExamsTitle:'📅 Geplante Prüfungen', timeStatusActive:'✅ Sie können an der Prüfung teilnehmen',
                timeStatusUpcoming:'⏰ beginnt um', timeStatusScheduled:'📅', btnJoinScheduledText:'Prüfung starten', btnWaitScheduledText:'Warten',
                btnViewExamsText:'Prüfungen anzeigen', btnViewResultsText:'Ergebnisse anzeigen',
                qPracticeDesc:'Üben Sie mit zufälligen Fragen', qProgressDesc:'Sehen Sie Ihren Fortschritt im Detail',
                qBadgesDesc:'Sehen Sie alle Abzeichen und Kriterien', qProfileDesc:'Bearbeiten Sie Ihre Kontoinformationen',
                qPwdDesc:'Aktualisieren Sie Ihr Passwort für die Sicherheit',
                statsTitle:'Ihre Statistiken diese Woche', completed:'Abgeschlossene Übungen',
                average:'Durchschnittspunktzahl', totalTime:'Gesamtzeit (Min)', solved:'Gelöste Fragen',
                progressTitle:'Ihr Fortschritt diese Woche', practiceCompletion:'Übungsabschluss',
                averageSuccess:'Durchschnittserfolg', studyTime:'Lernzeit',
                practiceUnit:'Übungen', minuteUnit:'Minuten',
                badgesTitle:'Meine Abzeichen', level:'Stufe', earned:'Verdient',
                noBadges:'Sie haben noch keine Abzeichen', noBadgesDesc:'Verdienen Sie Abzeichen durch Üben',
                levelText:'Stufe', earnedText:'Verdient:'
            };
            
            function setText(sel, text){ 
                const el=document.querySelector(sel); 
                if(el) el.innerText=text; 
            }
            
            function setHTML(sel, html){ 
                const el=document.querySelector(sel); 
                if(el) el.innerHTML=html; 
            }
            
            function apply(lang){ 
                const d=lang==='de'?de:tr; 
                setText('.logo p', d.panel); 
                setText('#btnBackHome', d.back); 
                setText('#btnLogout', d.logout); 
                setText('#btnPractice', d.practice); 
                setText('#btnViewExams', d.exams); 
                setText('#btnViewResults', d.results);
                setText('#btnViewExamsText', d.btnViewExamsText);
                setText('#btnViewResultsText', d.btnViewResultsText);
                setText('#scheduledExamsTitle', d.scheduledExamsTitle);
                setText('#timeStatusActive', d.timeStatusActive);
                setText('#timeStatusUpcoming', d.timeStatusUpcoming);
                setText('#timeStatusScheduled', d.timeStatusScheduled);
                setText('#btnJoinScheduledText', d.btnJoinScheduledText);
                setText('#btnWaitScheduledText', d.btnWaitScheduledText);
                setText('#qaPractice .quick-action-title', d.qPractice); 
                setText('#qaProgress .quick-action-title', d.qProgress); 
                setText('#qaBadges .quick-action-title', d.qBadges); 
                setText('#qaProfile .quick-action-title', d.qProfile); 
                setText('#qaChangePwd .quick-action-title', d.qPwd); 
                setText('.welcome-subtitle', d.subtitle);
                setText('.welcome-title', d.welcome + ', <?php echo htmlspecialchars($user['name']); ?>! 👋');
                setText('.user-info div:last-child div:last-child', d.role);
                setText('#actionPractice', d.actionPractice);
                setText('#actionPracticeDesc', d.actionPracticeDesc);
                setText('#actionExams', d.actionExams);
                setText('#actionExamsDesc', d.actionExamsDesc);
                setText('#actionResults', d.actionResults);
                setText('#actionResultsDesc', d.actionResultsDesc);
                setText('#qaPractice .quick-action-desc', d.qPracticeDesc);
                setText('#qaProgress .quick-action-desc', d.qProgressDesc);
                setText('#qaBadges .quick-action-desc', d.qBadgesDesc);
                setText('#qaProfile .quick-action-desc', d.qProfileDesc);
                setText('#qaChangePwd .quick-action-desc', d.qPwdDesc);
                setText('#statsTitle', d.statsTitle);
                setText('#statLabel1', d.completed);
                setText('#statLabel2', d.average);
                setText('#statLabel3', d.totalTime);
                setText('#statLabel4', d.solved);
                setText('#progressTitle', d.progressTitle);
                setText('#progressLabel1', d.practiceCompletion);
                setText('#progressLabel2', d.averageSuccess);
                setText('#progressLabel3', d.studyTime);
                setText('#practiceUnit', d.practiceUnit);
                setText('#timeUnit', d.minuteUnit);
                setText('#badgesTitle', d.badgesTitle);
                setText('#noBadgesTitle', d.noBadges);
                setText('#noBadgesDesc', d.noBadgesDesc);
                setText('#levelText', d.levelText);
                setText('#earnedText', d.earnedText);
                
                const toggle=document.getElementById('langToggle'); 
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE'); 
                localStorage.setItem('lang_dashboard_student', lang); 
            }
            
            document.addEventListener('DOMContentLoaded', function(){ 
                const lang=localStorage.getItem('lang_dashboard_student')||localStorage.getItem('lang')||'tr'; 
                apply(lang); 
                const toggle=document.getElementById('langToggle'); 
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_dashboard_student')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                } 
            });
        })();

        // Smooth scroll ve animasyonlar
        document.addEventListener('DOMContentLoaded', function() {
            // Kartlara hover efekti
            const cards = document.querySelectorAll('.action-card, .quick-action');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-4px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Progress bar animasyonları
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });
    </script>
</body>
</html>