<?php
/**
 * Öğretmen Profil Yönetimi
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$successMessage = '';
$errorMessage = '';

// Profil güncelleme işlemi
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        
        if (empty($name)) {
            $errorMessage = 'Ad soyad alanı zorunludur.';
        } else {
            // Kullanıcı verilerini güncelle
            $users = $auth->getAllUsers();
            if (isset($users[$user['username']])) {
                // Mevcut verileri koru, sadece güncellenen alanları değiştir
                $users[$user['username']]['name'] = $name;
                if (!empty($email)) {
                    $users[$user['username']]['email'] = $email;
                }
                if (!empty($phone)) {
                    $users[$user['username']]['phone'] = $phone;
                }
                if (!empty($department)) {
                    $users[$user['username']]['department'] = $department;
                }
                if (!empty($bio)) {
                    $users[$user['username']]['bio'] = $bio;
                }
                $users[$user['username']]['updated_at'] = date('Y-m-d H:i:s');
                
                // Dosyaya kaydet
                if (file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    // Session'ı güncelle
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    $_SESSION['user']['phone'] = $phone;
                    $_SESSION['user']['department'] = $department;
                    $_SESSION['user']['bio'] = $bio;
                    $user = $_SESSION['user'];
                    $successMessage = 'Profil bilgileriniz başarıyla güncellendi.';
                } else {
                    $errorMessage = 'Profil güncellenirken hata oluştu.';
                }
            } else {
                $errorMessage = 'Kullanıcı bulunamadı.';
            }
        }
    }
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $errorMessage = 'Tüm şifre alanları zorunludur.';
        } elseif ($newPassword !== $confirmPassword) {
            $errorMessage = 'Yeni şifreler eşleşmiyor.';
        } elseif (strlen($newPassword) < 6) {
            $errorMessage = 'Yeni şifre en az 6 karakter olmalıdır.';
        } else {
            // Mevcut şifreyi kontrol et
            $users = $auth->getAllUsers();
            if (isset($users[$user['username']]) && password_verify($currentPassword, $users[$user['username']]['password'])) {
                $users[$user['username']]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $users[$user['username']]['password_changed_at'] = date('Y-m-d H:i:s');
                
                if (file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                    $successMessage = 'Şifreniz başarıyla değiştirildi.';
                } else {
                    $errorMessage = 'Şifre değiştirilirken hata oluştu.';
                }
            } else {
                $errorMessage = 'Mevcut şifre yanlış.';
            }
        }
    }
    
    if ($action === 'update_preferences') {
        $language = $_POST['language'] ?? 'tr';
        $timezone = $_POST['timezone'] ?? 'Europe/Vienna';
        $emailNotifications = isset($_POST['email_notifications']);
        $examNotifications = isset($_POST['exam_notifications']);
        $systemNotifications = isset($_POST['system_notifications']);
        
        // Kullanıcı tercihlerini güncelle
        $users = $auth->getAllUsers();
        if (isset($users[$user['username']])) {
            $users[$user['username']]['language'] = $language;
            $users[$user['username']]['timezone'] = $timezone;
            $users[$user['username']]['email_notifications'] = $emailNotifications;
            $users[$user['username']]['exam_notifications'] = $examNotifications;
            $users[$user['username']]['system_notifications'] = $systemNotifications;
            $users[$user['username']]['preferences_updated_at'] = date('Y-m-d H:i:s');
            
            if (file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                // Session'ı güncelle
                $_SESSION['user']['language'] = $language;
                $_SESSION['user']['timezone'] = $timezone;
                $_SESSION['user']['email_notifications'] = $emailNotifications;
                $_SESSION['user']['exam_notifications'] = $examNotifications;
                $_SESSION['user']['system_notifications'] = $systemNotifications;
                $user = $_SESSION['user'];
                $successMessage = 'Tercihleriniz başarıyla kaydedildi.';
            } else {
                $errorMessage = 'Tercihler kaydedilirken hata oluştu.';
            }
        } else {
            $errorMessage = 'Kullanıcı bulunamadı.';
        }
    }
    
    if ($action === 'upload_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $fileExtension = strtolower(pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array($fileExtension, $allowedExtensions)) {
                $fileName = $user['username'] . '_' . time() . '.' . $fileExtension;
                $filePath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $filePath)) {
                    // Eski fotoğrafı sil
                    $users = $auth->getAllUsers();
                    if (isset($users[$user['username']]['profile_photo']) && file_exists('../' . $users[$user['username']]['profile_photo'])) {
                        unlink('../' . $users[$user['username']]['profile_photo']);
                    }
                    
                    // Yeni fotoğraf yolunu kaydet
                    $users[$user['username']]['profile_photo'] = 'uploads/profiles/' . $fileName;
                    $users[$user['username']]['photo_updated_at'] = date('Y-m-d H:i:s');
                    
                    if (file_put_contents('../data/users.json', json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                        $_SESSION['user']['profile_photo'] = 'uploads/profiles/' . $fileName;
                        $user = $_SESSION['user'];
                        $successMessage = 'Profil fotoğrafınız başarıyla güncellendi.';
                    } else {
                        $errorMessage = 'Fotoğraf kaydedilirken hata oluştu.';
                    }
                } else {
                    $errorMessage = 'Fotoğraf yüklenirken hata oluştu.';
                }
            } else {
                $errorMessage = 'Sadece JPG, PNG ve GIF dosyaları yüklenebilir.';
            }
        } else {
            $errorMessage = 'Fotoğraf seçilmedi veya yüklenirken hata oluştu.';
        }
    }
}

// Güncel kullanıcı bilgilerini al
$users = $auth->getAllUsers();
$userData = $users[$user['username']] ?? [];
$user = array_merge($user, $userData);

// Debug için
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    error_log("Profile Debug - User: " . json_encode($user));
    error_log("Profile Debug - Users: " . json_encode($users));
}

// Profil fotoğrafı
$profilePhoto = $user['profile_photo'] ?? null;
if ($profilePhoto && file_exists('../' . $profilePhoto)) {
    $profilePhotoUrl = '../' . $profilePhoto;
} else {
    $profilePhotoUrl = '../logo.png'; // Varsayılan fotoğraf
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Yönetimi - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
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
            color: #2c3e50;
        }
        
        .logo p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .lang-toggle {
            background: rgba(6, 132, 102, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .lang-toggle:hover {
            background: #068466;
            color: white;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Uzun kullanıcı adı kırpma */
        .user-info > div {
            max-width: 45vw;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .back-btn {
            background: rgba(6, 132, 102, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .back-btn:hover {
            background: #068466;
            color: white;
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 40px;
        }
        
        .page-title {
            font-size: 3em;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            font-weight: 300;
        }
        
        .page-subtitle {
            font-size: 1.2em;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
        }
        
        .profile-container {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
        }
        
        .profile-sidebar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            height: fit-content;
        }
        
        .profile-main {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }
        
        .profile-photo-section {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .profile-photo {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid #068466;
            margin-bottom: 20px;
            box-shadow: 0 10px 30px rgba(6, 132, 102, 0.3);
        }
        
        .photo-upload-btn {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .photo-upload-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(6, 132, 102, 0.4);
        }
        
        .profile-info {
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
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
        
        .section-title {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 2px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1em;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #068466;
            box-shadow: 0 0 0 3px rgba(6, 132, 102, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .btn {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 25px;
            font-size: 1em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(6, 132, 102, 0.3);
        }
        
        .btn-secondary {
            background: #6c757d;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
        }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; font-size: 1em; opacity: .7; }
        .toggle-password:hover { opacity: 1; }
        
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            border: 2px solid #c3e6cb;
            border-radius: 15px;
            padding: 20px;
            color: #155724;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            border: 2px solid #f5c6cb;
            border-radius: 15px;
            padding: 20px;
            color: #721c24;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }
        
        .tab-container {
            margin-bottom: 30px;
        }
        
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
        }
        
        .tab-btn {
            background: #f8f9fa;
            border: 2px solid #e1e8ed;
            color: #6c757d;
            padding: 12px 25px;
            border-radius: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .tab-btn.active {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            border-color: #068466;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            border-left: 4px solid #068466;
        }
        
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #068466;
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; flex-wrap: wrap; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .lang-toggle, .back-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }

            .profile-container { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .tab-buttons { flex-direction: column; }
        }

        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .lang-toggle, .back-btn { padding: 5px 8px; font-size: .85rem; }
            .user-info { gap: 8px; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p>Profil Yönetimi</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="lang-toggle">DE</button>
                <a href="dashboard.php" class="back-btn" id="backBtn">← Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="pageTitle">👤 Profil Yönetimi</h1>
            <p class="page-subtitle" id="pageSubtitle">Kişisel bilgilerinizi yönetin ve hesabınızı güncelleyin</p>
        </div>

        <?php if ($successMessage): ?>
            <div class="success-message">
                ✅ <?php echo htmlspecialchars($successMessage); ?>
            </div>
        <?php endif; ?>

        <?php if ($errorMessage): ?>
            <div class="error-message">
                ❌ <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Debug bilgileri -->
        <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
        <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; font-family: monospace; font-size: 0.9em;">
            <h4>🔍 Debug Bilgileri:</h4>
            <p><strong>Kullanıcı Adı:</strong> <?php echo $user['username']; ?></p>
            <p><strong>Session Verisi:</strong></p>
            <pre><?php echo htmlspecialchars(json_encode($_SESSION['user'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
            <p><strong>Dosya Yazma İzni:</strong> <?php echo is_writable('../data/users.json') ? '✅ Var' : '❌ Yok'; ?></p>
            <p><strong>Users.json İçeriği:</strong></p>
            <pre><?php echo htmlspecialchars(json_encode($users, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
        </div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Sol Taraf: Profil Bilgileri -->
            <div class="profile-sidebar">
                <div class="profile-photo-section">
                    <img src="<?php echo $profilePhotoUrl; ?>" alt="Profil Fotoğrafı" class="profile-photo">
                    <br>
                    <form method="POST" enctype="multipart/form-data" style="display: inline;">
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="file" name="profile_photo" accept="image/*" id="photo-upload" style="display: none;">
                        <label for="photo-upload" class="photo-upload-btn">📷 Fotoğraf Değiştir</label>
                    </form>
                </div>

                <div class="profile-info">
                    <div class="info-item">
                        <span class="info-label" id="labelName">👤 Ad Soyad</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['name']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" id="labelEmail">📧 E-posta</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'Belirtilmemiş'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" id="labelPhone">📱 Telefon</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'Belirtilmemiş'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" id="labelDepartment">🏫 Bölüm</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['department'] ?? 'Belirtilmemiş'); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" id="labelRole">👨‍🏫 Rol</span>
                        <span class="info-value"><?php echo htmlspecialchars($user['role']); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label" id="labelMembership">📅 Üyelik</span>
                        <span class="info-value"><?php echo date('d.m.Y', strtotime($user['created_at'] ?? 'now')); ?></span>
                    </div>
                </div>

                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">0</div>
                        <div class="stat-label" id="statLabel1">Oluşturulan Sınav</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">0</div>
                        <div class="stat-label" id="statLabel2">Aktif Öğrenci</div>
                    </div>
                </div>
            </div>

            <!-- Sağ Taraf: Profil Düzenleme -->
            <div class="profile-main">
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-btn active" onclick="showTab('personal')" id="tabPersonal">👤 Kişisel Bilgiler</button>
                        <button class="tab-btn" onclick="showTab('security')" id="tabSecurity">🔒 Güvenlik</button>
                        <button class="tab-btn" onclick="showTab('preferences')" id="tabPreferences">⚙️ Tercihler</button>
                    </div>

                    <!-- Kişisel Bilgiler Tab -->
                    <div id="personal" class="tab-content active">
                        <h2 class="section-title" id="sectionPersonal">👤 Kişisel Bilgiler</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="name" id="labelNameForm">Ad Soyad *</label>
                                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label for="email" id="labelEmailForm">E-posta</label>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="phone" id="labelPhoneForm">Telefon</label>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <div class="form-group">
                                    <label for="department" id="labelDepartmentForm">Bölüm</label>
                                    <input type="text" id="department" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="form-group">
                                <label for="bio" id="labelBio">Hakkımda</label>
                                <textarea id="bio" name="bio" placeholder="Kendiniz hakkında kısa bir açıklama yazın..." id="bioPlaceholder"><?php echo htmlspecialchars($user['bio'] ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn" id="btnUpdateInfo">💾 Bilgileri Güncelle</button>
                        </form>
                    </div>

                    <!-- Güvenlik Tab -->
                    <div id="security" class="tab-content">
                        <h2 class="section-title" id="sectionSecurity">🔒 Güvenlik</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="form-group">
                                <label for="current_password" id="labelCurrentPassword">Mevcut Şifre *</label>
                                <div class="password-wrapper">
                                    <input type="password" id="current_password" name="current_password" required>
                                    <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label for="new_password" id="labelNewPassword">Yeni Şifre *</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="new_password" name="new_password" required minlength="6">
                                        <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label for="confirm_password" id="labelConfirmPassword">Yeni Şifre Tekrar *</label>
                                    <div class="password-wrapper">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                                        <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn" id="btnChangePassword">🔑 Şifreyi Değiştir</button>
                        </form>
                    </div>

                    <!-- Tercihler Tab -->
                    <div id="preferences" class="tab-content">
                        <h2 class="section-title" id="sectionPreferences">⚙️ Tercihler</h2>
                        <form method="POST">
                            <input type="hidden" name="action" value="update_preferences">
                            
                            <div class="form-group">
                                <label for="language" id="labelLanguage">Dil Tercihi</label>
                                <select id="language" name="language">
                                    <option value="tr" <?php echo ($user['language'] ?? 'tr') === 'tr' ? 'selected' : ''; ?>>🇹🇷 Türkçe</option>
                                    <option value="de" <?php echo ($user['language'] ?? '') === 'de' ? 'selected' : ''; ?>>🇩🇪 Deutsch</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="timezone" id="labelTimezone">Saat Dilimi</label>
                                <select id="timezone" name="timezone">
                                    <option value="Europe/Vienna" <?php echo ($user['timezone'] ?? 'Europe/Vienna') === 'Europe/Vienna' ? 'selected' : ''; ?>>🇦🇹 Viyana (UTC+1/+2)</option>
                                    <option value="Europe/Istanbul" <?php echo ($user['timezone'] ?? '') === 'Europe/Istanbul' ? 'selected' : ''; ?>>🇹🇷 İstanbul (UTC+3)</option>
                                    <option value="Europe/London" <?php echo ($user['timezone'] ?? '') === 'Europe/London' ? 'selected' : ''; ?>>🇬🇧 Londra (UTC+0)</option>
                                    <option value="America/New_York" <?php echo ($user['timezone'] ?? '') === 'America/New_York' ? 'selected' : ''; ?>>🇺🇸 New York (UTC-5)</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="notifications" id="labelNotifications">Bildirim Tercihleri</label>
                                <div style="margin-top: 10px;">
                                    <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <input type="checkbox" name="email_notifications" <?php echo ($user['email_notifications'] ?? true) ? 'checked' : ''; ?>>
                                        <span id="labelEmailNotif">📧 E-posta bildirimleri</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px; margin-bottom: 10px;">
                                        <input type="checkbox" name="exam_notifications" <?php echo ($user['exam_notifications'] ?? true) ? 'checked' : ''; ?>>
                                        <span id="labelExamNotif">📝 Sınav bildirimleri</span>
                                    </label>
                                    <label style="display: flex; align-items: center; gap: 10px;">
                                        <input type="checkbox" name="system_notifications" <?php echo ($user['system_notifications'] ?? true) ? 'checked' : ''; ?>>
                                        <span id="labelSystemNotif">⚙️ Sistem bildirimleri</span>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn" id="btnSavePreferences">⚙️ Tercihleri Kaydet</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab değiştirme
        function showTab(tabName) {
            // Tüm tab içeriklerini gizle
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Tüm tab butonlarını deaktif et
            document.querySelectorAll('.tab-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Seçilen tab'ı göster
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        // Fotoğraf yükleme
        document.getElementById('photo-upload').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                this.form.submit();
            }
        });

        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });

        // TR/DE dil desteği
        (function(){
            const tr = {
                userRole:'Eğitmen', backBtn:'← Geri Dön',
                pageTitle:'👤 Profil Yönetimi', pageSubtitle:'Kişisel bilgilerinizi yönetin ve hesabınızı güncelleyin',
                labelName:'👤 Ad Soyad', labelEmail:'📧 E-posta', labelPhone:'📱 Telefon', labelDepartment:'🏫 Bölüm', labelRole:'👨‍🏫 Rol', labelMembership:'📅 Üyelik',
                statLabel1:'Oluşturulan Sınav', statLabel2:'Aktif Öğrenci',
                tabPersonal:'👤 Kişisel Bilgiler', tabSecurity:'🔒 Güvenlik', tabPreferences:'⚙️ Tercihler',
                sectionPersonal:'👤 Kişisel Bilgiler', labelNameForm:'Ad Soyad *', labelEmailForm:'E-posta', labelPhoneForm:'Telefon', labelDepartmentForm:'Bölüm', labelBio:'Hakkımda', btnUpdateInfo:'💾 Bilgileri Güncelle',
                sectionSecurity:'🔒 Güvenlik', labelCurrentPassword:'Mevcut Şifre *', labelNewPassword:'Yeni Şifre *', labelConfirmPassword:'Yeni Şifre Tekrar *', btnChangePassword:'🔑 Şifreyi Değiştir',
                sectionPreferences:'⚙️ Tercihler', labelLanguage:'Dil Tercihi', labelTimezone:'Saat Dilimi', labelNotifications:'Bildirim Tercihleri', labelEmailNotif:'📧 E-posta bildirimleri', labelExamNotif:'📝 Sınav bildirimleri', labelSystemNotif:'⚙️ Sistem bildirimleri', btnSavePreferences:'⚙️ Tercihleri Kaydet'
            };
            const de = {
                userRole:'Lehrpersonal', backBtn:'← Zurück',
                pageTitle:'👤 Profilverwaltung', pageSubtitle:'Verwalten Sie Ihre persönlichen Daten und aktualisieren Sie Ihr Konto',
                labelName:'👤 Vor- und Nachname', labelEmail:'📧 E-Mail', labelPhone:'📱 Telefon', labelDepartment:'🏫 Kompetenzstelle', labelRole:'👨‍🏫 Rolle', labelMembership:'📅 Mitgliedschaft',
                statLabel1:'Erstellte Prüfungen', statLabel2:'Aktive Schüler',
                tabPersonal:'👤 Persönliche Daten', tabSecurity:'🔒 Sicherheit', tabPreferences:'⚙️ Einstellungen',
                sectionPersonal:'👤 Persönliche Daten', labelNameForm:'Vor- und Nachname *', labelEmailForm:'E-Mail', labelPhoneForm:'Telefon', labelDepartmentForm:'Kompetenzstelle', labelBio:'Über mich', btnUpdateInfo:'💾 Daten aktualisieren',
                sectionSecurity:'🔒 Sicherheit', labelCurrentPassword:'Aktuelles Passwort *', labelNewPassword:'Neues Passwort *', labelConfirmPassword:'Neues Passwort wiederholen *', btnChangePassword:'🔑 Passwort ändern',
                sectionPreferences:'⚙️ Einstellungen', labelLanguage:'Sprache', labelTimezone:'Zeitzone', labelNotifications:'Benachrichtigungseinstellungen', labelEmailNotif:'📧 E-Mail-Benachrichtigungen', labelExamNotif:'📝 Prüfungsbenachrichtigungen', labelSystemNotif:'⚙️ Systembenachrichtigungen', btnSavePreferences:'⚙️ Einstellungen speichern'
            };
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#userRole', d.userRole); setText('#backBtn', d.backBtn);
                setText('#pageTitle', d.pageTitle); setText('#pageSubtitle', d.pageSubtitle);
                setText('#labelName', d.labelName); setText('#labelEmail', d.labelEmail); setText('#labelPhone', d.labelPhone); setText('#labelDepartment', d.labelDepartment); setText('#labelRole', d.labelRole); setText('#labelMembership', d.labelMembership);
                setText('#statLabel1', d.statLabel1); setText('#statLabel2', d.statLabel2);
                setText('#tabPersonal', d.tabPersonal); setText('#tabSecurity', d.tabSecurity); setText('#tabPreferences', d.tabPreferences);
                setText('#sectionPersonal', d.sectionPersonal); setText('#labelNameForm', d.labelNameForm); setText('#labelEmailForm', d.labelEmailForm); setText('#labelPhoneForm', d.labelPhoneForm); setText('#labelDepartmentForm', d.labelDepartmentForm); setText('#labelBio', d.labelBio); setText('#btnUpdateInfo', d.btnUpdateInfo);
                setText('#sectionSecurity', d.sectionSecurity); setText('#labelCurrentPassword', d.labelCurrentPassword); setText('#labelNewPassword', d.labelNewPassword); setText('#labelConfirmPassword', d.labelConfirmPassword); setText('#btnChangePassword', d.btnChangePassword);
                setText('#sectionPreferences', d.sectionPreferences); setText('#labelLanguage', d.labelLanguage); setText('#labelTimezone', d.labelTimezone); setText('#labelNotifications', d.labelNotifications); setText('#labelEmailNotif', d.labelEmailNotif); setText('#labelExamNotif', d.labelExamNotif); setText('#labelSystemNotif', d.labelSystemNotif); setText('#btnSavePreferences', d.btnSavePreferences);
                const toggle=document.getElementById('langToggle'); if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_teacher_profile', lang);
            }
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_teacher_profile')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle'); if(toggle){ toggle.addEventListener('click', function(){ const next=(localStorage.getItem('lang_teacher_profile')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; apply(next); }); }
                
                // Dil tercihi formu gönderildiğinde localStorage'a kaydet
                const languageSelect = document.getElementById('language');
                if (languageSelect) {
                    languageSelect.addEventListener('change', function() {
                        const selectedLang = this.value;
                        localStorage.setItem('lang_teacher_profile', selectedLang);
                        localStorage.setItem('lang', selectedLang);
                        apply(selectedLang);
                    });
                }
            });
        })();
    </script>
</body>
</html>
