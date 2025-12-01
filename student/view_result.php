<?php
session_start();
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('student')) { header('Location: ../login.php'); exit; }

$user = $auth->getUser();
$examCode = $_GET['exam_code'] ?? '';
if ($examCode === '') { header('Location: exams.php'); exit; }

$resultsFile = '../data/exam_results.json';
$result = null;
if (file_exists($resultsFile)) {
    $allResults = json_decode(file_get_contents($resultsFile), true) ?? [];
    $examResults = $allResults[$examCode] ?? [];
    $studentId = $user['username'] ?? $user['name'] ?? 'unknown';
    foreach ($examResults as $res) {
        if (($res['student_id'] ?? '') === $studentId) { $result = $res; break; }
    }
}

if (!$result) { header('Location: exams.php'); exit; }
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sonucum - Bir Soru Bir Sevap</title>
    <style>
        body { font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f7fafc; color:#2c3e50; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px; }
        .card { background:#fff; border-radius:16px; padding:24px; box-shadow:0 8px 24px rgba(0,0,0,.08); }
        .header { display:flex; justify-content:space-between; align-items:center; margin-bottom:16px; }
        .title { font-size:1.5rem; font-weight:700; }
        .meta { color:#6b7280; font-size:.95rem; }
        .grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap:12px; margin: 16px 0; }
        .stat { background:#f9fafb; border:1px solid #e5e7eb; border-radius:12px; padding:14px; text-align:center; }
        .stat .val { font-size:1.8rem; font-weight:800; color:#0ea5a4; }
        .qa { margin-top: 16px; }
        .qa-item { border-left: 4px solid #e5e7eb; background:#f9fafb; padding:12px; border-radius:8px; margin-bottom:10px; }
        .qa-q { font-weight:700; margin-bottom:6px; }
        .qa-a { color:#374151; }
        .correct { border-left-color:#10b981; background:#ecfdf5; }
        .incorrect { border-left-color:#ef4444; background:#fef2f2; }
        .actions { display:flex; gap:10px; margin-top: 16px; }
        .btn { background:#068567; color:#fff; border:none; padding:10px 14px; border-radius:10px; text-decoration:none; display:inline-block; }
        .btn.secondary { background:#6b7280; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <div>
                    <div class="title">📊 Sınav Sonucunuz</div>
                    <div class="meta">Kod: <?php echo htmlspecialchars($examCode); ?> • Öğrenci: <?php echo htmlspecialchars($result['student_name'] ?? ''); ?> • Tarih: <?php echo htmlspecialchars($result['completed_at'] ?? ''); ?></div>
                </div>
                <a class="btn" href="exams.php">← Sınavlarıma Dön</a>
            </div>
            <div class="grid">
                <div class="stat"><div>Kazanılan Puan</div><div class="val"><?php echo (int)($result['score'] ?? 0); ?>%</div></div>
                <div class="stat"><div>Doğru</div><div class="val"><?php echo (int)($result['correct'] ?? 0); ?></div></div>
                <div class="stat"><div>Yanlış</div><div class="val"><?php echo (int)($result['wrong'] ?? 0); ?></div></div>
                <div class="stat"><div>Süre</div><div class="val"><?php echo htmlspecialchars($result['duration'] ?? ''); ?></div></div>
            </div>
            <?php if (!empty($result['detailed_results'])): ?>
                <div class="qa">
                    <?php foreach ($result['detailed_results'] as $i => $item): ?>
                        <?php $ok = !empty($item['is_correct']); ?>
                        <div class="qa-item <?php echo $ok ? 'correct' : 'incorrect'; ?>">
                            <div class="qa-q"><?php echo ($i+1) . '. ' . htmlspecialchars($item['question'] ?? ''); ?></div>
                            <div class="qa-a"><?php echo $ok ? '✅ Doğru' : '❌ Yanlış'; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <div class="actions">
                <a class="btn" href="dashboard.php">🏠 Ana Sayfa</a>
                <a class="btn secondary" href="practice_setup.php">🚀 Alıştırma Yap</a>
            </div>
        </div>
    </div>
</body>
</html>


