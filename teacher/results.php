<?php
/**
 * Öğretmen - Sonuçlar
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

// Örnek sonuç verileri
$examResults = [
    [
        'id' => 1,
        'exam_name' => 'Matematik Sınavı 1',
        'student_name' => 'Ahmet Yılmaz',
        'student_number' => '2024001',
        'score' => 85,
        'total_questions' => 20,
        'correct_answers' => 17,
        'wrong_answers' => 3,
        'completion_time' => '35:42',
        'submitted_at' => '2024-01-15 14:30:00',
        'status' => 'completed'
    ],
    [
        'id' => 2,
        'exam_name' => 'Matematik Sınavı 1',
        'student_name' => 'Ayşe Demir',
        'student_number' => '2024002',
        'score' => 92,
        'total_questions' => 20,
        'correct_answers' => 18,
        'wrong_answers' => 2,
        'completion_time' => '28:15',
        'submitted_at' => '2024-01-15 14:25:00',
        'status' => 'completed'
    ],
    [
        'id' => 3,
        'exam_name' => 'Fen Bilgisi Testi',
        'student_name' => 'Mehmet Kaya',
        'student_number' => '2024003',
        'score' => 78,
        'total_questions' => 15,
        'correct_answers' => 12,
        'wrong_answers' => 3,
        'completion_time' => '25:30',
        'submitted_at' => '2024-01-14 16:45:00',
        'status' => 'completed'
    ],
    [
        'id' => 4,
        'exam_name' => 'Matematik Sınavı 1',
        'student_name' => 'Fatma Özkan',
        'student_number' => '2024004',
        'score' => 0,
        'total_questions' => 20,
        'correct_answers' => 0,
        'wrong_answers' => 0,
        'completion_time' => '00:00',
        'submitted_at' => null,
        'status' => 'not_started'
    ]
];

// Sınav bazında istatistikler
$examStats = [
    'Matematik Sınavı 1' => [
        'total_students' => 3,
        'completed' => 2,
        'not_started' => 1,
        'average_score' => 88.5,
        'highest_score' => 92,
        'lowest_score' => 85
    ],
    'Fen Bilgisi Testi' => [
        'total_students' => 1,
        'completed' => 1,
        'not_started' => 0,
        'average_score' => 78,
        'highest_score' => 78,
        'lowest_score' => 78
    ]
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sonuçlar - Öğretmen</title>
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

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .nav-breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .nav-breadcrumb a {
            color: #667eea;
            text-decoration: none;
            margin-right: 10px;
        }

        .nav-breadcrumb a:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            text-align: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stat-card p {
            color: #7f8c8d;
            font-size: 1.1rem;
            font-weight: 500;
        }

        .stat-card .icon {
            font-size: 3rem;
            margin-bottom: 15px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8rem;
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
            border-bottom: 1px solid #e1e8ed;
        }

        .results-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .results-table tr:hover {
            background: #f8f9fa;
        }

        .score-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .score-excellent {
            background: #d4edda;
            color: #155724;
        }

        .score-good {
            background: #cce5ff;
            color: #004085;
        }

        .score-average {
            background: #fff3cd;
            color: #856404;
        }

        .score-poor {
            background: #f8d7da;
            color: #721c24;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-not_started {
            background: #f8d7da;
            color: #721c24;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn-secondary:hover {
            box-shadow: 0 8px 25px rgba(149, 165, 166, 0.3);
        }

        .export-buttons {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }

        .exam-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .exam-stat-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            border-left: 4px solid #667eea;
        }

        .exam-stat-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .exam-stat-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }

        .exam-stat-item:last-child {
            margin-bottom: 0;
        }

        .exam-stat-label {
            color: #7f8c8d;
        }

        .exam-stat-value {
            font-weight: 600;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .header { padding: 20px; }
            .header h1 { font-size: 1.6rem; }
            .export-buttons { flex-direction: column; }
            .btn { padding: 10px 18px; border-radius: 12px; }
        }
        @media (max-width: 420px) {
            .header { padding: 16px; }
            .header h1 { font-size: 1.35rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 id="mainTitle">📊 Sonuçlar</h1>
            <p id="mainSubtitle">Öğrenci sınav sonuçlarını görüntüleyin ve analiz edin</p>
            <div style="margin-top:10px;">
                <button id="langToggle" class="btn btn-secondary" style="padding:8px 14px;border-radius:20px;">DE</button>
            </div>
        </div>

        <div class="nav-breadcrumb">
            <a href="dashboard.php" id="btnDashboard">Dashboard</a> > <span id="breadcrumbCurrent">Sonuçlar</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">📝</div>
                <h3><?php echo count($examResults); ?></h3>
                <p id="statLabel1">Toplam Sonuç</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">✅</div>
                <h3><?php echo count(array_filter($examResults, fn($r) => $r['status'] === 'completed')); ?></h3>
                <p id="statLabel2">Tamamlanan</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">📈</div>
                <h3><?php 
                    $completedResults = array_filter($examResults, fn($r) => $r['status'] === 'completed');
                    $avgScore = count($completedResults) > 0 ? round(array_sum(array_column($completedResults, 'score')) / count($completedResults), 1) : 0;
                    echo $avgScore;
                ?>%</h3>
                <p id="statLabel3">Ortalama Puan</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">⏱️</div>
                <h3><?php 
                    $completedResults = array_filter($examResults, fn($r) => $r['status'] === 'completed');
                    $avgTime = count($completedResults) > 0 ? round(array_sum(array_map(fn($r) => strtotime($r['completion_time'] . ':00'), $completedResults)) / count($completedResults) / 60, 1) : 0;
                    echo $avgTime;
                ?> <span id="minutesUnit">dk</span></h3>
                <p id="statLabel4">Ortalama Süre</p>
            </div>
        </div>

        <div class="exam-stats">
            <?php foreach ($examStats as $examName => $stats): ?>
                <div class="exam-stat-card">
                    <h3><?php echo htmlspecialchars($examName); ?></h3>
                    <div class="exam-stat-item">
                        <span class="exam-stat-label" id="labelTotalStudents">Toplam Öğrenci:</span>
                        <span class="exam-stat-value"><?php echo $stats['total_students']; ?></span>
                    </div>
                    <div class="exam-stat-item">
                        <span class="exam-stat-label" id="labelCompleted">Tamamlayan:</span>
                        <span class="exam-stat-value"><?php echo $stats['completed']; ?></span>
                    </div>
                    <div class="exam-stat-item">
                        <span class="exam-stat-label" id="labelAverageScore">Ortalama Puan:</span>
                        <span class="exam-stat-value"><?php echo $stats['average_score']; ?>%</span>
                    </div>
                    <div class="exam-stat-item">
                        <span class="exam-stat-label" id="labelHighestScore">En Yüksek:</span>
                        <span class="exam-stat-value"><?php echo $stats['highest_score']; ?>%</span>
                    </div>
                    <div class="exam-stat-item">
                        <span class="exam-stat-label" id="labelLowestScore">En Düşük:</span>
                        <span class="exam-stat-value"><?php echo $stats['lowest_score']; ?>%</span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <h2 id="detailedResultsTitle">📋 Detaylı Sonuçlar</h2>
            <table class="results-table">
                <thead>
                    <tr>
                        <th id="thExam">Sınav</th>
                        <th id="thStudent">Öğrenci</th>
                        <th id="thNumber">Numara</th>
                        <th id="thScore">Puan</th>
                        <th id="thCorrect">Doğru</th>
                        <th id="thWrong">Yanlış</th>
                        <th id="thTime">Süre</th>
                        <th id="thStatus">Durum</th>
                        <th id="thDate">Tarih</th>
                        <th id="thActions">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($examResults as $result): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($result['exam_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['student_name']); ?></td>
                            <td><?php echo htmlspecialchars($result['student_number']); ?></td>
                            <td>
                                <span class="score-badge <?php
                                    if ($result['score'] >= 90) echo 'score-excellent';
                                    elseif ($result['score'] >= 80) echo 'score-good';
                                    elseif ($result['score'] >= 70) echo 'score-average';
                                    else echo 'score-poor';
                                ?>">
                                    <?php echo $result['score']; ?>%
                                </span>
                            </td>
                            <td><?php echo $result['correct_answers']; ?></td>
                            <td><?php echo $result['wrong_answers']; ?></td>
                            <td><?php echo $result['completion_time']; ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $result['status']; ?>">
                                    <?php echo $result['status'] === 'completed' ? '<span id="statusCompleted">Tamamlandı</span>' : '<span id="statusNotStarted">Başlanmadı</span>'; ?>
                                </span>
                            </td>
                            <td><?php echo $result['submitted_at'] ? date('d.m.Y H:i', strtotime($result['submitted_at'])) : '-'; ?></td>
                            <td>
                                <?php if ($result['status'] === 'completed'): ?>
                                    <a href="result_details.php?id=<?php echo $result['id']; ?>" class="btn" style="padding: 8px 12px; font-size: 0.8rem;" id="btnDetail">
                                        Detay
                                    </a>
                                <?php else: ?>
                                    <span style="color: #7f8c8d; font-size: 0.8rem;">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div class="export-buttons">
                <button class="btn" id="btnExportExcel">Excel Olarak İndir</button>
                <button class="btn btn-secondary" id="btnExportPDF">PDF Olarak İndir</button>
                <button class="btn btn-secondary" id="btnExportCSV">CSV Olarak İndir</button>
            </div>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                mainTitle:'📊 Sonuçlar', mainSubtitle:'Öğrenci sınav sonuçlarını görüntüleyin ve analiz edin',
                btnDashboard:'Dashboard', breadcrumbCurrent:'Sonuçlar',
                statLabel1:'Toplam Sonuç', statLabel2:'Tamamlanan', statLabel3:'Ortalama Puan', statLabel4:'Ortalama Süre',
                minutesUnit:'dk',
                labelTotalStudents:'Toplam Öğrenci:', labelCompleted:'Tamamlayan:', labelAverageScore:'Ortalama Puan:',
                labelHighestScore:'En Yüksek:', labelLowestScore:'En Düşük:',
                detailedResultsTitle:'📋 Detaylı Sonuçlar',
                thExam:'Sınav', thStudent:'Öğrenci', thNumber:'Numara', thScore:'Puan', thCorrect:'Doğru', thWrong:'Yanlış',
                thTime:'Süre', thStatus:'Durum', thDate:'Tarih', thActions:'İşlemler',
                statusCompleted:'Tamamlandı', statusNotStarted:'Başlanmadı', btnDetail:'Detay',
                btnExportExcel:'Excel Olarak İndir', btnExportPDF:'PDF Olarak İndir', btnExportCSV:'CSV Olarak İndir'
            };
            const de = {
                mainTitle:'📊 Ergebnisse', mainSubtitle:'Zeigen Sie Schülerprüfungsergebnisse an und analysieren Sie sie',
                btnDashboard:'Dashboard', breadcrumbCurrent:'Ergebnisse',
                statLabel1:'Gesamt Ergebnisse', statLabel2:'Abgeschlossen', statLabel3:'Durchschnittspunktzahl', statLabel4:'Durchschnittszeit',
                minutesUnit:'Min',
                labelTotalStudents:'Gesamt Schüler:', labelCompleted:'Abgeschlossen:', labelAverageScore:'Durchschnittspunktzahl:',
                labelHighestScore:'Höchste:', labelLowestScore:'Niedrigste:',
                detailedResultsTitle:'📋 Detaillierte Ergebnisse',
                thExam:'Prüfung', thStudent:'Schüler', thNumber:'Nummer', thScore:'Punkte', thCorrect:'Richtig', thWrong:'Falsch',
                thTime:'Zeit', thStatus:'Status', thDate:'Datum', thActions:'Aktionen',
                statusCompleted:'Abgeschlossen', statusNotStarted:'Nicht begonnen', btnDetail:'Details',
                btnExportExcel:'Als Excel herunterladen', btnExportPDF:'Als PDF herunterladen', btnExportCSV:'Als CSV herunterladen'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#btnDashboard', d.btnDashboard);
                setText('#breadcrumbCurrent', d.breadcrumbCurrent);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#minutesUnit', d.minutesUnit);
                setText('#labelTotalStudents', d.labelTotalStudents);
                setText('#labelCompleted', d.labelCompleted);
                setText('#labelAverageScore', d.labelAverageScore);
                setText('#labelHighestScore', d.labelHighestScore);
                setText('#labelLowestScore', d.labelLowestScore);
                setText('#detailedResultsTitle', d.detailedResultsTitle);
                setText('#thExam', d.thExam);
                setText('#thStudent', d.thStudent);
                setText('#thNumber', d.thNumber);
                setText('#thScore', d.thScore);
                setText('#thCorrect', d.thCorrect);
                setText('#thWrong', d.thWrong);
                setText('#thTime', d.thTime);
                setText('#thStatus', d.thStatus);
                setText('#thDate', d.thDate);
                setText('#thActions', d.thActions);
                setText('#statusCompleted', d.statusCompleted);
                setText('#statusNotStarted', d.statusNotStarted);
                setText('#btnDetail', d.btnDetail);
                setText('#btnExportExcel', d.btnExportExcel);
                setText('#btnExportPDF', d.btnExportPDF);
                setText('#btnExportCSV', d.btnExportCSV);
                
                localStorage.setItem('lang_results_teacher', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_results_teacher')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){
                    toggle.textContent = (lang==='de'?'TR':'DE');
                    toggle.addEventListener('click', function(){
                        const next = (localStorage.getItem('lang_results_teacher')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr';
                        apply(next);
                        toggle.textContent = (next==='de'?'TR':'DE');
                    });
                }
            });
        })();
    </script>
</body>
</html>
