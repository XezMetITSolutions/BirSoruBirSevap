# 🧪 Test Talimatları

## 🔍 JSON Yapısını Test Etme

### 1. Test Sayfasını Açın

Tarayıcınızda şu URL'yi açın:

```
https://birsorubirsevap.at/admin/test_json_structure.php
```

Bu sayfa size şunları gösterecek:
- ✅ JSON dosyalarındaki gerçek veri yapısı
- ✅ burca.met1 için örnek kayıtlar
- ✅ Tüm alan adları ve değerleri
- ✅ Normalizasyon gereksinimleri
- ✅ Kayıt sayıları

### 2. Student Progress Sayfasını Test Edin

#### A. Debug Modu ile Test

```
https://birsorubirsevap.at/admin/student_progress.php?user=burca.met1&debug
```

**Beklenen Sonuç:**
```
Debug Bilgileri
Veri Kaynağı: JSON files
Kullanıcı: burca.met1
JSON Alıştırma: 6
JSON Sınav: 1
```

#### B. Normal Mod ile Test

```
https://birsorubirsevap.at/admin/student_progress.php?user=burca.met1
```

**Beklenen Sonuç:**
- 📊 İstatistik kartları: 6 alıştırma, 1 sınav
- 📝 Alıştırmalar tablosunda 6 kayıt (tarih ve yüzde ile)
- 📋 Sınavlar tablosunda 1 kayıt (tarih ve yüzde ile)
- 📈 Performans grafiği

## ✅ Kontrol Listesi

### Alıştırmalar Tablosu
- [ ] 6 kayıt görünüyor
- [ ] Tarihler görünüyor (boş değil)
- [ ] Soru sayıları doğru
- [ ] Doğru/Yanlış sayıları doğru
- [ ] Yüzde değerleri 0'dan farklı (en azından bazıları)
- [ ] Badge renkleri doğru (yeşil/sarı/kırmızı)

### Sınavlar Tablosu
- [ ] 1 kayıt görünüyor
- [ ] Tarih görünüyor (boş değil)
- [ ] Sınav ID görünüyor
- [ ] Soru sayısı doğru
- [ ] Doğru cevap sayısı doğru
- [ ] Yüzde değeri 0'dan farklı

### Performans Grafiği
- [ ] Grafik görünüyor
- [ ] Alıştırma noktaları var
- [ ] Sınav noktaları var
- [ ] Tarih etiketleri doğru

## 🐛 Sorun Giderme

### Sorun 1: Hala %0 Görünüyor

**Olası Nedenler:**
1. Cache sorunu - Hard refresh yapın (`Ctrl + Shift + R`)
2. JSON yapısı beklenenden farklı
3. Normalizasyon çalışmıyor

**Çözüm:**
1. `test_json_structure.php` sayfasını açın
2. "Örnek Practice Result" bölümünü inceleyin
3. Hangi alanların var olduğunu kontrol edin
4. Ekran görüntüsü alın ve paylaşın

### Sorun 2: Tarih Görünmüyor

**Olası Nedenler:**
1. JSON'da `timestamp` veya `created_at` alanı yok
2. Tarih formatı farklı
3. Alan adı farklı

**Çözüm:**
1. `test_json_structure.php` sayfasını açın
2. "Alan Analizi" tablosunu inceleyin
3. Tarih alanının adını bulun
4. Kod'da düzeltme yapın

### Sorun 3: Kayıt Sayısı Yanlış

**Olası Nedenler:**
1. Kullanıcı adı eşleşmiyor
2. Filtreler aktif
3. JSON yapısı farklı

**Çözüm:**
1. Debug modunu açın
2. "JSON Alıştırma" ve "JSON Sınav" sayılarını kontrol edin
3. `test_json_structure.php` ile karşılaştırın

## 📊 Örnek Test Sonuçları

### Başarılı Test
```
✅ Debug Bilgileri:
   - Veri Kaynağı: JSON files
   - JSON Alıştırma: 6
   - JSON Sınav: 1

✅ Alıştırmalar Tablosu:
   - 6 kayıt görünüyor
   - Tarihler: 2025-10-02 23:16:21 vb.
   - Yüzdeler: 20%, 20%, 0%, 0%, 0%, 0%

✅ Sınavlar Tablosu:
   - 1 kayıt görünüyor
   - Tarih: 2025-10-02
   - Yüzde: 10%
```

### Başarısız Test
```
❌ Debug Bilgileri:
   - Veri Kaynağı: JSON files
   - JSON Alıştırma: 0
   - JSON Sınav: 0

❌ Alıştırmalar Tablosu:
   - "Kayıt bulunamadı"

❌ Sınavlar Tablosu:
   - "Kayıt bulunamadı"
```

## 🔧 Manuel Düzeltme

Eğer test sayfası farklı bir yapı gösteriyorsa, aşağıdaki bilgileri paylaşın:

1. **Test sayfası ekran görüntüsü**
2. **"Örnek Practice Result" JSON çıktısı**
3. **"Alan Analizi" tablosu**
4. **Kayıt sayıları**

Bu bilgilerle kodu düzeltebiliriz.

## 📞 Yardım

Test sırasında sorun yaşarsanız:

1. **Test sayfasını açın**: `test_json_structure.php`
2. **Ekran görüntüsü alın**: Tüm sayfa
3. **Debug modunu açın**: `&debug` parametresi
4. **Ekran görüntüsü alın**: Debug kartı
5. **Paylaşın**: Her iki ekran görüntüsünü

---

**Son Güncelleme**: 30 Ekim 2025
**Versiyon**: 1.0.0
**Geliştirici**: Cascade AI
