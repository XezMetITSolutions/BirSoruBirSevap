<?php
/**
 * SuperAdmin - Kullanıcı Yönetimi
 * Tamamen Yeniden Tasarlanmış Modern Arayüz
 */

require_once '../auth.php';
require_once '../config.php';
require_once 'includes/locations.php';

$auth = Auth::getInstance();

// Yetki Kontrolü
if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

// === İŞLEM MANTIĞI (PHP LOGIC) ===

// 1. Yardımcı Fonksiyonlar
function generateRandomPassword($length = 8) {
    return substr(str_shuffle('abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'), 0, $length);
}

function normalizeUsername($firstName, $lastName) {
    $mapSearch = ['Ü','ü','Ö','ö','Ğ','ğ','Ş','ş','Ç','ç','İ','I','ı'];
    $mapReplace = ['ue','ue','oe','oe','g','g','s','s','c','c','i','i','i'];
    
    $lName = strlen($lastName) >= 5 ? substr($lastName, 0, 5) : $lastName;
    $fName = substr($firstName, 0, 3);
    
    $lName = str_replace($mapSearch, $mapReplace, $lName);
    $fName = str_replace($mapSearch, $mapReplace, $fName);
    
    return strtolower($lName . '.' . $fName);
}

// 2. Form İşlemleri Handle Et
$message = '';
$messageType = ''; // success, error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    // --- KULLANICI EKLEME ---
    if ($action === 'add_user') {
        try {
            $fname = trim($_POST['first_name'] ?? '');
            $lname = trim($_POST['last_name'] ?? '');
            $role = $_POST['role'] ?? 'student';
            $institution = $_POST['institution'] ?? '';
            $region = $_POST['region'] ?? '';
            
            // Bölge otomatik bulma
            if (empty($region) && !empty($institution)) {
                $region = getRegionByBranch($institution) ?? 'Arlberg';
            }
            
            if (empty($fname) || empty($lname)) throw new Exception("Ad ve Soyad zorunludur.");
            if ($role !== 'region_leader' && empty($institution)) throw new Exception("Kurum seçimi zorunludur.");

            $baseUsername = normalizeUsername($fname, $lname);
            $username = $baseUsername;
            $counter = 1;
            $existingUsers = $auth->getAllUsers();
            
            while (isset($existingUsers[$username])) {
                $username = $baseUsername . $counter++;
            }
            
            $password = 'iqra2025#';
            $fullName = $fname . ' ' . $lname;
            
            $res = $auth->saveUser(
                $username, 
                $password, 
                $role, 
                $fullName, 
                $institution, 
                $_POST['class_section'] ?? '', 
                $_POST['email'] ?? '', 
                $_POST['phone'] ?? '', 
                $region
            );
            
            if ($res) {
                $message = "Kullanıcı başarıyla oluşturuldu. <br>👤: <b>$username</b> <br>🔑: <b>$password</b>";
                $messageType = 'success';
            } else {
                throw new Exception("Kullanıcı kaydedilemedi.");
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // --- KULLANICI DÜZENLEME ---
    elseif ($action === 'edit_user') {
        try {
            $username = $_POST['username'] ?? '';
            $allUsers = $auth->getAllUsers();
            
            if (!isset($allUsers[$username])) throw new Exception("Kullanıcı bulunamadı.");
            
            $userData = $allUsers[$username];
            $password = !empty($_POST['new_password']) ? $_POST['new_password'] : $userData['password'];
            
            $institution = $_POST['institution'] ?? '';
            $region = $_POST['region'] ?? ($userData['region'] ?? '');
            if (empty($region) && !empty($institution)) {
                $region = getRegionByBranch($institution) ?? '';
            }

            $res = $auth->saveUser(
                $username,
                $password,
                $_POST['role'] ?? $userData['role'],
                $_POST['name'] ?? $userData['name'],
                $institution,
                $_POST['class_section'] ?? '',
                $_POST['email'] ?? '',
                $_POST['phone'] ?? '',
                $region
            );
            
            if ($res) {
                $message = "Kullanıcı başarıyla güncellendi.";
                $messageType = 'success';
            } else {
                throw new Exception("Güncelleme başarısız.");
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
    
    // --- KULLANICI SİLME ---
    elseif ($action === 'delete_user') {
        $u = $_POST['username'] ?? '';
        if ($auth->deleteUser($u)) {
            $message = "Kullanıcı silindi.";
            $messageType = 'success';
        } else {
            $message = "Silme işlemi başarısız.";
            $messageType = 'error';
        }
    }
}

// 3. Verileri Getirme ve Filtreleme
$search = trim($_GET['search'] ?? '');
$fRole = $_GET['role'] ?? '';
$fRegion = $_GET['region'] ?? '';
$fInst = $_GET['institution'] ?? '';

$allUsers = $auth->getAllUsers();
$users = []; // Filtrelenmiş liste

// İstatistikler için sayaçlar
$stats = ['total' => 0, 'student' => 0, 'teacher' => 0, 'admin' => 0];

foreach ($allUsers as $uName => $uData) {
    // İstatistik (filtre öncesi genel toplam)
    $stats['total']++;
    if (($uData['role'] ?? '') === 'student') $stats['student']++;
    if (($uData['role'] ?? '') === 'teacher') $stats['teacher']++;
    if (in_array($uData['role'] ?? '', ['admin', 'superadmin'])) $stats['admin']++;
    
    // Filtreleme
    if ($search) {
        $term = mb_strtolower($search);
        if (
            strpos(mb_strtolower($uName), $term) === false &&
            strpos(mb_strtolower($uData['name'] ?? ''), $term) === false
        ) continue;
    }
    
    if ($fRole && ($uData['role'] ?? '') !== $fRole) continue;
    if ($fRegion && ($uData['region'] ?? '') !== $fRegion) continue;
    
    // Kurum filtresi - biraz esnek
    $uInst = $uData['branch'] ?? $uData['institution'] ?? '';
    if ($fInst && $uInst !== $fInst) continue;
    
    $users[$uName] = $uData;
    $users[$uName]['username'] = $uName; // Array içine ekleyelim kullanım kolaylığı için
}

// Sayfalama
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 20;
$totalItems = count($users);
$totalPages = ceil($totalItems / $perPage);
$offset = ($page - 1) * $perPage;
$displayUsers = array_slice($users, $offset, $perPage);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi | SoruSevap</title>
    
    <!-- Fonts & Icons -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Ana CSS -->
    <link rel="stylesheet" href="css/admin-style.css">
    
    <style>
        /* Bu Sayfaya Özel Gelişmiş Tasarım Override'ları - Inline değil, scoped logic için buradalar */
        :root {
            --glass-bg: rgba(255, 255, 255, 0.03);
            --glass-border: rgba(255, 255, 255, 0.08);
            --glass-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.36);
            --accent-color: #068567;
            --text-main: #ffffff;
            --text-secondary: #94a3b8;
        }

        body {
            font-family: 'Outfit', sans-serif; /* Daha modern bir font */
        }

        /* Ambient background enhancements */
        .bg-glow {
            position: fixed;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(6,133,103,0.15) 0%, rgba(0,0,0,0) 70%);
            top: -100px;
            left: -100px;
            z-index: -1;
            pointer-events: none;
        }

        /* Layout */
        .page-grid {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* Stats Row */
        .modern-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }

        .m-stat-card {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 24px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, border-color 0.3s ease;
        }

        .m-stat-card:hover {
            transform: translateY(-5px);
            border-color: rgba(255,255,255,0.2);
        }

        .m-stat-info h3 {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0 0 8px 0;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .m-stat-info .value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1;
        }

        .m-stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: rgba(255,255,255,0.05);
            color: var(--accent-color);
        }

        /* Main Content Area */
        .glass-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 24px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Toolbar/Filter Bar */
        .toolbar {
            padding: 20px 24px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            flex-wrap: wrap;
            gap: 16px;
            align-items: center;
            background: rgba(0,0,0,0.2);
        }

        .search-box {
            position: relative;
            flex: 1;
            min-width: 250px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
        }

        .search-box input {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            padding: 12px 12px 12px 40px;
            border-radius: 12px;
            color: white;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s;
        }

        .search-box input:focus {
            background: rgba(255,255,255,0.1);
            border-color: var(--accent-color);
            outline: none;
            box-shadow: 0 0 0 2px rgba(6,133,103,0.2);
        }

        .filter-select {
            background: rgba(0,0,0,0.3);
            border: 1px solid var(--glass-border);
            color: white;
            padding: 12px 36px 12px 16px;
            border-radius: 12px;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='white'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 16px;
        }
        
        .filter-select option { background: #0f172a; }

        /* Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            text-align: left;
            padding: 16px 24px;
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 1px solid var(--glass-border);
        }

        .data-table td {
            padding: 16px 24px;
            color: var(--text-main);
            border-bottom: 1px solid rgba(255,255,255,0.03);
            vertical-align: middle;
        }

        .data-table tr:last-child td { border-bottom: none; }
        .data-table tr:hover td { background: rgba(255,255,255,0.02); }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .avatar-circle {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #068567, #0f4c3e);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: white;
            font-size: 16px;
        }
        
        .user-meta h4 { margin: 0; font-size: 15px; font-weight: 500; }
        .user-meta span { font-size: 12px; color: var(--text-secondary); }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .bg-role-student { background: rgba(59,130,246,0.15); color: #60a5fa; }
        .bg-role-teacher { background: rgba(245,158,11,0.15); color: #fbbf24; }
        .bg-role-admin { background: rgba(239,68,68,0.15); color: #fca5a5; }
        
        /* Actions */
        .action-btn-group {
            display: flex;
            gap: 8px;
        }
        
        .icon-btn {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--glass-border);
            background: transparent;
            color: var(--text-secondary);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .icon-btn:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-color: rgba(255,255,255,0.2);
        }
        
        .icon-btn.danger:hover {
            background: rgba(239,68,68,0.2);
            color: #fca5a5;
            border-color: rgba(239,68,68,0.4);
        }

        /* Improved Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .modal-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        .modal-box {
            background: #111827;
            width: 100%;
            max-width: 550px;
            border-radius: 24px;
            border: 1px solid var(--glass-border);
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
            transform: translateY(20px) scale(0.95);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            display: flex;
            flex-direction: column;
            max-height: 90vh;
        }
        
        .modal-overlay.active .modal-box {
            transform: translateY(0) scale(1);
        }
        
        .modal-header {
            padding: 24px;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 { margin: 0; font-size: 20px; color: white; }
        
        .modal-body {
            padding: 24px;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 20px 24px;
            border-top: 1px solid var(--glass-border);
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            background: rgba(0,0,0,0.2);
            border-radius: 0 0 24px 24px;
        }

        /* Form Elements inside Modal */
        .form-grid {
            display: grid;
            gap: 16px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .input-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-secondary);
            font-size: 13px;
        }
        
        .input-group input, 
        .input-group select {
            width: 100%;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--glass-border);
            padding: 10px 14px;
            border-radius: 10px;
            color: white;
        }
        
        .primary-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .primary-btn:hover { transform: scale(1.02); }

        .secondary-btn {
            background: transparent;
            border: 1px solid var(--glass-border);
            color: var(--text-secondary);
            padding: 10px 20px;
            border-radius: 10px;
            cursor: pointer;
        }
        
        .secondary-btn:hover { background: rgba(255,255,255,0.05); color: white; }
        
        /* Alert Message */
        .alert-float {
            position: fixed;
            top: 30px;
            right: 30px;
            padding: 16px 24px;
            border-radius: 12px;
            color: white;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .alert-success { background: rgba(16, 185, 129, 0.9); backdrop-filter: blur(10px); }
        .alert-error { background: rgba(239, 68, 68, 0.9); backdrop-filter: blur(10px); }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Pagination */
        .pagination {
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid var(--glass-border);
        }

        .page-link {
            padding: 8px 12px;
            border-radius: 8px;
            background: rgba(255,255,255,0.05);
            color: white;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.2s;
        }

        .page-link:hover, .page-link.active {
            background: var(--accent-color);
        }

    </style>
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>
    
    <div class="bg-glow"></div>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- Bildirim Mesajı -->
        <?php if (!empty($message)): ?>
        <div class="alert-float alert-<?php echo $messageType; ?>" id="mainAlert">
             <i class="fas <?php echo $messageType == 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?>"></i>
             <div><?php echo $message; ?></div>
             <button onclick="document.getElementById('mainAlert').style.display='none'" style="background:none; border:none; color:white; margin-left:10px; cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <script>setTimeout(() => document.getElementById('mainAlert').classList.add('hide-anim'), 5000);</script>
        <?php endif; ?>

        <div class="page-grid">
            <!-- Header -->
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <h1 style="font-size: 28px; font-weight:700; margin:0; background: linear-gradient(to right, #fff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent;">Kullanıcı Yönetimi</h1>
                    <p style="color:var(--text-secondary); margin:4px 0 0 0;">Toplam <?php echo $stats['total']; ?> kayıtlı kullanıcı</p>
                </div>
                <div>
                     <button onclick="openModal('addModal')" class="primary-btn" style="padding: 12px 24px; font-size:14px; box-shadow: 0 4px 14px rgba(6,133,103,0.4);">
                        <i class="fas fa-plus" style="margin-right: 8px;"></i> Yeni Kullanıcı
                    </button>
                </div>
            </div>

            <!-- Stats -->
            <div class="modern-stats">
                <div class="m-stat-card">
                    <div class="m-stat-info">
                        <h3>Toplam Kullanıcı</h3>
                        <div class="value"><?php echo $stats['total']; ?></div>
                    </div>
                    <div class="m-stat-icon"><i class="fas fa-users"></i></div>
                </div>
                <div class="m-stat-card">
                    <div class="m-stat-info">
                        <h3>Öğrenciler</h3>
                        <div class="value"><?php echo $stats['student']; ?></div>
                    </div>
                    <div class="m-stat-icon" style="color: #60a5fa;"><i class="fas fa-user-graduate"></i></div>
                </div>
                <div class="m-stat-card">
                    <div class="m-stat-info">
                        <h3>Eğitmenler</h3>
                        <div class="value"><?php echo $stats['teacher']; ?></div>
                    </div>
                    <div class="m-stat-icon" style="color: #fbbf24;"><i class="fas fa-chalkboard-teacher"></i></div>
                </div>
                <div class="m-stat-card">
                    <div class="m-stat-info">
                        <h3>Yöneticiler</h3>
                        <div class="value"><?php echo $stats['admin']; ?></div>
                    </div>
                    <div class="m-stat-icon" style="color: #fca5a5;"><i class="fas fa-shield-alt"></i></div>
                </div>
            </div>

            <!-- Table Section -->
            <div class="glass-container">
                <!-- Toolbar -->
                <form method="GET" class="toolbar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="İsim veya kullanıcı adı ara..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <select name="role" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tüm Roller</option>
                        <option value="student" <?php if($fRole == 'student') echo 'selected'; ?>>Öğrenci</option>
                        <option value="teacher" <?php if($fRole == 'teacher') echo 'selected'; ?>>Eğitmen</option>
                        <option value="branch_leader" <?php if($fRole == 'branch_leader') echo 'selected'; ?>>Şube Müdürü</option>
                        <option value="region_leader" <?php if($fRole == 'region_leader') echo 'selected'; ?>>Bölge Müdürü</option>
                        <option value="superadmin" <?php if($fRole == 'superadmin') echo 'selected'; ?>>Admin</option>
                    </select>

                    <select name="region" class="filter-select" onchange="this.form.submit()">
                        <option value="">Tüm Bölgeler</option>
                        <?php foreach ($regionConfig as $reg => $branches): ?>
                            <option value="<?php echo htmlspecialchars($reg); ?>" <?php if($fRegion == $reg) echo 'selected'; ?>><?php echo htmlspecialchars($reg); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <?php if(!empty($search) || !empty($fRole) || !empty($fRegion)): ?>
                        <a href="users.php" class="secondary-btn" style="text-decoration:none;"><i class="fas fa-times"></i> Temizle</a>
                    <?php endif; ?>
                </form>

                <!-- Data Table -->
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>Rol & Yetki</th>
                                <th>Lokasyon</th>
                                <th>İletişim</th>
                                <th style="text-align:right;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($displayUsers) > 0): ?>
                                <?php foreach ($displayUsers as $uKey => $u): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="avatar-circle">
                                                <?php echo strtoupper(substr($u['name'] ?? 'U', 0, 1) . substr(explode(' ', $u['name'] ?? '')[1] ?? '', 0, 1)); ?>
                                            </div>
                                            <div class="user-meta">
                                                <h4><?php echo htmlspecialchars($u['name'] ?? 'İsimsiz'); ?></h4>
                                                <span>@<?php echo htmlspecialchars($u['username']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php 
                                            $rClass = 'bg-role-student';
                                            $rName = 'Öğrenci';
                                            if($u['role'] == 'teacher') { $rClass = 'bg-role-teacher'; $rName = 'Eğitmen'; }
                                            if(strpos($u['role'], 'admin') !== false) { $rClass = 'bg-role-admin'; $rName = 'Yönetici'; }
                                            if(strpos($u['role'], 'leader') !== false) { $rClass = 'bg-role-admin'; $rName = 'Lider'; }
                                        ?>
                                        <span class="badge <?php echo $rClass; ?>"><?php echo $rName; ?></span>
                                    </td>
                                    <td>
                                        <div style="color: white; font-size:14px;"><?php echo htmlspecialchars($u['institution'] ?? $u['branch'] ?? '-'); ?></div>
                                        <small style="color: var(--text-secondary);"><?php echo htmlspecialchars($u['region'] ?? ''); ?></small>
                                    </td>
                                    <td>
                                        <div style="font-size:13px; color:var(--text-secondary);">
                                            <?php if(!empty($u['email'])): ?><i class="fas fa-envelope" style="width:16px;"></i> <?php echo htmlspecialchars($u['email']); ?><br><?php endif; ?>
                                            <?php if(!empty($u['phone'])): ?><i class="fas fa-phone" style="width:16px;"></i> <?php echo htmlspecialchars($u['phone']); ?><?php endif; ?>
                                        </div>
                                    </td>
                                    <td style="text-align:right;">
                                        <div class="action-btn-group" style="justify-content: flex-end;">
                                            <button onclick='editUser(<?php echo json_encode($u); ?>)' class="icon-btn" title="Düzenle"><i class="fas fa-pen"></i></button>
                                            
                                            <form method="POST" onsubmit="return confirm('Silmek istediğine emin misin?');" style="margin:0;">
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                                <button type="submit" class="icon-btn danger" title="Sil"><i class="fas fa-trash"></i></button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding: 40px; color: var(--text-secondary);">
                                        <i class="fas fa-search" style="font-size: 32px; margin-bottom: 12px; opacity:0.5;"></i><br>
                                        Kayıt bulunamadı.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <span style="color: var(--text-secondary); font-size: 13px;">Sayfa <?php echo $page; ?> / <?php echo $totalPages; ?></span>
                    <div style="display:flex; gap:8px;">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page-1; ?>" class="page-link"><i class="fas fa-chevron-left"></i></a>
                        <?php endif; ?>
                        <?php for($i=max(1, $page-2); $i<=min($totalPages, $page+2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="page-link <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                        <?php endfor; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page+1; ?>" class="page-link"><i class="fas fa-chevron-right"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- MODAL: ADD USER -->
    <div id="addModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Yeni Kullanıcı Ekle</h2>
                <button onclick="closeModal('addModal')" class="icon-btn"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body form-grid">
                    <div class="form-row">
                        <div class="input-group">
                            <label>Ad</label>
                            <input type="text" name="first_name" required placeholder="Örn: Ahmet">
                        </div>
                        <div class="input-group">
                            <label>Soyad</label>
                            <input type="text" name="last_name" required placeholder="Örn: Yılmaz">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>Rol</label>
                        <select name="role" required onchange="toggleInstitution(this.value, 'add')">
                            <option value="student">Öğrenci</option>
                            <option value="teacher">Eğitmen</option>
                            <option value="branch_leader">Şube Müdürü</option>
                            <option value="region_leader">Bölge Müdürü</option>
                            <option value="superadmin">Admin</option>
                        </select>
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>Bölge</label>
                            <select name="region" id="add_region" onchange="updateBranches('add')">
                                <option value="">Seçiniz...</option>
                                <?php foreach ($regionConfig as $reg => $branches): ?>
                                    <option value="<?php echo htmlspecialchars($reg); ?>"><?php echo htmlspecialchars($reg); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group" id="grupo_add_inst">
                            <label>Kurum</label>
                            <select name="institution" id="add_institution">
                                <option value="">Önce Bölge Seçin</option>
                            </select>
                        </div>
                    </div>

                    <div class="input-group">
                        <label>Sınıf (Opsiyonel)</label>
                        <input type="text" name="class_section" placeholder="örn. 9A">
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Email</label>
                            <input type="email" name="email">
                        </div>
                        <div class="input-group">
                            <label>Telefon</label>
                            <input type="text" name="phone">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addModal')" class="secondary-btn">İptal</button>
                    <button type="submit" class="primary-btn">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- MODAL: EDIT USER -->
    <div id="editModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header">
                <h2>Kullanıcı Düzenle</h2>
                <button onclick="closeModal('editModal')" class="icon-btn"><i class="fas fa-times"></i></button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="username" id="edit_username_field">
                
                <div class="modal-body form-grid">
                    <div class="input-group">
                        <label>Ad Soyad</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>

                    <div class="input-group">
                        <label>Rol</label>
                        <select name="role" id="edit_role" required>
                            <option value="student">Öğrenci</option>
                            <option value="teacher">Eğitmen</option>
                            <option value="branch_leader">Şube Müdürü</option>
                            <option value="region_leader">Bölge Müdürü</option>
                            <option value="superadmin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-row">
                        <div class="input-group">
                            <label>Bölge</label>
                            <select name="region" id="edit_region" onchange="updateBranches('edit')">
                                <?php foreach ($regionConfig as $reg => $branches): ?>
                                    <option value="<?php echo htmlspecialchars($reg); ?>"><?php echo htmlspecialchars($reg); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="input-group">
                            <label>Kurum</label>
                            <select name="institution" id="edit_institution">
                                <!-- JS ile dolacak -->
                            </select>
                        </div>
                    </div>

                     <div class="input-group">
                        <label>Sınıf</label>
                        <input type="text" name="class_section" id="edit_class_section">
                    </div>

                    <div class="form-row">
                        <div class="input-group">
                            <label>Email</label>
                            <input type="email" name="email" id="edit_email">
                        </div>
                        <div class="input-group">
                            <label>Telefon</label>
                            <input type="text" name="phone" id="edit_phone">
                        </div>
                    </div>
                    
                    <div class="input-group">
                        <label>Şifre (Değiştirmek için doldurun)</label>
                        <input type="text" name="new_password" placeholder="Mevcut şifreyi korumak için boş bırakın.">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')" class="secondary-btn">İptal</button>
                    <button type="submit" class="primary-btn">Değişiklikleri Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JAVASCRIPT LOGIC -->
    <script>
        const regions = <?php echo json_encode($regionConfig); ?>;

        function openModal(id) {
            document.getElementById(id).classList.add('active');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function updateBranches(prefix) {
            const regionSelect = document.getElementById(prefix + '_region');
            const instSelect = document.getElementById(prefix + '_institution');
            const selectedRegion = regionSelect.value;
            
            instSelect.innerHTML = '<option value="">Seçiniz...</option>';
            
            if (selectedRegion && regions[selectedRegion]) {
                regions[selectedRegion].forEach(branch => {
                    const opt = document.createElement('option');
                    opt.value = branch;
                    opt.textContent = branch;
                    instSelect.appendChild(opt);
                });
            }
        }

        function toggleInstitution(role, prefix) {
            // İsteğe bağlı mantık eklenebilir, şimdilik basit bırakıldı.
        }

        function editUser(user) {
            document.getElementById('edit_username_field').value = user.username;
            document.getElementById('edit_name').value = user.name || user.full_name || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_class_section').value = user.class_section || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            
            // Bölge & Kurum
            let region = user.region || '';
            const inst = user.institution || user.branch || '';
            
            // Bölge otomatik bulma (veride yoksa)
            if (!region && inst) {
                for(let r in regions) {
                    if (regions[r].includes(inst)) { region = r; break; }
                }
            }
            if(!region) region = 'Arlberg'; // Fallback
            
            const rSelect = document.getElementById('edit_region');
            if(rSelect) {
                rSelect.value = region;
                updateBranches('edit'); // Şube listesini güncelle
                
                // Şubeyi seç (async bekleme olmadan, hemen update sonrası)
                setTimeout(() => {
                    const iSelect = document.getElementById('edit_institution');
                    if(iSelect) iSelect.value = inst;
                }, 10);
            }
            
            openModal('editModal');
        }

        // Dışarı tıklayınca modal kapatma
        window.onclick = function(event) {
            if (event.target.classList.contains('modal-overlay')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>
