<?php
/**
 * Yardım Merkezi Sayfası
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yardım Merkezi - Bir Soru Bir Sevap</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --primary-light: #089b76;
            --secondary: #f8f9fa;
            --dark: #2c3e50;
            --gray: #64748b;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            line-height: 1.6;
            color: var(--dark);
            background: var(--secondary);
        }

        .header {
            background: var(--primary);
            color: var(--white);
            padding: 2rem 0;
            text-align: center;
        }

        .header h1 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
        }

        .header p {
            font-size: 1.2rem;
            opacity: 0.9;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-5px);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary);
            text-decoration: none;
            margin-bottom: 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-5px);
        }

        .help-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .help-card {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .help-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .help-card h3 {
            color: var(--primary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .help-card ul {
            list-style: none;
        }

        .help-card li {
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f1f1;
        }

        .help-card li:last-child {
            border-bottom: none;
        }

        .help-card a {
            color: var(--dark);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .help-card a:hover {
            color: var(--primary);
        }

        .faq-section {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .faq-item {
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid #f1f1f1;
        }

        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .faq-question {
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .faq-answer {
            color: var(--gray);
            line-height: 1.7;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .help-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-question-circle"></i> Yardım Merkezi</h1>
        <p>Size nasıl yardımcı olabiliriz?</p>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>

        <div class="help-grid">
            <div class="help-card">
                <h3><i class="fas fa-user-graduate"></i> Öğrenci Rehberi</h3>
                <ul>
                    <li><a href="#nasil-giris">Nasıl Giriş Yaparım?</a></li>
                    <li><a href="#alistirma-yapma">Alıştırma Nasıl Yaparım?</a></li>
                    <li><a href="#sinava-girme">Sınava Nasıl Girerim?</a></li>
                    <li><a href="#sonuclari-gorme">Sonuçlarımı Nasıl Görürüm?</a></li>
                </ul>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-chalkboard-teacher"></i> Eğitmen Rehberi</h3>
                <ul>
                    <li><a href="#sinav-olusturma">Sınav Nasıl Oluştururum?</a></li>
                    <li><a href="#soru-yukleme">Soru Bankası Nasıl Yüklerim?</a></li>
                    <li><a href="#ogrenci-takip">Öğrencileri Nasıl Takip Ederim?</a></li>
                    <li><a href="#rapor-gorme">Raporları Nasıl Görürüm?</a></li>
                </ul>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-cog"></i> Teknik Destek</h3>
                <ul>
                    <li><a href="#sifre-sifirlama">Şifremi Nasıl Sıfırlarım?</a></li>
                    <li><a href="#hesap-ayarlari">Hesap Ayarlarını Nasıl Değiştiririm?</a></li>
                    <li><a href="#mobil-uyumluluk">Mobil Cihazlarda Nasıl Kullanırım?</a></li>
                    <li><a href="#hata-cozme">Hata Mesajlarını Nasıl Çözerim?</a></li>
                </ul>
            </div>

            <div class="help-card">
                <h3><i class="fas fa-info-circle"></i> Genel Bilgiler</h3>
                <ul>
                    <li><a href="#platform-hakkinda">Platform Hakkında</a></li>
                    <li><a href="#ozellikler">Özellikler</a></li>
                    <li><a href="#gizlilik">Gizlilik Politikası</a></li>
                    <li><a href="#kullanim-kosullari">Kullanım Koşulları</a></li>
                </ul>
            </div>
        </div>

        <div class="faq-section">
            <h2 style="color: var(--primary); margin-bottom: 2rem; text-align: center;">
                <i class="fas fa-lightbulb"></i> Sık Sorulan Sorular
            </h2>

            <div class="faq-item">
                <div class="faq-question">Q: Platformu nasıl kullanmaya başlayabilirim?</div>
                <div class="faq-answer">A: Öncelikle "Başla" butonuna tıklayarak giriş yapın. Eğer hesabınız yoksa, öğretmeninizden hesap oluşturmasını isteyin.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Q: Alıştırma yapmak için ne yapmalıyım?</div>
                <div class="faq-answer">A: Giriş yaptıktan sonra "Alıştırma" bölümüne gidin, istediğiniz konuyu seçin ve soru sayısını belirleyin. Ardından alıştırmaya başlayabilirsiniz.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Q: Sınav kodunu nereden alabilirim?</div>
                <div class="faq-answer">A: Sınav kodunu eğitmeninizden alabilirsiniz. Eğitmeniniz sınav oluşturduğunda size sınav kodunu verecektir.</div>
            </div>

            <div class="faq-item">
                <div class="faq-question">Q: Mobil cihazlarda kullanabilir miyim?</div>
                <div class="faq-answer">A: Evet, platform tamamen mobil uyumludur. Telefon veya tabletinizden rahatlıkla kullanabilirsiniz.</div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <footer style="background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: var(--white); padding: 2rem 0; margin-top: 4rem;">
        <div style="max-width: 1200px; margin: 0 auto; padding: 0 2rem; text-align: center;">
            <p>&copy; 2025 Bir Soru Bir Sevap. Tüm hakları saklıdır.</p>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.8;">
                Design and Coding by <a href="https://www.xezmet.at" target="_blank" style="color: var(--primary-light); text-decoration: none;">XezMet IT-Solutions</a>
            </p>
        </div>
    </footer>
</body>
</html>
