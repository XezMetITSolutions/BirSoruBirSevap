# Bir Soru Bir Sevap - Eğitim Platformu

Modern, kullanıcı dostu bir soru-cevap platformu. Öğrenciler için alıştırma modu, öğretmenler için sınav oluşturma sistemi.

> 🚀 **Otomatik Deployment**: Bu proje GitHub Actions ile otomatik FTP deployment kullanmaktadır.
> 📅 Test deployment: 2025-01-07
> ✅ Passive mode enabled - Veri iletimi düzeltildi
> 🔧 Using lftp for reliable FTP transfers
> 📱 **PWA Support**: Mobil cihazlara webapp olarak yüklenebilir

## 🚀 Özellikler

### 📚 Alıştırma Modu (Öğrenciler için)
- **Anlık Geri Bildirim**: Her soru sonrası doğru/yanlış bilgisi
- **Açıklamalar**: Yanlış cevaplarda detaylı açıklamalar
- **Filtreleme**: Bank, kategori, zorluk seviyesine göre filtreleme
- **İlerleme Takibi**: Görsel ilerleme çubuğu ve istatistikler
- **CSV İndirme**: Sonuçları Excel'de açılabilir formatta indirme

### 📝 Karma Sınav Modu (Öğretmenler için)
- **PIN Korumalı**: Sadece öğretmenler sınav oluşturabilir
- **Esnek Konfigürasyon**: Bank/kategori bazında soru seçimi
- **Zaman Sınırı**: Ayarlanabilir sınav süresi
- **Negatif Puanlama**: İsteğe bağlı yanlış cevap cezası
- **Karıştırma**: Soru ve seçenek karıştırma
- **Paylaşılabilir Linkler**: Dosya yazmadan sınav paylaşımı

### 🎨 Kullanıcı Arayüzü
- **Responsive Tasarım**: Mobil ve masaüstü uyumlu
- **Modern UI**: Gradient arka planlar ve cam efekti
- **Erişilebilirlik**: Klavye navigasyonu ve ARIA etiketleri
- **Türkçe Karakter Desteği**: Tam Türkçe karakter normalizasyonu

## 📁 Dosya Yapısı

```
/
├── config.php                      # Yapılandırma dosyası
├── QuestionLoader.php              # Soru yükleme sınıfı
├── ExamManager.php                 # Sınav yönetim sınıfı
├── index.php                       # Ana sayfa
├── practice.php                    # Alıştırma sayfası
├── practice_results.php            # Alıştırma sonuçları
├── exam.php                        # Sınav başlangıç sayfası
├── exam_take.php                   # Sınav çözme sayfası
├── exam_results.php                # Sınav sonuçları
├── register_arlberg.php            # Arlberg öğrenci kayıt
├── register_arlberg_instructor.php # Arlberg eğitmen kayıt
├── README.md                       # Bu dosya
└── Sorular/                        # Soru dosyaları klasörü
    ├── Temel Bilgiler 1/
    │   ├── ahlak_json.json
    │   ├── itikat_1_30_json.json
    │   └── ...
    ├── Temel Bilgiler 2/
    └── Temel Bilgiler 3/
```

## ⚙️ Kurulum

1. **Dosyaları Yükleyin**: Tüm dosyaları web sunucunuza yükleyin
2. **Klasör İzinleri**: `Sorular/` klasörüne okuma izni verin
3. **PHP Gereksinimleri**: PHP 8.0+ gerekli
4. **Yapılandırma**: `config.php` dosyasındaki ayarları kontrol edin

## 🔧 Yapılandırma

`config.php` dosyasında aşağıdaki ayarları yapabilirsiniz:

```php
define('TEACHER_PIN', '1234');           // Öğretmen PIN kodu
define('ROOT_DIR', 'Sorular');           // Soru klasörü
define('DEFAULT_TIMER', 30);             // Varsayılan soru süresi
define('NEGATIVE_MARKING', false);       // Negatif puanlama
define('MAX_SCAN_DEPTH', 5);             // Maksimum tarama derinliği
```

## 📊 Soru Dosyası Formatı

Soru dosyaları JSON formatında olmalıdır:

