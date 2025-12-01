<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

$texts = isset($data['texts']) && is_array($data['texts']) ? $data['texts'] : [];
$target = isset($data['target']) && is_string($data['target']) ? strtoupper(trim($data['target'])) : 'DE';
$useCache = isset($data['use_cache']) ? (bool)$data['use_cache'] : true;
$writeCache = isset($data['write_cache']) ? (bool)$data['write_cache'] : true;

// Basit doğrulama
$texts = array_values(array_filter(array_map('trim', $texts), function($t){ return $t !== ''; }));
if (empty($texts)) {
    echo json_encode(['translations' => []]);
    exit;
}

// Önbellek yükle
$cachePath = __DIR__ . '/data/deepl_cache.json';
$cache = [];
if ($useCache && file_exists($cachePath)) {
    $cache = json_decode(@file_get_contents($cachePath), true) ?: [];
}

// İlk olarak cache karşılıklarını hazırla
$results = [];
$missing = [];
foreach ($texts as $idx => $t) {
    if ($useCache && isset($cache[$t][$target]) && $cache[$t][$target] !== '') {
        $results[$idx] = $cache[$t][$target];
    } else {
        $missing[$idx] = $t;
    }
}

// Eksikler için DeepL API çağrısı
if (!empty($missing)) {
    $url = DEEPL_API_URL;
    $fields = [ 'auth_key' => DEEPL_API_KEY, 'target_lang' => $target ];
    foreach ($missing as $t) { $fields['text'][] = $t; }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $errno = curl_errno($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0 || $httpCode >= 400 || !$response) {
        http_response_code(502);
        echo json_encode(['error' => 'Translation service error']);
        exit;
    }

    $json = json_decode($response, true);
    $translatedList = [];
    if (isset($json['translations']) && is_array($json['translations'])) {
        foreach ($json['translations'] as $tr) {
            $translatedList[] = $tr['text'] ?? '';
        }
    }

    // translatedList sırasıyla missing’e karşılık gelir
    $i = 0;
    foreach ($missing as $idx => $src) {
        $text = $translatedList[$i] ?? '';
        $results[$idx] = $text;
        if ($writeCache) {
            if (!isset($cache[$src])) $cache[$src] = [];
            $cache[$src][$target] = $text;
        }
        $i++;
    }
}

// Önbelleği yaz
if ($writeCache) {
    if (!is_dir(__DIR__ . '/data')) { @mkdir(__DIR__ . '/data', 0775, true); }
    @file_put_contents($cachePath, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

// Çıkışı kaynak sırasına göre üret
$out = [];
for ($i = 0; $i < count($texts); $i++) {
    $out[] = $results[$i] ?? '';
}

echo json_encode(['translations' => $out]);
exit;
?>


