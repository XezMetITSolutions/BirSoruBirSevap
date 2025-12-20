<?php
/**
 * Veritabanı Kurulum ve Migrasyon Sayfası
 */

require_once 'database.php';
require_once 'auth.php';

// Güvenlik kontrolü: Sadece superadmin veya CLI erişimi
if (php_sapi_name() !== 'cli') {
    $auth = Auth::getInstance();
    if (!$auth->isLoggedIn() || !$auth->hasRole('superadmin')) {
        // Eğer hiç kullanıcı yoksa izin ver (ilk kurulum)
        try {
            $db = Database::getInstance();
            $users = $db->getAllUsers();
            if (!empty($users)) {
                die('Erişim engellendi: Sadece Super Admin bu sayfaya erişebilir.');
            }
        } catch (Exception $e) {
            // Veritabanı hatası varsa (yani veritabanı yoksa) devam et
        }
    }
}

$message = '';
$messageType = '';

if ($_POST['action'] ?? '' === 'setup_database') {
    try {
        $db = Database::getInstance();
        
        // Tabloyu oluştur
        if ($db->createUsersTable()) {
            $message .= "✅ Kullanıcı tablosu başarıyla oluşturuldu!<br>";
        } else {
            $message .= "❌ Kullanıcı tablosu oluşturulamadı!<br>";
            $messageType = 'error';
        }
        
        // JSON verilerini aktar
        $migratedCount = $db->migrateFromJSON();
        if ($migratedCount > 0) {
            $message .= "✅ $migratedCount kullanıcı JSON'dan veritabanına aktarıldı!<br>";
        } else {
            $message .= "⚠️ Aktarılacak kullanıcı bulunamadı veya zaten mevcut!<br>";
        }
        
        if (empty($messageType)) {
            $messageType = 'success';
        }
        
    } catch (Exception $e) {
        $message = "❌ Hata: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Mevcut kullanıcıları kontrol et
$currentUsers = [];
try {
    $db = Database::getInstance();
    $currentUsers = $db->getAllUsers();
} catch (Exception $e) {
    // Veritabanı henüz kurulmamış
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Veritabanı Kurulum - Bir Soru Bir Sevap</title>
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
            padding: 20px;
            color: #333;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            max-width: 800px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header img {
            height: 60px;
            width: auto;
            margin-bottom: 20px;
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.2rem;
            margin-bottom: 10px;
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

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            margin: 10px 5px;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .info-box {
            background: #e8f4fd;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid #3498db;
        }

        .info-box h3 {
            color: #2980b9;
            margin-bottom: 10px;
        }

        .users-list {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
        }

        .user-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .user-info h4 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .user-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .role-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .role-superadmin {
            background: #e74c3c;
            color: white;
        }

        .role-teacher {
            background: #f39c12;
            color: white;
        }

        .role-student {
            background: #27ae60;
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
            <h1>🗄️ Veritabanı Kurulum</h1>
            <p>MySQL veritabanını kurun ve mevcut verileri aktarın</p>
        </div>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            <h3>📋 Veritabanı Bilgileri</h3>
            <p><strong>Host:</strong> localhost</p>
            <p><strong>Veritabanı:</strong> d0459a94</p>
            <p><strong>Kullanıcı:</strong> d0459a94</p>
            <p><strong>Şifre:</strong> 1528797Mb</p>
        </div>

        <?php if (empty($currentUsers)): ?>
            <div class="info-box">
                <h3>⚠️ Veritabanı Henüz Kurulmamış</h3>
                <p>Veritabanı tablosunu oluşturmak ve mevcut JSON verilerini aktarmak için aşağıdaki butona tıklayın.</p>
            </div>

            <form method="POST" style="text-align: center;">
                <input type="hidden" name="action" value="setup_database">
                <button type="submit" class="btn">
                    🚀 Veritabanını Kur ve Verileri Aktar
                </button>
            </form>
        <?php else: ?>
            <div class="info-box">
                <h3>✅ Veritabanı Hazır!</h3>
                <p>Veritabanı başarıyla kurulmuş ve <strong><?php echo count($currentUsers); ?></strong> kullanıcı aktarılmış.</p>
            </div>

            <div class="users-list">
                <h3>👥 Mevcut Kullanıcılar</h3>
                <?php foreach ($currentUsers as $user): ?>
                    <div class="user-item">
                        <div class="user-info">
                            <h4><?php echo htmlspecialchars($user['name']); ?></h4>
                            <p>@<?php echo htmlspecialchars($user['username']); ?> • <?php echo htmlspecialchars($user['institution']); ?></p>
                        </div>
                        <span class="role-badge role-<?php echo $user['role']; ?>">
                            <?php echo ucfirst($user['role']); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="admin/users.php" class="btn">👥 Kullanıcı Yönetimi</a>
                <a href="admin/dashboard.php" class="btn">🏠 Dashboard</a>
                <a href="login.php" class="btn">🔐 Giriş Yap</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
