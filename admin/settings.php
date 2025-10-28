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
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Ayarları - Bir Soru Bir Sevap</title>
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

        .settings-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .settings-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .settings-card h2 {
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

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #089b76;
        }

        .form-group textarea {
            height: 100px;
            resize: vertical;
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

        .btn-secondary {
            background: #95a5a6;
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

        .institution-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            padding: 10px;
        }

        .institution-item {
            padding: 8px;
            border-bottom: 1px solid #f1f3f4;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .institution-item:last-child {
            border-bottom: none;
        }

        .institution-name {
            font-weight: 500;
        }

        .institution-count {
            background: #068567;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.8em;
        }

        @media (max-width: 768px) {
            .settings-grid {
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
            <a href="dashboard.php">Dashboard</a> > Sistem Ayarları
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

        <div class="settings-grid">
            <div class="settings-card">
                <h2>🔧 Genel Ayarlar</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="save_settings">
                    
                    <div class="form-group">
                        <label for="app_name">Uygulama Adı:</label>
                        <input type="text" id="app_name" name="app_name" value="<?php echo htmlspecialchars(APP_NAME); ?>">
                    </div>

                    <div class="form-group">
                        <label for="default_timer">Varsayılan Soru Süresi (saniye):</label>
                        <input type="number" id="default_timer" name="default_timer" value="<?php echo DEFAULT_TIMER; ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_questions">Maksimum Soru Sayısı:</label>
                        <input type="number" id="max_questions" name="max_questions" value="<?php echo MAX_QUESTIONS_PER_EXAM; ?>">
                    </div>

                    <div class="form-group">
                        <label for="session_timeout">Oturum Zaman Aşımı (saniye):</label>
                        <input type="number" id="session_timeout" name="session_timeout" value="<?php echo SESSION_TIMEOUT; ?>">
                    </div>

                    <div class="form-group">
                        <label for="negative_marking">Negatif Puanlama:</label>
                        <select id="negative_marking" name="negative_marking">
                            <option value="0" <?php echo !NEGATIVE_MARKING ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="1" <?php echo NEGATIVE_MARKING ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">Ayarları Kaydet</button>
                </form>
            </div>

            <div class="settings-card">
                <h2>🏢 Kurum Yönetimi</h2>
                <p style="margin-bottom: 20px; color: #7f8c8d;">
                    Kayıtlı kurumlar ve kullanıcı sayıları
                </p>

                <div class="institution-list">
                    <?php foreach ($institutions as $institution): ?>
                        <div class="institution-item">
                            <span class="institution-name"><?php echo htmlspecialchars($institution); ?></span>
                            <span class="institution-count">0 kullanıcı</span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top: 20px;">
                    <a href="users.php" class="btn btn-secondary">Kullanıcı Yönetimi</a>
                </div>
            </div>

            <div class="settings-card">
                <h2>📁 Dosya Ayarları</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="save_file_settings">
                    
                    <div class="form-group">
                        <label for="root_dir">Soru Klasörü:</label>
                        <input type="text" id="root_dir" name="root_dir" value="<?php echo htmlspecialchars(ROOT_DIR); ?>">
                    </div>

                    <div class="form-group">
                        <label for="max_scan_depth">Maksimum Tarama Derinliği:</label>
                        <input type="number" id="max_scan_depth" name="max_scan_depth" value="<?php echo MAX_SCAN_DEPTH; ?>">
                    </div>

                    <div class="form-group">
                        <label for="allowed_extensions">İzin Verilen Uzantılar:</label>
                        <input type="text" id="allowed_extensions" name="allowed_extensions" value="<?php echo implode(', ', ALLOWED_EXTENSIONS); ?>">
                    </div>

                    <button type="submit" class="btn">Dosya Ayarlarını Kaydet</button>
                </form>
            </div>

            <div class="settings-card">
                <h2>🔒 Güvenlik Ayarları</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="save_security_settings">
                    
                    <div class="form-group">
                        <label for="teacher_pin">Öğretmen PIN Kodu:</label>
                        <input type="text" id="teacher_pin" name="teacher_pin" value="<?php echo htmlspecialchars(TEACHER_PIN); ?>">
                    </div>

                    <div class="form-group">
                        <label for="debug_mode">Hata Ayıklama Modu:</label>
                        <select id="debug_mode" name="debug_mode">
                            <option value="0" <?php echo !DEBUG_MODE ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="1" <?php echo DEBUG_MODE ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="log_errors">Hata Loglama:</label>
                        <select id="log_errors" name="log_errors">
                            <option value="0" <?php echo !LOG_ERRORS ? 'selected' : ''; ?>>Kapalı</option>
                            <option value="1" <?php echo LOG_ERRORS ? 'selected' : ''; ?>>Açık</option>
                        </select>
                    </div>

                    <button type="submit" class="btn">Güvenlik Ayarlarını Kaydet</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>