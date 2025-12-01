<?php
session_start();
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğrenci kontrolü
if (!$auth->hasRole('student')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

$errorMessage = '';
$examData = null;

// Sınav kodu kontrolü
if ($_POST && isset($_POST['exam_code'])) {
    $examCode = strtoupper(trim($_POST['exam_code']));
    
    if (empty($examCode)) {
        $errorMessage = 'Lütfen sınav kodunu girin.';
    } else {
        // Sınavları yükle
        $exams = [];
        if (file_exists('../data/exams.json')) {
            $exams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
        }
        
        if (isset($exams[$examCode])) {
            $exam = $exams[$examCode];
            
            // Sınav aktif mi kontrol et
            if (($exam['status'] ?? '') !== 'active') {
                $errorMessage = 'Bu sınav artık aktif değil.';
            } else {
                // Sınav süresi kontrolü
                $startTime = strtotime($exam['start_date'] ?? $exam['scheduled_start'] ?? '');
                $duration = (int)($exam['duration'] ?? 30); // dakika
                $endTime = $startTime + ($duration * 60); // saniye
                $currentTime = time();
                
                if ($currentTime > $endTime) {
                    $errorMessage = 'Bu sınavın süresi dolmuş. Sınav ' . date('d.m.Y H:i', $endTime) . ' tarihinde sona ermiş.';
                } else {
                    // Önceden bu sınavı tamamlamış mı?
                    $resultsFile = '../data/exam_results.json';
                    $studentId = $user['username'] ?? $user['name'] ?? 'unknown';
                    if (file_exists($resultsFile)) {
                        $allResults = json_decode(file_get_contents($resultsFile), true) ?? [];
                        $examResults = $allResults[$examCode] ?? [];
                        foreach ($examResults as $res) {
                            if (($res['student_id'] ?? '') === $studentId) {
                                header('Location: view_result.php?exam_code=' . urlencode($examCode));
                                exit;
                            }
                        }
                    }
                    
                    // Öğrencinin kurumu kontrol et (normalize ederek eşleştir)
                $studentInstitution = $user['institution'] ?? $user['branch'] ?? 'IQRA Innsbruck';
                $studentClass = $user['class_section'] ?? $studentInstitution;
                $examSection = $exam['class_section'] ?? '';
                $examInstitution = $exam['teacher_institution'] ?? $exam['institution'] ?? '';

                $norm = function($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); };
                $si = $norm($studentInstitution);
                $sc = $norm($studentClass);
                $es = $norm($examSection);
                $ei = $norm($examInstitution);

                $canJoin = ($es !== '' && ($es === $si || $es === $sc)) ||
                           ($ei !== '' && ($ei === $si || $ei === $sc));
                
                if (!$canJoin) {
                    $errorMessage = 'Bu sınav sizin kurumunuz için değil. Sadece ' . $examSection . ' kurumu bu sınava girebilir.';
                } else {
                    // Sınav verilerini session'a kaydet
                    $_SESSION['current_exam'] = $exam;
                    $_SESSION['exam_code'] = $examCode;
                    $_SESSION['exam_start_time'] = time();
                    
                    // Sınav sayfasına yönlendir
                    header('Location: exam_take.php');
                    exit;
                }
                }
            }
        } else {
            $errorMessage = 'Geçersiz sınav kodu.';
        }
    }
}

// Öğrencinin kurumundaki aktif sınavları getir
$studentInstitution = $user['institution'] ?? $user['branch'] ?? 'IQRA Innsbruck';
$studentClass = $user['class_section'] ?? $studentInstitution;
$norm = function($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); };
$si = $norm($studentInstitution);
$sc = $norm($studentClass);
$activeExams = [];

