# Arlberg Bölgesi Kayıt Sistemi

Bu dokümantasyon, Arlberg bölgesi için oluşturulan öğrenci ve eğitmen kayıt sistemini açıklar.

## 📋 Genel Bakış

Arlberg bölgesi için iki ayrı kayıt sayfası oluşturulmuştur:
1. **Öğrenci Kayıt Sayfası** (`register_arlberg.php`)
2. **Eğitmen Kayıt Sayfası** (`register_arlberg_instructor.php`)

## 🎓 Öğrenci Kayıt Sistemi

### Erişim
- **URL**: `https://yourdomain.com/register_arlberg.php`
- **Dosya**: `register_arlberg.php`

### Özellikler
- ✅ Ad ve soyad ile otomatik kullanıcı adı oluşturma
- ✅ Arlberg bölgesi şubelerinden seçim
- ✅ E-posta ve telefon (opsiyonel)
- ✅ Kullanıcı adı önizleme
- ✅ Otomatik şifre ataması

### Kullanıcı Adı Formatı
```
Format: soyad.ad
Örnek: yilmaz.ahm (Ahmet Yılmaz için)
```

### Standart Şifre
```
iqra2025#
```

### Kayıt Süreci
1. Öğrenci formu doldurur (Ad, Soyad, Şube)
2. Sistem otomatik kullanıcı adı oluşturur
3. Kullanıcı "student" rolü ile kaydedilir
4. Giriş bilgileri ekranda gösterilir
5. Öğrenci bu bilgilerle login.php'den giriş yapabilir

## 👨‍🏫 Eğitmen Kayıt Sistemi

### Erişim
- **URL**: `https://yourdomain.com/register_arlberg_instructor.php`
- **Dosya**: `register_arlberg_instructor.php`

### Özellikler
- ✅ Ad ve soyad ile otomatik kullanıcı adı oluşturma
- ✅ "egitmen." ön eki ile kullanıcı adı
- ✅ Arlberg bölgesi şubelerinden seçim
- ✅ E-posta zorunlu (iletişim için)
- ✅ Telefon (opsiyonel)
- ✅ Kullanıcı adı önizleme
- ✅ Otomatik şifre ataması
- ✅ Mavi/mor gradient tema (öğrenciden farklı)

### Kullanıcı Adı Formatı
```
Format: egitmen.soyad.ad
Örnek: egitmen.yilmaz.ahm (Ahmet Yılmaz için)
```

### Standart Şifre
```
iqra2025#
```

### Kayıt Süreci
1. Eğitmen formu doldurur (Ad, Soyad, Şube, E-posta)
2. Sistem otomatik kullanıcı adı oluşturur (egitmen. ön eki ile)
3. E-posta validasyonu yapılır
4. Kullanıcı "teacher" rolü ile kaydedilir
5. Giriş bilgileri ekranda gösterilir
6. Eğitmen bu bilgilerle login.php'den giriş yapabilir

## 🏢 Arlberg Bölgesi Şubeleri

Sistem aşağıdaki Arlberg şubelerini destekler:
- IQRA Bludenz
- IQRA Bregenz
- IQRA Dornbirn
- IQRA Feldkirch
- IQRA Hall in Tirol
- IQRA Innsbruck
- IQRA Jenbach
- IQRA Lustenau
- IQRA Radfeld
- IQRA Reutte
- IQRA Vomp
- IQRA Wörgl
- IQRA Zirl

## 🔐 Güvenlik

### Şifre Politikası
- İlk giriş sonrası şifre değiştirilmesi önerilir
- Sistem `must_change_password` flag'i ile şifre değişikliğini takip eder

### Validasyon
- **Öğrenci Kaydı**: Ad, Soyad, Şube zorunlu
- **Eğitmen Kaydı**: Ad, Soyad, Şube, E-posta zorunlu
- E-posta formatı kontrol edilir
- Şube listesi backend'de doğrulanır
- Kullanıcı adı benzersizliği garanti edilir

### Türkçe Karakter Desteği
Sistem Türkçe karakterleri otomatik olarak dönüştürür:
```
Ü,ü → ue
Ö,ö → oe
Ğ,ğ → g
Ş,ş → s
Ç,ç → c
İ,I,ı → i
```

## 🎨 Tasarım Farklılıkları

### Öğrenci Kayıt Sayfası
- Yeşil renk teması (#068567)
- "Öğrenci Kayıt" başlığı
- Basit form alanları

### Eğitmen Kayıt Sayfası
- Mavi/Mor gradient teması (#3b82f6, #8b5cf6)
- "Eğitmen Kayıt" başlığı
- "Eğitmen Kaydı - Arlberg" rozeti
- E-posta zorunlu alanı
- Uyarı mesajı (sadece eğitmenler için)

## 📊 Veritabanı Yapısı

Her iki kayıt türü de aynı `users` tablosunu kullanır:

```sql
- username: Benzersiz kullanıcı adı
- password: Hash'lenmiş şifre
- role: 'student' veya 'teacher'
- full_name: Tam ad
- branch: Şube adı
- email: E-posta adresi
- phone: Telefon numarası
- region: 'Arlberg'
- must_change_password: TRUE (varsayılan)
- created_at: Kayıt tarihi
```

## 🚀 Kullanım Örnekleri

### Öğrenci Kaydı
```
Ad: Mehmet
Soyad: Öztürk
Şube: IQRA Innsbruck

Oluşturulan Kullanıcı Adı: oztur.meh
Şifre: iqra2025#
Rol: student
```

### Eğitmen Kaydı
```
Ad: Ayşe
Soyad: Şahin
Şube: IQRA Dornbirn
E-posta: ayse.sahin@example.com

Oluşturulan Kullanıcı Adı: egitmen.sahin.ays
Şifre: iqra2025#
Rol: teacher
```

## 📝 Notlar

1. **Benzersizlik**: Aynı isimde birden fazla kullanıcı varsa, sistem otomatik olarak sayı ekler (örn: `yilmaz.ahm1`, `yilmaz.ahm2`)

2. **Bölge Ataması**: Tüm kayıtlar otomatik olarak "Arlberg" bölgesine atanır

3. **Şifre Güvenliği**: Şifreler `password_hash()` fonksiyonu ile hash'lenerek saklanır

4. **Kopyalama Özelliği**: Kayıt sonrası kullanıcı adı ve şifre tek tıkla kopyalanabilir

5. **Responsive Tasarım**: Her iki sayfa da mobil ve masaüstü cihazlarda mükemmel çalışır

## 🔗 İlgili Dosyalar

- `auth.php` - Kimlik doğrulama sistemi
- `database.php` - Veritabanı işlemleri
- `admin/includes/locations.php` - Bölge ve şube konfigürasyonu
- `login.php` - Giriş sayfası

## 📅 Güncelleme Tarihi

Son Güncelleme: 22 Ocak 2026

---

**Not**: Bu kayıt sayfaları sadece Arlberg bölgesi için tasarlanmıştır. Diğer bölgeler için benzer sayfalar oluşturulabilir.
