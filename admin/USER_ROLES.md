# 👥 Kullanıcı Rolleri ve Hesaplar

## 📋 Kullanıcı Yapısı

### Mete Burçak - İki Hesap

#### 1. **burca.met** - Öğretmen Hesabı 👨‍🏫
- **Rol**: Teacher (Öğretmen)
- **Branş**: IQRA Feldkirch
- **Yetkiler**:
  - ✅ Sınav oluşturma
  - ✅ Öğrenci sonuçlarını görme
  - ✅ Soru bankası yönetimi
  - ❌ Öğrenci olarak alıştırma yapamaz

#### 2. **burca.met1** - Öğrenci Hesabı 👨‍🎓
- **Rol**: Student (Öğrenci)
- **Branş**: IQRA Feldkirch
- **Şube**: (Belirtilmemiş)
- **Aktiviteler**:
  - ✅ 6 Alıştırma
  - ✅ 1 Sınav
  - ✅ Ortalama: %7

## 🔍 Veri Kaynakları

### Practice Results (Alıştırmalar)
```json
// burca.met1 için 6 kayıt
{
    "student_id": "burca.met1",
    "student_name": "Mete Burcak",
    "score": 20,
    "correct": 1,
    "wrong": 4,
    "questions": [...]
}
```

### Exam Results (Sınavlar)
```json
// burca.met1 için 1 kayıt
{
    "exam_code": "EDDBF5D1",
    "student_id": "burca.met1",
    "student_name": "Mete Burcak",
    "exam_title": "Temel",
    "score": 10
}
```

## 🎯 Student Progress Sayfası

### Filtreleme
- **Branş**: IQRA Feldkirch
- **Öğrenci**: burca.met1 (Mete Burcak)
- **Sonuçlar**: 6 alıştırma + 1 sınav

### URL
```
https://birsorubirsevap.metechnik.at/admin/student_progress.php?branch=IQRA+Feldkirch&user=burca.met1
```

## 📊 Beklenen Sonuçlar

### İstatistikler
- **Toplam Öğrenci**: 1 (filtrelenmiş)
- **Alıştırma Sayısı**: 6
- **Sınav Sayısı**: 1
- **Genel Ortalama**: ~7%

### Alıştırmalar Tablosu
| # | Tarih | Soru | Doğru | Yanlış | Başarı |
|---|-------|------|-------|--------|--------|
| 1 | ... | 5 | 1 | 4 | 20% |
| 2 | ... | 5 | 1 | 4 | 20% |
| 3 | ... | 5 | 0 | 5 | 0% |
| 4 | ... | 5 | 0 | 5 | 0% |
| 5 | ... | 5 | 0 | 5 | 0% |
| 6 | ... | 5 | 0 | 5 | 0% |

### Sınavlar Tablosu
| # | Tarih | Sınav ID | Toplam | Doğru | Başarı |
|---|-------|----------|--------|-------|--------|
| 1 | ... | EDDBF5D1 | 5 | 0.5 | 10% |

## 🔧 Sistem Davranışı

### 1. Öğretmen Hesabı (burca.met)
- ❌ Student Progress sayfasında **görünmez**
- ✅ Sadece `role = 'student'` olanlar listelenir
- ✅ Öğretmen panelinden öğrenci sonuçlarını görebilir

### 2. Öğrenci Hesabı (burca.met1)
- ✅ Student Progress sayfasında **görünür**
- ✅ Alıştırma ve sınav sonuçları gösterilir
- ✅ Filtreleme ile bulunabilir

## 🐛 Debug Bilgileri

### Debug Modu Açık
```
https://birsorubirsevap.metechnik.at/admin/student_progress.php?user=burca.met1&debug
```

### Beklenen Çıktı
```
Veri Kaynağı: JSON files
Kullanıcı: burca.met1
DB Alıştırma: 0
DB Sınav: 0
JSON Alıştırma: 6 ✅
JSON Sınav: 1 ✅
```

## 💡 Öneriler

### 1. Şube Bilgisi Ekle
```sql
UPDATE users 
SET class_section = '5-A' 
WHERE username = 'burca.met1';
```

### 2. Veritabanına Migrasyon
```php
// JSON verilerini veritabanına aktar
// Performans artışı sağlar
```

### 3. Hesap Ayrımı
- Öğretmen hesabı: Sadece öğretmen işlemleri
- Öğrenci hesabı: Sadece öğrenci işlemleri
- İki hesap arasında geçiş yapılabilir

## 📋 Kontrol Listesi

- [x] burca.met öğretmen olarak tanımlandı
- [x] burca.met1 öğrenci olarak tanımlandı
- [x] JSON'da 6 alıştırma kaydı var
- [x] JSON'da 1 sınav kaydı var
- [x] Kod student_id alanını okuyabiliyor
- [x] Normalizasyon çalışıyor
- [ ] Veritabanına migrasyon yapılacak
- [ ] Şube bilgisi eklenecek

## 🎓 Kullanıcı Rolleri Özeti

| Kullanıcı Adı | Rol | Branş | Şube | Aktivite |
|---------------|-----|-------|------|----------|
| burca.met | Teacher | IQRA Feldkirch | - | Sınav oluşturma |
| burca.met1 | Student | IQRA Feldkirch | - | 6 alıştırma, 1 sınav |

## 📞 Destek

Sorun yaşıyorsanız:
1. Debug modunu açın
2. Kullanıcı adını kontrol edin (burca.met1)
3. Veri kaynağını kontrol edin (JSON files)
4. Kayıt sayılarını kontrol edin

---

**Son Güncelleme**: 30 Ekim 2025
**Versiyon**: 1.0.0
**Geliştirici**: Cascade AI
