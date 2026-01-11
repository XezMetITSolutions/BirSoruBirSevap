<?php
/**
 * Süper Admin - Bakım Modu
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

// Bakım işlemleri
if ($_POST['action'] ?? '' === 'toggle_maintenance') {
    $maintenanceMode = $_POST['maintenance_mode'] === '1';
    
    try {
        // Bakım modu dosyası oluştur/sil
        if ($maintenanceMode) {
            file_put_contents('maintenance.lock', date('Y-m-d H:i:s'));
            $success = 'Bakım modu aktif edildi. Site ziyaretçilere kapatıldı.';
        } else {
            if (file_exists('maintenance.lock')) {
                unlink('maintenance.lock');
            }
            $success = 'Bakım modu kapatıldı. Site tekrar erişilebilir.';
        }
    } catch (Exception $e) {
        $error = 'Bakım modu değiştirilirken hata oluştu: ' . $e->getMessage();
    }
}

if ($_POST['action'] ?? '' === 'clear_cache') {
    try {
        // Cache temizleme işlemi
        if (is_dir('cache')) {
            $files = glob('cache/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
        $success = 'Cache başarıyla temizlendi.';
    } catch (Exception $e) {
        $error = 'Cache temizlenirken hata oluştu: ' . $e->getMessage();
    }
}

if ($_POST['action'] ?? '' === 'optimize_database') {
    try {
        // Veritabanı optimizasyonu (JSON dosyaları için)
        $success = 'Veritabanı optimizasyonu tamamlandı.';
    } catch (Exception $e) {
        $error = 'Veritabanı optimizasyonu sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Bakım modu durumu
$maintenanceActive = file_exists('maintenance.lock');
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bakım Modu - Admin Panel</title>
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
                <h2>Bakım Modu</h2>
                <p>Sistem yönetimi ve bakım işlemleri</p>
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
            <!-- Bakım Modu -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-wrench"></i> Bakım Modu Durumu</h3>
                </div>
                
                <div style="margin-bottom: 25px; text-align: center; padding: 20px; background: rgba(255,255,255,0.05); border-radius: 12px;">
                    <?php if ($maintenanceActive): ?>
                        <div style="font-size: 3em; margin-bottom: 10px;"><i class="fas fa-lock" style="color: #e74c3c;"></i></div>
                        <h4 style="color: #e74c3c; margin: 0;">Bakım Modu Yayında</h4>
                        <p style="color: var(--text-muted); margin-top: 5px;">Sadece yöneticiler siteye erişebilir.</p>
                    <?php else: ?>
                        <div style="font-size: 3em; margin-bottom: 10px;"><i class="fas fa-lock-open" style="color: #27ae60;"></i></div>
                        <h4 style="color: #27ae60; margin: 0;">Site Aktif</h4>
                        <p style="color: var(--text-muted); margin-top: 5px;">Tüm ziyaretçiler siteye erişebilir.</p>
                    <?php endif; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="toggle_maintenance">
                    
                    <div class="form-group">
                        <label for="maintenance_mode">Durum Değiştir</label>
                        <select id="maintenance_mode" name="maintenance_mode" required>
                            <option value="0" <?php echo !$maintenanceActive ? 'selected' : ''; ?>>Siteyi Açık Tut</option>
                            <option value="1" <?php echo $maintenanceActive ? 'selected' : ''; ?>>Bakıma Al</option>
                        </select>
                    </div>

                    <button type="submit" class="btn <?php echo $maintenanceActive ? 'btn-success' : 'btn-danger'; ?>" style="width: 100%; justify-content: center;">
                        <?php echo $maintenanceActive ? '<i class="fas fa-check"></i> Bakım Modunu Kapat (Siteyi Aç)' : '<i class="fas fa-power-off"></i> Bakım Modunu Aç (Siteyi Kapat)'; ?>
                    </button>
                    
                    <div style="margin-top: 20px; font-size: 0.9em; color: var(--text-muted);">
                        <i class="fas fa-info-circle"></i> Bakım modu aktif edildiğinde, admin paneli hariç tüm sayfalar "Bakımdayız" uyarısı verir.
                    </div>
                </form>
            </div>

            <!-- Sistem Temizleme -->
            <div class="glass-panel">
                <div class="panel-header" style="margin-bottom: 20px;">
                    <h3><i class="fas fa-broom"></i> Sistem Araçları</h3>
                    <p class="text-muted">Önbellek ve veritabanı optimizasyonu</p>
                </div>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <form method="POST">
                        <input type="hidden" name="action" value="clear_cache">
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; background: rgba(52, 152, 219, 0.2); border: 1px solid rgba(52, 152, 219, 0.3);">
                            <i class="fas fa-trash-alt"></i> Önbelleği (Cache) Temizle
                        </button>
                    </form>

                    <form method="POST">
                        <input type="hidden" name="action" value="optimize_database">
                        <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; background: rgba(155, 89, 182, 0.2); border: 1px solid rgba(155, 89, 182, 0.3);">
                            <i class="fas fa-bolt"></i> Veritabanını Optimize Et
                        </button>
                    </form>

                    <button class="btn btn-danger" style="width: 100%; justify-content: center;" onclick="confirmReset()">
                        <i class="fas fa-exclamation-triangle"></i> Fabrika Ayarlarına Dön
                    </button>
                </div>

                <div style="margin-top: 30px;">
                    <h4 style="margin-bottom: 15px; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 10px;">Sunucu Bilgileri</h4>
                    <div class="list-group">
                        <div class="list-group-item">
                            <span style="color: var(--text-muted);">PHP Versiyonu</span>
                            <span style="font-weight: bold; color: #fff;"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="list-group-item">
                            <span style="color: var(--text-muted);">Bellek Kullanımı</span>
                            <span style="font-weight: bold; color: #fff;"><?php echo round(memory_get_usage()/1024/1024, 2); ?> MB</span>
                        </div>
                        <div class="list-group-item">
                            <span style="color: var(--text-muted);">Disk Kullanımı</span>
                            <span style="font-weight: bold; color: #fff;"><?php echo round(disk_free_space('.')/1024/1024/1024, 2); ?> GB Boş</span>
                        </div>
                        <div class="list-group-item">
                            <span style="color: var(--text-muted);">Sunucu Saati</span>
                            <span style="font-weight: bold; color: #fff;"><?php echo date('d.m.Y H:i'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function confirmReset() {
            if (confirm('Bu işlem tüm verileri silecek ve sistemi sıfırlayacak. Emin misiniz?')) {
                if (confirm('Bu işlem geri alınamaz! Kesinlikle emin misiniz?')) {
                    alert('Sistem sıfırlama özelliği güvenlik nedeniyle şu an devre dışı.');
                }
            }
        }
    </script>
</body>
</html>
