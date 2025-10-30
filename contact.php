<?php
/**
 * İletişim Sayfası
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İletişim - Bir Soru Bir Sevap</title>
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

        .contact-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .contact-info {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .contact-info h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--secondary);
            border-radius: 0.5rem;
        }

        .contact-item i {
            color: var(--primary);
            font-size: 1.2rem;
            width: 20px;
        }

        .contact-form {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--primary);
        }

        .form-group textarea {
            height: 120px;
            resize: vertical;
        }

        .btn {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
        }

        .institutions {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .institutions h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .institution-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }

        .institution-card {
            background: var(--secondary);
            padding: 1.5rem;
            border-radius: 0.5rem;
            text-align: center;
            transition: all 0.3s ease;
        }

        .institution-card:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
        }

        .institution-card h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .institution-card p {
            color: var(--gray);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .contact-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .institution-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-envelope"></i> İletişim</h1>
        <p>Bizimle iletişime geçin</p>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>

        <div class="contact-grid">
            <div class="contact-info">
                <h3><i class="fas fa-info-circle"></i> İletişim Bilgileri</h3>
                
                <div class="contact-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>E-posta</strong><br>
                        <span>info@islamfederasyonu.at</span>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <div>
                        <strong>Adres</strong><br>
                        <span>Amberggasse 10<br>A-6800 Feldkirch, Avusturya</span>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-building"></i>
                    <div>
                        <strong>Kurum</strong><br>
                        <span>AIF – Avusturya İslam Federasyonu<br>Österreichische Islamische Föderation</span>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-id-card"></i>
                    <div>
                        <strong>ZVR-Zahl</strong><br>
                        <span>777051661</span>
                    </div>
                </div>

                <div class="contact-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Çalışma Saatleri</strong><br>
                        <span>Pazartesi - Cuma: 09:00 - 18:00</span>
                    </div>
                </div>
            </div>

            <div class="contact-form">
                <h3><i class="fas fa-paper-plane"></i> Mesaj Gönder</h3>
                <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
                <div style="background:#d1fae5;color:#065f46;border:1px solid #34d399;padding:12px 14px;border-radius:8px;margin-bottom:16px;">
                    Mesajınız alındı. En kısa sürede sizinle iletişime geçeceğiz.
                </div>
                <?php endif; ?>
                <?php if (isset($_GET['error']) && $_GET['error'] === '1'): ?>
                <div style="background:#fee2e2;color:#991b1b;border:1px solid #f87171;padding:12px 14px;border-radius:8px;margin-bottom:16px;">
                    Mesaj gönderilirken bir hata oluştu. Lütfen tekrar deneyin.
                </div>
                <?php endif; ?>
                <form method="post" action="contact_submit.php" novalidate>
                    <div class="form-group">
                        <label for="name">Ad Soyad</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="subject">Konu</label>
                        <select id="subject" name="subject" required>
                            <option value="">Konu Seçin</option>
                            <option value="genel">Genel Bilgi</option>
                            <option value="teknik">Teknik Destek</option>
                            <option value="hesap">Hesap Sorunu</option>
                            <option value="oneriler">Öneriler</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="message">Mesaj</label>
                        <textarea id="message" name="message" placeholder="Mesajınızı yazın..." required></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i>
                        Mesaj Gönder
                    </button>
                </form>
            </div>
        </div>

        <div class="institutions">
            <h3><i class="fas fa-building"></i> IQRA Şubelerimiz</h3>
            <div class="institution-grid">
                <div class="institution-card">
                    <h4>IQRA Innsbruck</h4>
                    <p>Ana Merkez</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Bludenz</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Bregenz</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Dornbirn</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Feldkirch</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Hall in Tirol</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Jenbach</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Lustenau</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Radfeld</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Reutte</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Vomp</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Wörgl</h4>
                    <p>Şube</p>
                </div>
                <div class="institution-card">
                    <h4>IQRA Zirl</h4>
                    <p>Şube</p>
                </div>
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

    <script>
        // Basit HTML5 doğrulama için placeholder
    </script>
</body>
</html>
