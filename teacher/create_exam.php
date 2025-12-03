<?php
session_start();
require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Öğretmenin şube bilgisini al (öncelik: institution → branch → class_section)
$teacherSection = $user['institution'] ?? ($user['branch'] ?? ($user['class_section'] ?? 'Genel'));

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];

// Kategorileri grupla ve temizle
$groupedCategories = [];
foreach ($banks as $bank) {
    $bankCategories = $categories[$bank] ?? [];
    $groupedCategories[$bank] = [];
    
    foreach ($bankCategories as $category) {
        // Tüm dosya uzantılarını ve gereksiz kelimeleri temizle
        $cleanCategory = preg_replace('/_json\.json$|\.json$|_questions\.json$|_sorulari\.json$|_full\.json$|_full$/', '', $category);
        
        // Sayısal aralıkları kaldır
        $cleanCategory = preg_replace('/_(\d+)_(\d+)_json$/', '', $cleanCategory);
        $cleanCategory = preg_replace('/_(\d+)_(\d+)$/', '', $cleanCategory);
        $cleanCategory = preg_replace('/_(\d+)$/', '', $cleanCategory);
        
        // Alt çizgileri boşlukla değiştir
        $cleanCategory = str_replace('_', ' ', $cleanCategory);
        
        // Özel isim dönüşümleri
        $cleanCategory = str_replace('igmg', '', $cleanCategory);
        $cleanCategory = str_replace('itikat', 'İtikat', $cleanCategory);
        $cleanCategory = str_replace('ahlak', 'Ahlak', $cleanCategory);
        $cleanCategory = str_replace('ibadet', 'İbadet', $cleanCategory);
        $cleanCategory = str_replace('siyer', 'Siyer', $cleanCategory);
        $cleanCategory = str_replace('musiki', 'Musiki', $cleanCategory);
        $cleanCategory = str_replace('teskilat', 'Teşkilat', $cleanCategory);
        $cleanCategory = str_replace('hadis', 'Hadis', $cleanCategory);
        $cleanCategory = str_replace('hitabet', 'Hitabet', $cleanCategory);
        $cleanCategory = str_replace('insan haklari', 'İnsan Hakları', $cleanCategory);
        $cleanCategory = str_replace('islam tarihi', 'İslam Tarihi', $cleanCategory);
        $cleanCategory = str_replace('tasavvuf', 'Tasavvuf', $cleanCategory);
        $cleanCategory = str_replace('tefsir', 'Tefsir', $cleanCategory);
        $cleanCategory = str_replace('turkce', 'Türkçe', $cleanCategory);
        
        // "Sorulari" kelimesini kaldır
        $cleanCategory = str_replace('sorulari', '', $cleanCategory);
        $cleanCategory = str_replace('soruları', '', $cleanCategory);
        $cleanCategory = str_replace('sorular', '', $cleanCategory);
        
        // "Dersleri" kelimesini düzelt
        $cleanCategory = str_replace('dersleri', 'Dersleri', $cleanCategory);
        
        // Başlık formatına çevir
        $cleanCategory = ucwords($cleanCategory);
        
        // Çift boşlukları tek boşluğa çevir
        $cleanCategory = preg_replace('/\s+/', ' ', $cleanCategory);
        
        // Boşlukları temizle
        $cleanCategory = trim($cleanCategory);
        
        // Aynı konuyu birleştir
        if (!in_array($cleanCategory, $groupedCategories[$bank])) {
            $groupedCategories[$bank][] = $cleanCategory;
        }
    }
}

// Sınav oluşturma işlemi
$examCreated = false;
$examCode = '';
$errorMessage = '';

