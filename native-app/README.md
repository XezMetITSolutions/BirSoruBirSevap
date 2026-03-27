# Bir Soru Bir Sevap - Native Mobile App

Bu dizin, "Bir Soru Bir Sevap" platformu için geliştirilmiş **gerçek native** mobil uygulama kodlarını içermektedir. **Expo (React Native)** altyapısı kullanılarak hem iOS (iPhone) hem de Android cihazlar için geliştirilmiştir.

## 🚀 Çalıştırma Talimatları

1.  **Bağımlılıkları Yükleyin:**
    Terminalde bu dizine gidin:
    ```bash
    cd native-app
    npm install
    ```

2.  **API Bağlantısını Ayarlayın:**
    `src/screens/` altındaki `.tsx` dosyalarında (`BankSelectionScreen.tsx`, `CategorySelectionScreen.tsx`, `QuizScreen.tsx`) bulunan `API_URL` değişkenini, sunucunuzun veya bilgisayarınızın yerel IP adresi ile güncelleyin (Örn: `http://192.168.1.50/api_mobile.php`).

3.  **Uygulamayı Başlatın:**
    ```bash
    npx expo start
    ```

4.  **Telefonunuzda Test Edin:**
    *   Telefonunuza **Expo Go** (App Store veya Play Store) uygulamasını yükleyin.
    *   Terminaldeki **QR kodu** telefonunuzun kamerasıyla taratın.

## ✨ Temel Özellikler

*   **%100 Native Performans**: Web tabanlı değil, doğrudan telefonun donanımını kullanan gerçek uygulama.
*   **Modern Tasarım**: Koyu tema, cam şeffaflığı (glassmorphism) ve pürüzsüz animasyonlar.
*   **Canlı Veri**: Mevcut soru bankalarınızla (`QuestionLoader`) tam entegre çalışır.
*   **Süreli/Süresiz Alıştırma**: Seçenekli alıştırma modları.

---
Geliştirici: **Antigravity**
