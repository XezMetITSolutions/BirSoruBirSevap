^b<?php
/**
 * Alıştırma Sonuçları Sayfası
 */

require_once 'config.php';

// Oturum kontrolü
if (!isset($_SESSION['practice_results']) || !isset($_SESSION['practice_finished'])) {
    header('Location: index.php');
    exit;
}

$results = $_SESSION['practice_results'];
$settings = $_SESSION['practice_settings'] ?? [];

// CSV dışa aktarma
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $csv = generateCSV($results);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="alisirma_sonuclari_' . date('Y-m-d_H-i-s') . '.csv"');
    echo $csv;
    exit;
}

function generateCSV($results) {
    $csv = "Alıştırma Sonuçları\n";
    $csv .= "Tarih," . date('Y-m-d H:i:s') . "\n";
    $csv .= "Toplam Soru," . $results['total_questions'] . "\n";
    $csv .= "Doğru Cevap," . $results['correct_answers'] . "\n";
    $csv .= "Yanlış Cevap," . $results['wrong_answers'] . "\n";
    $csv .= "Boş Cevap," . $results['unanswered'] . "\n";
    $csv .= "Toplam Puan," . $results['total_points'] . "\n";
    $csv .= "Kazanılan Puan," . $results['earned_points'] . "\n";
    $csv .= "Başarı Yüzdesi," . $results['percentage'] . "%\n";
    $csv .= "Toplam Süre," . format_time($results['time_spent']) . "\n";
    $csv .= "Ortalama Süre," . format_time($results['average_time_per_question']) . "\n\n";
    
    $csv .= "Soru Detayları\n";
    $csv .= "Soru No,Soru Metni,Kategori,Zorluk,Öğrenci Cevabı,Doğru Cevap,Doğru/Yanlış,Puan,Süre\n";
    
    foreach ($results['question_results'] as $index => $result) {
        $question = $result['question'];
        $studentAnswer = is_array($result['student_answer']) ? 
            implode(',', $result['student_answer']) : 
            ($result['student_answer'] ?? '');
        $correctAnswer = implode(',', $question['answer']);
        
        $csv .= sprintf("%d,\"%s\",%s,%d,\"%s\",\"%s\",%s,%.2f,%d\n",
            $index + 1,
            str_replace('"', '""', $question['text']),
            $question['category'],
            $question['difficulty'],
            str_replace('"', '""', $studentAnswer),
            str_replace('"', '""', $correctAnswer),
            $result['correct'] ? 'Doğru' : 'Yanlış',
            $result['points'],
            $result['time_spent']
        );
    }
    
    return $csv;
}

