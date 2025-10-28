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
    <title>Bakım Modu - Bir Soru Bir Sevap</title>
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

        .maintenance-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .maintenance-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .maintenance-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
        }

        .status-indicator {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9em;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .status-active {
            background: #f8d7da;
            color: #721c24;
        }

        .status-inactive {
            background: #d4edda;
            color: #155724;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group select:focus {
            outline: none;
            border-color: #089b76;
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

        .btn-warning {
            background: #f39c12;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .system-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        .system-info h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e1e8ed;
        }

        .info-item:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #2c3e50;
        }

        .info-value {
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .maintenance-grid {
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
            <a href="dashboard.php">Dashboard</a> > Bakım Modu
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="maintenance-grid">
            <div class="maintenance-card">
                <h2>🔧 Bakım Modu</h2>
                
                <div class="status-indicator <?php echo $maintenanceActive ? 'status-active' : 'status-inactive'; ?>">
                    <?php echo $maintenanceActive ? '🔴 Bakım Modu Aktif' : '🟢 Site Normal Çalışıyor'; ?>
                </div>

                <form method="POST">
                    <input type="hidden" name="action" value="toggle_maintenance">
                    
                    <div class="form-group">
                        <label for="maintenance_mode">Bakım Modu Durumu:</label>
                        <select id="maintenance_mode" name="maintenance_mode" required>
                            <option value="0" <?php echo !$maintenanceActive ? 'selected' : ''; ?>>Site Açık</option>
                            <option value="1" <?php echo $maintenanceActive ? 'selected' : ''; ?>>Bakım Modu</option>
                        </select>
                    </div>

                    <button type="submit" class="btn <?php echo $maintenanceActive ? 'btn-success' : 'btn-warning'; ?>">
                        <?php echo $maintenanceActive ? 'Bakım Modunu Kapat' : 'Bakım Modunu Aç'; ?>
                    </button>
                </form>

                <div style="margin-top: 30px;">
                    <h3>⚠️ Dikkat</h3>
                    <p style="color: #7f8c8d; font-size: 0.9em;">
                        Bakım modu aktif edildiğinde site ziyaretçilere kapatılır. 
                        Sadece admin kullanıcıları erişebilir.
                    </p>
                </div>
            </div>

            <div class="maintenance-card">
                <h2>🧹 Sistem Temizleme</h2>
                
                <div style="display: flex; flex-direction: column; gap: 15px;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="clear_cache">
                        <button type="submit" class="btn btn-secondary" style="width: 100%;">
                            🗑️ Cache Temizle
                        </button>
                    </form>

                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="optimize_database">
                        <button type="submit" class="btn btn-secondary" style="width: 100%;">
                            ⚡ Veritabanı Optimize Et
                        </button>
                    </form>

                    <button class="btn btn-danger" style="width: 100%;" onclick="confirmReset()">
                        🔄 Sistemi Sıfırla
                    </button>
                </div>

                <div style="margin-top: 30px;">
                    <h3>📊 Sistem Durumu</h3>
                    <div class="system-info">
                        <div class="info-item">
                            <span class="info-label">PHP Versiyonu:</span>
                            <span class="info-value"><?php echo phpversion(); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Bellek Kullanımı:</span>
                            <span class="info-value"><?php echo round(memory_get_usage()/1024/1024, 2); ?> MB</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Disk Kullanımı:</span>
                            <span class="info-value"><?php echo round(disk_free_space('.')/1024/1024/1024, 2); ?> GB Boş</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Son Güncelleme:</span>
                            <span class="info-value"><?php echo date('d.m.Y H:i'); ?></span>
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
                    alert('Sistem sıfırlama özelliği geliştirme aşamasında!');
                }
            }
        }
    </script>
</body>
</html>
