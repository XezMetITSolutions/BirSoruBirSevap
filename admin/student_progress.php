<?php
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('superadmin') && !$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

// Veri kaynakları
$practiceFile = __DIR__ . '/../data/practice_results.json';
$examFile = __DIR__ . '/../data/exam_results.json';

function readJsonFile($path) {
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

$practiceResults = readJsonFile($practiceFile);
$examResults = readJsonFile($examFile);

// Öğrenci bazlı grupla
$studentProgress = [];

foreach ($practiceResults as $row) {
    $u = $row['username'] ?? 'unknown';
    if (!isset($studentProgress[$u])) $studentProgress[$u] = ['practice'=>[], 'exams'=>[]];
    $studentProgress[$u]['practice'][] = $row;
}
foreach ($examResults as $row) {
    $u = $row['username'] ?? 'unknown';
    if (!isset($studentProgress[$u])) $studentProgress[$u] = ['practice'=>[], 'exams'=>[]];
    $studentProgress[$u]['exams'][] = $row;
}

$students = array_keys($studentProgress);
sort($students);

$selectedUser = $_GET['user'] ?? ($students[0] ?? '');

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Gelişimi - Admin</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:#f8fafc; color:#0f172a; margin:0; }
        .header { background:#0aa07c; color:#fff; padding:16px 20px; }
        .container { max-width:1200px; margin:0 auto; padding:20px; }
        .card { background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:16px; box-shadow:0 10px 24px rgba(2,6,23,.06); }
        .grid { display:grid; grid-template-columns: 1fr 2fr; gap:16px; }
        @media (max-width: 900px){ .grid { grid-template-columns: 1fr; } }
        .table { width:100%; border-collapse: collapse; }
        .table th, .table td { padding:10px; border-bottom:1px solid #e5e7eb; text-align:left; font-size:.95rem; }
        .table th { background:#f1f5f9; font-weight:700; }
        .badge { display:inline-block; padding:4px 8px; border-radius:999px; font-size:.8rem; border:1px solid #e5e7eb; background:#f8fafc; }
        .row { display:flex; gap:10px; align-items:center; }
        .muted { color:#64748b; font-size:.9rem; }
        select, input { padding:10px 12px; border:1.5px solid #e5e7eb; border-radius:10px; font-size:.95rem; }
        .section-title { font-weight:800; margin:0 0 10px 0; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;">
            <div style="font-weight:800; font-size:1.2rem;">Öğrenci Gelişimi</div>
            <div class="row">
                <a href="dashboard.php" style="color:#fff;text-decoration:none;">← Dashboard</a>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="card" style="margin-bottom:16px;">
            <div class="row" style="justify-content: space-between;">
                <div>
                    <div class="section-title">Öğrenci Seç</div>
                    <form method="GET" class="row" style="gap:8px;">
                        <select name="user" onchange="this.form.submit()">
                            <?php foreach ($students as $u): ?>
                                <option value="<?php echo htmlspecialchars($u); ?>" <?php echo $u===$selectedUser?'selected':''; ?>>
                                    <?php echo htmlspecialchars($u); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <noscript><button type="submit">Göster</button></noscript>
                    </form>
                    <div class="muted">Toplam öğrenci: <?php echo count($students); ?></div>
                </div>
                <div>
                    <?php 
                    $pCount = isset($studentProgress[$selectedUser]) ? count($studentProgress[$selectedUser]['practice']) : 0;
                    $eCount = isset($studentProgress[$selectedUser]) ? count($studentProgress[$selectedUser]['exams']) : 0;
                    ?>
                    <div class="row" style="gap:8px;">
                        <span class="badge">Alıştırma: <?php echo $pCount; ?></span>
                        <span class="badge">Sınav: <?php echo $eCount; ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid">
            <div class="card">
                <h3 class="section-title">Alıştırmalar</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Soru</th>
                            <th>Doğru</th>
                            <th>Yanlış</th>
                            <th>Yüzde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($studentProgress[$selectedUser]['practice'])): ?>
                            <?php foreach (array_reverse($studentProgress[$selectedUser]['practice']) as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></td>
                                    <td><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                    <td><?php echo (int)($row['correct_answers'] ?? 0); ?></td>
                                    <td><?php echo (int)($row['wrong_answers'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(number_format((float)($row['percentage'] ?? 0), 2)); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="muted">Kayıt bulunamadı</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card">
                <h3 class="section-title">Sınavlar</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <th>Sınav ID</th>
                            <th>Toplam</th>
                            <th>Doğru</th>
                            <th>Yüzde</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($studentProgress[$selectedUser]['exams'])): ?>
                            <?php foreach (array_reverse($studentProgress[$selectedUser]['exams']) as $row): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($row['created_at'] ?? $row['submit_time'] ?? '-'); ?></td>
                                    <td><?php echo htmlspecialchars($row['exam_id'] ?? '-'); ?></td>
                                    <td><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                    <td><?php echo (int)($row['correct_answers'] ?? 0); ?></td>
                                    <td><?php echo htmlspecialchars(number_format((float)($row['percentage'] ?? 0), 2)); ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="muted">Kayıt bulunamadı</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>