if ($_POST) {
    $examTitle = $_POST['exam_title'] ?? '';
    $questionCount = (int)($_POST['question_count'] ?? 10);
    $examDuration = (int)($_POST['exam_duration'] ?? 30);
    $selectedCategories = $_POST['categories'] ?? [];
    $examDescription = $_POST['exam_description'] ?? '';
    $scheduleType = $_POST['exam_schedule_type'] ?? 'immediate';
    $startDate = $_POST['exam_start_date'] ?? '';
    $startTime = $_POST['exam_start_time'] ?? '';
    
    // Soru türünü kontrol et
    $questionType = $_POST['question_type'] ?? 'random';
    
    if (empty($examTitle) || empty($questionCount)) {
        $errorMessage = 'Lütfen sınav başlığı ve soru sayısını girin.';
    } elseif ($questionType !== 'custom' && empty($selectedCategories)) {
        $errorMessage = 'Lütfen en az bir konu seçin.';
    } elseif ($scheduleType === 'scheduled' && (empty($startDate) || empty($startTime))) {
        $errorMessage = 'Planlanan sınav için tarih ve saat seçin.';
    } else {
        // Sınav kodu oluştur
        $examCode = strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Soruları yükle ve filtrele
        $questionLoader = new QuestionLoader();
        $questionLoader->loadQuestions();
        
        $selectedQuestions = [];
        
        if ($questionType === 'custom') {
            // Özel sorular için
            $customQuestions = $_POST['custom_questions'] ?? [];
            foreach ($customQuestions as $customQuestion) {
                if (!empty($customQuestion['question'])) {
                    $selectedQuestions[] = [
                        'id' => 'custom_' . uniqid(),
                        'question' => $customQuestion['question'],
                        'type' => $customQuestion['type'] ?? 'mcq',
                        'options' => $customQuestion['options'] ?? [],
                        'correct_answer' => $customQuestion['correct_answer'] ?? '',
                        'explanation' => $customQuestion['explanation'] ?? '',
                        'difficulty' => 'Orta',
                        'bank' => 'Özel',
                        'category' => 'Özel Sorular'
                    ];
                }
            }
        } else {
            // Rastgele veya manuel seçim için
        $filteredQuestions = [];
        
            if ($questionType === 'manual') {
                // Manuel seçim için seçilen soruları al
                $selectedQuestionIds = $_POST['selected_questions'] ?? [];
                $allQuestions = $_SESSION['all_questions'] ?? [];
                
                foreach ($allQuestions as $question) {
                    if (in_array($question['id'], $selectedQuestionIds)) {
                        $filteredQuestions[] = $question;
                    }
                }
            } else {
                // Rastgele için kategori desteği
        foreach ($selectedCategories as $categoryData) {
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
        
        // Sınav sorularını karıştır ve seç
        shuffle($filteredQuestions);
            }
            
        $selectedQuestions = array_slice($filteredQuestions, 0, $questionCount);
        }
        
        // Sınav verilerini hazırla
        $examData = [
            'id' => $examCode,
            'title' => $examTitle,
            'description' => $examDescription,
            'teacher_id' => $user['username'],
            'teacher_name' => $user['name'],
            'teacher_section' => $teacherSection,
            'class_section' => $user['institution'] ?? $user['branch'] ?? $teacherSection,
            'categories' => $questionType === 'custom' ? ['Özel|Özel Sorular'] : $selectedCategories,
            'question_type' => $questionType,
            'questions' => $selectedQuestions, // Soruları ekle
            'question_count' => $questionCount,
            'duration' => $examDuration,
            'created_at' => date('Y-m-d H:i:s'),
            'status' => $scheduleType === 'scheduled' ? 'scheduled' : 'active', // Planlanan veya aktif
            'participants' => [],
            'schedule_type' => $scheduleType,
            'scheduled_start' => $scheduleType === 'scheduled' ? $startDate . ' ' . $startTime : null
        ];
        
        // Veritabanına kaydet
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            $sql = "INSERT INTO exams (
                        exam_id, created_by, title, description, teacher_name, teacher_institution, 
                        class_section, duration, question_count, questions, categories, status, 
                        schedule_type, start_date, end_date, scheduled_start, created_at
                    ) VALUES (
                        :exam_id, :created_by, :title, :description, :teacher_name, :teacher_institution,
                        :class_section, :duration, :question_count, :questions, :categories, :status,
                        :schedule_type, :start_date, :end_date, :scheduled_start, :created_at
                    )";
            
            $stmt = $conn->prepare($sql);
            $result = $stmt->execute([
                ':exam_id' => $examCode,
                ':created_by' => $user['username'],
                ':title' => $examTitle,
                ':description' => $examDescription,
                ':teacher_name' => $user['name'],
                ':teacher_institution' => $teacherSection,
                ':class_section' => $user['institution'] ?? $user['branch'] ?? $teacherSection,
                ':duration' => $examDuration,
                ':question_count' => $questionCount,
                ':questions' => json_encode($selectedQuestions, JSON_UNESCAPED_UNICODE),
                ':categories' => json_encode($questionType === 'custom' ? ['Özel|Özel Sorular'] : $selectedCategories, JSON_UNESCAPED_UNICODE),
                ':status' => $scheduleType === 'scheduled' ? 'scheduled' : 'active',
                ':schedule_type' => $scheduleType,
                ':start_date' => null, // Opsiyonel, şu an formda yok ama şemada var
                ':end_date' => null,   // Opsiyonel
                ':scheduled_start' => $scheduleType === 'scheduled' ? $startDate . ' ' . $startTime : null,
                ':created_at' => date('Y-m-d H:i:s')
            ]);
            
            if ($result) {
                $examCreated = true;
            } else {
                $errorMessage = 'Sınav veritabanına kaydedilirken hata oluştu.';
            }
        } catch (Exception $e) {
            $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
            error_log($errorMessage);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sınav Oluştur - Bir Soru Bir Sevap</title>
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
            --secondary-color: #f8fafc;
            --accent-color: #ff6b35;
            --text-dark: #0f172a;
            --text-light: #64748b;
            --success-color: #22c55e;
            --warning-color: #eab308;
            --danger-color: #ef4444;
            --info-color: #06b6d4;
        }

        html, body { max-width: 100%; overflow-x: hidden; }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: radial-gradient(1200px 600px at 10% -10%, #e0f2fe 0%, transparent 60%),
                        radial-gradient(1000px 500px at 110% 0%, #fef9c3 0%, transparent 55%),
                        linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.65;
        }
        
        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 22px 0;
            box-shadow: 0 10px 30px rgba(8, 148, 115, 0.30);
            position: sticky;
            top: 0;
            z-index: 50;
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 10px;
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
            font-size: clamp(1.4rem, 1rem + 1.2vw, 2rem);
            margin-bottom: 4px;
            color: white;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.12);
            letter-spacing: .2px;
        }
        
        .logo p {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.9em;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Uzun kullanıcı adı metnini taşırmadan kısalt */
        .user-info > div {
            max-width: 45vw;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            backdrop-filter: blur(10px);
        }
        
        .back-btn {
            background: rgba(255, 255, 255, 0.18);
            border: 1.6px solid rgba(255, 255, 255, 0.35);
            color: white;
            padding: 10px 18px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.25s ease;
            font-weight: 700;
            backdrop-filter: blur(10px);
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }
        
        .lang-toggle {
            background: rgba(255, 255, 255, 0.16);
            border: 1.6px solid rgba(255, 255, 255, 0.35);
            color: white;
            padding: 8px 14px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 700;
            transition: all 0.25s ease;
            margin-left: 10px;
            backdrop-filter: blur(8px);
        }
        
        .lang-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 36px 20px 44px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: clamp(1.7rem, 1.1rem + 2vw, 2.6rem);
            color: #0b1324;
            margin-bottom: 10px;
            text-shadow: 0 1px 0 rgba(255,255,255,.6);
            font-weight: 900;
            letter-spacing: .2px;
        }
        
        .page-subtitle {
            font-size: clamp(.95rem, .8rem + .5vw, 1.15rem);
            color: #334155;
            margin-bottom: 26px;
        }
        
        .exam-form-card {
            background: linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            backdrop-filter: blur(6px);
            border-radius: 18px;
            padding: clamp(22px, 2.2vw, 38px);
            box-shadow: 0 20px 50px rgba(2,6,23,.10);
            border: 1px solid #e9eef5;
            margin-bottom: 28px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 28px;
        }
        
        .section-title {
            font-size: clamp(1.05rem, .9rem + .8vw, 1.45rem);
            color: #0f172a;
            margin-bottom: 18px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 900;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 64px;
            height: 3px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: 2px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 1.6px solid #e5e7eb;
            border-radius: 12px;
            font-size: clamp(.95rem, .9rem + .2vw, 1rem);
            transition: all 0.2s ease;
            background: white;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(8, 148, 115, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            max-height: 460px;
            overflow-y: auto;
            padding-right: 12px;
        }
        
        .category-grid::-webkit-scrollbar {
            width: 6px;
        }
        
        .category-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .category-grid::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            border-radius: 3px;
        }
        
        .bank-section {
            margin-bottom: 25px;
        }
        
        .bank-title {
            font-size: 1.05em;
            font-weight: 800;
            color: var(--text-dark);
            margin-bottom: 10px;
            padding: 14px 16px;
            background: linear-gradient(135deg, #f8fafc 0%, #eef2f7 100%);
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
            transition: background .2s ease, box-shadow .2s ease, border-color .2s ease;
        }
        .bank-title:hover { background:#f1f5f9; box-shadow: 0 8px 18px rgba(2,6,23,.06); border-color:#d7dde4; }
        .bank-title .chevron { transition: transform .2s ease; font-size: 1.1em; color:#64748b; }
        .bank-title.open .chevron { transform: rotate(180deg); }
        .bank-categories { display: none; padding-left: 4px; }
        
        .category-item {
            padding: 16px;
            border: 1.6px solid #e1e8ed;
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.25s ease;
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        .category-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }
        
        .category-item:hover::before {
            left: 100%;
        }
        
        .category-item:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.06) 0%, rgba(8, 148, 115, 0.02) 100%);
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(8, 148, 115, 0.15);
        }
        
        .category-item.selected {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 10px 25px rgba(8, 148, 115, 0.28);
        }
        
        .category-checkbox {
            display: none;
        }
        
        .category-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-weight: 500;
        }
        
        .category-checkbox:checked + .category-label {
            color: white;
        }
        
        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 1.8px solid #e1e8ed;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.25s ease;
        }
        
        .category-checkbox:checked + .category-label .custom-checkbox {
            background: white;
            border-color: white;
        }
        
        .category-checkbox:checked + .category-label .custom-checkbox::after {
            content: '✓';
            color: var(--primary-color);
            font-weight: bold;
            font-size: 14px;
        }
        
        .submit-section {
            grid-column: 1 / -1;
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1.6px solid #e1e8ed;
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 14px 26px;
            border-radius: 12px;
            font-size: clamp(1rem, .95rem + .25vw, 1.08rem);
            font-weight: 900;
            cursor: pointer;
            transition: all 0.22s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
            box-shadow: 0 14px 28px rgba(8, 148, 115, 0.28);
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 36px rgba(8, 148, 115, 0.38);
        }
        
        .success-card {
            background: linear-gradient(135deg, #ecfdf5 0%, #dcfce7 100%);
            border: 1.6px solid #bbf7d0;
            border-radius: 18px;
            padding: 32px;
            text-align: center;
            margin-bottom: 28px;
        }
        
        .success-icon {
            font-size: 4em;
            margin-bottom: 20px;
        }
        
        .success-title {
            font-size: 1.6em;
            color: #166534;
            margin-bottom: 12px;
            font-weight: 800;
        }
        
        .success-text {
            font-size: 1.2em;
            color: #155724;
            margin-bottom: 25px;
        }
        
        .exam-code {
            background: white;
            padding: 16px;
            border-radius: 14px;
            border: 1.6px solid #bbf7d0;
            font-size: 1.2em;
            font-weight: 900;
            color: #166534;
            letter-spacing: 2px;
            margin: 14px 0 6px;
        }
        
        .error-message {
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%);
            border: 1.6px solid #fecaca;
            border-radius: 14px;
            padding: 16px;
            color: #7f1d1d;
            margin-bottom: 18px;
            text-align: center;
            font-weight: 700;
        }
        
        .teacher-info {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1.6px solid #bae6fd;
            border-radius: 14px;
            padding: 18px;
            margin-bottom: 24px;
            text-align: center;
        }
        
        .teacher-info h3 {
            color: #075985;
            margin-bottom: 8px;
            font-size: 1.2em;
            font-weight: 800;
        }
        
        .teacher-info p {
            color: #0c4a6e;
            font-size: 1.02em;
            font-weight: 600;
        }
        
        .question-type-option:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.05) 0%, rgba(8, 148, 115, 0.02) 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(8, 148, 115, 0.1);
        }
        
        .question-type-option input[type="radio"]:checked + div {
            color: var(--primary-color);
        }
        
        .custom-question-item {
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            transition: all 0.3s ease;
        }
        
        .custom-question-item:hover {
            border-color: var(--primary-color);
            box-shadow: 0 5px 15px rgba(8, 148, 115, 0.1);
        }
        
        .option-inputs > div:hover {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.05) 0%, rgba(8, 148, 115, 0.02) 100%);
            transform: translateY(-1px);
            box-shadow: 0 3px 10px rgba(8, 148, 115, 0.1);
        }
        
        .option-inputs input[type="radio"]:checked + span {
            color: var(--primary-color);
            font-weight: 700;
        }
        
        .option-inputs > div:has(input[type="radio"]:checked) {
            border-color: var(--primary-color);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.1) 0%, rgba(8, 148, 115, 0.05) 100%);
            box-shadow: 0 3px 10px rgba(8, 148, 115, 0.2);
        }
        
        .question-item input[type="checkbox"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .question-item:has(input[type="checkbox"]:disabled) {
            opacity: 0.6;
            background: #f8f9fa;
            cursor: not-allowed;
        }
        
        .question-item:has(input[type="checkbox"]:disabled) label {
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .form-grid { grid-template-columns: 1fr; gap: 18px; }
            .exam-form-card { padding: 16px; border-radius: 14px; }
            .container { padding: 22px 14px; max-width: 100%; }
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; flex-wrap: wrap; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display: none; }
            .logo h1 { font-size: clamp(1.2rem, 1rem + 1vw, 1.5rem); }
            .user-avatar { width: 34px; height: 34px; }
            .back-btn, .lang-toggle { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .teacher-info, .exam-form-card, .success-card { width: 100%; }
        }

        /* Masaüstü düzen iyileştirmeleri */
        @media (min-width: 1024px) {
            .header-content { max-width: 1160px; }
            .container { max-width: 1160px; }
            .form-grid { gap: 28px; }
            .exam-form-card { padding: 32px; }
        }

        @media (min-width: 1440px) {
            .header-content { max-width: 1240px; }
            .container { max-width: 1240px; }
        }

        /* Çok küçük ekranlar için ek iyileştirmeler */
        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .back-btn, .lang-toggle { padding: 5px 8px; font-size: .85rem; }
            .user-info { gap: 8px; }
            .user-info > div { max-width: 58vw; }
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
                    <p>Sınav Oluştur</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: white;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: rgba(255, 255, 255, 0.8);" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="lang-toggle">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBack">← Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="pageTitle">📝 Sınav Oluştur</h1>
            <p class="page-subtitle" id="pageSubtitle">Yeni bir sınav oluşturun ve öğrencilerinize gönderin</p>
        </div>

        <?php if ($examCreated): ?>
            <div class="success-card">
                <div class="success-icon">🎉</div>
                <div class="success-title" id="successTitle">Sınav Başarıyla Oluşturuldu!</div>
                <div class="success-text" id="successText">Sınavınız hazır ve öğrencileriniz katılabilir.</div>
                <div class="exam-code" id="examCodeText">Sınav Kodu: <?php echo $examCode; ?></div>
                <p style="color: #155724; margin-bottom: 20px;" id="codeInfo">
                    Öğrenciler bu kodu kullanarak sınava katılabilirler.
                </p>
                <a href="exams.php" class="btn" id="btnViewExams">Sınavları Görüntüle</a>
            </div>
        <?php else: ?>
        <?php if ($errorMessage): ?>
            <div class="error-message">
                    ❌ <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

            <div class="teacher-info">
                <h3 id="teacherInfoTitle">👨‍🏫 Eğitmen Bilgileri</h3>
                <p id="teacherInfoText"><strong>Ad:</strong> <?php echo htmlspecialchars($user['name']); ?> | <strong>Şube:</strong> <?php echo htmlspecialchars($teacherSection); ?></p>
            </div>

        <div class="exam-form-card">
            <form method="POST">
                <div class="form-grid">
                    <!-- Sol Taraf: Sınav Bilgileri -->
                    <div>
                        <h2 class="section-title" id="sectionTitle1">📋 Sınav Bilgileri</h2>
                        
                        <div class="form-group">
                            <label for="exam_title" id="labelExamTitle">📝 Sınav Başlığı</label>
                            <input type="text" id="exam_title" name="exam_title" required 
                                   placeholder="Örn: Temel Bilgiler 1 - İtikat Sınavı">
                        </div>
                        
                        <div class="form-group">
                                <label for="exam_description" id="labelExamDescription">📄 Açıklama (İsteğe Bağlı)</label>
                                <textarea id="exam_description" name="exam_description" 
                                          placeholder="Sınav hakkında öğrencilere bilgi verin..."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label for="question_count" id="labelQuestionCount">🔢 Soru Sayısı</label>
                            <select id="question_count" name="question_count" required>
                                <option value="5">5 Soru</option>
                                <option value="10" selected>10 Soru</option>
                                <option value="15">15 Soru</option>
                                <option value="20">20 Soru</option>
                                <option value="25">25 Soru</option>
                                <option value="30">30 Soru</option>
                                    <option value="50">50 Soru</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                                <label for="exam_duration" id="labelExamDuration">⏱️ Süre (Dakika)</label>
                            <select id="exam_duration" name="exam_duration" required>
                                <option value="15">15 Dakika</option>
                                <option value="30" selected>30 Dakika</option>
                                <option value="45">45 Dakika</option>
                                <option value="60">60 Dakika</option>
                                    <option value="90">90 Dakika</option>
                                    <option value="120">120 Dakika</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="exam_schedule_type" id="labelScheduleType">📅 Sınav Zamanlaması</label>
                            <select id="exam_schedule_type" name="exam_schedule_type" required>
                                <option value="immediate" selected>Hemen Başlat</option>
                                <option value="scheduled">Planla</option>
                            </select>
                    </div>

                        <div id="schedule_options" class="schedule-options" style="display: none;">
                            <div class="form-group">
                                <label for="exam_start_date" id="labelStartDate">📅 Başlangıç Tarihi</label>
                                <input type="date" id="exam_start_date" name="exam_start_date" min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label for="exam_start_time" id="labelStartTime">🕐 Başlangıç Saati</label>
                                <input type="time" id="exam_start_time" name="exam_start_time">
                            </div>
                        </div>
                    </div>

                    <!-- Sağ Taraf: Soru Türü ve Konu Seçimi -->
                    <div>
                        <h2 class="section-title" id="sectionTitle2">📚 Soru Türü ve Konu Seçimi</h2>
                        
                        <!-- Soru Türü Seçimi -->
                        <div class="question-type-section" style="margin-bottom: 30px;">
                            <h3 style="color: #2c3e50; margin-bottom: 15px; font-size: 1.2em;" id="questionTypeTitle">Soru Türü Seçin:</h3>
                            <div class="question-type-options" style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                                <label class="question-type-option" style="display: flex; align-items: center; gap: 12px; padding: 15px; border: 2px solid #e1e8ed; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;">
                                    <input type="radio" name="question_type" value="random" checked style="margin: 0;">
                                    <div>
                                        <div style="font-weight: 600; color: #2c3e50;" id="option1Title">🎲 Rastgele Sorular</div>
                                        <div style="font-size: 0.9em; color: #7f8c8d;" id="option1Desc">Seçilen kategorilerden rastgele sorular</div>
                                    </div>
                                </label>
                                <label class="question-type-option" style="display: flex; align-items: center; gap: 12px; padding: 15px; border: 2px solid #e1e8ed; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;">
                                    <input type="radio" name="question_type" value="manual" style="margin: 0;">
                                    <div>
                                        <div style="font-weight: 600; color: #2c3e50;" id="option2Title">✋ Manuel Seçim</div>
                                        <div style="font-size: 0.9em; color: #7f8c8d;" id="option2Desc">Kategorilerden manuel olarak soru seçimi</div>
                                    </div>
                                </label>
                                <label class="question-type-option" style="display: flex; align-items: center; gap: 12px; padding: 15px; border: 2px solid #e1e8ed; border-radius: 12px; cursor: pointer; transition: all 0.3s ease;">
                                    <input type="radio" name="question_type" value="custom" style="margin: 0;">
                                    <div>
                                        <div style="font-weight: 600; color: #2c3e50;" id="option3Title">✏️ Özel Sorular</div>
                                        <div style="font-size: 0.9em; color: #7f8c8d;" id="option3Desc">Kendi sorularınızı ve seçeneklerinizi yazın</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Konu Seçimi (Rastgele ve Manuel için) -->
                        <div id="categorySelection" class="category-selection">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                                <h3 style="color: #2c3e50; font-size: 1.2em; margin: 0;" id="categoryTitle">Kategoriler:</h3>
                                <div id="selectedQuestionsCount" style="background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; display: none;">
                                    <span id="selectedCount">0</span> soru seçildi
                                </div>
                            </div>
                        <div class="category-grid">
                            <?php if (!empty($groupedCategories)): ?>
                                <?php foreach ($groupedCategories as $bank => $bankCategories): ?>
                                        <div class="bank-section">
                                            <div class="bank-title" data-bank="<?php echo htmlspecialchars($bank); ?>">
                                                <span><?php echo htmlspecialchars($bank); ?></span>
                                                <span class="chevron">▼</span>
                                            </div>
                                            <div class="bank-categories">
                                            <?php foreach ($bankCategories as $category): ?>
                                                <div class="category-item" data-category="<?php echo htmlspecialchars($bank . '|' . $category); ?>">
                                                    <div class="category-header" style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 15px;">
                                                    <input type="checkbox" 
                                                           id="category_<?php echo md5($bank . $category); ?>" 
                                                           name="categories[]" 
                                                           value="<?php echo htmlspecialchars($bank . '|' . $category); ?>"
                                                           class="category-checkbox">
                                                        <label for="category_<?php echo md5($bank . $category); ?>" class="category-label" style="flex: 1; cursor: pointer;">
                                                        <span class="custom-checkbox"></span>
                                                        <?php echo htmlspecialchars($category); ?>
                                                </label>
                                                        <span class="expand-icon" style="font-size: 1.2em; transition: transform 0.3s ease;">▼</span>
                                                    </div>
                                                    <div class="questions-container" style="display: none; padding: 0 15px 15px 15px; border-top: 1px solid #e1e8ed; margin-top: 10px;">
                        <div class="questions-loading" style="text-align: center; padding: 20px; color: #7f8c8d;">
                            <div class="loading-text">Sorular yükleniyor...</div>
                        </div>
                                                        <div class="questions-list" style="display: none;">
                                                            <!-- Sorular buraya dinamik olarak yüklenecek -->
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                            </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <p id="noQuestionsText">Henüz soru bankası yüklenmedi.</p>
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Özel Sorular Bölümü (Gizli) -->
                        <div id="customQuestionsSection" class="custom-questions-section" style="display: none;">
                            <h3 style="color: #2c3e50; margin-bottom: 15px; font-size: 1.2em;" id="customQuestionsTitle">Özel Sorularınızı Ekleyin:</h3>
                            <div id="customQuestionsContainer">
                                <!-- Dinamik olarak eklenecek -->
                            </div>
                            <button type="button" id="addCustomQuestion" class="btn" style="background: #6c757d; margin-top: 15px; padding: 10px 20px; font-size: 0.9em;">
                                ➕ Yeni Soru Ekle
                            </button>
                        </div>
                    </div>
                </div>
                
                    <div class="submit-section">
                        <button type="submit" class="btn" id="btnCreateExam">
                    🚀 Sınav Oluştur
                </button>
                    </div>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Konu seçimi için JavaScript
        document.querySelectorAll('.category-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const categoryItem = this.closest('.category-item');
                if (this.checked) {
                    categoryItem.classList.add('selected');
                } else {
                    categoryItem.classList.remove('selected');
                }
            });
        });

        // Soru türü değiştirme
        document.querySelectorAll('input[name="question_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const categorySelection = document.getElementById('categorySelection');
                const customQuestionsSection = document.getElementById('customQuestionsSection');
                const selectedQuestionsCount = document.getElementById('selectedQuestionsCount');
                
                if (this.value === 'custom') {
                    categorySelection.style.display = 'none';
                    customQuestionsSection.style.display = 'block';
                    selectedQuestionsCount.style.display = 'none';
                } else if (this.value === 'manual') {
                    categorySelection.style.display = 'block';
                    customQuestionsSection.style.display = 'none';
                    selectedQuestionsCount.style.display = 'block';
                    updateSelectedQuestionsCount();
                } else {
                    categorySelection.style.display = 'block';
                    customQuestionsSection.style.display = 'none';
                    selectedQuestionsCount.style.display = 'none';
                }
            });
        });

        // Planlama seçeneği değiştirme
        document.getElementById('exam_schedule_type').addEventListener('change', function() {
            const scheduleOptions = document.getElementById('schedule_options');
            if (this.value === 'scheduled') {
                scheduleOptions.style.display = 'block';
                // Bugünün tarihini varsayılan olarak ayarla
                document.getElementById('exam_start_date').value = new Date().toISOString().split('T')[0];
                // Şu anki saati varsayılan olarak ayarla
                const now = new Date();
                const timeString = now.getHours().toString().padStart(2, '0') + ':' + now.getMinutes().toString().padStart(2, '0');
                document.getElementById('exam_start_time').value = timeString;
            } else {
                scheduleOptions.style.display = 'none';
            }
        });

        // Accordion fonksiyonları
        document.addEventListener('DOMContentLoaded', function() {
            // Banka başlığına tıklama (Temel Bilgiler 1/2/3 vb.)
            document.querySelectorAll('.bank-title').forEach(title => {
                title.addEventListener('click', function(){
                    const parent = this.closest('.bank-section');
                    const list = parent ? parent.querySelector('.bank-categories') : null;
                    if (!list) return;
                    const isOpen = getComputedStyle(list).display !== 'none';
                    list.style.display = isOpen ? 'none' : 'block';
                    this.classList.toggle('open', !isOpen);
                });
            });
            // Kategori başlığına tıklama
            document.querySelectorAll('.category-header').forEach(header => {
                header.addEventListener('click', function(e) {
                    // Checkbox'a tıklanmışsa accordion'u açma
                    if (e.target.type === 'checkbox' || e.target.tagName === 'INPUT') {
                        return;
                    }
                    
                    const categoryItem = this.closest('.category-item');
                    const questionsContainer = categoryItem.querySelector('.questions-container');
                    const expandIcon = this.querySelector('.expand-icon');
                    const questionType = document.querySelector('input[name="question_type"]:checked').value;
                    
                    // Sadece manuel seçimde accordion çalışsın
                    if (questionType !== 'manual') {
                        return;
                    }
                    
                    if (questionsContainer.style.display === 'none') {
                        // Aç
                        questionsContainer.style.display = 'block';
                        expandIcon.style.transform = 'rotate(180deg)';
                        
                        // Soruları yükle
                        loadQuestionsForCategory(categoryItem);
                    } else {
                        // Kapat
                        questionsContainer.style.display = 'none';
                        expandIcon.style.transform = 'rotate(0deg)';
                    }
                });
            });
        });

        // Kategori sorularını yükle
        function loadQuestionsForCategory(categoryItem) {
            const questionsContainer = categoryItem.querySelector('.questions-container');
            const loadingDiv = questionsContainer.querySelector('.questions-loading');
            const questionsList = questionsContainer.querySelector('.questions-list');
            const category = categoryItem.dataset.category;
            
            // Loading göster
            loadingDiv.style.display = 'block';
            questionsList.style.display = 'none';
            
            // AJAX ile soruları yükle
            fetch('get_questions_by_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'category=' + encodeURIComponent(category)
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.style.display = 'none';
                questionsList.style.display = 'block';
                
                if (data.success && data.questions.length > 0) {
                    questionsList.innerHTML = '';
                    data.questions.forEach((question, index) => {
                        const questionDiv = document.createElement('div');
                        questionDiv.className = 'question-item';
                        questionDiv.style.cssText = 'padding: 10px; border: 1px solid #e1e8ed; border-radius: 8px; margin-bottom: 10px; background: #f8f9fa;';
                        
                        questionDiv.innerHTML = `
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="selected_questions[]" value="${question.id}" style="margin-top: 5px;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; margin-bottom: 5px; color: #2c3e50;">
                                        Soru ${index + 1}: ${question.question}
                                    </div>
                                    <div style="font-size: 0.9em; color: #6c757d;">
                                        Tip: ${getQuestionTypeText(question.type)} | Zorluk: ${question.difficulty || 'Belirtilmemiş'}
                                    </div>
                                </div>
                            </label>
                        `;
                        
                        questionsList.appendChild(questionDiv);
                    });
                } else {
                    const currentLang = localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr';
                    const noQuestionsText = currentLang === 'de' ? 'Keine Fragen in dieser Kategorie gefunden.' : 'Bu kategoride soru bulunamadı.';
                    questionsList.innerHTML = `<div style="text-align: center; padding: 20px; color: #7f8c8d;">${noQuestionsText}</div>`;
                }
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                questionsList.style.display = 'block';
                const currentLang = localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr';
                const errorText = currentLang === 'de' ? 'Fehler beim Laden der Fragen.' : 'Sorular yüklenirken hata oluştu.';
                questionsList.innerHTML = `<div style="text-align: center; padding: 20px; color: #dc3545;">${errorText}</div>`;
                console.error('Error:', error);
            });
        }

        // Soru tipi metnini döndür
        function getQuestionTypeText(type) {
            const types = {
                'mcq': 'Çoktan Seçmeli',
                'true_false': 'Doğru/Yanlış',
                'short_answer': 'Kısa Cevap'
            };
            return types[type] || type;
        }

        // Seçilen soru sayısını güncelle
        function updateSelectedQuestionsCount() {
            const selectedQuestions = document.querySelectorAll('input[name="selected_questions[]"]:checked');
            const countElement = document.getElementById('selectedCount');
            const countContainer = document.getElementById('selectedQuestionsCount');
            const questionCountSelect = document.getElementById('question_count');
            const currentLang = localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr';
            
            if (countElement && countContainer) {
                const tr = { 
                    selectedQuestionsText: 'soru seçildi',
                    requiredQuestionsText: 'soru gerekli'
                };
                const de = { 
                    selectedQuestionsText: 'Fragen ausgewählt',
                    requiredQuestionsText: 'Fragen erforderlich'
                };
                const d = currentLang === 'de' ? de : tr;
                
                const requiredCount = questionCountSelect ? parseInt(questionCountSelect.value) : 0;
                const selectedCount = selectedQuestions.length;
                
                countElement.textContent = selectedCount;
                
                // Renk kodlaması
                if (selectedCount === requiredCount) {
                    countContainer.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                } else if (selectedCount > requiredCount) {
                    countContainer.style.background = 'linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)';
                } else {
                    countContainer.style.background = 'linear-gradient(135deg, #6c757d 0%, #495057 100%)';
                }
                
                countContainer.innerHTML = `<span id="selectedCount">${selectedCount}</span> / ${requiredCount} ${d.selectedQuestionsText}`;
                
                if (selectedCount > 0 || requiredCount > 0) {
                    countContainer.style.display = 'block';
                } else {
                    countContainer.style.display = 'none';
                }
            }
        }

        // Soru seçimi değişikliklerini dinle
        document.addEventListener('change', function(e) {
            if (e.target.name === 'selected_questions[]') {
                const questionCountSelect = document.getElementById('question_count');
                const requiredCount = questionCountSelect ? parseInt(questionCountSelect.value) : 0;
                const selectedQuestions = document.querySelectorAll('input[name="selected_questions[]"]:checked');
                
                // Eğer maksimum sayıya ulaşıldıysa, diğer checkbox'ları devre dışı bırak
                if (selectedQuestions.length >= requiredCount) {
                    document.querySelectorAll('input[name="selected_questions[]"]:not(:checked)').forEach(checkbox => {
                        checkbox.disabled = true;
                    });
                } else {
                    // Eğer sayı azaldıysa, tüm checkbox'ları aktif et
                    document.querySelectorAll('input[name="selected_questions[]"]').forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                }
                
                updateSelectedQuestionsCount();
            } else if (e.target.id === 'question_count') {
                // Soru sayısı değiştiğinde güncelle
                const questionType = document.querySelector('input[name="question_type"]:checked');
                if (questionType && questionType.value === 'manual') {
                    // Tüm checkbox'ları aktif et
                    document.querySelectorAll('input[name="selected_questions[]"]').forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                    updateSelectedQuestionsCount();
                }
            }
        });

        // Özel soru ekleme
        let customQuestionCount = 0;
        document.getElementById('addCustomQuestion').addEventListener('click', function() {
            customQuestionCount++;
            const container = document.getElementById('customQuestionsContainer');
            const currentLang = localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr';
            
            const tr = {
                questionText: 'Soru Metni:', questionPlaceholder: 'Sorunuzu yazın...',
                questionType: 'Soru Tipi:', mcq: 'Çoktan Seçmeli', trueFalse: 'Doğru/Yanlış', shortAnswer: 'Kısa Cevap',
                optionsLabel: 'Seçenekler (Doğru cevabı işaretleyin):', optionPlaceholder: 'Seçenek',
                explanationLabel: 'Açıklama (İsteğe Bağlı):', explanationPlaceholder: 'Doğru cevabın açıklaması...',
                deleteBtn: 'Sil'
            };
            const de = {
                questionText: 'Fragetext:', questionPlaceholder: 'Schreiben Sie Ihre Frage...',
                questionType: 'Fragetyp:', mcq: 'Multiple Choice', trueFalse: 'Richtig/Falsch', shortAnswer: 'Kurze Antwort',
                optionsLabel: 'Optionen (Richtige Antwort markieren):', optionPlaceholder: 'Option',
                explanationLabel: 'Erklärung (optional):', explanationPlaceholder: 'Erklärung der richtigen Antwort...',
                deleteBtn: 'Löschen'
            };
            
            const d = currentLang === 'de' ? de : tr;
            
            const questionDiv = document.createElement('div');
            questionDiv.className = 'custom-question-item';
            questionDiv.style.cssText = 'border: 2px solid #e1e8ed; border-radius: 15px; padding: 20px; margin-bottom: 20px; background: white;';
            
            questionDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; color: #2c3e50;">${d.questionText} ${customQuestionCount}</h4>
                    <button type="button" onclick="removeCustomQuestion(this)" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">${d.deleteBtn}</button>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">${d.questionText}</label>
                    <textarea name="custom_questions[${customQuestionCount}][question]" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;" placeholder="${d.questionPlaceholder}"></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">${d.questionType}</label>
                    <select name="custom_questions[${customQuestionCount}][type]" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="mcq">${d.mcq}</option>
                        <option value="true_false">${d.trueFalse}</option>
                        <option value="short_answer">${d.shortAnswer}</option>
                    </select>
                </div>
                <div id="options_${customQuestionCount}" class="options-container">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2c3e50;">${d.optionsLabel}</label>
                    <div class="option-inputs">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="0" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">A)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="${d.optionPlaceholder} 1" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="1" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">B)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="${d.optionPlaceholder} 2" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="2" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">C)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="${d.optionPlaceholder} 3" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="3" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">D)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="${d.optionPlaceholder} 4" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">${d.explanationLabel}</label>
                    <textarea name="custom_questions[${customQuestionCount}][explanation]" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;" placeholder="${d.explanationPlaceholder}"></textarea>
                </div>
            `;
            
            container.appendChild(questionDiv);
        });

        // Özel soru silme
        function removeCustomQuestion(button) {
            button.closest('.custom-question-item').remove();
        }

        // TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'📝 Sınav Oluştur', pageSubtitle:'Yeni bir sınav oluşturun ve öğrencilerinize gönderin',
                userRole:'Eğitmen', btnBack:'← Geri Dön',
                successTitle:'Sınav Başarıyla Oluşturuldu!', successText:'Sınavınız hazır ve öğrencileriniz katılabilir.',
                examCodeText:'Sınav Kodu:', codeInfo:'Öğrenciler bu kodu kullanarak sınava katılabilirler.',
                btnViewExams:'Sınavları Görüntüle',
                teacherInfoTitle:'👨‍🏫 Eğitmen Bilgileri', teacherInfoText:'Ad:',
                sectionTitle1:'📋 Sınav Bilgileri', sectionTitle2:'📚 Soru Türü ve Konu Seçimi',
                labelExamTitle:'📝 Sınav Başlığı', labelExamDescription:'📄 Açıklama (İsteğe Bağlı)',
                labelQuestionCount:'🔢 Soru Sayısı', labelExamDuration:'⏱️ Süre (Dakika)',
                labelScheduleType:'📅 Sınav Zamanlaması', immediateStart:'Hemen Başlat', scheduleExam:'Planla',
                labelStartDate:'📅 Başlangıç Tarihi', labelStartTime:'🕐 Başlangıç Saati',
                noQuestionsText:'Henüz soru bankası yüklenmedi.', btnCreateExam:'🚀 Sınav Oluştur',
                alertSelectCategory:'Lütfen en az bir konu seçin!',
                questionTypeTitle:'Soru Türü Seçin:', option1Title:'🎲 Rastgele Sorular', option1Desc:'Seçilen kategorilerden rastgele sorular',
                option2Title:'✋ Manuel Seçim', option2Desc:'Kategorilerden manuel olarak soru seçimi',
                option3Title:'✏️ Özel Sorular', option3Desc:'Kendi sorularınızı ve seçeneklerinizi yazın',
                categoryTitle:'Kategoriler:', customQuestionsTitle:'Özel Sorularınızı Ekleyin:',
                loadingText:'Sorular yükleniyor...', noQuestionsFound:'Bu kategoride soru bulunamadı.', errorLoading:'Sorular yüklenirken hata oluştu.',
                optionsLabel:'Seçenekler (Doğru cevabı işaretleyin):', explanationLabel:'Açıklama (İsteğe Bağlı):', explanationPlaceholder:'Doğru cevabın açıklaması...',
                selectedQuestionsText:'soru seçildi', alertSelectQuestion:'Lütfen en az bir soru seçin!'
            };
            const de = {
                pageTitle:'📝 Prüfung erstellen', pageSubtitle:'Erstellen Sie eine neue Prüfung und senden Sie sie an Ihre Schüler',
                userRole:'Lehrpersonal', btnBack:'← Zurück',
                successTitle:'Prüfung erfolgreich erstellt!', successText:'Ihre Prüfung ist bereit und Ihre Schüler können teilnehmen.',
                examCodeText:'Prüfungscode:', codeInfo:'Schüler können mit diesem Code an der Prüfung teilnehmen.',
                btnViewExams:'Prüfungen anzeigen',
                teacherInfoTitle:'👨‍🏫 Lehrpersonal-Informationen', teacherInfoText:'Name:',
                sectionTitle1:'📋 Prüfungsinformationen', sectionTitle2:'📚 Fragentyp und Themenauswahl',
                labelExamTitle:'📝 Prüfungstitel', labelExamDescription:'📄 Beschreibung (optional)',
                labelQuestionCount:'🔢 Anzahl der Fragen', labelExamDuration:'⏱️ Zeit (Minuten)',
                labelScheduleType:'📅 Prüfungsplanung', immediateStart:'Sofort starten', scheduleExam:'Planen',
                labelStartDate:'📅 Startdatum', labelStartTime:'🕐 Startzeit',
                noQuestionsText:'Noch keine Fragensammlung geladen.', btnCreateExam:'🚀 Prüfung erstellen',
                alertSelectCategory:'Bitte wählen Sie mindestens ein Thema aus!',
                questionTypeTitle:'Fragentyp wählen:', option1Title:'🎲 Zufällige Fragen', option1Desc:'Zufällige Fragen aus ausgewählten Kategorien',
                option2Title:'✋ Manuelle Auswahl', option2Desc:'Manuelle Fragenauswahl aus Kategorien',
                option3Title:'✏️ Eigene Fragen', option3Desc:'Schreiben Sie Ihre eigenen Fragen und Antworten',
                categoryTitle:'Kategorien:', customQuestionsTitle:'Fügen Sie Ihre eigenen Fragen hinzu:',
                loadingText:'Fragen werden geladen...', noQuestionsFound:'Keine Fragen in dieser Kategorie gefunden.', errorLoading:'Fehler beim Laden der Fragen.',
                optionsLabel:'Optionen (Richtige Antwort markieren):', explanationLabel:'Erklärung (optional):', explanationPlaceholder:'Erklärung der richtigen Antwort...',
                selectedQuestionsText:'Fragen ausgewählt', alertSelectQuestion:'Bitte wählen Sie mindestens eine Frage aus!'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setPlaceholder(sel, text){ const el=document.querySelector(sel); if(el) el.placeholder=text; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#pageSubtitle', d.pageSubtitle);
                setText('#userRole', d.userRole);
                setText('#btnBack', d.btnBack);
                setText('#successTitle', d.successTitle);
                setText('#successText', d.successText);
                setText('#examCodeText', d.examCodeText + ' <?php echo $examCode; ?>');
                setText('#codeInfo', d.codeInfo);
                setText('#btnViewExams', d.btnViewExams);
                setText('#teacherInfoTitle', d.teacherInfoTitle);
                setText('#teacherInfoText', d.teacherInfoText + ' <?php echo htmlspecialchars($user['name']); ?> | ' + (lang==='de'?'Kompetenzstelle:':'Şube:') + ' <?php echo htmlspecialchars($teacherSection); ?>');
                setText('#sectionTitle1', d.sectionTitle1);
                setText('#sectionTitle2', d.sectionTitle2);
                setText('#labelExamTitle', d.labelExamTitle);
                setText('#labelExamDescription', d.labelExamDescription);
                setText('#labelQuestionCount', d.labelQuestionCount);
                setText('#labelExamDuration', d.labelExamDuration);
                setText('#labelScheduleType', d.labelScheduleType);
                setText('#labelStartDate', d.labelStartDate);
                setText('#labelStartTime', d.labelStartTime);
                
                // Planlama seçeneklerini güncelle
                const scheduleTypeSelect = document.getElementById('exam_schedule_type');
                if (scheduleTypeSelect) {
                    scheduleTypeSelect.options[0].text = d.immediateStart;
                    scheduleTypeSelect.options[1].text = d.scheduleExam;
                }
                setText('#noQuestionsText', d.noQuestionsText);
                setText('#btnCreateExam', d.btnCreateExam);
                setText('#questionTypeTitle', d.questionTypeTitle);
                setText('#option1Title', d.option1Title);
                setText('#option1Desc', d.option1Desc);
                setText('#option2Title', d.option2Title);
                setText('#option2Desc', d.option2Desc);
                setText('#option3Title', d.option3Title);
                setText('#option3Desc', d.option3Desc);
                setText('#categoryTitle', d.categoryTitle);
                setText('#customQuestionsTitle', d.customQuestionsTitle);
                
                // Loading ve hata mesajlarını güncelle
                document.querySelectorAll('.loading-text').forEach(el => {
                    el.textContent = d.loadingText;
                });
                
                // Seçilen soru sayısı metnini güncelle
                const selectedQuestionsCount = document.getElementById('selectedQuestionsCount');
                if (selectedQuestionsCount) {
                    const countText = selectedQuestionsCount.textContent;
                    const count = countText.match(/\d+/);
                    if (count) {
                        selectedQuestionsCount.innerHTML = `<span id="selectedCount">${count[0]}</span> ${d.selectedQuestionsText}`;
                    }
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_create_exam', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();

        // Form gönderimi öncesi kontrol
        document.querySelector('form').addEventListener('submit', function(e) {
            const questionType = document.querySelector('input[name="question_type"]:checked').value;
            const currentLang = localStorage.getItem('lang_create_exam')||localStorage.getItem('lang')||'tr';
            
            if (questionType === 'custom') {
                // Özel sorular için kontrol
                const customQuestions = document.querySelectorAll('.custom-question-item');
                if (customQuestions.length === 0) {
                    e.preventDefault();
                    const alertText = currentLang === 'de' ? 'Bitte fügen Sie mindestens eine Frage hinzu!' : 'Lütfen en az bir özel soru ekleyin!';
                    alert(alertText);
                    return false;
                }
                
                // Özel soruları form'a ekle
                customQuestions.forEach((questionDiv, index) => {
                    const questionText = questionDiv.querySelector('textarea[name*="[question]"]').value;
                    const questionType = questionDiv.querySelector('select[name*="[type]"]').value;
                    const options = Array.from(questionDiv.querySelectorAll('input[name*="[options][]"]')).map(input => input.value);
                    const correctAnswer = questionDiv.querySelector('input[name*="[correct]"]:checked').value;
                    const explanation = questionDiv.querySelector('textarea[name*="[explanation]"]').value;
                    
                    // Hidden input olarak ekle
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `custom_questions[${index}][question]`;
                    hiddenInput.value = questionText;
                    this.appendChild(hiddenInput);
                    
                    const hiddenType = document.createElement('input');
                    hiddenType.type = 'hidden';
                    hiddenType.name = `custom_questions[${index}][type]`;
                    hiddenType.value = questionType;
                    this.appendChild(hiddenType);
                    
                    const hiddenCorrect = document.createElement('input');
                    hiddenCorrect.type = 'hidden';
                    hiddenCorrect.name = `custom_questions[${index}][correct_answer]`;
                    hiddenCorrect.value = correctAnswer;
                    this.appendChild(hiddenCorrect);
                    
                    const hiddenExplanation = document.createElement('input');
                    hiddenExplanation.type = 'hidden';
                    hiddenExplanation.name = `custom_questions[${index}][explanation]`;
                    hiddenExplanation.value = explanation;
                    this.appendChild(hiddenExplanation);
                    
                    options.forEach((option, optionIndex) => {
                        const hiddenOption = document.createElement('input');
                        hiddenOption.type = 'hidden';
                        hiddenOption.name = `custom_questions[${index}][options][${optionIndex}]`;
                        hiddenOption.value = option;
                        this.appendChild(hiddenOption);
                    });
                });
            } else if (questionType === 'manual') {
                // Manuel seçim için soru kontrolü
                const selectedQuestions = document.querySelectorAll('input[name="selected_questions[]"]:checked');
                const questionCountSelect = document.getElementById('question_count');
                const requiredCount = questionCountSelect ? parseInt(questionCountSelect.value) : 0;
                
                if (selectedQuestions.length !== requiredCount) {
                    e.preventDefault();
                    const alertText = currentLang === 'de' 
                        ? `Bitte wählen Sie genau ${requiredCount} Fragen aus! (Aktuell: ${selectedQuestions.length})`
                        : `Lütfen tam olarak ${requiredCount} soru seçin! (Şu an: ${selectedQuestions.length})`;
                    alert(alertText);
                    return false;
                }
            } else {
                // Rastgele için kategori kontrolü
            const selectedCategories = document.querySelectorAll('input[name="categories[]"]:checked');
            if (selectedCategories.length === 0) {
                e.preventDefault();
                    const alertText = currentLang === 'de' ? 'Bitte wählen Sie mindestens ein Thema aus!' : 'Lütfen en az bir konu seçin!';
                    alert(alertText);
                return false;
                }
            }
        });
    </script>
</body>
</html>