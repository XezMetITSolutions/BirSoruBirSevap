<?php
/**
 * Arlberg Bölgesi - Eğitmen Kayıt Formu
 */

require_once 'auth.php';
require_once 'config.php';
require_once 'database.php';
require_once 'admin/includes/locations.php';

$auth = Auth::getInstance();
$error = '';
$success = '';
$registeredUser = null;
$registeredPassword = '';

// Arlberg bölgesi şubeleri
$arlbergBranches = $regionConfig['Arlberg'] ?? [];

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $branch = trim($_POST['branch'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    
    // Validasyon
    if (empty($firstName) || empty($lastName)) {
        $error = 'Ad ve Soyad alanları zorunludur.';
    } elseif (empty($branch) || !in_array($branch, $arlbergBranches)) {
        $error = 'Lütfen geçerli bir kurum seçin.';
    } elseif (empty($email)) {
        $error = 'E-posta adresi zorunludur.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Geçerli bir e-posta adresi girin.';
    } else {
        try {
            // Tam adı oluştur
            $fullName = $firstName . ' ' . $lastName;
            
            // Kullanıcı adını otomatik oluştur
            // Önce Türkçe karakterleri dönüştür
            $mapSearch = ['Ü','ü','Ö','ö','Ğ','ğ','Ş','ş','Ç','ç','İ','I','ı'];
            $mapReplace = ['ue','ue','oe','oe','g','g','s','s','c','c','i','i','i'];
            $lastNameConverted = str_replace($mapSearch, $mapReplace, $lastName);
            $firstNameConverted = str_replace($mapSearch, $mapReplace, $firstName);
            
            // Eğitmenler için format: soyad.ad (öğrencilerle aynı)
            $lastNamePart = strlen($lastNameConverted) >= 5 ? substr($lastNameConverted, 0, 5) : $lastNameConverted;
            $firstNamePart = substr($firstNameConverted, 0, 3);
            $baseUsername = strtolower($lastNamePart . '.' . $firstNamePart);
            
            // Kullanıcı adı benzersiz olana kadar sayı ekle
            $username = $baseUsername;
            $counter = 1;
            $existingUsers = $auth->getAllUsers();
            
            while (isset($existingUsers[$username])) {
                $username = $baseUsername . $counter;
                $counter++;
            }
            
            // Standart şifre
            $password = 'iqra2025#';
            
            // Bölge bilgisi
            $region = 'Arlberg';
            
            // Kullanıcıyı teacher rolü ile kaydet
            if ($auth->saveUser($username, $password, 'teacher', $fullName, $branch, '', $email, $phone, $region)) {
                $registeredUser = $username;
                $registeredPassword = $password;
                $success = 'Eğitmen kaydı başarıyla tamamlandı!';
            } else {
                $error = 'Kayıt sırasında bir hata oluştu. Lütfen tekrar deneyin.';
            }
        } catch (Exception $e) {
            $error = 'Kayıt sırasında bir hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eğitmen Kayıt - Arlberg Bölgesi</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3b82f6;
            --primary-dark: #2563eb;
            --secondary: #8b5cf6;
            --text-color: #1e293b;
            --text-muted: #64748b;
            --bg-gradient-start: #0f172a;
            --bg-gradient-end: #1e293b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: radial-gradient(circle at top right, #1e3a8a 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        .bg-decoration {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            pointer-events: none;
            z-index: -1;
            overflow: hidden;
        }

        .blob {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            opacity: 0.4;
            animation: float 20s infinite ease-in-out;
        }

        .blob-1 {
            top: -10%;
            left: -10%;
            width: 600px;
            height: 600px;
            background: var(--primary);
        }

        .blob-2 {
            bottom: -10%;
            right: -10%;
            width: 500px;
            height: 500px;
            background: var(--secondary);
            animation-delay: -5s;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        .register-container {
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            width: 100%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .register-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .register-header img {
            height: 60px;
            width: auto;
            margin-bottom: 16px;
            filter: drop-shadow(0 0 10px rgba(59, 130, 246, 0.4));
        }

        .register-header h1 {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 8px;
        }

        .register-header p {
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .instructor-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2) 0%, rgba(139, 92, 246, 0.2) 100%);
            color: #93c5fd;
            padding: 10px 20px;
            border-radius: 12px;
            border: 1px solid rgba(59, 130, 246, 0.3);
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 12px;
        }

        .instructor-badge i {
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            color: #fff;
            font-weight: 600;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        .form-group label .required {
            color: #ef4444;
            margin-left: 4px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            font-size: 1rem;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder {
            color: #64748b;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        .username-preview {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-family: 'Courier New', monospace;
            color: #60a5fa;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .username-preview i {
            font-size: 1.2rem;
        }

        .username-preview strong {
            color: #fff;
            margin-right: 8px;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.3s ease;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .alert-success {
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.3);
            color: #86efac;
        }

        .credentials-box {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
            border: 2px solid var(--primary);
            border-radius: 16px;
            padding: 24px;
            margin-top: 24px;
        }

        .credentials-box h3 {
            color: #fff;
            font-size: 1.2rem;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .credential-item {
            background: rgba(0, 0, 0, 0.3);
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .credential-item:last-child {
            margin-bottom: 0;
        }

        .credential-label {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .credential-value {
            color: #fff;
            font-family: 'Courier New', monospace;
            font-weight: 700;
            font-size: 1rem;
        }

        .copy-btn {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 6px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.85rem;
            transition: all 0.2s;
            margin-left: 12px;
        }

        .copy-btn:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .login-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .login-link a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
            transition: all 0.2s;
        }

        .login-link a:hover {
            color: #60a5fa;
            text-decoration: underline;
        }

        .info-text {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            padding: 12px 16px;
            border-radius: 10px;
            color: #60a5fa;
            font-size: 0.85rem;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .warning-text {
            background: rgba(251, 191, 36, 0.1);
            border: 1px solid rgba(251, 191, 36, 0.2);
            padding: 12px 16px;
            border-radius: 10px;
            color: #fbbf24;
            font-size: 0.85rem;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .warning-text i {
            margin-top: 2px;
        }
    </style>
</head>
<body>
    <div class="bg-decoration">
        <div class="blob blob-1"></div>
        <div class="blob blob-2"></div>
    </div>

    <div class="register-container">
        <div class="register-header">
            <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
            <h1>Eğitmen Kayıt</h1>
            <p>Arlberg Bölgesi</p>
            <div class="instructor-badge">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Eğitmen Kaydı - Arlberg</span>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success && $registeredUser): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
            </div>

            <div class="credentials-box">
                <h3>
                    <i class="fas fa-key"></i>
                    Eğitmen Giriş Bilgileri
                </h3>
                <div class="credential-item">
                    <span class="credential-label">👤 Kullanıcı Adı:</span>
                    <div style="display: flex; align-items: center;">
                        <span class="credential-value" id="username-display"><?php echo htmlspecialchars($registeredUser); ?></span>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($registeredUser); ?>', this)">
                            <i class="fas fa-copy"></i> Kopyala
                        </button>
                    </div>
                </div>
                <div class="credential-item">
                    <span class="credential-label">🔑 Şifre:</span>
                    <div style="display: flex; align-items: center;">
                        <span class="credential-value" id="password-display"><?php echo htmlspecialchars($registeredPassword); ?></span>
                        <button class="copy-btn" onclick="copyToClipboard('<?php echo htmlspecialchars($registeredPassword); ?>', this)">
                            <i class="fas fa-copy"></i> Kopyala
                        </button>
                    </div>
                </div>
                <div class="info-text" style="margin-top: 16px; margin-bottom: 0;">
                    <i class="fas fa-info-circle"></i>
                    <span><strong>Önemli:</strong> Bu bilgileri güvenli bir yerde saklayın. İlk girişte şifrenizi değiştirmeniz önerilir.</span>
                </div>
            </div>

            <div class="login-link">
                <a href="login.php">
                    <i class="fas fa-sign-in-alt"></i> Giriş Sayfasına Git
                </a>
            </div>
        <?php else: ?>
            <div class="info-text">
                <i class="fas fa-info-circle"></i>
                <span>Arlberg bölgesi eğitmenleri için kayıt formu. Lütfen tüm zorunlu alanları doldurun.</span>
            </div>

            <div class="warning-text">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>Dikkat:</strong> Bu form sadece Arlberg bölgesi eğitmenleri içindir. 
                    Kullanıcı adınız öğrencilerle aynı formatta (soyad.ad) oluşturulacaktır.
                </div>
            </div>

            <form method="POST" id="registerForm">
                <input type="hidden" name="action" value="register">
                
                <div class="form-group">
                    <label for="first_name">
                        Ad <span class="required">*</span>
                    </label>
                    <input type="text" id="first_name" name="first_name" required 
                           placeholder="Örn: Ahmet" 
                           value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>"
                           oninput="updateUsernamePreview()">
                </div>

                <div class="form-group">
                    <label for="last_name">
                        Soyad <span class="required">*</span>
                    </label>
                    <input type="text" id="last_name" name="last_name" required 
                           placeholder="Örn: Yılmaz"
                           value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>"
                           oninput="updateUsernamePreview()">
                </div>

                <div class="username-preview" id="username-preview" style="display: none;">
                    <i class="fas fa-info-circle"></i>
                    <div>
                        <strong>Oluşturulacak Kullanıcı Adı:</strong>
                        <span id="preview-username">...</span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="branch">
                        Kurum (Şube) <span class="required">*</span>
                    </label>
                    <select id="branch" name="branch" required>
                        <option value="">Kurum Seçin</option>
                        <?php foreach ($arlbergBranches as $branch): ?>
                            <option value="<?php echo htmlspecialchars($branch); ?>" 
                                    <?php echo (isset($_POST['branch']) && $_POST['branch'] === $branch) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($branch); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="email">
                        E-posta <span class="required">*</span>
                    </label>
                    <input type="email" id="email" name="email" required
                           placeholder="ornek@email.com"
                           value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label for="phone">
                        Telefon (Opsiyonel)
                    </label>
                    <input type="tel" id="phone" name="phone" 
                           placeholder="+43 123 456 7890"
                           value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>">
                </div>

                <button type="submit" class="btn">
                    <i class="fas fa-user-plus"></i> Eğitmen Kaydı Oluştur
                </button>
            </form>

            <div class="login-link">
                Zaten hesabınız var mı? <a href="login.php">Giriş Yap</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateUsernamePreview() {
            const firstName = document.getElementById('first_name').value.trim();
            const lastName = document.getElementById('last_name').value.trim();
            const previewDiv = document.getElementById('username-preview');
            const previewSpan = document.getElementById('preview-username');
            
            if (firstName && lastName) {
                const mapPairs = [
                    ['Ü','ue'], ['ü','ue'], ['Ö','oe'], ['ö','oe'],
                    ['Ğ','g'], ['ğ','g'], ['Ş','s'], ['ş','s'],
                    ['Ç','c'], ['ç','c'], ['İ','i'], ['I','i'], ['ı','i']
                ];
                
                // Önce Türkçe karakterleri dönüştür
                let lastNameConverted = lastName;
                let firstNameConverted = firstName;
                
                mapPairs.forEach(([ch, repl]) => {
                    lastNameConverted = lastNameConverted.split(ch).join(repl);
                    firstNameConverted = firstNameConverted.split(ch).join(repl);
                });
                
                // Eğitmen formatı: soyad.ad (öğrencilerle aynı)
                let lastPart = lastNameConverted.length >= 5 ? lastNameConverted.substring(0, 5) : lastNameConverted;
                let firstPart = firstNameConverted.substring(0, 3);
                
                const username = (lastPart + '.' + firstPart).toLowerCase();
                previewSpan.textContent = username;
                previewDiv.style.display = 'flex';
            } else {
                previewDiv.style.display = 'none';
            }
        }

        function copyToClipboard(text, button) {
            navigator.clipboard.writeText(text).then(function() {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-check"></i> Kopyalandı!';
                button.style.background = 'rgba(34, 197, 94, 0.2)';
                button.style.borderColor = 'rgba(34, 197, 94, 0.3)';
                
                setTimeout(() => {
                    button.innerHTML = originalText;
                    button.style.background = 'rgba(255, 255, 255, 0.1)';
                    button.style.borderColor = 'rgba(255, 255, 255, 0.2)';
                }, 2000);
            }).catch(function(err) {
                alert('Kopyalama başarısız: ' + err);
            });
        }

        // Sayfa yüklendiğinde preview'ı kontrol et
        document.addEventListener('DOMContentLoaded', function() {
            updateUsernamePreview();
        });
    </script>
</body>
</html>
