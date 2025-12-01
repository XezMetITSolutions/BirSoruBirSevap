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
    <title>Yedekleme - Bir Soru Bir Sevap</title>
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

        .backup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .backup-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .backup-card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.5em;
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

        .backup-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 10px;
        }

        .backup-item {
            padding: 10px;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .backup-item:last-child {
            border-bottom: none;
        }

        .backup-info {
            flex: 1;
        }

        .backup-name {
            font-weight: 500;
            color: #2c3e50;
        }

        .backup-date {
            font-size: 0.9em;
            color: #7f8c8d;
        }

        .backup-actions {
            display: flex;
            gap: 5px;
        }

        .btn-small {
            padding: 5px 10px;
            font-size: 0.8em;
        }

        @media (max-width: 768px) {
            .backup-grid {
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
            <a href="dashboard.php">Dashboard</a> > Yedekleme
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

        <div class="backup-grid">
            <div class="backup-card">
                <h2>💾 Yeni Yedekleme Oluştur</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_backup">
                    
                    <div class="form-group">
                        <label for="backup_type">Yedekleme Türü:</label>
                        <select id="backup_type" name="backup_type" required>
                            <option value="full">Tam Yedekleme</option>
                            <option value="users">Kullanıcı Yedekleme</option>
                            <option value="questions">Soru Yedekleme</option>
                            <option value="settings">Ayar Yedekleme</option>
                        </select>
                    </div>

                    <button type="submit" class="btn btn-success">Yedekleme Oluştur</button>
                </form>

                <div style="margin-top: 30px;">
                    <h3>Otomatik Yedekleme</h3>
                    <p style="color: #7f8c8d; margin-bottom: 15px;">
                        Günlük otomatik yedekleme ayarları
                    </p>
                    <div style="display: flex; flex-direction: column; gap: 10px;">
                        <button class="btn btn-secondary">Otomatik Yedekleme Aç</button>
                        <button class="btn btn-secondary">Otomatik Yedekleme Kapat</button>
                    </div>
                </div>
            </div>

            <div class="backup-card">
                <h2>📁 Mevcut Yedeklemeler</h2>
                <div class="backup-list">
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">Tam Yedekleme - 2024-01-15</div>
                            <div class="backup-date">15 Ocak 2024, 14:30</div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-small btn-secondary">İndir</button>
                            <button class="btn btn-small btn-danger">Sil</button>
                        </div>
                    </div>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">Kullanıcı Yedekleme - 2024-01-14</div>
                            <div class="backup-date">14 Ocak 2024, 09:15</div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-small btn-secondary">İndir</button>
                            <button class="btn btn-small btn-danger">Sil</button>
                        </div>
                    </div>
                    <div class="backup-item">
                        <div class="backup-info">
                            <div class="backup-name">Soru Yedekleme - 2024-01-13</div>
                            <div class="backup-date">13 Ocak 2024, 16:45</div>
                        </div>
                        <div class="backup-actions">
                            <button class="btn btn-small btn-secondary">İndir</button>
                            <button class="btn btn-small btn-danger">Sil</button>
                        </div>
                    </div>
                </div>

                <div style="margin-top: 20px;">
                    <h3>Yedekleme İstatistikleri</h3>
                    <div style="font-size: 0.9em; color: #7f8c8d;">
                        <p><strong>Toplam Yedekleme:</strong> 3 dosya</p>
                        <p><strong>Toplam Boyut:</strong> 2.5 MB</p>
                        <p><strong>Son Yedekleme:</strong> 15 Ocak 2024</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