if (file_exists('../data/exams.json')) {
    $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
    
    foreach ($allExams as $examCode => $exam) {
        $examClassSection = $exam['class_section'] ?? '';
        $examInstitution = $exam['teacher_institution'] ?? $exam['institution'] ?? '';

        // Sadece aktif sınavları ve öğrencinin kurumundaki sınavları al (normalize ederek)
        $isActive = strtolower((string)($exam['status'] ?? '')) === 'active';
        $es = $norm($examClassSection);
        $ei = $norm($examInstitution);
        $isForStudentInstitution = ($es !== '' && ($es === $si || $es === $sc)) ||
                                   ($ei !== '' && ($ei === $si || $ei === $sc));
        
        if ($isActive && $isForStudentInstitution) {
            $activeExams[$examCode] = $exam;
        }
    }
}

// AJAX: Aktif sınavlar için hafif durum döndür (sayfayı yeniden parse etmeyelim)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'count' => count($activeExams),
        'codes' => array_keys($activeExams),
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Giriş - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --primary-color: #068567;
            --primary-dark: #055a4a;
            --primary-light: #077a5f;
            --secondary-color: #f8f9fa;
            --accent-color: #ff6b35;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 8px 32px rgba(8, 148, 115, 0.3);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo img {
            height: 50px;
            width: auto;
        }
        
        .logo h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .logo p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9em;
        }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .back-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .page-title {
            font-size: 3em;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            font-weight: 300;
        }
        
        .page-subtitle {
            font-size: 1.2em;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
        }
        
        .exam-join-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 45px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.2em;
        }
        
        .form-group input {
            width: 100%;
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            font-size: 1.3em;
            background: white;
            transition: all 0.3s ease;
            text-align: center;
            letter-spacing: 2px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(8, 148, 115, 0.1);
        }
        
        .join-button {
            width: 100%;
            padding: 25px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 1.4em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 25px;
            box-shadow: 0 8px 25px rgba(8, 148, 115, 0.3);
        }
        
        .join-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(8, 148, 115, 0.4);
        }
        
        .join-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .error-message {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }

        /* Aktif Sınavlar Bölümü */
        .active-exams-section {
            background: white;
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .active-exams-section h2 {
            color: #2c3e50;
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.8em;
        }

        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .exam-card {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .exam-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(8, 148, 115, 0.2);
            transform: translateY(-2px);
        }

        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .exam-header h3 {
            color: #2c3e50;
            font-size: 1.3em;
            margin: 0;
        }

        .exam-code {
            background: var(--primary-color);
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .exam-details p {
            margin: 8px 0;
            color: #555;
            font-size: 0.95em;
        }

        .exam-actions {
            margin-top: 20px;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }

        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(8, 148, 115, 0.4);
        }

        .no-exams-message {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin-bottom: 30px;
        }

        .no-exams-message h3 {
            color: #6c757d;
            margin-bottom: 15px;
        }

        .no-exams-message p {
            color: #6c757d;
            margin: 10px 0;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .info-box h4 {
            margin-bottom: 10px;
            color: #0c5460;
        }
        
        .info-box ul {
            margin-left: 20px;
        }
        
        .info-box li {
            margin-bottom: 5px;
        }
        
        @media (max-width: 768px) {
            .header { padding: 14px 0; }
            .header-content { padding: 0 12px; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display: none; }
            .page-title { font-size: 2.2em; }
            .exam-join-card { padding: 30px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p id="pageTitle">Sınav Giriş</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: white;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: rgba(255, 255, 255, 0.8);" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.15);">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBack">← Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="mainTitle">🎯 Sınav Giriş</h1>
            <p class="page-subtitle" id="mainSubtitle">Eğitmeninizden aldığınız sınav kodunu girin</p>
        </div>

        <!-- Sınav Kodu ile Giriş (Üste taşındı) -->
        <div class="exam-join-card">
            <form method="POST">
                <div class="form-group">
                    <label for="exam_code" id="labelExamCode">🔑 Sınav Kodu</label>
                    <input type="text" id="exam_code" name="exam_code" required 
                           placeholder="Örn: A1B2C3D4" maxlength="8" autofocus
                           value="<?php echo isset($_POST['exam_code']) ? htmlspecialchars($_POST['exam_code']) : ''; ?>">
                </div>
                
                <button type="submit" class="join-button" id="btnJoin">
                    🚀 Sınava Gir
                </button>
            </form>
        </div>

        <!-- Aktif Sınavlar Bölümü -->
        <?php if (!empty($activeExams)): ?>
            <div class="active-exams-section">
                <h2 id="examsTitle">📝 Sınavlarım</h2>
                <div class="exams-grid">
                    <?php foreach ($activeExams as $examCode => $exam): ?>
                        <div class="exam-card">
                            <div class="exam-header">
                                <h3><?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?></h3>
                                <span class="exam-code"><?php echo htmlspecialchars($examCode); ?></span>
                            </div>
                            <div class="exam-details">
                                <p><strong id="descLabel">📝 Açıklama:</strong> <?php echo htmlspecialchars($exam['description'] ?? 'Açıklama yok'); ?></p>
                                <p><strong id="durationLabel">⏱️ Süre:</strong> <?php echo htmlspecialchars($exam['duration'] ?? 'Belirtilmemiş'); ?> <span class="durationUnit">dakika</span></p>
                                <p><strong id="questionsLabel">📊 Soru Sayısı:</strong> <?php echo count($exam['questions'] ?? []); ?> <span class="questionsUnit">soru</span></p>
                                <p><strong id="branchLabel">🏫 Şube:</strong> <?php echo htmlspecialchars($exam['class_section'] ?? 'Belirtilmemiş'); ?></p>
                            </div>
                            <div class="exam-actions">
                                <button class="btn btn-primary" onclick="fillExamCode('<?php echo htmlspecialchars($examCode); ?>')" id="btnJoinExam">
                                    🚀 Bu Sınava Gir
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="no-exams-message">
                <h3 id="noExamsTitle">📝 Sınavlarım</h3>
                <p id="noExamsText1">Şu anda kurumunuzda aktif bir sınav bulunmuyor.</p>
                <p id="noExamsText2">Eğitmeninizden sınav kodu alarak manuel olarak girebilirsiniz.</p>
                <p><small id="noExamsText3">💡 Yeni sınavlar otomatik olarak burada görünecektir.</small></p>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h4 id="infoTitle">ℹ️ Sınav Hakkında Bilgiler</h4>
            <ul>
                <li id="info1">Sınav kodunu eğitmeninizden alın veya yukarıdaki listeden seçin</li>
                <li id="info2">Sadece kendi kurumunuzun sınavlarına girebilirsiniz</li>
                <li id="info3">Sınav süresi dolduğunda otomatik olarak sonlanır</li>
                <li id="info4">Sınav sırasında sayfayı kapatmayın</li>
                <li id="info5">🔄 Yeni sınavlar otomatik olarak burada görünür</li>
            </ul>
        </div>

        <?php if ($errorMessage): ?>
            <div class="error-message">
                <h3 id="errorTitle">❌ Hata</h3>
                <p><?php echo htmlspecialchars($errorMessage); ?></p>
            </div>
        <?php endif; ?>

        
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Sınav Giriş', userRole:'Öğrenci', back:'← Geri Dön',
                mainTitle:'🎯 Sınav Giriş', mainSubtitle:'Eğitmeninizden aldığınız sınav kodunu girin',
                labelExamCode:'🔑 Sınav Kodu', btnJoin:'🚀 Sınava Gir',
                examsTitle:'📝 Sınavlarım', descLabel:'📝 Açıklama:', durationLabel:'⏱️ Süre:',
                questionsLabel:'📊 Soru Sayısı:', branchLabel:'🏫 Şube:',
                btnJoinExam:'🚀 Bu Sınava Gir', durationUnit:'dakika', questionsUnit:'soru',
                noExamsTitle:'📝 Sınavlarım', noExamsText1:'Şu anda kurumunuzda aktif bir sınav bulunmuyor.',
                noExamsText2:'Eğitmeninizden sınav kodu alarak manuel olarak girebilirsiniz.',
                noExamsText3:'💡 Yeni sınavlar otomatik olarak burada görünecektir.',
                infoTitle:'ℹ️ Sınav Hakkında Bilgiler', info1:'Sınav kodunu eğitmeninizden alın veya yukarıdaki listeden seçin',
                info2:'Sadece kendi kurumunuzun sınavlarına girebilirsiniz', info3:'Sınav süresi dolduğunda otomatik olarak sonlanır',
                info4:'Sınav sırasında sayfayı kapatmayın', info5:'🔄 Yeni sınavlar otomatik olarak burada görünür',
                errorTitle:'❌ Hata', newExamNotification:'🎉 Yeni sınav açıldı! Sayfa yenileniyor...'
            };
            const de = {
                pageTitle:'Prüfungseingang', userRole:'Schüler', back:'← Zurück',
                mainTitle:'🎯 Prüfungseingang', mainSubtitle:'Geben Sie den Prüfungscode ein, den Sie von Ihrem Lehrer erhalten haben',
                labelExamCode:'🔑 Prüfungscode', btnJoin:'🚀 Zur Prüfung',
                examsTitle:'📝 Meine Prüfungen', descLabel:'📝 Beschreibung:', durationLabel:'⏱️ Dauer:',
                questionsLabel:'📊 Anzahl Fragen:', branchLabel:'🏫 Zweig:',
                btnJoinExam:'🚀 Zu dieser Prüfung', durationUnit:'Minuten', questionsUnit:'Fragen',
                noExamsTitle:'📝 Meine Prüfungen', noExamsText1:'Derzeit gibt es keine aktiven Prüfungen in Ihrer Institution.',
                noExamsText2:'Sie können manuell eintreten, indem Sie einen Prüfungscode von Ihrem Lehrer erhalten.',
                noExamsText3:'💡 Neue Prüfungen werden automatisch hier angezeigt.',
                infoTitle:'ℹ️ Informationen zur Prüfung', info1:'Holen Sie sich den Prüfungscode von Ihrem Lehrer oder wählen Sie aus der obigen Liste',
                info2:'Sie können nur an Prüfungen Ihrer eigenen Institution teilnehmen', info3:'Die Prüfung endet automatisch, wenn die Zeit abgelaufen ist',
                info4:'Schließen Sie die Seite während der Prüfung nicht', info5:'🔄 Neue Prüfungen werden automatisch hier angezeigt',
                errorTitle:'❌ Fehler', newExamNotification:'🎉 Neue Prüfung eröffnet! Seite wird aktualisiert...'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){ 
                const d=lang==='de'?de:tr; 
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnBack', d.back);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#labelExamCode', d.labelExamCode);
                setText('#btnJoin', d.btnJoin);
                setText('#examsTitle', d.examsTitle);
                setText('#descLabel', d.descLabel);
                setText('#durationLabel', d.durationLabel);
                setText('#questionsLabel', d.questionsLabel);
                setText('#branchLabel', d.branchLabel);
                setText('#btnJoinExam', d.btnJoinExam);
                setText('.durationUnit', d.durationUnit);
                setText('.questionsUnit', d.questionsUnit);
                setText('#noExamsTitle', d.noExamsTitle);
                setText('#noExamsText1', d.noExamsText1);
                setText('#noExamsText2', d.noExamsText2);
                setText('#noExamsText3', d.noExamsText3);
                setText('#infoTitle', d.infoTitle);
                setText('#info1', d.info1);
                setText('#info2', d.info2);
                setText('#info3', d.info3);
                setText('#info4', d.info4);
                setText('#info5', d.info5);
                setText('#errorTitle', d.errorTitle);
                
                const toggle=document.getElementById('langToggle'); 
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE'); 
                localStorage.setItem('lang_exam_join', lang); 
            }
            
            document.addEventListener('DOMContentLoaded', function(){ 
                const lang=localStorage.getItem('lang_exam_join')||localStorage.getItem('lang')||'tr'; 
                apply(lang); 
                const toggle=document.getElementById('langToggle'); 
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_exam_join')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                } 
            });
        })();

        // Sınav kodu input'unu büyük harfe çevir
        document.getElementById('exam_code').addEventListener('input', function(e) {
            e.target.value = e.target.value.toUpperCase();
        });
        
        // Enter tuşu ile form gönder
        document.getElementById('exam_code').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.querySelector('form').submit();
            }
        });

        // Sınav kartındaki butona tıklayınca sınav kodunu doldur
        function fillExamCode(examCode) {
            document.getElementById('exam_code').value = examCode;
            // Formu otomatik gönder
            document.querySelector('form').submit();
        }

        // Arka planda otomatik yenileme
        let autoReloadInterval;
        let isPageVisible = true;
        let lastExamCount = <?php echo count($activeExams); ?>;
        let lastExamCodes = <?php echo json_encode(array_keys($activeExams)); ?>;

        // Sayfa görünürlük kontrolü
        document.addEventListener('visibilitychange', function() {
            isPageVisible = !document.hidden;
            if (isPageVisible) {
                startAutoReload();
            } else {
                stopAutoReload();
            }
        });

        // Otomatik yenileme başlat
        function startAutoReload() {
            if (autoReloadInterval) {
                clearInterval(autoReloadInterval);
            }
            
            autoReloadInterval = setInterval(function() {
                if (isPageVisible) {
                    checkForNewExams();
                }
            }, 10000); // 10 saniyede bir kontrol et
        }

        // Otomatik yenileme durdur
        function stopAutoReload() {
            if (autoReloadInterval) {
                clearInterval(autoReloadInterval);
                autoReloadInterval = null;
            }
        }

        // Yeni sınavları kontrol et (hafif AJAX)
        function checkForNewExams() {
            var url = window.location.pathname + '?ajax=1';
            fetch(url, { cache: 'no-store' })
                .then(function(res){ return res.json(); })
                .then(function(data){
                    if (!data || typeof data.count === 'undefined') return;
                    var currentCount = data.count;
                    var currentCodes = Array.isArray(data.codes) ? data.codes : [];
                    var changed = (currentCount !== lastExamCount) || (JSON.stringify(currentCodes) !== JSON.stringify(lastExamCodes));
                    if (changed) {
                        showNewExamNotification();
                        setTimeout(function(){ window.location.reload(); }, 1200);
                    }
                })
                .catch(function(){ /* sessiz geç */ });
        }

        // Yeni sınav bildirimi
        function showNewExamNotification() {
            // Mevcut bildirimi kaldır
            const existingNotification = document.querySelector('.new-exam-notification');
            if (existingNotification) {
                existingNotification.remove();
            }

            // Yeni bildirim oluştur
            const notification = document.createElement('div');
            notification.className = 'new-exam-notification';
            const lang = localStorage.getItem('lang_exam_join')||localStorage.getItem('lang')||'tr';
            const notificationText = lang === 'de' ? '🎉 Neue Prüfung eröffnet! Seite wird aktualisiert...' : '🎉 Yeni sınav açıldı! Sayfa yenileniyor...';
            notification.innerHTML = `
                <div style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: linear-gradient(135deg, #089473 0%, #067a5f 100%);
                    color: white;
                    padding: 15px 25px;
                    border-radius: 10px;
                    box-shadow: 0 8px 25px rgba(8, 148, 115, 0.3);
                    z-index: 1000;
                    font-weight: 600;
                    animation: slideInRight 0.5s ease-out;
                ">
                    ${notificationText}
                </div>
                <style>
                    @keyframes slideInRight {
                        from { transform: translateX(100%); opacity: 0; }
                        to { transform: translateX(0); opacity: 1; }
                    }
                </style>
            `;
            
            document.body.appendChild(notification);
            
            // 3 saniye sonra bildirimi kaldır
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 3000);
        }

        // Sayfa yüklendiğinde otomatik yenilemeyi başlat
        document.addEventListener('DOMContentLoaded', function() {
            startAutoReload();
            // İlk kontrol (hemen)
            checkForNewExams();
        });

        // Sayfa kapatılırken otomatik yenilemeyi durdur
        window.addEventListener('beforeunload', function() {
            stopAutoReload();
        });
    </script>
</body>
</html>
