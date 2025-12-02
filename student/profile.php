<?php
/**
 * Öğrenci Profil Sayfası
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğrenci kontrolü
if (!$auth->hasRole('student')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

require_once '../database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Güncel kullanıcı bilgilerini veritabanından çek
$stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
$stmt->execute([':username' => $user['username']]);
$dbUser = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$dbUser) {
    // Kullanıcı bulunamazsa session'dan devam et (fallback)
    $dbUser = $user;
}

// Profil güncelleme işlemi
if ($_POST && !isset($_POST['change_password'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $class = $_POST['class'] ?? '';
    
    // Basit validasyon
    if (empty($name)) {
        $error = 'Ad soyad gereklidir.';
    } elseif (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } else {
        // Veritabanını güncelle
        try {
            $updateSql = "UPDATE users SET full_name = :name, email = :email, phone = :phone, class_section = :class WHERE username = :username";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':phone' => $phone,
                ':class' => $class,
                ':username' => $user['username']
            ]);
            
            // Session'ı güncelle
            $_SESSION['user']['name'] = $name;
            $_SESSION['user']['email'] = $email;
            $_SESSION['user']['phone'] = $phone;
            $_SESSION['user']['class_section'] = $class;
            
            // dbUser'ı güncelle
            $dbUser['full_name'] = $name;
            $dbUser['email'] = $email;
            $dbUser['phone'] = $phone;
            $dbUser['class_section'] = $class;
            
            $success = 'Profil başarıyla güncellendi.';
        } catch (PDOException $e) {
            $error = 'Güncelleme hatası: ' . $e->getMessage();
        }
    }
}

// İstatistikleri hesapla
$totalExams = 0;
$totalPractice = 0;
$averageScore = 0;

try {
    // Alıştırma sayısı ve ortalaması
    $stmt = $conn->prepare("SELECT COUNT(*) as count, AVG(percentage) as avg_score FROM practice_results WHERE username = :username");
    $stmt->execute([':username' => $user['username']]);
    $practiceStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPractice = $practiceStats['count'] ?? 0;
    $practiceAvg = $practiceStats['avg_score'] ?? 0;

    // Sınav sayısı ve ortalaması
    $stmt = $conn->prepare("SELECT COUNT(*) as count, AVG(percentage) as avg_score FROM exam_results WHERE username = :username");
    $stmt->execute([':username' => $user['username']]);
    $examStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalExams = $examStats['count'] ?? 0;
    $examAvg = $examStats['avg_score'] ?? 0;

    // Genel ortalama
    $totalActivities = $totalPractice + $totalExams;
    if ($totalActivities > 0) {
        $averageScore = (($practiceAvg * $totalPractice) + ($examAvg * $totalExams)) / $totalActivities;
    }
} catch (Exception $e) {
    // Hata durumunda 0 kalır
}

// Profil verileri
$profileData = [
    'name' => $dbUser['full_name'] ?? $user['name'] ?? '',
    'email' => $dbUser['email'] ?? '',
    'phone' => $dbUser['phone'] ?? '',
    'class' => $dbUser['class_section'] ?? '',
    'join_date' => $dbUser['created_at'] ?? date('Y-m-d'),
    'last_login' => $dbUser['last_login'] ?? date('Y-m-d H:i:s'),
    'total_exams' => $totalExams,
    'total_practice' => $totalPractice,
    'average_score' => round($averageScore, 1)
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - Bir Soru Bir Sevap</title>
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
            gap: 12px;
            flex-wrap: wrap;
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

        .user-info { display: flex; align-items: center; gap: 12px; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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

        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .profile-header {
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            text-align: center;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3em;
            color: white;
            margin: 0 auto 20px;
            font-weight: bold;
        }

        .profile-name {
            font-size: 2em;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .profile-role {
            color: #7f8c8d;
            font-size: 1.1em;
            margin-bottom: 20px;
        }

        .profile-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-item {
            text-align: center;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
        }

        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #3498db;
            margin-bottom: 5px;
        }

        .stat-label {
            color: #7f8c8d;
            font-size: 0.9em;
        }

        .profile-content {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
        }

        .profile-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .card-title {
            font-size: 1.5em;
            color: #2c3e50;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e1e8ed;
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
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e1e8ed;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #3498db;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        .btn {
            background: #3498db;
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
            background: #2980b9;
            transform: translateY(-2px);
        }

        .btn-secondary {
            background: #95a5a6;
        }

        .btn-secondary:hover {
            background: #7f8c8d;
        }

        .btn-danger {
            background: #e74c3c;
        }

        .btn-danger:hover {
            background: #c0392b;
        }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; font-size: 1em; opacity: .7; }
        .toggle-password:hover { opacity: 1; }

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

        .password-section {
            border-top: 2px solid #e1e8ed;
            padding-top: 25px;
            margin-top: 25px;
        }

        .actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .back-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .profile-content { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
            .profile-stats { grid-template-columns: repeat(2, 1fr); }
            .actions { flex-direction: column; align-items: center; }
        }
        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .back-btn { padding: 5px 8px; font-size: .85rem; }
            .user-info { gap: 8px; }
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
                    <p id="pageTitle">Profil</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;" id="userRole">Öğrenci</div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBack">← Dashboard</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="profile-header">
            <div class="profile-avatar">
                <?php echo strtoupper(substr($profileData['name'], 0, 1)); ?>
            </div>
            <h2 class="profile-name"><?php echo htmlspecialchars($profileData['name']); ?></h2>
            <div class="profile-role" id="profileRole">Öğrenci</div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <div class="stat-number"><?php echo $profileData['total_exams']; ?></div>
                    <div class="stat-label" id="statExams">Sınav</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $profileData['total_practice']; ?></div>
                    <div class="stat-label" id="statPractice">Alıştırma</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $profileData['average_score']; ?>%</div>
                    <div class="stat-label" id="statAverage">Ortalama Puan</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('d.m.Y', strtotime($profileData['join_date'])); ?></div>
                    <div class="stat-label" id="statJoinDate">Katılım Tarihi</div>
                </div>
            </div>
        </div>

        <div class="profile-content">
            <div class="profile-card">
                <h3 class="card-title" id="profileInfoTitle">📝 Profil Bilgileri</h3>
                
                <?php if (isset($success)): ?>
                    <div class="alert alert-success" id="successAlert">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-error" id="errorAlert">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="name" id="labelName">Ad Soyad:</label>
                        <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($profileData['name']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email" id="labelEmail">E-posta:</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($profileData['email']); ?>" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="phone" id="labelPhone">Telefon:</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($profileData['phone']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="class" id="labelClass">Sınıf:</label>
                            <input type="text" id="class" name="class" value="<?php echo htmlspecialchars($profileData['class']); ?>">
                        </div>
                    </div>
                    
                    <button type="submit" class="btn" id="btnUpdateProfile">💾 Profili Güncelle</button>
                </form>
                
                <div class="password-section">
                    <h4 style="margin-bottom: 20px; color: #2c3e50;" id="passwordTitle">🔒 Şifre Değiştir</h4>
                    
                    <?php if (isset($passwordSuccess)): ?>
                        <div class="alert alert-success" id="passwordSuccessAlert">
                            <?php echo htmlspecialchars($passwordSuccess); ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (isset($passwordError)): ?>
                        <div class="alert alert-error" id="passwordErrorAlert">
                            <?php echo htmlspecialchars($passwordError); ?>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="form-group">
                            <label for="current_password" id="labelCurrentPassword">Mevcut Şifre:</label>
                            <div class="password-wrapper">
                                <input type="password" id="current_password" name="current_password" required>
                                <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password" id="labelNewPassword">Yeni Şifre:</label>
                            <div class="password-wrapper">
                                <input type="password" id="new_password" name="new_password" required>
                                <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password" id="labelConfirmPassword">Yeni Şifre (Tekrar):</label>
                            <div class="password-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" required>
                                <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                            </div>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-secondary" id="btnChangePassword">🔑 Şifreyi Değiştir</button>
                    </form>
                </div>
            </div>
            
            <div class="profile-card">
                <h3 class="card-title" id="accountInfoTitle">ℹ️ Hesap Bilgileri</h3>
                
                <div class="info-item">
                    <div class="info-label" id="labelUsername">Kullanıcı Adı:</div>
                    <div class="info-value"><?php echo $user['username']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" id="labelAccountType">Hesap Türü:</div>
                    <div class="info-value" id="accountTypeValue">Öğrenci</div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" id="labelJoinDate">Kayıt Tarihi:</div>
                    <div class="info-value"><?php echo date('d.m.Y', strtotime($profileData['join_date'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" id="labelLastLogin">Son Giriş:</div>
                    <div class="info-value"><?php echo date('d.m.Y H:i', strtotime($profileData['last_login'])); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" id="labelTotalExams">Toplam Sınav:</div>
                    <div class="info-value"><?php echo $profileData['total_exams']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" id="labelTotalPractice">Toplam Alıştırma:</div>
                    <div class="info-value"><?php echo $profileData['total_practice']; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label" id="labelAverageScore">Ortalama Puan:</div>
                    <div class="info-value"><?php echo $profileData['average_score']; ?>%</div>
                </div>
            </div>
        </div>

        <div class="actions">
            <a href="dashboard.php" class="btn" id="btnDashboard">📊 Dashboard</a>
            <a href="results.php" class="btn btn-secondary" id="btnResults">📈 Sonuçlarım</a>
            <a href="../logout.php" class="btn btn-danger" id="btnLogout">🚪 Çıkış Yap</a>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Profil', userRole:'Öğrenci', back:'← Dashboard',
                profileRole:'Öğrenci', statExams:'Sınav', statPractice:'Alıştırma', statAverage:'Ortalama Puan', statJoinDate:'Katılım Tarihi',
                profileInfoTitle:'📝 Profil Bilgileri', labelName:'Ad Soyad:', labelEmail:'E-posta:', labelPhone:'Telefon:', labelClass:'Sınıf:',
                btnUpdateProfile:'💾 Profili Güncelle', passwordTitle:'🔒 Şifre Değiştir', labelCurrentPassword:'Mevcut Şifre:', labelNewPassword:'Yeni Şifre:',
                labelConfirmPassword:'Yeni Şifre (Tekrar):', btnChangePassword:'🔑 Şifreyi Değiştir', accountInfoTitle:'ℹ️ Hesap Bilgileri',
                labelUsername:'Kullanıcı Adı:', labelAccountType:'Hesap Türü:', accountTypeValue:'Öğrenci',
                labelJoinDate:'Kayıt Tarihi:', labelLastLogin:'Son Giriş:', labelTotalExams:'Toplam Sınav:', labelTotalPractice:'Toplam Alıştırma:',
                labelAverageScore:'Ortalama Puan:', btnDashboard:'📊 Dashboard', btnResults:'📈 Sonuçlarım', btnLogout:'🚪 Çıkış Yap',
                successMessage:'Profil başarıyla güncellendi.', errorMessage:'Ad soyad gereklidir.', passwordSuccessMessage:'Şifre başarıyla değiştirildi.',
                passwordErrorMessage:'Şifreler eşleşmiyor.', passwordCurrentError:'Mevcut şifre hatalı.'
            };
            const de = {
                pageTitle:'Profil', userRole:'Schüler', back:'← Dashboard',
                profileRole:'Schüler', statExams:'Prüfungen', statPractice:'Übungen', statAverage:'Durchschnittspunktzahl', statJoinDate:'Beitrittsdatum',
                profileInfoTitle:'📝 Profilinformationen', labelName:'Vor- und Nachname:', labelEmail:'E-Mail:', labelPhone:'Telefon:', labelClass:'Klasse:',
                btnUpdateProfile:'💾 Profil aktualisieren', passwordTitle:'🔒 Passwort ändern', labelCurrentPassword:'Aktuelles Passwort:', labelNewPassword:'Neues Passwort:',
                labelConfirmPassword:'Neues Passwort (wiederholen):', btnChangePassword:'🔑 Passwort ändern', accountInfoTitle:'ℹ️ Kontoinformationen',
                labelUsername:'Benutzername:', labelAccountType:'Kontotyp:', accountTypeValue:'Schüler',
                labelJoinDate:'Registrierungsdatum:', labelLastLogin:'Letzter Login:', labelTotalExams:'Gesamt Prüfungen:', labelTotalPractice:'Gesamt Übungen:',
                labelAverageScore:'Durchschnittspunktzahl:', btnDashboard:'📊 Dashboard', btnResults:'📈 Meine Ergebnisse', btnLogout:'🚪 Abmelden',
                successMessage:'Profil erfolgreich aktualisiert.', errorMessage:'Vor- und Nachname sind erforderlich.', passwordSuccessMessage:'Passwort erfolgreich geändert.',
                passwordErrorMessage:'Passwörter stimmen nicht überein.', passwordCurrentError:'Aktuelles Passwort ist falsch.'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){ 
                const d=lang==='de'?de:tr; 
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnBack', d.back);
                setText('#profileRole', d.profileRole);
                setText('#statExams', d.statExams);
                setText('#statPractice', d.statPractice);
                setText('#statAverage', d.statAverage);
                setText('#statJoinDate', d.statJoinDate);
                setText('#profileInfoTitle', d.profileInfoTitle);
                setText('#labelName', d.labelName);
                setText('#labelEmail', d.labelEmail);
                setText('#labelPhone', d.labelPhone);
                setText('#labelClass', d.labelClass);
                setText('#btnUpdateProfile', d.btnUpdateProfile);
                setText('#passwordTitle', d.passwordTitle);
                setText('#labelCurrentPassword', d.labelCurrentPassword);
                setText('#labelNewPassword', d.labelNewPassword);
                setText('#labelConfirmPassword', d.labelConfirmPassword);
                setText('#btnChangePassword', d.btnChangePassword);
                setText('#accountInfoTitle', d.accountInfoTitle);
                setText('#labelUsername', d.labelUsername);
                setText('#labelAccountType', d.labelAccountType);
                setText('#accountTypeValue', d.accountTypeValue);
                setText('#labelJoinDate', d.labelJoinDate);
                setText('#labelLastLogin', d.labelLastLogin);
                setText('#labelTotalExams', d.labelTotalExams);
                setText('#labelTotalPractice', d.labelTotalPractice);
                setText('#labelAverageScore', d.labelAverageScore);
                setText('#btnDashboard', d.btnDashboard);
                setText('#btnResults', d.btnResults);
                setText('#btnLogout', d.btnLogout);
                
                const toggle=document.getElementById('langToggle'); 
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE'); 
                localStorage.setItem('lang_profile', lang); 
            }
            
            document.addEventListener('DOMContentLoaded', function(){ 
                const lang=localStorage.getItem('lang_profile')||localStorage.getItem('lang')||'tr'; 
                apply(lang); 
                const toggle=document.getElementById('langToggle'); 
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_profile')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                } 
            });
        })();

        // Şifre göster/gizle
        document.querySelectorAll('.toggle-password').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const input = this.previousElementSibling;
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                this.textContent = isHidden ? '🙈' : '👁';
                this.setAttribute('aria-label', isHidden ? 'Şifreyi gizle' : 'Şifreyi göster');
            });
        });
    </script>
</body>
</html>
