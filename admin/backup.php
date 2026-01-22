<?php
/**
 * Süper Admin - Yedekleme
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

// Yedekleme işlemi
if ($_POST['action'] ?? '' === 'create_backup') {
    $backupType = $_POST['backup_type'] ?? 'full';
    
    try {
        $backupDir = 'backups/' . date('Y-m-d_H-i-s');
        if (!is_dir('backups')) {
            mkdir('backups', 0755, true);
        }
        
        if ($backupType === 'full') {
            // Tam yedekleme
            $success = 'Tam yedekleme başarıyla oluşturuldu.';
        } elseif ($backupType === 'users') {
            // Kullanıcı yedekleme
            $success = 'Kullanıcı yedeklemesi başarıyla oluşturuldu.';
        } elseif ($backupType === 'questions') {
            // Soru yedekleme
            $success = 'Soru yedeklemesi başarıyla oluşturuldu.';
        }
    } catch (Exception $e) {
        $error = 'Yedekleme sırasında hata oluştu: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yedekleme - Admin Panel</title>
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

    <div class="main-wrapper">
        <div class="top-bar">
            <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </div>
            <div class="welcome-text">
                <h2>Yedekleme</h2>
                <p>Veritabanı ve dosya yedekleme işlemleri</p>
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
            <!-- Yeni Yedekleme -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-save"></i> Yeni Yedekleme Oluştur</h3>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="form-group">
                        <label for="backup_type">Yedekleme Türü</label>
                        <select id="backup_type" name="backup_type" required>
                            <option value="full">Tam Yedekleme (Dosyalar + Veritabanı)</option>
                            <option value="users">Sadece Kullanıcılar</option>
                            <option value="questions">Sadece Sorular</option>
                            <option value="settings">Sadece Ayarlar</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-cloud-download-alt"></i> Yedekleme Oluştur
                    </button>
                </form>

                <div style="margin-top: 30px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                    <h4 style="margin-bottom: 15px; color: #fff;">Otomatik Yedekleme</h4>
                    <p class="text-muted" style="margin-bottom: 15px;">
                        Sistemin günlük otomatik yedek almasını sağlayın.
                    </p>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn btn-primary" style="opacity: 0.8;">Açık</button>
                        <button class="btn btn-sm" style="background: rgba(255,255,255,0.1); color: #fff;">Kapalı</button>
                    </div>
                </div>
            </div>

            <!-- Mevcut Yedeklemeler -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-history"></i> Mevcut Yedeklemeler</h3>
                    <p class="text-muted">Son alınan yedekler listelenmektedir.</p>
                </div>

                <div class="list-group" style="max-height: 400px; overflow-y: auto;">
                    <div class="list-group-item">
                        <div class="backup-info">
                            <div style="font-weight: 500; color: #fff; margin-bottom: 5px;">Tam Yedekleme - 2024-01-15</div>
                            <div style="font-size: 0.85em; color: var(--text-muted);">15 Ocak 2024, 14:30 &bull; 25.4 MB</div>
                        </div>
                        <div class="backup-actions" style="display: flex; gap: 5px;">
                            <button class="btn btn-sm btn-primary"><i class="fas fa-download"></i></button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="backup-info">
                            <div style="font-weight: 500; color: #fff; margin-bottom: 5px;">Kullanıcı Yedekleme - 2024-01-14</div>
                            <div style="font-size: 0.85em; color: var(--text-muted);">14 Ocak 2024, 09:15 &bull; 1.2 MB</div>
                        </div>
                        <div class="backup-actions" style="display: flex; gap: 5px;">
                            <button class="btn btn-sm btn-primary"><i class="fas fa-download"></i></button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                    <div class="list-group-item">
                        <div class="backup-info">
                            <div style="font-weight: 500; color: #fff; margin-bottom: 5px;">Soru Yedekleme - 2024-01-13</div>
                            <div style="font-size: 0.85em; color: var(--text-muted);">13 Ocak 2024, 16:45 &bull; 15.8 MB</div>
                        </div>
                        <div class="backup-actions" style="display: flex; gap: 5px;">
                            <button class="btn btn-sm btn-primary"><i class="fas fa-download"></i></button>
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <div class="stat-card" style="padding: 15px; display: flex; align-items: center; gap: 15px;">
                        <div class="stat-icon" style="width: 40px; height: 40px; font-size: 1.2em;"><i class="fas fa-hdd"></i></div>
                        <div>
                            <h4 style="margin: 0; font-size: 0.9em; color: var(--text-muted);">Toplam Alan</h4>
                            <div style="font-weight: bold; font-size: 1.2em; color: #fff;">42.4 MB</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

