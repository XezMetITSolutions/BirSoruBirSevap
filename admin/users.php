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

// Kullanıcı ekleme
if ($_POST['action'] ?? '' === 'add_user') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $role = $_POST['role'] ?? 'student';
    $institution = $_POST['institution'] ?? '';
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
            if ($auth->saveUser($username, $password, $role, $fullName, $institution, $class_section, $email, $phone)) {
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
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Kullanıcı Yönetimi - SuperAdmin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            min-height: 100vh;
            color: #1a1a1a;
            position: relative;
            overflow-x: hidden;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 80%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, rgba(120, 219, 255, 0.2) 0%, transparent 50%);
            z-index: -1;
        }

        .container {
            max-width: 1600px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(30px);
            border-radius: 30px;
            padding: 50px;
            margin-bottom: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #068567, #27ae60, #3498db, #f39c12);
        }

        .header h1 {
            color: #1a1a1a;
            margin-bottom: 20px;
            font-size: 3.2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.02em;
        }

        .header p {
            color: #6b7280;
            font-size: 1.3rem;
            font-weight: 600;
            margin: 0;
        }

        .header img {
            transition: all 0.3s ease;
        }

        .header img:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
        }

        .nav-breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .nav-breadcrumb a {
            color: #068567;
            text-decoration: none;
            margin-right: 10px;
        }

        .nav-breadcrumb a:hover {
            text-decoration: underline;
        }

        .content-grid {
            display: block;
        }

        .card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(30px);
            border-radius: 30px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #068567, #27ae60);
        }

        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.2);
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8rem;
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
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #068567;
            box-shadow: 0 0 0 4px rgba(6, 133, 103, 0.15);
            background: white;
            transform: translateY(-2px);
        }

        .btn {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(6, 133, 103, 0.4);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .users-table-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 30px;
            overflow: hidden;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-top: 30px;
            min-height: 800px;
        }

        .table-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 40px;
            font-size: 1.4rem;
            font-weight: 800;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-header h3 {
            margin: 0;
            font-size: 1.6rem;
            font-weight: 800;
        }

        .table-title-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .table-title-section h3 {
            margin: 0;
            font-size: 1.4rem;
        }

        .table-controls {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .table-controls select {
            padding: 8px 12px;
            border: none;
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 0.9rem;
            cursor: pointer;
        }

        .table-controls select option {
            background: white;
            color: #333;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }

        .users-table th {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: #2c3e50;
            font-weight: 700;
            padding: 25px 20px;
            text-align: left;
            border-bottom: 2px solid #dee2e6;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 10;
        }

        .users-table td {
            padding: 25px 20px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }

        .users-table tbody tr {
            transition: all 0.3s ease;
            min-height: 80px;
        }

        .users-table tbody tr:hover {
            background: linear-gradient(90deg, #f8f9ff 0%, #ffffff 100%);
            transform: scale(1.01);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.1);
        }

        .users-table tbody tr:last-child td {
            border-bottom: none;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.1rem;
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.3);
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 700;
            color: #2c3e50;
            font-size: 1rem;
            margin-bottom: 2px;
        }

        .user-username {
            color: #7f8c8d;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .role-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .role-superadmin {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .role-teacher {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
        }

        .role-student {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
            color: white;
        }

        .institution-badge {
            background: linear-gradient(135deg, #e8f4fd 0%, #d1ecf1 100%);
            color: #2980b9;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-block;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(231, 76, 60, 0.3);
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            padding: 25px;
            background: #f8f9fa;
        }

        .pagination button {
            padding: 10px 15px;
            border: 1px solid #dee2e6;
            background: white;
            color: #667eea;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }

        .pagination button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .pagination button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination-info {
            color: #7f8c8d;
            font-size: 0.9rem;
            margin: 0 15px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
            background: white;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: #495057;
        }

        .empty-state p {
            font-size: 1.1rem;
            opacity: 0.8;
        }

        .table-stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .stat-item {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-icon {
            font-size: 1.5rem;
        }

        .stat-text {
            font-weight: 600;
            color: #2c3e50;
        }

        .stat-number {
            font-size: 1.2rem;
            font-weight: 700;
            color: #667eea;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-content {
            display: none;
            position: absolute;
            background: white;
            min-width: 200px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
            border-radius: 10px;
            z-index: 1000;
            top: 100%;
            right: 0;
            margin-top: 5px;
        }

        .dropdown-content a {
            color: #333;
            padding: 12px 16px;
            text-decoration: none;
            display: block;
            transition: background-color 0.3s;
            border-radius: 10px;
            margin: 5px;
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            overflow-y: auto;
        }

        .modal-content {
            background: white;
            margin: 2% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
            position: relative;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
        }

        .modal-title {
            font-size: 1.5em;
            font-weight: 700;
            color: #2c3e50;
        }

        .close {
            font-size: 2em;
            cursor: pointer;
            color: #aaa;
            transition: color 0.3s ease;
        }

        .close:hover {
            color: #000;
        }

        .file-upload-area {
            border: 2px dashed #667eea;
            border-radius: 15px;
            padding: 40px;
            text-align: center;
            margin: 20px 0;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: #5a6fd8;
            background: rgba(102, 126, 234, 0.05);
        }

        .file-upload-area.dragover {
            border-color: #5a6fd8;
            background: rgba(102, 126, 234, 0.1);
        }

        .file-input {
            display: none;
        }

        .upload-icon {
            font-size: 3rem;
            color: #667eea;
            margin-bottom: 15px;
        }

        .upload-text {
            font-size: 1.1rem;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .upload-hint {
            font-size: 0.9rem;
            color: #7f8c8d;
        }

        .csv-format-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #667eea;
        }

        .csv-format-info h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .csv-format-info ul {
            margin: 0;
            padding-left: 20px;
        }

        .csv-format-info li {
            margin-bottom: 5px;
            color: #7f8c8d;
        }

        .btn-warning {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
        }

        .btn-warning:hover {
            box-shadow: 0 5px 15px rgba(243, 156, 18, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
        }

        .btn-success:hover {
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }


        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #efe;
            color: #27ae60;
            border: 1px solid #c3e6cb;
        }

        .alert-success code {
            background: #d4edda;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: 'Courier New', monospace;
            font-size: 0.9em;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.98);
            padding: 30px;
            border-radius: 25px;
            text-align: center;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            color: #667eea;
            margin-bottom: 10px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .stat-card p {
            color: #7f8c8d;
            font-weight: 600;
            font-size: 1.1rem;
        }

        .search-filter-bar {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
            position: relative;
            overflow: hidden;
        }

        .search-filter-bar::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #667eea, #764ba2);
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .search-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            background: white;
        }

        .filter-select {
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            font-size: 1.1rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .filter-select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
            background: white;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            margin-right: 10px;
        }

        .user-info {
            display: flex;
            align-items: center;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 2px;
        }

        .user-username {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .institution-badge {
            background: #e8f4fd;
            color: #2980b9;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 500;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9rem;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }

        .btn-edit:hover {
            box-shadow: 0 8px 25px rgba(52, 152, 219, 0.3);
        }

        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .header h1 {
                font-size: 2rem;
            }

            .header {
                padding: 25px;
            }

            .header div {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }

            .search-filter-bar {
                flex-direction: column;
                gap: 15px;
            }

            .search-input,
            .filter-select {
                min-width: 100%;
            }

            .card {
                padding: 25px;
            }

            .users-table {
                font-size: 0.85rem;
            }

            .users-table th,
            .users-table td {
                padding: 12px 8px;
            }

            .user-info {
                flex-direction: column;
                gap: 8px;
                text-align: center;
            }

            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                gap: 5px;
            }

            .btn-sm {
                padding: 5px 10px;
                font-size: 0.75rem;
            }

            .table-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .table-controls {
                justify-content: center;
            }

            .pagination {
                flex-wrap: wrap;
                gap: 5px;
            }

            .pagination button {
                padding: 8px 12px;
                font-size: 0.8rem;
            }

            .pagination-info {
                margin: 10px 0;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div style="display: flex; align-items: center; gap: 20px;">
                <img src="../logo.png" alt="Bir Soru Bir Sevap Logo" style="height: 60px; width: auto; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.2);">
                <div>
            <h1>👥 Kullanıcı Yönetimi</h1>
            <p>Sistem kullanıcılarını yönetin ve yeni kullanıcılar ekleyin</p>
                </div>
            </div>
        </div>

        <div class="nav-breadcrumb">
            <a href="dashboard.php">Dashboard</a> > Kullanıcı Yönetimi
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <?php echo $success; ?>
        <?php endif; ?>

        <div class="table-stats">
            <div class="stat-item">
                <div class="stat-icon">👥</div>
                <div>
                    <div class="stat-text">Toplam Kullanıcı</div>
                    <div class="stat-number"><?php echo $totalUsers; ?></div>
            </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">👨‍🎓</div>
                <div>
                    <div class="stat-text">Öğrenci</div>
                    <div class="stat-number"><?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'student')); ?></div>
            </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">👨‍🏫</div>
                <div>
                    <div class="stat-text">Eğitmen</div>
                    <div class="stat-number"><?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'teacher')); ?></div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">👑</div>
                <div>
                    <div class="stat-text">SuperAdmin</div>
                    <div class="stat-number"><?php echo count(array_filter($filteredUsers, fn($u) => $u['role'] === 'superadmin')); ?></div>
                </div>
            </div>
            <div class="stat-item">
                <div class="stat-icon">📄</div>
                <div>
                    <div class="stat-text">Sayfa</div>
                    <div class="stat-number"><?php echo $currentPage; ?> / <?php echo $totalPages; ?></div>
                </div>
            </div>
        </div>

        <div class="search-filter-bar">
            <form method="GET" style="display: flex; gap: 15px; align-items: center; flex: 1;">
                <input type="text" name="search" class="search-input" placeholder="🔍 Kullanıcı ara..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                <select name="role" class="filter-select" onchange="this.form.submit()">
                <option value="">Tüm Roller</option>
                    <option value="student" <?php echo $roleFilter === 'student' ? 'selected' : ''; ?>>Öğrenci</option>
                    <option value="teacher" <?php echo $roleFilter === 'teacher' ? 'selected' : ''; ?>>Eğitmen</option>
                    <option value="superadmin" <?php echo $roleFilter === 'superadmin' ? 'selected' : ''; ?>>SuperAdmin</option>
            </select>
                <select name="institution" class="filter-select" onchange="this.form.submit()">
                <option value="">Tüm Kurumlar</option>
                <?php foreach ($institutions as $institution): ?>
                        <option value="<?php echo htmlspecialchars($institution); ?>" <?php echo $institutionFilter === $institution ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($institution); ?>
                    </option>
                <?php endforeach; ?>
            </select>
                <button type="submit" class="btn" style="padding: 12px 20px;">🔍 Ara</button>
                <?php if ($searchTerm || $roleFilter || $institutionFilter): ?>
                    <a href="users.php" class="btn btn-secondary" style="padding: 12px 20px;">❌ Temizle</a>
                <?php endif; ?>
            </form>
            
            <div style="display: flex; gap: 10px; align-items: center;">
                <div class="dropdown">
                    <button class="btn btn-success" onclick="toggleDropdown('exportDropdown')" style="padding: 12px 20px;">
                        📤 Export
                    </button>
                    <div id="exportDropdown" class="dropdown-content">
                        <a href="?action=export_csv&type=all">📊 Tüm Kullanıcılar (Excel)</a>
                        <a href="?action=export_csv&type=students">👨‍🎓 Sadece Öğrenciler (Excel)</a>
                        <a href="?action=export_csv&type=teachers">👨‍🏫 Sadece Eğitmenler (Excel)</a>
                    </div>
                    </div>
                    
                <button class="btn btn-warning" onclick="toggleImportModal()" style="padding: 12px 20px;">
                    📥 Import
                </button>
                
                <a href="template.csv" class="btn btn-secondary" style="padding: 12px 20px;" download>
                    📋 Template
                </a>
                
                <a href="../update_passwords.php" class="btn btn-warning" style="padding: 12px 20px;" 
                   onclick="return confirm('Mevcut tüm kullanıcıların şifrelerini iqra2025# olarak güncellemek istediğinizden emin misiniz? (Superadmin hariç)')">
                    🔐 Şifreleri Güncelle
                </a>
            </div>
                    </div>

        <div class="content-grid">
            <div class="users-table-container">
                <div class="table-header">
                    <div class="table-title-section">
                        <h3>📋 Kullanıcı Listesi</h3>
                        <button class="btn btn-primary" onclick="toggleAddUserModal()">
                            ➕ Yeni Kullanıcı Ekle
                        </button>
                    </div>
                    <div class="table-controls">
                        <select onchange="changeItemsPerPage(this.value)">
                            <option value="25" <?php echo $itemsPerPage == 25 ? 'selected' : ''; ?>>25 / sayfa</option>
                            <option value="50" <?php echo $itemsPerPage == 50 ? 'selected' : ''; ?>>50 / sayfa</option>
                            <option value="100" <?php echo $itemsPerPage == 100 ? 'selected' : ''; ?>>100 / sayfa</option>
                            <option value="200" <?php echo $itemsPerPage == 200 ? 'selected' : ''; ?>>200 / sayfa</option>
                            <option value="500" <?php echo $itemsPerPage == 500 ? 'selected' : ''; ?>>500 / sayfa</option>
                        </select>
                    </div>
            </div>

                <?php if (empty($users)): ?>
                    <div class="empty-state">
                        <i>👥</i>
                        <h3>Kullanıcı bulunamadı</h3>
                        <p>Arama kriterlerinizi değiştirerek tekrar deneyin</p>
                    </div>
                <?php else: ?>
                    <table class="users-table">
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
                                <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'] ?? 'U', 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="user-name"><?php echo htmlspecialchars($user['name'] ?? 'Bilinmiyor'); ?></div>
                                            <div class="user-username">@<?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="role-badge role-<?php echo $user['role']; ?>">
                                        <?php 
                                        $roleIcons = [
                                            'student' => '👨‍🎓',
                                            'teacher' => '👨‍🏫', 
                                            'superadmin' => '👑'
                                        ];
                                        echo $roleIcons[$user['role']] . ' ' . ucfirst($user['role']);
                                        ?>
                                    </span>
                                </td>
                                <td>
                                        <span class="institution-badge">
                                        <?php echo htmlspecialchars($user['institution']); ?>
                                        </span>
                                    <?php if (!empty($user['class_section'])): ?>
                                        <div style="font-size: 0.8rem; color: #7f8c8d; margin-top: 4px;">
                                            📚 <?php echo htmlspecialchars($user['class_section']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (!empty($user['email'])): ?>
                                        <div style="font-size: 0.9rem; margin-bottom: 2px;">
                                            📧 <?php echo htmlspecialchars($user['email']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($user['phone'])): ?>
                                        <div style="font-size: 0.9rem;">
                                            📱 <?php echo htmlspecialchars($user['phone']); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div style="font-size: 0.9rem;">
                                        📅 <?php echo date('d.m.Y', strtotime($user['created_at'])); ?>
                                    </div>
                                    <div style="font-size: 0.8rem; color: #7f8c8d;">
                                        🕒 <?php echo date('H:i', strtotime($user['created_at'])); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn btn-edit btn-sm" onclick="editUser('<?php echo htmlspecialchars($user['username']); ?>')">
                                            ✏️ Düzenle
                                        </button>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="delete_user">
                                        <input type="hidden" name="username" value="<?php echo htmlspecialchars($user['username']); ?>">
                                            <button type="submit" class="btn btn-danger btn-sm" 
                                                onclick="return confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')">
                                                🗑️ Sil
                                        </button>
                                    </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                    
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
                    <label for="add_institution">Kurum *</label>
                    <select id="add_institution" name="institution" required>
                        <option value="">Kurum Seçin</option>
                        <?php foreach ($institutions as $institution): ?>
                            <option value="<?php echo htmlspecialchars($institution); ?>">
                                <?php echo htmlspecialchars($institution); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
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
                    <label for="edit_institution">Kurum *</label>
                    <select id="edit_institution" name="institution" required>
                        <?php foreach ($institutions as $institution): ?>
                            <option value="<?php echo htmlspecialchars($institution); ?>">
                                <?php echo htmlspecialchars($institution); ?>
                            </option>
                        <?php endforeach; ?>
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
                document.getElementById('edit_institution').value = user.institution || '';
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
