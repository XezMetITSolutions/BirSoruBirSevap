<?php
session_start();
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Eğitmen kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Sınavları yükle
$exams = [];
if (file_exists('../data/exams.json')) {
    $exams = json_decode(file_get_contents('../data/exams.json'), true) ?? [];
}

// Sınav silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['exam_id'])) {
    $examId = $_GET['exam_id'];
    if (isset($exams[$examId])) {
        unset($exams[$examId]);
        file_put_contents('../data/exams.json', json_encode($exams, JSON_PRETTY_PRINT));
        header('Location: exam_management.php?deleted=1');
        exit;
    }
}

// Tüm sınavları silme işlemi
if (isset($_GET['action']) && $_GET['action'] === 'delete_all') {
    $exams = [];
    file_put_contents('../data/exams.json', json_encode($exams, JSON_PRETTY_PRINT));
    header('Location: exam_management.php?deleted_all=1');
    exit;
}

// Sınav durumu değiştirme
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && isset($_GET['exam_id'])) {
    $examId = $_GET['exam_id'];
    if (isset($exams[$examId])) {
        $exams[$examId]['status'] = $exams[$examId]['status'] === 'active' ? 'inactive' : 'active';
        file_put_contents('../data/exams.json', json_encode($exams, JSON_PRETTY_PRINT));
        header('Location: exam_management.php');
        exit;
    }
}

// Tüm sınavları göster (aktif ve pasif)
$allExams = $exams;