```json
{
  "category": "AHLAK",
  "title": "Ahlak Soruları",
  "questions": [
    {
      "id": 1,
      "question": "Soru metni buraya yazılır",
      "options": {
        "A": "Seçenek A",
        "B": "Seçenek B",
        "C": "Seçenek C",
        "D": "Seçenek D"
      },
      "correct_answer": "A",
      "explanation": "Açıklama (isteğe bağlı)",
      "difficulty": 1,
      "points": 1
    }
  ]
}
```

## 🎯 Kullanım

### Öğrenciler için:
1. Ana sayfada "Alıştırma" modunu seçin
2. Filtreleri ayarlayın (bank, kategori, zorluk)
3. Soru sayısını belirleyin
4. "Alıştırmayı Başlat" butonuna tıklayın
5. Soruları çözün ve anlık geri bildirim alın
6. Sonuçları görüntüleyin ve CSV olarak indirin

### Öğretmenler için:
1. Ana sayfada "Karma Sınav" modunu seçin
2. PIN kodunu girin (varsayılan: 1234)
3. Sınav ayarlarını yapın (süre, puanlama, karıştırma)
4. Bank/kategori bazında soru sayılarını belirleyin
5. "Sınav Oluştur" butonuna tıklayın
6. Oluşturulan linki öğrencilerle paylaşın

### Arlberg Bölgesi Kayıt Sistemi:

#### Öğrenci Kaydı:
- **Link**: `register_arlberg.php`
- Öğrenciler ad, soyad ve şube bilgileri ile kayıt olabilir
- Kullanıcı adı otomatik oluşturulur (format: `soyad.ad`)
- Standart şifre: `iqra2025#`

#### Eğitmen Kaydı:
- **Link**: `register_arlberg_instructor.php`
- Eğitmenler ad, soyad, şube ve e-posta ile kayıt olabilir
- Kullanıcı adı otomatik oluşturulur (format: `soyad.ad` - öğrencilerle aynı)
- Standart şifre: `iqra2025#`
- E-posta adresi zorunludur
- Kayıt sonrası "teacher" rolü ile sisteme giriş yapabilir


## 🔒 Güvenlik

- **Path Traversal Koruması**: Kullanıcı girdileri ile dosya yolu değiştirilemez
- **Input Sanitization**: Tüm kullanıcı girdileri temizlenir
- **Session Yönetimi**: Güvenli oturum yönetimi
- **PIN Koruması**: Öğretmen işlemleri PIN ile korunur

## 📱 Responsive Tasarım

- **Mobil Uyumlu**: Tüm cihazlarda mükemmel görünüm
- **Touch Friendly**: Dokunmatik ekranlar için optimize
- **Flexible Layout**: Farklı ekran boyutlarına uyum

## 🎨 Tema ve Stil

- **Modern Gradient**: Çekici renk geçişleri
- **Glassmorphism**: Cam efekti tasarım
- **Smooth Animations**: Yumuşak geçiş efektleri
- **Accessible Colors**: Erişilebilir renk kontrastları

## 🚀 Performans

- **Lazy Loading**: Sorular ihtiyaç halinde yüklenir
- **Session Caching**: Oturum süresince veri önbellekleme
- **Optimized Queries**: Verimli veri işleme
- **Minimal Dependencies**: Harici kütüphane bağımlılığı yok

## 🐛 Hata Ayıklama

Hata ayıklama modunu etkinleştirmek için `config.php` dosyasında:

```php
define('DEBUG_MODE', true);
define('LOG_ERRORS', true);
```

## 📄 Lisans

Bu proje eğitim amaçlı geliştirilmiştir. Ticari kullanım için izin alınması gerekebilir.

## 🤝 Katkıda Bulunma

1. Projeyi fork edin
2. Yeni özellik dalı oluşturun (`git checkout -b feature/yeni-ozellik`)
3. Değişikliklerinizi commit edin (`git commit -am 'Yeni özellik eklendi'`)
4. Dalınızı push edin (`git push origin feature/yeni-ozellik`)
5. Pull Request oluşturun

## 📞 Destek

Herhangi bir sorun yaşarsanız:
- GitHub Issues bölümünü kullanın
- Detaylı hata açıklaması yapın
- Ekran görüntüsü ekleyin

---

**Not**: Bu platform eğitim amaçlı geliştirilmiştir. Üretim ortamında kullanmadan önce güvenlik testlerini yapın.
