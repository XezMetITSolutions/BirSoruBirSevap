<?php
require_once '../auth.php';
require_once '../config.php';
require_once '../database.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();
$db = Database::getInstance();
$conn = $db->getConnection();

// Lazy Table Creation
try {
    $conn->query("SELECT 1 FROM training_materials LIMIT 1");
} catch (PDOException $e) {
    // Table likely doesn't exist
    $sql = "CREATE TABLE IF NOT EXISTS `training_materials` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `title` varchar(255) NOT NULL,
      `description` text,
      `file_path` varchar(255) NOT NULL,
      `file_type` varchar(50) DEFAULT NULL,
      `allowed_roles` text,
      `created_by` int(11) DEFAULT NULL,
      `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    $conn->exec($sql);
    
    // Ensure upload dir exists
    $uploadDir = '../uploads/training';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
}

$message = '';
$error = '';

// Handle Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $roles = $_POST['roles'] ?? [];
    
    if (empty($title) || empty($roles) || !isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Lütfen tüm alanları doldurun ve bir dosya seçin.';
    } else {
        $allowedRolesStr = implode(',', $roles);
        
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileSize = $_FILES['file']['size'];
        $fileType = $_FILES['file']['type'];
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));
        
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = '../uploads/training/';
        $dest_path = $uploadFileDir . $newFileName;
        
        if(move_uploaded_file($fileTmpPath, $dest_path)) {
            $sql = "INSERT INTO training_materials (title, description, file_path, file_type, allowed_roles, created_by) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            if ($stmt->execute([$title, $description, $newFileName, $fileExtension, $allowedRolesStr, $user['id'] ?? 0])) {
                $message = 'Materyal başarıyla yüklendi.';
            } else {
                $error = 'Veritabanı hatası.';
            }
        } else {
            $error = 'Dosya yüklenirken bir hata oluştu. Klasör izinlerini kontrol edin.';
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT file_path FROM training_materials WHERE id = ?");
    $stmt->execute([$id]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($file) {
        $filePath = '../uploads/training/' . $file['file_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
        
        $stmt = $conn->prepare("DELETE FROM training_materials WHERE id = ?");
        $stmt->execute([$id]);
        $message = 'Materyal silindi.';
    }
}

// List Materials
$materials = [];
try {
    $stmt = $conn->query("SELECT * FROM training_materials ORDER BY created_at DESC");
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = 'Materyaller listelenemedi: ' . $e->getMessage();
}

$availableRoles = [
    'student' => 'Öğrenci',
    'teacher' => 'Öğretmen', 
    'branch_leader' => 'Şube Sorumlusu',
    'region_leader' => 'Bölge Sorumlusu'
];

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitim Materyalleri - Yönetici</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/admin-style.css">
    <style>
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 0.5rem; }
        .btn-primary { background: #068567; color: #fff; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; }
        .checkbox-group { display: flex; gap: 1rem; flex-wrap: wrap; }
        .checkbox-item { display: flex; align-items: center; gap: 0.5rem; }
        .material-list { display: grid; gap: 1rem; }
        .material-item { background: #fff; padding: 1.5rem; border-radius: 0.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .material-info h4 { margin: 0 0 0.5rem 0; }
        .material-roles { font-size: 0.85rem; color: #666; margin-top: 0.5rem; }
        .badge { background: #e2e8f0; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; margin-right: 0.25rem; }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="main-wrapper">
        <div class="top-bar">
            <div class="menu-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">
                <i class="fas fa-bars"></i>
            </div>
            <h2>Eğitim Materyalleri Yönetimi</h2>
        </div>

        <?php if (!empty($message)): ?>
            <div class="success-box" style="background:#dcfce7; color:#166534; padding:1rem; border-radius:0.5rem; margin-bottom:1rem;">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="error-box" style="background:#fee2e2; color:#991b1b; padding:1rem; border-radius:0.5rem; margin-bottom:1rem;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="content-row">
            <div class="glass-panel" style="flex:1;">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-upload"></i> Yeni Materyal Yükle</div>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <div class="form-group">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" class="form-control" required placeholder="Materyal başlığı...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="İsteğe bağlı açıklama..."></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Erişim Yetkisi (Roller)</label>
                        <div class="checkbox-group">
                            <?php foreach ($availableRoles as $roleKey => $roleName): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="roles[]" value="<?php echo $roleKey; ?>">
                                    <?php echo $roleName; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Dosya</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>
                    <button type="submit" class="btn-primary">Yükle</button>
                </form>
            </div>

            <div class="glass-panel" style="flex:2;">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-list"></i> Mevcut Materyaller</div>
                </div>
                <div class="material-list">
                    <?php if (empty($materials)): ?>
                        <div style="text-align:center; padding:2rem; color:#666;">Henüz materyal yüklenmemiş.</div>
                    <?php else: ?>
                        <?php foreach ($materials as $item): ?>
                            <div class="material-item">
                                <div class="material-info">
                                    <h4><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <div style="font-size:0.9rem; margin-bottom:0.5rem;"><?php echo htmlspecialchars($item['description']); ?></div>
                                    <div class="material-roles">
                                        <strong>Erişim:</strong> 
                                        <?php 
                                            $itemRoles = explode(',', $item['allowed_roles']);
                                            foreach ($itemRoles as $r) {
                                                echo '<span class="badge">' . ($availableRoles[$r] ?? $r) . '</span>';
                                            }
                                        ?>
                                    </div>
                                    <div style="font-size:0.8rem; color:#999; margin-top:0.5rem;">
                                        <?php echo date('d.m.Y H:i', strtotime($item['created_at'])); ?> | <?php echo strtoupper($item['file_type']); ?>
                                    </div>
                                </div>
                                <div class="actions">
                                    <a href="../uploads/training/<?php echo $item['file_path']; ?>" class="action-btn" target="_blank">
                                        <i class="fas fa-download"></i>
                                    </a>
                                    <a href="?delete=<?php echo $item['id']; ?>" class="action-btn" style="color:#ef4444; border-color:rgba(239,68,68,0.2);" onclick="return confirm('Silmek istediğinize emin misiniz?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
