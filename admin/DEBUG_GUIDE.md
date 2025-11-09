# 🐛 Debug Kılavuzu - Öğrenci Sonuçları Görünmüyor

## ❓ Sorun: Öğrenci sonuçları görünmüyor

Eğer bir öğrencinin sınav veya alıştırma sonuçları görünmüyorsa, bu kılavuzu takip edin.

## 🔍 Debug Modu Nasıl Açılır?

URL'nin sonuna `&debug` ekleyin:

```
https://birsorubirsevap.metechnik.at/admin/student_progress.php?user=emekt.muh&debug
```

Veya mevcut parametrelerle:

```
https://birsorubirsevap.metechnik.at/admin/student_progress.php?branch=IQRA+Feldkirch&class_section=&user=emekt.muh&debug
```

## 📊 Debug Bilgileri Ne Gösterir?

Debug kartı şunları gösterir:

1. **Veri Kaynağı**: Database veya JSON files
2. **Kullanıcı**: Seçili kullanıcı adı
3. **DB Alıştırma**: Veritabanındaki alıştırma sayısı
4. **DB Sınav**: Veritabanındaki sınav sayısı
5. **JSON Alıştırma**: JSON dosyasındaki alıştırma sayısı
6. **JSON Sınav**: JSON dosyasındaki sınav sayısı
7. **Hata**: Varsa hata mesajı

## 🔧 Veri Kaynakları

Sistem iki kaynaktan veri çeker:

### 1. **Veritabanı (Öncelikli)**
```sql
-- Alıştırma sonuçları
SELECT * FROM practice_results WHERE username = 'emekt.muh'

-- Sınav sonuçları
SELECT * FROM exam_results WHERE username = 'emekt.muh'
```

### 2. **JSON Dosyaları (Fallback)**
Eğer veritabanında veri yoksa:
- `/data/practice_results.json`
- `/data/exam_results.json`

## 🚨 Olası Sorunlar ve Çözümler

### Sorun 1: Kullanıcı Adı Eşleşmiyor

**Belirti**: Debug'da "DB Alıştırma: 0, DB Sınav: 0" görünüyor

**Çözüm**: Kullanıcı adını kontrol edin

```sql
-- Kullanıcının gerçek adını bul
SELECT username, full_name FROM users WHERE full_name LIKE '%burca%';

-- Sonuçlarda bu kullanıcı adı var mı?
SELECT DISTINCT username FROM practice_results;
SELECT DISTINCT username FROM exam_results;
```

**Olası Nedenler**:
- Kullanıcı adı farklı yazılmış (örn: `burca.met1` vs `burca.met`)
- Büyük/küçük harf farkı
- Boşluk karakteri
- Özel karakter

### Sorun 2: Veriler JSON'da Ama DB'de Yok

**Belirti**: Debug'da "Veri Kaynağı: JSON files" görünüyor

**Çözüm**: Verileri JSON'dan veritabanına aktar

```php
// JSON'dan veritabanına aktarma scripti oluştur
// /admin/migrate_json_to_db.php
```

### Sorun 3: Kullanıcı Users Tablosunda Yok

**Belirti**: Öğrenci dropdown'da görünmüyor

**Çözüm**: Kullanıcıyı users tablosuna ekle

```sql
INSERT INTO users (username, password, role, full_name, branch, class_section) 
VALUES ('burca.met1', '$2y$10$...', 'student', 'Burca Met', 'IQRA Feldkirch', '5-A');
```

### Sorun 4: Şube/Branş Bilgisi Eksik

**Belirti**: Filtreler çalışmıyor

**Çözüm**: Kullanıcı bilgilerini güncelle

```sql
UPDATE users 
SET branch = 'IQRA Feldkirch', class_section = '5-A' 
WHERE username = 'burca.met1';
```

## 🔍 Manuel Kontrol Adımları

### 1. Veritabanını Kontrol Et

```sql
-- 1. Kullanıcı var mı?
SELECT * FROM users WHERE username = 'emekt.muh';

-- 2. Alıştırma sonuçları var mı?
SELECT COUNT(*) FROM practice_results WHERE username = 'emekt.muh';

-- 3. Sınav sonuçları var mı?
SELECT COUNT(*) FROM exam_results WHERE username = 'emekt.muh';

-- 4. Tüm kullanıcı adlarını listele
SELECT DISTINCT username FROM practice_results 
UNION 
SELECT DISTINCT username FROM exam_results;
```

### 2. JSON Dosyalarını Kontrol Et

```bash
# JSON dosyalarında kullanıcıyı ara
grep -i "burca" /path/to/data/practice_results.json
grep -i "burca" /path/to/data/exam_results.json
```

### 3. Kullanıcı Adı Eşleşmelerini Kontrol Et

```sql
-- Users tablosundaki kullanıcı adları
SELECT username, full_name FROM users WHERE role = 'student' ORDER BY username;

-- Practice results'taki kullanıcı adları
SELECT DISTINCT username FROM practice_results ORDER BY username;

-- Exam results'taki kullanıcı adları
SELECT DISTINCT username FROM exam_results ORDER BY username;

-- Eşleşmeyen kullanıcılar
SELECT DISTINCT pr.username 
FROM practice_results pr 
LEFT JOIN users u ON pr.username = u.username 
WHERE u.username IS NULL;
```

