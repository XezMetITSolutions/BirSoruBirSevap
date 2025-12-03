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

// Sınav kontrolü
if (!isset($_SESSION['current_exam']) || !isset($_SESSION['exam_questions'])) {
    header('Location: exam_join.php');
    exit;
}

$exam = $_SESSION['current_exam'];
$examCode = $_SESSION['exam_code'];
$questions = $_SESSION['exam_questions'];

// Cevapları al
$answers = [];
if (isset($_POST['answers'])) {
    $answers = json_decode($_POST['answers'], true) ?? [];
}

// Sonuçları hesapla
$correctAnswers = 0;
$totalQuestions = count($questions);
$results = [];

foreach ($questions as $index => $question) {
    $userAnswer = $answers[$index] ?? null;
    $correctAnswer = $question['answer'] ?? $question['correct_answer'] ?? 0;
    
    // Cevap formatını düzelt
    if (is_array($correctAnswer)) {
        $correctAnswer = $correctAnswer[0] ?? 0;
    }
    
    // String cevabı (A, B, C, D) sayıya çevir
    if (is_string($correctAnswer)) {
        $correctAnswer = ord(strtoupper($correctAnswer)) - ord('A');
    }
    
    // Kullanıcı cevabını da sayıya çevir
    if (is_string($userAnswer)) {
        $userAnswer = ord(strtoupper($userAnswer)) - ord('A');
    }
    
    $isCorrect = ($userAnswer === $correctAnswer);
    if ($isCorrect) {
        $correctAnswers++;
    }
    
    $results[] = [
        'question' => $question['question'] ?? $question['text'] ?? '',
        'user_answer' => $userAnswer,
        'correct_answer' => $correctAnswer,
        'is_correct' => $isCorrect,
        'options' => $question['options'] ?? []
    ];
}

$score = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100) : 0;


// Süre hesapla
$startTime = $_SESSION['exam_start_time'] ?? time();
$endTime = time();
$durationSeconds = $endTime - $startTime;
$durationMinutes = round($durationSeconds / 60, 1);

// Sınav sonucunu kaydet
$examResult = [
    'exam_code' => $examCode,
    'student_id' => $user['username'] ?? $user['name'] ?? 'unknown',
    'student_name' => $user['name'] ?? 'Bilinmeyen',
    'exam_title' => $exam['title'],
    'score' => $score,
    'correct' => $correctAnswers,
    'wrong' => $totalQuestions - $correctAnswers,
    'empty' => 0,
    'duration' => $durationMinutes . ' dakika',
    'completed_at' => date('Y-m-d H:i:s'),
    'answers' => $answers,
    'detailed_results' => $results
];

// Veritabanına kaydet
require_once '../database.php';
try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    $sql = "INSERT INTO exam_results (exam_id, username, student_name, total_questions, correct_answers, wrong_answers, score, percentage, time_taken, answers, start_time, submit_time) 
            VALUES (:exam_id, :username, :student_name, :total, :correct, :wrong, :score, :percentage, :duration, :answers, :start_time, :submit_time)";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        ':exam_id' => $examCode,
        ':username' => $user['username'],
        ':student_name' => $user['name'] ?? 'Bilinmeyen',
        ':total' => $totalQuestions,
        ':correct' => $correctAnswers,
        ':wrong' => $totalQuestions - $correctAnswers,
        ':score' => $score,
        ':percentage' => $score,
        ':duration' => $durationSeconds,
        ':answers' => json_encode($answers),
        ':start_time' => date('Y-m-d H:i:s', $startTime),
        ':submit_time' => date('Y-m-d H:i:s', $endTime)
    ]);
} catch (Exception $e) {
    error_log("Sınav veritabanı kayıt hatası: " . $e->getMessage());
}

