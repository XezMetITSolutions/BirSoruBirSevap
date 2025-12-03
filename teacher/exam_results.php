<?php
/**
 * Öğretmen - Sınav Sonuçları
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$examCode = $_GET['exam_code'] ?? '';
$user = $auth->getUser();
$teacherBranch = $user['branch'] ?? $user['institution'] ?? '';

// Sınav bilgilerini al
$exam = null;
$results = [];

require_once '../database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

if (!empty($examCode)) {
    try {
        // Sınav bilgilerini veritabanından çek
        $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = :exam_id");
        $stmt->execute([':exam_id' => $examCode]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exam) {
            // Sınav sonuçlarını veritabanından çek
            $stmt = $conn->prepare("SELECT * FROM exam_results WHERE exam_id = :exam_id ORDER BY score DESC");
            $stmt->execute([':exam_id' => $examCode]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // JSON alanları decode et (gerekirse)
            foreach ($results as &$res) {
                if (isset($res['answers']) && is_string($res['answers'])) {
                    $res['answers'] = json_decode($res['answers'], true);
                }
            }
        }
    } catch (Exception $e) {
        error_log("Teacher exam results error: " . $e->getMessage());
        $exam = null;
    }
}

if (!$exam) {
    header('Location: exams.php');
    exit;
}

// İstatistikleri hesapla
$totalParticipants = count($results);
$totalScore = array_sum(array_column($results, 'score'));
$averageScore = $totalParticipants > 0 ? round($totalScore / $totalParticipants, 1) : 0;
$maxScore = $totalParticipants > 0 ? max(array_column($results, 'score')) : 0;
$minScore = $totalParticipants > 0 ? min(array_column($results, 'score')) : 0;

// Puan dağılımını hesapla
$scoreDistribution = [
    '0-20' => 0,
    '21-40' => 0,
    '41-60' => 0,
    '61-80' => 0,
    '81-100' => 0
];

foreach ($results as $result) {
    $score = $result['score'];
    if ($score <= 20) $scoreDistribution['0-20']++;
    elseif ($score <= 40) $scoreDistribution['21-40']++;
    elseif ($score <= 60) $scoreDistribution['41-60']++;
    elseif ($score <= 80) $scoreDistribution['61-80']++;
    else $scoreDistribution['81-100']++;
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Sonuçları - <?php echo htmlspecialchars($exam['title']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            font-size: 1.8em;
            font-weight: bold;
            color: #667eea;
        }
        
        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        .back-btn {
            background: #667eea;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .back-btn:hover {
            background: #5a6fd8;
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .header { padding: 14px 0; }
            .header-content { padding: 0 12px; gap: 10px; }
            .logo { font-size: 1.4em; }
        }
        
        .container {
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 30px;
            color: white;
        }
        
        .page-title {
            font-size: 2.5em;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
        }
        
        .page-subtitle {
            font-size: 1.2em;
            opacity: 0.9;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card h3 {
            font-size: 2.5em;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .stat-card p {
            color: #666;
            font-weight: 600;
        }
        
        .card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.8em;
        }
        
        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .results-table th,
        .results-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #333;
        }
        
        .results-table tr:hover {
            background: #f8f9fa;
        }
        
        .score-badge {
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.9em;
        }
        
        .score-excellent { background: #d4edda; color: #155724; }
        .score-good { background: #d1ecf1; color: #0c5460; }
        .score-average { background: #fff3cd; color: #856404; }
        .score-poor { background: #f8d7da; color: #721c24; }
        
        .distribution-chart {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        
        .distribution-bar {
            background: #667eea;
            color: white;
            padding: 10px;
            border-radius: 5px;
            text-align: center;
            font-weight: 600;
        }
        
        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-results h3 {
            font-size: 1.5em;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo" id="logoTitle">📊 Sınav Sonuçları</div>
            <div class="user-info">
                <div>
                    <div style="font-weight: 600; color: #333;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: #666;" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 8px; background: rgba(102,126,234,0.2); border: 1px solid rgba(102,126,234,0.3);">DE</button>
                <a href="exams.php" class="back-btn" id="btnBack">← <span id="backText">Sınavlara Dön</span></a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="pageTitle">📊 <?php echo htmlspecialchars($exam['title']); ?></h1>
            <p class="page-subtitle" id="pageSubtitle">Sınav Sonuçları ve İstatistikler</p>
        </div>


        <?php if ($totalParticipants > 0): ?>
            <!-- İstatistikler -->
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php echo $totalParticipants; ?></h3>
                    <p id="stat1">Toplam Katılımcı</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $averageScore; ?>%</h3>
                    <p id="stat2">Ortalama Puan</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $maxScore; ?>%</h3>
                    <p id="stat3">En Yüksek Puan</p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $minScore; ?>%</h3>
                    <p id="stat4">En Düşük Puan</p>
                </div>
            </div>

            <!-- Puan Dağılımı -->
            <div class="card">
                <h2 id="distTitle">📈 Puan Dağılımı</h2>
                <div class="distribution-chart">
                    <?php foreach ($scoreDistribution as $range => $count): ?>
                        <div class="distribution-bar" style="height: <?php echo max(20, ($count / max($scoreDistribution)) * 100); ?>px;">
                            <div><?php echo $range; ?></div>
                            <div><?php echo $count; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Detaylı Sonuçlar -->
            <div class="card">
                <h2 id="detailTitle">👥 Detaylı Sonuçlar</h2>
                <table class="results-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th id="thStudent">Öğrenci Adı</th>
                            <th id="thScore">Puan</th>
                            <th id="thCorrect">Doğru</th>
                            <th id="thWrong">Yanlış</th>
                            <th id="thEmpty">Boş</th>
                            <th id="thDuration">Süre</th>
                            <th id="thDate">Tarih</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $counter = 1;
                        foreach ($results as $index => $result): 
                            $score = $result['score'];
                            $scoreClass = '';
                            if ($score >= 80) $scoreClass = 'score-excellent';
                            elseif ($score >= 60) $scoreClass = 'score-good';
                            elseif ($score >= 40) $scoreClass = 'score-average';
                            else $scoreClass = 'score-poor';
                        ?>
                            <tr class="result-row" data-index="<?php echo $index; ?>" style="cursor: pointer;">
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($result['student_name'] ?? 'Bilinmiyor'); ?></td>
                                <td>
                                    <span class="score-badge <?php echo $scoreClass; ?>">
                                        <?php echo $score; ?>%
                                    </span>
                                </td>
                                <td><?php echo $result['correct'] ?? 0; ?></td>
                                <td><?php echo $result['wrong'] ?? 0; ?></td>
                                <td><?php echo $result['empty'] ?? 0; ?></td>
                                <td><?php echo $result['duration'] ?? 'N/A'; ?></td>
                                <td><?php echo $result['completed_at'] ?? 'N/A'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="no-results">
                    <h3>📭 Henüz Sonuç Yok</h3>
                    <p>Bu sınava henüz katılım olmamış.</p>
                    <p>Öğrenciler sınava girdikten sonra sonuçlar burada görünecek.</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Detaylı Sonuç Modalı -->
    <div id="detailModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📋 Detaylı Sınav Sonuçları</h2>
                <span class="close">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Detaylı sonuçlar buraya yüklenecek -->
            </div>
        </div>
    </div>

    <script>
        // Modal elementleri
        const modal = document.getElementById('detailModal');
        const modalBody = document.getElementById('modalBody');
        const closeBtn = document.querySelector('.close');
        
        // Sonuç verilerini PHP'den al
        const results = <?php echo json_encode($results); ?>;
        
        // Satır tıklama olayı
        document.querySelectorAll('.result-row').forEach(row => {
            row.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                showDetailModal(index);
            });
        });
        
        // Modal kapatma
        closeBtn.addEventListener('click', closeModal);
        window.addEventListener('click', function(event) {
            if (event.target === modal) {
                closeModal();
            }
        });
        
        // Detaylı sonuç modalını göster
        function showDetailModal(index) {
            const result = results[index];
            if (!result) return;
            
            let html = `
                <div class="student-info">
                    <h3>👤 ${result.student_name || 'Bilinmeyen'}</h3>
                    <div class="score-summary">
                        <div class="score-item">
                            <span class="label">Puan:</span>
                            <span class="value">${result.score}%</span>
                        </div>
                        <div class="score-item">
                            <span class="label">Doğru:</span>
                            <span class="value">${result.correct || 0}</span>
                        </div>
                        <div class="score-item">
                            <span class="label">Yanlış:</span>
                            <span class="value">${result.wrong || 0}</span>
                        </div>
                        <div class="score-item">
                            <span class="label">Süre:</span>
                            <span class="value">${result.duration || 'N/A'}</span>
                        </div>
                    </div>
                </div>
            `;
            
            // Detaylı sonuçlar varsa göster
            if (result.detailed_results && result.detailed_results.length > 0) {
                html += '<div class="detailed-questions"><h4>📝 Soru Detayları</h4>';
                
                result.detailed_results.forEach((question, qIndex) => {
                    const isCorrect = question.is_correct;
                    const userAnswer = question.user_answer;
                    const correctAnswer = question.correct_answer;
                    
                    // Kullanıcı cevabını harfe çevir
                    const userLetter = userAnswer !== null ? String.fromCharCode(65 + userAnswer) : 'Boş';
                    const correctLetter = String.fromCharCode(65 + correctAnswer);
                    
                    html += `
                        <div class="question-detail ${isCorrect ? 'correct' : 'incorrect'}">
                            <div class="question-header">
                                <span class="question-number">${qIndex + 1}.</span>
                                <span class="question-status">${isCorrect ? '✅ Doğru' : '❌ Yanlış'}</span>
                            </div>
                            <div class="question-text">${question.question}</div>
                            <div class="answer-info">
                                <div class="user-answer">
                                    <strong>Öğrenci Cevabı:</strong> ${userLetter}
                                </div>
                                <div class="correct-answer">
                                    <strong>Doğru Cevap:</strong> ${correctLetter}
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
            } else {
                html += '<div class="no-details" id="noDetails">Detaylı sonuç bilgisi mevcut değil.</div>';
            }
            
            modalBody.innerHTML = html;
            modal.style.display = 'block';
        }
        
        // Modalı kapat
        function closeModal() {
            modal.style.display = 'none';
        }
    </script>

    <script>
        (function(){
            const tr = {
                logoTitle:'📊 Sınav Sonuçları', userRole:'Eğitmen', backText:'Sınavlara Dön',
                pageSubtitle:'Sınav Sonuçları ve İstatistikler',
                stat1:'Toplam Katılımcı', stat2:'Ortalama Puan', stat3:'En Yüksek Puan', stat4:'En Düşük Puan',
                distTitle:'📈 Puan Dağılımı', detailTitle:'👥 Detaylı Sonuçlar',
                thStudent:'Öğrenci Adı', thScore:'Puan', thCorrect:'Doğru', thWrong:'Yanlış', thEmpty:'Boş', thDuration:'Süre', thDate:'Tarih',
                noDetails:'Detaylı sonuç bilgisi mevcut değil.'
            };
            const de = {
                logoTitle:'📊 Prüfungsergebnisse', userRole:'Lehrpersonal', backText:'Zu den Prüfungen',
                pageSubtitle:'Prüfungsergebnisse und Statistiken',
                stat1:'Gesamt Teilnehmende', stat2:'Durchschnittspunktzahl', stat3:'Höchste Punktzahl', stat4:'Niedrigste Punktzahl',
                distTitle:'📈 Punkteverteilung', detailTitle:'👥 Detaillierte Ergebnisse',
                thStudent:'Schülername', thScore:'Punkte', thCorrect:'Richtig', thWrong:'Falsch', thEmpty:'Leer', thDuration:'Zeit', thDate:'Datum',
                noDetails:'Keine detaillierten Ergebnisse verfügbar.'
            };
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#logoTitle', d.logoTitle);
                setText('#userRole', d.userRole);
                setText('#backText', d.backText);
                setText('#pageSubtitle', d.pageSubtitle);
                setText('#stat1', d.stat1);
                setText('#stat2', d.stat2);
                setText('#stat3', d.stat3);
                setText('#stat4', d.stat4);
                setText('#distTitle', d.distTitle);
                setText('#detailTitle', d.detailTitle);
                setText('#thStudent', d.thStudent);
                setText('#thScore', d.thScore);
                setText('#thCorrect', d.thCorrect);
                setText('#thWrong', d.thWrong);
                setText('#thEmpty', d.thEmpty);
                setText('#thDuration', d.thDuration);
                setText('#thDate', d.thDate);
                setText('#noDetails', d.noDetails);
                const toggle=document.getElementById('langToggle'); if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_teacher_exam_results', lang);
            }
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_teacher_exam_results')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ toggle.addEventListener('click', function(){ const next=(localStorage.getItem('lang_teacher_exam_results')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; apply(next); }); }
            });
        })();
    </script>

    <style>
        /* Modal Stilleri */
        .modal {
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 0;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            animation: slideIn 0.3s ease;
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 30px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 1.5em;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.3s ease;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .student-info {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }
        
        .student-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.8em;
        }
        
        .score-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }
        
        .score-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .score-item .label {
            display: block;
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 5px;
        }
        
        .score-item .value {
            display: block;
            color: #2c3e50;
            font-size: 1.2em;
            font-weight: bold;
        }
        
        .detailed-questions h4 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.3em;
        }
        
        .question-detail {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 5px solid #e9ecef;
        }
        
        .question-detail.correct {
            border-left-color: #28a745;
            background: #d4edda;
        }
        
        .question-detail.incorrect {
            border-left-color: #dc3545;
            background: #f8d7da;
        }
        
        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .question-number {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .question-status {
            font-weight: bold;
            font-size: 0.9em;
        }
        
        .question-text {
            color: #2c3e50;
            margin-bottom: 15px;
            line-height: 1.5;
        }
        
        .answer-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .user-answer, .correct-answer {
            padding: 10px;
            border-radius: 5px;
            background: white;
        }
        
        .user-answer {
            border: 1px solid #dc3545;
        }
        
        .correct-answer {
            border: 1px solid #28a745;
        }
        
        .no-details {
            text-align: center;
            color: #6c757d;
            padding: 40px;
            font-style: italic;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @media (max-width: 768px) {
            .modal-content {
                width: 95%;
                margin: 10% auto;
            }
            
            .answer-info {
                grid-template-columns: 1fr;
            }
            
            .score-summary {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</body>
</html>
