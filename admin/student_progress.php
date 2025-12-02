<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('superadmin') && !$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

// Database bağlantısı
$db = Database::getInstance();
$conn = $db->getConnection();

// Filtre parametreleri
$selectedUser = $_GET['user'] ?? '';
$selectedSection = $_GET['class_section'] ?? '';
$selectedBranch = $_GET['branch'] ?? '';
$startDate = $_GET['start_date'] ?? '';
$endDate = $_GET['end_date'] ?? '';
$minScore = $_GET['min_score'] ?? '';

// Tüm öğrencileri çek
try {
    $sql = "SELECT DISTINCT u.username, u.full_name, u.class_section, u.branch 
            FROM users u 
            WHERE u.role = 'student' 
            ORDER BY u.class_section, u.full_name";
    $stmt = $conn->query($sql);
    $allStudents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Şubeleri çek
    $sql = "SELECT DISTINCT class_section FROM users WHERE role = 'student' AND class_section != '' ORDER BY class_section";
    $stmt = $conn->query($sql);
    $allSections = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Branşları çek
    $sql = "SELECT DISTINCT branch FROM users WHERE role = 'student' AND branch != '' ORDER BY branch";
    $stmt = $conn->query($sql);
    $allBranches = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (Exception $e) {
    $allStudents = [];
    $allSections = [];
    $allBranches = [];
}

// Filtrelenmiş öğrenciler
$filteredStudents = $allStudents;
if ($selectedSection) {
    $filteredStudents = array_filter($filteredStudents, function($s) use ($selectedSection) {
        return $s['class_section'] === $selectedSection;
    });
}
if ($selectedBranch) {
    $filteredStudents = array_filter($filteredStudents, function($s) use ($selectedBranch) {
        return $s['branch'] === $selectedBranch;
    });
}

// İlk öğrenciyi seç
if (!$selectedUser && !empty($filteredStudents)) {
    $selectedUser = reset($filteredStudents)['username'];
}

// Seçili öğrencinin bilgilerini çek
$selectedStudentInfo = null;
foreach ($allStudents as $student) {
    if ($student['username'] === $selectedUser) {
        $selectedStudentInfo = $student;
        break;
    }
}

// JSON dosyalarından veri okuma fonksiyonu (fallback)
function readJsonFile($path) {
    if (!file_exists($path)) return [];
    $json = file_get_contents($path);
    $data = json_decode($json, true);
    return is_array($data) ? $data : [];
}

// Öğrenci bazlı sonuçları çek
$studentProgress = [
    'practice' => [],
    'exams' => []
];

$debugInfo = [];

if ($selectedUser) {
    try {
        // Alıştırma sonuçları - Veritabanından
        $sql = "SELECT * FROM practice_results WHERE username = :username";
        if ($startDate) $sql .= " AND DATE(created_at) >= :start_date";
        if ($endDate) $sql .= " AND DATE(created_at) <= :end_date";
        if ($minScore) $sql .= " AND percentage >= :min_score";
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $selectedUser);
        if ($startDate) $stmt->bindParam(':start_date', $startDate);
        if ($endDate) $stmt->bindParam(':end_date', $endDate);
        if ($minScore) $stmt->bindParam(':min_score', $minScore);
        $stmt->execute();
        $studentProgress['practice'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo['db_practice_count'] = count($studentProgress['practice']);
        
        // Sınav sonuçları - Veritabanından
        $sql = "SELECT * FROM exam_results WHERE username = :username";
        if ($startDate) $sql .= " AND DATE(created_at) >= :start_date";
        if ($endDate) $sql .= " AND DATE(created_at) <= :end_date";
        if ($minScore) $sql .= " AND percentage >= :min_score";
        $sql .= " ORDER BY created_at DESC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bindParam(':username', $selectedUser);
        if ($startDate) $stmt->bindParam(':start_date', $startDate);
        if ($endDate) $stmt->bindParam(':end_date', $endDate);
        if ($minScore) $stmt->bindParam(':min_score', $minScore);
        $stmt->execute();
        $studentProgress['exams'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $debugInfo['db_exam_count'] = count($studentProgress['exams']);
        
        // Eğer veritabanında veri yoksa JSON dosyalarından dene
        if (empty($studentProgress['practice']) && empty($studentProgress['exams'])) {
            $practiceFile = __DIR__ . '/../data/practice_results.json';
            $examFile = __DIR__ . '/../data/exam_results.json';
            
            $practiceResults = readJsonFile($practiceFile);
            $examResults = readJsonFile($examFile);
            
            // Kullanıcıya ait sonuçları filtrele
            // JSON'da 'student_id' veya 'username' alanı kullanılabilir
            $studentProgress['practice'] = array_filter($practiceResults, function($r) use ($selectedUser) {
                $userId = $r['student_id'] ?? $r['username'] ?? '';
                return $userId === $selectedUser;
            });
            
            // Exam results için özel yapı (exam_code ile gruplanmış)
            $allExamResults = [];
            foreach ($examResults as $examCode => $results) {
                if (is_array($results)) {
                    foreach ($results as $result) {
                        $userId = $result['student_id'] ?? $result['username'] ?? '';
                        if ($userId === $selectedUser) {
                            $allExamResults[] = $result;
                        }
                    }
                }
            }
            $studentProgress['exams'] = $allExamResults;
            
            // JSON verilerini normalize et (alan adlarını düzelt)
            $normalizedPractice = [];
            foreach ($studentProgress['practice'] as $p) {
                $normalized = $p; // Kopyala
                
                // created_at yoksa timestamp'ten oluştur
                if (!isset($normalized['created_at'])) {
                    $normalized['created_at'] = $normalized['timestamp'] ?? date('Y-m-d H:i:s');
                }
                
                // correct_answers yoksa correct'ten al
                if (!isset($normalized['correct_answers'])) {
                    $normalized['correct_answers'] = $normalized['correct'] ?? 0;
                }
                
                // wrong_answers yoksa wrong'dan al
                if (!isset($normalized['wrong_answers'])) {
                    $normalized['wrong_answers'] = $normalized['wrong'] ?? 0;
                }
                
                // total_questions yoksa questions'dan al veya doğru+yanlıştan hesapla
                if (!isset($normalized['total_questions']) || $normalized['total_questions'] == 0) {
                    if (isset($normalized['questions']) && is_array($normalized['questions'])) {
                        $normalized['total_questions'] = count($normalized['questions']);
                    } else {
                        // Doğru + Yanlış = Toplam
                        $normalized['total_questions'] = $normalized['correct_answers'] + $normalized['wrong_answers'];
                    }
                }
                
                // percentage yoksa hesapla
                if (!isset($normalized['percentage'])) {
                    if ($normalized['total_questions'] > 0) {
                        $normalized['percentage'] = ($normalized['correct_answers'] / $normalized['total_questions']) * 100;
                    } else {
                        $normalized['percentage'] = 0;
                    }
                }
                
                $normalizedPractice[] = $normalized;
            }
            $studentProgress['practice'] = $normalizedPractice;
            
            $normalizedExams = [];
            foreach ($studentProgress['exams'] as $e) {
                $normalized = $e; // Kopyala
                
                // created_at yoksa submit_time'dan al
                if (!isset($normalized['created_at'])) {
                    $normalized['created_at'] = $normalized['submit_time'] ?? $normalized['timestamp'] ?? date('Y-m-d H:i:s');
                }
                
                // exam_id yoksa exam_code'dan al
                if (!isset($normalized['exam_id'])) {
                    $normalized['exam_id'] = $normalized['exam_code'] ?? 'N/A';
                }
                
                // correct_answers yoksa correct'ten al veya score'dan hesapla
                if (!isset($normalized['correct_answers'])) {
                    if (isset($normalized['correct'])) {
                        $normalized['correct_answers'] = $normalized['correct'];
                    } elseif (isset($normalized['score'])) {
                        // Her doğru 20 puan varsayımı
                        $normalized['correct_answers'] = $normalized['score'] / 20;
                    } else {
                        $normalized['correct_answers'] = 0;
                    }
                }
                
                // wrong_answers yoksa wrong'dan al
                if (!isset($normalized['wrong_answers'])) {
                    $normalized['wrong_answers'] = $normalized['wrong'] ?? 0;
                }
                
                // total_questions yoksa questions'dan al veya doğru+yanlıştan hesapla
                if (!isset($normalized['total_questions']) || $normalized['total_questions'] == 0) {
                    if (isset($normalized['questions']) && is_array($normalized['questions'])) {
                        $normalized['total_questions'] = count($normalized['questions']);
                    } else {
                        // Doğru + Yanlış = Toplam
                        $normalized['total_questions'] = $normalized['correct_answers'] + $normalized['wrong_answers'];
                    }
                }
                
                // percentage yoksa hesapla
                if (!isset($normalized['percentage'])) {
                    if ($normalized['total_questions'] > 0) {
                        $normalized['percentage'] = ($normalized['correct_answers'] / $normalized['total_questions']) * 100;
                    } else {
                        $normalized['percentage'] = 0;
                    }
                }
                
                $normalizedExams[] = $normalized;
            }
            $studentProgress['exams'] = $normalizedExams;
            
            // Tarih ve skor filtrelerini uygula
            if ($startDate) {
                $studentProgress['practice'] = array_filter($studentProgress['practice'], function($r) use ($startDate) {
                    return strtotime($r['created_at'] ?? '') >= strtotime($startDate);
                });
                $studentProgress['exams'] = array_filter($studentProgress['exams'], function($r) use ($startDate) {
                    $date = $r['created_at'] ?? $r['submit_time'] ?? '';
                    return strtotime($date) >= strtotime($startDate);
                });
            }
            
            if ($endDate) {
                $studentProgress['practice'] = array_filter($studentProgress['practice'], function($r) use ($endDate) {
                    return strtotime($r['created_at'] ?? '') <= strtotime($endDate);
                });
                $studentProgress['exams'] = array_filter($studentProgress['exams'], function($r) use ($endDate) {
                    $date = $r['created_at'] ?? $r['submit_time'] ?? '';
                    return strtotime($date) <= strtotime($endDate);
                });
            }
            
            if ($minScore) {
                $studentProgress['practice'] = array_filter($studentProgress['practice'], function($r) use ($minScore) {
                    return (float)($r['percentage'] ?? 0) >= (float)$minScore;
                });
                $studentProgress['exams'] = array_filter($studentProgress['exams'], function($r) use ($minScore) {
                    return (float)($r['percentage'] ?? 0) >= (float)$minScore;
                });
            }
            
            // Array'leri sırala
            usort($studentProgress['practice'], function($a, $b) {
                return strcmp($b['created_at'] ?? '', $a['created_at'] ?? '');
            });
            usort($studentProgress['exams'], function($a, $b) {
                $dateA = $a['created_at'] ?? $a['submit_time'] ?? '';
                $dateB = $b['created_at'] ?? $b['submit_time'] ?? '';
                return strcmp($dateB, $dateA);
            });
            
            $debugInfo['json_practice_count'] = count($studentProgress['practice']);
            $debugInfo['json_exam_count'] = count($studentProgress['exams']);
            $debugInfo['source'] = 'JSON files';
            
            // Tüm alıştırma detaylarını ekle
            $debugInfo['all_practice'] = [];
            foreach ($studentProgress['practice'] as $idx => $p) {
                $debugInfo['all_practice'][] = [
                    'index' => $idx + 1,
                    'date' => $p['created_at'] ?? 'YOK',
                    'total_questions' => $p['total_questions'] ?? 'YOK',
                    'correct' => $p['correct_answers'] ?? 'YOK',
                    'wrong' => $p['wrong_answers'] ?? 'YOK',
                    'percentage' => $p['percentage'] ?? 'YOK'
                ];
            }
            
            // Tüm sınav detaylarını ekle
            $debugInfo['all_exams'] = [];
            foreach ($studentProgress['exams'] as $idx => $e) {
                $debugInfo['all_exams'][] = [
                    'index' => $idx + 1,
                    'exam_id' => $e['exam_id'] ?? 'YOK',
                    'date' => $e['created_at'] ?? 'YOK',
                    'total_questions' => $e['total_questions'] ?? 'YOK',
                    'correct' => $e['correct_answers'] ?? 'YOK',
                    'percentage' => $e['percentage'] ?? 'YOK'
                ];
            }
        } else {
            $debugInfo['source'] = 'Database';
        }
        
    } catch (Exception $e) {
        $debugInfo['error'] = $e->getMessage();
        // Hata durumunda boş array
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Öğrenci Gelişimi - Modern Admin Panel</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --secondary: #3498db;
            --success: #27ae60;
            --warning: #f39c12;
            --danger: #e74c3c;
            --dark: #2c3e50;
            --light: #ecf0f1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            color: #333;
            min-height: 100vh;
        }

        /* Dark Theme */
        body.dark {
            background: #0f172a;
            color: #e2e8f0;
        }

        body.dark .header {
            background: rgba(15, 23, 42, 0.95);
            border-bottom: 1px solid #1e293b;
        }

        body.dark .card {
            background: rgba(30, 41, 59, 0.8);
            border: 1px solid #1e293b;
        }

        body.dark .stat-card {
            background: linear-gradient(135deg, rgba(30, 41, 59, 0.9) 0%, rgba(15, 23, 42, 0.9) 100%);
            border: 1px solid #1e293b;
        }

        body.dark .stat-card h3,
        body.dark .card h2,
        body.dark .card h3 {
            color: #e2e8f0;
        }

        body.dark .stat-card p,
        body.dark .muted {
            color: #94a3b8;
        }

        body.dark .table th {
            background: rgba(15, 23, 42, 0.5);
            color: #e2e8f0;
        }

        body.dark .table td {
            color: #cbd5e1;
            border-bottom-color: #1e293b;
        }

        body.dark .filter-btn,
        body.dark select,
        body.dark input {
            background: rgba(30, 41, 59, 0.8);
            border-color: #1e293b;
            color: #e2e8f0;
        }

        body.dark .badge {
            background: rgba(30, 41, 59, 0.8);
            border-color: #1e293b;
            color: #cbd5e1;
        }

        body.dark .search-box {
            background: rgba(30, 41, 59, 0.8);
            border-color: #1e293b;
        }

        body.dark .search-box input {
            color: #e2e8f0;
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
            padding: 20px 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-left h1 {
            font-size: 1.8em;
            color: var(--dark);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-btn {
            background: rgba(6, 133, 103, 0.1);
            border: 1px solid rgba(6, 133, 103, 0.2);
            color: var(--primary);
            padding: 10px 20px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .header-btn:hover {
            background: var(--primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);
        }

        /* Container */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%);
            backdrop-filter: blur(20px);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
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
            background: linear-gradient(90deg, var(--primary), var(--secondary));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5em;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);
        }

        .stat-card h3 {
            font-size: 2.5em;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #7f8c8d;
            font-size: 0.95em;
            font-weight: 500;
        }

        /* Card */
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            margin-bottom: 25px;
        }

        .card h2, .card h3 {
            color: var(--dark);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Filters */
        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .filter-group label {
            font-weight: 600;
            font-size: 0.9em;
            color: #7f8c8d;
        }

        select, input {
            padding: 12px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            background: white;
        }

        select:focus, input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(6, 133, 103, 0.1);
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border: none;
            padding: 12px 25px;
            border-radius: 12px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);
        }

        .filter-btn.secondary {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        /* Search Box */
        .search-box {
            position: relative;
            margin-bottom: 20px;
        }

        .search-box input {
            width: 100%;
            padding-left: 45px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7f8c8d;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
            border-radius: 15px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px;
            text-align: left;
            font-size: 0.95rem;
        }

        .table th {
            background: rgba(6, 133, 103, 0.1);
            font-weight: 700;
            color: var(--dark);
            cursor: pointer;
            user-select: none;
            transition: all 0.3s ease;
        }

        .table th:hover {
            background: rgba(6, 133, 103, 0.2);
        }

        .table th i {
            margin-left: 5px;
            font-size: 0.8em;
        }

        .table tbody tr {
            border-bottom: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }

        .table tbody tr:hover {
            background: rgba(6, 133, 103, 0.05);
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid;
        }

        .badge-success {
            background: rgba(39, 174, 96, 0.1);
            border-color: rgba(39, 174, 96, 0.3);
            color: #27ae60;
        }

        .badge-warning {
            background: rgba(243, 156, 18, 0.1);
            border-color: rgba(243, 156, 18, 0.3);
            color: #f39c12;
        }

        .badge-danger {
            background: rgba(231, 76, 60, 0.1);
            border-color: rgba(231, 76, 60, 0.3);
            color: #e74c3c;
        }

        .badge-info {
            background: rgba(52, 152, 219, 0.1);
            border-color: rgba(52, 152, 219, 0.3);
            color: #3498db;
        }

        /* Chart Container */
        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        /* Grid */
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 25px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .grid {
                grid-template-columns: 1fr;
            }

            .filters {
                grid-template-columns: 1fr;
            }

            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }

        /* Muted */
        .muted {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.3;
        }
    </style>
</head>
<body class="dark">
    <!-- Header -->
    <div class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="dashboard.php" style="text-decoration: none; color: inherit;">
                    <h1><i class="fas fa-chart-line"></i> Öğrenci Gelişimi</h1>
                </a>
            </div>
            <div class="header-right">
                <button id="themeToggle" class="header-btn">
                    <i class="fas fa-moon"></i> Tema
                </button>
                <a href="dashboard.php" class="header-btn">
                    <i class="fas fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <?php
        // Calculate stats for selected user
        $pCount = count($studentProgress['practice']);
        $eCount = count($studentProgress['exams']);
        
        // Calculate averages
        $avgPractice = 0;
        $avgExam = 0;
        
        if ($pCount > 0) {
            $total = 0;
            foreach ($studentProgress['practice'] as $p) {
                $total += (float)($p['percentage'] ?? 0);
            }
            $avgPractice = $total / $pCount;
        }
        
        if ($eCount > 0) {
            $total = 0;
            foreach ($studentProgress['exams'] as $e) {
                $total += (float)($e['percentage'] ?? 0);
            }
            $avgExam = $total / $eCount;
        }
        
        $totalActivities = $pCount + $eCount;
        $overallAvg = $totalActivities > 0 ? (($avgPractice * $pCount) + ($avgExam * $eCount)) / $totalActivities : 0;
        ?>

        <!-- Debug Info (sadece geliştirme için) -->
        <?php if (!empty($debugInfo) && isset($_GET['debug'])): ?>
        <div class="card fade-in" style="background: linear-gradient(135deg, rgba(243, 156, 18, 0.1) 0%, rgba(230, 126, 34, 0.1) 100%); border: 2px solid #f39c12;">
            <h3 style="color: #f39c12; margin-bottom: 15px;"><i class="fas fa-bug"></i> Debug Bilgileri</h3>
            <div style="font-family: monospace; font-size: 0.9em; background: rgba(0,0,0,0.05); padding: 15px; border-radius: 10px;">
                <div><strong>Veri Kaynağı:</strong> <?php echo htmlspecialchars($debugInfo['source'] ?? 'Unknown'); ?></div>
                <div><strong>Kullanıcı:</strong> <?php echo htmlspecialchars($selectedUser); ?></div>
                <?php if (isset($debugInfo['db_practice_count'])): ?>
                <div><strong>DB Alıştırma:</strong> <?php echo $debugInfo['db_practice_count']; ?></div>
                <div><strong>DB Sınav:</strong> <?php echo $debugInfo['db_exam_count']; ?></div>
                <?php endif; ?>
                <?php if (isset($debugInfo['json_practice_count'])): ?>
                <div><strong>JSON Alıştırma:</strong> <?php echo $debugInfo['json_practice_count']; ?></div>
                <div><strong>JSON Sınav:</strong> <?php echo $debugInfo['json_exam_count']; ?></div>
                
                <?php if (!empty($debugInfo['all_practice'])): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                    <strong style="font-size: 1.1em;">📝 Tüm Alıştırmalar:</strong>
                    <table style="width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 0.85em;">
                        <thead>
                            <tr style="background: #f0f0f0;">
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">#</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Tarih</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Toplam</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Doğru</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Yanlış</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Yüzde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debugInfo['all_practice'] as $p): ?>
                            <tr>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo $p['index']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($p['date']); ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center;"><?php echo $p['total_questions']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; color: #27ae60; font-weight: bold;"><?php echo $p['correct']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; color: #e74c3c; font-weight: bold;"><?php echo $p['wrong']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo $p['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($debugInfo['all_exams'])): ?>
                <div style="margin-top: 15px; padding-top: 15px; border-top: 2px solid #ddd;">
                    <strong style="font-size: 1.1em;">📋 Tüm Sınavlar:</strong>
                    <table style="width: 100%; margin-top: 10px; border-collapse: collapse; font-size: 0.85em;">
                        <thead>
                            <tr style="background: #f0f0f0;">
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">#</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Sınav ID</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: left;">Tarih</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Toplam</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Doğru</th>
                                <th style="padding: 8px; border: 1px solid #ddd; text-align: center;">Yüzde</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($debugInfo['all_exams'] as $e): ?>
                            <tr>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo $e['index']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($e['exam_id']); ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd;"><?php echo htmlspecialchars($e['date']); ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center;"><?php echo $e['total_questions']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; color: #27ae60; font-weight: bold;"><?php echo $e['correct']; ?></td>
                                <td style="padding: 6px; border: 1px solid #ddd; text-align: center; font-weight: bold;"><?php echo $e['percentage']; ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php endif; ?>
                <?php if (isset($debugInfo['error'])): ?>
                <div style="color: #e74c3c;"><strong>Hata:</strong> <?php echo htmlspecialchars($debugInfo['error']); ?></div>
                <?php endif; ?>
            </div>
            <div style="margin-top: 10px; font-size: 0.85em; color: #7f8c8d;">
                <i class="fas fa-info-circle"></i> Debug modunu kapatmak için URL'den <code>?debug</code> parametresini kaldırın.
            </div>
        </div>
        <?php endif; ?>

        <!-- Student Info Card -->
        <?php if ($selectedStudentInfo): ?>
        <div class="card fade-in" style="background: linear-gradient(135deg, rgba(6, 133, 103, 0.1) 0%, rgba(52, 152, 219, 0.1) 100%);">
            <div style="display: flex; align-items: center; gap: 20px; flex-wrap: wrap;">
                <div style="width: 60px; height: 60px; border-radius: 50%; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 1.8em; font-weight: 700; box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);">
                    <?php echo strtoupper(substr($selectedStudentInfo['full_name'], 0, 1)); ?>
                </div>
                <div style="flex: 1;">
                    <h2 style="margin: 0 0 5px 0; font-size: 1.5em;">
                        <i class="fas fa-user-graduate"></i> 
                        <?php echo htmlspecialchars($selectedStudentInfo['full_name']); ?>
                    </h2>
                    <div style="display: flex; gap: 15px; flex-wrap: wrap; color: #7f8c8d;">

                        <?php if ($selectedStudentInfo['branch']): ?>
                        <span><i class="fas fa-school"></i> <strong>Şube:</strong> <?php echo htmlspecialchars($selectedStudentInfo['branch']); ?></span>
                        <?php endif; ?>
                        <span><i class="fas fa-id-badge"></i> <strong>Kullanıcı Adı:</strong> <?php echo htmlspecialchars($selectedStudentInfo['username']); ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid fade-in">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-user-graduate"></i></div>
                </div>
                <h3><?php echo count($filteredStudents); ?></h3>
                <p>Toplam Öğrenci</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-dumbbell"></i></div>
                </div>
                <h3><?php echo $pCount; ?></h3>
                <p>Alıştırma Sayısı</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-file-alt"></i></div>
                </div>
                <h3><?php echo $eCount; ?></h3>
                <p>Sınav Sayısı</p>
            </div>
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-icon"><i class="fas fa-trophy"></i></div>
                </div>
                <h3><?php echo number_format($overallAvg, 1); ?>%</h3>
                <p>Genel Ortalama</p>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card fade-in">
            <h2><i class="fas fa-filter"></i> Filtreler</h2>
            
            <!-- Search Box -->
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="studentSearch" placeholder="Öğrenci ara..." value="<?php echo htmlspecialchars($selectedUser); ?>">
            </div>

            <form method="GET" id="filterForm">
                <div class="filters">
                    <div class="filter-group">
                        <label><i class="fas fa-school"></i> Şube</label>
                        <select name="branch" id="branchSelect" onchange="this.form.submit()">
                            <option value="">Tüm Branşlar</option>
                            <?php foreach ($allBranches as $branch): ?>
                                <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $branch===$selectedBranch?'selected':''; ?>>
                                    <?php echo htmlspecialchars($branch); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    

                    
                    <div class="filter-group">
                        <label><i class="fas fa-user"></i> Öğrenci Seç</label>
                        <select name="user" id="userSelect" onchange="this.form.submit()">
                            <?php if (empty($filteredStudents)): ?>
                                <option value="">Öğrenci bulunamadı</option>
                            <?php else: ?>
                                <?php foreach ($filteredStudents as $student): ?>
                                    <option value="<?php echo htmlspecialchars($student['username']); ?>" 
                                            <?php echo $student['username']===$selectedUser?'selected':''; ?>>
                                        <?php echo htmlspecialchars($student['full_name']); ?>
                                        <?php if ($student['class_section']): ?>
                                            (<?php echo htmlspecialchars($student['class_section']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar"></i> Başlangıç Tarihi</label>
                        <input type="date" name="start_date" id="startDate" value="<?php echo htmlspecialchars($startDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-calendar-check"></i> Bitiş Tarihi</label>
                        <input type="date" name="end_date" id="endDate" value="<?php echo htmlspecialchars($endDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-percentage"></i> Min. Başarı Oranı</label>
                        <input type="number" name="min_score" id="minScore" placeholder="0" min="0" max="100" value="<?php echo htmlspecialchars($minScore); ?>">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-check"></i> Filtrele
                    </button>
                    <button type="button" class="filter-btn secondary" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Sıfırla
                    </button>
                </div>
            </form>
        </div>

        <!-- Performance Chart -->
        <div class="card fade-in">
            <h2><i class="fas fa-chart-area"></i> Performans Grafiği</h2>
            <div class="chart-container">
                <canvas id="performanceChart"></canvas>
            </div>
        </div>

        <!-- Tables Grid -->
        <div class="grid fade-in">
            <!-- Practice Table -->
            <div class="card">
                <h3><i class="fas fa-dumbbell"></i> Alıştırmalar (<?php echo $pCount; ?>)</h3>
                <div class="table-container">
                    <table class="table" id="practiceTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('practiceTable', 0)">Tarih <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 1)">Soru <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 2)">Doğru <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 3)">Yanlış <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('practiceTable', 4)">Başarı <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentProgress['practice'])): ?>
                                <?php foreach ($studentProgress['practice'] as $row): 
                                    $percentage = (float)($row['percentage'] ?? 0);
                                    $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? '-'); ?></td>
                                        <td><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                        <td><span class="badge badge-success"><?php echo (int)($row['correct_answers'] ?? 0); ?></span></td>
                                        <td><span class="badge badge-danger"><?php echo (int)($row['wrong_answers'] ?? 0); ?></span></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><br>Kayıt bulunamadı</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Exam Table -->
            <div class="card">
                <h3><i class="fas fa-file-alt"></i> Sınavlar (<?php echo $eCount; ?>)</h3>
                <div class="table-container">
                    <table class="table" id="examTable">
                        <thead>
                            <tr>
                                <th onclick="sortTable('examTable', 0)">Tarih <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 1)">Sınav ID <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 2)">Toplam <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 3)">Doğru <i class="fas fa-sort"></i></th>
                                <th onclick="sortTable('examTable', 4)">Başarı <i class="fas fa-sort"></i></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($studentProgress['exams'])): ?>
                                <?php foreach ($studentProgress['exams'] as $row): 
                                    $percentage = (float)($row['percentage'] ?? 0);
                                    $badgeClass = $percentage >= 80 ? 'badge-success' : ($percentage >= 60 ? 'badge-warning' : 'badge-danger');
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['created_at'] ?? $row['submit_time'] ?? '-'); ?></td>
                                        <td><span class="badge badge-info"><?php echo htmlspecialchars($row['exam_id'] ?? '-'); ?></span></td>
                                        <td><?php echo (int)($row['total_questions'] ?? 0); ?></td>
                                        <td><span class="badge badge-success"><?php echo (int)($row['correct_answers'] ?? 0); ?></span></td>
                                        <td><span class="badge <?php echo $badgeClass; ?>"><?php echo number_format($percentage, 1); ?>%</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="empty-state"><i class="fas fa-inbox"></i><br>Kayıt bulunamadı</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Theme Toggle
        document.addEventListener('DOMContentLoaded', function() {
            const themeToggle = document.getElementById('themeToggle');
            const body = document.body;
            
            // Load saved theme
            const savedTheme = localStorage.getItem('student_progress_theme') || 'dark';
            if (savedTheme === 'dark') {
                body.classList.add('dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i> Tema';
            } else {
                body.classList.remove('dark');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i> Tema';
            }
            
            themeToggle.addEventListener('click', function() {
                body.classList.toggle('dark');
                const isDark = body.classList.contains('dark');
                localStorage.setItem('student_progress_theme', isDark ? 'dark' : 'light');
                themeToggle.innerHTML = isDark ? '<i class="fas fa-sun"></i> Tema' : '<i class="fas fa-moon"></i> Tema';
            });
        });

        // Student Search
        document.getElementById('studentSearch').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const select = document.getElementById('userSelect');
            const options = select.options;
            
            for (let i = 0; i < options.length; i++) {
                const optionText = options[i].text.toLowerCase();
                if (optionText.includes(searchTerm)) {
                    options[i].style.display = '';
                } else {
                    options[i].style.display = 'none';
                }
            }
        });

        // Reset Filters
        function resetFilters() {
            window.location.href = 'student_progress.php';
        }

        // Table Sorting
        function sortTable(tableId, column) {
            const table = document.getElementById(tableId);
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            // Skip if empty state
            if (rows.length === 1 && rows[0].cells.length === 1) return;
            
            const isAscending = table.dataset.sortOrder === 'asc';
            
            rows.sort((a, b) => {
                let aValue = a.cells[column].textContent.trim();
                let bValue = b.cells[column].textContent.trim();
                
                // Try to parse as number
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return isAscending ? aNum - bNum : bNum - aNum;
                }
                
                // String comparison
                return isAscending ? aValue.localeCompare(bValue) : bValue.localeCompare(aValue);
            });
            
            // Update table
            rows.forEach(row => tbody.appendChild(row));
            
            // Toggle sort order
            table.dataset.sortOrder = isAscending ? 'desc' : 'asc';
        }

        // Performance Chart
        <?php
        // Prepare chart data
        $chartLabels = [];
        $practiceData = [];
        $examData = [];
        
        // Combine and sort all activities by date
        $allActivities = [];
        
        if (!empty($studentProgress['practice'])) {
            foreach ($studentProgress['practice'] as $p) {
                $date = $p['created_at'] ?? '';
                if ($date) {
                    $allActivities[] = ['date' => $date, 'type' => 'practice', 'percentage' => (float)($p['percentage'] ?? 0)];
                }
            }
        }
        
        if (!empty($studentProgress['exams'])) {
            foreach ($studentProgress['exams'] as $e) {
                $date = $e['created_at'] ?? $e['submit_time'] ?? '';
                if ($date) {
                    $allActivities[] = ['date' => $date, 'type' => 'exam', 'percentage' => (float)($e['percentage'] ?? 0)];
                }
            }
        }
        
        // Sort by date
        usort($allActivities, function($a, $b) {
            return strcmp($a['date'], $b['date']);
        });
        
        // Take last 10 activities
        $recentActivities = array_slice($allActivities, -10);
        
        foreach ($recentActivities as $activity) {
            $chartLabels[] = substr($activity['date'], 0, 10);
            if ($activity['type'] === 'practice') {
                $practiceData[] = $activity['percentage'];
                $examData[] = null;
            } else {
                $examData[] = $activity['percentage'];
                $practiceData[] = null;
            }
        }
        ?>
        
        const ctx = document.getElementById('performanceChart');
        if (ctx) {
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode($chartLabels); ?>,
                    datasets: [
                        {
                            label: 'Alıştırma',
                            data: <?php echo json_encode($practiceData); ?>,
                            borderColor: '#068567',
                            backgroundColor: 'rgba(6, 133, 103, 0.1)',
                            tension: 0.4,
                            fill: true
                        },
                        {
                            label: 'Sınav',
                            data: <?php echo json_encode($examData); ?>,
                            borderColor: '#3498db',
                            backgroundColor: 'rgba(52, 152, 219, 0.1)',
                            tension: 0.4,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        title: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>




