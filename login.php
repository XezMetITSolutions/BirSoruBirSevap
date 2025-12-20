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
        $users = $auth->getAllUsers();
        $userRole = null;
        
        if (isset($users[$username]) && isset($users[$username]['role'])) {
            $userRole = $users[$username]['role'];
        }
        
        if ($userRole && $auth->login($username, $password, $userRole)) {
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #068567;
            --primary-dark: #055a4a;
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
            background: radial-gradient(circle at top left, #1a2942 0%, #0f172a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            position: relative;
        }

        /* Decorative background elements */
        .decoration {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: 0;
            animation: float 20s infinite ease-in-out;
            opacity: 0.6;
        }
        .decoration.one {
            top: -10%;
            left: -10%;
            width: 500px;
            height: 500px;
            background: #068567;
            animation-delay: 0s;
        }
        .decoration.two {
            bottom: -15%;
            right: -5%;
            width: 400px;
            height: 400px;
            background: #2563eb;
            animation-delay: -10s;
        }
        
        @keyframes float {
            0%, 100% { transform: translate(0, 0); }
            50% { transform: translate(30px, -30px); }
        }

        .login-container {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 48px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            width: 100%;
            max-width: 420px;
            position: relative;
            z-index: 10;
            animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(40px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo img {
            height: 70px;
            width: auto;
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.1));
            transition: transform 0.3s ease;
        }
        
        .logo img:hover { transform: scale(1.05); }

        .logo h1 {
            color: #ffffff;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .logo p {
            color: #94a3b8;
            font-size: 1rem;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 24px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.9rem;
            color: #e2e8f0;
            padding-left: 4px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon {
            position: absolute;
            left: 16px;
            color: #94a3b8;
            pointer-events: none;
            transition: color 0.3s ease;
        }
        
        .form-group input:focus ~ .input-icon {
            color: #068567;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px 14px 48px; /* Space for icon */
            background: rgba(30, 41, 59, 0.6);
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 12px;
            font-size: 1rem;
            color: #ffffff;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group input::placeholder { color: #64748b; }

        .form-group input:focus {
            outline: none;
            border-color: #068567;
            background: rgba(30, 41, 59, 0.9);
            box-shadow: 0 0 0 3px rgba(6, 133, 103, 0.2);
        }

        .toggle-password {
            position: absolute;
            right: 16px;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #94a3b8;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .toggle-password:hover { color: #ffffff; background: rgba(255,255,255,0.1); }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 24px;
        }
        
        .checkbox-group input[type="checkbox"] {
            appearance: none;
            -webkit-appearance: none;
            width: 20px;
            height: 20px;
            border: 2px solid #64748b;
            border-radius: 6px;
            background: transparent;
            cursor: pointer;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .checkbox-group input[type="checkbox"]:checked {
            background-color: #068567;
            border-color: #068567;
        }
        
        .checkbox-group input[type="checkbox"]:checked::after {
            content: '✓';
            position: absolute;
            color: white;
            font-size: 14px;
            left: 3px;
            top: -1px;
            font-weight: bold;
        }

        .checkbox-group label {
            margin: 0;
            color: #cbd5e1;
            font-size: 0.95rem;
            cursor: pointer;
        }

        .btn {
            width: 100%;
            background: linear-gradient(135deg, #068567 0%, #047857 100%);
            color: white;
            border: none;
            padding: 16px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 1.05rem;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            background: linear-gradient(rgba(255,255,255,0.2), transparent);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -10px rgba(6, 133, 103, 0.6);
        }
        
        .btn:hover::before { opacity: 1; }
        .btn:active { transform: translateY(0); }

        .error {
            background: rgba(220, 38, 38, 0.2);
            border: 1px solid rgba(220, 38, 38, 0.4);
            color: #fca5a5;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 24px;
            text-align: center;
            font-size: 0.95rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both;
        }
        
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        .demo-accounts {
            margin-top: 32px;
            padding: 20px;
            background: rgba(37, 99, 235, 0.1);
            border: 1px solid rgba(37, 99, 235, 0.2);
            border-radius: 16px;
        }

        .demo-accounts h3 {
            color: #60a5fa;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .demo-account {
            color: #cbd5e1;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .demo-account strong { color: #fff; }
        .demo-account span { color: #94a3b8; }
        
        /* Icons */
        svg { width: 20px; height: 20px; }
    </style>
</head>
<body>
    <div class="decoration one"></div>
    <div class="decoration two"></div>
    
    <div class="login-container">
        <div class="logo">
            <a href="index.php" style="text-decoration: none; display: block;">
                <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
                <h1>Bir Soru Bir Sevap</h1>
                <p id="loginSub">Modern Eğitim Platformu</p>
            </a>
        </div>

        <?php if ($error): ?>
            <div class="error">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="username" id="labelUser">Kullanıcı Adı</label>
                <div class="input-wrapper">
                    <svg class="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <input type="text" id="username" name="username" required 
                           placeholder="Kullanıcı adınızı girin" id="phUser" value="<?php echo htmlspecialchars($rememberedUsername); ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="password" id="labelPass">Şifre</label>
                <div class="input-wrapper">
                    <svg class="input-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                    </svg>
                    <input type="password" id="password" name="password" required 
                           placeholder="Şifrenizi girin" id="phPass">
                    <button type="button" class="toggle-password" aria-label="Şifreyi göster">
                        <svg class="eye-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="checkbox-group">
                <input type="checkbox" id="remember" name="remember" <?php echo $rememberedUsername ? 'checked' : ''; ?>>
                <label for="remember" id="labelRemember">Beni hatırla</label>
            </div>

            <button type="submit" class="btn" id="btnLogin">Giriş Yap</button>
        </form>

        <div class="demo-accounts">
            <h3 id="infoTitle">
                <svg fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                Bilgi
            </h3>
            <div class="demo-account">
                <strong id="createLabel">Hesap Oluşturma:</strong> <br>
                <span id="createText">Yeni bir hesap oluşturmak için lütfen eğitmeniniz veya sistem yöneticisi ile iletişime geçin.</span>
            </div>
        </div>
    </div>
    <script>
        // Şifre göster/gizle
        (function(){
            const btn = document.querySelector('.toggle-password');
            const input = document.getElementById('password');
            
            if(btn && input) {
                btn.addEventListener('click', function(){
                    const isHidden = input.type === 'password';
                    input.type = isHidden ? 'text' : 'password';
                    
                    // Icon değişimi (simple SVG swap logic)
                    if(isHidden) {
                        btn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" /></svg>`;
                        btn.setAttribute('aria-label', 'Şifreyi gizle');
                        btn.style.opacity = '1';
                    } else {
                        btn.innerHTML = `<svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" /></svg>`;
                        btn.setAttribute('aria-label', 'Şifreyi göster');
                        btn.style.opacity = '0.7';
                    }
                });
            }
        })();

        // Dil uygulaması (localStorage.lang)
        (function(){
            const tr = { sub:'Modern Eğitim Platformu', user:'Kullanıcı Adı', pass:'Şifre', phUser:'Kullanıcı adınızı girin', phPass:'Şifrenizi girin', login:'Giriş Yap', info:'Bilgi', createLabel:'Hesap Oluşturma:', createText:'Yeni bir hesap oluşturmak için lütfen eğitmeniniz veya sistem yöneticisi ile iletişime geçin.' };
            const de = { sub:'Moderne Lernplattform', user:'Benutzername', pass:'Passwort', phUser:'Benutzernamen eingeben', phPass:'Passwort eingeben', login:'Anmelden', info:'Hinweis', createLabel:'Kontoerstellung:', createText:'Für ein neues Konto wenden Sie sich an Ihre Lehrkraft oder den Systemadministrator.' };
            const trRemember = 'Beni hatırla';
            const deRemember = 'Angemeldet bleiben';
            
            function setText(sel, text){ 
                const el=document.querySelector(sel); 
                if(!el) return; 
                if(el.tagName==='INPUT'){ 
                    el.setAttribute('placeholder', text); 
                } else { 
                    // Icon silmemek için childNodes kontrolü veya innerText kullanımı
                    // Burada basitçe text node'u güncellemeyi deniyoruz ama icon varsa dikkatli olunmalı.
                    // En güvenli yol label içindeki metni span içine almak ama mevcut yapıdan devam ediyoruz.
                    // Info başlığı için icon var, onu koruyalım.
                    if(el.id === 'infoTitle') {
                        el.innerHTML = el.querySelector('svg').outerHTML + ' ' + text;
                    } else {
                        el.textContent = text; 
                    }
                } 
            }

            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang')||'tr'; 
                const d = lang==='de'?de:tr;
                
                setText('#loginSub', d.sub);
                setText('#labelUser', d.user);
                setText('#labelPass', d.pass);
                setText('#username', d.phUser); // ID selector changed slightly in HTML above but reused id for input
                setText('#password', d.phPass);
                setText('#btnLogin', d.login);
                setText('#infoTitle', d.info);
                setText('#createLabel', d.createLabel);
                setText('#createText', d.createText);
                
                const rem = document.getElementById('labelRemember'); 
                if (rem) rem.textContent = (lang==='de'?deRemember:trRemember);

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
