<?php
/**
 * Öğretmen - Analitik
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

// Gerçek analitik verilerini hesapla
$user = $auth->getUser();
$teacherBranch = $user['branch'] ?? $user['institution'] ?? '';

// Sınav verilerini yükle
$allExams = [];
if (file_exists('../data/exams.json')) {
    $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
}

// Sadece kendi kurumundaki sınavları filtrele
$teacherExams = array_filter($allExams, function($exam) use ($teacherBranch, $auth) {
    return $auth->hasRole('superadmin') || ($exam['class_section'] ?? '') === $teacherBranch;
});

// Sınav sonuçlarını yükle
$allResults = [];
if (file_exists('../data/exam_results.json')) {
    $allResults = json_decode(file_get_contents('../data/exam_results.json'), true) ?? [];
}

// İstatistikleri hesapla
$totalExams = count($teacherExams);
$totalQuestions = array_sum(array_column($teacherExams, 'question_count'));
$totalStudents = 0;
$totalScore = 0;
$totalAttempts = 0;

foreach ($allResults as $examCode => $results) {
    if (isset($teacherExams[$examCode])) {
        $totalStudents += count($results);
        foreach ($results as $result) {
            $totalScore += $result['score'] ?? 0;
            $totalAttempts++;
        }
    }
}

$averageScore = $totalAttempts > 0 ? round($totalScore / $totalAttempts, 1) : 0;
$completionRate = $totalStudents > 0 ? round(($totalAttempts / $totalStudents) * 100, 1) : 0;

$analytics = [
    'overview' => [
        'total_exams' => $totalExams,
        'total_questions' => $totalQuestions,
        'total_students' => $totalStudents,
        'average_score' => $averageScore,
        'completion_rate' => $completionRate
    ],
    'performance_trends' => [],
    'question_analysis' => [],
    'student_performance' => []
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analitik - Öğretmen</title>
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
        .lang-toggle { background: rgba(102,126,234,0.15); border: 1px solid rgba(102,126,234,0.3); color:#2c3e50; padding:8px 14px; border-radius:20px; cursor:pointer; }

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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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

        .chart-placeholder {
            height: 300px;
            background: #f8f9fa;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
            font-size: 1.1rem;
            margin: 20px 0;
        }

        .question-analysis {
            margin-top: 20px;
        }

        .question-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
        }

        .question-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .question-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .question-stat {
            text-align: center;
        }

        .question-stat h5 {
            font-size: 1.5rem;
            color: #667eea;
            margin-bottom: 5px;
        }

        .question-stat p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .difficulty-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .difficulty-easy {
            background: #d4edda;
            color: #155724;
        }

        .difficulty-medium {
            background: #fff3cd;
            color: #856404;
        }

        .difficulty-hard {
            background: #f8d7da;
            color: #721c24;
        }

        .student-performance {
            margin-top: 20px;
        }

        .student-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #27ae60;
        }

        .student-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .student-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .student-stat {
            text-align: center;
        }

        .student-stat h5 {
            font-size: 1.5rem;
            color: #27ae60;
            margin-bottom: 5px;
        }

        .student-stat p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .improvement {
            color: #27ae60;
            font-weight: 600;
        }

        .strengths-weaknesses {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 15px;
        }

        .strengths, .weaknesses {
            padding: 15px;
            border-radius: 10px;
        }

        .strengths {
            background: #d4edda;
            color: #155724;
        }

        .weaknesses {
            background: #f8d7da;
            color: #721c24;
        }

        .strengths h5, .weaknesses h5 {
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .strengths ul, .weaknesses ul {
            list-style: none;
            padding: 0;
        }

        .strengths li, .weaknesses li {
            padding: 5px 0;
            font-size: 0.9rem;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .no-data h3 {
            font-size: 1.5rem;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        .no-data p {
            font-size: 1.1rem;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .header { padding: 20px; }
            .header h1 { font-size: 1.6rem; }
            .lang-toggle { padding: 6px 10px; border-radius: 12px; }
            .stats-grid { grid-template-columns: 1fr; }
            .strengths-weaknesses { grid-template-columns: 1fr; }
        }
        @media (max-width: 420px) {
            .header { padding: 16px; }
            .header h1 { font-size: 1.35rem; }
            .lang-toggle { padding: 5px 8px; font-size: .85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 id="mainTitle">📈 Analitik</h1>
            <p id="mainSubtitle">Performans analizi ve detaylı istatistikler</p>
            <button id="langToggle" class="lang-toggle">DE</button>
        </div>

        <div class="nav-breadcrumb">
            <a href="dashboard.php" id="breadcrumbHome">Dashboard</a> > <span id="breadcrumbCurrent">Analitik</span>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="icon">📝</div>
                <h3><?php echo $analytics['overview']['total_exams']; ?></h3>
                <p id="statLabel1">Toplam Sınav</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">❓</div>
                <h3><?php echo $analytics['overview']['total_questions']; ?></h3>
                <p id="statLabel2">Toplam Soru</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">👥</div>
                <h3><?php echo $analytics['overview']['total_students']; ?></h3>
                <p id="statLabel3">Toplam Öğrenci</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">📊</div>
                <h3><?php echo $analytics['overview']['average_score']; ?>%</h3>
                <p id="statLabel4">Ortalama Puan</p>
            </div>
            
            <div class="stat-card">
                <div class="icon">✅</div>
                <h3><?php echo $analytics['overview']['completion_rate']; ?>%</h3>
                <p id="statLabel5">Tamamlama Oranı</p>
            </div>
        </div>

        <?php if ($totalExams > 0): ?>
            <div class="card">
                <h2 id="trendTitle">📈 Performans Trendi</h2>
                <div class="chart-placeholder">
                    <span id="trendPlaceholder">📊 Performans grafiği burada görüntülenecek</span>
                </div>
            </div>

            <div class="card">
                <h2 id="questionTitle">❓ Soru Analizi</h2>
                <div class="question-analysis">
                    <?php if (!empty($analytics['question_analysis'])): ?>
                        <?php foreach ($analytics['question_analysis'] as $question): ?>
                            <div class="question-item">
                                <h4><?php echo htmlspecialchars($question['question_text']); ?></h4>
                                <span class="difficulty-badge difficulty-<?php echo $question['difficulty']; ?>">
                                    <?php echo ucfirst($question['difficulty']); ?>
                                </span>
                                
                                <div class="question-stats">
                                    <div class="question-stat">
                                        <h5><?php echo $question['correct_rate']; ?>%</h5>
                                        <p id="labelCorrectRate">Doğru Cevap Oranı</p>
                                    </div>
                                    <div class="question-stat">
                                        <h5><?php echo $question['total_attempts']; ?></h5>
                                        <p id="labelAttempts">Toplam Deneme</p>
                                    </div>
                                    <div class="question-stat">
                                        <h5><?php echo $question['correct_attempts']; ?></h5>
                                        <p id="labelCorrect">Doğru Cevap</p>
                                    </div>
                                    <div class="question-stat">
                                        <h5><?php echo $question['total_attempts'] - $question['correct_attempts']; ?></h5>
                                        <p id="labelWrong">Yanlış Cevap</p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <h3 id="noQA">📭 Henüz Soru Analizi Yok</h3>
                            <p id="noQADesc">Sınavlar tamamlandıktan sonra soru analizi burada görünecek.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card">
                <h2 id="studentPerfTitle">👥 Öğrenci Performansı</h2>
                <div class="student-performance">
                    <?php if (!empty($analytics['student_performance'])): ?>
                        <?php foreach ($analytics['student_performance'] as $student): ?>
                            <div class="student-item">
                                <h4><?php echo htmlspecialchars($student['student_name']); ?></h4>
                                
                                <div class="student-stats">
                                    <div class="student-stat">
                                        <h5><?php echo $student['total_exams']; ?></h5>
                                        <p class="labelTotalExams">Toplam Sınav</p>
                                    </div>
                                    <div class="student-stat">
                                        <h5><?php echo $student['average_score']; ?>%</h5>
                                        <p class="labelAverageScore">Ortalama Puan</p>
                                    </div>
                                    <div class="student-stat">
                                        <h5 class="improvement"><?php echo $student['improvement']; ?></h5>
                                        <p class="labelImprovement">Gelişim</p>
                                    </div>
                                </div>
                                
                                <div class="strengths-weaknesses">
                                    <div class="strengths">
                                        <h5 class="labelStrengths">💪 Güçlü Yönler</h5>
                                        <ul>
                                            <?php foreach ($student['strengths'] as $strength): ?>
                                                <li><?php echo htmlspecialchars($strength); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                    <div class="weaknesses">
                                        <h5 class="labelWeaknesses">⚠️ Geliştirilmesi Gerekenler</h5>
                                        <ul>
                                            <?php foreach ($student['weaknesses'] as $weakness): ?>
                                                <li><?php echo htmlspecialchars($weakness); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="no-data">
                            <h3 id="noSP">📭 Henüz Öğrenci Performansı Yok</h3>
                            <p id="noSPDesc">Öğrenciler sınavları tamamladıktan sonra performans analizi burada görünecek.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="no-data">
                    <h3 id="noExams">📭 Henüz Sınav Yok</h3>
                    <p id="noExamsDesc">Analiz yapabilmek için önce sınavlar oluşturmanız gerekiyor.</p>
                    <a href="create_exam.php" style="display: inline-block; margin-top: 20px; padding: 15px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 25px; font-weight: 600;">
                        <span id="btnCreateFirst">🚀 İlk Sınavı Oluştur</span>
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        (function(){
            const tr = {
                mainTitle:'📈 Analitik', mainSubtitle:'Performans analizi ve detaylı istatistikler',
                breadcrumbHome:'Dashboard', breadcrumbCurrent:'Analitik',
                statLabel1:'Toplam Sınav', statLabel2:'Toplam Soru', statLabel3:'Toplam Öğrenci', statLabel4:'Ortalama Puan', statLabel5:'Tamamlama Oranı',
                trendTitle:'📈 Performans Trendi', trendPlaceholder:'📊 Performans grafiği burada görüntülenecek',
                questionTitle:'❓ Soru Analizi', labelCorrectRate:'Doğru Cevap Oranı', labelAttempts:'Toplam Deneme', labelCorrect:'Doğru Cevap', labelWrong:'Yanlış Cevap',
                noQA:'📭 Henüz Soru Analizi Yok', noQADesc:'Sınavlar tamamlandıktan sonra soru analizi burada görünecek.',
                studentPerfTitle:'👥 Öğrenci Performansı', labelTotalExams:'Toplam Sınav', labelAverageScore:'Ortalama Puan', labelImprovement:'Gelişim', labelStrengths:'💪 Güçlü Yönler', labelWeaknesses:'⚠️ Geliştirilmesi Gerekenler',
                noSP:'📭 Henüz Öğrenci Performansı Yok', noSPDesc:'Öğrenciler sınavları tamamladıktan sonra performans analizi burada görünecek.',
                noExams:'📭 Henüz Sınav Yok', noExamsDesc:'Analiz yapabilmek için önce sınavlar oluşturmanız gerekiyor.', btnCreateFirst:'🚀 İlk Sınavı Oluştur'
            };
            const de = {
                mainTitle:'📈 Analytik', mainSubtitle:'Leistungsanalyse und detaillierte Statistiken',
                breadcrumbHome:'Dashboard', breadcrumbCurrent:'Analytik',
                statLabel1:'Gesamt Prüfungen', statLabel2:'Gesamt Fragen', statLabel3:'Gesamt Schüler', statLabel4:'Durchschnittspunktzahl', statLabel5:'Abschlussrate',
                trendTitle:'📈 Leistungstrend', trendPlaceholder:'📊 Leistungsdiagramm wird hier angezeigt',
                questionTitle:'❓ Fragenanalyse', labelCorrectRate:'Quote richtiger Antworten', labelAttempts:'Gesamt Versuche', labelCorrect:'Richtige Antworten', labelWrong:'Falsche Antworten',
                noQA:'📭 Noch keine Fragenanalyse', noQADesc:'Nach Abschluss der Prüfungen erscheint hier die Analyse.',
                studentPerfTitle:'👥 Schülerleistung', labelTotalExams:'Gesamt Prüfungen', labelAverageScore:'Durchschnittspunktzahl', labelImprovement:'Entwicklung', labelStrengths:'💪 Stärken', labelWeaknesses:'⚠️ Verbesserungsbedarf',
                noSP:'📭 Noch keine Schülerleistung', noSPDesc:'Nach Abschluss der Prüfungen erscheint hier die Analyse.',
                noExams:'📭 Noch keine Prüfungen', noExamsDesc:'Erstellen Sie zuerst Prüfungen, um Analysen zu sehen.', btnCreateFirst:'🚀 Erste Prüfung erstellen'
            };
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#mainTitle', d.mainTitle); setText('#mainSubtitle', d.mainSubtitle);
                setText('#breadcrumbHome', d.breadcrumbHome); setText('#breadcrumbCurrent', d.breadcrumbCurrent);
                setText('#statLabel1', d.statLabel1); setText('#statLabel2', d.statLabel2); setText('#statLabel3', d.statLabel3); setText('#statLabel4', d.statLabel4); setText('#statLabel5', d.statLabel5);
                setText('#trendTitle', d.trendTitle); setText('#trendPlaceholder', d.trendPlaceholder);
                setText('#questionTitle', d.questionTitle); setText('#labelCorrectRate', d.labelCorrectRate); setText('#labelAttempts', d.labelAttempts); setText('#labelCorrect', d.labelCorrect); setText('#labelWrong', d.labelWrong);
                setText('#noQA', d.noQA); setText('#noQADesc', d.noQADesc);
                setText('#studentPerfTitle', d.studentPerfTitle);
                document.querySelectorAll('.labelTotalExams').forEach(e=>e.innerText=d.labelTotalExams);
                document.querySelectorAll('.labelAverageScore').forEach(e=>e.innerText=d.labelAverageScore);
                document.querySelectorAll('.labelImprovement').forEach(e=>e.innerText=d.labelImprovement);
                document.querySelectorAll('.labelStrengths').forEach(e=>e.innerText=d.labelStrengths);
                document.querySelectorAll('.labelWeaknesses').forEach(e=>e.innerText=d.labelWeaknesses);
                setText('#noSP', d.noSP); setText('#noSPDesc', d.noSPDesc);
                setText('#noExams', d.noExams); setText('#noExamsDesc', d.noExamsDesc); setText('#btnCreateFirst', d.btnCreateFirst);
                const toggle=document.getElementById('langToggle'); if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_teacher_analytics', lang);
            }
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_teacher_analytics')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle'); if(toggle){ toggle.addEventListener('click', function(){ const next=(localStorage.getItem('lang_teacher_analytics')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; apply(next); }); }
            });
        })();
    </script>
</body>
</html>
