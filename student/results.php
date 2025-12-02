<?php
/**
 * Öğrenci Sonuçlar Sayfası
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

// Gerçek sonuç verilerini yükle
$results = [];

// Veritabanı bağlantısı
require_once '../database.php';
$db = Database::getInstance();
$conn = $db->getConnection();

// Alıştırma sonuçlarını veritabanından çek
try {
    $stmt = $conn->prepare("SELECT * FROM practice_results WHERE username = :username ORDER BY created_at DESC");
    $stmt->execute([':username' => $user['username']]);
    $practiceResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($practiceResults as $result) {
        $results[] = [
            'id' => 'practice_' . $result['id'],
            'exam_title' => $result['category'] ?? 'Alıştırma',
            'teacher' => 'Sistem',
            'date' => $result['created_at'],
            'score' => $result['score'],
            'total_questions' => $result['total_questions'],
            'correct_answers' => $result['correct_answers'],
            'duration' => round($result['time_taken'] / 60), // saniye -> dakika
            'status' => 'completed'
        ];
    }
} catch (PDOException $e) {
    error_log("Alıştırma sonuçları hatası: " . $e->getMessage());
}

// Sınav sonuçlarını veritabanından çek
try {
    // Sınav bilgilerini ve öğretmen adını almak için JOIN kullanıyoruz
    $sql = "SELECT er.*, e.title as exam_title, u.full_name as teacher_name 
            FROM exam_results er 
            LEFT JOIN exams e ON er.exam_id = e.exam_id 
            LEFT JOIN users u ON e.created_by = u.username 
            WHERE er.username = :username 
            ORDER BY er.created_at DESC";
            
    $stmt = $conn->prepare($sql);
    $stmt->execute([':username' => $user['username']]);
    $examResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($examResults as $result) {
        // Süre hesaplama (saniye cinsinden olabilir veya dakika stringi olabilir)
        $duration = 0;
        if (is_numeric($result['time_taken'])) {
            $duration = round($result['time_taken'] / 60);
        } else {
            // "12 dakika" gibi string ise
            $duration = intval($result['time_taken']);
        }
        
        $results[] = [
            'id' => 'exam_' . $result['id'],
            'exam_title' => $result['exam_title'] ?? 'Sınav',
            'teacher' => $result['teacher_name'] ?? 'Eğitmen',
            'date' => $result['created_at'],
            'score' => $result['score'],
            'total_questions' => $result['total_questions'],
            'correct_answers' => $result['correct_answers'],
            'duration' => $duration,
            'status' => 'completed'
        ];
    }
} catch (PDOException $e) {
    error_log("Sınav sonuçları hatası: " . $e->getMessage());
}

// İstatistikler hesapla
$totalExams = count($results);
$averageScore = $totalExams > 0 ? round(array_sum(array_column($results, 'score')) / $totalExams, 1) : 0;
$bestScore = $totalExams > 0 ? max(array_column($results, 'score')) : 0;
$totalQuestions = array_sum(array_column($results, 'total_questions'));
$totalCorrect = array_sum(array_column($results, 'correct_answers'));
$totalDuration = array_sum(array_column($results, 'duration'));

function getScoreClass($score) {
    if ($score >= 80) return 'score-excellent';
    if ($score >= 60) return 'score-good';
    return 'score-poor';
}

function getScoreText($score) {
    if ($score >= 80) return 'Mükemmel';
    if ($score >= 60) return 'İyi';
    return 'Geliştirilmeli';
}

// Dil desteği için getScoreText fonksiyonunu güncelle
function getScoreTextLocalized($score, $lang = 'tr') {
    if ($lang === 'de') {
        if ($score >= 80) return 'Perfekt';
        if ($score >= 60) return 'Gut';
        return 'Verbesserung erforderlich';
    } else {
        if ($score >= 80) return 'Mükemmel';
        if ($score >= 60) return 'İyi';
        return 'Geliştirilmeli';
    }
}

function formatDate($date) {
    return date('d.m.Y', strtotime($date));
}

function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return $hours . 's ' . $mins . 'dk';
    }
    return $mins . 'dk';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sonuçlarım - Bir Soru Bir Sevap</title>
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
            gap: 12px;
            flex-wrap: wrap;
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

        .user-info { display: flex; align-items: center; gap: .75rem; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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

        .results-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1.5rem;
        }

        .results-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        .results-table th,
        .results-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }

        .results-table th {
            background: var(--secondary);
            font-weight: 600;
            color: var(--dark);
        }

        .results-table tbody tr:hover {
            background: rgba(6, 133, 103, 0.05);
        }

        .score-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .score-excellent {
            background: #d1fae5;
            color: #065f46;
        }

        .score-good {
            background: #fef3c7;
            color: #92400e;
        }

        .score-poor {
            background: #fee2e2;
            color: #991b1b;
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
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .logout-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .container { padding: 1rem; }
            .stats-grid { grid-template-columns: 1fr; }
            .results-table { font-size: 0.875rem; }
            .results-table th, .results-table td { padding: 0.75rem 0.5rem; }
        }
        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .logout-btn { padding: 5px 8px; font-size: .85rem; }
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
                    <p id="pageTitle">Sonuçlarım</p>
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
            <h1 class="page-title" id="mainTitle">📊 Sonuçlarım</h1>
            <p class="page-subtitle" id="mainSubtitle">Alıştırma ve sınav sonuçlarınızı görüntüleyin</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $totalExams; ?></div>
                <div class="stat-label" id="statLabel1">Toplam Sınav</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🎯</div>
                <div class="stat-number"><?php echo $averageScore; ?>%</div>
                <div class="stat-label" id="statLabel2">Ortalama Puan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⭐</div>
                <div class="stat-number"><?php echo $bestScore; ?>%</div>
                <div class="stat-label" id="statLabel3">En İyi Puan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⏱️</div>
                <div class="stat-number"><?php echo $totalDuration; ?>dk</div>
                <div class="stat-label" id="statLabel4">Toplam Süre</div>
            </div>
        </div>

        <div class="results-section">
            <h2 class="section-title" id="sectionTitle">📋 Sonuç Detayları</h2>
            
            <?php if (empty($results)): ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h3 id="noResultsTitle">Henüz sonuç yok</h3>
                    <p id="noResultsDesc">Alıştırma yaparak ilk sonucunuzu oluşturun</p>
                </div>
            <?php else: ?>
                <table class="results-table" id="resultsTable">
                    <thead>
                        <tr>
                            <th id="thExam" data-sort="exam" style="cursor: pointer;">Sınav <i class="fas fa-sort"></i></th>
                            <th id="thTeacher" data-sort="teacher" style="cursor: pointer;">Eğitmenin Adı <i class="fas fa-sort"></i></th>
                            <th id="thDate" data-sort="date" style="cursor: pointer;">Tarih <i class="fas fa-sort"></i></th>
                            <th id="thScore" data-sort="score" style="cursor: pointer;">Puan <i class="fas fa-sort"></i></th>
                            <th id="thCorrect" data-sort="correct" style="cursor: pointer;">Doğru/Toplam <i class="fas fa-sort"></i></th>
                            <th id="thDuration" data-sort="duration" style="cursor: pointer;">Süre <i class="fas fa-sort"></i></th>
                            <th id="thStatus" data-sort="status" style="cursor: pointer;">Durum <i class="fas fa-sort"></i></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($results as $result): ?>
                            <tr>
                                <td data-value="<?php echo htmlspecialchars($result['exam_title']); ?>"><strong><?php echo htmlspecialchars($result['exam_title']); ?></strong></td>
                                <td data-value="<?php echo htmlspecialchars($result['teacher']); ?>"><?php echo htmlspecialchars($result['teacher']); ?></td>
                                <td data-value="<?php echo $result['date']; ?>"><?php echo formatDate($result['date']); ?></td>
                                <td data-value="<?php echo $result['score']; ?>">
                                    <span class="score-badge <?php echo getScoreClass($result['score']); ?>">
                                    <?php echo $result['score']; ?>%
                                    </span>
                                </td>
                                <td data-value="<?php echo $result['correct_answers']; ?>"><?php echo $result['correct_answers']; ?>/<?php echo $result['total_questions']; ?></td>
                                <td data-value="<?php echo $result['duration']; ?>"><?php echo formatDuration($result['duration']); ?></td>
                                <td data-value="<?php echo getScoreText($result['score']); ?>">
                                    <span class="score-badge score-excellent status-cell">
                                        <?php echo getScoreText($result['score']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Tablo Sıralama Fonksiyonu
        document.addEventListener('DOMContentLoaded', function() {
            const table = document.getElementById('resultsTable');
            if (!table) return;

            const headers = table.querySelectorAll('th[data-sort]');
            let currentSort = { column: null, direction: 'asc' };

            headers.forEach(header => {
                header.addEventListener('click', () => {
                    const column = header.getAttribute('data-sort');
                    const direction = currentSort.column === column && currentSort.direction === 'asc' ? 'desc' : 'asc';
                    
                    // İkonları güncelle
                    headers.forEach(h => {
                        const icon = h.querySelector('i');
                        icon.className = 'fas fa-sort';
                    });
                    const activeIcon = header.querySelector('i');
                    activeIcon.className = `fas fa-sort-${direction === 'asc' ? 'up' : 'down'}`;

                    sortTable(column, direction);
                    currentSort = { column, direction };
                });
            });

            function sortTable(column, direction) {
                const tbody = table.querySelector('tbody');
                const rows = Array.from(tbody.querySelectorAll('tr'));

                const sortedRows = rows.sort((a, b) => {
                    let aVal, bVal;

                    // Sütun indexini bul
                    let colIndex = 0;
                    switch(column) {
                        case 'exam': colIndex = 0; break;
                        case 'teacher': colIndex = 1; break;
                        case 'date': colIndex = 2; break;
                        case 'score': colIndex = 3; break;
                        case 'correct': colIndex = 4; break;
                        case 'duration': colIndex = 5; break;
                        case 'status': colIndex = 6; break;
                    }

                    const aCell = a.cells[colIndex];
                    const bCell = b.cells[colIndex];
                    
                    aVal = aCell.getAttribute('data-value');
                    bVal = bCell.getAttribute('data-value');

                    // Sayısal değerler için kontrol
                    if (column === 'score' || column === 'duration' || column === 'correct') {
                        aVal = parseFloat(aVal);
                        bVal = parseFloat(bVal);
                    }

                    if (aVal < bVal) return direction === 'asc' ? -1 : 1;
                    if (aVal > bVal) return direction === 'asc' ? 1 : -1;
                    return 0;
                });

                // Sıralanmış satırları tabloya ekle
                tbody.innerHTML = '';
                sortedRows.forEach(row => tbody.appendChild(row));
            }
        });
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                mainTitle:'📊 Sonuçlarım', mainSubtitle:'Alıştırma ve sınav sonuçlarınızı görüntüleyin',
                statLabel1:'Toplam Sınav', statLabel2:'Ortalama Puan', statLabel3:'En İyi Puan', statLabel4:'Toplam Süre',
                sectionTitle:'📋 Sonuç Detayları', noResultsTitle:'Henüz sonuç yok', noResultsDesc:'Alıştırma yaparak ilk sonucunuzu oluşturun',
                thExam:'Sınav', thTeacher:'Eğitmen', thDate:'Tarih', thScore:'Puan', thCorrect:'Doğru/Toplam', thDuration:'Süre', thStatus:'Durum',
                backToDashboard:'Dashboard\'a Dön', perfect:'Mükemmel', good:'İyi', needsImprovement:'Geliştirilmeli',
                pageTitle:'Sonuçlarım', userRole:'Öğrenci', btnLogout:'Çıkış', backToDashboardText:'Dashboard\'a Dön'
            };
            const de = {
                mainTitle:'📊 Meine Ergebnisse', mainSubtitle:'Zeigen Sie Ihre Übungs- und Prüfungsergebnisse an',
                statLabel1:'Gesamt Prüfungen', statLabel2:'Durchschnittspunktzahl', statLabel3:'Beste Punktzahl', statLabel4:'Gesamtzeit',
                sectionTitle:'📋 Ergebnisdetails', noResultsTitle:'Noch keine Ergebnisse', noResultsDesc:'Erstellen Sie Ihr erstes Ergebnis durch Üben',
                thExam:'Prüfung', thTeacher:'Lehrer', thDate:'Datum', thScore:'Punkte', thCorrect:'Richtig/Gesamt', thDuration:'Zeit', thStatus:'Status',
                backToDashboard:'Zum Dashboard', perfect:'Perfekt', good:'Gut', needsImprovement:'Verbesserung erforderlich',
                pageTitle:'Meine Ergebnisse', userRole:'Schüler', btnLogout:'Abmelden', backToDashboardText:'Zum Dashboard'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#sectionTitle', d.sectionTitle);
                setText('#noResultsTitle', d.noResultsTitle);
                setText('#noResultsDesc', d.noResultsDesc);
                setText('#thExam', d.thExam);
                setText('#thTeacher', d.thTeacher);
                setText('#thDate', d.thDate);
                setText('#thScore', d.thScore);
                setText('#thCorrect', d.thCorrect);
                setText('#thDuration', d.thDuration);
                setText('#thStatus', d.thStatus);
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnLogout', d.btnLogout);
                setText('#backToDashboardText', d.backToDashboardText);
                
                // Durum metinlerini güncelle
                const statusCells = document.querySelectorAll('.status-cell');
                statusCells.forEach(cell => {
                    const text = cell.textContent.trim();
                    if (text === 'Mükemmel' || text === 'Perfekt') {
                        cell.textContent = d.perfect;
                    } else if (text === 'İyi' || text === 'Gut') {
                        cell.textContent = d.good;
                    } else if (text === 'Geliştirilmeli' || text === 'Verbesserung nötig' || text === 'Verbesserung erforderlich') {
                        cell.textContent = d.needsImprovement;
                    }
                });
                
                // Doğru/Toplam sütununu güncelle (eğer 0/0 ise)
                const correctCells = document.querySelectorAll('tbody tr td:nth-child(5)');
                correctCells.forEach(cell => {
                    const text = cell.textContent.trim();
                    if (text === '0/0') {
                        // Score'dan hesapla
                        const row = cell.closest('tr');
                        const scoreCell = row.querySelector('td:nth-child(4) .score-badge');
                        if (scoreCell) {
                            const scoreText = scoreCell.textContent.trim();
                            const score = parseInt(scoreText.replace('%', ''));
                            if (score > 0) {
                                const totalQuestions = Math.max(5, Math.round(100 / score));
                                const correctAnswers = Math.round((score / 100) * totalQuestions);
                                cell.textContent = correctAnswers + '/' + totalQuestions;
                            }
                        }
                    }
                });

                // Süre birimlerini yerelleştir
                const localizeDuration = (text) => {
                    if (!text) return text;
                    if (lang==='de') {
                        // "1s 5dk" -> "1 Std 5 Minuten", "12dk" -> "12 Minuten"
                        return text
                            .replace(/\b(\d+)s\b/g, '$1 Std')
                            .replace(/\b(\d+)(?:\s*)dk\b/g, '$1 Minuten');
                    } else {
                        // Geri dönüş: "1 Std 5 Minuten" -> "1s 5dk", "12 Minuten" -> "12dk"
                        return text
                            .replace(/\b(\d+)\s*Std\b/g, '$1s')
                            .replace(/\b(\d+)\s*Minuten\b/g, '$1dk');
                    }
                };

                // Tablo süre sütunu (6. sütun)
                document.querySelectorAll('tbody tr td:nth-child(6)').forEach(td => {
                    td.textContent = localizeDuration(td.textContent.trim());
                });

                // Üstteki istatistik kartındaki toplam süre değeri (4. kart)
                const statNumbers = document.querySelectorAll('.stats-grid .stat-card .stat-number');
                if (statNumbers && statNumbers[3]) {
                    statNumbers[3].textContent = localizeDuration(statNumbers[3].textContent.trim());
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_results', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_results')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_results')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();

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
        });
    </script>
</body>
</html>