# Sınav Planlama Sistemi - Cron Job Kurulumu

## Açıklama
Bu sistem, planlanan sınavları belirlenen zamanda otomatik olarak aktif hale getirir.

## Kurulum

### 1. Cron Job Ekleme
Aşağıdaki komutu sunucunuzda çalıştırın:

```bash
# Her dakika çalıştır
* * * * * /usr/bin/php /path/to/your/project/cron/activate_scheduled_exams.php

# Veya her 5 dakikada bir çalıştır (daha az kaynak kullanımı)
*/5 * * * * /usr/bin/php /path/to/your/project/cron/activate_scheduled_exams.php
```

### 2. Crontab Düzenleme
```bash
# Crontab'ı düzenle
crontab -e

# Yukarıdaki satırlardan birini ekle
```

### 3. Test Etme
```bash
# Manuel olarak test et
php /path/to/your/project/cron/activate_scheduled_exams.php
```

## Özellikler

- ✅ Planlanan sınavları otomatik aktif hale getirir
- ✅ Sınav başlangıç ve bitiş tarihlerini ayarlar
- ✅ Log kaydı tutar
- ✅ Hata yönetimi

## Log Dosyası
Loglar sunucunuzun error log dosyasında görüntülenir:
```bash
tail -f /var/log/apache2/error.log
# veya
tail -f /var/log/nginx/error.log
```

## Güvenlik
- Bu dosya sadece sunucu tarafından çalıştırılmalıdır
- Web erişimini engellemek için `.htaccess` kullanın:

```apache
# cron/.htaccess
Order Deny,Allow
Deny from all
```
