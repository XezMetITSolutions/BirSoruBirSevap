<?php
/**
 * Öğretmen - Öğrenci Yönetimi
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü (superadmin de erişebilir)
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$teacherClass = $user['class_section'] ?? '';
$teacherBranch = $user['branch'] ?? '';

// Mesajları işle
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'add_student':
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $class_section = sanitize_input($_POST['class_section'] ?? '');
                $password = 'iqra2025#'; // Standart şifre
                
                // Tam adı oluştur
                $fullName = $first_name . ' ' . $last_name;
                
                // Kullanıcı adını otomatik oluştur (Ü/ü -> ue, Ö/ö -> oe)
                $lastNamePart = strlen($last_name) >= 5 ? substr($last_name, 0, 5) : $last_name;
                $firstNamePart = substr($first_name, 0, 3);
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
                
                // Öğretmen sadece kendi kurumuna öğrenci ekleyebilir (sınıf fark etmez)
                if ($auth->hasRole('superadmin') || $teacherBranch) {
                    if ($auth->saveUser($username, $password, 'student', $fullName, $teacherBranch, $class_section)) {
                        $message = 'Öğrenci başarıyla eklendi!';
                        $messageType = 'success';
                    } else {
                        $message = 'Öğrenci eklenirken hata oluştu!';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Kurum bilginiz bulunamadı!';
                    $messageType = 'error';
                }
                break;
                
            case 'edit_student':
                $username = sanitize_input($_POST['username']);
                $first_name = sanitize_input($_POST['first_name']);
                $last_name = sanitize_input($_POST['last_name']);
                $class_section_input = $_POST['class_section'] ?? null;
                $fullName = $first_name . ' ' . $last_name;
                
                $users = $auth->getAllUsers();
                if (isset($users[$username]) && $users[$username]['role'] === 'student') {
                    // Öğretmen sadece kendi kurumundaki öğrencileri düzenleyebilir
                    if ($auth->hasRole('superadmin') || ($users[$username]['branch'] ?? '') === $teacherBranch) {
                        // Veritabanında güncelle
                        $db = Database::getInstance();
                        $connection = $db->getConnection();
                        $class_section = $class_section_input !== null ? sanitize_input($class_section_input) : ($users[$username]['class_section'] ?? '');
                        $stmt = $connection->prepare("UPDATE users SET full_name = ?, class_section = ?, updated_at = NOW() WHERE username = ?");
                        if ($stmt->execute([$fullName, $class_section, $username])) {
                            $message = 'Öğrenci bilgileri güncellendi!';
                            $messageType = 'success';
                        } else {
                            $message = 'Öğrenci güncellenirken hata oluştu!';
                            $messageType = 'error';
                        }
            } else {
                        $message = 'Sadece kendi kurumunuzdaki öğrencileri düzenleyebilirsiniz!';
                        $messageType = 'error';
            }
        } else {
                    $message = 'Öğrenci bulunamadı!';
                    $messageType = 'error';
                }
                break;
                
            case 'delete_student':
                $username = sanitize_input($_POST['username']);
                
                $users = $auth->getAllUsers();
                if (isset($users[$username]) && $users[$username]['role'] === 'student') {
                    // Öğretmen sadece kendi kurumundaki öğrencileri silebilir
                    if ($auth->hasRole('superadmin') || ($users[$username]['branch'] ?? '') === $teacherBranch) {
                        if ($auth->deleteUser($username)) {
                            $message = 'Öğrenci silindi!';
                            $messageType = 'success';
    } else {
                            $message = 'Öğrenci silinirken hata oluştu!';
                            $messageType = 'error';
                        }
            } else {
                        $message = 'Sadece kendi kurumunuzdaki öğrencileri silebilirsiniz!';
                        $messageType = 'error';
            }
        } else {
                    $message = 'Öğrenci bulunamadı!';
                    $messageType = 'error';
                }
                break;
                
            case 'change_password':
                $username = sanitize_input($_POST['username']);
                $newPassword = sanitize_input($_POST['new_password']);
                $forceChange = isset($_POST['force_change']) ? true : false;
                
                $users = $auth->getAllUsers();
                if (isset($users[$username]) && $users[$username]['role'] === 'student') {
                    // Öğretmen sadece kendi kurumundaki öğrencilerin şifresini değiştirebilir
                    if ($auth->hasRole('superadmin') || ($users[$username]['branch'] ?? '') === $teacherBranch) {
                        if ($auth->changePassword($username, $newPassword)) {
                            // Eğer "zorla değiştir" seçildiyse, öğrenci bir dahaki girişte şifresini değiştirmek zorunda
                            if ($forceChange) {
                                $db = Database::getInstance();
                                $stmt = $db->getConnection()->prepare("UPDATE users SET must_change_password = 1 WHERE username = ?");
                                $stmt->execute([$username]);
                                $message = 'Öğrenci şifresi değiştirildi! Öğrenci bir dahaki girişte yeni şifresini belirleyecek.';
                            } else {
                                $message = 'Öğrenci şifresi başarıyla değiştirildi!';
                            }
                            $messageType = 'success';
                        } else {
                            $message = 'Şifre değiştirilirken hata oluştu!';
                            $messageType = 'error';
                        }
                    } else {
                        $message = 'Sadece kendi kurumunuzdaki öğrencilerin şifresini değiştirebilirsiniz!';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Öğrenci bulunamadı!';
                    $messageType = 'error';
                }
                break;
        }
    } catch (Exception $e) {
        $message = 'Hata: ' . $e->getMessage();
        $messageType = 'error';
    }
}

// Öğrencileri getir (öğretmen sadece kendi kurumundakileri görür)
$allUsers = $auth->getAllUsers();
$students = [];

// Debug bilgisi (geçici)
echo "<!-- DEBUG: Öğretmen Kurumu: '$teacherBranch' -->";
echo "<!-- DEBUG: Toplam kullanıcı sayısı: " . count($allUsers) . " -->";

foreach ($allUsers as $username => $userData) {
    if ($userData['role'] === 'student') {
        // Superadmin tüm öğrencileri görür, öğretmen sadece kendi kurumundaki öğrencileri
        $studentBranch = $userData['branch'] ?? $userData['institution'] ?? '';
        $isSameBranch = $studentBranch === $teacherBranch;
        
        // Debug bilgisi (geçici)
        if ($username === 'burca.rav') {
            echo "<!-- DEBUG: Öğrenci: $username, Kurum: '$studentBranch', Öğretmen Kurumu: '$teacherBranch', Eşleşme: " . ($isSameBranch ? 'EVET' : 'HAYIR') . " -->";
        }
        
        if ($auth->hasRole('superadmin') || $isSameBranch) {
            $students[$username] = $userData;
        }
    }
}

// Debug bilgisi (geçici)
echo "<!-- DEBUG: Bulunan öğrenci sayısı: " . count($students) . " -->";
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Öğrenci Yönetimi - Bir Soru Bir Sevap</title>
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
            gap: 12px;
        }

        /* Uzun kullanıcı adı kırpma */
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 25px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .breadcrumb a {
            color: #089b76;
            text-decoration: none;
            font-weight: 600;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .main-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .page-title {
            font-size: 2.2em;
            color: #2c3e50;
            font-weight: 700;
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

        .btn-danger {
            background: #e74c3c;
        }

        .btn-warning {
            background: #f39c12;
        }

        .btn-info {
            background: #17a2b8;
        }

        .form-info {
            margin: 10px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
        }

        .form-info small {
            color: #6c757d;
        }

        #username-preview {
            font-weight: bold;
            color: #17a2b8;
        }

        .btn-success {
            background: #27ae60;
        }

        .btn-sm {
            padding: 8px 16px;
            font-size: 0.9em;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border: 1px solid #e9ecef;
        }

        .form-section h3 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.4em;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            margin-bottom: 8px;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select {
            padding: 12px 15px;
            border: 2px solid #e9ecef;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #089b76;
            box-shadow: 0 0 0 3px rgba(8, 155, 118, 0.1);
        }

        .students-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-top: 30px;
        }

        .table-header {
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            color: white;
            padding: 20px;
            font-size: 1.2em;
            font-weight: 700;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
        }

        .table th,
        .table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }

        .table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #2c3e50;
        }

        .table tbody tr:hover {
            background: rgba(8, 155, 118, 0.05);
        }

        .action-buttons {
            display: flex;
            gap: 10px;
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
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.3);
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

        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4em;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5em;
            margin-bottom: 10px;
            color: #495057;
        }

        .empty-state p {
            font-size: 1.1em;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; flex-wrap: wrap; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .logout-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }

            .table {
                font-size: 0.9em;
            }
            
            .table th,
            .table td {
                padding: 10px;
            }
        }

        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .logout-btn { padding: 5px 8px; font-size: .85rem; }
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
                    <p id="pageTitle">Öğrenci Yönetimi</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;" id="userRole">Eğitmen</div>
                </div>
                <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="../logout.php" class="logout-btn" id="btnLogout">Çıkış</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="breadcrumb">
            <a href="dashboard.php" id="btnHome">🏠 Ana Sayfa</a> > <span id="breadcrumbCurrent">👥 Öğrenci Yönetimi</span>
        </div>

        <div class="main-content">
            <div class="page-header">
                <h1 class="page-title" id="mainTitle">👥 Öğrenci Yönetimi</h1>
                <button class="btn btn-success" onclick="openAddModal()" id="btnAddStudent">
                    ➕ Yeni Öğrenci Ekle
                </button>
        </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

            <!-- Öğrenci Ekleme Formu -->
            <div class="form-section">
                <h3 id="formTitle">📝 Yeni Öğrenci Ekle</h3>
                <form method="POST" action="">
                    <input type="hidden" name="action" value="add_student">
                    <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name" id="labelFirstName">Ad</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="last_name" id="labelLastName">Soyad</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    </div>
                    <div class="form-info">
                        <small id="usernameInfo">💡 Kullanıcı adı otomatik oluşturulacak: <span id="username-preview">ad.soyad</span></small>
                    </div>
                    <button type="submit" class="btn btn-success" id="btnSubmitStudent">✅ Öğrenci Ekle</button>
                </form>
            </div>

            <!-- Öğrenci Listesi -->
            <div class="students-table">
                <div class="table-header">
                    📚 <span id="tableTitle">Sınıfınızdaki Öğrenciler</span> (<?php echo count($students); ?> <span id="studentCount">öğrenci</span>)
                </div>
                
                <?php if (empty($students)): ?>
                    <div class="empty-state">
                        <i>👥</i>
                        <h3 id="noStudentsTitle">Henüz öğrenci yok</h3>
                        <p id="noStudentsDesc">Yukarıdaki formu kullanarak ilk öğrencinizi ekleyin</p>
                        <!-- DEBUG: Öğrenci dizisi boş -->
                    </div>
                <?php else: ?>
                    <!-- DEBUG: Öğrenci dizisi dolu, öğrenci sayısı: <?php echo count($students); ?> -->
                    <table class="table">
                    <thead>
                        <tr>
                                <th id="thUsername">Kullanıcı Adı</th>
                                <th id="thFullName">Ad Soyad</th>
                                <th id="thClass">Sınıf</th>
                            <th id="thRegisterDate">Kayıt Tarihi</th>
                            <th id="thActions">İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                            <?php foreach ($students as $username => $studentData): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($username); ?></strong></td>
                                    <td><?php echo htmlspecialchars($studentData['full_name'] ?? $studentData['name'] ?? 'Bilinmiyor'); ?></td>
                                    <td><?php echo htmlspecialchars($studentData['class_section']); ?></td>
                                    <td><?php echo htmlspecialchars($studentData['created_at']); ?></td>
                                <td>
                                    <div class="action-buttons">
                                            <button class="btn btn-warning btn-sm" onclick="openEditModal('<?php echo $username; ?>', '<?php echo htmlspecialchars($studentData['full_name'] ?? $studentData['name'] ?? 'Bilinmiyor'); ?>')">
                                            ✏️ <span class="btnEditText">Düzenle</span>
                                        </button>
                                            <button class="btn btn-info btn-sm" onclick="openPasswordModal('<?php echo $username; ?>', '<?php echo htmlspecialchars($studentData['full_name'] ?? $studentData['name'] ?? 'Bilinmiyor'); ?>')">
                                            🔐 <span class="btnPasswordText">Şifre Değiştir</span>
                                        </button>
                                            <button class="btn btn-danger btn-sm" onclick="confirmDelete('<?php echo $username; ?>', '<?php echo htmlspecialchars($studentData['full_name'] ?? $studentData['name'] ?? 'Bilinmiyor'); ?>')">
                                                🗑️ <span class="btnDeleteText">Sil</span>
                                            </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Düzenleme Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="editModalTitle">✏️ Öğrenci Düzenle</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="edit_student">
                <input type="hidden" id="edit_username" name="username">
                <div class="form-group">
                    <label for="edit_first_name" id="editLabelFirstName">Ad</label>
                    <input type="text" id="edit_first_name" name="first_name" required>
                </div>
                <div class="form-group">
                    <label for="edit_last_name" id="editLabelLastName">Soyad</label>
                    <input type="text" id="edit_last_name" name="last_name" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()" id="btnCancelEdit">İptal</button>
                    <button type="submit" class="btn btn-warning" id="btnUpdateStudent">💾 Güncelle</button>
                </div>
            </form>
        </div>
                </div>

    <!-- Şifre Değişikliği Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="passwordModalTitle">🔐 Şifre Değiştir</h3>
                <span class="close" onclick="closePasswordModal()">&times;</span>
            </div>
            <div style="padding: 20px 0;">
                <p><strong id="passwordUsernameLabel">Kullanıcı Adı:</strong> <span id="passwordUsername"></span></p>
                <p><strong id="passwordNameLabel">Ad Soyad:</strong> <span id="passwordName"></span></p>
                <form method="POST" id="passwordForm">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" name="username" id="passwordUsernameInput">
                    <div class="form-group">
                        <label for="new_password" id="labelNewPassword">Yeni Şifre</label>
                        <input type="password" id="new_password" name="new_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label for="confirm_password" id="labelConfirmPassword">Şifre Tekrar</label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
                    </div>
                    <div class="form-group">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                            <input type="checkbox" id="force_change" name="force_change" value="1" style="margin: 0;">
                            <span id="forceChangeText">Öğrenci bir dahaki girişte şifresini değiştirmek zorunda olsun</span>
                        </label>
                        <small style="color: #6c757d; margin-top: 0.5rem; display: block;" id="forceChangeDesc">
                            💡 Bu seçenek işaretlenirse, öğrenci yeni şifreyle giriş yaptıktan sonra kendi şifresini belirleyecek
                        </small>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" onclick="submitPasswordChange()" id="btnChangePassword">🔐 Şifre Değiştir</button>
                <button type="button" class="btn btn-secondary" onclick="closePasswordModal()" id="btnCancelPassword">İptal</button>
            </div>
        </div>
    </div>

    <!-- Silme Onay Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="deleteModalTitle">⚠️ Öğrenci Sil</h3>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
                </div>
            <div style="padding: 20px 0;">
                <p id="deleteConfirmText">Bu öğrenciyi silmek istediğinizden emin misiniz?</p>
                <p><strong id="delete_student_name"></strong> <span id="deleteStudentText">adlı öğrenci kalıcı olarak silinecektir.</span></p>
                </div>
            <form method="POST" action="">
                <input type="hidden" name="action" value="delete_student">
                <input type="hidden" id="delete_username" name="username">
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()" id="btnCancelDelete">İptal</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmDelete">🗑️ Sil</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            // Form scroll to top
            document.querySelector('.form-section').scrollIntoView({ behavior: 'smooth' });
        }

        function openEditModal(username, name) {
            document.getElementById('edit_username').value = username;
            
            // Ad ve soyadı ayır
            const nameParts = name.split(' ');
            const firstName = nameParts[0] || '';
            const lastName = nameParts.slice(1).join(' ') || '';
            
            document.getElementById('edit_first_name').value = firstName;
            document.getElementById('edit_last_name').value = lastName;
            document.getElementById('editModal').style.display = 'block';
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        function confirmDelete(username, name) {
            document.getElementById('delete_username').value = username;
            document.getElementById('delete_student_name').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }

        function openPasswordModal(username, name) {
            document.getElementById('passwordUsername').textContent = username;
            document.getElementById('passwordName').textContent = name;
            document.getElementById('passwordUsernameInput').value = username;
            document.getElementById('passwordModal').style.display = 'block';
        }

        function closePasswordModal() {
            document.getElementById('passwordModal').style.display = 'none';
            document.getElementById('passwordForm').reset();
        }

        function submitPasswordChange() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                alert('Şifreler eşleşmiyor!');
                return;
            }
            
            if (newPassword.length < 6) {
                alert('Şifre en az 6 karakter olmalıdır!');
                return;
            }
            
            document.getElementById('passwordForm').submit();
        }

        // Modal dışına tıklayınca kapat
        window.onclick = function(event) {
            const editModal = document.getElementById('editModal');
            const deleteModal = document.getElementById('deleteModal');
            const passwordModal = document.getElementById('passwordModal');
            
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            if (event.target === passwordModal) {
                closePasswordModal();
            }
        }

        // Kullanıcı adı önizlemesi (superadmin panelindeki gibi)
        function updateUsernamePreview() {
            const firstName = document.getElementById('first_name').value;
            const lastName = document.getElementById('last_name').value;
            
            if (firstName && lastName) {
                // Superadmin panelindeki mantık: soyad(5) + '.' + ad(3)
                const lastNamePart = lastName.length >= 5 ? lastName.substring(0, 5) : lastName;
                const firstNamePart = firstName.substring(0, 3);
                const username = (lastNamePart + '.' + firstNamePart).toLowerCase();
                document.getElementById('username-preview').textContent = username;
            } else {
                document.getElementById('username-preview').textContent = 'soyad.ad';
            }
        }

        // Event listeners
        document.getElementById('first_name').addEventListener('input', updateUsernamePreview);
        document.getElementById('last_name').addEventListener('input', updateUsernamePreview);

        // Mesajları otomatik gizle
        setTimeout(function() {
            const messages = document.querySelectorAll('.message');
            messages.forEach(function(message) {
                message.style.opacity = '0';
                message.style.transition = 'opacity 0.5s ease';
                setTimeout(function() {
                    message.style.display = 'none';
                }, 500);
            });
        }, 5000);

        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Öğrenci Yönetimi', userRole:'Eğitmen', btnLogout:'Çıkış',
                btnHome:'🏠 Ana Sayfa', breadcrumbCurrent:'👥 Öğrenci Yönetimi',
                mainTitle:'👥 Öğrenci Yönetimi', btnAddStudent:'➕ Yeni Öğrenci Ekle',
                formTitle:'📝 Yeni Öğrenci Ekle', labelFirstName:'Ad', labelLastName:'Soyad',
                usernameInfo:'💡 Kullanıcı adı otomatik oluşturulacak:', btnSubmitStudent:'✅ Öğrenci Ekle',
                tableTitle:'Sınıfınızdaki Öğrenciler', studentCount:'öğrenci',
                noStudentsTitle:'Henüz öğrenci yok', noStudentsDesc:'Yukarıdaki formu kullanarak ilk öğrencinizi ekleyin',
                thUsername:'Kullanıcı Adı', thFullName:'Ad Soyad', thClass:'Sınıf', thRegisterDate:'Kayıt Tarihi', thActions:'İşlemler',
                btnEditText:'Düzenle', btnPasswordText:'Şifre Değiştir', btnDeleteText:'Sil',
                editModalTitle:'✏️ Öğrenci Düzenle', editLabelFirstName:'Ad', editLabelLastName:'Soyad',
                btnCancelEdit:'İptal', btnUpdateStudent:'💾 Güncelle',
                passwordModalTitle:'🔐 Şifre Değiştir', passwordUsernameLabel:'Kullanıcı Adı:', passwordNameLabel:'Ad Soyad:',
                labelNewPassword:'Yeni Şifre', labelConfirmPassword:'Şifre Tekrar',
                forceChangeText:'Öğrenci bir dahaki girişte şifresini değiştirmek zorunda olsun',
                forceChangeDesc:'💡 Bu seçenek işaretlenirse, öğrenci yeni şifreyle giriş yaptıktan sonra kendi şifresini belirleyecek',
                btnChangePassword:'🔐 Şifre Değiştir', btnCancelPassword:'İptal',
                deleteModalTitle:'⚠️ Öğrenci Sil', deleteConfirmText:'Bu öğrenciyi silmek istediğinizden emin misiniz?',
                deleteStudentText:'adlı öğrenci kalıcı olarak silinecektir.', btnCancelDelete:'İptal', btnConfirmDelete:'🗑️ Sil'
            };
            const de = {
                pageTitle:'Schülerverwaltung', userRole:'Lehrpersonal', btnLogout:'Abmelden',
                btnHome:'🏠 Startseite', breadcrumbCurrent:'👥 Schülerverwaltung',
                mainTitle:'👥 Schülerverwaltung', btnAddStudent:'➕ Neuen Schüler hinzufügen',
                formTitle:'📝 Neuen Schüler hinzufügen', labelFirstName:'Vorname', labelLastName:'Nachname',
                usernameInfo:'💡 Benutzername wird automatisch erstellt:', btnSubmitStudent:'✅ Schüler hinzufügen',
                tableTitle:'Ihre Schüler', studentCount:'Schüler',
                noStudentsTitle:'Noch keine Schüler', noStudentsDesc:'Verwenden Sie das Formular oben, um Ihren ersten Schüler hinzuzufügen',
                thUsername:'Benutzername', thFullName:'Vor- und Nachname', thClass:'Klasse', thRegisterDate:'Registrierungsdatum', thActions:'Aktionen',
                btnEditText:'Bearbeiten', btnPasswordText:'Passwort ändern', btnDeleteText:'Löschen',
                editModalTitle:'✏️ Schüler bearbeiten', editLabelFirstName:'Vorname', editLabelLastName:'Nachname',
                btnCancelEdit:'Abbrechen', btnUpdateStudent:'💾 Aktualisieren',
                passwordModalTitle:'🔐 Passwort ändern', passwordUsernameLabel:'Benutzername:', passwordNameLabel:'Vor- und Nachname:',
                labelNewPassword:'Neues Passwort', labelConfirmPassword:'Passwort wiederholen',
                forceChangeText:'Schüler muss beim nächsten Login das Passwort ändern',
                forceChangeDesc:'💡 Wenn diese Option aktiviert ist, kann der Schüler nach dem Login mit dem neuen Passwort sein eigenes Passwort festlegen',
                btnChangePassword:'🔐 Passwort ändern', btnCancelPassword:'Abbrechen',
                deleteModalTitle:'⚠️ Schüler löschen', deleteConfirmText:'Sind Sie sicher, dass Sie diesen Schüler löschen möchten?',
                deleteStudentText:'wird dauerhaft gelöscht.', btnCancelDelete:'Abbrechen', btnConfirmDelete:'🗑️ Löschen'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnLogout', d.btnLogout);
                setText('#btnHome', d.btnHome);
                setText('#breadcrumbCurrent', d.breadcrumbCurrent);
                setText('#mainTitle', d.mainTitle);
                setText('#btnAddStudent', d.btnAddStudent);
                setText('#formTitle', d.formTitle);
                setText('#labelFirstName', d.labelFirstName);
                setText('#labelLastName', d.labelLastName);
                setText('#usernameInfo', d.usernameInfo);
                setText('#btnSubmitStudent', d.btnSubmitStudent);
                setText('#tableTitle', d.tableTitle);
                setText('#studentCount', d.studentCount);
                setText('#noStudentsTitle', d.noStudentsTitle);
                setText('#noStudentsDesc', d.noStudentsDesc);
                setText('#thUsername', d.thUsername);
                setText('#thFullName', d.thFullName);
                setText('#thClass', d.thClass);
                setText('#thRegisterDate', d.thRegisterDate);
                setText('#thActions', d.thActions);
                
                // Buton metinlerini güncelle
                document.querySelectorAll('.btnEditText').forEach(el => el.textContent = d.btnEditText);
                document.querySelectorAll('.btnPasswordText').forEach(el => el.textContent = d.btnPasswordText);
                document.querySelectorAll('.btnDeleteText').forEach(el => el.textContent = d.btnDeleteText);
                
                setText('#editModalTitle', d.editModalTitle);
                setText('#editLabelFirstName', d.editLabelFirstName);
                setText('#editLabelLastName', d.editLabelLastName);
                setText('#btnCancelEdit', d.btnCancelEdit);
                setText('#btnUpdateStudent', d.btnUpdateStudent);
                setText('#passwordModalTitle', d.passwordModalTitle);
                setText('#passwordUsernameLabel', d.passwordUsernameLabel);
                setText('#passwordNameLabel', d.passwordNameLabel);
                setText('#labelNewPassword', d.labelNewPassword);
                setText('#labelConfirmPassword', d.labelConfirmPassword);
                setText('#forceChangeText', d.forceChangeText);
                setText('#forceChangeDesc', d.forceChangeDesc);
                setText('#btnChangePassword', d.btnChangePassword);
                setText('#btnCancelPassword', d.btnCancelPassword);
                setText('#deleteModalTitle', d.deleteModalTitle);
                setText('#deleteConfirmText', d.deleteConfirmText);
                setText('#deleteStudentText', d.deleteStudentText);
                setText('#btnCancelDelete', d.btnCancelDelete);
                setText('#btnConfirmDelete', d.btnConfirmDelete);
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_student_management', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_student_management')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_student_management')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>
