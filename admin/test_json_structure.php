<?php
/**
 * JSON Yapısını Test Et
 * Bu dosyayı tarayıcıda açarak JSON verilerinin yapısını görebilirsiniz
 */

header('Content-Type: text/html; charset=utf-8');

function readJsonFile($path) {
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

$practiceFile = __DIR__ . '/../data/practice_results.json';
$examFile = __DIR__ . '/../data/exam_results.json';

$practiceResults = readJsonFile($practiceFile);
$examResults = readJsonFile($examFile);

// burca.met1 için ilk kaydı bul
$samplePractice = null;
foreach ($practiceResults as $p) {
    if (($p['student_id'] ?? '') === 'burca.met1') {
        $samplePractice = $p;
        break;
    }
}

$sampleExam = null;
foreach ($examResults as $examCode => $results) {
    if (is_array($results)) {
        foreach ($results as $e) {
            if (($e['student_id'] ?? '') === 'burca.met1') {
                $sampleExam = $e;
                break 2;
            }
        }
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JSON Yapı Testi</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #068567;
            border-bottom: 3px solid #068567;
            padding-bottom: 10px;
        }
        h2 {
            color: #3498db;
            margin-top: 30px;
        }
        pre {
            background: #2c3e50;
            color: #ecf0f1;
            padding: 20px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 14px;
            line-height: 1.6;
        }
        .info {
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
            padding: 15px;
            margin: 20px 0;
        }
        .warning {
            background: #fff3e0;
            border-left: 4px solid #ff9800;
            padding: 15px;
            margin: 20px 0;
        }
        .error {
            background: #ffebee;
            border-left: 4px solid #f44336;
            padding: 15px;
            margin: 20px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 12px;
            text-align: left;
        }
        th {
            background: #068567;
            color: white;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔍 JSON Yapı Analizi - burca.met1</h1>
        
        <div class="info">
            <strong>📊 İstatistikler:</strong><br>
            Toplam Practice Results: <?php echo count($practiceResults); ?><br>
            Toplam Exam Results: <?php echo count($examResults); ?> exam code
        </div>

        <?php if ($samplePractice): ?>
        <h2>📝 Örnek Practice Result (burca.met1)</h2>
        <pre><?php echo json_encode($samplePractice, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
        
        <h3>🔑 Alan Analizi</h3>
        <table>
            <tr>
                <th>Alan Adı</th>
                <th>Değer</th>
                <th>Tip</th>
                <th>Açıklama</th>
            </tr>
            <?php foreach ($samplePractice as $key => $value): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($key); ?></strong></td>
                <td><?php echo is_array($value) ? 'Array[' . count($value) . ']' : htmlspecialchars(substr(json_encode($value), 0, 100)); ?></td>
                <td><?php echo gettype($value); ?></td>
                <td>
                    <?php
                    $descriptions = [
                        'student_id' => 'Kullanıcı adı (username)',
                        'student_name' => 'Öğrenci tam adı',
                        'timestamp' => 'Tarih/saat (created_at)',
                        'created_at' => 'Tarih/saat',
                        'questions' => 'Soru listesi (total_questions = count)',
                        'correct' => 'Doğru cevap sayısı',
                        'wrong' => 'Yanlış cevap sayısı',
                        'score' => 'Puan',
                        'percentage' => 'Yüzde'
                    ];
                    echo $descriptions[$key] ?? '-';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <div class="error">
            <strong>❌ Hata:</strong> burca.met1 için practice result bulunamadı!
        </div>
        <?php endif; ?>

        <?php if ($sampleExam): ?>
        <h2>📋 Örnek Exam Result (burca.met1)</h2>
        <pre><?php echo json_encode($sampleExam, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE); ?></pre>
        
        <h3>🔑 Alan Analizi</h3>
        <table>
            <tr>
                <th>Alan Adı</th>
                <th>Değer</th>
                <th>Tip</th>
                <th>Açıklama</th>
            </tr>
            <?php foreach ($sampleExam as $key => $value): ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($key); ?></strong></td>
                <td><?php echo is_array($value) ? 'Array[' . count($value) . ']' : htmlspecialchars(substr(json_encode($value), 0, 100)); ?></td>
                <td><?php echo gettype($value); ?></td>
                <td>
                    <?php
                    $descriptions = [
                        'exam_code' => 'Sınav kodu (exam_id)',
                        'student_id' => 'Kullanıcı adı (username)',
                        'student_name' => 'Öğrenci tam adı',
                        'exam_title' => 'Sınav başlığı',
                        'submit_time' => 'Teslim tarihi (created_at)',
                        'questions' => 'Soru listesi (total_questions = count)',
                        'correct' => 'Doğru cevap sayısı',
                        'wrong' => 'Yanlış cevap sayısı',
                        'score' => 'Puan',
                        'percentage' => 'Yüzde'
                    ];
                    echo $descriptions[$key] ?? '-';
                    ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php else: ?>
        <div class="error">
            <strong>❌ Hata:</strong> burca.met1 için exam result bulunamadı!
        </div>
        <?php endif; ?>

        <h2>🔧 Normalizasyon Gereksinimleri</h2>
        <div class="warning">
            <strong>⚠️ Dikkat:</strong> Aşağıdaki dönüşümler yapılmalı:
            <ul>
                <li><code>student_id</code> → <code>username</code></li>
                <li><code>timestamp</code> → <code>created_at</code></li>
                <li><code>submit_time</code> → <code>created_at</code></li>
                <li><code>exam_code</code> → <code>exam_id</code></li>
                <li><code>questions</code> (array) → <code>total_questions</code> (count)</li>
                <li><code>correct</code> → <code>correct_answers</code></li>
                <li><code>wrong</code> → <code>wrong_answers</code></li>
                <li>Eğer <code>percentage</code> yoksa hesapla</li>
            </ul>
        </div>

        <h2>📋 Tüm burca.met1 Kayıtları</h2>
        <?php
        $allPractice = array_filter($practiceResults, function($r) {
            return ($r['student_id'] ?? '') === 'burca.met1';
        });
        ?>
        <div class="info">
            <strong>Practice Results:</strong> <?php echo count($allPractice); ?> kayıt bulundu
        </div>
        
        <?php
        $allExams = [];
        foreach ($examResults as $examCode => $results) {
            if (is_array($results)) {
                foreach ($results as $e) {
                    if (($e['student_id'] ?? '') === 'burca.met1') {
                        $allExams[] = $e;
                    }
                }
            }
        }
        ?>
        <div class="info">
            <strong>Exam Results:</strong> <?php echo count($allExams); ?> kayıt bulundu
        </div>

        <h3>Practice Results Özeti</h3>
        <table>
            <tr>
                <th>#</th>
                <th>Tarih</th>
                <th>Soru Sayısı</th>
                <th>Doğru</th>
                <th>Yanlış</th>
                <th>Score</th>
            </tr>
            <?php $i = 1; foreach ($allPractice as $p): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($p['timestamp'] ?? $p['created_at'] ?? 'YOK'); ?></td>
                <td><?php echo isset($p['questions']) ? count($p['questions']) : ($p['total_questions'] ?? 'YOK'); ?></td>
                <td><?php echo $p['correct'] ?? $p['correct_answers'] ?? 'YOK'; ?></td>
                <td><?php echo $p['wrong'] ?? $p['wrong_answers'] ?? 'YOK'; ?></td>
                <td><?php echo $p['score'] ?? 'YOK'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <h3>Exam Results Özeti</h3>
        <table>
            <tr>
                <th>#</th>
                <th>Exam Code</th>
                <th>Tarih</th>
                <th>Soru Sayısı</th>
                <th>Doğru</th>
                <th>Score</th>
            </tr>
            <?php $i = 1; foreach ($allExams as $e): ?>
            <tr>
                <td><?php echo $i++; ?></td>
                <td><?php echo htmlspecialchars($e['exam_code'] ?? $e['exam_id'] ?? 'YOK'); ?></td>
                <td><?php echo htmlspecialchars($e['submit_time'] ?? $e['created_at'] ?? 'YOK'); ?></td>
                <td><?php echo isset($e['questions']) ? count($e['questions']) : ($e['total_questions'] ?? 'YOK'); ?></td>
                <td><?php echo $e['correct'] ?? $e['correct_answers'] ?? 'YOK'; ?></td>
                <td><?php echo $e['score'] ?? 'YOK'; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    </div>
</body>
</html>

