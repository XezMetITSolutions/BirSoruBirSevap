<?php
/**
 * Sınav Çözme Sayfası
 */

require_once 'config.php';
require_once 'ExamManager.php';

// Oturum kontrolü
if (!isset($_SESSION['exam']) || !isset($_SESSION['exam_start_time'])) {
    header('Location: index.php');
    exit;
}

$examManager = new ExamManager();
$examConfig = $_SESSION['exam'];
$questions = $examConfig['questions'] ?? [];
$currentQuestionIndex = $_SESSION['exam_current_index'] ?? 0;
$startTime = $_SESSION['exam_start_time'];

// AJAX isteklerini işle
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'submit_answer':
            $questionId = sanitize_input($_POST['question_id']);
            $answer = $_POST['answer'];
            $timeSpent = (int)$_POST['time_spent'];
            
            $examManager->saveAnswer($questionId, $answer, $timeSpent);
            echo json_encode(['success' => true]);
            exit;
            
        case 'next_question':
            $nextIndex = (int)$_POST['next_index'];
            $_SESSION['exam_current_index'] = $nextIndex;
            echo json_encode(['success' => true]);
            exit;
            
        case 'finish_exam':
            $results = $examManager->finishExam();
            $_SESSION['exam_results'] = $results;
            $_SESSION['exam_finished'] = true;
            echo json_encode(['success' => true, 'redirect' => 'exam_results.php']);
            exit;
            
        case 'check_time':
            $remaining = $examManager->getRemainingTime();
            $isTimeUp = $examManager->isTimeUp();
            echo json_encode([
                'success' => true,
                'remaining_time' => $remaining,
                'is_time_up' => $isTimeUp
            ]);
            exit;
    }
}

