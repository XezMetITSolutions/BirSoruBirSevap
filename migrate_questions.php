<?php
/**
 * Soru Göç Scripti
 * JSON dosyalarındaki soruları veritabanına aktarır.
 */

require_once 'config.php';
require_once 'database.php';
require_once 'QuestionLoader.php';

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>Soru Veritabanı Göçü</h1>";

try {
    $db = Database::getInstance();
    $conn = $db->getConnection();
    
    // QuestionLoader ile soruları yükle
    $loader = new QuestionLoader();
    // Veritabanından değil, doğrudan dosyalardan yükle (Göç işlemi için)
    $loader->loadFromFiles();
    $questions = $loader->getQuestions();
    
    echo "<p>Toplam " . count($questions) . " soru bulundu.</p>";
    
    $inserted = 0;
    $updated = 0;
    $errors = 0;
    
    $stmt = $conn->prepare("INSERT INTO questions 
        (question_uid, bank, category, type, question_text, options, answer, explanation, difficulty, points, media, tags) 
        VALUES 
        (:uid, :bank, :category, :type, :text, :options, :answer, :explanation, :difficulty, :points, :media, :tags)
        ON DUPLICATE KEY UPDATE 
        bank = VALUES(bank),
        category = VALUES(category),
        type = VALUES(type),
        question_text = VALUES(question_text),
        options = VALUES(options),
        answer = VALUES(answer),
        explanation = VALUES(explanation),
        difficulty = VALUES(difficulty),
        points = VALUES(points),
        media = VALUES(media),
        tags = VALUES(tags)
    ");
    
    foreach ($questions as $q) {
        try {
            // Verileri hazırla
            // ID çakışmasını önlemek için Banka + Kategori + ID kombinasyonunu kullan
            $rawId = $q['id'] ?? uniqid();
            $uid = md5($bank . '_' . $category . '_' . $rawId);
            $bank = $q['bank'] ?? 'Genel';
            $category = $q['category'] ?? 'Genel';
            $type = $q['type'] ?? 'mcq';
            $text = $q['question'] ?? $q['text'] ?? '';
            $options = json_encode($q['options'] ?? [], JSON_UNESCAPED_UNICODE);
            $answer = json_encode($q['answer'] ?? [], JSON_UNESCAPED_UNICODE);
            $explanation = $q['explanation'] ?? '';
            $difficulty = (int)($q['difficulty'] ?? 1);
            $points = (int)($q['points'] ?? 1);
            $media = json_encode($q['media'] ?? [], JSON_UNESCAPED_UNICODE);
            $tags = json_encode($q['tags'] ?? [], JSON_UNESCAPED_UNICODE);
            
            $stmt->execute([
                ':uid' => $uid,
                ':bank' => $bank,
                ':category' => $category,
                ':type' => $type,
                ':text' => $text,
                ':options' => $options,
                ':answer' => $answer,
                ':explanation' => $explanation,
                ':difficulty' => $difficulty,
                ':points' => $points,
                ':media' => $media,
                ':tags' => $tags
            ]);
            
            if ($stmt->rowCount() == 1) {
                $inserted++;
            } elseif ($stmt->rowCount() == 2) {
                $updated++;
            } else {
                // Değişiklik yok
            }
            
        } catch (Exception $e) {
            echo "<div style='color:red'>Hata (ID: {$q['id']}): " . $e->getMessage() . "</div>";
            $errors++;
        }
    }
    
    echo "<h2>Sonuçlar:</h2>";
    echo "<ul>";
    echo "<li>Yeni eklenen: $inserted</li>";
    echo "<li>Güncellenen: $updated</li>";
    echo "<li>Hatalar: $errors</li>";
    echo "</ul>";
    
    echo "<p>İşlem tamamlandı.</p>";
    echo "<a href='index.php'>Ana Sayfaya Dön</a>";

} catch (Exception $e) {
    die("Kritik Hata: " . $e->getMessage());
}
?>
