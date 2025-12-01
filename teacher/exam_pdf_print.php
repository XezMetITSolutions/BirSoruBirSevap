<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('teacher')) {
    header('Location: ../login.php');
    exit;
}
$user = $auth->getUser();

// Form verileri
$examTitle = $_POST['exam_title'] ?? 'Sınav';
$questionCount = (int)($_POST['question_count'] ?? 10);
$selectedCategories = $_POST['categories'] ?? [];
$examDescription = $_POST['exam_description'] ?? '';
$questionType = $_POST['question_type'] ?? 'random';
$selectedQuestions = [];

// Çıktıyı dosyaya da kaydetmek için buffer'ı başlat
ob_start();

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

if ($questionType === 'custom') {
    $customQuestions = $_POST['custom_questions'] ?? [];
    foreach ($customQuestions as $customQuestion) {
        if (!empty($customQuestion['question'])) {
            $selectedQuestions[] = [
                'id' => 'custom_' . uniqid(),
                'question' => $customQuestion['question'],
                'type' => $customQuestion['type'] ?? 'mcq',
                'options' => $customQuestion['options'] ?? [],
                'correct_answer' => $customQuestion['correct_answer'] ?? '',
                'explanation' => $customQuestion['explanation'] ?? '',
                'difficulty' => 'Orta',
                'bank' => 'Özel',
                'category' => 'Özel Sorular'
            ];
        }
    }
} else {
    $filteredQuestions = [];
    if ($questionType === 'manual') {
        $selectedQuestionIds = $_POST['selected_questions'] ?? [];
        $allQuestions = $_SESSION['all_questions'] ?? [];
        foreach ($allQuestions as $question) {
            if (in_array($question['id'], $selectedQuestionIds)) {
                $filteredQuestions[] = $question;
            }
        }
    } else {
        foreach ($selectedCategories as $categoryData) {
            $parts = explode('|', $categoryData);
            $bank = $parts[0] ?? '';
            $category = $parts[1] ?? '';
            $categoryQuestions = $questionLoader->getFilteredQuestions([
                'bank' => $bank,
                'category' => $category,
                'count' => 999
            ]);
            $filteredQuestions = array_merge($filteredQuestions, $categoryQuestions);
        }
        shuffle($filteredQuestions);
    }
    $selectedQuestions = array_slice($filteredQuestions, 0, $questionCount);
}

