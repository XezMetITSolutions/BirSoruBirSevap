<?php
/**
 * İlk Kurulum - Admin Kullanıcısı Oluşturma
 */

require_once 'auth.php';

$auth = Auth::getInstance();
$error = '';
$success = '';

// Admin kullanıcısı oluştur
if ($_POST['action'] ?? '' === 'create_admin') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $institution = $_POST['institution'] ?? '';
    
    if (empty($username) || empty($password) || empty($fullName) || empty($institution)) {
        $error = 'Tüm alanlar gereklidir.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Şifreler eşleşmiyor.';
    } elseif (strlen($password) < 6) {
        $error = 'Şifre en az 6 karakter olmalıdır.';
    } else {
        // Admin kullanıcısını oluştur
        if ($auth->saveUser($username, $password, 'admin', $fullName, $institution)) {
            $success = 'Admin kullanıcısı başarıyla oluşturuldu! Artık giriş yapabilirsiniz.';
        } else {
            $error = 'Admin kullanıcısı oluşturulurken bir hata oluştu.';
        }
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

// Eğer zaten admin kullanıcısı varsa login sayfasına yönlendir
$existingUsers = $auth->getAllUsers();
$hasAdmin = false;
foreach ($existingUsers as $user) {
    if ($user['role'] === 'admin') {
        $hasAdmin = true;
        break;
    }
}

if ($hasAdmin) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İlk Kurulum - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .setup-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 500px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            height: 80px;
            width: auto;
            margin-bottom: 15px;
        }

        .logo h1 {
            color: #2c3e50;
            font-size: 2.2em;
            margin-bottom: 10px;
        }

        .logo p {
            color: #7f8c8d;
            font-size: 1.1em;
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

        .form-group input {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #089b76;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #089b76 0%, #067a5f 100%);
            color: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 1.1em;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }

        .error {
            background: #e74c3c;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: #27ae60;
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .info {
            background: rgba(52, 152, 219, 0.1);
            border: 2px solid #3498db;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .info h3 {
            color: #2980b9;
            margin-bottom: 10px;
            text-align: center;
        }

        .info p {
            color: #2c3e50;
            font-size: 0.9em;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="logo">
            <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
            <h1>İlk Kurulum</h1>
            <p>Admin kullanıcısı oluşturun</p>
        </div>

        <div class="info">
            <h3>🔧 Kurulum</h3>
            <p>Bu ilk kurulum adımıdır. Admin kullanıcısı oluşturduktan sonra sistem kullanıma hazır olacaktır.</p>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="success">
                <?php echo htmlspecialchars($success); ?>
                <br><br>
                <a href="login.php" style="color: white; text-decoration: underline;">Giriş sayfasına git</a>
            </div>
        <?php else: ?>

        <form method="POST">
            <input type="hidden" name="action" value="create_admin">
            
            <div class="form-group">
                <label for="username">Admin Kullanıcı Adı:</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Kullanıcı adınızı girin">
            </div>

                         <div class="form-group">
                 <label for="full_name">Ad Soyad:</label>
                 <input type="text" id="full_name" name="full_name" required 
                        placeholder="Adınızı ve soyadınızı girin">
             </div>

             <div class="form-group">
                 <label for="institution">Kurum:</label>
                 <select id="institution" name="institution" required>
                     <option value="">Kurum Seçin</option>
                     <?php foreach ($institutions as $institution): ?>
                         <option value="<?php echo htmlspecialchars($institution); ?>">
                             <?php echo htmlspecialchars($institution); ?>
                         </option>
                     <?php endforeach; ?>
                 </select>
             </div>

            <div class="form-group">
                <label for="password">Şifre:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="En az 6 karakter">
            </div>

            <div class="form-group">
                <label for="confirm_password">Şifre Tekrar:</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Şifrenizi tekrar girin">
            </div>

            <button type="submit" class="btn">Admin Kullanıcısı Oluştur</button>
        </form>

        <?php endif; ?>
    </div>
</body>
</html>
