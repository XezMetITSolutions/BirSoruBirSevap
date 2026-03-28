<?php
/**
 * Bir Soru Bir Sevap - Sistem Tanılama ve Debug Paneli
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('admin') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Soruları yeniden yükle (tazeleme için)
if (isset($_GET['reload'])) {
    unset($_SESSION['all_questions'], $_SESSION['categories'], $_SESSION['banks'], $_SESSION['question_errors']);
}

$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions     = $_SESSION['all_questions'] ?? [];
$categoriesMap = $_SESSION['categories']    ?? [];
$banks         = $_SESSION['banks']         ?? [];
$errors        = $_SESSION['question_errors'] ?? [];

// İstatistikleri hesapla
$totalQuestions = count($questions);
$totalBanks = count($banks);
$totalCategories = array_sum(array_map('count', $categoriesMap));

// Banka detaylarını hazırla
$bankDetails = [];
foreach ($banks as $bankName) {
    $bankQuestions = array_filter($questions, fn($q) => $q['bank'] === $bankName);
    $bankCats = $categoriesMap[$bankName] ?? [];
    
    $catCounts = [];
    foreach ($bankCats as $cat) {
        $catCounts[$cat] = count(array_filter($bankQuestions, fn($q) => $q['category'] === $cat));
    }
    
    $bankDetails[$bankName] = [
        'total' => count($bankQuestions),
        'categories' => $catCounts
    ];
}

// Sunucu bilgileri
$serverInfo = [
    'PHP Versiyonu' => PHP_VERSION,
    'İşletim Sistemi' => PHP_OS,
    'Soru Klasörü' => defined('ROOT_DIR') ? ROOT_DIR : 'Bulunamadı',
    'Yazma İzni' => is_writable('../Sorular') ? '✅ Evet' : '❌ Hayır',
    'Max Upload' => ini_get('upload_max_filesize'),
    'Memory Limit' => ini_get('memory_limit'),
    'Time Limit' => ini_get('max_execution_time') . 's',
    'Hata Ayıklama' => defined('DEBUG_MODE') && DEBUG_MODE ? 'Açık' : 'Kapalı'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Debug - Bir Soru Bir Sevap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #068567;
            --primary-light: #08a781;
            --dark: #0f172a;
            --dark-card: #1e293b;
            --text: #f1f5f9;
            --text-muted: #94a3b8;
            --accent: #fbbf24;
            --danger: #ef4444;
            --success: #22c55e;
            --border: rgba(255, 255, 255, 0.1);
            --glass: rgba(30, 41, 59, 0.7);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Outfit', sans-serif;
            background: var(--dark);
            color: var(--text);
            min-height: 100vh;
            background-image: radial-gradient(circle at 0% 0%, rgba(6, 133, 103, 0.15) 0%, transparent 50%),
                              radial-gradient(circle at 100% 100%, rgba(251, 191, 36, 0.05) 0%, transparent 50%);
            padding: 2rem;
        }

        .container { max-width: 1200px; margin: 0 auto; }

        /* Header */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2.5rem;
        }

        .title-group h1 {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #fff 0%, #94a3b8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .title-group p { color: var(--text-muted); font-size: 1.1rem; }

        .actions { display: flex; gap: 1rem; }
        
        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 1rem;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--border);
            cursor: pointer;
        }

        .btn-glass { background: var(--glass); color: white; backdrop-filter: blur(10px); }
        .btn-glass:hover { background: rgba(255,255,255,0.1); transform: translateY(-2px); }
        
        .btn-primary { background: var(--primary); color: white; border: none; }
        .btn-primary:hover { background: var(--primary-light); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(6, 133, 103, 0.3); }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--dark-card);
            padding: 1.5rem;
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: transform 0.3s;
        }

        .stat-card:hover { transform: translateY(-5px); }

        .stat-card i {
            position: absolute;
            right: -10px;
            bottom: -10px;
            font-size: 6rem;
            opacity: 0.05;
            transform: rotate(-15deg);
        }

        .stat-label { color: var(--text-muted); font-weight: 500; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; }
        .stat-value { font-size: 2.5rem; font-weight: 800; margin-top: 0.5rem; display: block; }
        .stat-footer { margin-top: 1rem; font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem; }

        /* Main Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .glass-panel {
            background: var(--glass);
            backdrop-filter: blur(10px);
            border-radius: 2rem;
            border: 1px solid var(--border);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .panel-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            color: white;
        }

        .panel-title i { color: var(--accent); }

        /* Bank List */
        .bank-item {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid var(--border);
            border-radius: 1.25rem;
            margin-bottom: 1rem;
            overflow: hidden;
        }

        .bank-head {
            padding: 1.25rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            cursor: pointer;
            transition: background 0.3s;
        }

        .bank-head:hover { background: rgba(255, 255, 255, 0.05); }

        .bank-name { font-weight: 600; font-size: 1.1rem; display: flex; align-items: center; gap: 0.75rem; }
        .bank-name i { color: var(--primary); }

        .bank-badge {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            padding: 0.25rem 0.75rem;
            border-radius: 0.5rem;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .category-list {
            padding: 0 1.25rem 1.25rem 1.25rem;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
            gap: 0.75rem;
            border-top: 1px solid var(--border);
            margin-top: 0.5rem;
            padding-top: 1.25rem;
        }

        .category-pill {
            background: rgba(255, 255, 255, 0.05);
            padding: 0.75rem 1rem;
            border-radius: 0.75rem;
            display: flex;
            justify-content: space-between;
            font-size: 0.9rem;
        }

        .category-count { color: var(--accent); font-weight: 700; }

        /* Server Info Table */
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 1rem 0;
            border-bottom: 1px solid var(--border);
        }

        .info-row:last-child { border: none; }
        .info-label { color: var(--text-muted); }
        .info-value { font-weight: 600; font-family: 'Courier New', monospace; color: var(--accent); }

        /* Error Log */
        .error-log {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.2);
            border-radius: 1rem;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
        }

        .error-item {
            color: #fca5a5;
            font-family: 'Courier New', monospace;
            padding: 0.5rem 0;
            border-bottom: 1px solid rgba(239, 68, 68, 0.1);
            font-size: 0.85rem;
        }

        .error-item:last-child { border: none; }

        @media (max-width: 900px) {
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="container">
    <header class="header">
        <div class="title-group">
            <h1>Sistem Teşhisi</h1>
            <p>Soru bankaları ve sunucu altyapısı canlı analiz raporu.</p>
        </div>
        <div class="actions">
            <a href="dashboard.php" class="btn btn-glass">
                <i class="fas fa-arrow-left"></i> Panale Dön
            </a>
            <a href="?reload=1" class="btn btn-primary">
                <i class="fas fa-sync"></i> Tümünü Yenile
            </a>
        </div>
    </header>

    <!-- Stats -->
    <div class="stats-grid">
        <div class="stat-card">
            <i class="fas fa-tasks"></i>
            <span class="stat-label">Toplam Soru</span>
            <span class="stat-value"><?= number_format($totalQuestions) ?></span>
            <div class="stat-footer">
                <span style="color:var(--success)"><i class="fas fa-check-circle"></i> Aktif</span>
            </div>
        </div>
        <div class="stat-card">
            <i class="fas fa-database"></i>
            <span class="stat-label">Soru Bankası</span>
            <span class="stat-value"><?= $totalBanks ?></span>
            <div class="stat-footer">
                <span style="color:var(--accent)"><i class="fas fa-star"></i> <?= count(array_filter($banks, fn($b) => stripos($b, 'islami') !== false)) ?> Özel Banka</span>
            </div>
        </div>
        <div class="stat-card">
            <i class="fas fa-th-large"></i>
            <span class="stat-label">Kategori</span>
            <span class="stat-value"><?= $totalCategories ?></span>
            <div class="stat-footer">
                <span style="color:var(--text-muted)"><?= round($totalQuestions / ($totalCategories ?: 1), 1) ?> Soru/Kat. Ortalama</span>
            </div>
        </div>
        <div class="stat-card">
            <i class="fas fa-bug"></i>
            <span class="stat-label">Hata/Uyarı</span>
            <span class="stat-value" style="color:<?= count($errors) > 0 ? 'var(--danger)' : 'var(--success)' ?>"><?= count($errors) ?></span>
            <div class="stat-footer">
                <span><?= count($errors) > 0 ? 'İlgilenilmesi gerekiyor' : 'Sistem stabil' ?></span>
            </div>
        </div>
    </div>

    <div class="content-grid">
        <!-- Bank Details -->
        <div class="main-content">
            <div class="glass-panel">
                <div class="panel-title">
                    <i class="fas fa-layer-group"></i>
                    Banka ve Kategori Detayları
                </div>
                
                <?php foreach ($bankDetails as $name => $info): ?>
                <div class="bank-item">
                    <div class="bank-head">
                        <div class="bank-name">
                            <i class="<?= stripos($name, 'islami') !== false ? 'fas fa-star' : 'fas fa-folder' ?>"></i>
                            <?= htmlspecialchars($name) ?>
                        </div>
                        <div class="bank-badge">
                            <?= $info['total'] ?> Soru
                        </div>
                    </div>
                    <div class="category-list">
                        <?php foreach ($info['categories'] as $cat => $count): ?>
                        <div class="category-pill">
                            <span><?= htmlspecialchars($cat) ?></span>
                            <span class="category-count"><?= $count ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Sidebar / Info -->
        <div class="side-content">
            <div class="glass-panel">
                <div class="panel-title">
                    <i class="fas fa-server"></i>
                    Sunucu Durumu
                </div>
                <div class="server-info-list">
                    <?php foreach ($serverInfo as $label => $val): ?>
                    <div class="info-row">
                        <span class="info-label"><?= $label ?></span>
                        <span class="info-value"><?= $val ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if (!empty($errors)): ?>
            <div class="glass-panel">
                <div class="panel-title" style="color: var(--danger);">
                    <i class="fas fa-exclamation-triangle"></i>
                    Sistem Logları (Hatalar)
                </div>
                <div class="error-log">
                    <?php foreach ($errors as $err): ?>
                    <div class="error-item">• <?= htmlspecialchars($err) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>