?><!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> </title>
    <style>
        /* Sayfa marjinleri: Kopfzeile/Fußzeile'ye yer bırak */
        @page { size: A4; margin: 20mm 18mm 20mm 18mm; }
        * { box-sizing: border-box; }
        html, body { height: 100%; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #111827; }

        /* Ekranda üst bilgi */
        .header { display:flex; align-items:center; justify-content:space-between; margin-bottom:14px; }
        .brand { font-weight:800; font-size:18px; color:#065f46; }
        .muted { color:#6b7280; font-size:12px; display:none; }
        .title { text-align:center; font-size:22px; font-weight:800; margin:10px 0 6px; }
        .hr { height:2px; background:#e5e7eb; margin:10px 0 14px; }
        .meta { display:flex; gap:16px; flex-wrap:wrap; font-size:12px; margin-bottom:10px; }
        .meta > div { background:#f3f4f6; padding:6px 10px; border-radius:8px; }
        .student { border:1px solid #e5e7eb; padding:10px; border-radius:8px; margin:10px 0 16px; }
        .student h3 { margin:0 0 8px; font-size:14px; }
        .row { display:flex; justify-content:space-between; gap:12px; margin:6px 0; }
        .cell { flex:1; }
        .line { border-bottom:1px dashed #9ca3af; height:20px; }
        .section { font-weight:800; margin:14px 0 10px; }

        /* Soru blokları: sayfa içinde bölünmeyi engelle */
        .q { margin:12px 0; break-inside: avoid; page-break-inside: avoid; }
        .q .q-head { font-weight:700; margin-bottom:6px; break-after: avoid; }
        .opts { margin-left:14px; break-inside: avoid; page-break-inside: avoid; }
        .opt { margin:4px 0; display:flex; gap:8px; break-inside: avoid; page-break-inside: avoid; }

        /* Yazdırılacak üst bilgi (logo + detaylar) */
        .print-header { display:flex; align-items:center; gap:12px; margin-bottom:12px; }
        .print-header img { height: 42px; width: auto; }
        .ph-text { line-height:1.35; }
        .ph-title { font-weight:800; }
        .ph-sub { font-size:12px; color:#374151; }

        /* Yazdır düğmesi */
        .print-btn { display:none; }

        @media print {
            /* Ekrandaki başlık/meta bloklarını gizle, yazdırma Kopfzeile/Fußzeile göster */
            .header, .title, .hr { display: none !important; }
            .print-btn { display:none; }
            .print-header { display:flex; }

            /* Genel kırılma iyileştirmeleri */
            h1, h2, h3, .section { break-after: avoid; page-break-after: avoid; }

            /* Bağlantıların yanına otomatik URL eklenmesini engelle (bazı tarayıcılar) */
            a[href]:after { content: none !important; }
        }
    </style>
</head>
<body>
    
    <!-- Yazdırılacak özel üst bilgi (logo + sınav/şube/eğitmen) -->
    <?php 
        $teacherSection = $user['institution'] ?? ($user['branch'] ?? ($user['class_section'] ?? ''));
        // Logoyu base64 embed et: geçmiş kayıtlar teacher/prints altından açılınca da görünür olsun
        $logoData = '';
        $logoCandidates = [__DIR__ . '/../logo.png', __DIR__ . '/../../logo.png'];
        foreach ($logoCandidates as $lp) {
            if (file_exists($lp)) { $logoData = 'data:image/png;base64,' . base64_encode(@file_get_contents($lp)); break; }
        }
    ?>
    <div class="print-header">
        <img src="<?php echo $logoData ?: '../logo.png'; ?>" alt="Logo">
        <div class="ph-text">
            <div class="ph-title"><?php echo htmlspecialchars($examTitle); ?></div>
            <div class="ph-sub">Eğitmen: <?php echo htmlspecialchars($user['name'] ?? ''); ?></div>
        </div>
    </div>

    <!-- Ekranda gösterilen başlık -->
    <div class="header">
        <div class="brand">Bir Soru Bir Sevap</div>
        <div class="muted"></div>
    </div>
    <div class="title"><?php echo htmlspecialchars($examTitle); ?></div>
    <div class="hr"></div>
    <div class="meta">
        <div>🔢 Soru: <?php echo count($selectedQuestions); ?></div>
        <?php if (!empty($examDescription)): ?><div>📄 Açıklama: <?php echo htmlspecialchars($examDescription); ?></div><?php endif; ?>
    </div>

    <div class="student">
        <h3>Öğrenci Bilgileri</h3>
        <div class="row">
            <div class="cell">Ad Soyad: <div class="line"></div></div>
            <div class="cell">Sınıf: <div class="line"></div></div>
        </div>
        <div class="row">
            <div class="cell">Tarih: <div class="line"></div></div>
        </div>
    </div>

    <div class="section">Sorular</div>
    <?php foreach ($selectedQuestions as $i => $q): ?>
        <div class="q">
            <div class="q-head"><?php echo ($i+1) . '. ' . htmlspecialchars($q['question']); ?></div>
            <?php if (!empty($q['options']) && is_array($q['options'])): ?>
                <div class="opts">
                    <?php foreach ($q['options'] as $idx => $opt): $label = chr(65 + $idx) . ')'; ?>
                        <div class="opt"><strong><?php echo $label; ?></strong> <span><?php echo htmlspecialchars(is_array($opt) && isset($opt['text']) ? $opt['text'] : (string)$opt); ?></span></div>
                    <?php endforeach; ?>
                </div>
            <?php elseif (($q['type'] ?? '') === 'true_false'): ?>
                <div class="opts">
                    <div class="opt"><strong>A)</strong> Doğru</div>
                    <div class="opt"><strong>B)</strong> Yanlış</div>
                </div>
            <?php elseif (($q['type'] ?? '') === 'short_answer'): ?>
                <div class="opts" style="margin-top:8px;">
                    <div class="line" style="height:26px;"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>

    
    <script>
        // Sayfa yüklenir yüklenmez belge başlığını boş yap ve yazdırma penceresini aç
        window.addEventListener('load', function(){
            try { document.title = ' '; } catch(e){}
            setTimeout(function(){ window.print(); }, 50);
        });

        // Yazdırma bittikten sonra dashboard'a dön
        function goBackAfterPrint(){
            setTimeout(function(){
                var path = window.location.pathname || '';
                // Eski kayıt (teacher/prints/*) ise yukarı çıkıp PDF sayfasına dön
                if (path.indexOf('/teacher/prints/') !== -1) {
                    window.location.href = '../exam_pdf.php';
                } else {
                    window.location.href = 'exam_pdf.php';
                }
            }, 100);
        }
        if (window.matchMedia) {
            const mediaQueryList = window.matchMedia('print');
            mediaQueryList.addEventListener ?
                mediaQueryList.addEventListener('change', function(e){ if (!e.matches) goBackAfterPrint(); }) :
                mediaQueryList.addListener(function(e){ if (!e.matches) goBackAfterPrint(); });
        }
        window.addEventListener('afterprint', goBackAfterPrint);
    </script>
</body>
</html>

<?php
// --- Kaydetme: HTML snapshot ve meta ---
$html = ob_get_contents();
$printsDir = __DIR__ . '/prints';
if (!is_dir($printsDir)) { @mkdir($printsDir, 0775, true); }
// Güvenli dosya adı
$safeTitle = preg_replace('/[^a-zA-Z0-9-_]+/','_', mb_substr($examTitle, 0, 60));
$fileName = 'sinav_' . date('Ymd_His') . '_' . ($safeTitle ?: 'sinav') . '.html';
$filePath = $printsDir . '/' . $fileName;
@file_put_contents($filePath, $html);

// Meta kaydı
$metaPath = __DIR__ . '/../data/exam_prints.json';
$metaAll = file_exists($metaPath) ? (json_decode(file_get_contents($metaPath), true) ?: []) : [];
$metaAll[] = [
    'title' => $examTitle,
    'teacher' => $user['name'] ?? '',
    'created_at' => date('c'),
    'file' => 'teacher/prints/' . $fileName,
    'questions' => count($selectedQuestions)
];
@file_put_contents($metaPath, json_encode($metaAll, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

ob_end_flush();
?>


