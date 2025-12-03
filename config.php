<?php
/**
 * Bir Soru Bir Sevap - Yapılandırma Dosyası
 */

// Database Configuration (from environment or default)
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'd0449c07');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'd0449c07');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: '01528797Mb##');

// Yapılandırma Ayarları
if (!defined('TEACHER_PIN')) define('TEACHER_PIN', getenv('TEACHER_PIN') ?: '1234'); // Öğretmen PIN kodu
if (!defined('ROOT_DIR')) {
    // Mutlak yol oluştur
    $rootDir = __DIR__ . DIRECTORY_SEPARATOR . 'Sorular';
    if (!is_dir($rootDir)) {
        $rootDir = 'Sorular'; // Fallback olarak göreceli yol
    }
    define('ROOT_DIR', $rootDir);
}
if (!defined('DEFAULT_TIMER')) define('DEFAULT_TIMER', 30); // Varsayılan soru süresi (saniye)
if (!defined('NEGATIVE_MARKING')) define('NEGATIVE_MARKING', false); // Yanlış cevap için puan kesme
if (!defined('MAX_SCAN_DEPTH')) define('MAX_SCAN_DEPTH', 5); // Maksimum klasör tarama derinliği
if (!defined('SESSION_TIMEOUT')) define('SESSION_TIMEOUT', 0); // Oturum zaman aşımı (0 = tarayıcı kapanana kadar)

// Uygulama Ayarları
if (!defined('APP_NAME')) define('APP_NAME', 'Bir Soru Bir Sevap');
if (!defined('APP_VERSION')) define('APP_VERSION', '1.0.0');
if (!defined('DEFAULT_QUESTIONS_COUNT')) define('DEFAULT_QUESTIONS_COUNT', 10); // Varsayılan soru sayısı
if (!defined('MAX_QUESTIONS_PER_EXAM')) define('MAX_QUESTIONS_PER_EXAM', 100); // Sınav başına maksimum soru sayısı

// Çeviri/DeepL Ayarları
// Tercihen ortam değişkeni ile: DEEPL_API_KEY ve DEEPL_API_URL
if (!defined('DEEPL_API_KEY')) {
    $envKey = getenv('DEEPL_API_KEY');
    define('DEEPL_API_KEY', $envKey !== false && $envKey !== '' ? $envKey : 'ff6617c9-f87a-4a05-868c-0fb6dada4052:fx');
}
if (!defined('DEEPL_API_URL')) {
    $envUrl = getenv('DEEPL_API_URL');
    define('DEEPL_API_URL', $envUrl !== false && $envUrl !== '' ? $envUrl : 'https://api-free.deepl.com/v2/translate');
}

// Güvenlik Ayarları
if (!defined('ALLOWED_EXTENSIONS')) {
    define('ALLOWED_EXTENSIONS', ['json']); // İzin verilen dosya uzantıları
}
if (!defined('BLOCKED_PATTERNS')) {
    define('BLOCKED_PATTERNS', ['..', '.git', '.env', 'config', 'setup', 'debug', 'test', 'create', 'fix', 'backup']); // Engellenen klasör/ dosya isimleri
}

// Güvenlik sabitleri
define('MAX_LOGIN_ATTEMPTS', 5); // Maksimum giriş denemesi
define('LOGIN_LOCKOUT_TIME', 900); // Giriş kilitleme süresi (saniye)
define('PASSWORD_MIN_LENGTH', 6); // Minimum şifre uzunluğu
define('PASSWORD_REQUIRE_SPECIAL', false); // Özel karakter zorunluluğu

// UI Ayarları
define('DEFAULT_THEME', 'light'); // Varsayılan tema (light/dark)
define('ITEMS_PER_PAGE', 20); // Sayfa başına öğe sayısı

// Hata Ayıklama (Production için kapatıldı)
define('DEBUG_MODE', false); // Hata ayıklama modu
define('LOG_ERRORS', true); // Hataları logla

// Saat dilimi ayarı (Avusturya)
date_default_timezone_set('Europe/Vienna');

