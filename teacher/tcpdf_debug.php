<?php
session_start();
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

function resolveTcpdfPath() {
    $base = __DIR__ . '/..';
    $candidates = [
        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
        __DIR__ . '/../TCPDF-main/tcpdf.php',
        __DIR__ . '/../vendor/tcpdf/tcpdf.php',
        __DIR__ . '/../vendor/autoload.php',
    ];
    foreach ($candidates as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    return null;
}

$diagnostics = [
    'paths' => [],
    'resolved' => null,
    'autoload_used' => false,
    'tcpdf_class_exists' => false,
    'mbstring' => extension_loaded('mbstring'),
    'gd' => extension_loaded('gd'),
    'errors' => []
];

$candidatePaths = [
    '../vendor/tecnickcom/tcpdf/tcpdf.php',
    '../TCPDF-main/tcpdf.php',
    '../vendor/tcpdf/tcpdf.php',
    '../vendor/autoload.php',
];
foreach ($candidatePaths as $p) {
    $diagnostics['paths'][] = [ 'path' => $p, 'exists' => file_exists(__DIR__ . '/' . basename($p)) ? true : file_exists(__DIR__ . '/../' . trim($p, './')) ];
}

$resolved = resolveTcpdfPath();
$diagnostics['resolved'] = $resolved ? str_replace(__DIR__ . '/..', '..', $resolved) : null;

if (isset($_GET['load']) || isset($_GET['test'])) {
    if ($resolved) {
        if (substr($resolved, -12) === 'autoload.php') {
            require_once $resolved;
            $diagnostics['autoload_used'] = true;
        } else {
            require_once $resolved;
        }
        $diagnostics['tcpdf_class_exists'] = class_exists('TCPDF');
    } else {
        $diagnostics['errors'][] = 'TCPDF bulunamadı. vendor veya TCPDF-main yollarını kontrol edin.';
    }
}

if (isset($_GET['test']) && $diagnostics['tcpdf_class_exists']) {
    try {
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        $pdf->SetCreator('Bir Soru Bir Sevap');
        $pdf->SetAuthor($auth->getUser()['name'] ?? 'Teacher');
        $pdf->SetTitle('TCPDF Test');
        $pdf->SetSubject('TCPDF Tanılama');
        $pdf->AddPage();
        $pdf->SetFont('dejavusans', '', 12);
        $pdf->Write(6, "TCPDF başarıyla yüklendi. Bu bir test sayfasıdır.\n" . date('d.m.Y H:i'));
        $pdf->Output('tcpdf_test.pdf', 'I');
        exit;
    } catch (Exception $e) {
        $diagnostics['errors'][] = 'PDF oluşturma hatası: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TCPDF Tanılama - Bir Soru Bir Sevap</title>
    <style>
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Ubuntu, Cantarell, 'Helvetica Neue', Arial; background:#f6f8fb; margin:0; }
        .container { max-width: 900px; margin: 30px auto; background:#fff; border-radius:14px; box-shadow:0 8px 24px rgba(0,0,0,.08); padding:24px; }
        h1 { margin:0 0 8px; font-size:1.6rem; }
        .sub { color:#64748b; margin-bottom:18px; }
        .grid { display:grid; grid-template-columns: 1fr 1fr; gap:12px; }
        .card { border:1px solid #e5e7eb; border-radius:10px; padding:14px; }
        .ok { color:#0ea5e9; font-weight:700; }
        .yes { color:#16a34a; font-weight:700; }
        .no { color:#dc2626; font-weight:700; }
        code { background:#f1f5f9; padding:2px 6px; border-radius:6px; }
        .actions { margin-top:16px; display:flex; gap:10px; flex-wrap:wrap; }
        .btn { background:#089473; color:#fff; border:none; padding:10px 14px; border-radius:10px; cursor:pointer; text-decoration:none; display:inline-block; }
        .btn.secondary { background:#64748b; }
        .errors { background:#fef2f2; border:1px solid #fecaca; color:#991b1b; padding:12px; border-radius:10px; margin-top:12px; }
        ul { margin:8px 0 0 18px; }
    </style>
<head>
<body>
    <div class="container">
        <h1>TCPDF Tanılama</h1>
        <div class="sub">Ortamınızda TCPDF’in erişilebilirliğini test edin ve örnek PDF üretin.</div>

        <div class="grid">
            <div class="card">
                <div><strong>Aday Yollar</strong></div>
                <ul>
                    <?php foreach ($candidatePaths as $p): $exists = file_exists(__DIR__ . '/../' . trim($p, './')); ?>
                        <li><code><?php echo htmlspecialchars($p); ?></code> — <span class="<?php echo $exists?'yes':'no'; ?>"><?php echo $exists?'BULUNDU':'YOK'; ?></span></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card">
                <div><strong>Çözümlenen Yol</strong></div>
                <div style="margin-top:6px;">
                    <?php if ($diagnostics['resolved']): ?>
                        <code><?php echo htmlspecialchars($diagnostics['resolved']); ?></code>
                    <?php else: ?>
                        <span class="no">Belirlenemedi</span>
                    <?php endif; ?>
                </div>
                <div style="margin-top:10px;"><strong>Autoload:</strong> <span class="<?php echo $diagnostics['autoload_used']?'yes':'no'; ?>"><?php echo $diagnostics['autoload_used']?'Kullanıldı':'Kullanılmadı'; ?></span></div>
                <div style="margin-top:6px;"><strong>TCPDF Sınıfı:</strong> <span class="<?php echo $diagnostics['tcpdf_class_exists']?'yes':'no'; ?>"><?php echo $diagnostics['tcpdf_class_exists']?'Yüklü':'Yüklü Değil'; ?></span></div>
            </div>
            <div class="card">
                <div><strong>PHP Uzantıları</strong></div>
                <ul>
                    <li>mbstring — <span class="<?php echo $diagnostics['mbstring']?'yes':'no'; ?>"><?php echo $diagnostics['mbstring']?'Var':'Yok'; ?></span></li>
                    <li>gd — <span class="<?php echo $diagnostics['gd']?'yes':'no'; ?>"><?php echo $diagnostics['gd']?'Var':'Yok'; ?></span></li>
                </ul>
            </div>
            <div class="card">
                <div><strong>İpuçları</strong></div>
                <ul>
                    <li>Composer ile: <code>composer require tecnickcom/tcpdf</code></li>
                    <li>Ya da proje içindeki <code>TCPDF-main/</code> klasörünü kullanın.</li>
                    <li>Sunucuda <code>vendor/</code> dizininin okuma izni olduğundan emin olun.</li>
                </ul>
            </div>
        </div>

        <?php if (!empty($diagnostics['errors'])): ?>
            <div class="errors">
                <?php foreach ($diagnostics['errors'] as $e): ?>
                    <div>• <?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="actions">
            <a href="tcpdf_debug.php?load=1" class="btn">Yeniden Tara</a>
            <?php if ($diagnostics['resolved']): ?>
                <a href="tcpdf_debug.php?test=1" class="btn secondary">Örnek PDF Oluştur</a>
            <?php endif; ?>
            <a href="dashboard.php" class="btn secondary">← Dashboard</a>
        </div>
    </div>
</body>
</html>


