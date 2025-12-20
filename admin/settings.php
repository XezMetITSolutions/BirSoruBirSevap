<?php
/**
 * Süper Admin - Sistem Ayarları
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Admin kontrolü
if (!$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$success = '';
$error = '';

// Ayarları kaydet
if ($_POST['action'] ?? '' === 'save_settings') {
    // Burada ayarları kaydetme işlemi yapılabilir
    $success = 'Ayarlar başarıyla kaydedildi.';
}

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

// Kullanıcı sayılarını hesapla
$userCounts = array_fill_keys($institutions, 0);
try {
    $allUsers = $auth->getAllUsers();
    foreach ($allUsers as $u) {
        $inst = $u['institution'] ?? $u['branch'] ?? '';
        if (isset($userCounts[$inst])) {
            $userCounts[$inst]++;
        } else {
            // Tam eşleşme yoksa, trim veya case-insensitive dene
            foreach ($institutions as $key) {
                if (strcasecmp(trim($inst), trim($key)) === 0) {
                    $userCounts[$key]++;
                    break;
                }
            }
        }
    }
} catch (Exception $e) {
    // Hata durumunda (sessizce devam et, count 0 kalır)
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - Admin Panel</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/admin-style.css">
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <div class="top-bar">
            <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </div>
            <div class="welcome-text">
                <h2>Sistem Ayarları</h2>
                <p>Genel sistem yapılandırması ve tercihler</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success animate-fade-in">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error animate-fade-in">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="content-grid animate-slide-up">
            <!-- Genel Ayarlar -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-wrench"></i> Genel Ayarlar</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="form-group">
                        <label for="app_name">Uygulama Adı</label>
                        <input type="text" id="app_name" name="app_name" value="<?php echo htmlspecialchars(APP_NAME); ?>">
                    </div>

                    <div class="form-group">
                        <label for="default_timer">Varsayılan Soru Süresi (saniye)</label>
                        <input type="number" id="default_timer" name="default_timer" value="<?php echo DEFAULT_TIMER; ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_questions">Maksimum Soru Sayısı</label>
                        <input type="number" id="max_questions" name="max_questions" value="<?php echo MAX_QUESTIONS_PER_EXAM; ?>">
                    </div>

                    <div class="form-group">
                        <label for="session_timeout">Oturum Zaman Aşımı (saniye)</label>
                        <input type="number" id="session_timeout" name="session_timeout" value="<?php echo SESSION_TIMEOUT; ?>">
                    </div>

                    <div class="form-group">
                        <label for="negative_marking">Negatif Puanlama</label>
                        <select id="negative_marking" name="negative_marking">
                            <option value="0" <?php echo !NEGATIVE_MARKING ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="1" <?php echo NEGATIVE_MARKING ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Ayarları Kaydet
                    </button>
                </form>
            </div>

            <!-- Kurum Yönetimi -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-building"></i> Kurum Yönetimi</h3>
                    <p class="text-muted">Kayıtlı kurumlar ve kullanıcı sayıları</p>
                </div>

                <div class="list-group" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($institutions as $institution): ?>
                        <div class="list-group-item">
                            <span style="font-weight: 500; color: #fff;"><?php echo htmlspecialchars($institution); ?></span>
                            <span class="badge badge-info"><?php echo $userCounts[$institution] ?? 0; ?> kullanıcı</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px;">
                    <a href="users.php" class="btn btn-primary" style="background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.1); box-shadow:none;">
                        <i class="fas fa-users"></i> Kullanıcı Yönetimi
                    </a>
                </div>
            </div>

            <!-- Dosya Ayarları -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-folder-open"></i> Dosya Ayarları</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="save_file_settings">
                    
                    <div class="form-group">
                        <label for="root_dir">Soru Klasörü</label>
                        <input type="text" id="root_dir" name="root_dir" value="<?php echo htmlspecialchars(ROOT_DIR); ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_scan_depth">Maksimum Tarama Derinliği</label>
                        <input type="number" id="max_scan_depth" name="max_scan_depth" value="<?php echo MAX_SCAN_DEPTH; ?>">
                    </div>

                    <div class="form-group">
                        <label for="allowed_extensions">İzin Verilen Uzantılar</label>
                        <input type="text" id="allowed_extensions" name="allowed_extensions" value="<?php echo implode(', ', ALLOWED_EXTENSIONS); ?>">
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Dosya Ayarlarını Kaydet
                    </button>
                </form>
            </div>

            <!-- Güvenlik Ayarları -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-shield-alt"></i> Güvenlik Ayarları</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="save_security_settings">
                    
                    <div class="form-group">
                        <label for="teacher_pin">Öğretmen PIN Kodu</label>
                        <input type="text" id="teacher_pin" name="teacher_pin" value="<?php echo htmlspecialchars(TEACHER_PIN); ?>">
                    </div>

                    <div class="form-group">
                        <label for="debug_mode">Hata Ayıklama Modu</label>
                        <select id="debug_mode" name="debug_mode">
                            <option value="0" <?php echo !DEBUG_MODE ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="1" <?php echo DEBUG_MODE ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="log_errors">Hata Loglama</label>
                        <select id="log_errors" name="log_errors">
                            <option value="0" <?php echo !LOG_ERRORS ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="1" <?php echo LOG_ERRORS ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Güvenlik Ayarlarını Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>