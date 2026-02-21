<?php
/**
 * SuperAdmin - Kullanıcı Yönetimi
 * Minimal & Temiz Tasarım
 */

require_once '../auth.php';
require_once '../config.php';
require_once 'includes/locations.php';

$auth = Auth::getInstance();

if (!$auth->isLoggedIn() || !$auth->hasRole('admin')) {
    header('Location: ../login.php');
    exit;
}

$message = '';
$messageType = '';

// Kullanıcı adı normalize
function normalizeUsername($firstName, $lastName) {
    $map = ['Ü'=>'ue','ü'=>'ue','Ö'=>'oe','ö'=>'oe','Ğ'=>'g','ğ'=>'g','Ş'=>'s','ş'=>'s','Ç'=>'c','ç'=>'c','İ'=>'i','I'=>'i','ı'=>'i'];
    // Soyadından ilk 5 harf, addan ilk 3 harf
    $lName = mb_substr($lastName, 0, 5);
    $fName = mb_substr($firstName, 0, 3);
    return strtolower(str_replace(array_keys($map), array_values($map), $lName . '.' . $fName));
}

// POST İşlemleri
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_user') {
        try {
            $fname = trim($_POST['first_name'] ?? '');
            $lname = trim($_POST['last_name'] ?? '');
            $role = $_POST['role'] ?? 'student';
            $institution = $_POST['institution'] ?? '';
            $region = $_POST['region'] ?? '';
            
            if (empty($region) && !empty($institution)) {
                $region = getRegionByBranch($institution) ?? 'Arlberg';
            }
            
            if (empty($fname) || empty($lname)) throw new Exception("Ad ve Soyad zorunlu.");
            if ($role !== 'region_leader' && empty($institution)) throw new Exception("Kurum seçimi zorunlu.");

            $baseUsername = normalizeUsername($fname, $lname);
            $username = $baseUsername;
            $counter = 1;
            $existingUsers = $auth->getAllUsers();
            
            while (isset($existingUsers[$username])) {
                $username = $baseUsername . $counter++;
            }
            
            $password = 'iqra2025#';
            $fullName = $fname . ' ' . $lname;
            
            if ($auth->saveUser($username, $password, $role, $fullName, $institution, 
                $_POST['class_section'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '', $region)) {
                $message = "✓ Kullanıcı oluşturuldu: <strong>$username</strong> / <strong>$password</strong>";
                $messageType = 'success';
            } else {
                throw new Exception("Kayıt başarısız.");
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
    
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

            if ($auth->saveUser($username, $password, $_POST['role'] ?? $userData['role'], 
                $_POST['name'] ?? $userData['name'], $institution, 
                $_POST['class_section'] ?? '', $_POST['email'] ?? '', $_POST['phone'] ?? '', $region)) {
                $message = "✓ Kullanıcı güncellendi.";
                $messageType = 'success';
            } else {
                throw new Exception("Güncelleme başarısız.");
            }
        } catch (Exception $e) {
            $message = $e->getMessage();
            $messageType = 'error';
        }
    }
    
    elseif ($action === 'delete_user') {
        $u = $_POST['username'] ?? '';
        if ($auth->deleteUser($u)) {
            $message = "✓ Kullanıcı silindi.";
            $messageType = 'success';
        } else {
            $message = "Silme başarısız.";
            $messageType = 'error';
        }
    }
}

// Filtreleme
$search = trim($_GET['search'] ?? '');
$fRole = $_GET['role'] ?? '';
$fRegion = $_GET['region'] ?? '';
$fInst = $_GET['institution'] ?? '';

$allUsers = $auth->getAllUsers();
$users = [];

foreach ($allUsers as $uName => $uData) {
    if ($search) {
        $term = mb_strtolower($search);
        $uFullName = mb_strtolower($uData['full_name'] ?? $uData['name'] ?? '');
        $uUsername = mb_strtolower($uName);
        
        if (strpos($uUsername, $term) === false &&
            strpos($uFullName, $term) === false) continue;
    }
    
    if ($fRole && ($uData['role'] ?? '') !== $fRole) continue;
    if ($fRegion && ($uData['region'] ?? '') !== $fRegion) continue;
    
    $uInst = $uData['branch'] ?? $uData['institution'] ?? '';
    if ($fInst && $uInst !== $fInst) continue;
    
    $users[$uName] = $uData;
    $users[$uName]['username'] = $uName;
}

