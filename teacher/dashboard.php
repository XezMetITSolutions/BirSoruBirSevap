<?php
/**
 * Eğitmen Dashboard - Modern Tasarım
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';
require_once '../ExamManager.php';

$auth = Auth::getInstance();

// Eğitmen kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
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

// İstatistikler
$totalQuestions = count($questions);
$totalBanks = count($banks);
$totalCategories = array_sum(array_map('count', $categories));

// Veritabanı bağlantısı
require_once '../database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Sınav istatistikleri (gerçek veriler)
$totalExams = 0;
$activeExams = 0;
$completedExams = 0;

try {
    // Şube bazlı filtreleme için WHERE koşulu hazırla
    $whereClause = "";
    $params = [];
    
    // Sadece superadmin her şeyi görür, diğerleri kendi şubesini
    if (!$auth->hasRole('superadmin')) {
        $userBranch = $user['branch'] ?? $user['institution'] ?? '';
        if (!empty($userBranch)) {
            $whereClause = " WHERE class_section = :branch OR teacher_institution = :branch";
            $params[':branch'] = $userBranch;
        }
    }

    // Toplam sınav sayısı
    $stmt = $conn->prepare("SELECT COUNT(*) FROM exams" . $whereClause);
    $stmt->execute($params);
    $totalExams = $stmt->fetchColumn();
    
    // Aktif sınav sayısı
    $activeWhere = empty($whereClause) ? " WHERE status = 'active'" : $whereClause . " AND status = 'active'";
    $stmt = $conn->prepare("SELECT COUNT(*) FROM exams" . $activeWhere);
    $stmt->execute($params);
    $activeExams = $stmt->fetchColumn();
    
    // Tamamlanan sınav sayısı
    $completedWhere = empty($whereClause) ? 
        " WHERE status = 'completed' OR (expires_at IS NOT NULL AND expires_at < NOW())" : 
        $whereClause . " AND (status = 'completed' OR (expires_at IS NOT NULL AND expires_at < NOW()))";
    $stmt = $conn->prepare("SELECT COUNT(*) FROM exams" . $completedWhere);
    $stmt->execute($params);
    $completedExams = $stmt->fetchColumn();
    
} catch (Exception $e) {
    error_log("Teacher dashboard stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitmen Paneli - Bir Soru Bir Sevap</title>
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
            gap: 0.75rem;
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
                        <p id="pageTitle">Eğitmen Paneli</p>
                    </div>
                </a>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.875rem; opacity: 0.8;" id="userRole">Eğitmen</div>
                </div>
                <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.15);">DE</button>
                <a href="../logout.php" class="logout-btn" id="btnLogout">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="../index.php" class="back-btn" id="btnBackHome">
            <i class="fas fa-arrow-left"></i>
            <span id="backHomeText">Ana Sayfaya Dön</span>
        </a>

        <div class="welcome-section">
            <h1 class="welcome-title" id="welcomeTitle">Hoş Geldiniz, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p class="welcome-subtitle" id="welcomeSubtitle">Eğitmen panelinizde sınav oluşturun, öğrencileri yönetin ve sonuçları takip edin</p>
        </div>

        <div class="main-grid">
            <div class="primary-actions">
                <!-- En çok kullanılan özellikler büyük kartlar -->
                <a href="create_exam.php" class="action-card primary">
                    <div class="action-icon">
                        <i class="fas fa-plus-circle"></i>
                    </div>
                    <div class="action-title" id="actionTitle1">Yeni Sınav Oluştur</div>
                    <div class="action-description" id="actionDesc1">
                        Hızlıca yeni bir sınav oluşturun, soruları seçin ve öğrencilerinize atayın
                    </div>
                    <div class="action-btn" id="btnCreateExam">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnCreateExamText">Sınav Oluştur</span>
                    </div>
                </a>

                <a href="exam_pdf.php" class="action-card primary">
                    <div class="action-icon">
                        <i class="fas fa-file-pdf"></i>
                    </div>
                    <div class="action-title" id="actionTitle1b">PDF Sınav Oluştur</div>
                    <div class="action-description" id="actionDesc1b">
                        Sınavı PDF olarak indirin ve kağıt-kalem ile yapın
                    </div>
                    <div class="action-btn" id="btnCreatePDF">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnCreatePDFText">PDF Oluştur</span>
                    </div>
                </a>

                <a href="../practice_setup.php" class="action-card primary">
                    <div class="action-icon">
                        <i class="fas fa-dumbbell"></i>
                    </div>
                    <div class="action-title" id="actionTitlePractice">Alıştırma Yap</div>
                    <div class="action-description" id="actionDescPractice">
                        Kendinizi test edin ve soru bankasındaki soruları çözün
                    </div>
                    <div class="action-btn" id="btnPractice">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnPracticeText">Alıştırma Başlat</span>
                    </div>
                </a>

                <a href="exams.php" class="action-card success">
                    <div class="action-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="action-title" id="actionTitle2">Sınavlarım</div>
                    <div class="action-description" id="actionDesc2">
                        Oluşturduğunuz sınavları görüntüleyin ve yönetin
                    </div>
                    <div class="action-btn" id="btnViewExams">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnViewExamsText">Sınavları Görüntüle</span>
                    </div>
                </a>


                <a href="exam_results.php" class="action-card warning">
                    <div class="action-icon">
                        <i class="fas fa-chart-bar"></i>
                    </div>
                    <div class="action-title" id="actionTitle3">Sınav Sonuçları</div>
                    <div class="action-description" id="actionDesc3">
                        Öğrenci sonuçlarını analiz edin ve raporları görüntüleyin
                    </div>
                    <div class="action-btn" id="btnViewResults">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnViewResultsText">Sonuçları Görüntüle</span>
                    </div>
                </a>

                <a href="../training_panel.php" class="action-card info">
                    <div class="action-icon">
                        <i class="fas fa-book-reader"></i>
                    </div>
                    <div class="action-title" id="actionTitleTraining">Eğitim Paneli</div>
                    <div class="action-description" id="actionDescTraining">
                        Eğitim materyallerine ve kaynaklara erişin
                    </div>
                    <div class="action-btn" id="btnViewTraining">
                        <i class="fas fa-arrow-right"></i>
                        <span id="btnViewTrainingText">Materyalleri Gör</span>
                    </div>
                </a>
            </div>

            <div class="stats-section">
                <h3 class="stats-title" id="statsTitle">📊 Sistem İstatistikleri</h3>
                <div class="stats-grid">
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel1">Toplam Soru</span>
                        <span class="stat-value"><?php echo number_format($totalQuestions); ?></span>
                                </div>
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel2">Soru Bankası</span>
                        <span class="stat-value"><?php echo number_format($totalBanks); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel3">Kategoriler</span>
                        <span class="stat-value"><?php echo number_format($totalCategories); ?></span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label" id="statLabel4">Aktif Sınavlar</span>
                        <span class="stat-value"><?php echo number_format($activeExams); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="quick-actions">
            <a href="student_management.php" class="quick-action" id="btnStudents">
                <div class="quick-action-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="quick-action-title" id="quickTitle1">Öğrenci Yönetimi</div>
                <div class="quick-action-desc" id="quickDesc1">Öğrencileri görüntüle ve yönet</div>
            </a>

            <a href="analytics.php" class="quick-action" id="btnAnalytics">
                <div class="quick-action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="quick-action-title" id="quickTitle2">Analitik Raporlar</div>
                <div class="quick-action-desc" id="quickDesc2">Detaylı analiz ve raporlar</div>
            </a>

            <a href="profile.php" class="quick-action" id="btnProfile">
                <div class="quick-action-icon">
                    <i class="fas fa-user-cog"></i>
                </div>
                <div class="quick-action-title" id="quickTitle3">Profil Ayarları</div>
                <div class="quick-action-desc" id="quickDesc3">Hesap bilgilerinizi düzenleyin</div>
            </a>

            <a href="../change_password.php" class="quick-action" id="btnChangePwd">
                <div class="quick-action-icon">
                    <i class="fas fa-key"></i>
                </div>
                <div class="quick-action-title" id="quickTitle4">Şifre Değiştir</div>
                <div class="quick-action-desc" id="quickDesc4">Güvenlik için şifrenizi güncelleyin</div>
            </a>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Eğitmen Paneli', userRole:'Eğitmen', backHomeText:'Ana Sayfaya Dön', logout:'Çıkış',
                welcomeTitle:'Hoş Geldiniz, {name}! 👋', welcomeSubtitle:'Eğitmen panelinizde sınav oluşturun, öğrencileri yönetin ve sonuçları takip edin',
                actionTitle1:'Yeni Sınav Oluştur', actionDesc1:'Hızlıca yeni bir sınav oluşturun, soruları seçin ve öğrencilerinize atayın',
                actionTitle1b:'PDF Sınav Oluştur', actionDesc1b:'Sınavı PDF olarak indirin ve kağıt-kalem ile yapın',
                actionTitle2:'Sınavlarım', actionDesc2:'Oluşturduğunuz sınavları görüntüleyin ve yönetin',
                actionTitle3:'Sınav Sonuçları', actionDesc3:'Öğrenci sonuçlarını analiz edin ve raporları görüntüleyin',
                btnCreateExamText:'Sınav Oluştur', btnViewExamsText:'Sınavları Görüntüle', btnViewResultsText:'Sonuçları Görüntüle',
                statsTitle:'📊 Sistem İstatistikleri', statLabel1:'Toplam Soru', statLabel2:'Soru Bankası', statLabel3:'Kategoriler', statLabel4:'Aktif Sınavlar',
                quickTitle1:'Öğrenci Yönetimi', quickDesc1:'Öğrencileri görüntüle ve yönet',
                quickTitle2:'Analitik Raporlar', quickDesc2:'Detaylı analiz ve raporlar',
                quickTitle3:'Profil Ayarları', quickDesc3:'Hesap bilgilerinizi düzenleyin',
                quickTitle3:'Profil Ayarları', quickDesc3:'Hesap bilgilerinizi düzenleyin',
                quickTitle4:'Şifre Değiştir', quickDesc4:'Güvenlik için şifrenizi güncelleyin',
                actionTitlePractice:'Alıştırma Yap', actionDescPractice:'Kendinizi test edin ve soru bankasındaki soruları çözün', btnPracticeText:'Alıştırma Başlat'
            };
            const de = {
                pageTitle:'Lehrpersonal-Panel', userRole:'Lehrpersonal', backHomeText:'Zur Startseite', logout:'Abmelden',
                welcomeTitle:'Willkommen, {name}! 👋', welcomeSubtitle:'Erstellen Sie Prüfungen, verwalten Sie Schüler und verfolgen Sie Ergebnisse im Lehrpersonal-Panel',
                actionTitle1:'Neue Prüfung erstellen', actionDesc1:'Erstellen Sie schnell eine neue Prüfung, wählen Sie Fragen aus und weisen Sie sie Ihren Schülern zu',
                actionTitle1b:'PDF-Prüfung erstellen', actionDesc1b:'Laden Sie die Prüfung als PDF herunter und machen Sie sie mit Papier und Stift',
                actionTitle2:'Meine Prüfungen', actionDesc2:'Zeigen Sie Ihre erstellten Prüfungen an und verwalten Sie sie',
                actionTitle3:'Prüfungsergebnisse', actionDesc3:'Analysieren Sie Schülerergebnisse und zeigen Sie Berichte an',
                btnCreateExamText:'Prüfung erstellen', btnViewExamsText:'Prüfungen anzeigen', btnViewResultsText:'Ergebnisse anzeigen',
                statsTitle:'📊 Systemstatistiken', statLabel1:'Gesamt Fragen', statLabel2:'Fragendatenbank', statLabel3:'Kategorien', statLabel4:'Aktive Prüfungen',
                quickTitle1:'Schülerverwaltung', quickDesc1:'Schüler anzeigen und verwalten',
                quickTitle2:'Analytische Berichte', quickDesc2:'Detaillierte Analysen und Berichte',
                quickTitle3:'Profileinstellungen', quickDesc3:'Bearbeiten Sie Ihre Kontoinformationen',
                quickTitle3:'Profileinstellungen', quickDesc3:'Bearbeiten Sie Ihre Kontoinformationen',
                quickTitle4:'Passwort ändern', quickDesc4:'Aktualisieren Sie Ihr Passwort für die Sicherheit',
                actionTitlePractice:'Üben', actionDescPractice:'Testen Sie sich selbst und lösen Sie Fragen aus der Datenbank', btnPracticeText:'Übung starten'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#backHomeText', d.backHomeText);
                setText('#btnLogout', d.logout);
                setText('#actionTitle1', d.actionTitle1);
                setText('#actionDesc1', d.actionDesc1);
                setText('#actionTitle1b', d.actionTitle1b);
                setText('#actionDesc1b', d.actionDesc1b);
                setText('#actionTitle2', d.actionTitle2);
                setText('#actionDesc2', d.actionDesc2);
                setText('#actionTitle3', d.actionTitle3);
                setText('#actionDesc3', d.actionDesc3);
                setText('#btnCreateExamText', d.btnCreateExamText);
                setText('#btnViewExamsText', d.btnViewExamsText);
                setText('#btnViewResultsText', d.btnViewResultsText);
                setText('#statsTitle', d.statsTitle);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#quickTitle1', d.quickTitle1);
                setText('#quickDesc1', d.quickDesc1);
                setText('#quickTitle2', d.quickTitle2);
                setText('#quickDesc2', d.quickDesc2);
                setText('#quickTitle3', d.quickTitle3);
                setText('#quickDesc3', d.quickDesc3);
                setText('#quickTitle4', d.quickTitle4);
                setText('#quickDesc4', d.quickDesc4);
                setText('#actionTitlePractice', d.actionTitlePractice);
                setText('#actionDescPractice', d.actionDescPractice);
                setText('#btnPracticeText', d.btnPracticeText);
                
                // Welcome title'da isim değişimi
                const welcomeTitle = document.getElementById('welcomeTitle');
                if (welcomeTitle) {
                    const name = '<?php echo htmlspecialchars($user['name']); ?>';
                    const titleText = d.welcomeTitle.replace('{name}', name);
                    setText('#welcomeTitle', titleText);
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_dashboard_teacher', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_dashboard_teacher')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_dashboard_teacher')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
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
        });
    </script>
</body>
</html>