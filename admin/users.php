<?php
/**
 * SuperAdmin - Kullanıcı Yönetimi
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// SuperAdmin kontrolü
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// URL parametrelerinden başarı mesajlarını al
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'user_added':
            $username = $_GET['username'] ?? '';
            $password = $_GET['password'] ?? '';
            $success = '<div style="background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%); border: 1px solid #c3e6cb; border-radius: 10px; padding: 20px; margin: 20px 0;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <span style="font-size: 24px; margin-right: 10px;">✅</span>
                    <h3 style="margin: 0; color: #155724; font-size: 1.3rem;">Kullanıcı Başarıyla Eklendi!</h3>
                </div>
                <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #b8dacc;">
                    <div style="margin-bottom: 10px;">
                        <strong style="color: #155724;">👤 Kullanıcı Adı:</strong>
                        <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-family: monospace; margin-left: 8px;">' . htmlspecialchars($username) . '</code>
                    </div>
                    <div>
                        <strong style="color: #155724;">🔑 Şifre:</strong>
                        <code style="background: #f8f9fa; padding: 4px 8px; border-radius: 4px; font-family: monospace; margin-left: 8px;">' . htmlspecialchars($password) . '</code>
                    </div>
                </div>
                <div style="margin-top: 15px; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 6px; color: #856404; font-size: 0.9rem;">
                    ⚠️ <strong>Önemli:</strong> Bu bilgileri güvenli bir yerde saklayın. Şifre tekrar gösterilmeyecektir.
                </div>
            </div>';
            break;
        case 'user_deleted':
            $success = 'Kullanıcı başarıyla silindi.';
            break;
        case 'user_updated':
            $success = 'Kullanıcı başarıyla güncellendi.';
            break;
    }
}

// Konfigürasyon dosyasını dahil et
require_once 'includes/locations.php';
$institutions = getAllBranches(); // Düz liste gerekirse diye


// Kullanıcı ekleme
if ($_POST['action'] ?? '' === 'add_user') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $institution = $_POST['institution'] ?? '';
    $region = $_POST['region'] ?? '';
    if (empty($region) && !empty($institution)) {
        $region = getRegionByBranch($institution) ?? 'Arlberg';
    }
    $class_section = trim($_POST['class_section'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    if (empty($firstName) || empty($lastName) || empty($institution)) {
        $error = 'Tüm zorunlu alanlar doldurulmalıdır.';
    } else {
        // Kullanıcı adını otomatik oluştur (Ü/ü -> ue, Ö/ö -> oe)
        $lastNamePart = strlen($lastName) >= 5 ? substr($lastName, 0, 5) : $lastName;
        $firstNamePart = substr($firstName, 0, 3);
        $mapSearch = ['Ü','ü','Ö','ö','Ğ','ğ','Ş','ş','Ç','ç','İ','I','ı'];
        $mapReplace = ['ue','ue','oe','oe','g','g','s','s','c','c','i','i','i'];
        $lastNamePart = str_replace($mapSearch, $mapReplace, $lastNamePart);
        $firstNamePart = str_replace($mapSearch, $mapReplace, $firstNamePart);
        $baseUsername = strtolower($lastNamePart . '.' . $firstNamePart);
        
        // Kullanıcı adı benzersiz olana kadar sayı ekle
        $username = $baseUsername;
        $counter = 1;
        $existingUsers = $auth->getAllUsers();
        
        while (isset($existingUsers[$username])) {
            $username = $baseUsername . $counter;
            $counter++;
        }
        
        // Standart şifre ata
        $password = 'iqra2025#';
        
        // Tam adı oluştur
        $fullName = $firstName . ' ' . $lastName;
        
        try {
            if ($auth->saveUser($username, $password, $role, $fullName, $institution, $class_section, $email, $phone, $region)) {
                    // POST-redirect-GET pattern - sayfayı yeniden yönlendir
                header('Location: users.php?success=user_added&username=' . urlencode($username) . '&password=' . urlencode($password));
                    exit;
                } else {
                    $error = 'Kullanıcı eklenirken bir hata oluştu.';
                }
            } catch (Exception $e) {
                $error = 'Hata: ' . $e->getMessage();
            }
        }
    }

// Random şifre oluşturma fonksiyonu
function generateRandomPassword($length = 8) {
    $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $password;
}

// Kullanıcı silme
if ($_POST['action'] ?? '' === 'delete_user') {
    $username = $_POST['username'] ?? '';
    if (!empty($username)) {
        if ($auth->deleteUser($username)) {
            // POST-redirect-GET pattern - sayfayı yeniden yönlendir
            header('Location: users.php?success=user_deleted');
            exit;
        } else {
            $error = 'Kullanıcı silinirken bir hata oluştu.';
        }
    }
}

// Kullanıcı düzenleme
if ($_POST['action'] ?? '' === 'edit_user') {
    $username = $_POST['username'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $role = $_POST['role'] ?? '';
    $institution = trim($_POST['institution'] ?? '');
    $class_section = trim($_POST['class_section'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_password = trim($_POST['new_password'] ?? '');
    
    if (!empty($username) && !empty($name) && !empty($role) && !empty($institution)) {
        // Mevcut kullanıcıyı al
$allUsers = $auth->getAllUsers();
        if (isset($allUsers[$username])) {
            $userData = $allUsers[$username];
            
            // Şifre değiştirilmişse yeni şifre kullan, yoksa eski şifreyi koru
            $password = !empty($new_password) ? $new_password : $userData['password'];
            
            // Kullanıcıyı güncelle
            if ($auth->saveUser($username, $password, $role, $name, $institution, $class_section, $email, $phone)) {
                header('Location: users.php?success=user_updated');
                exit;
            } else {
                $error = 'Kullanıcı güncellenirken bir hata oluştu.';
            }
        } else {
            $error = 'Kullanıcı bulunamadı.';
        }
    } else {
        $error = 'Lütfen tüm zorunlu alanları doldurun.';
    }
}

// CSV Import
if ($_POST['action'] ?? '' === 'import_csv') {
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $csvFile = $_FILES['csv_file']['tmp_name'];
        $importedCount = 0;
        $errorCount = 0;
        $errors = [];
        
        if (($handle = fopen($csvFile, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ","); // İlk satır başlık
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($data) >= 4) { // En az 4 sütun olmalı
                    $firstName = trim($data[0]);
                    $lastName = trim($data[1]);
                    $role = trim($data[2]);
                    $institution = trim($data[3]);
                    $class_section = isset($data[4]) ? trim($data[4]) : '';
                    $email = isset($data[5]) ? trim($data[5]) : '';
                    $phone = isset($data[6]) ? trim($data[6]) : '';
                    
                    if (!empty($firstName) && !empty($lastName) && !empty($role) && !empty($institution)) {
                        // Kullanıcı adını otomatik oluştur (Ü/ü -> ue, Ö/ö -> oe)
                        $lastNamePart = strlen($lastName) >= 5 ? substr($lastName, 0, 5) : $lastName;
                        $firstNamePart = substr($firstName, 0, 3);
                        $mapSearch = ['Ü','ü','Ö','ö','Ğ','ğ','Ş','ş','Ç','ç','İ','I','ı'];
                        $mapReplace = ['ue','ue','oe','oe','g','g','s','s','c','c','i','i','i'];
                        $lastNamePart = str_replace($mapSearch, $mapReplace, $lastNamePart);
                        $firstNamePart = str_replace($mapSearch, $mapReplace, $firstNamePart);
                        $baseUsername = strtolower($lastNamePart . '.' . $firstNamePart);
                        
                        // Kullanıcı adı benzersiz olana kadar sayı ekle
                        $username = $baseUsername;
                        $counter = 1;
                        $existingUsers = $auth->getAllUsers();
                        
                        while (isset($existingUsers[$username])) {
                            $username = $baseUsername . $counter;
                            $counter++;
                        }
                        
                        // Standart şifre ata
                        $password = 'iqra2025#';
                        
                        // Tam adı oluştur
                        $fullName = $firstName . ' ' . $lastName;
                        
                        try {
                            if ($auth->saveUser($username, $password, $role, $fullName, $institution, $class_section, $email, $phone)) {
                                $importedCount++;
                            } else {
                                $errorCount++;
                                $errors[] = "Hata: $fullName eklenemedi";
                            }
                        } catch (Exception $e) {
                            $errorCount++;
                            $errors[] = "Hata: $fullName - " . $e->getMessage();
                        }
                    } else {
                        $errorCount++;
                        $errors[] = "Eksik veri: " . implode(', ', $data);
                    }
                }
            }
            fclose($handle);
        }
        
        if ($importedCount > 0) {
            $success = "CSV import tamamlandı! $importedCount kullanıcı eklendi.";
            if ($errorCount > 0) {
                $success .= " $errorCount hata oluştu.";
            }
        } else {
            $error = "Hiç kullanıcı eklenemedi. CSV formatını kontrol edin.";
        }
    } else {
        $error = 'CSV dosyası yüklenirken hata oluştu.';
    }
}

// Excel Export
if ($_GET['action'] ?? '' === 'export_csv') {
    $exportType = $_GET['type'] ?? 'all'; // all, students, teachers
    
    $allUsers = $auth->getAllUsers();
    $exportUsers = [];

foreach ($allUsers as $username => $userData) {
        if ($exportType === 'students' && $userData['role'] !== 'student') continue;
        if ($exportType === 'teachers' && $userData['role'] !== 'teacher') continue;
        
        $exportUsers[] = [
            'username' => $username,
            'name' => $userData['full_name'] ?? $userData['name'] ?? 'Bilinmiyor',
            'role' => $userData['role'],
            'institution' => $userData['branch'] ?? $userData['institution'] ?? '',
            'class_section' => $userData['class_section'] ?? '',
            'email' => $userData['email'] ?? '',
            'phone' => $userData['phone'] ?? '',
            'created_at' => $userData['created_at'] ?? ''
        ];
    }
    
    // Dosya adı
    $typeNames = [
        'all' => 'Tum_Kullanicilar',
        'students' => 'Ogrenciler',
        'teachers' => 'Ogretmenler'
    ];
    $filename = $typeNames[$exportType] . '_' . date('Y-m-d_H-i-s') . '.xls';
    
    // Excel uyumlu HTML tablosu oluştur
    $html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
<head>
    <meta charset="UTF-8">
    <meta name="ExcelCreated" content="1">
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 30px; background: #f8f9fa; padding: 20px; border-radius: 10px; }
        .header h1 { color: #2c3e50; margin: 0 0 10px 0; font-size: 24px; }
        .header p { color: #7f8c8d; margin: 5px 0; font-size: 14px; }
        .stats { background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
        .stats h3 { margin: 0 0 15px 0; color: #1976d2; font-size: 16px; }
        .stats-grid { display: table; width: 100%; }
        .stats-row { display: table-row; }
        .stat-item { display: table-cell; text-align: center; padding: 10px; width: 25%; }
        .stat-number { font-size: 20px; font-weight: bold; color: #1976d2; }
        .stat-label { color: #666; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th { background: #4a90e2; color: white; padding: 12px 8px; text-align: left; font-weight: bold; border: 1px solid #ddd; }
        td { padding: 10px 8px; border: 1px solid #ddd; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        tr:hover { background-color: #e8f4fd; }
        .role-student { color: #27ae60; font-weight: bold; }
        .role-teacher { color: #f39c12; font-weight: bold; }
        .role-superadmin { color: #e74c3c; font-weight: bold; }
        .username { font-weight: bold; color: #2c3e50; }
    </style>
</head>
<body>
    <div class="header">
        <h1>📊 BİR SORU BİR SEVAP - KULLANICI LİSTESİ</h1>
        <p><strong>Export Tarihi:</strong> ' . date('d.m.Y H:i:s') . '</p>
        <p><strong>Export Türü:</strong> ' . ucfirst($exportType) . '</p>
    </div>
    
    <div class="stats">
        <h3>📈 İstatistikler</h3>
        <div class="stats-grid">
            <div class="stats-row">
                <div class="stat-item">
                    <div class="stat-number">' . count($exportUsers) . '</div>
                    <div class="stat-label">Toplam Kullanıcı</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">' . count(array_filter($exportUsers, fn($u) => $u['role'] === 'student')) . '</div>
                    <div class="stat-label">Öğrenci</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">' . count(array_filter($exportUsers, fn($u) => $u['role'] === 'teacher')) . '</div>
                    <div class="stat-label">Öğretmen</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">' . count(array_filter($exportUsers, fn($u) => $u['role'] === 'superadmin')) . '</div>
                    <div class="stat-label">SuperAdmin</div>
                </div>
            </div>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Kullanıcı Adı</th>
                <th>Ad Soyad</th>
                <th>Rol</th>
                <th>Kurum</th>
                <th>Sınıf</th>
                <th>E-posta</th>
                <th>Telefon</th>
                <th>Kayıt Tarihi</th>
            </tr>
        </thead>
        <tbody>';
    
    foreach ($exportUsers as $user) {
        $roleClass = 'role-' . $user['role'];
        $roleText = '';
        switch ($user['role']) {
            case 'student':
                $roleText = '👨‍🎓 Öğrenci';
                break;
            case 'teacher':
                $roleText = '👨‍🏫 Eğitmen';
                break;
            case 'superadmin':
                $roleText = '👑 SuperAdmin';
                break;
        }
        
        $html .= '<tr>
            <td class="username">' . htmlspecialchars($user['username']) . '</td>
            <td>' . htmlspecialchars($user['name']) . '</td>
            <td class="' . $roleClass . '">' . $roleText . '</td>
            <td>' . htmlspecialchars($user['institution']) . '</td>
            <td>' . htmlspecialchars($user['class_section']) . '</td>
            <td>' . htmlspecialchars($user['email']) . '</td>
            <td>' . htmlspecialchars($user['phone']) . '</td>
            <td>' . htmlspecialchars($user['created_at']) . '</td>
        </tr>';
    }
    
    $html .= '</tbody>
    </table>
</body>
</html>';
    
    // Excel uyumlu dosya olarak gönder
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    echo $html;
    exit;
}

// Sayfalama parametreleri
$itemsPerPage = isset($_GET['items_per_page']) ? max(10, intval($_GET['items_per_page'])) : 50;
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$searchTerm = isset($_GET['search']) ? trim($_GET['search']) : '';
$roleFilter = isset($_GET['role']) ? $_GET['role'] : '';
$institutionFilter = isset($_GET['institution']) ? $_GET['institution'] : '';

// Kullanıcıları getir ve filtrele
$allUsers = $auth->getAllUsers();
$filteredUsers = [];

foreach ($allUsers as $username => $userData) {
    $user = [
        'username' => $username,
        'role' => $userData['role'],
        'name' => $userData['full_name'] ?? $userData['name'] ?? 'Bilinmiyor',
        'password' => $userData['password'] ?? 'N/A',
        'institution' => $userData['branch'] ?? $userData['institution'] ?? 'Belirtilmemiş',
        'class_section' => $userData['class_section'] ?? '',
        'email' => $userData['email'] ?? '',
        'phone' => $userData['phone'] ?? '',
        'created_at' => $userData['created_at'] ?? 'Bilinmiyor',
        'last_login' => $userData['last_login'] ?? 'Hiç giriş yapmamış'
    ];
    
    // Arama filtresi
    if ($searchTerm && !stripos($user['name'], $searchTerm) && !stripos($user['username'], $searchTerm)) {
        continue;
    }
    
    // Rol filtresi
    if ($roleFilter && $user['role'] !== $roleFilter) {
        continue;
    }
    
    // Kurum filtresi
    if ($institutionFilter && $user['institution'] !== $institutionFilter) {
        continue;
    }
    
    $filteredUsers[] = $user;
}

// Sayfalama hesaplamaları
$totalUsers = count($filteredUsers);
$totalPages = ceil($totalUsers / $itemsPerPage);
$offset = ($currentPage - 1) * $itemsPerPage;

// Mevcut sayfa için kullanıcıları al
$users = array_slice($filteredUsers, $offset, $itemsPerPage);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - SuperAdmin</title>
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
                <h2>Kullanıcı Yönetimi</h2>
                <p>Sistem kullanıcılarını yönetin ve yeni kullanıcılar ekleyin</p>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert-box">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <?php echo $success; ?>
        <?php endif; ?>

        <!-- Premium Stats -->
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px;">
            <div class="glass-card-premium">
                <i class="fas fa-users glass-card-icon"></i>
                <div class="glass-card-title">
                    <i class="fas fa-users" style="color: #3b82f6;"></i> Toplam Kullanıcı
                </div>
                <div class="glass-card-value"><?php echo number_format($totalUsers); ?></div>
                <div class="progress-mini"><div class="progress-fill" style="width:100%; background: linear-gradient(90deg, #3b82f6, #60a5fa);"></div></div>
            </div>
            
            <div class="glass-card-premium">
                <i class="fas fa-user-graduate glass-card-icon"></i>
                <div class="glass-card-title">
                    <i class="fas fa-user-graduate" style="color: #22c55e;"></i> Öğrenci
                </div>
                <div class="glass-card-value"><?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'student')); ?></div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo ($totalUsers > 0 ? (count(array_filter($filteredUsers, fn($u) => $u['role'] === 'student'))/$totalUsers)*100 : 0); ?>%; background: linear-gradient(90deg, #22c55e, #4ade80);"></div></div>
            </div>
            
            <div class="glass-card-premium">
                <i class="fas fa-chalkboard-teacher glass-card-icon"></i>
                <div class="glass-card-title">
                    <i class="fas fa-chalkboard-teacher" style="color: #f59e0b;"></i> Eğitmen
                </div>
                <div class="glass-card-value"><?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'teacher')); ?></div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo ($totalUsers > 0 ? (count(array_filter($filteredUsers, fn($u) => $u['role'] === 'teacher'))/$totalUsers)*100 : 0); ?>%; background: linear-gradient(90deg, #f59e0b, #fbbf24);"></div></div>
            </div>
            
            <div class="glass-card-premium">
                <i class="fas fa-user-shield glass-card-icon"></i>
                <div class="glass-card-title">
                    <i class="fas fa-user-shield" style="color: #ef4444;"></i> SuperAdmin
                </div>
                <div class="glass-card-value"><?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'superadmin')); ?></div>
                <div class="progress-mini"><div class="progress-fill" style="width:<?php echo ($totalUsers > 0 ? (count(array_filter($filteredUsers, fn($u) => $u['role'] === 'superadmin'))/$totalUsers)*100 : 0); ?>%; background: linear-gradient(90deg, #ef4444, #f87171);"></div></div>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="control-panel">
            <form method="GET" style="display: flex; gap: 15px; align-items: center; flex: 1; flex-wrap: wrap;">
                <div class="search-wrapper">
                    <input type="text" name="search" class="search-input-modern" placeholder="Kullanıcı adı veya isim ara..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    <i class="fas fa-search search-icon"></i>
                </div>
                
                <select name="role" class="filter-select-modern" onchange="this.form.submit()">
                    <option value="">Tüm Roller</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Öğrenci</option>
                    <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Eğitmen</option>
                    <option value="superadmin" <?php echo $roleFilter === 'superadmin' ? 'selected' : ''; ?>>SuperAdmin</option>
                </select>

                <select name="region" class="filter-select-modern" onchange="this.form.submit()">
                    <option value="">Tüm Bölgeler</option>
                    <?php foreach ($regionConfig as $region => $branches): ?>
                        <option value="<?php echo htmlspecialchars($region); ?>" <?php echo ($regionFilter ?? '') === $region ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($region); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="institution" class="filter-select-modern" onchange="this.form.submit()">
                    <option value="">Tüm Kurumlar</option>
                    <?php foreach ($regionConfig as $region => $branches): ?>
                        <?php if (!empty($branches)): ?>
                            <optgroup label="<?php echo htmlspecialchars($region); ?>">
                                <?php foreach ($branches as $branch): ?>
                                    <option value="<?php echo htmlspecialchars($branch); ?>" <?php echo $institutionFilter === $branch ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($branch); ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>

                <?php if ($searchTerm || $roleFilter || $institutionFilter): ?>
                    <a href="users.php" class="clean-btn">
                        <i class="fas fa-times"></i> Temizle
                    </a>
                <?php endif; ?>
            </form>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <div class="dropdown">
                    <button class="btn btn-success" onclick="toggleDropdown('exportDropdown')" style="padding: 12px 20px; font-weight: 500;">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                    <div id="exportDropdown" class="dropdown-content">
                        <a href="?action=export_csv&type=all">📊 Tüm Kullanıcılar (Excel)</a>
                        <a href="?action=export_csv&type=students">👨‍🎓 Sadece Öğrenciler (Excel)</a>
                        <a href="?action=export_csv&type=teachers">👨‍🏫 Sadece Eğitmenler (Excel)</a>
                    </div>
                </div>
                    
                <button class="btn btn-warning" onclick="toggleImportModal()" style="padding: 12px 20px; font-weight: 500;">
                    <i class="fas fa-file-import"></i> Import
                </button>
                
                <a href="template.csv" class="btn btn-secondary" style="padding: 12px 20px; font-weight: 500;" download>
                    <i class="fas fa-download"></i> Şablon
                </a>
                
                <a href="../update_passwords.php" class="btn btn-danger" style="padding: 12px 20px; font-weight: 500; background: rgba(220, 38, 38, 0.2); border-color: rgba(220, 38, 38, 0.3); color: #f87171;" 
                   onclick="return confirm('Mevcut tüm kullanıcıların şifrelerini iqra2025# olarak güncellemek istediğinizden emin misiniz? (Superadmin hariç)')">
                    <i class="fas fa-key"></i> Şifre Reset
                </a>
            </div>
        </div>

        <div class="content-grid">
            <div class="glass-panel" style="grid-column: 1 / -1;">
                <div class="panel-header">
                    <div class="table-title-section">
                        <h3><i class="fas fa-users"></i> Kullanıcı Listesi</h3>
                        <div class="text-muted" style="font-size: 0.9em; margin-top: 5px;">Toplam <?php echo $totalUsers; ?> kullanıcı yönetiliyor</div>
                    </div>
                    <div style="display: flex; gap: 15px; align-items: center;">
                        <button class="btn btn-primary" onclick="toggleAddUserModal()">
                            <i class="fas fa-plus"></i> Yeni Kullanıcı
                        </button>
                        <div class="table-controls">
                            <select onchange="changeItemsPerPage(this.value)" class="form-select" style="padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255,255,255,0.1); background: rgba(0,0,0,0.2); color: white;">
                                <option value="25" <?php echo $itemsPerPage == 25 ? 'selected' : ''; ?>>25 / sayfa</option>
                                <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50 / sayfa</option>
                                <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100 / sayfa</option>
                            </select>
                        </div>
                    </div>
                </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state" style="text-align: center; padding: 50px;">
                        <div style="font-size: 4em; color: rgba(255,255,255,0.1); margin-bottom: 20px;"><i class="fas fa-users-slash"></i></div>
                        <h3 style="color: white; margin-bottom: 10px;">Kullanıcı Bulunamadı</h3>
                        <p style="color: var(--text-muted);">Arama kriterlerinize uygun kullanıcı bulunmamaktadır.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive" style="overflow-x: auto;">
                    <table class="table users-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Kurum</th>
                            <th>İletişim</th>
                            <th>Kayıt Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                        <tbody>
                        <?php foreach ($users as $user): ?>
                                <tr style="transition: background 0.2s;">
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar" style="box-shadow: 0 4px 10px rgba(0,0,0,0.1);">
                                            <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name" style="font-weight: 600; color: white;"><?php echo htmlspecialchars($user['name'] ?? 'Bilinmiyor'); ?></div>
                                            <div class="user-username" style="font-size: 0.85em; color: var(--text-muted);">@<?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php 
                                        $roleIcons = [
                                            'student' => '<i class="fas fa-user-graduate"></i>',
                                            'teacher' => '<i class="fas fa-chalkboard-teacher"></i>', 
                                            'superadmin' => '<i class="fas fa-crown"></i>'
                                        ];
                                        echo ($roleIcons[$user['role']] ?? '') . ' ' . ucfirst($user['role']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                        <div style="font-weight: 500; color: #e2e8f0;">
                                        <?php 
                                            $dispRegion = $user['region'] ?? getRegionByBranch($user['institution']) ?? '';
                                            if ($dispRegion) {
                                                echo '<span style="color: var(--text-muted); font-size: 0.9em;">' . htmlspecialchars($dispRegion) . ' &rsaquo; </span>';
                                            }
                                            echo htmlspecialchars($user['institution']); 
                                        ?>
                                        </div>
                                    <?php if (!empty($user['class_section'])): ?>
                                        <div style="font-size: 0.8rem; color: #7f8c8d; margin-top: 4px; display: inline-block; background: rgba(255,255,255,0.05); padding: 2px 6px; border-radius: 4px;">
                                            <i class="fas fa-layer-group"></i> <?php echo htmlspecialchars($user['class_section']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="display: flex; flex-direction: column; gap: 4px;">
                                    <?php if (!empty($user['email'])): ?>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <i class="fas fa-envelope" style="width: 16px;"></i> <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($user['phone'])): ?>
                                        <div style="font-size: 0.85rem; color: var(--text-muted);">
                                            <i class="fas fa-phone" style="width: 16px;"></i> <?php echo htmlspecialchars($user['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                    </div>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem; color: #e2e8f0;">
                                        <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #7f8c8d;">
                                        <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons" style="display: flex; gap: 8px;">
                                        <button class="btn btn-sm" style="background: rgba(59, 130, 246, 0.2); color: #60a5fa; padding: 6px 10px;" onclick="editUser('<?php echo htmlspecialchars($user['username']); ?>')" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?');">
                                            <input type="hidden" name="action" value="delete_user">
                                            <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                            <button type="submit" class="btn btn-sm" style="background: rgba(239, 68, 68, 0.2); color: #fca5a5; padding: 6px 10px;" title="Sil">
                                            <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                    
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <button onclick="goToPage(1)" <?php echo $currentPage == 1 ? 'disabled' : ''; ?>>
                                ⏮️ İlk
                            </button>
                            <button onclick="goToPage(<?php echo $currentPage - 1; ?>)" <?php echo $currentPage == 1 ? 'disabled' : ''; ?>>
                                ⏪ Önceki
                            </button>
                            
                            <?php
                            $startPage = max(1, $currentPage - 2);
                            $endPage = min($totalPages, $currentPage + 2);
                            
                            for ($i = $startPage; $i <= $endPage; $i++):
                            ?>
                                <button onclick="goToPage(<?php echo $i; ?>)" class="<?php echo $i == $currentPage ? 'active' : ''; ?>">
                                    <?php echo $i; ?>
                                </button>
                            <?php endfor; ?>
                            
                            <button onclick="goToPage(<?php echo $currentPage + 1; ?>)" <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>>
                                Sonraki ⏩
                            </button>
                            <button onclick="goToPage(<?php echo $totalPages; ?>)" <?php echo $currentPage == $totalPages ? 'disabled' : ''; ?>>
                                Son ⏭️
                            </button>
                            
                            <div class="pagination-info">
                                <?php echo $offset + 1; ?>-<?php echo min($offset + $itemsPerPage, $totalUsers); ?> / <?php echo $totalUsers; ?> kullanıcı
            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">➕ Yeni Kullanıcı Ekle</h3>
                <span class="close" onclick="closeAddUserModal()">&times;</span>
            </div>
            
            <form method="POST" id="addUserForm">
                <input type="hidden" name="action" value="add_user">
                
                <div class="form-group">
                    <label for="add_first_name">Ad *</label>
                    <input type="text" id="add_first_name" name="first_name" required placeholder="Örn: Ahmet" oninput="updateAddUsernamePreview()">
                </div>
                
                <div class="form-group">
                    <label for="add_last_name">Soyad *</label>
                    <input type="text" id="add_last_name" name="last_name" required placeholder="Örn: Yılmaz" oninput="updateAddUsernamePreview()">
                </div>
                
                <div id="add-username-preview" style="background: #e8f4fd; padding: 10px; border-radius: 5px; margin-bottom: 15px; font-family: monospace; color: #2c3e50;">
                    <strong>Oluşturulacak Kullanıcı Adı:</strong> <span id="add-preview-username">...</span>
                </div>
                
                <div class="form-group">
                    <label for="add_role">Rol *</label>
                    <select id="add_role" name="role" required>
                        <option value="">Rol Seçin</option>
                        <option value="student">👨‍🎓 Öğrenci</option>
                        <option value="teacher">👨‍🏫 Eğitmen</option>
                        <option value="superadmin">👑 SuperAdmin</option>
                    </select>
                </div>
                
            <div class="form-group">
                    <label for="add_region">Bölge *</label>
                    <select id="add_region" name="region" required onchange="updateBranchOptions('add')">
                        <option value="">Bölge Seçin</option>
                        <?php foreach ($regionConfig as $region => $branches): ?>
                            <option value="<?php echo htmlspecialchars($region); ?>"><?php echo htmlspecialchars($region); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="add_institution">Kurum (Şube) *</label>
                    <select id="add_institution" name="institution" required>
                        <option value="">Önce Bölge Seçin</option>
                    </select>
                </div>
                
                <script>
                    const regionData = <?php echo json_encode($regionConfig); ?>;
                    
                    function updateBranchOptions(prefix) {
                        const regionSelect = document.getElementById(prefix + '_region');
                        const branchSelect = document.getElementById(prefix + '_institution');
                        const selectedRegion = regionSelect.value;
                        
                        branchSelect.innerHTML = '<option value="">Kurum Seçin</option>';
                        
                        if (selectedRegion && regionData[selectedRegion]) {
                            regionData[selectedRegion].forEach(branch => {
                                const option = document.createElement('option');
                                option.value = branch;
                                option.textContent = branch;
                                branchSelect.appendChild(option);
                            });
                        }
                    }
                </script>
                
                <div class="form-group">
                    <label for="add_class_section">Sınıf</label>
                    <input type="text" id="add_class_section" name="class_section" placeholder="Sınıf bilgisi">
                </div>
                
                <div class="form-group">
                    <label for="add_email">E-posta</label>
                    <input type="email" id="add_email" name="email" placeholder="ornek@email.com">
                </div>
                
                <div class="form-group">
                    <label for="add_phone">Telefon</label>
                    <input type="tel" id="add_phone" name="phone" placeholder="+43 123 456 7890">
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAddUserModal()">İptal</button>
                    <button type="submit" class="btn btn-primary">➕ Kullanıcı Ekle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">✏️ Kullanıcı Düzenle</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            
            <form method="POST" id="editForm">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="username" id="edit_username">
                
                <div class="form-group">
                    <label for="edit_name">Ad Soyad *</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_role">Rol *</label>
                    <select id="edit_role" name="role" required>
                        <option value="student">👨‍🎓 Öğrenci</option>
                        <option value="teacher">👨‍🏫 Eğitmen</option>
                        <option value="superadmin">👑 SuperAdmin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_region">Bölge *</label>
                    <select id="edit_region" name="region" required onchange="updateBranchOptions('edit')">
                        <option value="">Bölge Seçin</option>
                        <?php foreach ($regionConfig as $region => $branches): ?>
                            <option value="<?php echo htmlspecialchars($region); ?>"><?php echo htmlspecialchars($region); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit_institution">Kurum (Şube) *</label>
                    <select id="edit_institution" name="institution" required>
                        <option value="">Önce Bölge Seçin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_class_section">Sınıf</label>
                    <input type="text" id="edit_class_section" name="class_section" placeholder="Sınıf bilgisi">
                </div>
                
                <div class="form-group">
                    <label for="edit_email">E-posta</label>
                    <input type="email" id="edit_email" name="email" placeholder="ornek@email.com">
                </div>
                
                <div class="form-group">
                    <label for="edit_phone">Telefon</label>
                    <input type="tel" id="edit_phone" name="phone" placeholder="+43 123 456 7890">
                </div>
                
                <div class="form-group">
                    <label for="edit_new_password">Yeni Şifre</label>
                    <input type="password" id="edit_new_password" name="new_password" placeholder="Yeni şifre girin (boş bırakırsanız mevcut şifre korunur)">
                    <small style="color: #7f8c8d; font-size: 0.8rem;">
                        💡 Mevcut şifre güvenlik nedeniyle gösterilmez. Yeni şifre girmek için bu alanı doldurun.
                    </small>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">İptal</button>
                    <button type="submit" class="btn btn-primary">💾 Güncelle</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="importModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">📥 CSV Import</h3>
                <span class="close" onclick="closeImportModal()">&times;</span>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="import_csv">
                
                <div class="file-upload-area" onclick="document.getElementById('csvFile').click()">
                    <div class="upload-icon">📁</div>
                    <div class="upload-text">CSV dosyasını seçin veya sürükleyin</div>
                    <div class="upload-hint">Maksimum dosya boyutu: 10MB</div>
                    <input type="file" id="csvFile" name="csv_file" class="file-input" accept=".csv" required>
                </div>
                
                <div class="csv-format-info">
                    <h4>📋 CSV Formatı</h4>
                    <p>CSV dosyanız aşağıdaki sırayla sütunlara sahip olmalıdır:</p>
                    <ul>
                        <li><strong>Ad</strong> - Kullanıcının adı (zorunlu)</li>
                        <li><strong>Soyad</strong> - Kullanıcının soyadı (zorunlu)</li>
                        <li><strong>Rol</strong> - student, teacher veya superadmin (zorunlu)</li>
                        <li><strong>Kurum</strong> - Kurum adı (zorunlu)</li>
                        <li><strong>Sınıf</strong> - Sınıf bilgisi (isteğe bağlı)</li>
                        <li><strong>E-posta</strong> - E-posta adresi (isteğe bağlı)</li>
                        <li><strong>Telefon</strong> - Telefon numarası (isteğe bağlı)</li>
                    </ul>
                    <p><strong>Not:</strong> Kullanıcı adı ve şifre otomatik oluşturulacaktır.</p>
                    <div class="template-download" style="margin-top: 18px; background: rgba(6, 133, 103, 0.08); border: 1px solid rgba(6, 133, 103, 0.2); border-radius: 12px; padding: 18px;">
                        <p style="margin: 0 0 12px 0; font-weight: 600; color: #055a4a;">🎯 Hazır Şablon</p>
                        <a href="template.csv" download class="btn btn-success" style="display: inline-block; padding: 10px 18px; border-radius: 10px; font-weight: 600;">📄 CSV Şablonunu İndir</a>
                        <small style="display: block; margin-top: 10px; color: #2c3e50;">Dosyayı indirip örnek satırları kendi kullanıcı bilgilerinizle güncelleyebilirsiniz.</small>
                    </div>
                </div>
                
                <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 20px;">
                    <button type="button" class="btn btn-secondary" onclick="closeImportModal()">İptal</button>
                    <button type="submit" class="btn btn-warning">📥 Import Et</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function goToPage(page) {
            const url = new URL(window.location);
            url.searchParams.set('page', page);
            window.location.href = url.toString();
        }

        function changeItemsPerPage(itemsPerPage) {
            const url = new URL(window.location);
            url.searchParams.set('items_per_page', itemsPerPage);
            url.searchParams.set('page', 1); // Reset to first page
            window.location.href = url.toString();
        }

        function editUser(username) {
            // Kullanıcı verilerini al
            const users = <?php echo json_encode($allUsers); ?>;
            const user = users[username];
            
            if (user) {
                // Modal alanlarını doldur
                document.getElementById('edit_username').value = username;
                document.getElementById('edit_name').value = user.name || '';
                document.getElementById('edit_role').value = user.role || '';
                
                // Bölge ve Kurum Ayarlama
                let region = user.region || '';
                const institution = user.institution || user.branch || ''; // Branch fallback
                
                // Eğer bölge yoksa ama kurum varsa, bölgeyi bulmaya çalış
                if (!region && institution && typeof regionData !== 'undefined') {
                    for (const [reg, branches] of Object.entries(regionData)) {
                        if (branches.includes(institution)) {
                            region = reg;
                            break;
                        }
                    }
                }
                if (!region) region = 'Arlberg'; // Varsayılan

                const regionSelect = document.getElementById('edit_region');
                if (regionSelect) {
                    regionSelect.value = region;
                    // Bölge değişince şubeleri güncelle
                    updateBranchOptions('edit');
                    // Şubeyi seç
                    const instSelect = document.getElementById('edit_institution');
                    if (instSelect) instSelect.value = institution;
                } else {
                    // Fallback for missing region select
                    document.getElementById('edit_institution').innerHTML = `<option value="${institution}">${institution}</option>`;
                    document.getElementById('edit_institution').value = institution;
                }

                document.getElementById('edit_class_section').value = user.class_section || '';
                document.getElementById('edit_class_section').value = user.class_section || '';
                document.getElementById('edit_email').value = user.email || '';
                document.getElementById('edit_phone').value = user.phone || '';
                document.getElementById('edit_new_password').value = '';
                
                // Modal'ı aç
                document.getElementById('editModal').style.display = 'block';
            } else {
                alert('Kullanıcı bulunamadı!');
            }
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function toggleAddUserModal() {
            document.getElementById('addUserModal').style.display = 'block';
        }

        function closeAddUserModal() {
            document.getElementById('addUserModal').style.display = 'none';
            // Form'u temizle
            document.getElementById('addUserForm').reset();
            document.getElementById('add-preview-username').textContent = '...';
        }

        function updateAddUsernamePreview() {
            const firstName = document.getElementById('add_first_name').value.trim();
            const lastName = document.getElementById('add_last_name').value.trim();
            
            if (firstName && lastName) {
                const mapPairs = [ ['Ü','ue'], ['ü','ue'], ['Ö','oe'], ['ö','oe'], ['Ğ','g'], ['ğ','g'], ['Ş','s'], ['ş','s'], ['Ç','c'], ['ç','c'], ['İ','i'], ['I','i'], ['ı','i'] ];
                let lastPart = lastName.length >= 5 ? lastName.substring(0, 5) : lastName;
                let firstPart = firstName.substring(0, 3);
                mapPairs.forEach(([ch, repl]) => { lastPart = lastPart.split(ch).join(repl); firstPart = firstPart.split(ch).join(repl); });
                const username = (lastPart + '.' + firstPart).toLowerCase();
                document.getElementById('add-preview-username').textContent = username;
            } else {
                document.getElementById('add-preview-username').textContent = '...';
            }
        }

        function togglePassword(username) {
            const passwordSpan = document.getElementById('password-' + username);
            const hiddenSpan = document.getElementById('password-hidden-' + username);
            const button = event.target;
            
            if (passwordSpan.style.display === 'none') {
                passwordSpan.style.display = 'inline';
                hiddenSpan.style.display = 'none';
                button.textContent = '🙈';
                button.style.background = '#e3f2fd';
            } else {
                passwordSpan.style.display = 'none';
                hiddenSpan.style.display = 'inline';
                button.textContent = '👁️';
                button.style.background = 'none';
            }
        }


        function toggleDropdown(dropdownId) {
            const dropdown = document.getElementById(dropdownId);
            dropdown.style.display = dropdown.style.display === 'block' ? 'none' : 'block';
        }

        function toggleImportModal() {
            const modal = document.getElementById('importModal');
            modal.style.display = 'block';
        }

        function closeImportModal() {
            const modal = document.getElementById('importModal');
            modal.style.display = 'none';
        }

        // Dropdown dışına tıklayınca kapat
        window.onclick = function(event) {
            if (!event.target.matches('.btn')) {
                const dropdowns = document.getElementsByClassName('dropdown-content');
                for (let i = 0; i < dropdowns.length; i++) {
                    dropdowns[i].style.display = 'none';
                }
            }
            
            const importModal = document.getElementById('importModal');
            const editModal = document.getElementById('editModal');
            const addUserModal = document.getElementById('addUserModal');
            if (event.target === importModal) {
                closeImportModal();
            }
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === addUserModal) {
                closeAddUserModal();
            }
        }

        // Dosya yükleme alanı için drag & drop
        const fileUploadArea = document.querySelector('.file-upload-area');
        const fileInput = document.getElementById('csvFile');

        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileDisplay(files[0]);
            }
        });

        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                updateFileDisplay(e.target.files[0]);
            }
        });

        function updateFileDisplay(file) {
            const uploadText = fileUploadArea.querySelector('.upload-text');
            uploadText.textContent = `Seçilen dosya: ${file.name}`;
            uploadText.style.color = '#27ae60';
        }


        // Sayfa yüklendiğinde filtreleme ve animasyonlar
        document.addEventListener('DOMContentLoaded', function() {
            filterUsers();
            
            // Kartlara animasyon ekle
            const cards = document.querySelectorAll('.card, .stat-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });

            // Kartlara animasyon ekle
            const userCards = document.querySelectorAll('.user-card');
            userCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>
