# Veritabanı Kurulum Rehberi - phpMyAdmin

## 📝 Adımlar

### 1. phpMyAdmin'e Giriş Yapın
- Tarayıcınızda: `http://localhost/phpmyadmin` (veya hosting panel URL'i)
- Kullanıcı adı ve şifre ile giriş yapın

### 2. Veritabanı Seçin veya Oluşturun

**Seçenek A: Mevcut Veritabanı Kullan**
- Sol menüden mevcut veritabanınızı seçin

**Seçenek B: Yeni Veritabanı Oluştur**
- "Veritabanları" sekmesine gidin
- Veritabanı adı: `bir_soru_bir_sevap`
- `utf8mb4_general_ci` seçin
- "Oluştur" tıklayın

### 3. SQL Dosyasını İmport Edin

1. Veritabanını seçin (sol menüden)
2. Üst menüden **"SQL"** sekmesine gidin
3. **"Import files"** butonuna tıklayın
4. `database_structure.sql` dosyasını seçin
5. **"İleri"** butonuna tıklayın
6. İşlem tamamlanana kadar bekleyin

### 4. `.env` Dosyası Oluşturun

Proje klasörünüzde (root dizinde) `.env` dosyası oluşturun:

```bash
# .env dosyası
DB_HOST=localhost
DB_NAME=bir_soru_bir_sevap
DB_USER=your_username
DB_PASS=your_password
```

**Örnek (.env):**
```
DB_HOST=localhost
DB_NAME=bir_soru_bir_sevap
DB_USER=root
DB_PASS=
```

### 5. Veritabanı Bağlantısını Test Edin

Tarayıcıda `http://localhost/BirSoruBirSevap` (veya siteniz) açın.

## 🔧 İyileştirmeler (150 Kullanıcı İçin)

### A. Index'leri Kontrol Edin

phpMyAdmin'de SQL sekmesine gidin ve çalıştırın:

```sql
-- Username index (zaten var ama kontrol edin)
ALTER TABLE users ADD INDEX idx_username (username) IF NOT EXISTS;

-- Role index (zaten var ama kontrol edin)
ALTER TABLE users ADD INDEX idx_role (role) IF NOT EXISTS;

-- Exam ID index
ALTER TABLE exams ADD INDEX idx_exam_id (exam_id) IF NOT EXISTS;

-- Results indexes
ALTER TABLE exam_results ADD INDEX idx_username (username) IF NOT EXISTS;
ALTER TABLE exam_results ADD INDEX idx_exam_id (exam_id) IF NOT EXISTS;
```

### B. MySQL Ayarları

**my.cnf** veya **my.ini** dosyasını düzenleyin:

```ini
[mysqld]
max_connections = 200
thread_cache_size = 50
table_open_cache = 4000
innodb_buffer_pool_size = 1G
```

### C. Kullanıcı Limit Ayarları

```sql
-- phpMyAdmin'de SQL sekmesine gidin ve çalıştırın:
SET GLOBAL max_connections = 200;
SET GLOBAL wait_timeout = 600;
SET GLOBAL interactive_timeout = 600;
```

## 📊 Oluşturulan Tablolar

1. **users** - Kullanıcı bilgileri
2. **exams** - Sınav bilgileri
3. **exam_results** - Sınav sonuçları
4. **practice_results** - Alıştırma sonuçları
5. **user_badges** - Kullanıcı rozetleri
6. **login_attempts** - Giriş denemeleri (güvenlik)

## 🔐 Örnek Kullanıcılar

Tablo oluşturulduktan sonra şu kullanıcılar eklenecek:

- **admin** / `password` - Superadmin
- **teacher** / `password` - Öğretmen
- **student1** / `password` - Öğrenci

⚠️ **ÖNEMLİ:** İlk girişte şifreleri değiştirin!

## 🐛 Sorun Giderme

### "Veritabanı yapılandırması eksik!" Hatası
- `.env` dosyasının doğru oluşturulduğundan emin olun
- Veritabanı adının doğru olduğunu kontrol edin

### "Access denied" Hatası
- Veritabanı kullanıcı izinlerini kontrol edin
- `.env` dosyasındaki bilgilerin doğru olduğunu kontrol edin

### "Table doesn't exist" Hatası
- SQL dosyasını tekrar import edin
- Veritabanının seçili olduğundan emin olun

## 📈 Performans İpuçları

### 1. JSON Dosyalarını Kullanmayın
Artık veritabanı kullanılıyor, JSON dosyalarına gerek yok.

### 2. Cache Kullanın
Soru bankası için cache mekanizması ekleyin.

### 3. Session Yönetimi
150 kullanıcı için session'ları database'de saklayın.

## ✅ Başarı Kontrolü

1. phpMyAdmin'de tabloları görüyor musunuz? ✅
2. `.env` dosyası oluşturuldu mu? ✅
3. İlk giriş yapabiliyor musunuz? ✅
4. Veritabanı bağlantısı çalışıyor mu? ✅

## 🎉 Hazırsınız!

Artık 150 kullanıcıya hazır bir veritabanı yapınız var!

