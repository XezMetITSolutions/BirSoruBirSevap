<?php
/**
 * Sınav Detayları Sayfası
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$examId = $_GET['id'] ?? '';
$exam = null;
$error = '';
$success = '';

// AJAX isteği kontrolü
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    // Sadece katılımcı sayısını döndür
    if (!empty($examId)) {
        require_once '../database.php';
        $db = Database::getInstance();
        $conn = $db->getConnection();
        
        $stmt = $conn->prepare("SELECT COUNT(*) FROM exam_results WHERE exam_id = :id");
        $stmt->execute([':id' => $examId]);
        $participants = $stmt->fetchColumn();
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'participants' => $participants
        ]);
        exit;
    } else {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Sınav bulunamadı'
        ]);
        exit;
    }
}

// URL parametrelerinden mesajları al
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $success = 'Sınav durumu başarıyla güncellendi.';
}

// Sınav bilgilerini yükle
if (!empty($examId)) {
    require_once '../database.php';
    $db = Database::getInstance();
    $conn = $db->getConnection();

    try {
        $stmt = $conn->prepare("SELECT * FROM exams WHERE exam_id = :exam_id");
        $stmt->execute([':exam_id' => $examId]);
        $exam = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($exam) {
            // JSON alanları decode et
            if (isset($exam['questions']) && is_string($exam['questions'])) {
                $exam['questions'] = json_decode($exam['questions'], true) ?? [];
            }
            if (isset($exam['categories']) && is_string($exam['categories'])) {
                $exam['categories'] = json_decode($exam['categories'], true) ?? [];
            }

            // Kurum kontrolü
            $user = $auth->getUser();
            $teacherBranch = $user['branch'] ?? $user['institution'] ?? '';
            $teacherClassSection = $user['class_section'] ?? $teacherBranch;
            $examClassSection = $exam['class_section'] ?? '';
            
            $canView = $auth->hasRole('superadmin') || 
                       ($examClassSection === $teacherBranch) || 
                       ($examClassSection === $teacherClassSection) ||
                       ($examClassSection === $user['institution']);
            
            if (!$canView) {
                $error = 'Bu sınavı görüntüleme yetkiniz yok.';
                $exam = null;
            }
        } else {
            $error = 'Sınav bulunamadı.';
        }
    } catch (Exception $e) {
        $error = 'Veritabanı hatası: ' . $e->getMessage();
    }
} else {
    $error = 'Geçersiz sınav ID.';
}

// Sınav durumu değiştirme
if (($_POST['action'] ?? '') === 'change_status' && $exam) {
    $newStatus = $_POST['new_status'] ?? '';
    if (!empty($newStatus)) {
        try {
            $stmt = $conn->prepare("UPDATE exams SET status = :status, updated_at = NOW() WHERE exam_id = :id");
            if ($stmt->execute([':status' => $newStatus, ':id' => $examId])) {
                header('Location: exam_details.php?id=' . $examId . '&updated=1');
                exit;
            } else {
                $error = 'Sınav durumu güncellenirken hata oluştu.';
            }
        } catch (Exception $e) {
            $error = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}

// Sınav silme
if (($_POST['action'] ?? '') === 'delete_exam' && $exam) {
    try {
        $stmt = $conn->prepare("DELETE FROM exams WHERE exam_id = :id");
        if ($stmt->execute([':id' => $examId])) {
            header('Location: exams.php?deleted=1');
            exit;
        } else {
            $error = 'Sınav silinirken hata oluştu.';
        }
    } catch (Exception $e) {
        $error = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

// Eğer sınavda sorular yoksa, kategorilerden yükle
if ($exam && empty($exam['questions'])) {
    require_once '../QuestionLoader.php';
    $questionLoader = new QuestionLoader();
    $questionLoader->loadQuestions();
    
    $filteredQuestions = [];
    
    // Çoklu kategori desteği
    if (isset($exam['categories']) && is_array($exam['categories'])) {
        foreach ($exam['categories'] as $categoryData) {
            $parts = explode('|', $categoryData);
            $bank = $parts[0] ?? '';
            $category = $parts[1] ?? '';
            
            $categoryQuestions = $questionLoader->getFilteredQuestions([
                'bank' => $bank,
                'category' => $category,
                'count' => 999 // Tüm soruları al
            ]);
            
            $filteredQuestions = array_merge($filteredQuestions, $categoryQuestions);
        }
    }
    
    // Sınav sorularını karıştır ve seç
    shuffle($filteredQuestions);
    $selectedQuestions = array_slice($filteredQuestions, 0, $exam['question_count'] ?? 10);
    
    if (!empty($selectedQuestions)) {
        // Sınav verilerine soruları ekle
        $exam['questions'] = $selectedQuestions;
        
        // Güncellenmiş sınav verilerini kaydet
        $allExams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
        $allExams[$examId]['questions'] = $selectedQuestions;
        file_put_contents('../data/exams.json', json_encode($allExams, JSON_PRETTY_PRINT));
    }
}

// Sınav sonuçlarını yükle
$examResults = [];
$participants = 0;
$averageScore = 0;

if ($exam) {
    try {
        $stmt = $conn->prepare("SELECT score FROM exam_results WHERE exam_id = :exam_id");
        $stmt->execute([':exam_id' => $examId]);
        $examResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $participants = count($examResults);
        
        if ($participants > 0) {
            $totalScore = array_sum(array_column($examResults, 'score'));
            $averageScore = round($totalScore / $participants, 1);
        }
    } catch (Exception $e) {
        // Hata durumunda varsayılan 0 kalır
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Detayları - <?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?></title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-color: #089473;
            --primary-dark: #067a5f;
            --primary-light: #0aa67a;
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
        
        .lang-toggle {
            position: absolute;
            top: 20px;
            right: 20px;
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
        }
        
        .lang-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
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
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
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

        .exam-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px;
            background: var(--secondary-color);
            border-radius: 12px;
            border-left: 4px solid var(--primary-color);
        }

        .info-item i {
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        .info-item span {
            font-weight: 600;
            color: var(--text-dark);
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
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

        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color) 0%, #e0a800 100%);
            color: #212529;
            box-shadow: 0 4px 15px rgba(255, 193, 7, 0.3);
        }

        .btn-warning:hover {
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.4);
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info-color) 0%, #138496 100%);
            box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
        }

        .btn-info:hover {
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.4);
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

        .action-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            margin-top: 20px;
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
            border-radius: 16px;
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

        .questions-list {
            max-height: 400px;
            overflow-y: auto;
            padding-right: 15px;
        }

        .questions-list::-webkit-scrollbar {
            width: 6px;
        }

        .questions-list::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .questions-list::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: 3px;
        }

        .question-item {
            padding: 15px;
            border: 1px solid #e9ecef;
            border-radius: 12px;
            margin-bottom: 10px;
            background: var(--secondary-color);
            transition: all 0.3s ease;
        }

        .question-item:hover {
            border-color: var(--primary-color);
            background: rgba(8, 148, 115, 0.05);
        }

        .question-item h4 {
            color: var(--text-dark);
            margin-bottom: 8px;
            font-size: 1rem;
        }

        .question-item p {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .exam-info {
                grid-template-columns: 1fr;
            }
            
            .header { padding: 28px 16px; }
            .header h1 { font-size: 1.7rem; }
            .back-button { position: static; padding: 10px 14px; border-radius: 16px; font-size: .95rem; margin-bottom: 10px; }
            .lang-toggle { top: 16px; right: 16px; padding: 8px 12px; border-radius: 12px; }
            .header-content { flex-wrap: wrap; gap: 10px; }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .header { padding: 20px 12px; }
            .header h1 { font-size: 1.4rem; }
            .lang-toggle { padding: 6px 10px; font-size: .85rem; }
            .back-button { padding: 8px 12px; font-size: .85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($exam): ?>
            <div class="header">
                <a href="exams.php" class="back-button" id="btnBack">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
                <button id="langToggle" class="lang-toggle">DE</button>
                <div class="header-content">
                    <h1><i class="fas fa-clipboard-list"></i> <?php echo htmlspecialchars($exam['title']); ?></h1>
                    <p id="examDescription"><?php echo htmlspecialchars($exam['description'] ?? 'Sınav detayları'); ?></p>
                </div>
            </div>

            <div class="nav-breadcrumb">
                <a href="dashboard.php" id="breadcrumbDashboard"><i class="fas fa-home"></i> Dashboard</a> 
                <i class="fas fa-chevron-right"></i> 
                <a href="exams.php" id="breadcrumbExams">Sınav Yönetimi</a>
                <i class="fas fa-chevron-right"></i> 
                <span id="breadcrumbCurrent">Sınav Detayları</span>
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
                    <h3><?php echo count($exam['questions'] ?? []); ?></h3>
                    <p><i class="fas fa-question-circle"></i> <span id="statTotalQuestions">Toplam Soru</span></p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $participants; ?></h3>
                    <p><i class="fas fa-users"></i> <span id="statParticipants">Katılımcı</span></p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $averageScore; ?>%</h3>
                    <p><i class="fas fa-chart-line"></i> <span id="statAverageScore">Ortalama Puan</span></p>
                </div>
                <div class="stat-card">
                    <h3><?php echo $exam['duration'] ?? 0; ?> dk</h3>
                    <p><i class="fas fa-clock"></i> <span id="statDuration">Süre</span></p>
                </div>
            </div>

            <div class="content-grid">
                <div class="card">
                    <h2><i class="fas fa-info-circle"></i> <span id="examInfoTitle">Sınav Bilgileri</span></h2>
                    
                    <div class="exam-info">
                        <div class="info-item">
                            <i class="fas fa-hashtag"></i>
                            <span>Sınav Kodu: <strong><?php echo htmlspecialchars($examId); ?></strong></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-user"></i>
                            <span>Öğretmen: <strong><?php echo htmlspecialchars($exam['teacher_name'] ?? 'Bilinmiyor'); ?></strong></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-building"></i>
                            <span id="institutionLabel">Kurum: <strong><?php echo htmlspecialchars($exam['class_section'] ?? 'Bilinmiyor'); ?></strong></span>
                        </div>
                        <div class="info-item">
                            <i class="fas fa-calendar"></i>
                            <span>Oluşturulma: <strong><?php echo date('d.m.Y H:i', strtotime($exam['created_at'] ?? 'now')); ?></strong></span>
                        </div>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <strong>Durum:</strong>
                        <span class="status-badge status-<?php echo $exam['status']; ?>">
                            <?php 
                            $statusText = '';
                            switch($exam['status']) {
                                case 'active': $statusText = 'Aktif'; break;
                                case 'completed': $statusText = 'Tamamlandı'; break;
                                case 'draft': $statusText = 'Taslak'; break;
                                default: $statusText = ucfirst($exam['status']);
                            }
                            echo $statusText;
                            ?>
                        </span>
                    </div>

                    <div class="action-buttons">
                        <!-- Durum Değiştirme -->
                        <?php if ($exam['status'] === 'draft'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="new_status" value="active">
                                <button type="submit" class="btn btn-success" 
                                        onclick="return confirm('Bu sınavı aktif hale getirmek istediğinizden emin misiniz?')">
                                    <i class="fas fa-play"></i> Aktif Et
                                </button>
                            </form>
                        <?php elseif ($exam['status'] === 'active'): ?>
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="change_status">
                                <input type="hidden" name="new_status" value="completed">
                                <button type="submit" class="btn btn-warning" 
                                        onclick="return confirm('Bu sınavı tamamlandı olarak işaretlemek istediğinizden emin misiniz?')">
                                    <i class="fas fa-check"></i> Tamamla
                                </button>
                            </form>
                        <?php endif; ?>
                        
                        <!-- Sonuçları Görüntüle -->
                        <a href="exam_results.php?exam_code=<?php echo $examId; ?>" class="btn btn-info">
                            <i class="fas fa-chart-bar"></i> Sonuçlar
                        </a>
                        
                        <!-- Düzenle -->
                        <a href="create_exam.php?edit=<?php echo $examId; ?>" class="btn btn-success">
                            <i class="fas fa-edit"></i> Düzenle
                        </a>
                        
                        <!-- Sil -->
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="delete_exam">
                            <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($examId); ?>">
                            <button type="submit" class="btn btn-danger" 
                                    onclick="return confirm('Bu sınavı silmek istediğinizden emin misiniz?')">
                                <i class="fas fa-trash"></i> Sil
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <h2><i class="fas fa-list"></i> Sınav Soruları</h2>
                    
                    <div class="questions-list">
                        <?php if (!empty($exam['questions'])): ?>
                            <?php foreach ($exam['questions'] as $index => $question): ?>
                                <div class="question-item">
                                    <h4>Soru <?php echo $index + 1; ?></h4>
                                    <p><?php echo htmlspecialchars($question['question'] ?? 'Soru metni yok'); ?></p>
                                    <small style="color: var(--text-light);">
                                        <?php echo count($question['options'] ?? []); ?> seçenek
                                    </small>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="text-align: center; color: var(--text-light); padding: 20px;">
                                Sınavda soru bulunmuyor.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        <?php else: ?>
            <div class="header">
                <a href="exams.php" class="back-button">
                    <i class="fas fa-arrow-left"></i>
                    Geri Dön
                </a>
                <div class="header-content">
                    <h1><i class="fas fa-exclamation-triangle"></i> Sınav Bulunamadı</h1>
                    <p>Belirtilen sınav bulunamadı veya görüntüleme yetkiniz yok.</p>
                </div>
            </div>

            <div class="nav-breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a> 
                <i class="fas fa-chevron-right"></i> 
                <a href="exams.php">Sınav Yönetimi</a>
                <i class="fas fa-chevron-right"></i> 
                <span>Sınav Detayları</span>
            </div>

            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>

            <div class="card">
                <h2><i class="fas fa-info-circle"></i> Ne Yapabilirsiniz?</h2>
                <ul style="margin-left: 20px; margin-top: 15px;">
                    <li>Sınav ID'sini kontrol edin</li>
                    <li>Sınavın sizin kurumunuza ait olduğundan emin olun</li>
                    <li><a href="exams.php" style="color: var(--primary-color);">Sınav listesine</a> dönün</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Arka plan yenileme sistemi
        let lastParticipantCount = <?php echo $participants; ?>;
        let isPageVisible = true;
        let refreshInterval;
        
        // Sayfa görünürlük kontrolü
        document.addEventListener('visibilitychange', function() {
            isPageVisible = !document.hidden;
            if (isPageVisible) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        // Otomatik yenileme başlat
        function startAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
            }
            
            refreshInterval = setInterval(function() {
                if (isPageVisible) {
                    refreshParticipantCount();
                }
            }, 5000); // 5 saniyede bir kontrol et
        }
        
        // Otomatik yenileme durdur
        function stopAutoRefresh() {
            if (refreshInterval) {
                clearInterval(refreshInterval);
                refreshInterval = null;
            }
        }
        
        // Katılımcı sayısını yenile
        function refreshParticipantCount() {
            fetch('exam_details.php?id=<?php echo $examId; ?>&ajax=1')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const newCount = data.participants;
                        const currentCount = parseInt(document.querySelector('.stat-card h3').textContent);
                        
                        if (newCount > currentCount) {
                            // Yeni katılımcı var, sayfayı yenile
                            showNotification('Yeni katılımcı eklendi! Sayfa yenileniyor...', 'success');
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else if (newCount !== currentCount) {
                            // Katılımcı sayısı değişti, güncelle
                            updateParticipantStats(data);
                        }
                    }
                })
                .catch(error => {
                    // Hata durumunda sessizce devam et
                });
        }
        
        // Katılımcı istatistiklerini güncelle
        function updateParticipantStats(data) {
            const statCards = document.querySelectorAll('.stat-card');
            if (statCards.length >= 2) {
                statCards[1].querySelector('h3').textContent = data.participants;
            }
        }
        
        // Bildirim göster
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#d4edda' : '#d1ecf1'};
                color: ${type === 'success' ? '#155724' : '#0c5460'};
                padding: 15px 20px;
                border-radius: 10px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 1000;
                font-weight: 600;
                animation: slideIn 0.3s ease;
            `;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(notification);
                }, 300);
            }, 3000);
        }
        
        // CSS animasyonları
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
        
        // TR/DE dil desteği
        (function(){
            const tr = {
                btnBack:'Geri Dön', examDescription:'Sınav detayları',
                breadcrumbDashboard:'Dashboard', breadcrumbExams:'Sınav Yönetimi', breadcrumbCurrent:'Sınav Detayları',
                statTotalQuestions:'Toplam Soru', statParticipants:'Katılımcı', statAverageScore:'Ortalama Puan', statDuration:'Süre',
                examInfoTitle:'Sınav Bilgileri', examQuestionsTitle:'Sınav Soruları',
                examCode:'Sınav Kodu:', teacher:'Öğretmen:', institution:'Şube:', created:'Oluşturulma:',
                status:'Durum:', statusActive:'Aktif', statusCompleted:'Tamamlandı', statusDraft:'Taslak',
                btnActivate:'Aktif Et', btnComplete:'Tamamla', btnResults:'Sonuçlar', btnEdit:'Düzenle', btnDelete:'Sil',
                confirmActivate:'Bu sınavı aktif hale getirmek istediğinizden emin misiniz?',
                confirmComplete:'Bu sınavı tamamlandı olarak işaretlemek istediğinizden emin misiniz?',
                confirmDelete:'Bu sınavı silmek istediğinizden emin misiniz?',
                noQuestions:'Sınavda soru bulunmuyor.', questionCount:'seçenek',
                newParticipant:'Yeni katılımcı eklendi! Sayfa yenileniyor...'
            };
            const de = {
                btnBack:'Zurück', examDescription:'Prüfungsdetails',
                breadcrumbDashboard:'Dashboard', breadcrumbExams:'Prüfungsverwaltung', breadcrumbCurrent:'Prüfungsdetails',
                statTotalQuestions:'Gesamt Fragen', statParticipants:'Teilnehmer', statAverageScore:'Durchschnittspunktzahl', statDuration:'Zeit',
                examInfoTitle:'Prüfungsinformationen', examQuestionsTitle:'Prüfungsfragen',
                examCode:'Prüfungscode:', teacher:'Lehrer:', institution:'Kompetenzstelle:', created:'Erstellt:',
                status:'Status:', statusActive:'Aktiv', statusCompleted:'Abgeschlossen', statusDraft:'Entwurf',
                btnActivate:'Aktivieren', btnComplete:'Abschließen', btnResults:'Ergebnisse', btnEdit:'Bearbeiten', btnDelete:'Löschen',
                confirmActivate:'Sind Sie sicher, dass Sie diese Prüfung aktivieren möchten?',
                confirmComplete:'Sind Sie sicher, dass Sie diese Prüfung als abgeschlossen markieren möchten?',
                confirmDelete:'Sind Sie sicher, dass Sie diese Prüfung löschen möchten?',
                noQuestions:'Keine Fragen in der Prüfung.', questionCount:'Optionen',
                newParticipant:'Neuer Teilnehmer hinzugefügt! Seite wird aktualisiert...'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#btnBack', d.btnBack);
                setText('#examDescription', d.examDescription);
                setText('#breadcrumbDashboard', d.breadcrumbDashboard);
                setText('#breadcrumbExams', d.breadcrumbExams);
                setText('#breadcrumbCurrent', d.breadcrumbCurrent);
                setText('#statTotalQuestions', d.statTotalQuestions);
                setText('#statParticipants', d.statParticipants);
                setText('#statAverageScore', d.statAverageScore);
                setText('#statDuration', d.statDuration);
                setText('#examInfoTitle', d.examInfoTitle);
                setText('#examQuestionsTitle', d.examQuestionsTitle);
                setHTML('#institutionLabel', d.institution + ' <strong><?php echo htmlspecialchars($exam['class_section'] ?? 'Bilinmiyor'); ?></strong>');
                
                // Durum metinlerini güncelle
                const statusBadges = document.querySelectorAll('.status-badge');
                statusBadges.forEach(badge => {
                    const text = badge.textContent.trim();
                    if (text === 'Aktif' || text === 'Aktiv') {
                        badge.textContent = d.statusActive;
                    } else if (text === 'Tamamlandı' || text === 'Abgeschlossen') {
                        badge.textContent = d.statusCompleted;
                    } else if (text === 'Taslak' || text === 'Entwurf') {
                        badge.textContent = d.statusDraft;
                    }
                });
                
                // Buton metinlerini güncelle
                const buttons = document.querySelectorAll('.btn');
                buttons.forEach(btn => {
                    const text = btn.textContent.trim();
                    if (text.includes('Aktif Et') || text.includes('Aktivieren')) {
                        btn.innerHTML = `<i class="fas fa-play"></i> ${d.btnActivate}`;
                    } else if (text.includes('Tamamla') || text.includes('Abschließen')) {
                        btn.innerHTML = `<i class="fas fa-check"></i> ${d.btnComplete}`;
                    } else if (text.includes('Sonuçlar') || text.includes('Ergebnisse')) {
                        btn.innerHTML = `<i class="fas fa-chart-bar"></i> ${d.btnResults}`;
                    } else if (text.includes('Düzenle') || text.includes('Bearbeiten')) {
                        btn.innerHTML = `<i class="fas fa-edit"></i> ${d.btnEdit}`;
                    } else if (text.includes('Sil') || text.includes('Löschen')) {
                        btn.innerHTML = `<i class="fas fa-trash"></i> ${d.btnDelete}`;
                    }
                });
                
                // Onclick event'lerini güncelle
                const activateBtn = document.querySelector('button[onclick*="aktif"]');
                if (activateBtn) {
                    activateBtn.setAttribute('onclick', `return confirm('${d.confirmActivate}')`);
                }
                
                const completeBtn = document.querySelector('button[onclick*="tamamlandı"]');
                if (completeBtn) {
                    completeBtn.setAttribute('onclick', `return confirm('${d.confirmComplete}')`);
                }
                
                const deleteBtn = document.querySelector('button[onclick*="silmek"]');
                if (deleteBtn) {
                    deleteBtn.setAttribute('onclick', `return confirm('${d.confirmDelete}')`);
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_exam_details', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_exam_details')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_exam_details')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();

        // Sayfa yüklendiğinde otomatik yenilemeyi başlat
        document.addEventListener('DOMContentLoaded', function() {
            if (isPageVisible) {
                startAutoRefresh();
            }
        });
        
        // Sayfa kapatılırken temizlik yap
        window.addEventListener('beforeunload', function() {
            stopAutoRefresh();
        });
    </script>
</body>
</html>
