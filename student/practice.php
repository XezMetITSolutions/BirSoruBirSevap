<?php
/**
 * Öğrenci Alıştırma Sayfası
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Öğrenci kontrolü
if (!$auth->hasRole('student')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];

// Filtreler
$selectedBank = $_GET['bank'] ?? '';
$selectedCategory = $_GET['category'] ?? '';
$selectedDifficulty = $_GET['difficulty'] ?? '';
$questionCount = (int)($_GET['count'] ?? 10);
$timer = (int)($_GET['timer'] ?? 0);
$shuffle = (bool)($_GET['shuffle'] ?? true);
$showCorrectAnswer = (bool)($_GET['show_correct'] ?? false);

// Filtrelenmiş sorular
$filteredQuestions = $questionLoader->getFilteredQuestions([
    'bank' => $selectedBank,
    'category' => $selectedCategory,
    'difficulty' => $selectedDifficulty
]);

// Eğer kategori seçildiyse ve birleştirilmiş kategori ise, tüm alt kategorileri dahil et
if (!empty($selectedCategory) && !empty($selectedBank)) {
    $allBankQuestions = $questionLoader->getFilteredQuestions([
        'bank' => $selectedBank,
        'difficulty' => $selectedDifficulty
    ]);
    
    // Seçilen kategoriye benzer tüm kategorileri bul
    $matchingQuestions = [];
    foreach ($allBankQuestions as $question) {
        $questionCategory = $question['category'];
        
        // Kategori ismini temizle (aynı mantık)
        $cleanQuestionCategory = preg_replace('/_json\.json$|\.json$|_questions\.json$|_sorulari\.json$/', '', $questionCategory);
        $cleanQuestionCategory = preg_replace('/_(\d+)_(\d+)_json$/', '', $cleanQuestionCategory);
        $cleanQuestionCategory = preg_replace('/_(\d+)_(\d+)$/', '', $cleanQuestionCategory);
        $cleanQuestionCategory = preg_replace('/_(\d+)$/', '', $cleanQuestionCategory);
        $cleanQuestionCategory = str_replace('_', ' ', $cleanQuestionCategory);
        $cleanQuestionCategory = ucwords($cleanQuestionCategory);
        $cleanQuestionCategory = trim($cleanQuestionCategory);
        
        // Eğer temizlenmiş kategori eşleşiyorsa dahil et
        if ($cleanQuestionCategory === $selectedCategory) {
            $matchingQuestions[] = $question;
        }
    }
    
    if (!empty($matchingQuestions)) {
        $filteredQuestions = $matchingQuestions;
    }
}

// Soru sayısını sınırla
$selectedQuestions = array_slice($filteredQuestions, 0, min($questionCount, count($filteredQuestions)));

// Karıştır
if ($shuffle) {
    shuffle($selectedQuestions);
}

// Session'a kaydet
$_SESSION['practice_questions'] = $selectedQuestions;
$_SESSION['practice_settings'] = [
    'bank' => $selectedBank,
    'category' => $selectedCategory,
    'difficulty' => $selectedDifficulty,
    'timer' => $timer,
    'shuffle' => $shuffle,
    'show_correct_answer' => $showCorrectAnswer
];
$_SESSION['practice_answers'] = [];
$_SESSION['practice_start_time'] = time();
$_SESSION['practice_current_question'] = 0;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alıştırma - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
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

        /* Logo boyutunu kesin olarak sınırla (diğer kuralların üstüne çıksın) */
        .header .logo img {
            height: 48px !important;
            max-height: 48px !important;
            width: auto !important;
        }

        .logo h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
        }

        .logo p {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-5px);
        }

        .practice-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .practice-title {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .practice-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #3498db;
        }

        .info-label {
            font-size: 0.9em;
            color: #7f8c8d;
            margin-bottom: 5px;
        }

        .info-value {
            font-size: 1.2em;
            font-weight: 600;
            color: #2c3e50;
        }

        .timer {
            background: #e74c3c;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1em;
            display: inline-block;
            margin: 20px 0;
        }

        .question-card {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e1e8ed;
        }

        .question-number {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
        }

        .question-type {
            background: #95a5a6;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .question-text {
            font-size: 1.3em;
            line-height: 1.6;
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .option {
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .option:hover {
            border-color: #3498db;
            background: rgba(52, 152, 219, 0.05);
        }

        .option.selected {
            border-color: #3498db;
            background: rgba(52, 152, 219, 0.1);
        }

        .option.correct {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }

        .option.incorrect {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }

        .option input[type="radio"] {
            width: 20px;
            height: 20px;
            accent-color: #3498db;
        }

        .option input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #3498db;
        }

        .option-text {
            flex: 1;
            font-size: 1.1em;
        }

        /* Modern rozet harfleri */
        .option-letter {
            width: 36px;
            height: 36px;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #eef2f7;
            color: #0f172a;
            font-weight: 700;
            flex: 0 0 auto;
        }

        .option.selected .option-letter {
            background: #3498db;
            color: #fff;
        }

        /* Üst sabit ilerleme ve zaman şeridi */
        .top-bar {
            position: sticky;
            top: 10px;
            z-index: 5;
            background: #fff;
            border: 1px solid #e1e8ed;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.06);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }

        .progress-mini {
            flex: 1;
            height: 8px;
            background: #e1e8ed;
            border-radius: 999px;
            overflow: hidden;
        }

        .progress-mini > span {
            display: block;
            height: 100%;
            width: 0%;
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            transition: width 0.3s ease;
        }

        .short-answer {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1.1em;
            transition: border-color 0.3s ease;
        }

        .short-answer:focus {
            outline: none;
            border-color: #3498db;
        }

        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
            gap: 12px;
        }

        /* Alt yapışkan gezinme çubuğu (tablet/telefon) */
        @media (max-width: 1024px) {
            .navigation {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(255,255,255,0.98);
                backdrop-filter: blur(20px);
                border-top: 1px solid #e1e8ed;
                border-radius: 0;
                padding: 12px 16px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
                box-shadow: 0 -4px 20px rgba(0,0,0,.1);
                z-index: 1000;
                margin: 0;
            }
            .container {
                padding-bottom: calc(80px + env(safe-area-inset-bottom));
            }
        }

        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 14px 22px;
            border-radius: 14px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn:disabled {
            background: #95a5a6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .progress-bar {
            background: #e1e8ed;
            border-radius: 10px;
            height: 10px;
            margin: 20px 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .explanation {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            display: none;
        }

        .explanation.show {
            display: block;
        }

        .explanation h4 {
            color: #155724;
            margin-bottom: 10px;
        }

        .explanation p {
            color: #155724;
            line-height: 1.6;
        }

        @media (max-width: 768px) {
            html, body { width:100%; max-width:100%; overflow-x:hidden; }
            .header-content { flex-direction: column; gap: 12px; padding: 0 12px; }
            .container { padding: 16px 12px; padding-bottom: calc(90px + env(safe-area-inset-bottom)); }
            .practice-header { padding: 16px; border-radius: 12px; }
            .practice-title { font-size: 1.4em; }
            .practice-info { 
                grid-template-columns: repeat(3, 1fr); 
                gap: 8px; 
                margin-top: 12px;
            }
            .info-item { 
                padding: 8px; 
                text-align: center;
            }
            .info-label { 
                font-size: .75em; 
                margin-bottom: 3px;
            }
            .info-value { 
                font-size: .9em; 
                font-weight: 600;
            }
            .question-card { padding: 16px; border-radius: 12px; margin-bottom: 20px; }
            .question-header { flex-direction: column; gap: 12px; text-align: center; }
            .question-text { font-size: 1.05em; }
            .options { gap: 10px; margin-bottom: 20px; }
            .option { padding: 14px; min-height: 56px; }
            .option-letter { width: 32px; height: 32px; font-size: 1em; }
            .navigation { 
                flex-direction: row; 
                gap: 8px; 
                justify-content: space-between;
                padding: 12px 16px;
                padding-bottom: calc(12px + env(safe-area-inset-bottom));
            }
            .navigation > div { display: none; }
            .btn { 
                padding: 16px 20px; 
                border-radius: 12px; 
                flex: 1;
                font-size: 1em;
                min-height: 52px;
                font-weight: 600;
            }
            .top-bar { position: sticky; top: 6px; gap: 10px; padding: 8px 10px; }
            /* Gereksiz öğeleri gizle */
            .back-btn { display: none; }
            .question-type { display: none; }
            .practice-header { display: none; }
            .progress-bar { margin-bottom: 20px; }
        }

        /* Ultra kompakt düzen: küçük telefonlar (≤420px) için */
        @media (max-width: 420px) {
            .practice-title { font-size: 1.1em; margin-bottom: 8px; }
            #practiceDesc { font-size: .9em; }
            .practice-info { gap: 8px; }
            .info-item { padding: 10px; }
            .info-label { font-size: .8em; }
            .info-value { font-size: 1em; }
            .question-card { padding: 12px; border-radius: 10px; }
            .question-number { padding: 6px 10px; }
            .question-type { padding: 6px 10px; font-size: .8em; }
            .question-text { font-size: 1em; margin-bottom: 16px; }
            .option { padding: 12px; gap: 10px; min-height: 52px; }
            .option-letter { width: 28px; height: 28px; font-size: .95em; }
            .short-answer { font-size: 1em; padding: 12px; }
            .btn { padding: 14px 16px; font-size: .95em; border-radius: 12px; min-height: 48px; }
            .navigation { gap: 8px; padding: 10px 12px; padding-bottom: calc(10px + env(safe-area-inset-bottom)); }
            .progress-bar { height: 8px; }
            .top-bar { top: 4px; gap: 8px; padding: 6px 8px; }
            .timer { padding: 6px 10px; font-size: .95em; }
        }

        /* iPhone 14 Pro Max ve benzeri büyük telefonlar için (≤430px) */
        @media (max-width: 430px) {
            .header-content { padding: 0 8px; }
            .container { padding: 12px 8px; padding-bottom: calc(85px + env(safe-area-inset-bottom)); }
            .practice-header { padding: 12px; }
            .practice-title { font-size: 1.2em; }
            .question-card { padding: 12px; margin-bottom: 16px; }
            .question-text { font-size: .95em; line-height: 1.4; }
            .option { padding: 12px; min-height: 50px; }
            .option-text { font-size: .9em; }
            .btn { padding: 14px 16px; font-size: .9em; min-height: 48px; }
            .navigation { padding: 10px 12px; padding-bottom: calc(10px + env(safe-area-inset-bottom)); }
            .top-bar { padding: 4px 6px; }
            .timer { padding: 4px 8px; font-size: .9em; }
            .progress-mini { height: 6px; }
            /* Bilgi kartlarını tek satıra sığdır */
            .practice-info { 
                grid-template-columns: repeat(3, 1fr); 
                gap: 6px; 
                margin-top: 10px;
            }
            .info-item { 
                padding: 6px; 
                text-align: center;
            }
            .info-label { 
                font-size: .7em; 
                margin-bottom: 2px;
            }
            .info-value { 
                font-size: .8em; 
                font-weight: 600;
            }
            /* İpucu kutusunu gizle */
            #tipText { display: none; }
        }

        /* Ekstra dar ekranlar (≤360px): bazı ikincil metinleri gizle */
        @media (max-width: 360px) {
            #tipText { display:none; }
            .logo h1 { display:none; }
            .logo p { display:none; }
            .user-info > div { max-width: 60vw; }
        }
        /* Kopya önleme: metin seçimi ve çağrı menülerini engelle */
        html, body { 
            -webkit-user-select: none; 
            -ms-user-select: none; 
            user-select: none; 
            -webkit-touch-callout: none; 
            padding-left: env(safe-area-inset-left); 
            padding-right: env(safe-area-inset-right);
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* Hareket azaltma tercihi olanlar için animasyonları hafiflet */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Bir Soru Bir Sevap Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p id="pageTitle">Alıştırma Modu</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;" id="userRole">Öğrenci</div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnDashboard">← Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="../index.php" class="back-btn" id="btnBackHome">
            <i class="fas fa-arrow-left"></i>
            <span id="backHomeText">Ana Sayfaya Dön</span>
        </a>

        <div class="practice-header">
            <h2 class="practice-title" id="practiceTitle">🚀 Alıştırma Başladı!</h2>
            <p style="color: #7f8c8d; font-size: 1.1em;" id="practiceDesc">
                <?php echo count($selectedQuestions); ?> <span id="questionsText">soru ile alıştırma yapıyorsunuz</span>
            </p>
            
            <div class="top-bar">
                <div class="timer" id="timer" style="margin:0; box-shadow:none; position:static;">
                    <span id="timer-label"><?php echo $timer > 0 ? 'Kalan Süre:' : 'Geçen Süre:'; ?></span>
                    <span id="time-display"><?php echo $timer > 0 ? ($timer . ':00') : '00:00'; ?></span>
                </div>
                <div class="progress-mini"><span id="progress-mini-fill" style="width:0%"></span></div>
            </div>

            <div class="practice-info">
                <div class="info-item">
                    <div class="info-label" id="infoLabel1">📚 Soru Bankası</div>
                    <div class="info-value"><?php echo $selectedBank ?: 'Tümü'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label" id="infoLabel2">📖 Konu</div>
                    <div class="info-value"><?php echo $selectedCategory ?: 'Tümü'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label" id="infoLabel3">🔢 Toplam Soru</div>
                    <div class="info-value"><?php echo count($selectedQuestions); ?></div>
                </div>
            </div>
            
            <div style="margin-top: 20px; padding: 15px; background: #e8f5e8; border-radius: 10px; border-left: 4px solid #27ae60;">
                <p style="margin: 0; color: #2c3e50; font-size: 0.9em;" id="tipText">
                    💡 <strong id="tipLabel">İpucu:</strong> <span id="tipDesc">Soruları dikkatli okuyun ve doğru cevabı seçin. Alıştırma sonunda detaylı sonuçlar göreceksiniz.</span>
                </p>
            </div>
        </div>

        <?php if (empty($selectedQuestions)): ?>
            <div class="question-card">
                <div style="text-align: center; padding: 40px;">
                    <h3 style="color: #e74c3c; margin-bottom: 20px;" id="noQuestionsTitle">⚠️ Soru Bulunamadı</h3>
                    <p style="color: #7f8c8d; margin-bottom: 30px;" id="noQuestionsDesc">
                        Seçilen kriterlere uygun soru bulunamadı. Lütfen farklı filtreler deneyin.
                    </p>
                    <a href="dashboard.php" class="btn" id="btnBackToDashboard">Dashboard'a Dön</a>
                </div>
            </div>
        <?php else: ?>
            <div id="question-container">
                <!-- Sorular JavaScript ile yüklenecek -->
            </div>

            <div class="progress-bar">
                <div class="progress-fill" id="progress-fill" style="width: 0%"></div>
            </div>

            <div class="navigation">
                <button class="btn btn-secondary" id="prev-btn" onclick="previousQuestion()" disabled>
                    <span id="btnPrevious">← Önceki</span>
                </button>
                <div>
                    <span id="question-counter">1 / <?php echo count($selectedQuestions); ?></span>
                </div>
                <button class="btn" id="next-btn" onclick="nextQuestion()">
                    <span id="btnNext">Sonraki →</span>
                </button>
            </div>
        <?php endif; ?>
    </div>

    <script>
        const questions = <?php echo json_encode($selectedQuestions); ?>;
        const timer = <?php echo $timer; ?>;
        let currentQuestionIndex = 0;
        let answers = {};
        let startTime = Date.now();

        // Timer: her zaman görünür; geri sayım varsa kalan, yoksa kronometre
        let timerInterval;
        const timerDisplay = document.getElementById('time-display');
        const timerLabel = document.getElementById('timer-label');
        if (timer > 0) {
            let timeLeft = timer * 60;
            timerLabel.textContent = 'Kalan Süre:';
            timerInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    finishPractice();
                }
                timeLeft--;
            }, 1000);
        } else {
            // kronometre
            let elapsed = 0;
            timerLabel.textContent = 'Geçen Süre:';
            timerInterval = setInterval(() => {
                elapsed++;
                const minutes = Math.floor(elapsed / 60);
                const seconds = elapsed % 60;
                timerDisplay.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }, 1000);
        }

        function loadQuestion(index) {
            if (index < 0 || index >= questions.length) return;
            
            currentQuestionIndex = index;
            const question = questions[index];
            const container = document.getElementById('question-container');
            
            
            let html = `
                <div class="question-card">
                    <div class="question-header">
                        <div class="question-number">Soru ${index + 1}</div>
                        <div class="question-type">${getQuestionTypeText(question.type)}</div>
                    </div>
                    <div class="question-text">${question.text}</div>
                    <div class="options">
                        ${generateOptions(question)}
                    </div>
                    
                </div>
            `;
            
            container.innerHTML = html;
            
            // Mevcut cevabı yükle
            if (answers[index]) {
                loadAnswer(index, answers[index]);
            }
            
            updateProgress();
            updateNavigation();
        }

        function generateOptions(question) {
            if (question.type === 'short_answer') {
                return `
                    <input type="text" class="short-answer" id="answer-${currentQuestionIndex}" 
                           placeholder="Cevabınızı yazın..." 
                           value="${answers[currentQuestionIndex] || ''}"
                           onchange="saveAnswer(${currentQuestionIndex}, this.value)">
                `;
            }
            
            if (question.type === 'true_false') {
                return `
                    <div class="option" onclick="selectOption(${currentQuestionIndex}, 0, 'radio')">
                        <input type="radio" name="question-${currentQuestionIndex}" value="true" 
                               id="option-${currentQuestionIndex}-0">
                        <div class="option-text">Doğru</div>
                    </div>
                    <div class="option" onclick="selectOption(${currentQuestionIndex}, 1, 'radio')">
                        <input type="radio" name="question-${currentQuestionIndex}" value="false" 
                               id="option-${currentQuestionIndex}-1">
                        <div class="option-text">Yanlış</div>
                    </div>
                `;
            }
            
            // Çoktan seçmeli sorular (tek seçim)
            if (question.type === 'mcq' || question.type === 'multiple_choice') {
                let options = '';
                question.options.forEach((option, optionIndex) => {
                    // Seçenek metnini doğru şekilde al
                    let optionText = '';
                    if (typeof option === 'string') {
                        optionText = option;
                    } else if (typeof option === 'object' && option.text) {
                        optionText = option.text;
                    } else {
                        optionText = String(option);
                    }
                    
                    const letter = String.fromCharCode(65 + optionIndex);
                    options += `
                        <div class="option" onclick="selectOption(${currentQuestionIndex}, ${optionIndex}, 'radio')">
                            <input type="radio" name="question-${currentQuestionIndex}" value="${optionIndex}" 
                                   id="option-${currentQuestionIndex}-${optionIndex}">
                            <div class="option-letter">${letter}</div>
                            <div class="option-text">${optionText}</div>
                        </div>
                    `;
                });
                return options;
            }
            
            return '<p style="color: #e74c3c; text-align: center; padding: 20px;">Bu soru türü desteklenmiyor.</p>';
        }

        function getQuestionTypeText(type) {
            const types = {
                'mcq': 'Çoktan Seçmeli',
                'multiple_choice': 'Çoktan Seçmeli',
                'true_false': 'Doğru/Yanlış',
                'short_answer': 'Kısa Cevap'
            };
            return types[type] || 'Desteklenmeyen Soru Türü';
        }

        function selectOption(questionIndex, optionIndex, inputType) {
            const input = document.getElementById(`option-${questionIndex}-${optionIndex}`);
            const option = input.closest('.option');
            
            // Sadece radio button (doğru/yanlış) destekleniyor
            if (inputType === 'radio') {
                // Radio button - sadece bir seçenek
                document.querySelectorAll(`input[name="question-${questionIndex}"]`).forEach(inp => {
                    inp.closest('.option').classList.remove('selected');
                });
                input.checked = true;
                option.classList.add('selected');
            }
            
            saveAnswer(questionIndex);
        }

        function saveAnswer(questionIndex, value = null) {
            if (value !== null) {
                answers[questionIndex] = value;
                return;
            }
            
            const question = questions[questionIndex];
            if (question.type === 'short_answer') {
                answers[questionIndex] = document.getElementById(`answer-${questionIndex}`).value;
                return;
            }
            
            if (question.type === 'true_false') {
                const inputName = `question-${questionIndex}`;
                const inputs = document.querySelectorAll(`input[name="${inputName}"]:checked`);
                answers[questionIndex] = inputs.length > 0 ? inputs[0].value : null;
                return;
            }
            
            if (question.type === 'mcq' || question.type === 'multiple_choice') {
                const inputName = `question-${questionIndex}`;
                const inputs = document.querySelectorAll(`input[name="${inputName}"]:checked`);
                answers[questionIndex] = inputs.length > 0 ? parseInt(inputs[0].value) : null;
                return;
            }
            
            answers[questionIndex] = null;
        }

        function loadAnswer(questionIndex, answer) {
            const question = questions[questionIndex];
            
            if (question.type === 'short_answer') {
                document.getElementById(`answer-${questionIndex}`).value = answer;
                return;
            }
            
            if (question.type === 'true_false') {
                if (answer !== null) {
                    const input = document.getElementById(`option-${questionIndex}-${answer === 'true' ? '0' : '1'}`);
                    if (input) {
                        input.checked = true;
                        input.closest('.option').classList.add('selected');
                    }
                }
                return;
            }
            
            if (question.type === 'mcq' || question.type === 'multiple_choice') {
                if (answer !== null) {
                    const input = document.getElementById(`option-${questionIndex}-${answer}`);
                    if (input) {
                        input.checked = true;
                        input.closest('.option').classList.add('selected');
                    }
                }
                return;
            }
        }

        function nextQuestion() {
            if (currentQuestionIndex < questions.length - 1) {
                loadQuestion(currentQuestionIndex + 1);
            } else {
                finishPractice();
            }
        }

        function previousQuestion() {
            if (currentQuestionIndex > 0) {
                loadQuestion(currentQuestionIndex - 1);
            }
        }

        function updateProgress() {
            const progress = ((currentQuestionIndex + 1) / questions.length) * 100;
            document.getElementById('progress-fill').style.width = progress + '%';
            const pm = document.getElementById('progress-mini-fill');
            if (pm) pm.style.width = progress + '%';
            document.getElementById('question-counter').textContent = 
                `${currentQuestionIndex + 1} / ${questions.length}`;
        }

        function updateNavigation() {
            document.getElementById('prev-btn').disabled = currentQuestionIndex === 0;
            const nextBtn = document.getElementById('next-btn');
            const nextBtnText = document.getElementById('btnNext');
            if (nextBtnText) {
                nextBtnText.textContent = currentQuestionIndex === questions.length - 1 ? 'Bitir' : 'Sonraki →';
            }
        }

        function finishPractice() {
            // Cevapsız soruları kontrol et
            const unansweredCount = questions.length - Object.keys(answers).filter(key => {
                const answer = answers[key];
                return answer !== null && answer !== undefined && answer !== '';
            }).length;
            
            if (unansweredCount > 0) {
                const confirmText = currentLang === 'de' ? 
                    `Sie haben ${unansweredCount} unbeantwortete Fragen. Möchten Sie trotzdem fortfahren?` : 
                    `${unansweredCount} cevaplanmamış soru var. Yine de devam etmek istiyor musunuz?`;
                
                if (!confirm(confirmText)) {
                    return; // Kullanıcı iptal etti
                }
            }
            
            const endTime = Date.now();
            const duration = Math.floor((endTime - startTime) / 1000);
            
            // Sonuçları session'a kaydet
            fetch('practice_results.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    answers: answers,
                    duration: duration,
                    questions: questions
                })
            }).then(() => {
                window.location.href = 'practice_results.php';
            }).catch(error => {
                console.error('Error:', error);
                // Hata durumunda da yönlendir
                window.location.href = 'practice_results.php';
            });
        }

        // Kapsamlı TR/DE dil desteği
        let currentLang = 'tr';
        
        (function(){
            const tr = {
                pageTitle:'Alıştırma Modu', userRole:'Öğrenci', backHomeText:'Ana Sayfaya Dön', dashboard:'← Dashboard',
                practiceTitle:'🚀 Alıştırma Başladı!', questionsText:'soru ile alıştırma yapıyorsunuz',
                infoLabel1:'📚 Soru Bankası', infoLabel2:'📖 Konu', infoLabel3:'🔢 Toplam Soru',
                tipLabel:'İpucu:', tipDesc:'Soruları dikkatli okuyun ve doğru cevabı seçin. Alıştırma sonunda detaylı sonuçlar göreceksiniz.',
                noQuestionsTitle:'⚠️ Soru Bulunamadı', noQuestionsDesc:'Seçilen kriterlere uygun soru bulunamadı. Lütfen farklı filtreler deneyin.',
                btnBackToDashboard:'Dashboard\'a Dön', btnPrevious:'← Önceki', btnNext:'Sonraki →',
                timerRemaining:'Kalan Süre:', timerElapsed:'Geçen Süre:', questionText:'Soru', finishText:'Bitir'
            };
            const de = {
                pageTitle:'Übungsmodus', userRole:'Schüler', backHomeText:'Zur Startseite', dashboard:'← Dashboard',
                practiceTitle:'🚀 Übung gestartet!', questionsText:'Fragen üben Sie',
                infoLabel1:'📚 Fragendatenbank', infoLabel2:'📖 Thema', infoLabel3:'🔢 Gesamt Fragen',
                tipLabel:'Tipp:', tipDesc:'Lesen Sie die Fragen sorgfältig und wählen Sie die richtige Antwort. Am Ende der Übung sehen Sie detaillierte Ergebnisse.',
                noQuestionsTitle:'⚠️ Keine Fragen gefunden', noQuestionsDesc:'Keine Fragen entsprechen den ausgewählten Kriterien. Bitte versuchen Sie andere Filter.',
                btnBackToDashboard:'Zum Dashboard', btnPrevious:'← Vorherige', btnNext:'Nächste →',
                timerRemaining:'Verbleibende Zeit:', timerElapsed:'Verstrichene Zeit:', questionText:'Frage', finishText:'Beenden'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                currentLang = lang; // Global değişkeni güncelle
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#backHomeText', d.backHomeText);
                setText('#btnDashboard', d.dashboard);
                setText('#practiceTitle', d.practiceTitle);
                setText('#questionsText', d.questionsText);
                setText('#infoLabel1', d.infoLabel1);
                setText('#infoLabel2', d.infoLabel2);
                setText('#infoLabel3', d.infoLabel3);
                setText('#tipLabel', d.tipLabel);
                setText('#tipDesc', d.tipDesc);
                setText('#noQuestionsTitle', d.noQuestionsTitle);
                setText('#noQuestionsDesc', d.noQuestionsDesc);
                setText('#btnBackToDashboard', d.btnBackToDashboard);
                setText('#btnPrevious', d.btnPrevious);
                setText('#btnNext', d.btnNext);
                
                // Timer label'ları güncelle
                const timerLabel = document.getElementById('timer-label');
                if (timerLabel) {
                    const isRemaining = timerLabel.textContent.includes('Kalan') || timerLabel.textContent.includes('Verbleibende');
                    timerLabel.textContent = isRemaining ? d.timerRemaining : d.timerElapsed;
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_practice', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_practice')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_practice')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();

        // İlk soruyu yükle
        if (questions.length > 0) {
            loadQuestion(0);
        }
        // Kopyalamayı ve sağ tık menüsünü engelle
        document.addEventListener('contextmenu', function(e){ e.preventDefault(); });
        ['copy','cut','paste','selectstart','dragstart'].forEach(function(evt){
            document.addEventListener(evt, function(e){ e.preventDefault(); }, true);
        });
        document.addEventListener('keydown', function(e){
            const k = (e.key||'').toLowerCase();
            if ((e.ctrlKey || e.metaKey) && ['c','x','s','p','u','a'].includes(k)) { e.preventDefault(); }
            if (k === 'printscreen') { e.preventDefault(); }
        });
    </script>
</body>
</html>
