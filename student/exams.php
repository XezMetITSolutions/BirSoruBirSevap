<?php
/**
 * Öğrenci Sınav Listesi
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../ExamManager.php';

$auth = Auth::getInstance();

// Öğrenci kontrolü
if (!$auth->hasRole('student')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Gerçek sınav verilerini yükle
$examManager = new ExamManager();
$exams = $examManager->getExamsForStudent($user['username']);

// Planlanan sınavları yükle (ayrı olarak)
$scheduledExams = [];

// Kurum bazlı aktif sınavları yükle (exams.json üzerinden)
$studentInstitution = $user['institution'] ?? $user['branch'] ?? '';
$studentClass = $user['class_section'] ?? $studentInstitution;
$norm = function($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); };
$si = $norm($studentInstitution);
$sc = $norm($studentClass);
$activeExams = [];

if (file_exists('../data/exams.json')) {
    $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
    foreach ($allExams as $examCode => $exam) {
        $examClassSection = $exam['class_section'] ?? '';
        $examInstitution = $exam['teacher_institution'] ?? $exam['institution'] ?? '';

        $isActive = strtolower((string)($exam['status'] ?? '')) === 'active';
        $es = $norm($examClassSection);
        $ei = $norm($examInstitution);
        $isForStudentInstitution = ($es !== '' && ($es === $si || $es === $sc)) || ($ei !== '' && ($ei === $si || $ei === $sc));

        if ($isActive && $isForStudentInstitution) {
            // Sınav süresi kontrolü
            $startTime = strtotime($exam['start_date'] ?? $exam['scheduled_start'] ?? '');
            $duration = (int)($exam['duration'] ?? 30); // dakika
            $endTime = $startTime + ($duration * 60); // saniye
            $currentTime = time();
            
            if ($currentTime <= $endTime) {
                $activeExams[$examCode] = $exam;
            }
        }
    }
}

// Hafif AJAX: sadece sayı ve kodları döndür (10 sn kontrol için)
if (isset($_GET['ajax']) && $_GET['ajax'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'count' => count($activeExams),
        'codes' => array_keys($activeExams),
    ]);
    exit;
}

// Kurumdaki aktif sınavları (data/exams.json) filtrele
$studentInstitution = $user['institution'] ?? $user['branch'] ?? '';
$studentClass = $user['class_section'] ?? $studentInstitution;
$norm = function($s){ return mb_strtolower(trim((string)$s), 'UTF-8'); };
$si = $norm($studentInstitution);
$sc = $norm($studentClass);
$activeExams = [];

if (file_exists('../data/exams.json')) {
    $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
    foreach ($allExams as $examCode => $exam) {
        $examClassSection = $exam['class_section'] ?? '';
        $examInstitution = $exam['teacher_institution'] ?? $exam['institution'] ?? '';
        $isActive = strtolower((string)($exam['status'] ?? '')) === 'active';
        $es = $norm($examClassSection);
        $ei = $norm($examInstitution);
        $isForStudentInstitution = ($es !== '' && ($es === $si || $es === $sc)) ||
                                   ($ei !== '' && ($ei === $si || $ei === $sc));
        if ($isActive && $isForStudentInstitution) {
            // Sınav süresi kontrolü
            $startTime = strtotime($exam['start_date'] ?? $exam['scheduled_start'] ?? '');
            $duration = (int)($exam['duration'] ?? 30); // dakika
            $endTime = $startTime + ($duration * 60); // saniye
            $currentTime = time();
            
            if ($currentTime <= $endTime) {
                $activeExams[$examCode] = $exam;
            }
        }
        
        // Planlanan sınavları ayrı olarak yükle
        if (($exam['status'] ?? '') === 'scheduled' && $isForStudentInstitution) {
            $scheduledExams[$examCode] = $exam;
        }
    }
}

// AJAX: Aktif sınavlar için hafif durum dönüşü
if (isset($_GET['ajax_active']) && $_GET['ajax_active'] === '1') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'count' => count($activeExams),
        'codes' => array_keys($activeExams),
    ]);
    exit;
}

// Sınav durumlarını hesapla (eğer sınav varsa)
if (!empty($exams)) {
    $currentTime = time();
    foreach ($exams as &$exam) {
        $startTime = strtotime($exam['start_date']);
        $endTime = strtotime($exam['end_date']);
        
        // Sınav sonuçlarını kontrol et
        $examResults = $examManager->getExamResults($exam['id'], $user['username']);
        
        if (!empty($examResults)) {
            $exam['status'] = 'completed';
            $exam['score'] = $examResults[0]['score'] ?? 0;
            $exam['attempts'] = count($examResults);
        } elseif ($currentTime < $startTime) {
            $exam['status'] = 'not_started';
        } elseif ($currentTime > $endTime) {
            $exam['status'] = 'expired';
        } else {
            $exam['status'] = 'available';
        }
    }
}

function getStatusText($status) {
    $statuses = [
        'available' => 'Mevcut',
        'completed' => 'Tamamlandı',
        'expired' => 'Süresi Doldu',
        'not_started' => 'Henüz Başlamadı'
    ];
    return $statuses[$status] ?? $status;
}

function getStatusClass($status) {
    $classes = [
        'available' => 'status-available',
        'completed' => 'status-completed',
        'expired' => 'status-expired',
        'not_started' => 'status-not-started'
    ];
    return $classes[$status] ?? '';
}

function formatDateTime($datetime) {
    return date('d.m.Y H:i', strtotime($datetime));
}

function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return $hours . ' saat ' . $mins . ' dakika';
    }
    return $mins . ' dakika';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınavlarım - Bir Soru Bir Sevap</title>
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
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
        }

        .logo p {
            opacity: 0.9;
            font-size: 0.9em;
        }

        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: white;
            text-decoration: none;
            margin-bottom: 0;
            font-weight: 600;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-1px);
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .page-title {
            font-size: 2.5em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .page-subtitle {
            color: #7f8c8d;
            font-size: 1.1em;
        }

        .filters {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
        }

        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .filter-group select {
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .filter-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1em;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-success:hover {
            background: #229954;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }

        .btn:disabled {
            background: #bdc3c7;
            cursor: not-allowed;
            transform: none;
        }

        .exams-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .exam-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .exam-header {
            padding: 25px;
            border-bottom: 1px solid #e1e8ed;
        }

        .exam-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }

        .exam-teacher {
            color: #7f8c8d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }

        .exam-description {
            color: #5a6c7d;
            line-height: 1.5;
            font-size: 0.95em;
        }

        .exam-body {
            padding: 25px;
        }

        .exam-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.8em;
            color: #7f8c8d;
            margin-bottom: 4px;
            text-transform: uppercase;
            font-weight: 600;
        }

        .info-value {
            color: #2c3e50;
            font-weight: 500;
        }

        .exam-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: bold;
            margin-bottom: 20px;
        }

        .status-available {
            background: #d4edda;
            color: #155724;
        }

        .status-completed {
            background: #cce5ff;
            color: #004085;
        }

        .status-expired {
            background: #f8d7da;
            color: #721c24;
        }

        .status-not-started {
            background: #fff3cd;
            color: #856404;
        }

        .exam-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .score-display {
            background: #e8f5e8;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
        }

        .score-number {
            font-size: 2em;
            font-weight: bold;
            color: #27ae60;
        }

        .score-label {
            color: #155724;
            font-size: 0.9em;
        }

        .countdown {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            margin-bottom: 15px;
        }

        .countdown-text {
            color: #856404;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 15px;
            color: #2c3e50;
        }

        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; flex-wrap: wrap; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .logout-btn, .back-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            
            .filter-row {
                grid-template-columns: 1fr;
            }
            
            .exams-grid {
                grid-template-columns: 1fr;
            }
            
            .exam-info {
                grid-template-columns: 1fr;
            }
            
            .exam-actions {
                flex-direction: column;
            }
        }
        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .logout-btn, .back-btn { padding: 5px 8px; font-size: .85rem; }
            .user-info { gap: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Bir Soru Bir Sevap Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p id="pageTitle">Sınavlarım</p>
                </div>
            </div>
            <div class="user-info">
                <a href="dashboard.php" class="back-btn" id="btnBack" style="margin-right: 15px;">
                    <i class="fas fa-arrow-left"></i>
                    Dashboard'a Dön
                </a>
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;" id="userRole">Öğrenci</div>
                </div>
                <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.15);">DE</button>
                <a href="../logout.php" class="logout-btn" id="btnLogout">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h2 class="page-title" id="mainTitle">📝 Sınavlarım</h2>
            <p class="page-subtitle" id="mainSubtitle">Atanan sınavları görüntüleyin ve katılın</p>
        </div>

        <!-- Sınav Kodu ile Giriş -->
        <div style="background: #fff; border-radius: 16px; padding: 20px; box-shadow: 0 8px 20px rgba(0,0,0,0.06); margin-bottom: 20px;">
            <form method="POST" action="exam_join.php" style="display: grid; grid-template-columns: 1fr auto; gap: 12px; align-items: end;">
                <div>
                    <label for="exam_code" style="display:block; margin-bottom:8px; font-weight:600; color:#2c3e50;" id="labelExamCode">🔑 Sınav Kodu</label>
                    <input type="text" id="exam_code" name="exam_code" required maxlength="8" placeholder="Örn: A1B2C3D4" style="width:100%; padding: 12px 14px; border:2px solid #e1e8ed; border-radius:10px; font-size:1rem; letter-spacing:2px; text-transform:uppercase; font-weight:700;" />
                </div>
                <button type="submit" class="btn" style="padding: 12px 18px;" id="btnJoin">🚀 Sınava Gir</button>
            </form>
        </div>

        

        <!-- Süresi Dolmuş Sınavlar -->
        <?php 
        $expiredExams = [];
        if (file_exists('../data/exams.json')) {
            $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
            foreach ($allExams as $examCode => $exam) {
                $examClassSection = $exam['class_section'] ?? '';
                $examInstitution = $exam['teacher_institution'] ?? $exam['institution'] ?? '';
                $isActive = strtolower((string)($exam['status'] ?? '')) === 'active';
                $es = $norm($examClassSection);
                $ei = $norm($examInstitution);
                $isForStudentInstitution = ($es !== '' && ($es === $si || $es === $sc)) || ($ei !== '' && ($ei === $si || $ei === $sc));
                
                if ($isActive && $isForStudentInstitution) {
                    $startTime = strtotime($exam['start_date'] ?? $exam['scheduled_start'] ?? '');
                    $duration = (int)($exam['duration'] ?? 30);
                    $endTime = $startTime + ($duration * 60);
                    $currentTime = time();
                    
                    if ($currentTime > $endTime) {
                        // Öğrenci bu sınavı almış mı kontrol et
                        $studentId = $user['username'] ?? $user['name'] ?? 'unknown';
                        $hasResult = false;
                        
                        if (file_exists('../data/exam_results.json')) {
                            $allResults = json_decode(file_get_contents('../data/exam_results.json'), true) ?? [];
                            $examResults = $allResults[$examCode] ?? [];
                            foreach ($examResults as $res) {
                                if (($res['student_id'] ?? '') === $studentId) {
                                    $hasResult = true;
                                    break;
                                }
                            }
                        }
                        
                        // Sadece öğrenci sınava girmişse göster
                        if ($hasResult) {
                            $expiredExams[$examCode] = $exam;
                        }
                    }
                }
            }
        }
        ?>
        <?php if (!empty($expiredExams)): ?>
        <div id="expired-exams-section" style="background:#fff; border-radius:16px; padding:20px; box-shadow:0 8px 20px rgba(0,0,0,0.06); margin-bottom:20px;">
            <h3 style="margin:0 0 12px 0; color:#2c3e50;" id="expiredExamsTitle">⏰ Süresi Dolmuş Sınavlar</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                <?php foreach ($expiredExams as $code => $exam): ?>
                    <?php 
                    $startTime = strtotime($exam['start_date'] ?? $exam['scheduled_start'] ?? '');
                    $duration = (int)($exam['duration'] ?? 30);
                    $endTime = $startTime + ($duration * 60);
                    ?>
                    <div style="border:1px solid #ef4444; border-radius:12px; padding:14px; background:linear-gradient(135deg, rgba(239, 68, 68, 0.05) 0%, rgba(239, 68, 68, 0.02) 100%);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="font-weight:700; color:#2c3e50;">
                                <?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?>
                            </div>
                            <span style="background:#ef4444; color:#fff; padding:4px 8px; border-radius:12px; font-weight:700; font-size:12px;">
                                <?php echo htmlspecialchars($code); ?>
                            </span>
                        </div>
                        <div style="color:#6c757d; font-size:0.95rem; margin-bottom:8px;">
                            ⏱️ <span id="durationLabel3">Süre:</span> <?php echo htmlspecialchars($exam['duration'] ?? '—'); ?> <span class="durationUnit3">dk</span> • 📊 <span id="questionsLabel3">Soru:</span> <?php echo count($exam['questions'] ?? []); ?>
                            <br>📅 <span id="expiredTimeLabel">Sona Erdi:</span> <?php echo date('d.m.Y H:i', $endTime); ?>
                        </div>
                        <div style="background:#fef2f2; color:#dc2626; padding:8px 12px; border-radius:8px; text-align:center; font-weight:600;" id="btnExpired">
                            <a href="view_result.php?exam_code=<?php echo urlencode($code); ?>" style="color:#dc2626; text-decoration:none;">
                                <i class="fas fa-chart-bar"></i> <span id="btnExpiredText">Sonucu Gör</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Planlanan Sınavlar -->
        <?php if (!empty($scheduledExams)): ?>
        <div id="scheduled-exams-section" style="background:#fff; border-radius:16px; padding:20px; box-shadow:0 8px 20px rgba(0,0,0,0.06); margin-bottom:20px;">
            <h3 style="margin:0 0 12px 0; color:#2c3e50;" id="scheduledExamsTitle">📅 Planlanan Sınavlar</h3>
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap:12px;">
                <?php foreach ($scheduledExams as $code => $exam): ?>
                    <?php 
                    $scheduledTime = strtotime($exam['scheduled_start'] ?? '');
                    $now = time();
                    $timeDiff = $scheduledTime - $now;
                    $isUpcoming = $timeDiff > 0 && $timeDiff <= 3600; // 1 saat içinde
                    ?>
                    <div style="border:1px solid #3b82f6; border-radius:12px; padding:14px; background:linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, rgba(59, 130, 246, 0.02) 100%);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:8px;">
                            <div style="font-weight:700; color:#2c3e50;">
                                <?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?>
                            </div>
                            <span style="background:#3b82f6; color:#fff; padding:4px 8px; border-radius:12px; font-weight:700; font-size:12px;">
                                <?php echo htmlspecialchars($code); ?>
                            </span>
                        </div>
                        <div style="color:#6c757d; font-size:0.95rem; margin-bottom:8px;">
                            ⏱️ <span id="durationLabel2">Süre:</span> <?php echo htmlspecialchars($exam['duration'] ?? '—'); ?> <span class="durationUnit2">dk</span> • 📊 <span id="questionsLabel2">Soru:</span> <?php echo count($exam['questions'] ?? []); ?>
                            <br>📅 <span id="scheduledTimeLabel2">Planlanan:</span> <?php echo date('d.m.Y H:i', $scheduledTime); ?>
                        </div>
                        <div style="background:#e5e7eb; color:#6b7280; padding:8px 12px; border-radius:8px; text-align:center; font-weight:600;" id="btnWaitScheduled2">
                            <i class="fas fa-clock"></i> <span id="btnWaitScheduledText2">Bekleniyor</span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="filters">
            <div class="filter-row">
                <div class="filter-group">
                    <label for="status-filter" id="statusLabel">Durum:</label>
                    <select id="status-filter">
                        <option value="" id="statusAll">Tümü</option>
                        <option value="available" id="statusAvailable">Mevcut</option>
                        <option value="completed" id="statusCompleted">Tamamlandı</option>
                        <option value="expired" id="statusExpired">Süresi Doldu</option>
                        <option value="not_started" id="statusNotStarted">Henüz Başlamadı</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="teacher-filter" id="teacherLabel">Eğitmen:</label>
                    <select id="teacher-filter">
                        <option value="" id="teacherAll">Tümü</option>
                        <option value="Ahmet Eğitmen" id="teacher1">Ahmet Eğitmen</option>
                        <option value="Ayşe Eğitmen" id="teacher2">Ayşe Eğitmen</option>
                        <option value="Mehmet Eğitmen" id="teacher3">Mehmet Eğitmen</option>
                        <option value="Fatma Eğitmen" id="teacher4">Fatma Eğitmen</option>
                    </select>
                </div>
                <div class="filter-group">
                    <button class="btn" onclick="applyFilters()" id="btnFilter">Filtrele</button>
                </div>
            </div>
        </div>

        <!-- Kurumumdaki Aktif Sınavlar (canlı) -->
        <div class="filters" style="margin-top: -10px; margin-bottom: 20px;">
            <h3 style="margin-bottom: 10px; color:#2c3e50;" id="institutionExamsTitle">🏫 Kurumumda Yapmış Olduğum Sınavlar</h3>
            <?php if (!empty($activeExams)): ?>
                <div class="exams-grid">
                    <?php foreach ($activeExams as $examCode => $exam): ?>
                        <div class="exam-card">
                            <div class="exam-header" style="display:flex; justify-content:space-between; align-items:center;">
                                <div>
                                    <div class="exam-title"><?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?></div>
                                    <div class="exam-description" style="margin-top:6px;">
                                        <?php echo htmlspecialchars($exam['description'] ?? ''); ?>
                                    </div>
                                </div>
                                <div class="exam-code" style="background:#068567; color:#fff; padding:6px 12px; border-radius:12px; font-weight:700; letter-spacing:1px;">
                                    <?php echo htmlspecialchars($examCode); ?>
                                </div>
                            </div>
                            <div class="exam-body">
                                <div class="exam-info" style="margin-bottom: 10px;">
                                    <div class="info-item">
                                        <div class="info-label" id="labelStartDateInstitution">Başlangıç</div>
                                        <div class="info-value">
                                            <?php 
                                            $startAt = $exam['start_date'] ?? ($exam['scheduled_start'] ?? ($exam['created_at'] ?? null));
                                            echo $startAt ? date('d.m.Y H:i', strtotime($startAt)) : '—';
                                            ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label" id="labelDurationInstitution">Süre</div>
                                        <div class="info-value"><?php echo htmlspecialchars((string)($exam['duration'] ?? '—')); ?> dk</div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label" id="labelQuestionInstitution">Soru</div>
                                        <div class="info-value"><?php echo count($exam['questions'] ?? []); ?></div>
                                    </div>
                                </div>
                                <div class="exam-actions">
                                    <?php
                                    $resultsFile = '../data/exam_results.json';
                                    $studentId = $user['username'] ?? $user['name'] ?? 'unknown';
                                    $hasResult = false;
                                    if (file_exists($resultsFile)) {
                                        $allResults = json_decode(file_get_contents($resultsFile), true) ?? [];
                                        $examResults = $allResults[$examCode] ?? [];
                                        foreach ($examResults as $res) {
                                            if (($res['student_id'] ?? '') === $studentId) { $hasResult = true; break; }
                                        }
                                    }
                                    ?>
                                    <?php if ($hasResult): ?>
                                        <a href="view_result.php?exam_code=<?php echo htmlspecialchars($examCode); ?>" class="btn btn-secondary" id="btnViewResult2">📊 Sonucu Gör</a>
                                    <?php else: ?>
                                        <button class="btn btn-success" onclick="fillExamCode('<?php echo htmlspecialchars($examCode); ?>')" id="btnJoinExam2">🚀 Bu Sınava Gir</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding: 20px;">
                    <div style="color:#6c757d;" id="noInstitutionExams">Şu anda kurumunuzda aktif bir sınav bulunmuyor.</div>
                    <div style="font-size: 0.9em; margin-top:6px; color:#95a5a6;" id="noInstitutionExamsDesc">Yeni sınavlar otomatik olarak burada görünecek.</div>
                </div>
            <?php endif; ?>
        </div>

        <div class="exams-grid" id="exams-grid">
            <?php foreach ($exams as $exam): ?>
                <div class="exam-card" data-status="<?php echo $exam['status']; ?>" data-teacher="<?php echo $exam['teacher']; ?>">
                    <div class="exam-header">
                        <h3 class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></h3>
                        <div class="exam-teacher">👨‍🏫 <?php echo htmlspecialchars($exam['teacher']); ?></div>
                        <p class="exam-description"><?php echo htmlspecialchars($exam['description']); ?></p>
                    </div>
                    
                    <div class="exam-body">
                        <div class="exam-status <?php echo getStatusClass($exam['status']); ?>">
                            <?php echo getStatusText($exam['status']); ?>
                        </div>
                        
                        <?php if ($exam['status'] === 'completed' && $exam['score'] !== null): ?>
                            <div class="score-display">
                                <div class="score-number"><?php echo $exam['score']; ?>%</div>
                                <div class="score-label">Başarı Puanı</div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($exam['status'] === 'available'): ?>
                            <div class="countdown">
                                <div class="countdown-text">
                                    ⏰ Sınav süresi: <?php echo formatDateTime($exam['end_date']); ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="exam-info">
                            <div class="info-item">
                                <div class="info-label">Başlangıç</div>
                                <div class="info-value"><?php echo formatDateTime($exam['start_date']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Bitiş</div>
                                <div class="info-value"><?php echo formatDateTime($exam['end_date']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Süre</div>
                                <div class="info-value"><?php echo formatDuration($exam['duration']); ?></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Soru Sayısı</div>
                                <div class="info-value"><?php echo $exam['question_count']; ?></div>
                            </div>
                        </div>
                        
                        <div class="exam-actions">
                            <?php if ($exam['status'] === 'available'): ?>
                                <a href="take_exam.php?id=<?php echo $exam['id']; ?>" class="btn btn-success">
                                    🚀 Sınava Başla
                                </a>
                            <?php elseif ($exam['status'] === 'completed'): ?>
                                <a href="exam_result.php?id=<?php echo $exam['id']; ?>" class="btn">
                                    📊 Sonuçları Gör
                                </a>
                            <?php elseif ($exam['status'] === 'expired'): ?>
                                <a href="exam_result.php?id=<?php echo $exam['id']; ?>" class="btn btn-secondary">
                                    📋 Sonuçları Gör
                                </a>
                            <?php else: ?>
                                <button class="btn" disabled>
                                    ⏳ Henüz Başlamadı
                                </button>
                            <?php endif; ?>
                            
                            <button class="btn btn-secondary" onclick="showExamDetails(<?php echo $exam['id']; ?>)">
                                ℹ️ Detaylar
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
        <?php if (empty($exams)): ?>
            <div class="empty-state">
                <h3 id="noExamsTitle">📝 Henüz sınav atanmamış</h3>
                <p id="noExamsText">Size atanan sınav bulunmuyor. Eğitmeninizden sınav atanmasını isteyebilirsiniz.</p>
                <a href="dashboard.php" class="btn" style="margin-top: 20px;" id="btnBackToDashboard">Dashboard'a Dön</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Sınavlarım', userRole:'Öğrenci', logout:'Çıkış', back:'Dashboard\'a Dön',
                mainTitle:'📝 Sınavlarım', mainSubtitle:'Atanan sınavları görüntüleyin ve katılın',
                labelExamCode:'🔑 Sınav Kodu', btnJoin:'🚀 Sınava Gir',
                activeExamsTitle:'📚 Kurumunuzdaki Aktif Sınavlar', durationLabel1:'Süre:', questionsLabel1:'Soru:',
                durationUnit1:'dk', btnJoinExam1:'🚀 Bu Sınava Gir', noActiveExams:'Kurumunuzda aktif sınav bulunmuyor.',
                expiredExamsTitle:'⏰ Süresi Dolmuş Sınavlar', durationLabel3:'Süre:', questionsLabel3:'Soru:',
                durationUnit3:'dk', expiredTimeLabel:'Sona Erdi:', btnExpiredText:'Sonucu Gör',
                scheduledTimeLabel:'Planlanan:', btnWaitScheduledText:'Bekleniyor',
                scheduledExamsTitle:'📅 Planlanan Sınavlar', durationLabel2:'Süre:', questionsLabel2:'Soru:',
                durationUnit2:'dk', scheduledTimeLabel2:'Planlanan:', btnWaitScheduledText2:'Bekleniyor',
                statusLabel:'Durum:', statusAll:'Tümü', statusAvailable:'Mevcut', statusCompleted:'Tamamlandı',
                statusExpired:'Süresi Doldu', statusNotStarted:'Henüz Başlamadı', teacherLabel:'Eğitmen:',
                teacherAll:'Tümü', teacher1:'Ahmet Eğitmen', teacher2:'Ayşe Eğitmen', teacher3:'Mehmet Eğitmen', teacher4:'Fatma Eğitmen',
                btnFilter:'Filtrele', institutionExamsTitle:'🏫 Kurumumdaki Aktif Sınavlar',
                noInstitutionExams:'Şu anda kurumunuzda aktif bir sınav bulunmuyor.',
                noInstitutionExamsDesc:'Yeni sınavlar otomatik olarak burada görünecek.',
                btnJoinExam2:'🚀 Bu Sınava Gir', noExamsTitle:'📝 Henüz sınav atanmamış',
                noExamsText:'Size atanan sınav bulunmuyor. Eğitmeninizden sınav atanmasını isteyebilirsiniz.',
                btnBackToDashboard:'Dashboard\'a Dön', newExamNotification:'📚 Aktif sınav listesi güncellendi',
                labelStartDateInstitution:'Başlangıç', labelDurationInstitution:'Süre', labelQuestionInstitution:'Soru'
            };
            const de = {
                pageTitle:'Meine Prüfungen', userRole:'Schüler', logout:'Abmelden', back:'Zurück zum Dashboard',
                mainTitle:'📝 Meine Prüfungen', mainSubtitle:'Zeigen Sie zugewiesene Prüfungen an und nehmen Sie teil',
                labelExamCode:'🔑 Prüfungscode', btnJoin:'🚀 Zur Prüfung',
                activeExamsTitle:'📚 Aktive Prüfungen in Ihrer Institution', durationLabel1:'Dauer:', questionsLabel1:'Fragen:',
                durationUnit1:'Min', btnJoinExam1:'🚀 Zu dieser Prüfung', noActiveExams:'Keine aktiven Prüfungen in Ihrer Institution.',
                expiredExamsTitle:'⏰ Abgelaufene Prüfungen', durationLabel3:'Dauer:', questionsLabel3:'Fragen:',
                durationUnit3:'Min', expiredTimeLabel:'Beendet:', btnExpiredText:'Ergebnis anzeigen',
                scheduledTimeLabel:'Geplant:', btnWaitScheduledText:'Warten',
                scheduledExamsTitle:'📅 Geplante Prüfungen', durationLabel2:'Dauer:', questionsLabel2:'Fragen:',
                durationUnit2:'Min', scheduledTimeLabel2:'Geplant:', btnWaitScheduledText2:'Warten',
                statusLabel:'Status:', statusAll:'Alle', statusAvailable:'Verfügbar', statusCompleted:'Abgeschlossen',
                statusExpired:'Abgelaufen', statusNotStarted:'Noch nicht gestartet', teacherLabel:'Lehrer:',
                teacherAll:'Alle', teacher1:'Ahmet Lehrer', teacher2:'Ayşe Lehrer', teacher3:'Mehmet Lehrer', teacher4:'Fatma Lehrer',
                btnFilter:'Filtern', institutionExamsTitle:'🏫 Aktive Prüfungen in meiner Institution',
                noInstitutionExams:'Derzeit gibt es keine aktiven Prüfungen in Ihrer Institution.',
                noInstitutionExamsDesc:'Neue Prüfungen werden automatisch hier angezeigt.',
                btnJoinExam2:'🚀 Zu dieser Prüfung', noExamsTitle:'📝 Noch keine Prüfungen zugewiesen',
                noExamsText:'Ihnen wurden noch keine Prüfungen zugewiesen. Sie können Ihren Lehrer um Prüfungszuweisungen bitten.',
                btnBackToDashboard:'Zurück zum Dashboard', newExamNotification:'📚 Aktive Prüfungsliste aktualisiert',
                labelStartDateInstitution:'Beginn', labelDurationInstitution:'Dauer', labelQuestionInstitution:'Fragen'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){ 
                const d=lang==='de'?de:tr; 
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnLogout', d.logout);
                setText('#btnBack', d.back);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#labelExamCode', d.labelExamCode);
                setText('#btnJoin', d.btnJoin);
                setText('#activeExamsTitle', d.activeExamsTitle);
                setText('#expiredExamsTitle', d.expiredExamsTitle);
                setText('#durationLabel1', d.durationLabel1);
                setText('#questionsLabel1', d.questionsLabel1);
                setText('.durationUnit1', d.durationUnit1);
                setText('#scheduledTimeLabel', d.scheduledTimeLabel);
                setText('#btnWaitScheduledText', d.btnWaitScheduledText);
                setText('#scheduledExamsTitle', d.scheduledExamsTitle);
                setText('#durationLabel2', d.durationLabel2);
                setText('#questionsLabel2', d.questionsLabel2);
                setText('.durationUnit2', d.durationUnit2);
                setText('#scheduledTimeLabel2', d.scheduledTimeLabel2);
                setText('#btnWaitScheduledText2', d.btnWaitScheduledText2);
                setText('#btnJoinExam1', d.btnJoinExam1);
                setText('#durationLabel3', d.durationLabel3);
                setText('#questionsLabel3', d.questionsLabel3);
                setText('.durationUnit3', d.durationUnit3);
                setText('#expiredTimeLabel', d.expiredTimeLabel);
                setText('#btnExpiredText', d.btnExpiredText);
                setText('#noActiveExams', d.noActiveExams);
                setText('#statusLabel', d.statusLabel);
                setText('#statusAll', d.statusAll);
                setText('#statusAvailable', d.statusAvailable);
                setText('#statusCompleted', d.statusCompleted);
                setText('#statusExpired', d.statusExpired);
                setText('#statusNotStarted', d.statusNotStarted);
                setText('#teacherLabel', d.teacherLabel);
                setText('#teacherAll', d.teacherAll);
                setText('#teacher1', d.teacher1);
                setText('#teacher2', d.teacher2);
                setText('#teacher3', d.teacher3);
                setText('#teacher4', d.teacher4);
                setText('#btnFilter', d.btnFilter);
                setText('#institutionExamsTitle', '🏫 Kurumumda Yapmış Olduğum Sınavlar');
                setText('#noInstitutionExams', d.noInstitutionExams);
                setText('#noInstitutionExamsDesc', d.noInstitutionExamsDesc);
                setText('#btnJoinExam2', d.btnJoinExam2);
                setText('#noExamsTitle', d.noExamsTitle);
                setText('#noExamsText', d.noExamsText);
                setText('#btnBackToDashboard', d.btnBackToDashboard);
                setText('#labelStartDateInstitution', d.labelStartDateInstitution);
                setText('#labelDurationInstitution', d.labelDurationInstitution);
                setText('#labelQuestionInstitution', d.labelQuestionInstitution);
                
                const toggle=document.getElementById('langToggle'); 
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE'); 
                localStorage.setItem('lang_exams', lang); 
            }
            
            document.addEventListener('DOMContentLoaded', function(){ 
                const lang=localStorage.getItem('lang_exams')||localStorage.getItem('lang')||'tr'; 
                apply(lang); 
                const toggle=document.getElementById('langToggle'); 
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_exams')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                } 
            });
        })();

        // Sınav kodunu otomatik büyük harfe çevir
        (function(){
            var input = document.getElementById('exam_code');
            if (input) {
                input.addEventListener('input', function(e){ e.target.value = e.target.value.toUpperCase(); });
            }
        })();

        // Kurumumdaki aktif sınavlar için hafif otomatik yenileme (10 sn)
        var lastExamCount = <?php echo count($activeExams); ?>;
        var lastExamCodes = <?php echo json_encode(array_keys($activeExams)); ?>;
        var autoReloadInterval;
        var isPageVisible = true;

        document.addEventListener('visibilitychange', function(){
            isPageVisible = !document.hidden;
            if (isPageVisible) startAutoReload(); else stopAutoReload();
        });

        function startAutoReload(){
            if (autoReloadInterval) clearInterval(autoReloadInterval);
            autoReloadInterval = setInterval(function(){ if (isPageVisible) checkForNewExams(); }, 10000);
        }

        function stopAutoReload(){
            if (autoReloadInterval) { clearInterval(autoReloadInterval); autoReloadInterval = null; }
        }

        function checkForNewExams(){
            var url = window.location.pathname + '?ajax=1';
            fetch(url, { cache: 'no-store' })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (!data || typeof data.count === 'undefined') return;
                    var changed = (data.count !== lastExamCount) || (JSON.stringify(data.codes||[]) !== JSON.stringify(lastExamCodes||[]));
                    if (changed) { setTimeout(function(){ window.location.reload(); }, 200); }
                })
                .catch(function(){});
        }

        document.addEventListener('DOMContentLoaded', function(){
            startAutoReload();
            checkForNewExams();
        });

        window.addEventListener('beforeunload', function(){ stopAutoReload(); });

        // Aktif sınavları 10 sn'de bir kontrol et ve değişiklik varsa yenile
        (function(){
            var lastCount = <?php echo count($activeExams); ?>;
            var lastCodes = <?php echo json_encode(array_keys($activeExams)); ?>;
            function checkActive() {
                fetch(window.location.pathname + '?ajax_active=1', { cache: 'no-store' })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (!data) return;
                        var changed = (data.count !== lastCount) || (JSON.stringify(data.codes||[]) !== JSON.stringify(lastCodes||[]));
                        if (changed) {
                            // küçük bir bildirim gösterip yenile
                            try {
                                var n = document.createElement('div');
                                n.style.cssText = 'position:fixed;top:16px;right:16px;background:#067a5f;color:#fff;padding:10px 14px;border-radius:10px;box-shadow:0 8px 20px rgba(0,0,0,.15);z-index:1000;font-weight:600;';
                                const lang = localStorage.getItem('lang_exams')||localStorage.getItem('lang')||'tr';
                                n.textContent = lang === 'de' ? '📚 Aktive Prüfungsliste aktualisiert' : '📚 Aktif sınav listesi güncellendi';
                                document.body.appendChild(n);
                                setTimeout(function(){ location.reload(); }, 1000);
                                setTimeout(function(){ if(n&&n.parentNode){n.remove();} }, 2000);
                            } catch(e) { location.reload(); }
                        }
                    })
                    .catch(function(){});
            }
            setInterval(checkActive, 10000);
        })();
        function applyFilters() {
            const statusFilter = document.getElementById('status-filter').value;
            const teacherFilter = document.getElementById('teacher-filter').value;
            const examCards = document.querySelectorAll('.exam-card');
            
            examCards.forEach(card => {
                const cardStatus = card.getAttribute('data-status');
                const cardTeacher = card.getAttribute('data-teacher');
                
                let show = true;
                
                if (statusFilter && cardStatus !== statusFilter) {
                    show = false;
                }
                
                if (teacherFilter && cardTeacher !== teacherFilter) {
                    show = false;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        function showExamDetails(examId) {
            // Modal veya detay sayfası açılabilir
            alert('Sınav detayları: ID ' + examId);
        }
        
        // Sayfa yüklendiğinde filtreleri uygula
        document.addEventListener('DOMContentLoaded', function() {
            applyFilters();
        });

        // Kurum sınav kartından hızlı giriş
        function fillExamCode(examCode) {
            var input = document.getElementById('exam_code');
            if (input) {
                input.value = examCode;
                input.form && input.form.submit();
            }
        }
    </script>
</body>
</html>
