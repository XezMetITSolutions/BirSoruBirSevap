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
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Yükleme - Bir Soru Bir Sevap</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dark-theme.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <?php include 'sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-wrapper">
        <div class="top-bar">
            <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </div>
            <div class="welcome-text">
                <h2>Soru Yükleme</h2>
                <p>Soru bankalarını yönetin ve güncelleyin</p>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="<?php echo $messageType == 'success' ? 'success-box' : 'alert-box'; ?>">
                <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <i class="fas fa-question-circle stat-icon"></i>
                <div class="stat-value"><?php echo number_format($totalQuestions); ?></div>
                <div class="stat-label">Toplam Soru</div>
                <div class="progress-mini"><div class="progress-fill" style="width:100%; background:#3b82f6;"></div></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-database stat-icon"></i>
                <div class="stat-value"><?php echo $totalBanks; ?></div>
                <div class="stat-label">Soru Bankası</div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo min(100, $totalBanks*10); ?>%; background:#8b5cf6;"></div></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-folder stat-icon"></i>
                <div class="stat-value"><?php echo $totalCategories; ?></div>
                <div class="stat-label">Kategori</div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo min(100, $totalCategories*5); ?>%; background:#ec4899;"></div></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-bug stat-icon"></i>
                <div class="stat-value" style="color:<?php echo $errorCount > 0 ? '#fca5a5' : '#86efac'; ?>"><?php echo $errorCount; ?></div>
                <div class="stat-label">Hata</div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo $errorCount > 0 ? '100%' : '0%'; ?>; background:#ef4444;"></div></div>
            </div>
        </div>

        <div class="content-row">
            <div class="glass-panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-sync"></i> Soru Yükleme İşlemi</div>
                </div>
                
                <p style="margin-bottom: 25px; color: var(--text-muted);">
                    Soru bankalarını yeniden yüklemek için aşağıdaki butona tıklayın. Bu işlem <strong>Sorular</strong> klasörünü tarayarak veritabanını güncelleyecektir.
                </p>

                <form method="POST" style="margin-bottom: 30px;">
                    <input type="hidden" name="action" value="load_questions">
                    <button type="submit" class="btn btn-large" style="width:auto; min-width:200px;">
                        <i class="fas fa-sync-alt"></i> Soruları Yeniden Yükle
                    </button>
                </form>

                <div style="background: rgba(15,23,42,0.4); padding: 20px; border-radius: 12px; margin-bottom: 20px; border:1px solid rgba(255,255,255,0.05);">
                    <h3 style="margin-bottom:15px; font-size:1.1rem; color:#fff;">📋 Yükleme Bilgileri</h3>
                    <div style="display:grid; gap:10px; color:var(--text-muted);">
                        <div>
                            <strong>Soru Klasörü:</strong> 
                            <span style="color:#fff;"><?php echo htmlspecialchars(defined('ROOT_DIR') ? ROOT_DIR : 'Sorular'); ?></span>
                        </div>
                        <div>
                            <strong>Klasör Durumu:</strong> 
                            <span style="color: <?php echo is_dir(defined('ROOT_DIR') ? ROOT_DIR : 'Sorular') ? '#4ade80' : '#ef4444'; ?>">
                                <?php echo is_dir(defined('ROOT_DIR') ? ROOT_DIR : 'Sorular') ? '✅ Mevcut' : '❌ Bulunamadı'; ?>
                            </span>
                        </div>
                        <div>
                            <strong>İzin Verilen Uzantılar:</strong> 
                            <span style="color:#fff;"><?php echo implode(', ', defined('ALLOWED_EXTENSIONS') ? ALLOWED_EXTENSIONS : ['json']); ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($errors)): ?>
                    <div class="alert-box">
                        <h4 style="margin-bottom:10px; color:#fca5a5;"><i class="fas fa-exclamation-triangle"></i> Sistem Hataları</h4>
                        <div class="error-list" style="max-height: 200px; overflow-y: auto;">
                            <?php foreach ($errors as $error): ?>
                                <div style="margin-bottom:8px; padding:8px; background:rgba(239,68,68,0.1); border-radius:6px; font-size:0.9rem;">
                                    <?php echo htmlspecialchars($error); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="glass-panel">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-layer-group"></i> Yüklenen Bankalar</div>
                </div>
                <div class="bank-list">
                    <?php if (!empty($banks)): ?>
                        <?php foreach ($banks as $bank): ?>
                            <div class="bank-item">
                                <div class="bank-info">
                                    <strong><?php echo htmlspecialchars($bank); ?></strong>
                                    <span><?php echo count($categories[$bank] ?? []); ?> kategori</span>
                                </div>
                                <div style="width:8px; height:8px; background:#4ade80; border-radius:50%; box-shadow:0 0 10px #4ade80;"></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align:center; padding:30px; color:var(--text-muted);">Henüz banka yok.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

