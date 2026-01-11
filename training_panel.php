<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'database.php';

$auth = Auth::getInstance();
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getUser();
$userRole = $user['role'];
$userName = $user['name'];

// Fix: Superadmin should see everything, others only their allowed role
$db = Database::getInstance();
$conn = $db->getConnection();

$materials = [];
try {
    // Check if table exists to avoid crash if not yet created
    $conn->query("SELECT 1 FROM training_materials LIMIT 1");
    
    if ($userRole === 'superadmin' || $userRole === 'admin') {
        $stmt = $conn->query("SELECT * FROM training_materials ORDER BY created_at DESC");
    } else {
        // Use LIKE for simple string matching in comma-separated list
        $stmt = $conn->prepare("SELECT * FROM training_materials WHERE allowed_roles LIKE ? ORDER BY created_at DESC");
        $stmt->execute(['%' . $userRole . '%']);
    }
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table might not exist or error
    $materials = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitim Materyalleri</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #068567;
            --secondary: #f8f9fa;
            --dark: #2c3e50;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        body {
            font-family: 'Inter', sans-serif;
            background: #f0f2f5;
            margin: 0;
            color: var(--dark);
        }
        .header {
            background: linear-gradient(135deg, var(--primary) 0%, #055a4a 100%);
            color: white;
            padding: 1rem 0;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .logo { font-size: 1.5rem; font-weight: 700; display: flex; align-items: center; gap: 0.5rem; color: #fff; text-decoration: none; }
        .back-btn {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: 0.3s;
        }
        .back-btn:hover { background: rgba(255,255,255,0.3); }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            padding-bottom: 2rem;
        }
        .card {
            background: white;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: transform 0.3s ease;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
        }
        .card:hover { transform: translateY(-5px); }
        .card-icon {
            height: 140px;
            background: #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 4rem;
            color: var(--primary);
        }
        .card-body { padding: 1.5rem; flex: 1; display: flex; flex-direction: column; }
        .card-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
        .card-desc { color: #64748b; font-size: 0.9rem; margin-bottom: 1rem; flex: 1; }
        .card-meta { font-size: 0.8rem; color: #94a3b8; display: flex; justify-content: space-between; align-items: center; }
        .btn-download {
            background: var(--primary);
            color: white;
            text-align: center;
            padding: 0.75rem;
            font-weight: 600;
            transition: 0.3s;
        }
        .btn-download:hover { background: #055a4a; }
        .empty-state { text-align: center; padding: 4rem; color: #64748b; grid-column: 1/-1; }
    </style>
</head>
<body>
    <div class="header">
        <div class="container header-content">
            <a href="index.php" class="logo">
                <i class="fas fa-book-open"></i> Eğitim Paneli
            </a>
            <div style="display:flex; align-items:center; gap:1rem;">
                <span><?php echo htmlspecialchars($userName); ?></span>
                <a href="javascript:history.back()" class="back-btn"><i class="fas fa-arrow-left"></i> Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="grid">
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open" style="font-size:3rem; margin-bottom:1rem; opacity:0.5;"></i>
                    <h3>Henüz materyal bulunmuyor.</h3>
                    <p>Size uygun eğitim materyalleri eklendiğinde burada görünecektir.</p>
                </div>
            <?php else: ?>
                <?php foreach ($materials as $item): ?>
                    <?php 
                        $icon = 'fa-file';
                        if (in_array($item['file_type'], ['pdf'])) $icon = 'fa-file-pdf';
                        elseif (in_array($item['file_type'], ['doc', 'docx'])) $icon = 'fa-file-word';
                        elseif (in_array($item['file_type'], ['xls', 'xlsx'])) $icon = 'fa-file-excel';
                        elseif (in_array($item['file_type'], ['ppt', 'pptx'])) $icon = 'fa-file-powerpoint';
                        elseif (in_array($item['file_type'], ['jpg', 'png', 'jpeg'])) $icon = 'fa-file-image';
                        elseif (in_array($item['file_type'], ['mp4', 'mov'])) $icon = 'fa-file-video';
                    ?>
                    <a href="uploads/training/<?php echo $item['file_path']; ?>" class="card" target="_blank">
                        <div class="card-icon">
                            <i class="fas <?php echo $icon; ?>"></i>
                        </div>
                        <div class="card-body">
                            <div class="card-title"><?php echo htmlspecialchars($item['title']); ?></div>
                            <div class="card-desc"><?php echo htmlspecialchars($item['description']); ?></div>
                            <div class="card-meta">
                                <span><i class="fas fa-clock"></i> <?php echo date('d.m.Y', strtotime($item['created_at'])); ?></span>
                                <span><?php echo strtoupper($item['file_type']); ?></span>
                            </div>
                        </div>
                        <div class="btn-download">
                            <i class="fas fa-download"></i> İndir / Görüntüle
                        </div>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
