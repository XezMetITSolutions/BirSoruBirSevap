# Performans ve Ölçeklenebilirlik Notları

## ⚠️ ÖNEMLİ: 150 Kullanıcı İçin Hazırlık

### 🔒 Yapılan Güvenlik İyileştirmeleri:
1. ✅ Veritabanı bilgileri artık ortam değişkenlerinden okunuyor (hardcoded kaldırıldı)
2. ✅ `.env` dosyası kullanılmalı (Git'e eklenmemeli!)
3. ✅ Connection timeout ayarlandı (10 saniye)
4. ✅ Persistent connections kapalı (connection pooling için)

### 📊 Mevcut Durum:

#### ✅ İyi Olan Kısımlar:
- Singleton pattern kullanılıyor (Database::getInstance())
- Prepared statements kullanılıyor (güvenli)
- PDO kullanılıyor
- Session yönetimi var

#### ⚠️ İyileştirmeye İhtiyaç Olan Kısımlar:

1. **JSON Dosyaları Sorunu:**
   - `data/users.json` dosyası kullanılıyor
   - JSON okuma/yazma race condition'a açık
   - **Çözüm:** Tüm veriyi MySQL'e taşı

2. **Connection Pooling:**
   - Şu anda her istek yeni bağlantı açıyor
   - 150 eşzamanlı kullanıcı için optimizasyon gerekli
   - **Çözüm:** MySQL max_connections ayarını artır

3. **Index Eksikliği:**
   - Username, role gibi sütunlar indexlenmemiş
   - Arama sorguları yavaşlayabilir
   - **Çözüm:** Index ekle:
     ```sql
     CREATE INDEX idx_username ON users(username);
     CREATE INDEX idx_role ON users(role);
     CREATE INDEX idx_class_section ON users(class_section);
     ```

4. **Session Locking:**
   - PHP session dosya locking kullanıyor
   - Eşzamanlı 150 kullanıcı için sorun olabilir
   - **Çözüm:** Session'ları database'de sakla

### 🚀 Önerilen İyileştirmeler:

#### 1. Session'ları Database'de Sakla
```php
// config.php'ye ekle
ini_set('session.save_handler', 'user');
ini_set('session.save_path', 'user');
```

#### 2. MySQL Bağlantı Ayarları
`.env` dosyasına ekle:
```
DB_MAX_CONNECTIONS=200
DB_TIMEOUT=10
```

#### 3. Cache Mekanizması Ekle
Özellikle soru bankası için:
```php
// Cache helper sınıfı
class Cache {
    private static $cache = [];
    
    public static function remember($key, $ttl, $callback) {
        // Implementation
    }
}
```

#### 4. JSON Dosyalarından Vazgeç
Tüm veriyi MySQL'e taşı:
- `data/users.json` → `users` tablosu ✅ (Zaten var)
- `data/exams.json` → `exams` tablosu
- `data/exam_results.json` → `exam_results` tablosu
- vb.

### 📈 Performans Testi:

150 eşzamanlı kullanıcı için:
- **Şu anki durum:** ❌ Hazır değil
- **Gereken minimum:** 
  - MySQL max_connections ≥ 200
  - PHP-FPM max_children ≥ 50
  - Session handler = database
  - Index'ler eklenmeli

### 🔧 Sunucu Ayarları:

#### PHP.ini:
```ini
max_execution_time = 300
memory_limit = 256M
max_input_time = 300
```

#### MySQL my.cnf:
```ini
max_connections = 200
thread_cache_size = 50
table_open_cache = 4000
```

### ⚡ Hızlı İyileştirmeler:

1. **İndex ekle:**
```bash
mysql -u root -p your_database
ALTER TABLE users ADD INDEX idx_username (username);
ALTER TABLE users ADD INDEX idx_role (role);
ALTER TABLE users ADD INDEX idx_class_section (class_section);
```

2. **Session'ları database'e taşı:**
```php
// Database.php'ye session handler ekle
public function sessionHandler() {
    // Implementation
}
```

3. **JSON dosyalarından kurtul:**
Tüm JSON dosyalarını MySQL'e migrate et.

### 📝 Deployment Checklist:

- [ ] `.env` dosyasını oluştur ve veritabanı bilgilerini ekle
- [ ] Veritabanında index'leri ekle
- [ ] JSON dosyalarını MySQL'e migrate et
- [ ] PHP-FPM ve MySQL ayarlarını yap
- [ ] Load testing yap (150 kullanıcı)

### 🎯 Sonuç:

**Şu anda 150 kullanıcı için HAZIR DEĞİL.** Ancak yukarıdaki iyileştirmeler yapıldığında çalışabilir.