// Sayfalama
$page = max(1, intval($_GET['page'] ?? 1));
$perPage = 25;
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
    <title>Kullanıcı Yönetimi</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="css/dark-theme.css">
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
        }

        .main-wrapper {
            margin-left: 280px;
            padding: 40px;
            max-width: 1400px;
        }

        /* Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: white;
        }

        .page-subtitle {
            font-size: 14px;
            color: #94a3b8;
            margin-top: 4px;
        }

        .btn-primary {
            background: #068567;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary:hover {
            background: #055a4a;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(6, 133, 103, 0.3);
        }

        /* Container */
        .content-box {
            background: rgba(30, 41, 59, 0.4);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.06);
            border-radius: 16px;
            overflow: hidden;
        }

        /* Toolbar */
        .toolbar {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            align-items: center;
        }

        .search-input {
            flex: 1;
            min-width: 250px;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 10px 14px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }

        .search-input:focus {
            outline: none;
            border-color: #068567;
            background: rgba(15, 23, 42, 0.8);
        }

        select.filter {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            color: white;
            padding: 10px 14px;
            border-radius: 8px;
            cursor: pointer;
        }

        select.filter:focus {
            outline: none;
            border-color: #068567;
        }

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: rgba(15, 23, 42, 0.4);
        }

        th {
            text-align: left;
            padding: 14px 20px;
            font-size: 13px;
            font-weight: 600;
            color: #94a3b8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 16px 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.04);
            color: #cbd5e1;
        }

        tbody tr {
            transition: background 0.15s;
        }

        tbody tr:hover {
            background: rgba(255, 255, 255, 0.02);
        }

        .user-name {
            font-weight: 600;
            color: white;
        }

        .user-username {
            font-size: 13px;
            color: #64748b;
            margin-top: 2px;
        }

        .role-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-student { background: rgba(59, 130, 246, 0.15); color: #60a5fa; }
        .role-teacher { background: rgba(245, 158, 11, 0.15); color: #fbbf24; }
        .role-admin { background: rgba(239, 68, 68, 0.15); color: #f87171; }

        .btn-icon {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.08);
            width: 32px;
            height: 32px;
            border-radius: 6px;
            cursor: pointer;
            color: #94a3b8;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-icon:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
            border-color: rgba(255, 255, 255, 0.12);
        }

        .btn-icon.danger:hover {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal.active { display: flex; }

        .modal-content {
            background: #1e293b;
            width: 90%;
            max-width: 500px;
            border-radius: 16px;
            border: 1px solid rgba(255, 255, 255, 0.08);
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            font-size: 18px;
            color: white;
        }

        .modal-body {
            padding: 24px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-size: 13px;
            color: #94a3b8;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 10px 12px;
            border-radius: 8px;
            color: white;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #068567;
        }

        .modal-footer {
            padding: 16px 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .btn-secondary {
            background: transparent;
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #94a3b8;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.05);
            color: white;
        }

        /* Alert */
        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 14px 20px;
            border-radius: 10px;
            z-index: 2000;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        .alert-success { background: #10b981; color: white; }
        .alert-error { background: #ef4444; color: white; }

        @keyframes slideIn {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        /* Pagination */
        .pagination {
            padding: 16px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
        }

        .page-info {
            font-size: 13px;
            color: #94a3b8;
        }

        .page-btn {
            padding: 6px 12px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 6px;
            color: #cbd5e1;
            text-decoration: none;
            font-size: 13px;
            margin: 0 2px;
            transition: all 0.2s;
        }

        .page-btn:hover,
        .page-btn.active {
            background: #068567;
            border-color: #068567;
            color: white;
        }

        @media (max-width: 1024px) {
            .main-wrapper { margin-left: 0; padding: 20px; }
        }
    </style>
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <?php include 'sidebar.php'; ?>

    <div class="main-wrapper">
        <!-- Alert -->
        <?php if (!empty($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?>" id="alertBox">
            <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
            <span><?php echo $message; ?></span>
            <button onclick="document.getElementById('alertBox').remove()" style="background:none;border:none;color:white;cursor:pointer;margin-left:10px;">×</button>
        </div>
        <script>setTimeout(() => document.getElementById('alertBox')?.remove(), 5000);</script>
        <?php endif; ?>

        <!-- Header -->
        <div class="page-header">
            <div>
                <h1 class="page-title">Kullanıcı Yönetimi</h1>
                <div class="page-subtitle"><?php echo $totalItems; ?> kullanıcı</div>
            </div>
            <button onclick="openModal('addModal')" class="btn-primary">
                <i class="fas fa-plus"></i> Yeni Kullanıcı
            </button>
        </div>

        <!-- Content -->
        <div class="content-box">
            <!-- Toolbar -->
            <form method="GET" class="toolbar">
                <div style="display:flex; gap:5px; flex: 1; min-width: 300px;">
                    <input type="text" name="search" class="search-input" placeholder="Ara..." value="<?php echo htmlspecialchars($search); ?>" style="flex:1;">
                    <button type="submit" class="btn-primary" style="padding: 10px 15px;">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
                
                <select name="role" class="filter" onchange="this.form.submit()">
                    <option value="">Tüm Roller</option>
                    <option value="student" <?php if($fRole == 'student') echo 'selected'; ?>>Öğrenci</option>
                    <option value="teacher" <?php if($fRole == 'teacher') echo 'selected'; ?>>Eğitmen</option>
                    <option value="branch_leader" <?php if($fRole == 'branch_leader') echo 'selected'; ?>>Şube Eğitim Başkanı</option>
                    <option value="region_leader" <?php if($fRole == 'region_leader') echo 'selected'; ?>>Bölge Eğitim Başkanı</option>
                    <option value="superadmin" <?php if($fRole == 'superadmin') echo 'selected'; ?>>Admin</option>
                </select>

                <select name="region" class="filter" onchange="this.form.submit()">
                    <option value="">Tüm Bölgeler</option>
                    <?php foreach ($regionConfig as $reg => $branches): ?>
                        <option value="<?php echo htmlspecialchars($reg); ?>" <?php if($fRegion == $reg) echo 'selected'; ?>><?php echo htmlspecialchars($reg); ?></option>
                    <?php endforeach; ?>
                </select>

                <?php if(!empty($search) || !empty($fRole) || !empty($fRegion)): ?>
                    <a href="users.php" class="btn-secondary" style="text-decoration:none;padding:10px 16px;display:inline-block;">Temizle</a>
                <?php endif; ?>
            </form>

            <!-- Table -->
            <table>
                <thead>
                    <tr>
                        <th>Kullanıcı</th>
                        <th>Rol</th>
                        <th>Kurum</th>
                        <th>Bölge</th>
                        <th style="text-align:right;">İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($displayUsers) > 0): ?>
                        <?php foreach ($displayUsers as $u): ?>
                        <tr>
                            <td>
                                <div class="user-name"><?php echo htmlspecialchars($u['full_name'] ?? $u['name'] ?? 'İsimsiz'); ?></div>
                                <div class="user-username">@<?php echo htmlspecialchars($u['username']); ?></div>
                            </td>
                            <td>
                                <?php 
                                    // Rol badge sınıfı ve isim eşleştirmesi
                                    $roleMap = [
                                        'student' => ['class' => 'role-student', 'name' => 'Öğrenci'],
                                        'teacher' => ['class' => 'role-teacher', 'name' => 'Eğitmen'],
                                        'branch_leader' => ['class' => 'role-admin', 'name' => 'Şube Eğitim Başkanı'],
                                        'region_leader' => ['class' => 'role-admin', 'name' => 'Bölge Eğitim Başkanı'],
                                        'admin' => ['class' => 'role-admin', 'name' => 'Admin'],
                                        'superadmin' => ['class' => 'role-admin', 'name' => 'Admin']
                                    ];
                                    
                                    $currentRole = $u['role'] ?? 'student';
                                    $roleInfo = $roleMap[$currentRole] ?? ['class' => 'role-student', 'name' => ucfirst($currentRole)];
                                    
                                    $rClass = $roleInfo['class'];
                                    $rName = $roleInfo['name'];
                                ?>
                                <span class="role-badge <?php echo $rClass; ?>"><?php echo $rName; ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($u['institution'] ?? $u['branch'] ?? '-'); ?></td>
                            <td style="font-size:13px;">
                                <?php echo htmlspecialchars($u['region'] ?? '-'); ?>
                            </td>
                            <td style="text-align:right;">
                                <button onclick='editUser(<?php echo json_encode($u); ?>)' class="btn-icon" title="Düzenle">
                                    <i class="fas fa-pen"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Silmek istediğine emin misin?');" style="display:inline;margin:0;">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="username" value="<?php echo htmlspecialchars($u['username']); ?>">
                                    <button type="submit" class="btn-icon danger" title="Sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="5" style="text-align:center;padding:40px;color:#64748b;">Kayıt bulunamadı.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <div class="page-info">Sayfa <?php echo $page; ?> / <?php echo $totalPages; ?></div>
                <div>
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page-1; ?>" class="page-btn">‹</a>
                    <?php endif; ?>
                    <?php for($i=max(1, $page-2); $i<=min($totalPages, $page+2); $i++): ?>
                        <a href="?page=<?php echo $i; ?>" class="page-btn <?php echo $i==$page?'active':''; ?>"><?php echo $i; ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page+1; ?>" class="page-btn">›</a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ADD MODAL -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Yeni Kullanıcı Ekle</h2>
                <button onclick="closeModal('addModal')" class="btn-icon">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_user">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ad</label>
                        <input type="text" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label>Soyad</label>
                        <input type="text" name="last_name" required>
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role" required>
                            <option value="student">Öğrenci</option>
                            <option value="teacher">Eğitmen</option>
                            <option value="branch_leader">Şube Eğitim Başkanı</option>
                            <option value="region_leader">Bölge Eğitim Başkanı</option>
                            <option value="superadmin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bölge</label>
                        <select name="region" id="add_region" onchange="updateBranches('add')">
                            <option value="">Seçiniz</option>
                            <?php foreach ($regionConfig as $reg => $branches): ?>
                                <option value="<?php echo htmlspecialchars($reg); ?>"><?php echo htmlspecialchars($reg); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kurum</label>
                        <select name="institution" id="add_institution">
                            <option value="">Önce Bölge Seçin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sınıf</label>
                        <input type="text" name="class_section">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email">
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('addModal')" class="btn-secondary">İptal</button>
                    <button type="submit" class="btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Kullanıcı Düzenle</h2>
                <button onclick="closeModal('editModal')" class="btn-icon">×</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="username" id="edit_username">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Ad Soyad</label>
                        <input type="text" name="name" id="edit_name" required>
                    </div>
                    <div class="form-group">
                        <label>Rol</label>
                        <select name="role" id="edit_role" required>
                            <option value="student">Öğrenci</option>
                            <option value="teacher">Eğitmen</option>
                            <option value="branch_leader">Şube Eğitim Başkanı</option>
                            <option value="region_leader">Bölge Eğitim Başkanı</option>
                            <option value="superadmin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Bölge</label>
                        <select name="region" id="edit_region" onchange="updateBranches('edit')">
                            <?php foreach ($regionConfig as $reg => $branches): ?>
                                <option value="<?php echo htmlspecialchars($reg); ?>"><?php echo htmlspecialchars($reg); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kurum</label>
                        <select name="institution" id="edit_institution"></select>
                    </div>
                    <div class="form-group">
                        <label>Sınıf</label>
                        <input type="text" name="class_section" id="edit_class">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" id="edit_email">
                    </div>
                    <div class="form-group">
                        <label>Telefon</label>
                        <input type="text" name="phone" id="edit_phone">
                    </div>
                    <div class="form-group">
                        <label>Yeni Şifre (opsiyonel)</label>
                        <input type="text" name="new_password" placeholder="Boş bırakırsanız değişmez">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" onclick="closeModal('editModal')" class="btn-secondary">İptal</button>
                    <button type="submit" class="btn-primary">Güncelle</button>
                </div>
            </form>
        </div>
    </div>

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
            
            instSelect.innerHTML = '<option value="">Seçiniz</option>';
            
            if (selectedRegion && regions[selectedRegion]) {
                regions[selectedRegion].forEach(branch => {
                    const opt = document.createElement('option');
                    opt.value = branch;
                    opt.textContent = branch;
                    instSelect.appendChild(opt);
                });
            }
        }

        function editUser(user) {
            document.getElementById('edit_username').value = user.username;
            document.getElementById('edit_name').value = user.name || user.full_name || '';
            document.getElementById('edit_role').value = user.role;
            document.getElementById('edit_class').value = user.class_section || '';
            document.getElementById('edit_email').value = user.email || '';
            document.getElementById('edit_phone').value = user.phone || '';
            
            let region = user.region || '';
            const inst = user.institution || user.branch || '';
            
            if (!region && inst) {
                for(let r in regions) {
                    if (regions[r].includes(inst)) { region = r; break; }
                }
            }
            if(!region) region = 'Arlberg';
            
            document.getElementById('edit_region').value = region;
            updateBranches('edit');
            
            setTimeout(() => {
                document.getElementById('edit_institution').value = inst;
            }, 10);
            
            openModal('editModal');
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('active');
            }
        }
    </script>
</body>
</html>

