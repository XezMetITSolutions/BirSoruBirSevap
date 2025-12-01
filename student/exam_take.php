<?php
session_start();
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

// Sınav kontrolü
if (!isset($_SESSION['current_exam']) || !isset($_SESSION['exam_code'])) {
    header('Location: exam_join.php');
    exit;
}

$exam = $_SESSION['current_exam'];
$examCode = $_SESSION['exam_code'];

// Sınav sorularını al
$selectedQuestions = $exam['questions'] ?? [];

// Eğer sınavda sorular yoksa, kategorilerden yükle
if (empty($selectedQuestions)) {
    $questionLoader = new QuestionLoader();
    $questionLoader->loadQuestions();
    
    $filteredQuestions = [];
    
    // Çoklu kategori desteği
    if (isset($exam['categories']) && is_array($exam['categories'])) {
        foreach ($exam['categories'] as $categoryData) {
            $parts = explode('|', $categoryData);
            $bank = $parts[0] ?? '';
            $category = $parts[1] ?? '';
            
            $categoryQuestions = $questionLoader->getFilteredQuestions([
                'bank' => $bank,
                'category' => $category,
                'count' => 999 // Tüm soruları al
            ]);
            
            $filteredQuestions = array_merge($filteredQuestions, $categoryQuestions);
        }
    }
    
    // Sınav sorularını karıştır ve seç
    shuffle($filteredQuestions);
    $selectedQuestions = array_slice($filteredQuestions, 0, $exam['question_count'] ?? 10);
    
    if (!empty($selectedQuestions)) {
        // Sınav verilerine soruları ekle ve session'ı güncelle
        $exam['questions'] = $selectedQuestions;
        $_SESSION['current_exam'] = $exam;
        
        // Güncellenmiş sınav verilerini kaydet
        $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
        $allExams[$exam['id']]['questions'] = $selectedQuestions;
        file_put_contents('../data/exams.json', json_encode($allExams, JSON_PRETTY_PRINT));
    }
}

// Debug: Eğer hala soru yoksa hata göster
if (empty($selectedQuestions)) {
    $errorMessage = "Sınavda soru bulunamadı. Lütfen öğretmeninizle iletişime geçin.";
}

// Session'a kaydet
$_SESSION['exam_questions'] = $selectedQuestions;
$_SESSION['exam_answers'] = [];
$_SESSION['exam_current_question'] = 0;
$_SESSION['exam_start_time'] = time();