// Oturum başlat (tarayıcı kapanana kadar - session cookie expire olmaz)
if (session_status() === PHP_SESSION_NONE) {
    // Session cookie ayarları - tarayıcı kapanana kadar geçerli
    $lifetime = 0; // 0 = tarayıcı kapanana kadar (session cookie)
    
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => $lifetime, // 0 = session cookie (tarayıcı kapanana kadar)
            'path' => '/',
            'domain' => '', // Tüm alt domainler için
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params($lifetime, '/', '', isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', true);
    }
    
    // Session timeout'u çok uzun yap (30 gün)
    ini_set('session.gc_maxlifetime', 86400 * 30); // 30 gün
    ini_set('session.cookie_lifetime', 0); // Session cookie (tarayıcı kapanana kadar)
    ini_set('session.gc_probability', 1); // Garbage collection olasılığı
    ini_set('session.gc_divisor', 1000); // Daha az sıklıkla temizlik
    
    session_start();
    
    // Session'ı her sayfa yüklemesinde yenile (timeout'u sıfırla)
    if (isset($_SESSION['user'])) {
        $_SESSION['last_activity'] = time();
        $_SESSION['refresh_time'] = time();
        // Session dosyasını yeniden yaz (garbage collection'dan koru)
        session_write_close();
        session_start();
    }
}

// Hata raporlama
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Yardımcı fonksiyonlar
function sanitize_input($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function generate_unique_id() {
    return uniqid('q_', true);
}

function format_time($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $seconds);
}

function log_error($message) {
    if (LOG_ERRORS) {
        error_log(date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL, 3, 'error.log');
    }
}

// Basit güvenlik fonksiyonları
function validate_password($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    if (PASSWORD_REQUIRE_SPECIAL && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        return false;
    }
    
    return true;
}

function sanitize_filename($filename) {
    // Tehlikeli karakterleri temizle
    $filename = preg_replace('/[^a-zA-Z0-9._-]/', '', $filename);
    // Çoklu nokta ve slash'leri temizle
    $filename = preg_replace('/\.{2,}/', '.', $filename);
    $filename = str_replace(['../', '..\\'], '', $filename);
    
    return $filename;
}

function is_safe_path($path) {
    $blocked_patterns = BLOCKED_PATTERNS;
    foreach ($blocked_patterns as $pattern) {
        if (strpos($path, $pattern) !== false) {
            return false;
        }
    }
    return true;
}

// Bakım Modu Kontrolü
if (file_exists(__DIR__ . '/maintenance.lock')) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    $userRole = $_SESSION['user']['role'] ?? '';
    $scriptName = basename($_SERVER['PHP_SELF']);
    
    // Admin değilse ve login sayfası değilse engelle
    if ($userRole !== 'admin' && $userRole !== 'superadmin' && $scriptName !== 'login.php' && $scriptName !== 'auth.php') {
        // API isteği ise JSON dön
        if ((!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') || 
            (strpos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false)) {
            header('HTTP/1.1 503 Service Unavailable');
            header('Content-Type: application/json');
            echo json_encode(['error' => 'maintenance', 'message' => 'Sistem bakım modunda.']);
            exit;
        }
        
        // HTML sayfası göster
        header('HTTP/1.1 503 Service Unavailable');
        ?>
        <!DOCTYPE html>
        <html lang="tr">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Bakım Modu - Bir Soru Bir Sevap</title>
            <style>
                body {
                    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                    background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
                    height: 100vh;
                    margin: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    color: #333;
                }
                .container {
                    background: rgba(255, 255, 255, 0.95);
                    padding: 40px;
                    border-radius: 20px;
                    box-shadow: 0 20px 40px rgba(0,0,0,0.2);
                    text-align: center;
                    max-width: 500px;
                    width: 90%;
                }
                h1 { color: #068567; margin-bottom: 15px; }
                p { color: #555; line-height: 1.6; margin-bottom: 25px; }
                .btn {
                    display: inline-block;
                    padding: 10px 20px;
                    background: #3498db;
                    color: white;
                    text-decoration: none;
                    border-radius: 25px;
                    transition: background 0.3s;
                }
                .btn:hover { background: #2980b9; }
            </style>
        </head>
        <body>
            <div class="container">
                <h1>🛠️ Bakım Modu</h1>
                <p>Sistemimizde şu anda bakım çalışması yapılmaktadır.<br>Lütfen daha sonra tekrar ziyaret ediniz.</p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}
?>
