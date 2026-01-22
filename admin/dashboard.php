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
    <link rel="stylesheet" href="css/dark-theme.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <!-- Sidebar -->
    <?php include 'sidebar.php'; ?>

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

