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

// Lazy Table Creation (Ensure table exists)
try {
    $conn->query("SELECT 1 FROM training_materials LIMIT 1");
} catch (PDOException $e) {
    // ... same creation logic as before ...
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
    
    $uploadDir = '../uploads/training';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
}

$message = '';
$error = '';

$editMode = false;
$editItem = null;

// Handle Edit Fetch
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM training_materials WHERE id = ?");
    $stmt->execute([$editId]);
    $editItem = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($editItem) {
        $editMode = true;
    }
}

// Handle Form Submission (Create or Update)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $roles = $_POST['roles'] ?? [];
    $allowedRolesStr = implode(',', $roles);
    
    if (empty($title) || empty($roles)) {
        $error = 'Başlık ve en az bir erişim rolü seçilmelidir.';
    } else {
        if ($_POST['action'] === 'upload') {
            // New Upload
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                $error = 'Lütfen bir dosya seçin.';
            } else {
                $fileName = $_FILES['file']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = '../uploads/training/' . $newFileName;
                
                if(move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
                    $sql = "INSERT INTO training_materials (title, description, file_path, file_type, allowed_roles, created_by) VALUES (?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($sql);
                    if ($stmt->execute([$title, $description, $newFileName, $fileExtension, $allowedRolesStr, $user['id'] ?? 0])) {
                        $message = 'Materyal başarıyla yüklendi.';
                    } else {
                        $error = 'Veritabanı hatası.';
                    }
                } else {
                    $error = 'Dosya yüklenemedi.';
                }
            }
        } elseif ($_POST['action'] === 'update') {
            // Update Existing
            $id = (int)$_POST['id'];
            
            // Check if new file uploaded
            if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                // Upload new file
                $fileName = $_FILES['file']['name'];
                $fileNameCmps = explode(".", $fileName);
                $fileExtension = strtolower(end($fileNameCmps));
                $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
                $dest_path = '../uploads/training/' . $newFileName;
                
                if(move_uploaded_file($_FILES['file']['tmp_name'], $dest_path)) {
                    // Delete old file
                    $stmt = $conn->prepare("SELECT file_path FROM training_materials WHERE id = ?");
                    $stmt->execute([$id]);
                    $oldFile = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($oldFile && file_exists('../uploads/training/' . $oldFile['file_path'])) {
                        unlink('../uploads/training/' . $oldFile['file_path']);
                    }
                    
                    // Update with file
                    $sql = "UPDATE training_materials SET title = ?, description = ?, allowed_roles = ?, file_path = ?, file_type = ? WHERE id = ?";
                    $stmt = $conn->prepare($sql);
                    $stmt->execute([$title, $description, $allowedRolesStr, $newFileName, $fileExtension, $id]);
                }
            } else {
                // Update without file
                $sql = "UPDATE training_materials SET title = ?, description = ?, allowed_roles = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->execute([$title, $description, $allowedRolesStr, $id]);
            }
            
            $message = 'Materyal güncellendi.';
            $editMode = false; // Reset mode
            $editItem = null;
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
    $error = 'Listeleme hatası: ' . $e->getMessage();
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
    <title>Eğitim Materyalleri Yönetimi</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/dark-theme.css">
    <style>
        .form-group { margin-bottom: 1rem; }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 500; }
        .form-control { width: 100%; padding: 0.75rem; border: 1px solid #ddd; border-radius: 0.5rem; }
        .btn-primary { background: #068567; color: #fff; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; }
        .btn-secondary { background: #64748b; color: #fff; padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; text-decoration: none; display: inline-block; }
        .checkbox-group { display: flex; gap: 1rem; flex-wrap: wrap; }
        .checkbox-item { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .badge { background: #e2e8f0; padding: 0.2rem 0.5rem; border-radius: 0.25rem; font-size: 0.75rem; margin-right: 0.25rem; display: inline-block; margin-bottom: 2px; }
        
        /* Table Styles */
        .custom-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        .custom-table th, .custom-table td { padding: 1rem; text-align: left; border-bottom: 1px solid #e2e8f0; }
        .custom-table th { background: #f8fafc; font-weight: 600; color: #64748b; }
        .custom-table tr:hover { background: #f1f5f9; }
        .action-icon-btn { padding: 0.5rem; border-radius: 0.375rem; color: #64748b; transition: all 0.2s; margin-right: 0.5rem; display: inline-block; }
        .action-icon-btn:hover { background: #e2e8f0; color: #0f172a; }
        .action-icon-btn.delete:hover { background: #fee2e2; color: #ef4444; }
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
            <!-- Form Section -->
            <div class="glass-panel" style="flex:1;">
                <div class="panel-header">
                    <div class="panel-title">
                        <i class="fas <?php echo $editMode ? 'fa-edit' : 'fa-upload'; ?>"></i> 
                        <?php echo $editMode ? 'Materyal Düzenle' : 'Yeni Materyal Yükle'; ?>
                    </div>
                </div>
                
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="<?php echo $editMode ? 'update' : 'upload'; ?>">
                    <?php if ($editMode): ?>
                        <input type="hidden" name="id" value="<?php echo $editItem['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="form-group">
                        <label class="form-label">Başlık</label>
                        <input type="text" name="title" class="form-control" required placeholder="Materyal başlığı..." value="<?php echo $editMode ? htmlspecialchars($editItem['title']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="İsteğe bağlı açıklama..."><?php echo $editMode ? htmlspecialchars($editItem['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Erişim Yetkisi (Roller)</label>
                        <div class="checkbox-group">
                            <?php 
                                $currentRoles = [];
                                if ($editMode && !empty($editItem['allowed_roles'])) {
                                    $currentRoles = explode(',', $editItem['allowed_roles']);
                                }
                            ?>
                            <?php foreach ($availableRoles as $roleKey => $roleName): ?>
                                <label class="checkbox-item">
                                    <input type="checkbox" name="roles[]" value="<?php echo $roleKey; ?>" <?php echo in_array($roleKey, $currentRoles) ? 'checked' : ''; ?>>
                                    <?php echo $roleName; ?>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label"><?php echo $editMode ? 'Dosya (Değiştirmek istiyorsanız seçin)' : 'Dosya'; ?></label>
                        <input type="file" name="file" class="form-control" <?php echo $editMode ? '' : 'required'; ?>>
                        <?php if ($editMode): ?>
                            <small style="color:#666; display:block; margin-top:5px;">Mevcut dosya: <?php echo htmlspecialchars($editItem['file_path']); ?></small>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn-primary">
                        <?php echo $editMode ? 'Güncelle' : 'Yükle'; ?>
                    </button>
                    
                    <?php if ($editMode): ?>
                        <a href="training_materials.php" class="btn-secondary" style="margin-left: 10px;">İptal</a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- List Section -->
            <div class="glass-panel" style="flex:2;">
                <div class="panel-header">
                    <div class="panel-title"><i class="fas fa-list"></i> Mevcut Materyaller</div>
                </div>
                
                <div style="overflow-x:auto;">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th>Dosya</th>
                                <th>Başlık / Açıklama</th>
                                <th>Yetkili Roller</th>
                                <th>Tarih</th>
                                <th style="text-align:right;">İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($materials)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center; padding:2rem; color:#666;">Henüz materyal bulunmuyor.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($materials as $item): ?>
                                    <tr>
                                        <td>
                                            <?php 
                                                $icon = 'fa-file';
                                                $ft = $item['file_type'];
                                                if ($ft == 'pdf') $icon = 'fa-file-pdf';
                                                elseif (in_array($ft, ['doc', 'docx'])) $icon = 'fa-file-word';
                                                elseif (in_array($ft, ['xls', 'xlsx'])) $icon = 'fa-file-excel';
                                                elseif (in_array($ft, ['ppt', 'pptx'])) $icon = 'fa-file-powerpoint';
                                                elseif (in_array($ft, ['jpg', 'png'])) $icon = 'fa-file-image';
                                                elseif (in_array($ft, ['mp4', 'mov'])) $icon = 'fa-file-video';
                                            ?>
                                            <div style="width:40px; height:40px; background:#f1f5f9; border-radius:0.5rem; display:flex; align-items:center; justify-content:center; color:#64748b;">
                                                <i class="fas <?php echo $icon; ?>"></i>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-weight:600; color:#1e293b;"><?php echo htmlspecialchars($item['title']); ?></div>
                                            <div style="font-size:0.85rem; color:#64748b;"><?php echo htmlspecialchars($item['description']); ?></div>
                                        </td>
                                        <td>
                                            <?php 
                                                $itemRoles = explode(',', $item['allowed_roles']);
                                                foreach ($itemRoles as $r) {
                                                    echo '<span class="badge">' . ($availableRoles[$r] ?? $r) . '</span>';
                                                }
                                            ?>
                                        </td>
                                        <td style="font-size:0.9rem; color:#64748b;">
                                            <?php echo date('d.m.Y H:i', strtotime($item['created_at'])); ?>
                                        </td>
                                        <td style="text-align:right;">
                                            <a href="?edit=<?php echo $item['id']; ?>" class="action-icon-btn" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="../uploads/training/<?php echo $item['file_path']; ?>" target="_blank" class="action-icon-btn" title="İndir">
                                                <i class="fas fa-download"></i>
                                            </a>
                                            <a href="?delete=<?php echo $item['id']; ?>" class="action-icon-btn delete" title="Sil" onclick="return confirm('Bu materyali silmek istediğinize emin misiniz?');">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>

