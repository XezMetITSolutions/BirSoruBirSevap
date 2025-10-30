<?php
/**
 * Süper Admin Dashboard
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Admin kontrolü (superadmin ve admin erişebilir)
if (!$auth->hasRole('admin') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Şifre değiştirme kontrolü
if ($user && ($user['must_change_password'] ?? false)) {
    header('Location: ../change_password.php');
    exit;
}

// Soru yükleme işlemi
$reloadMessage = '';
if (isset($_GET['action']) && $_GET['action'] === 'reload_questions') {
    // Mevcut session verilerini temizle
    unset($_SESSION['all_questions']);
    unset($_SESSION['categories']);
    unset($_SESSION['banks']);
    unset($_SESSION['question_errors']);
    
    // Soruları yeniden yükle
    $questionLoader = new QuestionLoader();
    $questionLoader->loadQuestions();
    
    $reloadMessage = 'Soru bankaları başarıyla yeniden yüklendi!';
}

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];
$errors = $_SESSION['question_errors'] ?? [];


// İstatistikler
$totalQuestions = count($questions);
$totalBanks = count($banks);
$totalCategories = array_sum(array_map('count', $categories));
$errorCount = count($errors);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Süper Admin Dashboard - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            min-height: 100vh;
            color: #333;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            color: #2c3e50;
            padding: 20px 0;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .logo img {
            height: 60px;
            width: auto;
            border-radius: 15px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .logo h1 {
            font-size: 2.2em;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .logo p {
            color: #7f8c8d;
            font-size: 1em;
            font-weight: 500;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
            background: rgba(6, 133, 103, 0.1);
            padding: 15px 25px;
            border-radius: 25px;
            backdrop-filter: blur(10px);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2em;
            color: white;
            box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);
        }

        .user-details {
            text-align: right;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .user-role {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .logout-btn {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            border: none;
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
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
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
        }

        .welcome-title {
            font-size: 2.5em;
            margin-bottom: 15px;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-subtitle {
            color: #7f8c8d;
            font-size: 1.2em;
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid rgba(255,255,255,0.2);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
        }

        .stat-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        .stat-icon {
            font-size: 3em;
            margin-bottom: 20px;
            display: block;
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 700;
            color: #2c3e50;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 1em;
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .main-content h2 {
            font-size: 2em;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        .main-content p {
            color: #7f8c8d;
            font-size: 1.1em;
            margin-bottom: 30px;
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .widget {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid rgba(255,255,255,0.2);
        }

        .widget h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3em;
            font-weight: 600;
        }

        .btn {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(6, 133, 103, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            box-shadow: 0 4px 15px rgba(39, 174, 96, 0.3);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            box-shadow: 0 4px 15px rgba(243, 156, 18, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .quick-actions .btn {
            padding: 20px;
            font-size: 1.1em;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .error-list {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .error-item {
            padding: 15px;
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 1px solid #f5c6cb;
            border-radius: 15px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #721c24;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .bank-list {
            max-height: 250px;
            overflow-y: auto;
            padding-right: 10px;
        }

        .bank-item {
            padding: 15px;
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 1px solid #c3e6cb;
            border-radius: 15px;
            margin-bottom: 15px;
            font-size: 0.9em;
            color: #155724;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-online {
            background: #27ae60;
            box-shadow: 0 0 10px rgba(39, 174, 96, 0.5);
        }

        .status-offline {
            background: #e74c3c;
            box-shadow: 0 0 10px rgba(231, 76, 60, 0.5);
        }

        .debug-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.9em;
            border-left: 4px solid #068567;
        }

        .debug-section strong {
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 20px;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }

            .welcome-title {
                font-size: 2em;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
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
                    <p id="pageTitle">🎯 Süper Admin Paneli</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div class="user-details">
                    <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div class="user-role" id="userRole">👑 Süper Admin</div>
                </div>
                <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(6, 133, 103, 0.1); color: #2c3e50; border: 1px solid rgba(6, 133, 103, 0.3); padding: 10px 20px; border-radius: 25px; text-decoration: none; transition: all 0.3s ease; font-weight: 600; cursor: pointer;">DE</button>
                <a href="../logout.php" class="logout-btn" id="btnLogout">🚪 Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="../index.php" class="back-btn" id="btnBackHome">
            <i class="fas fa-arrow-left"></i>
            <span id="backHomeText">Ana Sayfaya Dön</span>
        </a>

        <div class="welcome-section fade-in">
            <h1 class="welcome-title" id="welcomeTitle">Hoş Geldiniz, <?php echo htmlspecialchars($user['name']); ?>! 👋</h1>
            <p class="welcome-subtitle" id="welcomeSubtitle">Sistem yönetimi ve izleme paneliniz</p>
        </div>

        <?php if (!empty($reloadMessage)): ?>
            <div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: 1px solid #c3e6cb; color: #155724; padding: 20px; border-radius: 15px; margin-bottom: 30px; text-align: center; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                <strong>✅ <?php echo htmlspecialchars($reloadMessage); ?></strong>
            </div>
        <?php endif; ?>
        
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo $totalQuestions; ?></div>
                <div class="stat-label" id="statLabel1">Toplam Soru</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $totalBanks; ?></div>
                <div class="stat-label" id="statLabel2">Soru Bankası</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📁</div>
                <div class="stat-number"><?php echo $totalCategories; ?></div>
                <div class="stat-label" id="statLabel3">Kategori</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-number"><?php echo $errorCount; ?></div>
                <div class="stat-label" id="statLabel4">Hata</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content fade-in">
                <h2 id="mainTitle">🎛️ Sistem Yönetimi</h2>
                <p style="margin-bottom: 30px; color: #7f8c8d; font-size: 1.1em;" id="mainDesc">
                    Sistem durumu ve yönetim araçlarına hızlı erişim
                </p>

                <div class="quick-actions">
                    <a href="users.php" class="btn" id="btnUsers">
                        👥 Kullanıcı Yönetimi
                    </a>
                    <a href="settings.php" class="btn btn-secondary" id="btnSettings">
                        ⚙️ Sistem Ayarları
                    </a>
                    <a href="reports.php" class="btn btn-success" id="btnReports">
                        📈 Raporlar
                    </a>
                    <a href="student_progress.php" class="btn" id="btnStudentProgress">
                        🎓 Öğrenci Gelişimi
                    </a>
                    <a href="load_questions.php" class="btn btn-warning" id="btnLoadQuestions">
                        📚 Soru Yükleme
                    </a>
                    <a href="?action=reload_questions" class="btn btn-warning" id="btnReload">
                        🔄 Hızlı Yenile
                    </a>
                    <a href="../index.php" class="btn btn-danger" id="btnHome">
                        🏠 Ana Sayfa
                    </a>
                </div>

                <?php if (!empty($errors)): ?>
                    <div style="margin-top: 40px;">
                        <h3 id="errorTitle">⚠️ Sistem Hataları</h3>
                        <div class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <div class="error-item">
                                    <span class="status-indicator status-offline"></span>
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

            </div>

            <div class="sidebar">
                <div class="widget fade-in">
                    <h3 id="sidebarTitle1">📚 Soru Bankaları</h3>
                    <div class="bank-list">
                        <?php if (!empty($banks)): ?>
                            <?php foreach ($banks as $bank): ?>
                                <div class="bank-item">
                                    <strong>📖 <?php echo htmlspecialchars($bank); ?></strong><br>
                                    <span style="color: #7f8c8d;">📁 <?php echo count($categories[$bank] ?? []); ?> <span id="categoryText">kategori</span></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; color: #7f8c8d; padding: 20px;" id="noBanksText">
                                📭 Henüz soru bankası yüklenmemiş
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="widget fade-in">
                    <h3 id="sidebarTitle2">⚡ Hızlı İşlemler</h3>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <a href="load_questions.php" class="btn btn-warning" id="btnLoadQuestions2">
                            📚 Soru Yükleme
                        </a>
                        <a href="?action=reload_questions" class="btn btn-warning" id="btnReload2">
                            🔄 Hızlı Yenile
                        </a>
                        <a href="backup.php" class="btn btn-secondary" id="btnBackup">
                            💾 Yedekle
                        </a>
                        <a href="maintenance.php" class="btn btn-danger" id="btnMaintenance">
                            🔧 Bakım Modu
                        </a>
                    </div>
                </div>

                <div class="widget fade-in">
                    <h3 id="sidebarTitle3">📊 Sistem Durumu</h3>
                    <div style="font-size: 0.9em;">
                        <div style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 10px;">
                            <div style="margin-bottom: 8px;">
                                <strong id="phpVersionLabel">🐘 PHP Versiyonu:</strong> <?php echo phpversion(); ?>
                            </div>
                            <div style="margin-bottom: 8px;">
                                <strong id="memoryLabel">💾 Bellek Kullanımı:</strong> <?php echo round(memory_get_usage()/1024/1024, 2); ?> MB
                            </div>
                            <div>
                                <strong id="lastUpdateLabel">🕒 Son Güncelleme:</strong> <?php echo date('d.m.Y H:i'); ?>
                            </div>
                        </div>
                        
                    </div>
                </div>

                <div class="widget fade-in">
                    <h3 id="sidebarTitle4">🎯 Hızlı Erişim</h3>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <a href="users.php" class="btn" style="padding: 12px 20px; font-size: 0.9em;" id="btnUsers2">
                            👥 Kullanıcılar
                        </a>
                        <a href="settings.php" class="btn btn-secondary" style="padding: 12px 20px; font-size: 0.9em;" id="btnSettings2">
                            ⚙️ Ayarlar
                        </a>
                        <a href="reports.php" class="btn btn-success" style="padding: 12px 20px; font-size: 0.9em;" id="btnReports2">
                            📈 Raporlar
                        </a>
                        <a href="../index.php" class="btn btn-danger" style="padding: 12px 20px; font-size: 0.9em;" id="btnHome2">
                            🏠 Ana Sayfa
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'🎯 Süper Admin Paneli', userRole:'👑 Süper Admin', backHomeText:'Ana Sayfaya Dön', logout:'🚪 Çıkış',
                welcomeTitle:'Hoş Geldiniz, {name}! 👋', welcomeSubtitle:'Sistem yönetimi ve izleme paneliniz',
                statLabel1:'Toplam Soru', statLabel2:'Soru Bankası', statLabel3:'Kategori', statLabel4:'Hata',
                mainTitle:'🎛️ Sistem Yönetimi', mainDesc:'Sistem durumu ve yönetim araçlarına hızlı erişim',
                btnUsers:'👥 Kullanıcı Yönetimi', btnSettings:'⚙️ Sistem Ayarları', btnReports:'📈 Raporlar',
                btnLoadQuestions:'📚 Soru Yükleme', btnReload:'🔄 Hızlı Yenile', btnHome:'🏠 Ana Sayfa',
                btnStudentProgress:'🎓 Öğrenci Gelişimi',
                errorTitle:'⚠️ Sistem Hataları', sidebarTitle1:'📚 Soru Bankaları', categoryText:'kategori',
                noBanksText:'📭 Henüz soru bankası yüklenmemiş', sidebarTitle2:'⚡ Hızlı İşlemler',
                btnLoadQuestions2:'📚 Soru Yükleme', btnReload2:'🔄 Hızlı Yenile', btnBackup:'💾 Yedekle',
                btnMaintenance:'🔧 Bakım Modu', sidebarTitle3:'📊 Sistem Durumu',
                phpVersionLabel:'🐘 PHP Versiyonu:', memoryLabel:'💾 Bellek Kullanımı:', lastUpdateLabel:'🕒 Son Güncelleme:',
                sidebarTitle4:'🎯 Hızlı Erişim', btnUsers2:'👥 Kullanıcılar', btnSettings2:'⚙️ Ayarlar',
                btnReports2:'📈 Raporlar', btnHome2:'🏠 Ana Sayfa'
            };
            const de = {
                pageTitle:'🎯 Super-Admin-Panel', userRole:'👑 Super-Admin', backHomeText:'Zur Startseite', logout:'🚪 Abmelden',
                welcomeTitle:'Willkommen, {name}! 👋', welcomeSubtitle:'Ihr Systemverwaltungs- und Überwachungspanel',
                statLabel1:'Gesamt Fragen', statLabel2:'Fragendatenbank', statLabel3:'Kategorie', statLabel4:'Fehler',
                mainTitle:'🎛️ Systemverwaltung', mainDesc:'Schneller Zugang zu Systemstatus und Verwaltungstools',
                btnUsers:'👥 Benutzerverwaltung', btnSettings:'⚙️ Systemeinstellungen', btnReports:'📈 Berichte',
                btnLoadQuestions:'📚 Fragen laden', btnReload:'🔄 Schnell aktualisieren', btnHome:'🏠 Startseite',
                btnStudentProgress:'🎓 Schülerfortschritt',
                errorTitle:'⚠️ Systemfehler', sidebarTitle1:'📚 Fragendatenbanken', categoryText:'Kategorien',
                noBanksText:'📭 Noch keine Fragendatenbank geladen', sidebarTitle2:'⚡ Schnelle Aktionen',
                btnLoadQuestions2:'📚 Fragen laden', btnReload2:'🔄 Schnell aktualisieren', btnBackup:'💾 Sichern',
                btnMaintenance:'🔧 Wartungsmodus', sidebarTitle3:'📊 Systemstatus',
                phpVersionLabel:'🐘 PHP-Version:', memoryLabel:'💾 Speichernutzung:', lastUpdateLabel:'🕒 Letzte Aktualisierung:',
                sidebarTitle4:'🎯 Schnellzugriff', btnUsers2:'👥 Benutzer', btnSettings2:'⚙️ Einstellungen',
                btnReports2:'📈 Berichte', btnHome2:'🏠 Startseite'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#backHomeText', d.backHomeText);
                setText('#btnLogout', d.logout);
                setText('#welcomeTitle', d.welcomeTitle);
                setText('#welcomeSubtitle', d.welcomeSubtitle);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#mainTitle', d.mainTitle);
                setText('#mainDesc', d.mainDesc);
                setText('#btnUsers', d.btnUsers);
                setText('#btnSettings', d.btnSettings);
                setText('#btnReports', d.btnReports);
                setText('#btnLoadQuestions', d.btnLoadQuestions);
                setText('#btnReload', d.btnReload);
                setText('#btnHome', d.btnHome);
                const sp=document.getElementById('btnStudentProgress'); if (sp) setText('#btnStudentProgress', d.btnStudentProgress);
                setText('#errorTitle', d.errorTitle);
                setText('#sidebarTitle1', d.sidebarTitle1);
                setText('#categoryText', d.categoryText);
                setText('#noBanksText', d.noBanksText);
                setText('#sidebarTitle2', d.sidebarTitle2);
                setText('#btnLoadQuestions2', d.btnLoadQuestions2);
                setText('#btnReload2', d.btnReload2);
                setText('#btnBackup', d.btnBackup);
                setText('#btnMaintenance', d.btnMaintenance);
                setText('#sidebarTitle3', d.sidebarTitle3);
                setText('#phpVersionLabel', d.phpVersionLabel);
                setText('#memoryLabel', d.memoryLabel);
                setText('#lastUpdateLabel', d.lastUpdateLabel);
                setText('#sidebarTitle4', d.sidebarTitle4);
                setText('#btnUsers2', d.btnUsers2);
                setText('#btnSettings2', d.btnSettings2);
                setText('#btnReports2', d.btnReports2);
                setText('#btnHome2', d.btnHome2);
                
                // Welcome title'da isim değişimi
                const welcomeTitle = document.getElementById('welcomeTitle');
                if (welcomeTitle) {
                    const name = '<?php echo htmlspecialchars($user['name']); ?>';
                    const titleText = d.welcomeTitle.replace('{name}', name);
                    setText('#welcomeTitle', titleText);
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_dashboard_admin', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_dashboard_admin')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_dashboard_admin')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>