// Sınav süresi (saniye)
$examDuration = $exam['duration'] * 60;
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav - <?php echo htmlspecialchars($exam['title']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 15px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
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
        
        .exam-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .exam-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .timer {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 1.1em;
        }
        
        .lang-toggle {
            background: rgba(6, 133, 103, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        
        .lang-toggle:hover {
            background: #068466;
            color: white;
        }
        
        .progress-bar {
            width: 200px;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            transition: width 0.3s ease;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 20px;
        }
        
        .question-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .question-number {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
        }
        
        .question-text {
            font-size: 1.3em;
            line-height: 1.6;
            margin-bottom: 30px;
            color: #2c3e50;
        }
        
        .options {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .option {
            padding: 20px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
        }
        
        .option:hover {
            border-color: #068567;
            background: rgba(6, 133, 103, 0.05);
            transform: translateY(-2px);
        }
        
        .option.selected {
            border-color: #068567;
            background: rgba(6, 133, 103, 0.1);
        }
        
        .option input[type="radio"] {
            margin-right: 15px;
            accent-color: #068567;
        }
        
        .option label {
            cursor: pointer;
            font-size: 1.1em;
            color: #2c3e50;
        }
        
        .navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }
        
        .nav-button {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .nav-button:disabled {
            background: #6c757d;
            color: white;
            cursor: not-allowed;
        }
        
        .btn-prev {
            background: #6c757d;
            color: white;
        }
        
        .btn-next {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
        }
        
        .btn-prev:hover:not(:disabled) {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .btn-next:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(6, 132, 102, 0.3);
        }
        
        .submit-button {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
            padding: 20px 40px;
            border: none;
            border-radius: 25px;
            font-size: 1.2em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 30px;
        }
        
        .submit-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(220, 53, 69, 0.3);
        }
        
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        @media (max-width: 768px) {
            .header { padding: 14px 0; }
            .header-content { padding: 0 12px; gap: 10px; }
            
            .exam-info {
                flex-direction: column;
                gap: 10px;
            }
            
            .progress-bar {
                width: 150px;
            }
            
            .question-card {
                padding: 25px;
            }
            
            .navigation {
                flex-direction: column;
                gap: 15px;
            }
        }
        @media (max-width: 420px) {
            .timer { padding: 8px 14px; border-radius: 16px; font-size: 1rem; }
        }

        /* Kopya önleme: metin seçimini ve çağrı menülerini engelle */
        html, body { -webkit-user-select: none; -ms-user-select: none; user-select: none; -webkit-touch-callout: none; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="exam-info">
                <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                <div class="timer" id="timer">00:00</div>
                <button id="langToggle" class="lang-toggle">DE</button>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" id="progress" style="width: 0%"></div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php if (isset($errorMessage)): ?>
            <div class="warning" style="background: #f8d7da; border-color: #f5c6cb; color: #721c24;">
                ❌ <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>
        
        <div class="warning" id="warningMessage">
            ⚠️ Sınav sırasında sayfayı kapatmayın veya yenilemeyin. Bu durumda sınavınız sonlanabilir.
        </div>

        <div class="question-card">
            <div class="question-header">
                <div class="question-number" id="questionNumber">Soru 1 / <?php echo count($selectedQuestions); ?></div>
            </div>
            
            <div class="question-text" id="questionText">
                Soru yükleniyor...
            </div>
            
            <div class="options" id="options">
                <!-- Seçenekler JavaScript ile yüklenecek -->
            </div>
            
            <div class="navigation">
                <button class="nav-button btn-prev" id="prevBtn" onclick="previousQuestion()" disabled>
                    <span id="btnPrevText">← Önceki</span>
                </button>
                <button class="nav-button btn-next" id="nextBtn" onclick="nextQuestion()">
                    <span id="btnNextText">Sonraki →</span>
                </button>
            </div>
            
            <div style="text-align: center;">
                <button class="submit-button" id="submitBtn" onclick="submitExam()" style="display: none;">
                    <span id="btnSubmitText">🏁 Sınavı Tamamla</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentQuestion = 0;
        let totalQuestions = <?php echo count($selectedQuestions); ?>;
        let examDuration = <?php echo $examDuration; ?>;
        let timeLeft = examDuration;
        let answers = {};
        let allowUnload = false;
        
        const questions = <?php echo json_encode($selectedQuestions); ?>;
        
        // Timer
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            document.getElementById('timer').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                allowUnload = true;
                submitExam();
                return;
            }
            
            timeLeft--;
        }
        
        // Progress bar
        function updateProgress() {
            const progress = ((currentQuestion + 1) / totalQuestions) * 100;
            document.getElementById('progress').style.width = progress + '%';
        }
        
        // Question navigation
        function loadQuestion(index) {
            if (index < 0 || index >= totalQuestions) return;
            
            currentQuestion = index;
            const question = questions[index];
            
            // Question info
            updateQuestionNumber();
            document.getElementById('questionText').textContent = question.question;
            
            // Options
            const optionsContainer = document.getElementById('options');
            optionsContainer.innerHTML = '';
            
            question.options.forEach((option, optionIndex) => {
                const optionDiv = document.createElement('div');
                optionDiv.className = 'option';
                if (answers[index] === optionIndex) {
                    optionDiv.classList.add('selected');
                }
                
                const optionText = typeof option === 'string' ? option : option.text;
                
                optionDiv.innerHTML = `
                    <input type="radio" name="question_${index}" value="${optionIndex}" 
                           id="option_${index}_${optionIndex}" ${answers[index] === optionIndex ? 'checked' : ''}>
                    <label for="option_${index}_${optionIndex}">${optionText}</label>
                `;
                
                optionDiv.addEventListener('click', function() {
                    selectOption(index, optionIndex);
                });
                
                optionsContainer.appendChild(optionDiv);
            });
            
            // Navigation buttons
            document.getElementById('prevBtn').disabled = index === 0;
            document.getElementById('nextBtn').style.display = index === totalQuestions - 1 ? 'none' : 'inline-block';
            document.getElementById('submitBtn').style.display = index === totalQuestions - 1 ? 'block' : 'none';
            
            updateProgress();
        }
        
        function selectOption(questionIndex, optionIndex) {
            answers[questionIndex] = optionIndex;
            
            // Update visual selection
            document.querySelectorAll('.option').forEach(option => {
                option.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
        }
        
        function previousQuestion() {
            if (currentQuestion > 0) {
                loadQuestion(currentQuestion - 1);
            }
        }
        
        function nextQuestion() {
            if (currentQuestion < totalQuestions - 1) {
                loadQuestion(currentQuestion + 1);
            }
        }
        
        function submitExam() {
            // Cevapsız soruları kontrol et
            const unansweredCount = totalQuestions - Object.keys(answers).filter(key => {
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
            
            const confirmText2 = currentLang === 'de' ? 
                'Sind Sie sicher, dass Sie die Prüfung abschließen möchten?' : 
                'Sınavı tamamlamak istediğinizden emin misiniz?';
            
            if (confirm(confirmText2)) {
                allowUnload = true;
                // Submit answers
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'exam_submit.php';
                
                const answersInput = document.createElement('input');
                answersInput.type = 'hidden';
                answersInput.name = 'answers';
                answersInput.value = JSON.stringify(answers);
                form.appendChild(answersInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // TR/DE dil desteği
        let currentLang = 'tr';
        
        const tr = {
            warningMessage: '⚠️ Sınav sırasında sayfayı kapatmayın veya yenilemeyin. Bu durumda sınavınız sonlanabilir.',
            questionNumber: 'Soru',
            questionLoading: 'Soru yükleniyor...',
            btnPrev: '← Önceki',
            btnNext: 'Sonraki →',
            btnSubmit: '🏁 Sınavı Tamamla',
            noQuestions: 'Soru bulunamadı. Lütfen eğitmeninizle iletişime geçin.',
            confirmSubmit: 'Sınavı tamamlamak istediğinizden emin misiniz?'
        };
        
        const de = {
            warningMessage: '⚠️ Schließen oder aktualisieren Sie die Seite während der Prüfung nicht. Dies kann zum Ende Ihrer Prüfung führen.',
            questionNumber: 'Frage',
            questionLoading: 'Frage wird geladen...',
            btnPrev: '← Zurück',
            btnNext: 'Weiter →',
            btnSubmit: '🏁 Prüfung abschließen',
            noQuestions: 'Keine Fragen gefunden. Bitte kontaktieren Sie Ihren Lehrer.',
            confirmSubmit: 'Sind Sie sicher, dass Sie die Prüfung abschließen möchten?'
        };
        
        function applyLanguage(lang) {
            currentLang = lang;
            const d = lang === 'de' ? de : tr;
            
            document.getElementById('warningMessage').textContent = d.warningMessage;
            document.getElementById('btnPrevText').textContent = d.btnPrev;
            document.getElementById('btnNextText').textContent = d.btnNext;
            document.getElementById('btnSubmitText').textContent = d.btnSubmit;
            
            // Update question number format
            updateQuestionNumber();
            
            const toggle = document.getElementById('langToggle');
            if (toggle) toggle.textContent = (lang === 'de' ? 'TR' : 'DE');
            localStorage.setItem('lang_exam_take', lang);
        }
        
        function updateQuestionNumber() {
            const d = currentLang === 'de' ? de : tr;
            document.getElementById('questionNumber').textContent = 
                `${d.questionNumber} ${currentQuestion + 1} / ${totalQuestions}`;
        }
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Load language preference
            const savedLang = localStorage.getItem('lang_exam_take') || localStorage.getItem('lang') || 'tr';
            applyLanguage(savedLang);
            
            // Language toggle
            const toggle = document.getElementById('langToggle');
            if (toggle) {
                toggle.addEventListener('click', function() {
                    const nextLang = currentLang === 'tr' ? 'de' : 'tr';
                    applyLanguage(nextLang);
                });
            }
            
            if (totalQuestions > 0) {
                loadQuestion(0);
                setInterval(updateTimer, 1000);
            } else {
                const d = currentLang === 'de' ? de : tr;
                document.getElementById('questionText').textContent = d.noQuestions;
            }
        });
        
        // Prevent page refresh
        window.addEventListener('beforeunload', function(e) {
            if (allowUnload) return;
            e.preventDefault();
            e.returnValue = '';
        });

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