// Session'ı temizle
unset($_SESSION['current_exam']);
unset($_SESSION['exam_code']);
unset($_SESSION['exam_questions']);
unset($_SESSION['exam_answers']);
unset($_SESSION['exam_current_question']);
unset($_SESSION['exam_start_time']);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Sonucu - Bir Soru Bir Sevap</title>
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
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
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
            color: #2c3e50;
        }
        
        .logo p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .back-btn {
            background: rgba(6, 132, 102, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .back-btn:hover {
            background: #068466;
            color: white;
            transform: translateY(-2px);
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
        
        .container {
            max-width: 800px;
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
        
        .result-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 45px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .score-display {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2.5em;
            font-weight: bold;
            color: white;
        }
        
        .score-excellent {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        }
        
        .score-good {
            background: linear-gradient(135deg, #17a2b8 0%, #6f42c1 100%);
        }
        
        .score-average {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
        }
        
        .score-poor {
            background: linear-gradient(135deg, #dc3545 0%, #e83e8c 100%);
        }
        
        .score-text {
            font-size: 1.5em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .score-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 40px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
        }
        
        .detail-value {
            font-size: 2em;
            font-weight: bold;
            color: #068466;
        }
        
        .detail-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .results-summary {
            margin-top: 30px;
        }
        
        .results-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .result-item {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #e9ecef;
        }
        
        .result-item.correct {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .result-item.incorrect {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .result-question {
            font-weight: 600;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        
        .result-answer {
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .result-answer.correct {
            color: #155724;
        }
        
        .result-answer.incorrect {
            color: #721c24;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
        }
        
        .action-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .lang-toggle, .back-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .page-title { font-size: 2.2em; }
            .result-card { padding: 30px; }
            .score-details { grid-template-columns: 1fr; }
            .action-buttons { flex-direction: column; }
        }
        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .lang-toggle, .back-btn { padding: 5px 8px; font-size: .85rem; }
            .user-info { gap: 8px; }
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
                    <p>Sınav Sonucu</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="lang-toggle">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBack">← Ana Sayfa</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="pageTitle">🎯 Sınav Tamamlandı</h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($exam['title']); ?></p>
        </div>


        <div class="result-card">
            <div class="score-display">
                <div class="score-circle <?php 
                    if ($score >= 80) echo 'score-excellent';
                    elseif ($score >= 60) echo 'score-good';
                    elseif ($score >= 40) echo 'score-average';
                    else echo 'score-poor';
                ?>">
                    <?php echo $score; ?>%
                </div>
                <div class="score-text" id="scoreText">
                    <?php 
                    if ($score >= 80) echo 'Mükemmel! 🎉';
                    elseif ($score >= 60) echo 'İyi İş! 👍';
                    elseif ($score >= 40) echo 'Orta Seviye 📚';
                    else echo 'Daha Çok Çalışmalısın 💪';
                    ?>
                </div>
            </div>
            
            <div class="score-details">
                <div class="detail-item">
                    <div class="detail-value"><?php echo $correctAnswers; ?></div>
                    <div class="detail-label" id="labelCorrect">Doğru Cevap</div>
                </div>
                <div class="detail-item">
                    <div class="detail-value"><?php echo $totalQuestions - $correctAnswers; ?></div>
                    <div class="detail-label" id="labelWrong">Yanlış Cevap</div>
                </div>
                <div class="detail-item">
                    <div class="detail-value"><?php echo $totalQuestions; ?></div>
                    <div class="detail-label" id="labelTotal">Toplam Soru</div>
                </div>
                <div class="detail-item">
                    <div class="detail-value"><?php echo $exam['duration']; ?> dk</div>
                    <div class="detail-label" id="labelDuration">Sınav Süresi</div>
                </div>
            </div>
            
            <div class="results-summary">
                <h3 class="results-title" id="resultsTitle">📋 Detaylı Sonuçlar</h3>
                <?php foreach ($results as $index => $result): ?>
                    <div class="result-item <?php echo $result['is_correct'] ? 'correct' : 'incorrect'; ?>">
                        <div class="result-question">
                            <?php echo ($index + 1); ?>. <?php echo htmlspecialchars($result['question']); ?>
                        </div>
                        <div class="result-answer <?php echo $result['is_correct'] ? 'correct' : 'incorrect'; ?>">
                            <?php if ($result['is_correct']): ?>
                                <span class="correct-text">✅ Doğru cevap verdiniz</span>
                            <?php else: ?>
                                <span class="incorrect-text">❌ Yanlış cevap. Doğru cevap: 
                                <?php 
                                // Doğru cevabı harfe çevir
                                $correctLetter = chr($result['correct_answer'] + ord('A'));
                                $correctOption = $result['options'][$correctLetter] ?? $result['options'][$result['correct_answer']] ?? '';
                                $correctText = is_string($correctOption) ? $correctOption : ($correctOption['text'] ?? '');
                                echo htmlspecialchars($correctText);
                                ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="action-buttons">
                <a href="dashboard.php" class="action-btn btn-primary" id="btnHome">🏠 Ana Sayfaya Dön</a>
                <a href="practice_setup.php" class="action-btn btn-secondary" id="btnPractice">🚀 Alıştırma Yap</a>
            </div>
        </div>
    </div>

    <script>
        // TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'🎯 Sınav Tamamlandı', userRole:'Öğrenci', btnBack:'← Ana Sayfa',
                scoreTexts: {
                    excellent: 'Mükemmel! 🎉',
                    good: 'İyi İş! 👍',
                    average: 'Orta Seviye 📚',
                    poor: 'Daha Çok Çalışmalısın 💪'
                },
                labelCorrect:'Doğru Cevap', labelWrong:'Yanlış Cevap', labelTotal:'Toplam Soru', labelDuration:'Sınav Süresi',
                resultsTitle:'📋 Detaylı Sonuçlar', correctText:'✅ Doğru cevap verdiniz', incorrectText:'❌ Yanlış cevap. Doğru cevap:',
                btnHome:'🏠 Ana Sayfaya Dön', btnPractice:'🚀 Alıştırma Yap'
            };
            const de = {
                pageTitle:'🎯 Prüfung abgeschlossen', userRole:'Schüler', btnBack:'← Startseite',
                scoreTexts: {
                    excellent: 'Perfekt! 🎉',
                    good: 'Gute Arbeit! 👍',
                    average: 'Mittleres Niveau 📚',
                    poor: 'Mehr lernen! 💪'
                },
                labelCorrect:'Richtige Antworten', labelWrong:'Falsche Antworten', labelTotal:'Gesamt Fragen', labelDuration:'Prüfungszeit',
                resultsTitle:'📋 Detaillierte Ergebnisse', correctText:'✅ Richtige Antwort gegeben', incorrectText:'❌ Falsche Antwort. Richtige Antwort:',
                btnHome:'🏠 Zur Startseite', btnPractice:'🚀 Übung machen'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnBack', d.btnBack);
                setText('#labelCorrect', d.labelCorrect);
                setText('#labelWrong', d.labelWrong);
                setText('#labelTotal', d.labelTotal);
                setText('#labelDuration', d.labelDuration);
                setText('#resultsTitle', d.resultsTitle);
                setText('#btnHome', d.btnHome);
                setText('#btnPractice', d.btnPractice);
                
                // Score text'i güncelle
                const scoreText = document.getElementById('scoreText');
                if (scoreText) {
                    const currentText = scoreText.textContent.trim();
                    if (currentText.includes('Mükemmel') || currentText.includes('Perfekt')) {
                        scoreText.textContent = d.scoreTexts.excellent;
                    } else if (currentText.includes('İyi') || currentText.includes('Gute')) {
                        scoreText.textContent = d.scoreTexts.good;
                    } else if (currentText.includes('Orta') || currentText.includes('Mittleres')) {
                        scoreText.textContent = d.scoreTexts.average;
                    } else if (currentText.includes('Çalışmalısın') || currentText.includes('lernen')) {
                        scoreText.textContent = d.scoreTexts.poor;
                    }
                }
                
                // Result text'leri güncelle
                document.querySelectorAll('.correct-text').forEach(el => {
                    el.textContent = d.correctText;
                });
                document.querySelectorAll('.incorrect-text').forEach(el => {
                    el.textContent = d.incorrectText + ' ' + el.textContent.split(': ')[1];
                });
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_exam_submit', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_exam_submit')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_exam_submit')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>

