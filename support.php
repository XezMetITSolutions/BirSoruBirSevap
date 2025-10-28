<?php
/**
 * Teknik Destek Sayfası
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teknik Destek - Bir Soru Bir Sevap</title>
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

        .support-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            margin-bottom: 3rem;
        }

        .support-form {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .support-form h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
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

        .support-info {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
        }

        .support-info h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .support-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: var(--secondary);
            border-radius: 0.5rem;
        }

        .support-item i {
            color: var(--primary);
            font-size: 1.2rem;
            width: 20px;
        }

        .troubleshooting {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .troubleshooting h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .trouble-item {
            margin-bottom: 1.5rem;
            padding: 1.5rem;
            background: var(--secondary);
            border-radius: 0.5rem;
            border-left: 4px solid var(--primary);
        }

        .trouble-item h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .trouble-item p {
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .trouble-item ul {
            margin-left: 1rem;
            color: var(--gray);
        }

        .trouble-item li {
            margin-bottom: 0.25rem;
        }

        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 0.5rem;
        }

        .status-online {
            background: #10b981;
        }

        .status-offline {
            background: #ef4444;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .support-grid {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-tools"></i> Teknik Destek</h1>
        <p>Teknik sorunlarınız için buradayız</p>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>

        <div class="support-grid">
            <div class="support-form">
                <h3><i class="fas fa-paper-plane"></i> Destek Talebi Gönder</h3>
                <form>
                    <div class="form-group">
                        <label for="name">Ad Soyad</label>
                        <input type="text" id="name" name="name" required>
                    </div>

                    <div class="form-group">
                        <label for="email">E-posta</label>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="role">Rol</label>
                        <select id="role" name="role" required>
                            <option value="">Rol Seçin</option>
                            <option value="student">Öğrenci</option>
                            <option value="teacher">Eğitmen</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="issue">Sorun Türü</label>
                        <select id="issue" name="issue" required>
                            <option value="">Sorun Türü Seçin</option>
                            <option value="login">Giriş Sorunu</option>
                            <option value="performance">Performans Sorunu</option>
                            <option value="mobile">Mobil Sorun</option>
                            <option value="exam">Sınav Sorunu</option>
                            <option value="other">Diğer</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="description">Sorun Açıklaması</label>
                        <textarea id="description" name="description" placeholder="Sorununuzu detaylı olarak açıklayın..." required></textarea>
                    </div>

                    <button type="submit" class="btn">
                        <i class="fas fa-paper-plane"></i>
                        Destek Talebi Gönder
                    </button>
                </form>
            </div>

            <div class="support-info">
                <h3><i class="fas fa-info-circle"></i> Destek Bilgileri</h3>
                
                <div class="support-item">
                    <i class="fas fa-envelope"></i>
                    <div>
                        <strong>E-posta Desteği</strong><br>
                        <span>support@birsorubirsevap.com</span>
                    </div>
                </div>

                <div class="support-item">
                    <i class="fas fa-phone"></i>
                    <div>
                        <strong>Telefon Desteği</strong><br>
                        <span>+43 123 456 7890</span>
                    </div>
                </div>

                <div class="support-item">
                    <i class="fas fa-clock"></i>
                    <div>
                        <strong>Destek Saatleri</strong><br>
                        <span>Pazartesi - Cuma: 09:00 - 18:00</span>
                    </div>
                </div>

                <div class="support-item">
                    <i class="fas fa-server"></i>
                    <div>
                        <strong>Sistem Durumu</strong><br>
                        <span class="status-indicator status-online"></span>
                        <span>Çevrimiçi</span>
                    </div>
                </div>

                <div class="support-item">
                    <i class="fas fa-reply"></i>
                    <div>
                        <strong>Yanıt Süresi</strong><br>
                        <span>24 saat içinde</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="troubleshooting">
            <h3><i class="fas fa-wrench"></i> Sık Karşılaşılan Sorunlar</h3>
            
            <div class="trouble-item">
                <h4>Giriş Yapamıyorum</h4>
                <p>Kullanıcı adı veya şifre hatalı olabilir.</p>
                <ul>
                    <li>Kullanıcı adınızı ve şifrenizi kontrol edin</li>
                    <li>Büyük/küçük harf duyarlılığına dikkat edin</li>
                    <li>Eğitmeninizle iletişime geçin</li>
                </ul>
            </div>

            <div class="trouble-item">
                <h4>Sayfa Yüklenmiyor</h4>
                <p>İnternet bağlantınızı kontrol edin.</p>
                <ul>
                    <li>İnternet bağlantınızı kontrol edin</li>
                    <li>Tarayıcınızı yenileyin (F5)</li>
                    <li>Farklı bir tarayıcı deneyin</li>
                    <li>Tarayıcı önbelleğini temizleyin</li>
                </ul>
            </div>

            <div class="trouble-item">
                <h4>Mobil Cihazda Sorun Yaşıyorum</h4>
                <p>Mobil uyumluluk sorunları olabilir.</p>
                <ul>
                    <li>Tarayıcınızı güncelleyin</li>
                    <li>Sayfayı yenileyin</li>
                    <li>Masaüstü modunu deneyin</li>
                    <li>Farklı bir mobil tarayıcı kullanın</li>
                </ul>
            </div>

            <div class="trouble-item">
                <h4>Sınav Sırasında Sorun Yaşıyorum</h4>
                <p>Sınav sırasında teknik sorunlar olabilir.</p>
                <ul>
                    <li>İnternet bağlantınızı kontrol edin</li>
                    <li>Sayfayı yenilemeyin</li>
                    <li>Başka sekmeler açmayın</li>
                    <li>Eğitmeninize bildirin</li>
                </ul>
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
        // Form submission
        document.querySelector('form').addEventListener('submit', function(e) {
            e.preventDefault();
            alert('Destek talebiniz alındı! En kısa sürede size dönüş yapacağız.');
        });
    </script>
</body>
</html>
