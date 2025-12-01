<?php
/**
 * Öğretmen - Sınav Yönetimi
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';


// Sınav durumu değiştirme (önce kontrol et)
if ($_POST['action'] ?? '' === 'change_status') {
    $examId = $_POST['exam_id'] ?? '';
    $newStatus = $_POST['new_status'] ?? '';
    
    if (!empty($examId) && !empty($newStatus)) {
        // Mevcut sınavları yükle
        $allExams = [];
        if (file_exists('../data/exams.json')) {
            $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
        }
        
        if (isset($allExams[$examId])) {
            $allExams[$examId]['status'] = $newStatus;
            $allExams[$examId]['updated_at'] = date('Y-m-d H:i:s');
            
            // Dosyaya kaydet
            if (file_put_contents('../data/exams.json', json_encode($allExams, JSON_PRETTY_PRINT))) {
                $success = 'Sınav durumu başarıyla güncellendi.';
            } else {
                $error = 'Sınav durumu güncellenirken hata oluştu.';
            }
        } else {
            $error = 'Sınav bulunamadı. Lütfen sayfayı yenileyin ve tekrar deneyin.';
        }
    } else {
        $error = 'Sınav ID veya durum bilgisi eksik.';
    }
}

// Alternatif silme akışı: GET ile (liste linklerinden) silme desteği
elseif (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['exam_id'])) {
    $examId = trim($_GET['exam_id']);
    if ($examId !== '') {
        $allExams = [];
        if (file_exists('../data/exams.json')) {
            $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
        }
        if (isset($allExams[$examId])) {
            unset($allExams[$examId]);
            if (file_put_contents('../data/exams.json', json_encode($allExams, JSON_PRETTY_PRINT))) {
                $success = 'Sınav başarıyla silindi.';
            } else {
                $error = 'Sınav silinirken hata oluştu.';
            }
        } else {
            $error = 'Sınav bulunamadı. Lütfen sayfayı yenileyin ve tekrar deneyin.';
        }
    } else {
        $error = 'Sınav ID eksik.';
    }
}

// Sınav silme (durum değiştirmeden sonra kontrol et)
elseif ($_POST['action'] ?? '' === 'delete_exam') {
    $examId = $_POST['exam_id'] ?? '';
    if (!empty($examId)) {
        // Mevcut sınavları yükle
        $allExams = [];
        if (file_exists('../data/exams.json')) {
            $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
        }
        
        // Sınavı sil
        if (isset($allExams[$examId])) {
            unset($allExams[$examId]);
            
            // Dosyaya kaydet
            if (file_put_contents('../data/exams.json', json_encode($allExams, JSON_PRETTY_PRINT))) {
                $success = 'Sınav başarıyla silindi.';
            } else {
                $error = 'Sınav silinirken hata oluştu.';
            }
        } else {
            $error = 'Sınav bulunamadı. Lütfen sayfayı yenileyin ve tekrar deneyin.';
        }
    } else {
        $error = 'Sınav ID eksik.';
    }
}

// Gerçek sınavları yükle
$exams = [];

if (file_exists('../data/exams.json')) {
    $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
    
    // Sadece bu öğretmenin kurumundaki sınavları al
    $user = $auth->getUser();
    $teacherBranch = $user['branch'] ?? $user['institution'] ?? '';
    $teacherClassSection = $user['class_section'] ?? $teacherBranch;
    
    foreach ($allExams as $examCode => $exam) {
        // Sınavın class_section'ını kontrol et
        $examClassSection = $exam['class_section'] ?? '';
        
        // Superadmin tüm sınavları görebilir, diğerleri sadece kendi kurumundakileri
        $canView = $auth->hasRole('superadmin') || 
                   ($examClassSection === $teacherBranch) || 
                   ($examClassSection === $teacherClassSection) ||
                   ($examClassSection === $user['institution']);
        
        if ($canView) {
            // Sınav sonuçlarını hesapla
            $participants = 0;
            $totalScore = 0;
            $averageScore = 0;
            
            if (file_exists('../data/exam_results.json')) {
                $allResults = json_decode(file_get_contents('../data/exam_results.json'), true) ?? [];
                $examResults = $allResults[$examCode] ?? [];
                $participants = count($examResults);
                
                if ($participants > 0) {
                    $totalScore = array_sum(array_column($examResults, 'score'));
                    $averageScore = round($totalScore / $participants, 1);
                }
            }
            
            $exams[] = [
                'id' => $examCode,
                'name' => $exam['title'] ?? 'Sınav',
                'description' => $exam['description'] ?? '',
                'question_count' => count($exam['questions'] ?? []),
                'time_limit' => $exam['duration'] ?? 30,
                'negative_marking' => $exam['negative_marking'] ?? 0,
                'created_at' => $exam['created_at'] ?? date('Y-m-d'),
                'status' => $exam['status'] ?? 'draft',
                'participants' => $participants,
                'average_score' => $averageScore
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Yönetimi - Öğretmen</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #068567;
            --primary-dark: #055a4a;
            --primary-light: #077a5f;
            --secondary-color: #f8f9fa;
            --accent-color: #ff6b35;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #17a2b8;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 24px;
            padding: 40px;
            margin-bottom: 30px;
            box-shadow: 0 20px 40px rgba(8, 148, 115, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255, 255, 255, 0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .header-content {
            position: relative;
            z-index: 2;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .header h1 {
            font-size: 3rem;
            font-weight: 700;
            margin-bottom: 15px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
            font-weight: 300;
        }
        .lang-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(255,255,255,0.2);
            color: white;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 16px;
            border-radius: 20px;
            cursor: pointer;
        }

        .back-button {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 50px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateX(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .nav-breadcrumb {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border-left: 4px solid var(--primary-color);
        }

        .nav-breadcrumb a {
            color: var(--primary-color);
            text-decoration: none;
            margin-right: 15px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .nav-breadcrumb a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 35px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(8, 148, 115, 0.1);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.12);
        }

        .card h2 {
            color: var(--text-dark);
            margin-bottom: 25px;
            font-size: 2rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(8, 148, 115, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color) 0%, #c82333 100%);
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }

        .btn-danger:hover {
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color) 0%, #1e7e34 100%);
            box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
        }

        .btn-success:hover {
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #138496 100%);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }

        .btn-info:hover {
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #e0a800 100%);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        .exams-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .exams-table th {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 20px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .exams-table td {
            padding: 20px 15px;
            border-bottom: 1px solid #f1f3f4;
            vertical-align: middle;
        }

        .exams-table tr:hover {
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.05) 0%, rgba(8, 148, 115, 0.02) 100%);
        }

        .exams-table tr:last-child td {
            border-bottom: none;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-active {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .status-completed {
            background: linear-gradient(135deg, #cce5ff 0%, #b3d7ff 100%);
            color: #004085;
            border: 1px solid #b3d7ff;
        }

        .status-draft {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
            border: 1px solid #ffeaa7;
        }

        .alert {
            padding: 20px 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            font-weight: 500;
            border-left: 4px solid;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-error {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            border-left-color: var(--danger-color);
        }

        .alert-success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            border-left-color: var(--success-color);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(8, 148, 115, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.12);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .stat-card p {
            color: var(--text-light);
            font-weight: 500;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-buttons .btn {
            padding: 8px 16px;
            font-size: 0.85rem;
            border-radius: 8px;
        }

        .exam-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .exam-description {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2.2rem;
            }
            
            .header { padding: 24px 16px; }
            .header-content { flex-wrap: wrap; gap: 10px; }
            .lang-toggle { padding: 8px 12px; border-radius: 12px; }
            .back-button { padding: 10px 14px; border-radius: 16px; font-size: .95rem; }
            
            .back-button {
                position: static;
                margin-bottom: 20px;
            }
            
            .exams-table {
                font-size: 0.9rem;
            }
            
            .exams-table th,
            .exams-table td {
                padding: 15px 10px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .header { padding: 18px 12px; }
            .header h1 { font-size: 1.6rem; }
            .lang-toggle { padding: 6px 10px; font-size: .85rem; }
            .back-button { padding: 8px 12px; font-size: .85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="dashboard.php" class="back-button" id="btnBack">
                <i class="fas fa-arrow-left"></i>
                <span id="backText">Geri Dön</span>
            </a>
            <div class="header-content">
                <h1 id="mainTitle"><i class="fas fa-clipboard-list"></i> Sınav Yönetimi</h1>
                <p id="mainSubtitle">Sınavlarınızı oluşturun ve yönetin</p>
                <button id="langToggle" class="lang-toggle">DE</button>
            </div>
        </div>

        <div class="nav-breadcrumb">
            <a href="dashboard.php" id="breadcrumbHome"><i class="fas fa-home"></i> Dashboard</a> 
            <i class="fas fa-chevron-right"></i> 
            <span id="breadcrumbCurrent">Sınav Yönetimi</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo count($exams); ?></h3>
                <p id="statLabel1"><i class="fas fa-clipboard-list"></i> Toplam Sınav</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($exams, fn($e) => $e['status'] === 'active')); ?></h3>
                <p id="statLabel2"><i class="fas fa-play-circle"></i> Aktif Sınav</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($exams, fn($e) => $e['status'] === 'completed')); ?></h3>
                <p id="statLabel3"><i class="fas fa-check-circle"></i> Tamamlanan</p>
            </div>
            <div class="stat-card">
                <h3><?php echo array_sum(array_column($exams, 'participants')); ?></h3>
                <p id="statLabel4"><i class="fas fa-users"></i> Toplam Katılımcı</p>
            </div>
        </div>

        <div class="content-grid">

            <div class="card">
                <h2 id="listTitle"><i class="fas fa-list-alt"></i> Sınav Listesi</h2>
                <table class="exams-table">
                    <thead>
                        <tr>
                            <th id="thName"><i class="fas fa-file-alt"></i> Sınav Adı</th>
                            <th id="thStatus"><i class="fas fa-info-circle"></i> Durum</th>
                            <th id="thQuestions"><i class="fas fa-question-circle"></i> Sorular</th>
                            <th id="thDuration"><i class="fas fa-clock"></i> Süre</th>
                            <th id="thParticipants"><i class="fas fa-users"></i> Katılımcı</th>
                            <th id="thAverage"><i class="fas fa-chart-line"></i> Ortalama</th>
                            <th id="thActions"><i class="fas fa-cogs"></i> İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($exams as $exam): ?>
                            <tr>
                                <td>
                                    <div class="exam-name"><?php echo htmlspecialchars($exam['name']); ?></div>
                                    <div class="exam-description"><?php echo htmlspecialchars($exam['description']); ?></div>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $exam['status']; ?>">
                                        <?php 
                                        $statusText = '';
                                        switch($exam['status']) {
                                            case 'active': $statusText = '<span class="statusTextActive">Aktif</span>'; break;
                                            case 'completed': $statusText = '<span class="statusTextCompleted">Tamamlandı</span>'; break;
                                            case 'draft': $statusText = '<span class="statusTextDraft">Taslak</span>'; break;
                                            default: $statusText = ucfirst($exam['status']);
                                        }
                                        echo $statusText;
                                        ?>
                                    </span>
                                </td>
                                <td><strong><?php echo $exam['question_count']; ?></strong></td>
                                <td><strong><?php echo $exam['time_limit']; ?> <span id="minutesUnit">dk</span></strong></td>
                                <td><strong><?php echo $exam['participants']; ?></strong></td>
                                <td><strong><?php echo $exam['average_score']; ?>%</strong></td>
                                <td>
                                    <div class="action-buttons">
                                        <!-- Durum Değiştirme -->
                                        <?php if ($exam['status'] === 'draft'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <input type="hidden" name="new_status" value="active">
                                                <button type="submit" class="btn btn-success" id="btnActivate"
                                                        onclick="return confirm('Bu sınavı aktif hale getirmek istediğinizden emin misiniz?')">
                                                    <i class="fas fa-play"></i> <span class="btnActivateText">Aktif Et</span>
                                                </button>
                                            </form>
                                        <?php elseif ($exam['status'] === 'active'): ?>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="change_status">
                                                <input type="hidden" name="exam_id" value="<?php echo $exam['id']; ?>">
                                                <input type="hidden" name="new_status" value="completed">
                                                <button type="submit" class="btn btn-warning" id="btnComplete"
                                                        onclick="return confirm('Bu sınavı tamamlandı olarak işaretlemek istediğinizden emin misiniz?')">
                                                    <i class="fas fa-check"></i> <span class="btnCompleteText">Tamamla</span>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        
                                        <!-- Sonuçları Görüntüle -->
                                        <a href="exam_results.php?exam_code=<?php echo $exam['id']; ?>" 
                                           class="btn btn-info" id="btnResults">
                                            <i class="fas fa-chart-bar"></i> <span class="btnResultsText">Sonuçlar</span>
                                        </a>
                                        
                                        <!-- Görüntüle -->
                                        <a href="exam_details.php?id=<?php echo $exam['id']; ?>" 
                                           class="btn btn-success" id="btnView">
                                            <i class="fas fa-eye"></i> <span class="btnViewText">Görüntüle</span>
                                        </a>
                                        
                                        <!-- Sil -->
                                        <a href="exams.php?action=delete&exam_id=<?php echo urlencode($exam['id']); ?>" class="btn btn-danger"
                                           onclick="return confirm('Bu sınavı silmek istediğinizden emin misiniz?')">
                                            <i class="fas fa-trash"></i> <span class="btnDeleteText">Sil</span>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        (function(){
            const tr = {
                backText:'Geri Dön', mainTitle:'Sınav Yönetimi', mainSubtitle:'Sınavlarınızı oluşturun ve yönetin',
                breadcrumbHome:'Dashboard', breadcrumbCurrent:'Sınav Yönetimi',
                statLabel1:'Toplam Sınav', statLabel2:'Aktif Sınav', statLabel3:'Tamamlanan', statLabel4:'Toplam Katılımcı',
                listTitle:'Sınav Listesi', thName:'Sınav Adı', thStatus:'Durum', thQuestions:'Sorular', thDuration:'Süre', thParticipants:'Katılımcı', thAverage:'Ortalama', thActions:'İşlemler',
                minutesUnit:'dk', statusActive:'Aktif', statusCompleted:'Tamamlandı', statusDraft:'Taslak',
                btnActivate:'Aktif Et', btnComplete:'Tamamla', btnResults:'Sonuçlar', btnView:'Görüntüle', btnDelete:'Sil'
            };
            const de = {
                backText:'Zurück', mainTitle:'Prüfungsverwaltung', mainSubtitle:'Erstellen und verwalten Sie Ihre Prüfungen',
                breadcrumbHome:'Dashboard', breadcrumbCurrent:'Prüfungsverwaltung',
                statLabel1:'Gesamt Prüfungen', statLabel2:'Aktive Prüfungen', statLabel3:'Abgeschlossen', statLabel4:'Gesamt Teilnehmende',
                listTitle:'Prüfungsliste', thName:'Prüfungsname', thStatus:'Status', thQuestions:'Fragen', thDuration:'Zeit', thParticipants:'Teilnehmende', thAverage:'Durchschnitt', thActions:'Aktionen',
                minutesUnit:'Min', statusActive:'Aktiv', statusCompleted:'Abgeschlossen', statusDraft:'Entwurf',
                btnActivate:'Aktivieren', btnComplete:'Abschließen', btnResults:'Ergebnisse', btnView:'Ansehen', btnDelete:'Löschen'
            };
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#backText', d.backText);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#breadcrumbHome', d.breadcrumbHome);
                setText('#breadcrumbCurrent', d.breadcrumbCurrent);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#listTitle', d.listTitle);
                setText('#thName', d.thName);
                setText('#thStatus', d.thStatus);
                setText('#thQuestions', d.thQuestions);
                setText('#thDuration', d.thDuration);
                setText('#thParticipants', d.thParticipants);
                setText('#thAverage', d.thAverage);
                setText('#thActions', d.thActions);
                setText('#minutesUnit', d.minutesUnit);
                document.querySelectorAll('.statusTextActive').forEach(e=>e.innerText=d.statusActive);
                document.querySelectorAll('.statusTextCompleted').forEach(e=>e.innerText=d.statusCompleted);
                document.querySelectorAll('.statusTextDraft').forEach(e=>e.innerText=d.statusDraft);
                document.querySelectorAll('.btnActivateText').forEach(e=>e.innerText=d.btnActivate);
                document.querySelectorAll('.btnCompleteText').forEach(e=>e.innerText=d.btnComplete);
                document.querySelectorAll('.btnResultsText').forEach(e=>e.innerText=d.btnResults);
                document.querySelectorAll('.btnViewText').forEach(e=>e.innerText=d.btnView);
                document.querySelectorAll('.btnDeleteText').forEach(e=>e.innerText=d.btnDelete);
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_teacher_exams', lang);
            }
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_teacher_exams')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ toggle.addEventListener('click', function(){ const next=(localStorage.getItem('lang_teacher_exams')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; apply(next); }); }
            });
        })();
    </script>

</body>
</html>