// Yanlış cevapları filtrele
$wrongAnswers = array_filter($results['question_results'], function($result) {
    return !$result['correct'];
});
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alıştırma Sonuçları - <?php echo APP_NAME; ?></title>
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
            max-width: 1000px;
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

        .results-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .result-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .result-card:hover {
            transform: translateY(-5px);
        }

        .result-card h3 {
            font-size: 2.5em;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .result-card p {
            color: #7f8c8d;
            font-weight: 600;
        }

        .result-card.correct h3 {
            color: #27ae60;
        }

        .result-card.incorrect h3 {
            color: #e74c3c;
        }

        .result-card.unanswered h3 {
            color: #f39c12;
        }

        .result-card.points h3 {
            color: #3498db;
        }

        .result-card.percentage h3 {
            color: #9b59b6;
        }

        .result-card.time h3 {
            color: #e67e22;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 30px;
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

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-warning {
            background: #f39c12;
        }

        .wrong-answers {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .wrong-answers h2 {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .wrong-question {
            border: 2px solid #e74c3c;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            background: rgba(231, 76, 60, 0.05);
        }

        .wrong-question h4 {
            color: #e74c3c;
            margin-bottom: 10px;
        }

        .wrong-question p {
            margin-bottom: 10px;
        }

        .wrong-answer {
            color: #e74c3c;
            font-weight: 600;
        }

        .correct-answer {
            color: #27ae60;
            font-weight: 600;
        }

        .explanation {
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid #3498db;
            border-radius: 10px;
            padding: 15px;
            margin-top: 10px;
            color: #2980b9;
        }

        .detailed-results {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .detailed-results h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8em;
        }

        .question-item {
            border: 1px solid #e1e8ed;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .question-item:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .question-item.correct {
            border-left: 5px solid #27ae60;
            background: rgba(39, 174, 96, 0.05);
        }

        .question-item.incorrect {
            border-left: 5px solid #e74c3c;
            background: rgba(231, 76, 60, 0.05);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .question-number {
            background: #667eea;
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .question-status {
            padding: 5px 12px;
            border-radius: 15px;
            font-weight: 600;
            font-size: 0.9em;
        }

        .question-status.correct {
            background: #27ae60;
            color: white;
        }

        .question-status.incorrect {
            background: #e74c3c;
            color: white;
        }

        .question-text {
            margin-bottom: 10px;
            font-weight: 500;
        }

        .question-meta {
            display: flex;
            gap: 20px;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .performance-chart {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            text-align: center;
        }

        .performance-chart h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .chart-container {
            position: relative;
            width: 200px;
            height: 200px;
            margin: 0 auto;
        }

        .chart-circle {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: conic-gradient(
                #27ae60 0deg <?php echo ($results['correct_answers'] / $results['total_questions']) * 360; ?>deg,
                #e74c3c <?php echo ($results['correct_answers'] / $results['total_questions']) * 360; ?>deg <?php echo (($results['correct_answers'] + $results['wrong_answers']) / $results['total_questions']) * 360; ?>deg,
                #f39c12 <?php echo (($results['correct_answers'] + $results['wrong_answers']) / $results['total_questions']) * 360; ?>deg 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .chart-inner {
            width: 80%;
            height: 80%;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2em;
            font-weight: 700;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .results-summary {
                grid-template-columns: 1fr 1fr;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .question-header {
                flex-direction: column;
                gap: 10px;
                align-items: flex-start;
            }
            
            .question-meta {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎉 Alıştırma Tamamlandı!</h1>
            <p>Sonuçlarınız aşağıda görüntülenmektedir</p>
        </div>

        <div class="results-summary">
            <div class="result-card correct">
                <h3><?php echo $results['correct_answers']; ?></h3>
                <p>Doğru Cevap</p>
            </div>
            <div class="result-card incorrect">
                <h3><?php echo $results['wrong_answers']; ?></h3>
                <p>Yanlış Cevap</p>
            </div>
            <div class="result-card unanswered">
                <h3><?php echo $results['unanswered']; ?></h3>
                <p>Boş Cevap</p>
            </div>
            <div class="result-card points">
                <h3><?php echo $results['earned_points']; ?>/<?php echo $results['total_points']; ?></h3>
                <p>Puan</p>
            </div>
            <div class="result-card percentage">
                <h3><?php echo $results['percentage']; ?>%</h3>
                <p>Başarı Oranı</p>
            </div>
            <div class="result-card time">
                <h3><?php echo format_time($results['time_spent']); ?></h3>
                <p>Toplam Süre</p>
            </div>
        </div>

        <div class="performance-chart">
            <h2>Performans Grafiği</h2>
            <div class="chart-container">
                <div class="chart-circle">
                    <div class="chart-inner">
                        <?php echo $results['percentage']; ?>%
                    </div>
                </div>
            </div>
            <div style="margin-top: 20px; display: flex; justify-content: center; gap: 20px;">
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 15px; height: 15px; background: #27ae60; border-radius: 3px;"></div>
                    <span>Doğru (<?php echo $results['correct_answers']; ?>)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 15px; height: 15px; background: #e74c3c; border-radius: 3px;"></div>
                    <span>Yanlış (<?php echo $results['wrong_answers']; ?>)</span>
                </div>
                <div style="display: flex; align-items: center; gap: 5px;">
                    <div style="width: 15px; height: 15px; background: #f39c12; border-radius: 3px;"></div>
                    <span>Boş (<?php echo $results['unanswered']; ?>)</span>
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="index.php" class="btn btn-secondary">Ana Sayfa</a>
            <a href="?export=csv" class="btn btn-success">CSV İndir</a>
            <?php if (!empty($wrongAnswers)): ?>
                <a href="#wrong-answers" class="btn btn-warning">Yanlışlara Dön</a>
            <?php endif; ?>
        </div>

        <?php if (!empty($wrongAnswers)): ?>
            <div class="wrong-answers" id="wrong-answers">
                <h2>❌ Yanlış Cevaplar</h2>
                <?php foreach ($wrongAnswers as $index => $result): ?>
                    <div class="wrong-question">
                        <h4>Soru <?php echo $index + 1; ?></h4>
                        <p><strong>Soru:</strong> <?php echo htmlspecialchars($result['question']['text']); ?></p>
                        <p><strong>Sizin Cevabınız:</strong> 
                            <span class="wrong-answer">
                                <?php 
                                $studentAnswer = $result['student_answer'];
                                if (is_array($studentAnswer)) {
                                    echo htmlspecialchars(implode(', ', $studentAnswer));
                                } else {
                                    echo htmlspecialchars($studentAnswer ?? 'Boş');
                                }
                                ?>
                            </span>
                        </p>
                        <p><strong>Doğru Cevap:</strong> 
                            <span class="correct-answer">
                                <?php echo htmlspecialchars(implode(', ', $result['question']['answer'])); ?>
                            </span>
                        </p>
                        <?php if (!empty($result['question']['explanation'])): ?>
                            <div class="explanation">
                                <strong>Açıklama:</strong> <?php echo htmlspecialchars($result['question']['explanation']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="detailed-results">
            <h2>📊 Detaylı Sonuçlar</h2>
            <?php foreach ($results['question_results'] as $index => $result): ?>
                <div class="question-item <?php echo $result['correct'] ? 'correct' : 'incorrect'; ?>">
                    <div class="question-header">
                        <div class="question-number">Soru <?php echo $index + 1; ?></div>
                        <div class="question-status <?php echo $result['correct'] ? 'correct' : 'incorrect'; ?>">
                            <?php echo $result['correct'] ? '✅ Doğru' : '❌ Yanlış'; ?>
                        </div>
                    </div>
                    <div class="question-text">
                        <?php echo htmlspecialchars($result['question']['text']); ?>
                    </div>
                    <div class="question-meta">
                        <span><strong>Kategori:</strong> <?php echo htmlspecialchars($result['question']['category']); ?></span>
                        <span><strong>Zorluk:</strong> <?php echo $result['question']['difficulty']; ?>/5</span>
                        <span><strong>Puan:</strong> <?php echo $result['points']; ?></span>
                        <span><strong>Süre:</strong> <?php echo format_time($result['time_spent']); ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>
