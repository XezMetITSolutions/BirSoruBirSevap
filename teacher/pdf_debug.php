<?php
// Basit TCPDF tanılama ve test sayfası
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
	header('Location: ../login.php');
	exit;
}

header('Content-Type: text/html; charset=utf-8');

function resolveTcpdfPath() {
	$candidates = [
		__DIR__ . '/../TCPDF-main/tcpdf.php',
		__DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
		__DIR__ . '/../vendor/tcpdf/tcpdf.php',
		__DIR__ . '/../vendor/autoload.php',
	];
	foreach ($candidates as $path) {
		if (file_exists($path)) return $path;
	}
	return null;
}

$diag = [
	'php_version' => PHP_VERSION,
	'extensions' => [
		'mbstring' => extension_loaded('mbstring'),
		'gd' => extension_loaded('gd'),
	],
	'tcpdf' => [
		'path' => null,
		'loaded' => false,
		'class_exists_before' => class_exists('TCPDF', false),
		'class_exists_after' => false,
		'constants_available' => false,
		'load_error' => null,
	],
];

$tcpdfPath = resolveTcpdfPath();
$diag['tcpdf']['path'] = $tcpdfPath;

if ($tcpdfPath) {
	try {
		require_once $tcpdfPath;
		$diag['tcpdf']['loaded'] = true;
		$diag['tcpdf']['class_exists_after'] = class_exists('TCPDF', false);
		$diag['tcpdf']['constants_available'] = defined('PDF_PAGE_ORIENTATION');
	} catch (Throwable $e) {
		$diag['tcpdf']['load_error'] = $e->getMessage();
	}
}

$action = $_GET['action'] ?? '';
if ($action === 'sample' && $diag['tcpdf']['loaded'] && class_exists('TCPDF')) {
	// Basit örnek PDF üret
	$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
	$pdf->SetCreator('Bir Soru Bir Sevap');
	$pdf->SetAuthor('Debug');
	$pdf->SetTitle('TCPDF Test');
	$pdf->SetMargins(15, 20, 15);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetFont('dejavusans', '', 12);
	$pdf->AddPage();
	$pdf->Write(6, "Merhaba! Bu bir TCPDF test belgesidir. ✓ Türkçe karakterler: ğüşiöç ĞÜŞİÖÇ", '', 0, 'L', true, 0, false, false, 0);
	$pdf->Ln(4);
	$pdf->SetFont('dejavusans', '', 10);
	$pdf->MultiCell(0, 0, "Sayfa oluşturma başarılıysa TCPDF kurulumunuz çalışıyor demektir.", 0, 'L');
	$pdf->Output('tcpdf_test.pdf', 'I');
	exit;
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<title>TCPDF Tanılama</title>
	<style>
		body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:#f8fafc; color:#0f172a; margin:0; }
		.container { max-width: 900px; margin: 40px auto; background:#fff; border-radius: 12px; box-shadow: 0 10px 24px rgba(0,0,0,.06); overflow:hidden; }
		.header { background: linear-gradient(135deg, #089473, #067a5f); color:#fff; padding:16px 20px; }
		.content { padding: 20px; }
		pre { background:#0b1220; color:#e2e8f0; padding:12px; border-radius:8px; overflow:auto; }
		.btn { display:inline-block; padding:10px 16px; border-radius:10px; text-decoration:none; color:#fff; background:#089473; }
		.btn:Hover { opacity:.9; }
		.kv { display:grid; grid-template-columns: 220px 1fr; gap:8px; margin:10px 0; }
		.kv b { color:#334155; }
	</style>
</head>
<body>
	<div class="container">
		<div class="header"><h1>TCPDF Tanılama</h1></div>
		<div class="content">
			<div class="kv"><b>PHP Sürümü</b><div><?= htmlspecialchars($diag['php_version']) ?></div></div>
			<div class="kv"><b>mbstring</b><div><?= $diag['extensions']['mbstring'] ? 'Yüklü' : 'Eksik' ?></div></div>
			<div class="kv"><b>gd</b><div><?= $diag['extensions']['gd'] ? 'Yüklü' : 'Eksik' ?></div></div>
			<hr />
			<div class="kv"><b>TCPDF Yol</b><div><?= htmlspecialchars($diag['tcpdf']['path'] ?? 'Bulunamadı') ?></div></div>
			<div class="kv"><b>Yüklendi</b><div><?= $diag['tcpdf']['loaded'] ? 'Evet' : 'Hayır' ?></div></div>
			<div class="kv"><b>Sınıf (önce)</b><div><?= $diag['tcpdf']['class_exists_before'] ? 'Var' : 'Yok' ?></div></div>
			<div class="kv"><b>Sınıf (sonra)</b><div><?= $diag['tcpdf']['class_exists_after'] ? 'Var' : 'Yok' ?></div></div>
			<div class="kv"><b>PDF sabitleri</b><div><?= $diag['tcpdf']['constants_available'] ? 'Var' : 'Yok' ?></div></div>
			<?php if ($diag['tcpdf']['load_error']): ?>
				<div style="color:#b91c1c; margin:10px 0;">
					<b>Yükleme Hatası:</b> <?= htmlspecialchars($diag['tcpdf']['load_error']) ?>
				</div>
			<?php endif; ?>
			<hr />
			<?php if ($diag['tcpdf']['loaded'] && class_exists('TCPDF')): ?>
				<a class="btn" href="?action=sample" target="_blank">Örnek PDF Oluştur</a>
			<?php else: ?>
				<p>TCPDF bulunamadı. Lütfen şu konumlardan birine kurun ve sunucuda erişilebilir olduğundan emin olun:</p>
				<pre>TCPDF-main/tcpdf.php
vendor/tecnickcom/tcpdf/tcpdf.php
vendor/autoload.php (composer)</pre>
			<?php endif; ?>
			<hr />
			<h3>Ham JSON</h3>
			<pre><?= htmlspecialchars(json_encode($diag, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
		</div>
	</div>
</body>
</html>