## 🛠️ Hızlı Düzeltme Scripti

### JSON'dan Veritabanına Aktarma

```php
<?php
// migrate_json_to_db.php
require_once '../database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Practice results
$practiceFile = '../data/practice_results.json';
if (file_exists($practiceFile)) {
    $data = json_decode(file_get_contents($practiceFile), true);
    
    foreach ($data as $row) {
        $sql = "INSERT INTO practice_results 
                (username, student_name, total_questions, correct_answers, wrong_answers, score, percentage, time_taken, created_at) 
                VALUES (:username, :student_name, :total_questions, :correct_answers, :wrong_answers, :score, :percentage, :time_taken, :created_at)
                ON DUPLICATE KEY UPDATE 
                total_questions = VALUES(total_questions)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':username' => $row['username'] ?? '',
            ':student_name' => $row['student_name'] ?? '',
            ':total_questions' => $row['total_questions'] ?? 0,
            ':correct_answers' => $row['correct_answers'] ?? 0,
            ':wrong_answers' => $row['wrong_answers'] ?? 0,
            ':score' => $row['score'] ?? 0,
            ':percentage' => $row['percentage'] ?? 0,
            ':time_taken' => $row['time_taken'] ?? 0,
            ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
    
    echo "Practice results migrated: " . count($data) . " records\n";
}

// Exam results
$examFile = '../data/exam_results.json';
if (file_exists($examFile)) {
    $data = json_decode(file_get_contents($examFile), true);
    
    foreach ($data as $row) {
        $sql = "INSERT INTO exam_results 
                (exam_id, username, student_name, total_questions, correct_answers, wrong_answers, score, percentage, time_taken, answers, start_time, submit_time, created_at) 
                VALUES (:exam_id, :username, :student_name, :total_questions, :correct_answers, :wrong_answers, :score, :percentage, :time_taken, :answers, :start_time, :submit_time, :created_at)
                ON DUPLICATE KEY UPDATE 
                total_questions = VALUES(total_questions)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            ':exam_id' => $row['exam_id'] ?? '',
            ':username' => $row['username'] ?? '',
            ':student_name' => $row['student_name'] ?? '',
            ':total_questions' => $row['total_questions'] ?? 0,
            ':correct_answers' => $row['correct_answers'] ?? 0,
            ':wrong_answers' => $row['wrong_answers'] ?? 0,
            ':score' => $row['score'] ?? 0,
            ':percentage' => $row['percentage'] ?? 0,
            ':time_taken' => $row['time_taken'] ?? 0,
            ':answers' => json_encode($row['answers'] ?? []),
            ':start_time' => $row['start_time'] ?? null,
            ':submit_time' => $row['submit_time'] ?? null,
            ':created_at' => $row['created_at'] ?? date('Y-m-d H:i:s')
        ]);
    }
    
    echo "Exam results migrated: " . count($data) . " records\n";
}

echo "Migration completed!\n";
?>
```

### Kullanıcı Adlarını Düzeltme

```php
<?php
// fix_usernames.php
require_once '../database.php';

$db = Database::getInstance();
$conn = $db->getConnection();

// Kullanıcı adı eşleştirme tablosu
$mappings = [
    'burca.met' => 'burca.met1',
    'emekt.muh' => 'emekt.muh1',
    // Diğer eşleştirmeler...
];

foreach ($mappings as $old => $new) {
    // Practice results güncelle
    $sql = "UPDATE practice_results SET username = :new WHERE username = :old";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':new' => $new, ':old' => $old]);
    
    // Exam results güncelle
    $sql = "UPDATE exam_results SET username = :new WHERE username = :old";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':new' => $new, ':old' => $old]);
    
    echo "Updated: $old -> $new\n";
}

echo "Username fix completed!\n";
?>
```

## 📋 Kontrol Listesi

- [ ] Debug modu açık mı? (`&debug` parametresi)
- [ ] Kullanıcı adı doğru mu?
- [ ] Kullanıcı users tablosunda var mı?
- [ ] Veritabanında sonuçlar var mı?
- [ ] JSON dosyalarında sonuçlar var mı?
- [ ] Şube/branş bilgileri doğru mu?
- [ ] Tarih filtreleri aktif mi?
- [ ] Min. başarı oranı filtresi aktif mi?

## 🎯 Örnek Debug Çıktısı

### Başarılı Durum (Veritabanı)
```
Veri Kaynağı: Database
Kullanıcı: burca.met1
DB Alıştırma: 6
DB Sınav: 1
```

### Başarılı Durum (JSON)
```
Veri Kaynağı: JSON files
Kullanıcı: burca.met1
JSON Alıştırma: 6
JSON Sınav: 1
```

### Hatalı Durum
```
Veri Kaynağı: Database
Kullanıcı: emekt.muh
DB Alıştırma: 0
DB Sınav: 0
```

## 📞 Destek

Sorun devam ediyorsa:
1. Debug modunu açın
2. Ekran görüntüsü alın
3. SQL sorgularını çalıştırın
4. Sonuçları paylaşın

---

**Son Güncelleme**: 30 Ekim 2025
**Versiyon**: 1.0.0
**Geliştirici**: Cascade AI
