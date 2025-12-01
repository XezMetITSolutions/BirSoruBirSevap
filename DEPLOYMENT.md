# FTP Deployment Kurulumu

Bu proje GitHub Actions ile otomatik FTP deployment kullanıyor. Her `main` veya `master` branch'ine push yapıldığında kodlar otomatik olarak FTP sunucusuna yüklenir.

## 🔐 GitHub Secrets Kurulumu

FTP deployment'ın çalışması için GitHub repository'nize aşağıdaki secrets'ları eklemeniz gerekiyor:

### Adımlar:

1. GitHub repository sayfanıza gidin: `https://github.com/XezMetITSolutions/BirSoruBirSevap`
2. **Settings** sekmesine tıklayın
3. Sol menüden **Secrets and variables** → **Actions** seçin
4. **New repository secret** butonuna tıklayın
5. Aşağıdaki 3 adet secret'ı ekleyin:

### Eklenecek Secrets:

#### 1. FTP_SERVER
- **Name:** `FTP_SERVER`
- **Value:** FTP sunucu adresiniz (örn: `ftp.example.com` veya `IP adresi`)

#### 2. FTP_USERNAME
- **Name:** `FTP_USERNAME`
- **Value:** FTP kullanıcı adınız

#### 3. FTP_PASSWORD
- **Name:** `FTP_PASSWORD`
- **Value:** FTP şifreniz

## 🚀 Deployment Nasıl Çalışır?

1. Kodlarınızı `main` veya `master` branch'ine commit edip push edin
2. GitHub Actions otomatik olarak testleri çalıştırır
3. Testler başarılı olursa kodlar FTP sunucunuza yüklenir
4. Deployment tamamlandığında GitHub Actions sayfasında görebilirsiniz

## 📋 Deployment İşlemleri

Deployment sırasında yapılanlar:
- ✅ PHP syntax kontrolü
- ✅ Composer dependencies yükleme
- ✅ PHP extensions kontrolü
- ✅ FTP'ye dosya yükleme

## 🗑️ Hariç Tutulan Dosyalar

Aşağıdaki dosya ve klasörler FTP'ye yüklenmez:
- `.git/` ve `.github/` klasörleri
- `vendor/` klasöründeki gereksiz dosyalar
- `TCPDF-main/` klasörü
- `.gitignore`, `composer.json`, `composer.lock`
- `package.json`, `README.md` ve diğer `.md` dosyaları

## 🔍 Deployment Durumunu Kontrol Etme

1. GitHub repository sayfanıza gidin
2. **Actions** sekmesine tıklayın
3. Deployment durumunu görebilirsiniz
4. Bir deployment'a tıklayarak detaylarını görebilirsiniz

## ⚠️ Troubleshooting

### Deployment başarısız oluyorsa:

1. **FTP bilgilerini kontrol edin:**
   - FTP_SERVER: Doğru sunucu adresi
   - FTP_USERNAME: Doğru kullanıcı adı
   - FTP_PASSWORD: Doğru şifre

2. **FTP sunucu portunu kontrol edin:**
   - Varsayılan port 21'dir
   - Farklı bir port kullanıyorsanız, FTP_SERVER'a ekleyin: `ftp.example.com:port`

3. **Dizin izinlerini kontrol edin:**
   - FTP sunucuda dizin yazma izni olduğundan emin olun

4. **GitHub Actions loglarını kontrol edin:**
   - Actions sekmesinden son deployment'a tıklayın
   - "Deploy to FTP" adımının loglarını okuyun

## 📞 Destek

Sorun yaşarsanız:
- GitHub Actions loglarını kontrol edin
- FTP bilgilerinizi doğrulayın
- GitHub Issues'da sorun bildirin

---

**Not:** Secrets'ları eklemeden önce deployment yapılmaya çalışılırsa başarısız olur. Lütfen önce yukarıdaki secrets'ları ekleyin.

