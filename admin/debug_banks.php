<?php
/**
 * Debug - Soru Bankası Tanılama Sayfası
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

// Soruları zorla yeniden yükle
unset($_SESSION['all_questions'], $_SESSION['categories'], $_SESSION['banks'], $_SESSION['question_errors']);
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions  = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories']    ?? [];
$banks      = $_SESSION['banks']         ?? [];
$errors     = $_SESSION['question_errors'] ?? [];

// ROOT_DIR bilgisi
$rootDir = defined('ROOT_DIR') ? ROOT_DIR : 'Sorular';
$rootExists = is_dir($rootDir);

// Tüm alt klasörleri tara (derinlemesine)
function scanDirDeep($dir, $depth = 0, $maxDepth = 6) {
    $result = [];
    if ($depth > $maxDepth || !is_dir($dir)) return $result;
    $items = @scandir($dir);
    if (!$items) return $result;
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $full = $dir . DIRECTORY_SEPARATOR . $item;
        $isDir = is_dir($full);
        $result[] = [
            'name'  => $item,
            'path'  => $full,
            'depth' => $depth,
            'isDir' => $isDir,
            'size'  => $isDir ? null : filesize($full),
            'blocked' => isBlockedItem($item),
        ];
        if ($isDir) {
            $children = scanDirDeep($full, $depth + 1, $maxDepth);
            $result = array_merge($result, $children);
        }
    }
    return $result;
}

function isBlockedItem($name) {
    $blocked = defined('BLOCKED_PATTERNS') ? BLOCKED_PATTERNS : [];
    foreach ($blocked as $p) {
        if (stripos($name, $p) !== false) return $p;
    }
    return false;
}

$allFiles = $rootExists ? scanDirDeep($rootDir) : [];

// İslami İlimler ile ilgili dosyaları filtrele
$islamiFiles = array_filter($allFiles, function($f) {
    return stripos($f['name'], 'islami') !== false || stripos($f['path'], 'islami') !== false;
});

// Bank erişim testi
$testBanks = array_unique(array_merge($banks, ['İslami İlimler', 'İslamiİlimler']));
$accessTests = [];
$testRoles = ['admin', 'superadmin', 'teacher', 'student'];
$testInstitutions = ['IQRA Vorarlberg', 'IQRA Tirol', 'IQRA Bludenz', ''];
foreach ($testBanks as $b) {
    foreach ($testRoles as $r) {
        foreach ($testInstitutions as $i) {
            $accessTests[] = [
                'bank' => $b,
                'role' => $r,
                'institution' => $i ?: '(boş)',
                'access' => $questionLoader->isBankAccessible($b, $i, $r),
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Soru Bankası Tanılama</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Inter', monospace;
            background: #0f172a;
            color: #e2e8f0;
            padding: 20px;
            font-size: 14px;
        }
        h1 { font-size: 1.8rem; color: #f59e0b; margin-bottom: 6px; }
        h1 span { font-size: 1rem; color: #94a3b8; font-weight: 400; }
        .meta { color: #64748b; font-size: 0.85rem; margin-bottom: 24px; }

        .section {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .section-header {
            background: #0f172a;
            padding: 14px 20px;
            font-weight: 700;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #334155;
        }
        .section-body { padding: 16px 20px; }

        .badge {
            display: inline-block;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 600;
        }
        .badge-green  { background: rgba(34,197,94,0.15);  color: #4ade80; }
        .badge-red    { background: rgba(239,68,68,0.15);  color: #f87171; }
        .badge-yellow { background: rgba(251,191,36,0.15); color: #fbbf24; }
        .badge-blue   { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .badge-purple { background: rgba(168,85,247,0.15); color: #c084fc; }

        .kv { display: flex; gap: 12px; margin-bottom: 8px; flex-wrap: wrap; align-items: center; }
        .kv .k { color: #94a3b8; min-width: 200px; }
        .kv .v { color: #f1f5f9; font-family: 'Courier New', monospace; }

        table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
        th { background: #0f172a; padding: 10px 12px; text-align: left; color: #94a3b8; font-weight: 600; border-bottom: 1px solid #334155; }
        td { padding: 8px 12px; border-bottom: 1px solid #1e293b; vertical-align: middle; }
        tr:hover td { background: rgba(255,255,255,0.03); }
        .indent { padding-left: calc(8px + var(--d) * 18px); }
        .dir-row td { color: #60a5fa; }
        .file-row td { color: #94a3b8; }
        .blocked-row td { color: #f87171; text-decoration: line-through; opacity: 0.7; }
        .islami-row td { color: #fbbf24; font-weight: 600; }

        .err-item { background: rgba(239,68,68,0.08); border-left: 3px solid #ef4444; padding: 8px 12px; border-radius: 6px; margin-bottom: 6px; font-family: 'Courier New', monospace; font-size: 0.82rem; color: #fca5a5; }

        .bank-pill { display: inline-flex; align-items: center; gap: 6px; background: rgba(59,130,246,0.1); border: 1px solid rgba(59,130,246,0.2); border-radius: 8px; padding: 4px 12px; margin: 4px; color: #93c5fd; font-size: 0.9rem; }

        .top-nav { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; }
        .top-nav a { background: #1e293b; border: 1px solid #334155; color: #94a3b8; padding: 8px 14px; border-radius: 8px; text-decoration: none; font-size: 0.85rem; transition: all 0.2s; }
        .top-nav a:hover { background: #334155; color: #f1f5f9; }

        .highlight { background: rgba(251,191,36,0.1); border: 1px solid rgba(251,191,36,0.3); padding: 12px 16px; border-radius: 8px; color: #fbbf24; margin-bottom: 12px; }

        .access-yes { color: #4ade80; }
        .access-no  { color: #f87171; }
    </style>
</head>
<body>

<h1>🔬 Soru Bankası Tanılama <span>debug_banks.php</span></h1>
<div class="meta">
    <?= date('Y-m-d H:i:s') ?> &bull; 
    Giriş yapan: <strong><?= htmlspecialchars($user['name'] ?? '?') ?></strong> (<?= htmlspecialchars($user['role'] ?? '?') ?>)
</div>

<div class="top-nav">
    <a href="load_questions.php"><i class="fas fa-arrow-left"></i> Soru Yükleme</a>
    <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="?reload=1"><i class="fas fa-sync"></i> Yenile</a>
</div>

<!-- ===== ÖZET ===== -->
<div class="section">
    <div class="section-header"><i class="fas fa-chart-bar" style="color:#3b82f6;"></i> Genel Durum</div>
    <div class="section-body">
        <div class="kv"><span class="k">ROOT_DIR</span><span class="v"><?= htmlspecialchars($rootDir) ?></span> <span class="badge <?= $rootExists ? 'badge-green' : 'badge-red' ?>"><?= $rootExists ? '✅ Mevcut' : '❌ Bulunamadı' ?></span></div>
        <div class="kv"><span class="k">Toplam Yüklenen Soru</span><span class="v"><?= count($questions) ?></span></div>
        <div class="kv"><span class="k">Yüklenen Banka Sayısı</span><span class="v"><?= count($banks) ?></span></div>
        <div class="kv"><span class="k">Toplam Kategori</span><span class="v"><?= array_sum(array_map('count', $categories)) ?></span></div>
        <div class="kv"><span class="k">Hata Sayısı</span><span class="v <?= count($errors) > 0 ? 'badge-red' : '' ?>"><?= count($errors) ?></span></div>
        <div class="kv"><span class="k">MAX_SCAN_DEPTH</span><span class="v"><?= defined('MAX_SCAN_DEPTH') ? MAX_SCAN_DEPTH : '(tanımsız)' ?></span></div>
        <div class="kv"><span class="k">BLOCKED_PATTERNS</span><span class="v"><?= htmlspecialchars(implode(', ', defined('BLOCKED_PATTERNS') ? BLOCKED_PATTERNS : [])) ?></span></div>
    </div>
</div>

<!-- ===== YÜKLENEN BANKALAR ===== -->
<div class="section">
    <div class="section-header"><i class="fas fa-layer-group" style="color:#8b5cf6;"></i> Yüklenen Bankalar (<?= count($banks) ?>)</div>
    <div class="section-body">
        <?php if (empty($banks)): ?>
            <div class="highlight">⚠️ Hiçbir banka yüklenemedi!</div>
        <?php else: ?>
            <?php foreach ($banks as $bank): ?>
                <?php $isIslami = stripos($bank, 'islami') !== false; ?>
                <span class="bank-pill" style="<?= $isIslami ? 'border-color: rgba(251,191,36,0.5); color: #fbbf24;' : '' ?>">
                    <?= $isIslami ? '⭐' : '<i class="fas fa-database"></i>' ?>
                    <?= htmlspecialchars($bank) ?>
                    <small style="color:#64748b;">(<?= count($categories[$bank] ?? []) ?> kat.)</small>
                </span>
            <?php endforeach; ?>
            <?php if (!in_array('İslami İlimler', $banks) && !in_array('İslamiİlimler', $banks)): ?>
                <div class="highlight" style="margin-top:12px;">❌ <strong>İslami İlimler</strong> bankası yüklenen bankalar arasında YOK!</div>
            <?php else: ?>
                <div class="highlight" style="background: rgba(34,197,94,0.08); border-color: rgba(34,197,94,0.3); color: #4ade80; margin-top:12px;">✅ İslami İlimler bankası yüklendi!</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ===== İSLAMİ İLİMLER DOSYALARI ===== -->
<div class="section">
    <div class="section-header"><i class="fas fa-search" style="color:#f59e0b;"></i> "İslami" İçeren Dosya/Klasörler (<?= count($islamiFiles) ?>)</div>
    <div class="section-body">
        <?php if (empty($islamiFiles)): ?>
            <div class="highlight">❌ Sorular klasöründe "islami" içeren hiçbir dosya/klasör bulunamadı!<br>
            <small style="color:#94a3b8;">Yol: <?= htmlspecialchars($rootDir) ?></small></div>
        <?php else: ?>
            <table>
                <tr><th>Ad</th><th>Tür</th><th>Derinlik</th><th>Boyut</th><th>Tam Yol</th></tr>
                <?php foreach ($islamiFiles as $f): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
                    <td><?= $f['isDir'] ? '<span class="badge badge-blue">📁 Klasör</span>' : '<span class="badge badge-green">📄 Dosya</span>' ?></td>
                    <td><?= $f['depth'] ?></td>
                    <td><?= $f['size'] !== null ? number_format($f['size']) . ' B' : '-' ?></td>
                    <td><small style="color:#64748b; word-break:break-all;"><?= htmlspecialchars($f['path']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===== ENGELLENMİŞ ÖĞELER ===== -->
<?php $blockedItems = array_filter($allFiles, fn($f) => $f['blocked'] !== false); ?>
<div class="section">
    <div class="section-header"><i class="fas fa-ban" style="color:#ef4444;"></i> Engellenen Dosya/Klasörler (<?= count($blockedItems) ?>)</div>
    <div class="section-body">
        <?php if (empty($blockedItems)): ?>
            <div style="color:#4ade80;">✅ Engellenen öğe yok.</div>
        <?php else: ?>
            <table>
                <tr><th>Ad</th><th>Tür</th><th>Engelleme Sebebi</th><th>Yol</th></tr>
                <?php foreach ($blockedItems as $f): ?>
                <tr>
                    <td style="color:#f87171;"><?= htmlspecialchars($f['name']) ?></td>
                    <td><?= $f['isDir'] ? '📁' : '📄' ?></td>
                    <td><span class="badge badge-red"><?= htmlspecialchars($f['blocked']) ?></span></td>
                    <td><small style="word-break:break-all; color:#64748b;"><?= htmlspecialchars($f['path']) ?></small></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===== BANKA ERİŞİM KONTROLÜ ===== -->
<div class="section">
    <div class="section-header"><i class="fas fa-lock" style="color:#ec4899;"></i> Banka Erişim Kontrolü - isBankAccessible() Testi</div>
    <div class="section-body">
        <table>
            <tr><th>Banka</th><th>Rol</th><th>Kurum</th><th>Erişim</th></tr>
            <?php foreach ($accessTests as $t): ?>
            <?php $isIslami = stripos($t['bank'], 'islami') !== false; ?>
            <tr <?= $isIslami ? 'style="background:rgba(251,191,36,0.04);"' : '' ?>>
                <td><?= htmlspecialchars($t['bank']) ?> <?= $isIslami ? '⭐' : '' ?></td>
                <td><span class="badge badge-purple"><?= htmlspecialchars($t['role']) ?></span></td>
                <td><?= htmlspecialchars($t['institution']) ?></td>
                <td class="<?= $t['access'] ? 'access-yes' : 'access-no' ?>"><?= $t['access'] ? '✅ Erişebilir' : '❌ Erişemez' ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</div>

<!-- ===== TÜM KLASöR YAPISI ===== -->
<div class="section">
    <div class="section-header"><i class="fas fa-folder-tree" style="color:#22c55e;"></i> Sorular Klasörü Tam Yapısı (<?= count($allFiles) ?> öğe)</div>
    <div class="section-body">
        <?php if (!$rootExists): ?>
            <div class="highlight">❌ Sorular klasörü mevcut değil: <?= htmlspecialchars($rootDir) ?></div>
        <?php elseif (empty($allFiles)): ?>
            <div class="highlight">⚠️ Klasör boş.</div>
        <?php else: ?>
            <table>
                <tr><th>Ad</th><th>Tür</th><th>Derinlik</th><th>Boyut</th><th>Durum</th></tr>
                <?php foreach ($allFiles as $f):
                    $islami = stripos($f['name'], 'islami') !== false || stripos($f['path'], 'islami') !== false;
                    $rowClass = $f['blocked'] ? 'blocked-row' : ($islami ? 'islami-row' : ($f['isDir'] ? 'dir-row' : 'file-row'));
                ?>
                <tr class="<?= $rowClass ?>">
                    <td>
                        <span style="--d:<?= $f['depth'] ?>; padding-left: calc(<?= $f['depth'] ?> * 18px);">
                            <?= $f['isDir'] ? '📁' : '📄' ?>
                            <?= htmlspecialchars($f['name']) ?>
                        </span>
                    </td>
                    <td><?= $f['isDir'] ? 'Klasör' : 'Dosya' ?></td>
                    <td><?= $f['depth'] ?></td>
                    <td><?= $f['size'] !== null ? number_format($f['size']) . ' B' : '-' ?></td>
                    <td>
                        <?php if ($f['blocked']): ?>
                            <span class="badge badge-red">🚫 Engellendi (<?= $f['blocked'] ?>)</span>
                        <?php elseif ($islami): ?>
                            <span class="badge badge-yellow">⭐ İslami</span>
                        <?php else: ?>
                            <span class="badge badge-green">✅</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
</div>

<!-- ===== HATALAR ===== -->
<?php if (!empty($errors)): ?>
<div class="section">
    <div class="section-header"><i class="fas fa-exclamation-triangle" style="color:#ef4444;"></i> Yükleme Hataları (<?= count($errors) ?>)</div>
    <div class="section-body">
        <?php foreach ($errors as $err): ?>
            <div class="err-item"><?= htmlspecialchars($err) ?></div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

</body>
</html>
