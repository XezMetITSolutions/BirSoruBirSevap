<?php
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('teacher')) {
    header('Location: ../login.php');
    exit;
}
$user = $auth->getUser();

$metaPath = __DIR__ . '/../data/exam_prints.json';
$prints = file_exists($metaPath) ? (json_decode(file_get_contents($metaPath), true) ?: []) : [];
// Yeni kaydedilenler sonda; tarihe göre tersten sırala
usort($prints, function($a,$b){ return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Geçmiş PDF Sınavlar</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f8fafc; margin:0; color:#0f172a; }
        .container { max-width: 1100px; margin: 0 auto; padding: 20px; }
        .header { display:flex; align-items:center; justify-content:space-between; margin: 12px 0 18px; }
        .title { font-size: 1.6rem; font-weight: 800; }
        .back { text-decoration:none; padding:10px 14px; border-radius:10px; border:1px solid #e5e7eb; color:#0f172a; background:#fff; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:14px; padding:16px; margin-bottom:12px; display:flex; justify-content:space-between; align-items:center; }
        .meta { color:#475569; font-size:.95rem; }
        .badge { display:inline-block; background:#f1f5f9; border:1px solid #e2e8f0; padding:4px 8px; border-radius:8px; margin-left:8px; font-size:.85rem; }
        .row { display:flex; flex-direction:column; gap:4px; }
        .actions { display:flex; gap:8px; }
        .btn { text-decoration:none; padding:8px 12px; border-radius:8px; border:1px solid #e5e7eb; background:#fff; color:#0f172a; }
        .btn-primary { background:#0aa07c; border-color:#0aa07c; color:#fff; }
    </style>
    <script>
        function removePrint(file){
            if(!confirm('Bu kaydı silmek istiyor musunuz?')) return;
            fetch('exam_prints_remove.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'file='+encodeURIComponent(file) })
            .then(()=> location.reload());
        }
    </script>
    </head>
<body>
    <div class="container">
        <div class="header">
            <div class="title">📄 Geçmiş PDF Sınavlar</div>
            <a href="exam_management.php" class="back">← Sınav Yönetimi</a>
        </div>
        <?php if (empty($prints)): ?>
            <div class="card"><div>Henüz kayıt yok.</div><div></div></div>
        <?php else: ?>
            <?php foreach ($prints as $p): ?>
                <div class="card">
                    <div class="row">
                        <div><strong><?php echo htmlspecialchars($p['title'] ?? 'Sınav'); ?></strong> <span class="badge"><?php echo (int)($p['questions'] ?? 0); ?> soru</span></div>
                        <div class="meta">Oluşturma: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($p['created_at'] ?? 'now'))); ?> — Eğitmen: <?php echo htmlspecialchars($p['teacher'] ?? ''); ?></div>
                    </div>
                    <div class="actions">
                        <a class="btn btn-primary" href="../<?php echo htmlspecialchars($p['file']); ?>" target="_blank">🖨️ Aç / Yazdır</a>
                        <button class="btn" onclick="removePrint('<?php echo htmlspecialchars($p['file']); ?>')">🗑️ Sil</button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>


