# PDF Sınav Oluşturma - Kurulum Talimatları

## Gereksinimler
PDF sınav oluşturma özelliği için TCPDF kütüphanesi gereklidir.

## Kurulum Adımları

### 1. Composer Kurulumu
Eğer sisteminizde Composer yüklü değilse, önce Composer'ı kurun:
```bash
# Windows için
# https://getcomposer.org/download/ adresinden indirin

# Linux/Mac için
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

### 2. TCPDF Kütüphanesini Kurun
Proje ana dizininde terminal/komut satırını açın ve şu komutu çalıştırın:

```bash
composer install
```

### 3. Vendor Klasörü Kontrolü
Kurulum tamamlandıktan sonra `vendor/` klasörünün oluştuğunu kontrol edin.

### 4. Test Etme
1. Öğretmen paneline giriş yapın
2. "PDF Sınav Oluştur" butonuna tıklayın
3. Sınav bilgilerini doldurun
4. "PDF Oluştur" butonuna tıklayın
5. PDF dosyasının indirildiğini kontrol edin

## Özellikler

### 📄 **PDF İçeriği:**
- **Sınav Başlığı**: Özelleştirilebilir başlık
- **Eğitmen Bilgileri**: Ad, süre, soru sayısı
- **Öğrenci Bilgi Alanı**: Ad, sınıf, numara için boş alanlar
- **Sorular**: Numaralandırılmış sorular ve seçenekler
- **Cevap Anahtarı**: Ayrı sayfada doğru cevaplar

### 🎯 **Soru Türleri:**
- **Çoktan Seçmeli**: A, B, C, D seçenekleri
- **Doğru/Yanlış**: A) Doğru, B) Yanlış
- **Kısa Cevap**: Boş alan bırakır

### 📊 **Sınav Seçenekleri:**
- **Rastgele Sorular**: Kategorilerden rastgele seçim
- **Manuel Seçim**: Belirli soruları seçme
- **Özel Sorular**: Kendi sorularınızı yazma

## Sorun Giderme

### Composer Hatası
```bash
# Composer'ı güncelleyin
composer self-update

# Kütüphaneleri yeniden yükleyin
composer install --no-dev
```

### PDF Oluşturma Hatası
- `vendor/` klasörünün mevcut olduğunu kontrol edin
- PHP'nin `file_put_contents` fonksiyonuna yazma izni olduğunu kontrol edin
- Sunucu loglarını kontrol edin

### Font Hatası
TCPDF varsayılan olarak DejaVu Sans fontunu kullanır. Eğer font bulunamazsa:
1. `vendor/tecnickcom/tcpdf/fonts/` klasörünü kontrol edin
2. Font dosyalarının mevcut olduğunu doğrulayın

## Güvenlik Notları
- PDF dosyaları geçici olarak oluşturulur ve hemen indirilir
- Sunucuda kalıcı olarak saklanmaz
- Her PDF oluşturma işlemi benzersiz bir dosya adı kullanır

## Destek
Herhangi bir sorun yaşarsanız:
1. Sunucu hata loglarını kontrol edin
2. PHP hata raporlamasını açın
3. Composer kurulumunu doğrulayın
