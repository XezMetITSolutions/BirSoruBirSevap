<?php
/**
 * Alıştırma Modu - Öğrenci Sayfası
 */

require_once 'config.php';
require_once 'QuestionLoader.php';

// Oturum kontrolü
if (!isset($_SESSION['practice_questions']) || empty($_SESSION['practice_questions'])) {
    header('Location: index.php');
    exit;
}

$questions = $_SESSION['practice_questions'];
$settings = $_SESSION['practice_settings'] ?? [];
$answers = $_SESSION['practice_answers'] ?? [];
$currentQuestionIndex = $_SESSION['practice_current_index'] ?? 0;
$startTime = $_SESSION['practice_start_time'] ?? time();

// AJAX isteklerini işle
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'submit_answer':
            $questionIndex = (int)$_POST['question_index'];
            $answer = $_POST['answer'];
            $timeSpent = (int)$_POST['time_spent'];
            
            if (!isset($_SESSION['practice_answers'])) {
                $_SESSION['practice_answers'] = [];
            }
            
            $_SESSION['practice_answers'][$questionIndex] = [
                'answer' => $answer,
                'time_spent' => $timeSpent,
                'answered_at' => time()
            ];
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'next_question':
            $nextIndex = (int)$_POST['next_index'];
            $_SESSION['practice_current_index'] = $nextIndex;
            echo json_encode(['success' => true]);
            exit;
            
        case 'finish_practice':
            $results = calculatePracticeResults();
            $_SESSION['practice_results'] = $results;
            $_SESSION['practice_finished'] = true;
            echo json_encode(['success' => true, 'redirect' => 'practice_results.php']);
            exit;
    }
}

function calculatePracticeResults() {
    $questions = $_SESSION['practice_questions'];
    $answers = $_SESSION['practice_answers'] ?? [];
    $startTime = $_SESSION['practice_start_time'];
    $endTime = time();
    
    $totalQuestions = count($questions);
    $correctAnswers = 0;
    $wrongAnswers = 0;
    $unanswered = 0;
    $totalPoints = 0;
    $earnedPoints = 0;
    $timeSpent = $endTime - $startTime;
    
    $questionResults = [];
    
    foreach ($questions as $index => $question) {
        $totalPoints += $question['points'];
        $studentAnswer = $answers[$index] ?? null;
        
        if (!$studentAnswer || empty($studentAnswer['answer'])) {
            $unanswered++;
            $questionResults[] = [
                'question' => $question,
                'student_answer' => null,
                'correct' => false,
                'points' => 0,
                'time_spent' => 0
            ];
        } else {
            $isCorrect = checkAnswer($question, $studentAnswer['answer']);
            
            if ($isCorrect) {
                $correctAnswers++;
                $earnedPoints += $question['points'];
            } else {
                $wrongAnswers++;
            }
            
            $questionResults[] = [
                'question' => $question,
                'student_answer' => $studentAnswer['answer'],
                'correct' => $isCorrect,
                'points' => $isCorrect ? $question['points'] : 0,
                'time_spent' => $studentAnswer['time_spent'] ?? 0
            ];
        }
    }
    
    $percentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
    $averageTime = $totalQuestions > 0 ? round($timeSpent / $totalQuestions, 2) : 0;
    
    return [
        'total_questions' => $totalQuestions,
        'correct_answers' => $correctAnswers,
        'wrong_answers' => $wrongAnswers,
        'unanswered' => $unanswered,
        'total_points' => $totalPoints,
        'earned_points' => $earnedPoints,
        'percentage' => $percentage,
        'time_spent' => $timeSpent,
        'average_time_per_question' => $averageTime,
        'question_results' => $questionResults
    ];
}

function checkAnswer($question, $studentAnswer) {
    $correctAnswers = $question['answer'];
    
    if ($question['type'] === 'mcq') {
        if (is_array($studentAnswer)) {
            sort($studentAnswer);
            sort($correctAnswers);
            return $studentAnswer === $correctAnswers;
        } else {
            return in_array($studentAnswer, $correctAnswers);
        }
    } elseif ($question['type'] === 'true_false') {
        $normalizedStudent = normalizeTrueFalse($studentAnswer);
        $normalizedCorrect = array_map('normalizeTrueFalse', $correctAnswers);
        return in_array($normalizedStudent, $normalizedCorrect);
    } elseif ($question['type'] === 'short_answer') {
        $normalizedStudent = normalizeText($studentAnswer);
        foreach ($correctAnswers as $correctAnswer) {
            if (normalizeText($correctAnswer) === $normalizedStudent) {
                return true;
            }
        }
        return false;
    }
    
    return false;
}

