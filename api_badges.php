<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

require_once 'config.php';
require_once 'database.php';
require_once 'Badges.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(['success' => false, 'message' => 'Kullanıcı adı gerekli.']);
    exit;
}

$badgesManager = new Badges();

// Öncelikle rozetleri değerlendir (yeni kazanılanlar varsa kaydeder)
$newlyAwarded = $badgesManager->evaluateAndAward($username);

// Kullanıcının tüm rozetlerini yükle
$userBadgesRaw = $badgesManager->loadUserBadges($username);
$userBadges = $userBadgesRaw[$username] ?? [];

// Tüm rozet tanımlarını yükle (icon, name bilgileri için)
$allBadgeDefinitions = $badgesManager->loadBadges();

$formattedBadges = [];
foreach ($allBadgeDefinitions as $def) {
    if (isset($userBadges[$def['key']])) {
        $formattedBadges[] = [
            'key' => $def['key'],
            'name' => $def['name'],
            'icon' => $def['icon'],
            'level' => $userBadges[$def['key']]['level'],
            'description' => $def['description'] ?? '',
            'earned_at' => $userBadges[$def['key']]['awarded_at']
        ];
    }
}

echo json_encode([
    'success' => true,
    'badges' => $formattedBadges,
    'newlyAwarded' => $newlyAwarded,
    'total' => count($formattedBadges)
]);
