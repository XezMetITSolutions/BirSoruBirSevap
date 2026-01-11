<?php
/**
 * Giriş Sayfası
 */

require_once 'auth.php';

$auth = Auth::getInstance();

// Zaten giriş yapmışsa yönlendir
if ($auth->isLoggedIn()) {
    $auth->redirectToRole();
}

$error = '';
$rememberedUsername = isset($_COOKIE['remember_username']) ? $_COOKIE['remember_username'] : '';

if ($_POST) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === 'on';
    // Veritabanından kullanıcıyı kontrol et
    try {
        // Kullanıcıyı bul
        $user = $auth->getUserByUsernameOrEmail($username);
        $userRole = null;
        $realUsername = null;
        
        if ($user) {
            $userRole = $user['role'];
            $realUsername = $user['username'];
        }
        
        if ($realUsername && $auth->login($realUsername, $password, $userRole)) {
            // Session'ı yenile (timeout'u sıfırla)
            $_SESSION['last_activity'] = time();
            $_SESSION['refresh_time'] = time();
            $_SESSION['login_time'] = time();
            
            // Beni hatırla
            if ($remember) {
                setcookie('remember_username', $username, time() + (60 * 60 * 24 * 30), '/'); // 30 gün
                // Otomatik giriş için hafif kalıcı cookie (demo amaçlı)
                $payload = base64_encode(json_encode(['u'=>$username, 'r'=>$userRole]));
                setcookie('remember_login', $payload, time() + (60 * 60 * 24 * 30), '/');
            } else {
                if (isset($_COOKIE['remember_username'])) {
                    setcookie('remember_username', '', time() - 3600, '/');
                }
                if (isset($_COOKIE['remember_login'])) {
                    setcookie('remember_login', '', time() - 3600, '/');
                }
            }
            // Şifre değiştirme kontrolü
            $user = $auth->getUser();
            if ($user && ($user['must_change_password'] ?? false)) {
                header('Location: change_password.php');
                exit;
            }
            $auth->redirectToRole();
        } else {
            $error = 'Kullanıcı adı veya şifre hatalı!';
        }
    } catch (Exception $e) {
        $error = 'Veritabanı bağlantı hatası! Lütfen veritabanını kurun.';
        error_log("Login error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş - Bir Soru Bir Sevap</title>
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
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo img {
            height: 60px;
            width: auto;
            margin-bottom: 15px;
        }

        .logo h1 {
            color: #2c3e50;
            font-size: 2em;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #068567;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
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

        .demo-accounts {
            margin-top: 30px;
            padding: 20px;
            background: rgba(52, 152, 219, 0.1);
            border-radius: 10px;
            border: 2px solid #3498db;
        }

        .demo-accounts h3 {
            color: #2980b9;
            margin-bottom: 15px;
            text-align: center;
        }

        .demo-account {
            margin-bottom: 10px;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border-left: 4px solid #3498db;
        }

        .demo-account strong {
            color: #2c3e50;
        }

        .demo-account span {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        .password-wrapper { position: relative; }
        .password-wrapper input { padding-right: 44px; }
        .toggle-password { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: transparent; border: none; cursor: pointer; font-size: 1em; opacity: .7; }
        .toggle-password:hover { opacity: 1; }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <a href="index.php" style="text-decoration: none; color: inherit;">
                <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
                <h1>Bir Soru Bir Sevap</h1>
                <p id="loginSub">Modern Eğitim Platformu</p>
            </a>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username" id="labelUser">Kullanıcı Adı veya E-posta:</label>
                <input type="text" id="username" name="username" required 
                       placeholder="Kullanıcı adı veya e-posta girin" id="phUser" value="<?php echo htmlspecialchars($rememberedUsername); ?>">
            </div>

            <div class="form-group">
                <label for="password" id="labelPass">Şifre:</label>
                <input type="password" id="password" name="password" required 
                       placeholder="Şifrenizi girin" id="phPass">
            </div>

            <div class="form-group" style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" id="remember" name="remember" <?php echo $rememberedUsername ? 'checked' : ''; ?>>
                <label for="remember" id="labelRemember" style="margin:0;cursor:pointer;">Beni hatırla</label>
            </div>

            <button type="submit" class="btn" id="btnLogin">Giriş Yap</button>
        </form>

        <div class="demo-accounts">
            <h3 id="infoTitle">💡 Bilgi</h3>
            <div class="demo-account">
                <strong id="createLabel">Hesap Oluşturma:</strong> <span id="createText">Yeni hesap oluşturmak için eğitmeninizle iletişime geçin.</span>
            </div>
        </div>
    </div>
    <script>
        // Şifre göster/gizle
        (function(){
            document.querySelectorAll('input[type="password"]').forEach(function(input){
                const wrapper = document.createElement('div');
                wrapper.className = 'password-wrapper';
                input.parentNode.insertBefore(wrapper, input);
                wrapper.appendChild(input);

                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'toggle-password';
                btn.setAttribute('aria-label','Şifreyi göster');
                btn.textContent = '👁';
                wrapper.appendChild(btn);

                btn.addEventListener('click', function(){
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    btn.textContent = isHidden ? '🙈' : '👁';
                    btn.setAttribute('aria-label', isHidden ? 'Şifreyi gizle' : 'Şifreyi göster');
                });
            });
        })();
        // Dil uygulaması (localStorage.lang)
        (function(){
            const tr = { sub:'Modern Eğitim Platformu', user:'Kullanıcı Adı veya E-posta:', pass:'Şifre:', phUser:'Kullanıcı adı veya e-posta girin', phPass:'Şifrenizi girin', login:'Giriş Yap', info:'💡 Bilgi', createLabel:'Hesap Oluşturma:', createText:'Yeni hesap oluşturmak için eğitmeninizle iletişime geçin.' };
            const de = { sub:'Moderne Lernplattform', user:'Benutzername oder E-Mail:', pass:'Passwort:', phUser:'Benutzernamen oder E-Mail eingeben', phPass:'Passwort eingeben', login:'Anmelden', info:'💡 Hinweis', createLabel:'Kontoerstellung:', createText:'Für ein neues Konto wenden Sie sich an Ihre Lehrkraft.' };
            const trRemember = 'Beni hatırla';
            const deRemember = 'Angemeldet bleiben';
            function setText(sel, text){ const el=document.querySelector(sel); if(!el) return; if(el.tagName==='INPUT'){ el.setAttribute('placeholder', text); } else { el.textContent = text; } }
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang')||'tr'; const d = lang==='de'?de:tr;
                setText('#loginSub', d.sub);
                setText('#labelUser', d.user);
                setText('#labelPass', d.pass);
                setText('#phUser', d.phUser);
                setText('#phPass', d.phPass);
                setText('#btnLogin', d.login);
                setText('#infoTitle', d.info);
                setText('#createLabel', d.createLabel);
                setText('#createText', d.createText);
                const rem = document.getElementById('labelRemember'); if (rem) rem.textContent = (lang==='de'?deRemember:trRemember);
                // LocalStorage fallback: kullanıcı adını hatırla
                try {
                    const storedUser = localStorage.getItem('remember_username');
                    const userInput = document.getElementById('username');
                    const rememberCb = document.getElementById('remember');
                    if (storedUser && userInput) {
                        if (!userInput.value) userInput.value = storedUser;
                        if (rememberCb) rememberCb.checked = true;
                    }
                    const form = document.querySelector('form');
                    if (form) {
                        form.addEventListener('submit', function(){
                            if (rememberCb && rememberCb.checked && userInput) {
                                localStorage.setItem('remember_username', userInput.value || '');
                            } else {
                                localStorage.removeItem('remember_username');
                            }
                        });
                    }
                } catch(e) { /* ignore */ }
            });
        })();
    </script>
</body>
</html>
