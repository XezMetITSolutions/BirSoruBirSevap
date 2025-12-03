<?php
/**
 * Süper Admin - Raporlar
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';
require_once '../QuestionLoader.php'; // QuestionLoader sınıfını dahil et

$auth = Auth::getInstance();
$db = Database::getInstance();
$conn = $db->getConnection();

// Admin kontrolü
if (!$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Kurum listesi
$institutions = [
    'IQRA Bludenz',
    'IQRA Bregenz', 
    'IQRA Dornbirn',
    'IQRA Feldkirch',
    'IQRA Hall in Tirol',
    'IQRA Innsbruck',
    'IQRA Jenbach',
    'IQRA Lustenau',
    'IQRA Radfeld',
    'IQRA Reutte',
    'IQRA Vomp',
    'IQRA Wörgl',
    'IQRA Zirl'
];

// Gerçek rapor verilerini yükle
$allUsers = $auth->getAllUsers();

// Kullanıcı istatistikleri
$totalUsers = count($allUsers);
$activeUsers = 0;
$institutionStats = [];

foreach ($allUsers as $u) {
    if ($u['role'] !== 'superadmin') {
        $institution = $u['branch'] ?? $u['institution'] ?? 'Bilinmiyor';
        if (!isset($institutionStats[$institution])) {
            $institutionStats[$institution] = ['users' => 0, 'exams' => 0, 'questions' => 0];
        }
        $institutionStats[$institution]['users']++;
        $activeUsers++;
    }
}

// Sınav sayısını veritabanından hesapla
try {
    $sql = "SELECT COUNT(*) FROM exams";
    $stmt = $conn->query($sql);
    $totalExams = $stmt->fetchColumn();
} catch (Exception $e) {
    $totalExams = 0;
}

// Soru sayısını hesapla
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();
$questions = $_SESSION['all_questions'] ?? [];
$totalQuestions = count($questions);

$reportData = [
    'total_users' => $totalUsers,
    'active_users' => $activeUsers,
    'total_exams' => $totalExams,
    'total_questions' => $totalQuestions,
    'institution_stats' => $institutionStats
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f8f9fa;
            color: #333;
        }

        .header {
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            color: white;
            padding: 20px 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

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

        .logout-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .breadcrumb {
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #089b76;
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-icon {
            font-size: 2.5em;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .reports-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .report-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .report-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .institution-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .institution-table th,
        .institution-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e1e8ed;
        }

        .institution-table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .institution-table tr:hover {
            background: #f8f9fa;
        }

        .btn {
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
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
            margin: 5px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .export-options {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 20px;
        }

        .chart-container {
            height: 300px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
            font-size: 1.1em;
        }

        @media (max-width: 768px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 15px;
            }
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
                    <p>Süper Admin Paneli</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;">Süper Admin</div>
                </div>
                <a href="../logout.php" class="logout-btn">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> > Raporlar
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-number"><?php echo $reportData['total_users']; ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-number"><?php echo $reportData['active_users']; ?></div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📝</div>
                <div class="stat-number"><?php echo $reportData['total_exams']; ?></div>
                <div class="stat-label">Toplam Sınav</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">❓</div>
                <div class="stat-number"><?php echo $reportData['total_questions']; ?></div>
                <div class="stat-label">Toplam Soru</div>
            </div>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <h2>🏢 Kurum Bazlı İstatistikler</h2>
                <table class="institution-table">
                    <thead>
                        <tr>
                            <th>Kurum</th>
                            <th>Kullanıcı</th>
                            <th>Sınav</th>
                            <th>Soru</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reportData['institution_stats'] as $institution => $stats): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($institution); ?></td>
                                <td><?php echo $stats['users']; ?></td>
                                <td><?php echo $stats['exams']; ?></td>
                                <td><?php echo $stats['questions']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="export-options">
                    <button class="btn btn-success" onclick="exportToCSV()">CSV İndir</button>
                    <button class="btn btn-secondary" onclick="exportToPDF()">PDF İndir</button>
                    <button class="btn" onclick="printReport()">Yazdır</button>
                </div>
            </div>

            <div class="report-card">
                <h2>📊 Grafik Raporları</h2>
                <div class="chart-container">
                    📈 Grafik görünümü<br>
                    <small>(Geliştirme aşamasında)</small>
                </div>

                <div style="margin-top: 20px;">
                    <h3>Hızlı Raporlar</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <a href="#" class="btn btn-secondary">Günlük Rapor</a>
                        <a href="#" class="btn btn-secondary">Haftalık Rapor</a>
                        <a href="#" class="btn btn-secondary">Aylık Rapor</a>
                        <a href="#" class="btn btn-secondary">Yıllık Rapor</a>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <h3>Özel Raporlar</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-top: 15px;">
                        <a href="#" class="btn">Kullanıcı Aktivite Raporu</a>
                        <a href="#" class="btn">Sınav Performans Raporu</a>
                        <a href="#" class="btn">Soru Analiz Raporu</a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function exportToCSV() {
            alert('CSV export özelliği geliştirme aşamasında!');
        }

        function exportToPDF() {
            alert('PDF export özelliği geliştirme aşamasında!');
        }

        function printReport() {
            window.print();
        }
    </script>
</body>
</html>