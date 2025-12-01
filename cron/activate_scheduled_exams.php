<?php
/**
 * Planlanan Sınavları Aktif Hale Getiren Cron Job
 * Bu dosya her dakika çalıştırılmalıdır
 */

require_once '../config.php';

// Sınavları yükle
$examsFile = '../data/exams.json';
if (!file_exists($examsFile)) {
    exit('Exams file not found');
}

$exams = json_decode(file_get_contents($examsFile), true) ?? [];
$updated = false;
$currentTime = time();

foreach ($exams as $examCode => &$exam) {
    // Sadece planlanan sınavları kontrol et
    if (($exam['status'] ?? '') === 'scheduled' && isset($exam['scheduled_start'])) {
        $scheduledTime = strtotime($exam['scheduled_start']);
        
        // Zamanı geldi mi?
        if ($scheduledTime <= $currentTime) {
            $exam['status'] = 'active';
            $exam['start_date'] = date('Y-m-d H:i:s', $scheduledTime);
            $exam['end_date'] = date('Y-m-d H:i:s', $scheduledTime + ($exam['duration'] ?? 30) * 60);
            $updated = true;
            
            // Log
            error_log("Scheduled exam activated: {$examCode} at " . date('Y-m-d H:i:s'));
        }
    }
}

// Değişiklik varsa dosyayı güncelle
if ($updated) {
    file_put_contents($examsFile, json_encode($exams, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Scheduled exams activated successfully\n";
} else {
    echo "No scheduled exams to activate\n";
}
?>
