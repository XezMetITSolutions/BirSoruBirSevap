<?php
/**
 * Öğrenci İlerleme Takibi Sayfası
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğrenci kontrolü
if (!$auth->hasRole('student')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Gerçek ilerleme verilerini yükle
$progressData = [
    'weekly' => [],
    'subjects' => [],
    'achievements' => [],
    'goals' => []
];

// Toplama için ortak konteynerler
$weeklyData = [];
$subjectData = [];

// Veritabanı bağlantısı
require_once '../database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Alıştırma sonuçlarını veritabanından çek
try {
    $stmt = $conn->prepare("SELECT * FROM practice_results WHERE username = :username ORDER BY created_at ASC");
    $stmt->execute([':username' => $user['username']]);
    $practiceResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Haftalık verileri hesapla (alıştırma)
    foreach ($practiceResults as $result) {
        $week = date('W', strtotime($result['created_at']));
        if (!isset($weeklyData[$week])) {
            $weeklyData[$week] = ['exams' => 0, 'practice' => 0, 'scores' => []];
        }
        $weeklyData[$week]['practice']++;
        $weeklyData[$week]['scores'][] = $result['score'] ?? 0;
        
        // Konu bazlı verileri hesapla
        $category = $result['category'] ?? 'Genel';
        if (empty($category)) $category = 'Genel';
        
        if (!isset($subjectData[$category])) {
            $subjectData[$category] = ['scores' => [], 'count' => 0];
        }
        $subjectData[$category]['scores'][] = $result['score'] ?? 0;
        $subjectData[$category]['count']++;
    }
} catch (Exception $e) {
    error_log("Progress page practice error: " . $e->getMessage());
}

// Sınav sonuçlarını veritabanından çek ve entegre et
try {
    $stmt = $conn->prepare("SELECT * FROM exam_results WHERE username = :username ORDER BY created_at ASC");
    $stmt->execute([':username' => $user['username']]);
    $examResults = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($examResults as $entry) {
        // Haftalık ekle
        $week = date('W', strtotime($entry['created_at']));
        if (!isset($weeklyData[$week])) {
            $weeklyData[$week] = ['exams' => 0, 'practice' => 0, 'scores' => []];
        }
        $weeklyData[$week]['exams']++;
        $weeklyData[$week]['scores'][] = (int)($entry['score'] ?? 0);

        // Konu/başlık ekle: sınavlar için genel bir başlık kullan
        $subject = 'Sınavlar';
        if (!isset($subjectData[$subject])) {
            $subjectData[$subject] = ['scores' => [], 'count' => 0, 'is_exam' => true];
        }
        $subjectData[$subject]['scores'][] = (int)($entry['score'] ?? 0);
        $subjectData[$subject]['count']++;
    }
} catch (Exception $e) {
    error_log("Progress page exam error: " . $e->getMessage());
}

// Haftalık verileri formatla (alıştırma + sınav birleştirilmiş)
if (!empty($weeklyData)) {
    ksort($weeklyData);
    $weekCount = 1;
    foreach ($weeklyData as $week => $data) {
        $avgScore = count($data['scores']) > 0 ? round(array_sum($data['scores']) / count($data['scores'])) : 0;
        $progressData['weekly'][] = [
            'week' => $weekCount . '. Hafta',
            'exams' => $data['exams'],
            'practice' => $data['practice'],
            'score' => $avgScore
        ];
        $weekCount++;
    }
}

// Konu verilerini formatla (alıştırma + sınav)
if (!empty($subjectData)) {
    foreach ($subjectData as $subject => $data) {
        $avgScore = count($data['scores']) > 0 ? round(array_sum($data['scores']) / count($data['scores'])) : 0;
        $progressData['subjects'][] = [
            'subject' => $subject,
            'progress' => $avgScore,
            'exams' => ($data['is_exam'] ?? false) ? $data['count'] : 0,
            'practice' => ($data['is_exam'] ?? false) ? 0 : $data['count']
        ];
    }
}

// İstatistikleri hesapla
$totalPractice = array_sum(array_column($progressData['subjects'], 'practice'));
$totalExams = array_sum(array_column($progressData['subjects'], 'exams'));
$averageScore = 0;
if (!empty($progressData['subjects'])) {
    $averageScore = round(array_sum(array_column($progressData['subjects'], 'progress')) / count($progressData['subjects']));
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlerleme Takibi - Bir Soru Bir Sevap</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --primary-light: #089b76;
            --secondary: #f8f9fa;
            --dark: #2c3e50;
            --gray: #64748b;
            --white: #ffffff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark);
        }

        .header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 1.5rem 0;
            box-shadow: var(--shadow-lg);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo img {
            height: 3rem;
            width: auto;
        }

        .logo h1 {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }

        .logo p {
            opacity: 0.9;
            font-size: 0.875rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.125rem;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: var(--white);
            padding: 0.5rem 1rem;
            border-radius: 1.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-1px);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-5px);
        }

        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            text-align: center;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 1.125rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            color: var(--primary);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            color: var(--gray);
            font-size: 0.875rem;
            font-weight: 500;
        }

        .section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
        }

        .chart-container {
            height: 300px;
            margin: 1rem 0;
        }

        .progress-bar {
            width: 100%;
            height: 0.5rem;
            background: var(--secondary);
            border-radius: 0.25rem;
            overflow: hidden;
            margin: 0.5rem 0;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 0.25rem;
            transition: width 0.3s ease;
        }

        .subject-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: var(--secondary);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .subject-name {
            font-weight: 600;
            color: var(--dark);
        }

        .subject-progress {
            text-align: right;
        }

        .subject-score {
            font-weight: 700;
            color: var(--primary);
        }

        .subject-count {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .empty-state p {
            font-size: 1rem;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }

            .container {
                padding: 1rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .subject-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .subject-progress {
                text-align: left;
            }
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
                    <p id="pageTitle">İlerleme Takibi</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.875rem; opacity: 0.8;" id="userRole">Öğrenci</div>
                </div>
                <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="../logout.php" class="logout-btn" id="btnLogout">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn" id="btnBackToDashboard">
            <i class="fas fa-arrow-left"></i>
            <span id="backToDashboardText">Dashboard'a Dön</span>
        </a>

        <div class="page-header">
            <h1 class="page-title" id="mainTitle">📈 İlerleme Takibi</h1>
            <p class="page-subtitle" id="mainSubtitle">Gelişiminizi takip edin ve hedeflerinize ulaşın</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $totalPractice; ?></div>
                <div class="stat-label" id="statLabel1">Toplam Alıştırma</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $totalExams; ?></div>
                <div class="stat-label" id="statLabel5">Toplam Sınav</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-number"><?php echo $averageScore; ?>%</div>
                <div class="stat-label" id="statLabel2">Ortalama Puan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo count($progressData['subjects']); ?></div>
                <div class="stat-label" id="statLabel3">Konu Sayısı</div>
                            </div>
            <div class="stat-card">
                <div class="stat-icon">📅</div>
                <div class="stat-number"><?php echo count($progressData['weekly']); ?></div>
                <div class="stat-label" id="statLabel4">Aktif Hafta</div>
                    </div>
                </div>

        <?php if (!empty($progressData['subjects'])): ?>
        <div class="section">
            <h2 class="section-title" id="sectionTitle1">📖 Konu Bazlı İlerleme</h2>
                    <?php foreach ($progressData['subjects'] as $subject): ?>
                        <div class="subject-item">
                    <div class="subject-name"><?php echo htmlspecialchars($subject['subject']); ?></div>
                            <div class="subject-progress">
                        <div class="subject-score"><?php echo $subject['progress']; ?>%</div>
                        <div class="subject-count">
                            <?php echo ($subject['practice'] ?? 0); ?> <span id="practiceUnit">alıştırma</span>
                            <?php if (($subject['exams'] ?? 0) > 0): ?> • <?php echo (int)$subject['exams']; ?> sınav<?php endif; ?>
                        </div>
                                <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo $subject['progress']; ?>%"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
        <?php endif; ?>

        <?php if (!empty($progressData['weekly'])): ?>
        <div class="section">
            <h2 class="section-title" id="sectionTitle2">📅 Haftalık İlerleme</h2>
            <div class="chart-container">
                <canvas id="weeklyChart"></canvas>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($progressData['subjects']) && empty($progressData['weekly'])): ?>
            <div class="empty-state">
                <i class="fas fa-chart-line"></i>
                <h3 id="noDataTitle">Henüz veri yok</h3>
                <p id="noDataDesc">Alıştırma yaparak ilerleme verilerinizi oluşturun</p>
        </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Haftalık grafik
        <?php if (!empty($progressData['weekly'])): ?>
        const weeklyData = <?php echo json_encode($progressData['weekly']); ?>;
        const ctx = document.getElementById('weeklyChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: weeklyData.map(item => item.week),
                datasets: [{
                    label: 'Ortalama Puan',
                    data: weeklyData.map(item => item.score),
                    borderColor: '#068567',
                    backgroundColor: 'rgba(6, 133, 103, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        <?php endif; ?>

        // Smooth scroll ve animasyonlar
        document.addEventListener('DOMContentLoaded', function() {
            // Kartlara hover efekti
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-2px)';
                });
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });

            // Progress bar animasyonları
            const progressBars = document.querySelectorAll('.progress-fill');
            progressBars.forEach(bar => {
                const width = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = width;
                }, 500);
            });
        });

        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'İlerleme Takibi', userRole:'Öğrenci', btnLogout:'Çıkış', backToDashboardText:'Dashboard\'a Dön',
                mainTitle:'📈 İlerleme Takibi', mainSubtitle:'Gelişiminizi takip edin ve hedeflerinize ulaşın',
                statLabel1:'Toplam Alıştırma', statLabel2:'Ortalama Puan', statLabel3:'Konu Sayısı', statLabel4:'Aktif Hafta',
                sectionTitle1:'📖 Konu Bazlı İlerleme', sectionTitle2:'📅 Haftalık İlerleme',
                practiceUnit:'alıştırma', noDataTitle:'Henüz veri yok', noDataDesc:'Alıştırma yaparak ilerleme verilerinizi oluşturun',
                chartLabel:'Ortalama Puan'
            };
            const de = {
                pageTitle:'Fortschrittsverfolgung', userRole:'Schüler', btnLogout:'Abmelden', backToDashboardText:'Zum Dashboard',
                mainTitle:'📈 Fortschrittsverfolgung', mainSubtitle:'Verfolgen Sie Ihre Entwicklung und erreichen Sie Ihre Ziele',
                statLabel1:'Gesamt Übungen', statLabel2:'Durchschnittspunktzahl', statLabel3:'Themenanzahl', statLabel4:'Aktive Wochen',
                sectionTitle1:'📖 Themenbasierter Fortschritt', sectionTitle2:'📅 Wöchentlicher Fortschritt',
                practiceUnit:'Übungen', noDataTitle:'Noch keine Daten', noDataDesc:'Erstellen Sie Ihre Fortschrittsdaten durch Üben',
                chartLabel:'Durchschnittspunktzahl'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnLogout', d.btnLogout);
                setText('#backToDashboardText', d.backToDashboardText);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#sectionTitle1', d.sectionTitle1);
                setText('#sectionTitle2', d.sectionTitle2);
                setText('#practiceUnit', d.practiceUnit);
                setText('#noDataTitle', d.noDataTitle);
                setText('#noDataDesc', d.noDataDesc);
                
                // Chart label'ını güncelle
                const chart = Chart.getChart('weeklyChart');
                if (chart) {
                    chart.data.datasets[0].label = d.chartLabel;
                    chart.update();
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_progress', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_progress')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_progress')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>