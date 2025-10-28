<?php
/**
 * Soru Yükleme Sayfası
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Admin kontrolü
if (!$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$message = '';
$messageType = '';

// Soru yükleme işlemi
if (isset($_POST['action']) && $_POST['action'] === 'load_questions') {
    try {
        // Mevcut session verilerini temizle
        unset($_SESSION['all_questions']);
        unset($_SESSION['categories']);
        unset($_SESSION['banks']);
        unset($_SESSION['question_errors']);
        
        // Soruları yeniden yükle
        $questionLoader = new QuestionLoader();
        $questionLoader->loadQuestions();
        
        $questions = $_SESSION['all_questions'] ?? [];
        $banks = $_SESSION['banks'] ?? [];
        $errors = $_SESSION['question_errors'] ?? [];
        
        if (count($questions) > 0) {
            $message = "✅ " . count($questions) . " soru başarıyla yüklendi! " . count($banks) . " soru bankası bulundu.";
            $messageType = 'success';
        } else {
            $message = "⚠️ Hiç soru yüklenemedi. Lütfen soru dosyalarını kontrol edin.";
            $messageType = 'warning';
        }
        
        if (count($errors) > 0) {
            $message .= " (" . count($errors) . " hata bulundu)";
        }
        
    } catch (Exception $e) {
        $message = "❌ Soru yükleme hatası: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Mevcut durumu al
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];
$errors = $_SESSION['question_errors'] ?? [];

$totalQuestions = count($questions);
$totalBanks = count($banks);
$totalCategories = array_sum(array_map('count', $categories));
$errorCount = count($errors);
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Yükleme - Bir Soru Bir Sevap</title>
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

        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
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
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
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

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        .main-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .sidebar {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .widget {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .widget h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 1.2em;
        }

        .btn {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
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
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-large {
            padding: 20px 40px;
            font-size: 1.2em;
            width: 100%;
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .error-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .error-item {
            padding: 10px;
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 0.9em;
            color: #721c24;
        }

        .bank-list {
            max-height: 200px;
            overflow-y: auto;
        }

        .bank-item {
            padding: 10px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 5px;
            margin-bottom: 10px;
            font-size: 0.9em;
            color: #155724;
        }

        @media (max-width: 768px) {
            .content-grid {
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
                    <p>Soru Yükleme Paneli</p>
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
            <a href="dashboard.php">Dashboard</a> > Soru Yükleme
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">📊</div>
                <div class="stat-number"><?php echo $totalQuestions; ?></div>
                <div class="stat-label">Toplam Soru</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-number"><?php echo $totalBanks; ?></div>
                <div class="stat-label">Soru Bankası</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📁</div>
                <div class="stat-number"><?php echo $totalCategories; ?></div>
                <div class="stat-label">Kategori</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">⚠️</div>
                <div class="stat-number"><?php echo $errorCount; ?></div>
                <div class="stat-label">Hata</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="main-content">
                <h2>🔄 Soru Yükleme İşlemi</h2>
                <p style="margin-bottom: 25px; color: #7f8c8d;">
                    Soru bankalarını yeniden yüklemek için aşağıdaki butona tıklayın.
                </p>

                <form method="POST" style="margin-bottom: 30px;">
                    <input type="hidden" name="action" value="load_questions">
                    <button type="submit" class="btn btn-large">
                        🔄 Soruları Yeniden Yükle
                    </button>
                </form>

                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px;">
                    <h3>📋 Yükleme Bilgileri</h3>
                    <p><strong>Soru Klasörü:</strong> <?php echo htmlspecialchars(defined('ROOT_DIR') ? ROOT_DIR : 'Sorular'); ?></p>
                    <p><strong>Klasör Durumu:</strong> 
                        <span style="color: <?php echo is_dir(defined('ROOT_DIR') ? ROOT_DIR : 'Sorular') ? '#27ae60' : '#e74c3c'; ?>">
                            <?php echo is_dir(defined('ROOT_DIR') ? ROOT_DIR : 'Sorular') ? '✅ Mevcut' : '❌ Bulunamadı'; ?>
                        </span>
                    </p>
                    <p><strong>İzin Verilen Uzantılar:</strong> <?php echo implode(', ', defined('ALLOWED_EXTENSIONS') ? ALLOWED_EXTENSIONS : ['json']); ?></p>
                    <p><strong>Maksimum Tarama Derinliği:</strong> <?php echo defined('MAX_SCAN_DEPTH') ? MAX_SCAN_DEPTH : 5; ?></p>
                    
                    <h4 style="margin-top: 20px; color: #2c3e50;">🔍 Debug Bilgileri</h4>
                    <div style="background: #fff; padding: 15px; border-radius: 8px; font-family: monospace; font-size: 0.9em; margin-top: 10px;">
                        <p><strong>Mevcut Çalışma Dizini:</strong> <?php echo getcwd(); ?></p>
                        <p><strong>Script Dizini:</strong> <?php echo __DIR__; ?></p>
                        <p><strong>Parent Dizini:</strong> <?php echo dirname(__DIR__); ?></p>
                        <p><strong>Alternatif Yol 1:</strong> <?php echo __DIR__ . DIRECTORY_SEPARATOR . 'Sorular'; ?> 
                            <span style="color: <?php echo is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'Sorular') ? '#27ae60' : '#e74c3c'; ?>">
                                (<?php echo is_dir(__DIR__ . DIRECTORY_SEPARATOR . 'Sorular') ? 'Mevcut' : 'Yok'; ?>)
                            </span>
                        </p>
                        <p><strong>Alternatif Yol 2:</strong> <?php echo dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Sorular'; ?> 
                            <span style="color: <?php echo is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Sorular') ? '#27ae60' : '#e74c3c'; ?>">
                                (<?php echo is_dir(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Sorular') ? 'Mevcut' : 'Yok'; ?>)
                            </span>
                        </p>
                        <p><strong>Alternatif Yol 3:</strong> <?php echo getcwd() . DIRECTORY_SEPARATOR . 'Sorular'; ?> 
                            <span style="color: <?php echo is_dir(getcwd() . DIRECTORY_SEPARATOR . 'Sorular') ? '#27ae60' : '#e74c3c'; ?>">
                                (<?php echo is_dir(getcwd() . DIRECTORY_SEPARATOR . 'Sorular') ? 'Mevcut' : 'Yok'; ?>)
                            </span>
                        </p>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div>
                        <h3>⚠️ Sistem Hataları</h3>
                        <div class="error-list">
                            <?php foreach ($errors as $error): ?>
                                <div class="error-item">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <div class="sidebar">
                <div class="widget">
                    <h3>📚 Yüklenen Soru Bankaları</h3>
                    <div class="bank-list">
                        <?php if (!empty($banks)): ?>
                            <?php foreach ($banks as $bank): ?>
                                <div class="bank-item">
                                    <strong><?php echo htmlspecialchars($bank); ?></strong><br>
                                    <span><?php echo count($categories[$bank] ?? []); ?> kategori</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p style="color: #7f8c8d; text-align: center;">Henüz soru bankası yüklenmedi</p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="widget">
                    <h3>⚡ Hızlı İşlemler</h3>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <a href="dashboard.php" class="btn btn-secondary">Dashboard'a Dön</a>
                        <a href="settings.php" class="btn btn-secondary">Sistem Ayarları</a>
                        <a href="../index.php" class="btn btn-secondary">Ana Sayfa</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