// Eğitmenin sınavlarını filtrele (superadmin hariç)
if ($user['role'] !== 'superadmin') {
    $allExams = array_filter($allExams, function($exam) use ($user) {
        return $exam['teacher_id'] === $user['username'];
    });
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Yönetimi - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
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
            color: #2c3e50;
        }
        
        .logo p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Uzun adlar için ellipsis */
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .back-btn {
            background: rgba(6, 132, 102, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .back-btn:hover {
            background: #068466;
            color: white;
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .page-title {
            font-size: 3em;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            font-weight: 300;
        }
        
        .page-subtitle {
            font-size: 1.2em;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-bottom: 40px;
        }
        
        .action-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 25px;
            font-size: 1.1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
        }
        
        .exam-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }
        
        .exam-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }
        
        .exam-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .exam-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }
        
        .exam-title {
            font-size: 1.3em;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .exam-code {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: bold;
        }
        
        .exam-details {
            margin-bottom: 20px;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.95em;
        }
        
        .detail-label {
            color: #6c757d;
            font-weight: 500;
        }
        
        .detail-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .exam-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-small {
            padding: 8px 16px;
            border: none;
            border-radius: 15px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: #212529;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .status-active {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-inactive {
            color: #dc3545;
            font-weight: bold;
        }
        
        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: rgba(255, 255, 255, 0.8);
        }
        
        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 15px;
        }
        
        .empty-state p {
            font-size: 1.1em;
            margin-bottom: 25px;
        }
        
        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; flex-wrap: wrap; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .back-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .page-title {
                font-size: 2.2em;
            }
            
            .exam-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
        }

        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .back-btn { padding: 5px 8px; font-size: .85rem; }
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
                    <p id="pageTitle">Sınav Yönetimi</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBackToDashboard">← Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="mainTitle">📝 Sınav Yönetimi</h1>
            <p class="page-subtitle" id="mainSubtitle">Oluşturduğunuz sınavları yönetin</p>
        </div>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="success-message">
                <h3 id="deletedMessage">✅ Sınav Başarıyla Silindi!</h3>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['deleted_all'])): ?>
            <div class="success-message">
                <h3 id="deletedAllMessage">✅ Tüm Sınavlar Başarıyla Silindi!</h3>
            </div>
        <?php endif; ?>

        <div class="action-buttons">
            <a href="create_exam.php" class="action-btn btn-primary" id="btnCreateExam">📝 Yeni Sınav Oluştur</a>
            <a href="exam_management.php?action=delete_all" class="action-btn btn-danger" id="btnDeleteAll" onclick="return confirm('Tüm sınavları silmek istediğinizden emin misiniz?')">🗑️ Tüm Sınavları Sil</a>
            <a href="exam_prints.php" class="action-btn" id="btnPrints">📄 Geçmiş PDF Sınavlar</a>
        </div>

        <?php if (empty($allExams)): ?>
            <div class="empty-state">
                <h3 id="noExamsTitle">📚 Henüz Sınav Oluşturulmamış</h3>
                <p id="noExamsDesc">İlk sınavınızı oluşturmak için yukarıdaki butonu kullanın.</p>
                <a href="create_exam.php" class="action-btn btn-primary" id="btnCreateExam2">📝 Sınav Oluştur</a>
            </div>
        <?php else: ?>
            <div class="exam-grid">
                <?php foreach ($allExams as $examId => $exam): ?>
                    <div class="exam-card">
                        <div class="exam-header">
                            <div>
                                <div class="exam-title"><?php echo htmlspecialchars($exam['title']); ?></div>
                                <div class="detail-value status-<?php echo $exam['status']; ?>">
                                    <?php echo $exam['status'] === 'active' ? '🟢 <span id="activeText">Aktif</span>' : '🔴 <span id="inactiveText">Pasif</span>'; ?>
                                </div>
                            </div>
                            <div class="exam-code"><?php echo $examId; ?></div>
                        </div>
                        
                        <div class="exam-details">
                            <div class="detail-row">
                                <span class="detail-label" id="labelBranch">📚 Şube:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($exam['class_section']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label" id="labelQuestions">🔢 Soru Sayısı:</span>
                                <span class="detail-value"><?php echo $exam['question_count']; ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label" id="labelDuration">⏱️ Süre:</span>
                                <span class="detail-value"><?php echo $exam['duration']; ?> <span id="minutesUnit">dakika</span></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label" id="labelTeacher">👨‍🏫 Eğitmen:</span>
                                <span class="detail-value"><?php echo htmlspecialchars($exam['teacher_name']); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label" id="labelCreated">📅 Oluşturulma:</span>
                                <span class="detail-value"><?php echo date('d.m.Y H:i', strtotime($exam['created_at'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <span class="detail-label" id="labelTopics">📋 Konular:</span>
                                <span class="detail-value"><?php echo count($exam['categories']); ?> <span id="topicsUnit">konu</span></span>
                            </div>
                        </div>
                        
                        <div class="exam-actions">
                            <a href="exam_management.php?action=toggle_status&exam_id=<?php echo $examId; ?>" 
                               class="btn-small <?php echo $exam['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>">
                                <?php echo $exam['status'] === 'active' ? '⏸️ <span id="deactivateText">Pasifleştir</span>' : '▶️ <span id="activateText">Aktifleştir</span>'; ?>
                            </a>
                            <a href="exam_management.php?action=delete&exam_id=<?php echo $examId; ?>" 
                               class="btn-small btn-danger"
                               onclick="return confirm('Bu sınavı silmek istediğinizden emin misiniz?')">
                                🗑️ <span id="deleteText">Sil</span>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Sınav Yönetimi', userRole:'Eğitmen', btnBackToDashboard:'← Geri Dön',
                mainTitle:'📝 Sınav Yönetimi', mainSubtitle:'Oluşturduğunuz sınavları yönetin',
                deletedMessage:'✅ Sınav Başarıyla Silindi!', deletedAllMessage:'✅ Tüm Sınavlar Başarıyla Silindi!',
                btnCreateExam:'📝 Yeni Sınav Oluştur', btnDeleteAll:'🗑️ Tüm Sınavları Sil',
                noExamsTitle:'📚 Henüz Sınav Oluşturulmamış', noExamsDesc:'İlk sınavınızı oluşturmak için yukarıdaki butonu kullanın.',
                btnCreateExam2:'📝 Sınav Oluştur',
                activeText:'Aktif', inactiveText:'Pasif', labelBranch:'📚 Şube:', labelQuestions:'🔢 Soru Sayısı:',
                labelDuration:'⏱️ Süre:', labelTeacher:'👨‍🏫 Eğitmen:', labelCreated:'📅 Oluşturulma:', labelTopics:'📋 Konular:',
                minutesUnit:'dakika', topicsUnit:'konu', deactivateText:'Pasifleştir', activateText:'Aktifleştir', deleteText:'Sil'
            };
            const de = {
                pageTitle:'Prüfungsverwaltung', userRole:'Lehrpersonal', btnBackToDashboard:'← Zurück',
                mainTitle:'📝 Prüfungsverwaltung', mainSubtitle:'Verwalten Sie Ihre erstellten Prüfungen',
                deletedMessage:'✅ Prüfung erfolgreich gelöscht!', deletedAllMessage:'✅ Alle Prüfungen erfolgreich gelöscht!',
                btnCreateExam:'📝 Neue Prüfung erstellen', btnDeleteAll:'🗑️ Alle Prüfungen löschen',
                noExamsTitle:'📚 Noch keine Prüfungen erstellt', noExamsDesc:'Verwenden Sie die Schaltfläche oben, um Ihre erste Prüfung zu erstellen.',
                btnCreateExam2:'📝 Prüfung erstellen',
                activeText:'Aktiv', inactiveText:'Inaktiv', labelBranch:'📚 Zweig:', labelQuestions:'🔢 Fragenanzahl:',
                labelDuration:'⏱️ Zeit:', labelTeacher:'👨‍🏫 Lehrpersonal:', labelCreated:'📅 Erstellt:', labelTopics:'📋 Themen:',
                minutesUnit:'Minuten', topicsUnit:'Themen', deactivateText:'Deaktivieren', activateText:'Aktivieren', deleteText:'Löschen'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnBackToDashboard', d.btnBackToDashboard);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#deletedMessage', d.deletedMessage);
                setText('#deletedAllMessage', d.deletedAllMessage);
                setText('#btnCreateExam', d.btnCreateExam);
                setText('#btnDeleteAll', d.btnDeleteAll);
                setText('#noExamsTitle', d.noExamsTitle);
                setText('#noExamsDesc', d.noExamsDesc);
                setText('#btnCreateExam2', d.btnCreateExam2);
                setText('#activeText', d.activeText);
                setText('#inactiveText', d.inactiveText);
                setText('#labelBranch', d.labelBranch);
                setText('#labelQuestions', d.labelQuestions);
                setText('#labelDuration', d.labelDuration);
                setText('#labelTeacher', d.labelTeacher);
                setText('#labelCreated', d.labelCreated);
                setText('#labelTopics', d.labelTopics);
                setText('#minutesUnit', d.minutesUnit);
                setText('#topicsUnit', d.topicsUnit);
                setText('#deactivateText', d.deactivateText);
                setText('#activateText', d.activateText);
                setText('#deleteText', d.deleteText);
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_exam_management', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_exam_management')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_exam_management')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>