$currentQuestion = $questions[$currentQuestionIndex] ?? null;
$totalQuestions = count($questions);
$progress = $totalQuestions > 0 ? round((($currentQuestionIndex + 1) / $totalQuestions) * 100) : 0;
$timeLimit = $examConfig['timeLimit'] * 60; // dakikayı saniyeye çevir
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav - <?php echo $examConfig['title']; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .exam-title {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .progress-bar {
            background: #e1e8ed;
            border-radius: 10px;
            height: 20px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-fill {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            transition: width 0.3s ease;
            border-radius: 10px;
        }

        .exam-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 10px;
        }

        .question-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e1e8ed;
        }

        .question-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .question-timer {
            background: #e74c3c;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
        }

        .question-timer.warning {
            background: #f39c12;
            animation: pulse 1s infinite;
        }

        .question-timer.danger {
            background: #e74c3c;
            animation: pulse 0.5s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        .question-text {
            font-size: 1.2em;
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .options {
            margin-bottom: 25px;
        }

        .option {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .option.selected {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .option input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.2);
        }

        .option-label {
            font-weight: 600;
            margin-right: 10px;
            min-width: 30px;
        }

        .option-text {
            flex: 1;
        }

        .short-answer-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1.1em;
            margin-bottom: 20px;
        }

        .short-answer-input:focus {
            outline: none;
            border-color: #667eea;
        }

        .controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 30px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .navigation {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }

        .nav-btn {
            background: rgba(255, 255, 255, 0.9);
            border: 2px solid #e1e8ed;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .nav-btn:hover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
        }

        .nav-btn.answered {
            background: #27ae60;
            color: white;
            border-color: #27ae60;
        }

        .nav-btn.current {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .time-warning {
            background: #f39c12;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            display: none;
        }

        .time-warning.show {
            display: block;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .question-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .controls {
                flex-direction: column;
                gap: 15px;
            }
            
            .navigation {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="exam-title"><?php echo htmlspecialchars($examConfig['title']); ?></div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
            </div>
            <div class="exam-info">
                <span>Soru <?php echo $currentQuestionIndex + 1; ?> / <?php echo $totalQuestions; ?> (<?php echo $progress; ?>%)</span>
                <span id="remaining-time">--:--</span>
            </div>
        </div>

        <div class="time-warning" id="time-warning">
            <strong>⚠️ Uyarı:</strong> Sınav süreniz azalıyor! Lütfen cevaplarınızı tamamlayın.
        </div>

        <div class="navigation">
            <?php for ($i = 0; $i < $totalQuestions; $i++): ?>
                <?php 
                $isAnswered = isset($_SESSION['student_answers'][$questions[$i]['id']]);
                $isCurrent = $i === $currentQuestionIndex;
                $class = 'nav-btn';
                if ($isCurrent) $class .= ' current';
                elseif ($isAnswered) $class .= ' answered';
                ?>
                <button class="<?php echo $class; ?>" onclick="goToQuestion(<?php echo $i; ?>)">
                    <?php echo $i + 1; ?>
                </button>
            <?php endfor; ?>
        </div>

        <?php if ($currentQuestion): ?>
            <div class="question-card">
                <div class="question-header">
                    <div class="question-number">
                        Soru <?php echo $currentQuestionIndex + 1; ?>
                    </div>
                    <div class="question-timer" id="timer">
                        --:--
                    </div>
                </div>

                <div class="question-text">
                    <?php echo htmlspecialchars($currentQuestion['text']); ?>
                </div>

                <form id="answer-form">
                    <input type="hidden" name="question_id" value="<?php echo $currentQuestion['id']; ?>">
                    <input type="hidden" name="time_spent" value="0" id="time-spent">

                    <?php if ($currentQuestion['type'] === 'mcq'): ?>
                        <div class="options">
                            <?php foreach ($currentQuestion['options'] as $option): ?>
                                <div class="option" data-value="<?php echo htmlspecialchars($option['key']); ?>">
                                    <input type="radio" name="answer" value="<?php echo htmlspecialchars($option['key']); ?>" 
                                           id="option-<?php echo $option['key']; ?>">
                                    <label for="option-<?php echo $option['key']; ?>" class="option-label">
                                        <?php echo htmlspecialchars($option['key']); ?>
                                    </label>
                                    <div class="option-text">
                                        <?php echo htmlspecialchars($option['text']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php elseif ($currentQuestion['type'] === 'short_answer'): ?>
                        <input type="text" name="answer" class="short-answer-input" 
                               placeholder="Cevabınızı yazın...">
                    <?php endif; ?>

                    <div class="controls">
                        <button type="button" class="btn btn-secondary" id="prev-btn" 
                                <?php echo $currentQuestionIndex === 0 ? 'disabled' : ''; ?>>
                            ← Önceki
                        </button>
                        
                        <button type="button" class="btn" id="save-btn">
                            Kaydet
                        </button>
                        
                        <button type="button" class="btn btn-success" id="next-btn" 
                                <?php echo $currentQuestionIndex === $totalQuestions - 1 ? 'disabled' : ''; ?>>
                            Sonraki →
                        </button>
                        
                        <button type="button" class="btn btn-danger" id="finish-btn">
                            Sınavı Bitir
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let questionStartTime = Date.now();
        let timeInterval;
        let timeLimit = <?php echo $timeLimit; ?>;
        let startTime = <?php echo $startTime; ?> * 1000;

        // Timer başlat
        timeInterval = setInterval(updateTimer, 1000);
        updateTimer();

        function updateTimer() {
            const now = Date.now();
            const elapsed = Math.floor((now - startTime) / 1000);
            const remaining = Math.max(0, timeLimit - elapsed);
            
            const timeDisplay = formatTime(remaining);
            document.getElementById('timer').textContent = timeDisplay;
            document.getElementById('remaining-time').textContent = timeDisplay;
            document.getElementById('time-spent').value = Math.floor((now - questionStartTime) / 1000);
            
            // Uyarı göster
            const warning = document.getElementById('time-warning');
            const timer = document.getElementById('timer');
            
            if (remaining <= 300) { // 5 dakika
                warning.classList.add('show');
                timer.classList.add('danger');
            } else if (remaining <= 600) { // 10 dakika
                timer.classList.add('warning');
            }
            
            if (remaining === 0) {
                clearInterval(timeInterval);
                finishExam();
            }
        }

        function formatTime(seconds) {
            const mins = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        }

        // Seçenek tıklama
        document.querySelectorAll('.option').forEach(option => {
            option.addEventListener('click', function() {
                const radio = this.querySelector('input[type="radio"]');
                radio.checked = true;
                
                // Seçili seçeneği işaretle
                document.querySelectorAll('.option').forEach(opt => opt.classList.remove('selected'));
                this.classList.add('selected');
            });
        });

        // Mevcut cevabı yükle
        loadCurrentAnswer();

        function loadCurrentAnswer() {
            const questionId = document.querySelector('input[name="question_id"]').value;
            
            // AJAX ile mevcut cevabı kontrol et
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'check_time'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.is_time_up) {
                    finishExam();
                    return;
                }
            });
        }

        // Cevabı kaydet
        document.getElementById('save-btn').addEventListener('click', function() {
            saveAnswer();
        });

        function saveAnswer() {
            const formData = new FormData(document.getElementById('answer-form'));
            const answer = formData.get('answer');
            
            if (!answer) {
                alert('Lütfen bir cevap seçin');
                return;
            }

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'submit_answer',
                    question_id: formData.get('question_id'),
                    answer: answer,
                    time_spent: formData.get('time_spent')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Navigasyon butonlarını güncelle
                    updateNavigation();
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function updateNavigation() {
            const currentIndex = <?php echo $currentQuestionIndex; ?>;
            const navBtn = document.querySelector(`.nav-btn:nth-child(${currentIndex + 1})`);
            if (navBtn) {
                navBtn.classList.add('answered');
            }
        }

        // Sonraki soru
        document.getElementById('next-btn').addEventListener('click', function() {
            const nextIndex = <?php echo $currentQuestionIndex; ?> + 1;
            goToQuestion(nextIndex);
        });

        // Önceki soru
        document.getElementById('prev-btn').addEventListener('click', function() {
            const prevIndex = <?php echo $currentQuestionIndex; ?> - 1;
            goToQuestion(prevIndex);
        });

        function goToQuestion(index) {
            if (index < 0 || index >= <?php echo $totalQuestions; ?>) {
                return;
            }

            // Mevcut cevabı kaydet
            const formData = new FormData(document.getElementById('answer-form'));
            const answer = formData.get('answer');
            
            if (answer) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'submit_answer',
                        question_id: formData.get('question_id'),
                        answer: answer,
                        time_spent: formData.get('time_spent')
                    })
                });
            }

            // Yeni soruya git
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'next_question',
                    next_index: index
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        }

        // Sınavı bitir
        document.getElementById('finish-btn').addEventListener('click', function() {
            if (confirm('Sınavı bitirmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) {
                finishExam();
            }
        });

        function finishExam() {
            // Son cevabı kaydet
            const formData = new FormData(document.getElementById('answer-form'));
            const answer = formData.get('answer');
            
            if (answer) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'submit_answer',
                        question_id: formData.get('question_id'),
                        answer: answer,
                        time_spent: formData.get('time_spent')
                    })
                });
            }

            // Sınavı bitir
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'finish_exam'
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                }
            });
        }

        // Sayfa kapatma uyarısı
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'Sınav devam ediyor. Sayfayı kapatmak istediğinizden emin misiniz?';
        });

        // Klavye kısayolları
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch(e.key) {
                    case 'ArrowLeft':
                        e.preventDefault();
                        document.getElementById('prev-btn').click();
                        break;
                    case 'ArrowRight':
                        e.preventDefault();
                        document.getElementById('next-btn').click();
                        break;
                    case 's':
                        e.preventDefault();
                        document.getElementById('save-btn').click();
                        break;
                }
            }
        });
    </script>
</body>
</html>
