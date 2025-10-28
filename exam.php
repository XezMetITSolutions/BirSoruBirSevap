<?php
/**
 * Sınav Sayfası - Öğrenci Sınav Arayüzü
 */

require_once 'config.php';
require_once 'QuestionLoader.php';
require_once 'ExamManager.php';

// Sınav konfigürasyonunu yükle
$examConfig = null;
$mode = $_GET['mode'] ?? '';

if ($mode === 'exam' && isset($_GET['cfg'])) {
    try {
        $configData = base64_decode($_GET['cfg']);
        $examConfig = json_decode($configData, true);
        
        if (!$examConfig) {
            throw new Exception('Geçersiz sınav konfigürasyonu');
        }
    } catch (Exception $e) {
        $error = 'Sınav konfigürasyonu yüklenemedi: ' . $e->getMessage();
    }
} else {
    $error = 'Geçersiz sınav linki';
}

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();
$allQuestions = $_SESSION['all_questions'] ?? [];

// Sınav sorularını hazırla
$examQuestions = [];
if ($examConfig) {
    foreach ($examConfig['questions'] as $bank => $categories) {
        foreach ($categories as $category => $count) {
            $filteredQuestions = $questionLoader->getFilteredQuestions([
                'bank' => $bank,
                'category' => $category
            ]);
            
            if (count($filteredQuestions) >= $count) {
                $selectedQuestions = array_slice($filteredQuestions, 0, $count);
                $examQuestions = array_merge($examQuestions, $selectedQuestions);
            }
        }
    }
    
    // Soruları karıştır
    if ($examConfig['shuffleQuestions']) {
        mt_srand($examConfig['seed']);
        shuffle($examQuestions);
    }
    
    $examConfig['questions'] = $examQuestions;
}

// AJAX isteklerini işle
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'start_exam':
            if (!$examConfig) {
                echo json_encode(['success' => false, 'message' => 'Sınav konfigürasyonu bulunamadı']);
                exit;
            }
            
            $studentName = sanitize_input($_POST['student_name'] ?? '');
            $examManager = new ExamManager($examQuestions);
            $examManager->createExam($examConfig);
            $examManager->startExam($studentName);
            
            echo json_encode(['success' => true, 'redirect' => 'exam_take.php']);
            exit;
            
        case 'submit_answer':
            $questionId = sanitize_input($_POST['question_id']);
            $answer = $_POST['answer'];
            $timeSpent = (int)$_POST['time_spent'];
            
            if (!isset($_SESSION['student_answers'])) {
                $_SESSION['student_answers'] = [];
            }
            
            $_SESSION['student_answers'][$questionId] = [
                'answer' => $answer,
                'time_spent' => $timeSpent,
                'answered_at' => time()
            ];
            
            echo json_encode(['success' => true]);
            exit;
            
        case 'finish_exam':
            $examManager = new ExamManager();
            $results = $examManager->finishExam();
            $_SESSION['exam_results'] = $results;
            $_SESSION['exam_finished'] = true;
            
            echo json_encode(['success' => true, 'redirect' => 'exam_results.php']);
            exit;
    }
}

$totalQuestions = count($examQuestions);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav - <?php echo APP_NAME; ?></title>
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
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .exam-info {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .exam-info h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .info-item {
            background: rgba(102, 126, 234, 0.1);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }

        .info-item h4 {
            color: #667eea;
            font-size: 1.2em;
            margin-bottom: 5px;
        }

        .info-item p {
            color: #7f8c8d;
            font-weight: 600;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
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
            width: 100%;
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

        .error-message {
            background: #e74c3c;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .warning-message {
            background: #f39c12;
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .rules {
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid #3498db;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .rules h3 {
            color: #2980b9;
            margin-bottom: 15px;
        }

        .rules ul {
            list-style: none;
            padding-left: 0;
        }

        .rules li {
            margin-bottom: 8px;
            padding-left: 20px;
            position: relative;
        }

        .rules li:before {
            content: "•";
            color: #3498db;
            font-weight: bold;
            position: absolute;
            left: 0;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 Sınav</h1>
            <p>Lütfen sınav bilgilerini kontrol edin ve başlayın</p>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                <h3>❌ Hata</h3>
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="index.php" class="btn" style="margin-top: 15px; width: auto; display: inline-block;">Ana Sayfaya Dön</a>
            </div>
        <?php elseif ($examConfig): ?>
            <div class="exam-info">
                <h2><?php echo htmlspecialchars($examConfig['title']); ?></h2>
                
                <div class="info-grid">
                    <div class="info-item">
                        <h4>📊 Toplam Soru</h4>
                        <p><?php echo $totalQuestions; ?></p>
                    </div>
                    <div class="info-item">
                        <h4>⏱️ Süre</h4>
                        <p><?php echo $examConfig['timeLimit']; ?> dakika</p>
                    </div>
                    <div class="info-item">
                        <h4>📝 Puanlama</h4>
                        <p><?php echo $examConfig['negativeMarking'] ? 'Yanlış cevap için puan kesilir' : 'Sadece doğru cevaplar puanlanır'; ?></p>
                    </div>
                    <div class="info-item">
                        <h4>🔀 Karıştırma</h4>
                        <p><?php echo $examConfig['shuffleQuestions'] ? 'Sorular karıştırılır' : 'Sorular sıralı'; ?></p>
                    </div>
                </div>

                <div class="rules">
                    <h3>📋 Sınav Kuralları</h3>
                    <ul>
                        <li>Sınav süresi <?php echo $examConfig['timeLimit']; ?> dakikadır</li>
                        <li>Süre bittiğinde sınav otomatik olarak sona erer</li>
                        <li>Geri dönüş yapabilirsiniz</li>
                        <li>Boş bırakılan sorular puanlanmaz</li>
                        <?php if ($examConfig['negativeMarking']): ?>
                            <li>Yanlış cevaplar için puan kesilir</li>
                        <?php endif; ?>
                        <li>Sınav sırasında başka sayfalara geçmeyin</li>
                        <li>Sonuçlar sınav bitiminde gösterilir</li>
                    </ul>
                </div>

                <form id="exam-start-form">
                    <div class="form-group">
                        <label for="student-name">Adınız (İsteğe bağlı):</label>
                        <input type="text" id="student-name" name="student_name" 
                               placeholder="Adınızı girin...">
                    </div>

                    <div class="warning-message">
                        <strong>⚠️ Önemli:</strong> Sınavı başlattıktan sonra sayfayı yenilemeyin veya kapatmayın. 
                        Bu durumda sınavınız kaybolabilir.
                    </div>

                    <button type="submit" class="btn">🚀 Sınavı Başlat</button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.getElementById('exam-start-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const studentName = document.getElementById('student-name').value.trim();
            
            // Loading göster
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.textContent;
            submitBtn.textContent = 'Başlatılıyor...';
            submitBtn.disabled = true;

            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'start_exam',
                    student_name: studentName
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.href = data.redirect;
                } else {
                    alert('Hata: ' + data.message);
                    submitBtn.textContent = originalText;
                    submitBtn.disabled = false;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu');
                submitBtn.textContent = originalText;
                submitBtn.disabled = false;
            });
        });
    </script>
</body>
</html>