function normalizeText($text) {
    $text = mb_strtolower(trim($text), 'UTF-8');
    $replacements = [
        'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
        'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u'
    ];
    return strtr($text, $replacements);
}

function normalizeTrueFalse($value) {
    $value = normalizeText($value);
    $trueValues = ['true', 'doğru', 'evet', 'yes', '1'];
    $falseValues = ['false', 'yanlış', 'hayır', 'no', '0'];
    
    if (in_array($value, $trueValues)) {
        return 'true';
    } elseif (in_array($value, $falseValues)) {
        return 'false';
    }
    
    return $value;
}

$currentQuestion = $questions[$currentQuestionIndex] ?? null;
$totalQuestions = count($questions);
$progress = $totalQuestions > 0 ? round((($currentQuestionIndex + 1) / $totalQuestions) * 100) : 0;
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

        .option.correct {
            border-color: #27ae60;
            background: rgba(39, 174, 96, 0.1);
        }

        .option.incorrect {
            border-color: #e74c3c;
            background: rgba(231, 76, 60, 0.1);
        }

        .option input[type="radio"],
        .option input[type="checkbox"] {
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

        .feedback {
            margin-top: 20px;
            padding: 20px;
            border-radius: 10px;
            display: none;
        }

        .feedback.correct {
            background: rgba(39, 174, 96, 0.1);
            border: 2px solid #27ae60;
            color: #27ae60;
        }

        .feedback.incorrect {
            background: rgba(231, 76, 60, 0.1);
            border: 2px solid #e74c3c;
            color: #e74c3c;
        }

        .explanation {
            margin-top: 15px;
            padding: 15px;
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid #3498db;
            border-radius: 10px;
            color: #2980b9;
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

        .stats {
            display: flex;
            justify-content: space-around;
            margin-bottom: 20px;
        }

        .stat {
            text-align: center;
            color: white;
        }

        .stat-number {
            font-size: 1.5em;
            font-weight: 600;
        }

        .stat-label {
            font-size: 0.9em;
            opacity: 0.9;
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
            
            .stats {
                flex-direction: column;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📚 Alıştırma</h1>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
            </div>
            <p>Soru <?php echo $currentQuestionIndex + 1; ?> / <?php echo $totalQuestions; ?> (<?php echo $progress; ?>%)</p>
        </div>

        <?php if ($currentQuestion): ?>
            <div class="question-card">
                <div class="question-header">
                    <div class="question-number">
                        Soru <?php echo $currentQuestionIndex + 1; ?>
                    </div>
                    <div class="question-timer" id="timer">
                        <?php echo format_time($settings['timer'] ? 30 : 0); ?>
                    </div>
                </div>

                <div class="question-text">
                    <?php echo htmlspecialchars($currentQuestion['text']); ?>
                </div>

                <form id="answer-form">
                    <input type="hidden" name="question_index" value="<?php echo $currentQuestionIndex; ?>">
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

                    <div class="feedback" id="feedback">
                        <div id="feedback-text"></div>
                        <?php if (!empty($currentQuestion['explanation'])): ?>
                            <div class="explanation">
                                <strong>Açıklama:</strong> <?php echo htmlspecialchars($currentQuestion['explanation']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="controls">
                        <button type="button" class="btn btn-secondary" id="prev-btn" 
                                <?php echo $currentQuestionIndex === 0 ? 'disabled' : ''; ?>>
                            ← Önceki
                        </button>
                        
                        <button type="submit" class="btn" id="check-btn">
                            Cevabı Kontrol Et
                        </button>
                        
                        <button type="button" class="btn btn-success" id="next-btn" style="display: none;">
                            Sonraki →
                        </button>
                        
                        <button type="button" class="btn btn-danger" id="finish-btn" style="display: none;">
                            Alıştırmayı Bitir
                        </button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        let questionStartTime = Date.now();
        let timerInterval;
        let timeLimit = <?php echo $settings['timer'] ? 30 : 0; ?>;

        // Timer başlat
        if (timeLimit > 0) {
            timerInterval = setInterval(updateTimer, 1000);
        }

        function updateTimer() {
            const elapsed = Math.floor((Date.now() - questionStartTime) / 1000);
            const remaining = Math.max(0, timeLimit - elapsed);
            
            document.getElementById('timer').textContent = formatTime(remaining);
            document.getElementById('time-spent').value = elapsed;
            
            if (remaining === 0) {
                clearInterval(timerInterval);
                // Otomatik olarak cevabı kontrol et
                checkAnswer();
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

        // Form gönderimi
        document.getElementById('answer-form').addEventListener('submit', function(e) {
            e.preventDefault();
            checkAnswer();
        });

        function checkAnswer() {
            const formData = new FormData(document.getElementById('answer-form'));
            const answer = formData.get('answer');
            
            if (!answer) {
                alert('Lütfen bir cevap seçin');
                return;
            }

            // Cevabı kaydet
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'submit_answer',
                    question_index: formData.get('question_index'),
                    answer: answer,
                    time_spent: formData.get('time_spent')
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showFeedback(answer);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function showFeedback(studentAnswer) {
            const question = <?php echo json_encode($currentQuestion); ?>;
            const correctAnswers = question.answer;
            const isCorrect = checkAnswerCorrect(question, studentAnswer);
            
            // Seçenekleri işaretle
            if (question.type === 'mcq') {
                document.querySelectorAll('.option').forEach(option => {
                    const value = option.dataset.value;
                    if (correctAnswers.includes(value)) {
                        option.classList.add('correct');
                    } else if (value === studentAnswer) {
                        option.classList.add('incorrect');
                    }
                });
            }
            
            // Geri bildirim göster
            const feedback = document.getElementById('feedback');
            const feedbackText = document.getElementById('feedback-text');
            
            if (isCorrect) {
                feedback.className = 'feedback correct';
                feedbackText.innerHTML = '✅ <strong>Doğru!</strong> Tebrikler!';
            } else {
                feedback.className = 'feedback incorrect';
                feedbackText.innerHTML = '❌ <strong>Yanlış!</strong> Doğru cevap: ' + correctAnswers.join(', ');
            }
            
            feedback.style.display = 'block';
            
            // Butonları güncelle
            document.getElementById('check-btn').style.display = 'none';
            
            if (<?php echo $currentQuestionIndex + 1; ?> < <?php echo $totalQuestions; ?>) {
                document.getElementById('next-btn').style.display = 'inline-block';
            } else {
                document.getElementById('finish-btn').style.display = 'inline-block';
            }
            
            // Timer'ı durdur
            if (timerInterval) {
                clearInterval(timerInterval);
            }
        }

        function checkAnswerCorrect(question, studentAnswer) {
            const correctAnswers = question.answer;
            
            if (question.type === 'mcq') {
                if (Array.isArray(studentAnswer)) {
                    return JSON.stringify(studentAnswer.sort()) === JSON.stringify(correctAnswers.sort());
                } else {
                    return correctAnswers.includes(studentAnswer);
                }
            } else if (question.type === 'short_answer') {
                const normalizedStudent = normalizeText(studentAnswer);
                return correctAnswers.some(correct => normalizeText(correct) === normalizedStudent);
            }
            
            return false;
        }

        function normalizeText(text) {
            return text.toLowerCase().trim()
                .replace(/ç/g, 'c').replace(/ğ/g, 'g').replace(/ı/g, 'i')
                .replace(/ö/g, 'o').replace(/ş/g, 's').replace(/ü/g, 'u');
        }

        // Sonraki soru
        document.getElementById('next-btn').addEventListener('click', function() {
            const nextIndex = <?php echo $currentQuestionIndex; ?> + 1;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'next_question',
                    next_index: nextIndex
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        });

        // Önceki soru
        document.getElementById('prev-btn').addEventListener('click', function() {
            const prevIndex = <?php echo $currentQuestionIndex; ?> - 1;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'next_question',
                    next_index: prevIndex
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                }
            });
        });

        // Alıştırmayı bitir
        document.getElementById('finish-btn').addEventListener('click', function() {
            if (confirm('Alıştırmayı bitirmek istediğinizden emin misiniz?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'finish_practice'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.href = data.redirect;
                    }
                });
            }
        });
    </script>
</body>
</html>
