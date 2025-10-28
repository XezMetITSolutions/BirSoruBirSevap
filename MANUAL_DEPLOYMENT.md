# Manuel FTP Deployment Rehberi

GitHub Actions otomatik deployment kullanamadığınız durumlarda (firewall, ECONNRESET hatası vb.) bu rehberi kullanarak manuel olarak dosyalarınızı yükleyebilirsiniz.

## 🚀 Yöntem 1: FileZilla ile Manuel Deployment

### Adım 1: FileZilla İndirin
1. https://filezilla-project.org/ sitesinden FileZilla'yı indirin ve kurun

### Adım 2: Bağlantı Yapın
1. FileZilla'yı açın
2. Host: FTP sunucu adresiniz
3. Username: FTP kullanıcı adınız
4. Password: FTP şifreniz
5. Port: 21 (veya belirlediğiniz port)
6. **Quickconnect**'e tıklayın

### Adım 3: Dosya Yükleme
1. Sol taraftan yerel projenin bulunduğu klasörü açın
2. Sağ taraftan FTP sunucudaki hedef klasörü açın
3. Yüklemek istediğiniz dosyaları seçin:
   - **Seçmeyin:** `.git`, `.github`, `vendor`, `TCPDF-main`
   - **Yükleyin:** Diğer tüm PHP dosyaları ve `Sorular` klasörü
4. Dosyaları sürükleyip bırakın veya **Upload** butonuna tıklayın

## 🚀 Yöntem 2: Windows Explorer ile WebDAV

Eğer hosting sağlayıcınız WebDAV destekliyorsa:

1. **Windows Explorer** açın
2. Adres çubuğuna: `ftp://your-server.com` yazın
3. Kullanıcı adı ve şifre isteyecek
4. Dosyaları kopyalayıp yapıştırın

## 🚀 Yöntem 3: Git ile Deployment

### Adım 1: Git Reposunu Sunucuya Clone Edin
```bash
ssh kullanici@sunucu.com
cd public_html  # veya hangi klasörde çalışacaksa
git clone https://github.com/XezMetITSolutions/BirSoruBirSevap.git .
```

### Adım 2: Değişiklik Sonrası
```bash
git pull origin main
```

Bu yöntem için SSH erişiminiz olması gerekiyor.

## 🚀 Yöntem 4: SSH ile Otomatik Deployment

Eğer SSH erişiminiz varsa, GitHub Actions ile SSH deployment kurabilirsiniz.

### `.github/workflows/deploy.yml` oluşturun:

```yaml
name: SSH Deployment

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
    - uses: actions/checkout@v3
    
    - name: Deploy via SSH
      uses: appleboy/scp-action@master
      with:
        host: ${{ secrets.HOST }}
        username: ${{ secrets.USERNAME }}
        key: ${{ secrets.SSH_KEY }}
        port: 22
        source: "."
        target: "/path/to/your/project"
```

## 🚀 Yöntem 5: cPanel ile Dosya Yöneticisi

1. cPanel'e giriş yapın
2. **Dosya Yöneticisi** açın
3. İlgili dizine gidin (`public_html` veya `www`)
4. **Upload** butonuna tıklayın
5. Dosyaları seçip yükleyin

## 📋 Yüklenecek Dosyalar

### ✅ Yüklemeniz Gerekenler:
- Tüm PHP dosyaları (`.php`)
- `Sorular/` klasörü ve içeriği
- `config.php`
- `database.php`
- Gerekli `.json` dosyaları

### ❌ Yüklemeyin:
- `.git/` klasörü
- `.github/` klasörü
- `vendor/` klasörü (composer install edin)
- `TCPDF-main/` klasörü
- `README.md`
- `.gitignore`

## 🔧 Hosting Sunucu Kurulumu

Sunucuda yapılması gerekenler:

### 1. Composer Kurulumu (SSH erişim gerekir)
```bash
# Vendor klasörünü yükle
php composer.phar install --no-dev --optimize-autoloader
```

### 2. Klasör İzinleri
```bash
chmod 755 data/
chmod 755 Sorular/
chmod 644 data/*.json
```

### 3. PHP Ayarları
`.htaccess` dosyası gerekirse:
```apache
php_value upload_max_filesize 20M
php_value post_max_size 20M
php_value memory_limit 256M
```

## 🎯 Önerilen Deployment Senaryosu

1. **Geliştirme:** Yerel makinede çalışın
2. **Test:** Yerel ortamda test edin
3. **Deployment:** Manuel olarak FileZilla ile sunucuya yükleyin
4. **Production:** Sunucuda test edin

## ⚙️ FTP Bağlantı Sorunları İçin Çözümler

### ECONNRESET Hatası

Bu hata genellikle şu nedenlerden olur:
1. **Firewall Sorunu:** Pasif mod gerekiyor
2. **Port Sorunu:** Pasif port aralığı açılmamış
3. **NAT Sorunu:** Router ayarları

**Çözüm:** Hosting sağlayıcınıza başvurun ve:
- Pasif mode kullanın
- Pasif port aralığını açın (örn: 49152-65535)
- FTP port 21'in açık olduğundan emin olun

### SFTP Kullanın

FTP yerine SFTP kullanın:
1. **FileZilla:** Connection type → SFTP
2. Port: **22**

## 📞 Destek

Sorun yaşarsanız:
- Hosting sağlayıcınızla iletişime geçin
- Firewall ayarlarını kontrol edin
- Alternatif deployment yöntemlerini deneyin

---

**Not:** Manuel deployment için FileZilla kullanımı en pratik yöntemdir.

