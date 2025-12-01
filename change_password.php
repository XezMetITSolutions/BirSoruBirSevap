<?php
/**
 * Şifre Değiştirme Sayfası
 */

require_once 'auth.php';
require_once 'config.php';

$auth = Auth::getInstance();

// Giriş kontrolü
if (!$auth->isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$user = $auth->getUser();
$message = '';
$messageType = '';
$isForcedChange = $user['must_change_password'] ?? false;

// Şifre değiştirme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
        $message = 'Tüm alanları doldurun!';
        $messageType = 'error';
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Yeni şifreler eşleşmiyor!';
        $messageType = 'error';
    } elseif (strlen($newPassword) < 6) {
        $message = 'Yeni şifre en az 6 karakter olmalıdır!';
        $messageType = 'error';
    } else {
        // Mevcut şifre kontrolü
        $users = $auth->getUsers();
        if (isset($users[$user['username']]) && 
            password_verify($currentPassword, $users[$user['username']]['password'])) {
            
            // Şifre değiştir
            if ($auth->changePassword($user['username'], $newPassword)) {
                // Zorla değiştirme flag'ini kaldır
                if ($isForcedChange) {
                    $db = Database::getInstance();
                    $stmt = $db->getConnection()->prepare("UPDATE users SET must_change_password = 0 WHERE username = ?");
                    $stmt->execute([$user['username']]);
                    
                    $message = 'Şifreniz başarıyla değiştirildi! Artık kendi şifrenizi kullanabilirsiniz.';
                } else {
                    $message = 'Şifreniz başarıyla değiştirildi!';
                }
                $messageType = 'success';
                
                // Session'ı güncelle
                $_SESSION['user']['must_change_password'] = false;
                
                // 3 saniye sonra dashboard'a yönlendir
                header('refresh:3;url=' . ($user['role'] === 'superadmin' ? 'admin/dashboard.php' : 
                    ($user['role'] === 'teacher' ? 'teacher/dashboard.php' : 'student/dashboard.php')));
            } else {
                $message = 'Şifre değiştirilirken hata oluştu!';
                $messageType = 'error';
            }
        } else {
            $message = 'Mevcut şifre yanlış!';
            $messageType = 'error';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Değiştir - Bir Soru Bir Sevap</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }

        .container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            padding: 40px;
            border-radius: 25px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            width: 100%;
            max-width: 500px;
            position: relative;
            overflow: hidden;
        }

        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #068567, #055a4a, #077a5f, #0a8b6b);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header img {
            height: 60px;
            width: auto;
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 2.2rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.1rem;
        }

        .form-group input {
            width: 100%;
            padding: 15px 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
        }

        .form-group input:focus {
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
            width: 100%;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 8px 25px rgba(6, 133, 103, 0.3);
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

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            text-align: center;
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

        .password-requirements {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #068567;
        }

        .password-requirements h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1rem;
        }

        .password-requirements ul {
            margin: 0;
            padding-left: 20px;
            color: #7f8c8d;
            font-size: 0.9rem;
        }

        .password-requirements li {
            margin-bottom: 5px;
        }

        .user-info {
            background: #e8f4fd;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .user-info h3 {
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .user-info p {
            color: #7f8c8d;
            font-size: 0.9rem;
        }
        
        .lang-toggle {
            background: rgba(6, 133, 103, 0.1);
            border: 2px solid #068567;
            color: #068567;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            position: absolute;
            top: 20px;
            right: 20px;
        }
        
        .lang-toggle:hover {
            background: #068567;
            color: white;
        }

        @media (max-width: 768px) {
            .container {
                margin: 20px;
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 1.8rem;
            }
        }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 48px; }
        .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; font-size: 1.1rem; opacity: .75; }
        .toggle-password:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="container">
        <button id="langToggle" class="lang-toggle">DE</button>
        <div class="header">
            <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
            <?php if ($isForcedChange): ?>
                <h1 id="pageTitle">🔒 Şifre Değiştirme Zorunlu</h1>
                <p id="pageSubtitle">Eğitmeniniz tarafından yeni bir şifre belirlendi. Lütfen kendi şifrenizi oluşturun.</p>
            <?php else: ?>
                <h1 id="pageTitle">🔐 Şifre Değiştir</h1>
                <p id="pageSubtitle">Güvenliğiniz için şifrenizi değiştirin</p>
            <?php endif; ?>
        </div>

        <div class="user-info">
            <h3>👤 <?php echo htmlspecialchars($user['name']); ?></h3>
            <p>@<?php echo htmlspecialchars($user['username']); ?> • <span id="userRole"><?php echo ucfirst($user['role']); ?></span></p>
        </div>

        <?php if ($isForcedChange): ?>
            <div class="message warning" style="background: #fff3cd; color: #856404; border: 1px solid #ffeaa7;">
                <strong id="warningTitle">⚠️ Önemli:</strong> <span id="warningText">Eğitmeniniz tarafından yeni bir şifre belirlendi. 
                Güvenlik nedeniyle kendi şifrenizi oluşturmanız gerekiyor. 
                Mevcut şifre ile giriş yapıp yeni şifrenizi belirleyin.</span>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="current_password" id="labelCurrentPassword">Mevcut Şifre</label>
                <div class="password-wrapper">
                    <input type="password" id="current_password" name="current_password" required 
                           placeholder="Mevcut şifrenizi girin" id="currentPasswordPlaceholder">
                    <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                </div>
            </div>

            <div class="form-group">
                <label for="new_password" id="labelNewPassword">Yeni Şifre</label>
                <div class="password-wrapper">
                    <input type="password" id="new_password" name="new_password" required 
                           placeholder="Yeni şifrenizi girin" id="newPasswordPlaceholder">
                    <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password" id="labelConfirmPassword">Yeni Şifre (Tekrar)</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" required 
                           placeholder="Yeni şifrenizi tekrar girin" id="confirmPasswordPlaceholder">
                    <button type="button" class="toggle-password" aria-label="Şifreyi göster">👁</button>
                </div>
            </div>

            <div class="password-requirements">
                <h4 id="requirementsTitle">🔒 Şifre Gereksinimleri</h4>
                <ul>
                    <li id="req1">En az 6 karakter uzunluğunda olmalıdır</li>
                    <li id="req2">Güvenli bir şifre seçin</li>
                    <li id="req3">Şifrenizi kimseyle paylaşmayın</li>
                </ul>
            </div>

            <button type="submit" class="btn" id="btnSubmit">
                🔄 Şifremi Değiştir
            </button>
        </form>
    </div>

    <script>
        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword && newPassword !== confirmPassword) {
                this.style.borderColor = '#e74c3c';
                this.style.boxShadow = '0 0 0 4px rgba(231, 76, 60, 0.15)';
            } else {
                this.style.borderColor = '#e1e8ed';
                this.style.boxShadow = 'none';
            }
        });

        // Form gönderiminde son kontrol
        document.querySelector('form').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Yeni şifreler eşleşmiyor!');
                return false;
            }
            
            if (newPassword.length < 6) {
                e.preventDefault();
                alert('Yeni şifre en az 6 karakter olmalıdır!');
                return false;
            }
        });

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
        // Şifre göster/gizle
        document.querySelectorAll('.toggle-password').forEach(function(btn){
            btn.addEventListener('click', function(){
                const input = this.previousElementSibling;
                const isHidden = input.type === 'password';
                input.type = isHidden ? 'text' : 'password';
                this.textContent = isHidden ? '🙈' : '👁';
                this.setAttribute('aria-label', isHidden ? 'Şifreyi gizle' : 'Şifreyi göster');
            });
        });

        // TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'🔐 Şifre Değiştir', pageSubtitle:'Güvenliğiniz için şifrenizi değiştirin',
                pageTitleForced:'🔒 Şifre Değiştirme Zorunlu', pageSubtitleForced:'Eğitmeniniz tarafından yeni bir şifre belirlendi. Lütfen kendi şifrenizi oluşturun.',
                userRole:'Eğitmen', warningTitle:'⚠️ Önemli:', warningText:'Eğitmeniniz tarafından yeni bir şifre belirlendi. Güvenlik nedeniyle kendi şifrenizi oluşturmanız gerekiyor. Mevcut şifre ile giriş yapıp yeni şifrenizi belirleyin.',
                labelCurrentPassword:'Mevcut Şifre', labelNewPassword:'Yeni Şifre', labelConfirmPassword:'Yeni Şifre (Tekrar)',
                currentPasswordPlaceholder:'Mevcut şifrenizi girin', newPasswordPlaceholder:'Yeni şifrenizi girin', confirmPasswordPlaceholder:'Yeni şifrenizi tekrar girin',
                requirementsTitle:'🔒 Şifre Gereksinimleri', req1:'En az 6 karakter uzunluğunda olmalıdır', req2:'Güvenli bir şifre seçin', req3:'Şifrenizi kimseyle paylaşmayın',
                btnSubmit:'🔄 Şifremi Değiştir'
            };
            const de = {
                pageTitle:'🔐 Passwort ändern', pageSubtitle:'Ändern Sie Ihr Passwort für die Sicherheit',
                pageTitleForced:'🔒 Passwortänderung erforderlich', pageSubtitleForced:'Ihr Lehrpersonal hat ein neues Passwort festgelegt. Bitte erstellen Sie Ihr eigenes Passwort.',
                userRole:'Lehrpersonal', warningTitle:'⚠️ Wichtig:', warningText:'Ihr Lehrpersonal hat ein neues Passwort festgelegt. Aus Sicherheitsgründen müssen Sie Ihr eigenes Passwort erstellen. Melden Sie sich mit dem aktuellen Passwort an und legen Sie Ihr neues Passwort fest.',
                labelCurrentPassword:'Aktuelles Passwort', labelNewPassword:'Neues Passwort', labelConfirmPassword:'Neues Passwort (wiederholen)',
                currentPasswordPlaceholder:'Aktuelles Passwort eingeben', newPasswordPlaceholder:'Neues Passwort eingeben', confirmPasswordPlaceholder:'Neues Passwort wiederholen',
                requirementsTitle:'🔒 Passwortanforderungen', req1:'Mindestens 6 Zeichen lang', req2:'Wählen Sie ein sicheres Passwort', req3:'Teilen Sie Ihr Passwort nicht mit anderen',
                btnSubmit:'🔄 Passwort ändern'
            };
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setPlaceholder(sel, text){ const el=document.querySelector(sel); if(el) el.placeholder=text; }
            function apply(lang){
                const d = lang==='de'?de:tr;
                const isForced = document.getElementById('pageTitle').innerText.includes('Zorunlu') || document.getElementById('pageTitle').innerText.includes('erforderlich');
                setText('#pageTitle', isForced ? d.pageTitleForced : d.pageTitle);
                setText('#pageSubtitle', isForced ? d.pageSubtitleForced : d.pageSubtitle);
                setText('#userRole', d.userRole);
                setText('#warningTitle', d.warningTitle);
                setText('#warningText', d.warningText);
                setText('#labelCurrentPassword', d.labelCurrentPassword);
                setText('#labelNewPassword', d.labelNewPassword);
                setText('#labelConfirmPassword', d.labelConfirmPassword);
                setPlaceholder('#currentPasswordPlaceholder', d.currentPasswordPlaceholder);
                setPlaceholder('#newPasswordPlaceholder', d.newPasswordPlaceholder);
                setPlaceholder('#confirmPasswordPlaceholder', d.confirmPasswordPlaceholder);
                setText('#requirementsTitle', d.requirementsTitle);
                setText('#req1', d.req1);
                setText('#req2', d.req2);
                setText('#req3', d.req3);
                setText('#btnSubmit', d.btnSubmit);
                const toggle=document.getElementById('langToggle'); if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_change_password', lang);
            }
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_change_password')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle'); if(toggle){ toggle.addEventListener('click', function(){ const next=(localStorage.getItem('lang_change_password')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; apply(next); }); }
            });
        })();
    </script>
</body>
</html>
