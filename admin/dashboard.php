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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --secondary: #3b82f6;
            --text-light: #f1f5f9;
            --text-muted: #94a3b8;
            --sidebar-width: 280px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top left, #1a2942 0%, #0f172a 100%);
            color: var(--text-light);
            min-height: 100vh;
            display: flex;
            overflow-x: hidden;
        }

        /* Ambient Background */
        .bg-decoration {
            position: fixed;
            top: 0; left: 0; width: 100vw; height: 100vh;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }
        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s infinite ease-in-out;
        }
        .blob-1 { top: -10%; left: -10%; width: 600px; height: 600px; background: var(--primary); }
        .blob-2 { bottom: -10%; right: -10%; width: 500px; height: 500px; background: var(--secondary); animation-delay: -5s; }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        /* Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.05);
            display: flex;
            flex-direction: column;
            position: fixed;
            height: 100vh;
            z-index: 50;
        }

        .sidebar-header {
            padding: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .sidebar-header img {
            height: 40px;
            width: auto;
            filter: drop-shadow(0 0 10px rgba(6, 133, 103, 0.4));
        }

        .sidebar-header h1 {
            font-size: 1.1rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-menu {
            padding: 20px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 10px;
            overflow-y: auto;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            text-decoration: none;
            color: var(--text-muted);
            border-radius: 12px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-item:hover, .nav-item.active {
            background: rgba(255, 255, 255, 0.08);
            color: #fff;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            transition: transform 0.3s;
        }

        .nav-item:hover i { transform: scale(1.1); }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.05);
        }

        .user-mini {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            background: rgba(255, 255, 255, 0.03);
            border-radius: 12px;
            margin-bottom: 10px;
        }

        .avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 0.9rem;
        }

        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 30px;
            max-width: 1600px;
        }

        .top-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .welcome-text h2 {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 5px;
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-text p { color: var(--text-muted); }

        .actions {
            display: flex;
            gap: 12px;
        }

        .action-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--text-light);
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }

        .action-btn:hover { background: rgba(255, 255, 255, 0.1); transform: translateY(-2px); }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            padding: 24px;
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease;
        }

        .stat-card:hover { transform: translateY(-5px); background: rgba(255, 255, 255, 0.05); }

        .stat-icon {
            position: absolute;
            top: 20px;
            right: 20px;
            font-size: 1.5rem;
            opacity: 0.2;
            color: #fff;
        }

        .stat-value {
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 5px;
            color: #fff;
        }

        .stat-label { color: var(--text-muted); font-size: 0.95rem; }

        .progress-mini {
            height: 4px;
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
            margin-top: 15px;
            overflow: hidden;
        }
        .progress-fill { height: 100%; border-radius: 4px; transition: width 1s ease; }
        
        /* Content Sections */
        .content-row {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .glass-panel {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.05);
            border-radius: 20px;
            padding: 25px;
        }

        .panel-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .panel-title { font-size: 1.1rem; font-weight: 600; color: #fff; display: flex; align-items: center; gap: 10px; }

        .bank-list { display: flex; flex-direction: column; gap: 12px; }
        
        .bank-item {
            background: rgba(255, 255, 255, 0.02);
            padding: 16px;
            border-radius: 12px;
            border-left: 3px solid var(--primary);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .bank-info strong { color: #e2e8f0; display: block; font-size: 0.95rem; margin-bottom: 4px; }
        .bank-info span { color: var(--text-muted); font-size: 0.85rem; }

        .alert-box {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #fca5a5;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .success-box {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.2);
            color: #86efac;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        @media (max-width: 1024px) {
            .content-row { grid-template-columns: 1fr; }
            .sidebar { transform: translateX(-100%); transition: transform 0.3s; }
            .sidebar.open { transform: translateX(0); }
            .main-wrapper { margin-left: 0; }
            .menu-toggle { display: block; }
        }
        
        .menu-toggle { display: none; font-size: 1.5rem; color: #fff; cursor: pointer; }

    </style>
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../logo.png" alt="BSBS Logo">
            <h1>Bir Soru<br>Bir Sevap</h1>
        </div>
        
        <nav class="nav-menu">
            <a href="dashboard.php" class="nav-item active" id="navDash">
                <i class="fas fa-home"></i>
                <span>Panel</span>
            </a>
            <a href="users.php" class="nav-item" id="navUsers">
                <i class="fas fa-users"></i>
                <span>Kullanıcılar</span>
            </a>
            <a href="load_questions.php" class="nav-item" id="navQuestions">
                <i class="fas fa-book"></i>
                <span>Soru Yükleme</span>
            </a>
             <a href="student_progress.php" class="nav-item" id="navProgress">
                <i class="fas fa-chart-line"></i>
                <span>Öğrenci Gelişimi</span>
            </a>
            <a href="reports.php" class="nav-item" id="navReports">
                <i class="fas fa-file-alt"></i>
                <span>Raporlar</span>
            </a>
            <a href="settings.php" class="nav-item" id="navSettings">
                <i class="fas fa-cog"></i>
                <span>Ayarlar</span>
            </a>
            <a href="../index.php" class="nav-item" id="navHome">
                <i class="fas fa-external-link-alt"></i>
                <span>Siteyi Görüntüle</span>
            </a>
        </nav>

        <div class="sidebar-footer">
            <div class="user-mini">
                <div class="avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                <div style="flex:1; overflow:hidden;">
                    <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size:0.8rem; color:var(--text-muted);">Admin</div>
                </div>
            </div>
            <a href="../logout.php" class="action-btn" style="justify-content:center; width:100%; color:#fca5a5; border-color:rgba(239,68,68,0.2);" id="btnLogout">
                <i class="fas fa-sign-out-alt"></i> Çıkış Yap
            </a>
        </div>
    </aside>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="top-bar">
            <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </div>
            <div class="welcome-text">
                <h2 id="welcomeTitle">Hoş Geldiniz, <?php echo htmlspecialchars($user['name']); ?>!</h2>
                <p id="welcomeDesc">Bugün sistemde neler oluyor?</p>
            </div>
            <div class="actions">
                <button class="action-btn" id="langToggle">DE</button>
                <a href="?action=reload_questions" class="action-btn" style="background:rgba(6,133,103,0.2); border-color:#068567; color:#fff;">
                    <i class="fas fa-sync-alt"></i> <span id="btnRefresh">Yenile</span>
                </a>
            </div>
        </div>

        <?php if (!empty($reloadMessage)): ?>
            <div class="success-box">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($reloadMessage); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert-box">
                <h4 style="margin-bottom:10px; display:flex; align-items:center; gap:8px;"><i class="fas fa-exclamation-triangle"></i> <span id="errTitle">Sistem Uyarıları</span></h4>
                <ul style="list-style:none; padding:0;">
                    <?php foreach ($errors as $error): ?>
                        <li style="margin-bottom:5px; font-size:0.9rem;">• <?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-question-circle stat-icon"></i>
                <div class="stat-value"><?php echo number_format($totalQuestions); ?></div>
                <div class="stat-label" id="lblQuestions">Toplam Soru</div>
                <div class="progress-mini"><div class="progress-fill" style="width:100%; background:#3b82f6;"></div></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-database stat-icon"></i>
                <div class="stat-value"><?php echo $totalBanks; ?></div>
                <div class="stat-label" id="lblBanks">Soru Bankası</div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo min(100, $totalBanks*10); ?>%; background:#8b5cf6;"></div></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-folder stat-icon"></i>
                <div class="stat-value"><?php echo $totalCategories; ?></div>
                <div class="stat-label" id="lblCats">Kategori</div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo min(100, $totalCategories*5); ?>%; background:#ec4899;"></div></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-bug stat-icon"></i>
                <div class="stat-value" style="color:<?php echo $errorCount > 0 ? '#fca5a5' : '#86efac'; ?>"><?php echo $errorCount; ?></div>
                <div class="stat-label" id="lblErrors">Sistem Hatası</div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo $errorCount > 0 ? '100%' : '0%'; ?>; background:#ef4444;"></div></div>
            </div>
        </div>

        <div class="content-row">
            <div class="glass-panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-layer-group"></i> <span id="titleBanks">Aktif Bankalar</span></div>
                </div>
                <div class="bank-list">
                    <?php if (!empty($banks)): ?>
                        <?php foreach ($banks as $bank): ?>
                            <div class="bank-item">
                                <div class="bank-info">
                                    <strong><?php echo htmlspecialchars($bank); ?></strong>
                                    <span><i class="fas fa-folder-open"></i> <?php echo count($categories[$bank] ?? []); ?> <span class="txt-cat">Kategori</span></span>
                                </div>
                                <div style="width:8px; height:8px; background:#4ade80; border-radius:50%; box-shadow:0 0 10px #4ade80;"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:30px; color:var(--text-muted);" id="msgNoBanks">Henüz yüklü banka yok.</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="glass-panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-bolt"></i> <span id="titleQuick">Hızlı İşlemler</span></div>
                </div>
                <div style="display:flex; flex-direction:column; gap:10px;">
                    <a href="users.php?action=new" class="action-btn">
                        <i class="fas fa-user-plus"></i> <span id="btnNewUser">Yeni Kullanıcı Ekle</span>
                    </a>
                    <a href="question_editor.php" class="action-btn">
                        <i class="fas fa-plus-circle"></i> <span id="btnNewQ">Soru Düzenleyici</span>
                    </a>
                    <a href="backup.php" class="action-btn">
                        <i class="fas fa-download"></i> <span id="btnBackup">Yedek Al</span>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const tr = {
                navDash: 'Panel', navUsers: 'Kullanıcılar', navQuestions: 'Soru Yükleme', navProgress: 'Öğrenci Gelişimi', navReports: 'Raporlar', navSettings: 'Ayarlar', navHome: 'Siteyi Görüntüle', btnLogout: 'Çıkış Yap',
                welcomeTitle: 'Hoş Geldiniz, {name}!', welcomeDesc: 'Bugün sistemde neler oluyor?', btnRefresh: 'Yenile', errTitle: 'Sistem Uyarıları',
                lblQuestions: 'Toplam Soru', lblBanks: 'Soru Bankası', lblCats: 'Kategori', lblErrors: 'Sistem Hatası',
                titleBanks: 'Aktif Bankalar', txtCat: 'Kategori', msgNoBanks: 'Henüz yüklü banka yok.',
                titleQuick: 'Hızlı İşlemler', btnNewUser: 'Yeni Kullanıcı Ekle', btnNewQ: 'Soru Düzenleyici', btnBackup: 'Yedek Al'
            };
            const de = {
                navDash: 'Dashboard', navUsers: 'Benutzer', navQuestions: 'Fragen laden', navProgress: 'Schülerfortschritt', navReports: 'Berichte', navSettings: 'Einstellungen', navHome: 'Website anzeigen', btnLogout: 'Abmelden',
                welcomeTitle: 'Willkommen, {name}!', welcomeDesc: 'Was passiert heute im System?', btnRefresh: 'Aktualisieren', errTitle: 'Systemwarnungen',
                lblQuestions: 'Gesamtfragen', lblBanks: 'Fragendatenbanken', lblCats: 'Kategorien', lblErrors: 'Systemfehler',
                titleBanks: 'Aktive Datenbanken', txtCat: 'Kategorien', msgNoBanks: 'Keine Datenbank geladen.',
                titleQuick: 'Schnelle Aktionen', btnNewUser: 'Benutzer hinzufügen', btnNewQ: 'Fragen-Editor', btnBackup: 'Backup erstellen'
            };

            function setText(sel, text) { const el = document.querySelector(sel); if(el) el.innerText = text; }
            function setHTML(sel, html) { const el = document.querySelector(sel); if(el) el.innerHTML = html; }

            function apply(lang) {
                const d = lang === 'de' ? de : tr;
                
                const nameProto = '<?php echo htmlspecialchars($user['name']); ?>';
                setText('#welcomeTitle', d.welcomeTitle.replace('{name}', nameProto));
                setText('#welcomeDesc', d.welcomeDesc);
                
                setText('#navDash span', d.navDash);
                setText('#navUsers span', d.navUsers);
                setText('#navQuestions span', d.navQuestions);
                setText('#navProgress span', d.navProgress);
                setText('#navReports span', d.navReports);
                setText('#navSettings span', d.navSettings);
                setText('#navHome span', d.navHome);
                // btnLogout is special due to icon
                const btnLogout = document.getElementById('btnLogout');
                if(btnLogout) btnLogout.innerHTML = '<i class="fas fa-sign-out-alt"></i> ' + d.btnLogout;

                setText('#btnRefresh', d.btnRefresh);
                setText('#errTitle', d.errTitle);

                setText('#lblQuestions', d.lblQuestions);
                setText('#lblBanks', d.lblBanks);
                setText('#lblCats', d.lblCats);
                setText('#lblErrors', d.lblErrors);

                setText('#titleBanks', d.titleBanks);
                document.querySelectorAll('.txt-cat').forEach(el => el.innerText = d.txtCat);
                setText('#msgNoBanks', d.msgNoBanks);

                setText('#titleQuick', d.titleQuick);
                setText('#btnNewUser', d.btnNewUser);
                setText('#btnNewQ', d.btnNewQ);
                setText('#btnBackup', d.btnBackup);

                const btnLang = document.getElementById('langToggle');
                if(btnLang) btnLang.innerText = lang === 'de' ? 'TR' : 'DE';
                localStorage.setItem('lang_admin', lang);
            }

            document.addEventListener('DOMContentLoaded', () => {
                const lang = localStorage.getItem('lang_admin') || 'tr';
                apply(lang);
                
                const btn = document.getElementById('langToggle');
                if(btn) {
                    btn.addEventListener('click', () => {
                        const cur = localStorage.getItem('lang_admin') || 'tr';
                        apply(cur === 'de' ? 'tr' : 'de');
                    });
                }
            });
        })();
    </script>
</body>
</html>
