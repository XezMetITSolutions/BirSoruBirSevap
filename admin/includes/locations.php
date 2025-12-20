<?php
/**
 * Bölgeler ve Şubeler Konfigürasyonu
 * 
 * Bu dosya sistemdeki tüm bölge ve şube yapısını tutar.
 */

$regionConfig = [
    'Arlberg' => [
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
    ],
    'Berlin' => [],
    'Bremen' => [],
    'Kuzey Ruhr' => [],
    'Düsseldorf' => [],
    'Hessen' => [],
    'Kuzey Bavyera' => [],
    'Freiburg-Donau' => [],
    'Rhein-Neckar-Saar' => [],
    'Hamburg' => [],
    'Hannover' => [],
    'Ruhr-A' => [],
    'Köln' => [],
    'Württemberg' => [],
    'Güney Bavyera' => [],
    'Schwaben' => [],
    'Viyana' => [],
    'Linz' => [],
    'Fransa' => [
        'Fransa Genel',
        'Lyon',
        'Doğu Fransa',
        'Güney Batı Fransa',
        'Paris',
        'Alpes',
        'Güney Fransa'
    ],
    'Kuzey Hollanda' => [],
    'Hollanda Genel' => [],
    'Güney Hollanda' => [],
    'Türkiye' => [],
    'Belçika' => [],
    'İsveç' => [],
    'Danimarka' => [],
    'İtalya' => [],
    'Kanada' => [],
    'Amerika (ABD)' => [],
    'İsviçre' => [],
    'Norveç' => [],
    'İngiltere' => [],
    'Avustralya' => [],
    'Japonya' => []
];

// Düz liste olarak tüm şubeleri almak için yardımcı fonksiyon
function getAllBranches() {
    global $regionConfig;
    $branches = [];
    foreach ($regionConfig as $region => $items) {
        if (!empty($items)) {
            $branches = array_merge($branches, $items);
        }
    }
    return $branches;
}

// Şubenin hangi bölgede olduğunu bulan fonksiyon
function getRegionByBranch($branchName) {
    global $regionConfig;
    foreach ($regionConfig as $region => $branches) {
        if (in_array($branchName, $branches)) {
            return $region;
        }
    }
    return null;
}
?>
