<?php
/**
 * Sık Sorulan Sorular Sayfası
 */
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sık Sorulan Sorular - Bir Soru Bir Sevap</title>
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
            max-width: 1000px;
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

        .search-box {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            text-align: center;
        }

        .search-box h3 {
            color: var(--primary);
            margin-bottom: 1rem;
        }

        .search-input {
            width: 100%;
            max-width: 500px;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            font-size: 1rem;
            margin: 0 auto;
            display: block;
        }

        .search-input:focus {
            outline: none;
            border-color: var(--primary);
        }

        .faq-categories {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .faq-category {
            background: var(--white);
            border-radius: 1rem;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .category-header {
            background: var(--primary);
            color: var(--white);
            padding: 1.5rem;
            text-align: center;
        }

        .category-header h3 {
            font-size: 1.25rem;
            margin-bottom: 0.5rem;
        }

        .category-header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }

        .faq-list {
            padding: 0;
        }

        .faq-item {
            border-bottom: 1px solid #f1f1f1;
        }

        .faq-item:last-child {
            border-bottom: none;
        }

        .faq-question {
            padding: 1.5rem;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.3s ease;
        }

        .faq-question:hover {
            background: var(--secondary);
        }

        .faq-question.active {
            background: var(--secondary);
            color: var(--primary);
        }

        .faq-question h4 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0;
        }

        .faq-icon {
            transition: transform 0.3s ease;
        }

        .faq-question.active .faq-icon {
            transform: rotate(180deg);
        }

        .faq-answer {
            padding: 0 1.5rem 1.5rem;
            color: var(--gray);
            line-height: 1.7;
            display: none;
        }

        .faq-answer.show {
            display: block;
        }

        .quick-links {
            background: var(--white);
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: var(--shadow);
            text-align: center;
        }

        .quick-links h3 {
            color: var(--primary);
            margin-bottom: 1.5rem;
        }

        .quick-links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-link {
            display: block;
            padding: 1rem;
            background: var(--secondary);
            color: var(--dark);
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .quick-link:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .header h1 {
                font-size: 2rem;
            }

            .faq-categories {
                grid-template-columns: 1fr;
            }

            .quick-links-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1><i class="fas fa-question-circle"></i> Sık Sorulan Sorular</h1>
        <p>Merak ettiğiniz soruların cevapları burada</p>
    </div>

    <div class="container">
        <a href="index.php" class="back-btn">
            <i class="fas fa-arrow-left"></i>
            Ana Sayfaya Dön
        </a>

        <div class="search-box">
            <h3><i class="fas fa-search"></i> Soru Ara</h3>
            <input type="text" class="search-input" placeholder="Aradığınız soruyu yazın..." id="searchInput">
        </div>

        <div class="faq-categories">
            <div class="faq-category">
                <div class="category-header">
                    <h3><i class="fas fa-user-graduate"></i> Öğrenci Soruları</h3>
                    <p>Öğrenciler için sık sorulan sorular</p>
                </div>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Platforma nasıl giriş yaparım?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Ana sayfadaki "Başla" butonuna tıklayarak giriş sayfasına gidin. Eğitmeninizden aldığınız kullanıcı adı ve şifre ile giriş yapabilirsiniz.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Alıştırma nasıl yaparım?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Giriş yaptıktan sonra "Alıştırma" bölümüne gidin. İstediğiniz konuyu seçin, soru sayısını belirleyin ve alıştırmaya başlayın.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Sınav kodunu nereden alırım?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Sınav kodunu eğitmeninizden alırsınız. Eğitmeniniz sınav oluşturduğunda size sınav kodunu verecektir.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Sonuçlarımı nasıl görürüm?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Alıştırma veya sınav tamamlandıktan sonra sonuçlar sayfasına yönlendirileceksiniz. Ayrıca "Sonuçlar" bölümünden geçmiş sonuçlarınızı görüntüleyebilirsiniz.
                        </div>
                    </div>
                </div>
            </div>

            <div class="faq-category">
                <div class="category-header">
                    <h3><i class="fas fa-chalkboard-teacher"></i> Eğitmen Soruları</h3>
                    <p>Eğitmenler için sık sorulan sorular</p>
                </div>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Sınav nasıl oluştururum?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Eğitmen paneline giriş yapın, "Sınav Oluştur" bölümüne gidin. Sınav ayarlarını yapın, soruları seçin ve sınavı aktif hale getirin.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Öğrenci sonuçlarını nasıl takip ederim?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            "Sınav Sonuçları" bölümünden öğrencilerinizin sonuçlarını görüntüleyebilir, detaylı analizler yapabilirsiniz.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Soru bankasına nasıl soru eklerim?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            "Soru Bankası" bölümünden yeni sorular ekleyebilir, mevcut soruları düzenleyebilirsiniz.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Öğrenci hesaplarını nasıl yönetirim?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            "Öğrenci Yönetimi" bölümünden öğrenci hesaplarını oluşturabilir, düzenleyebilir ve silebilirsiniz.
                        </div>
                    </div>
                </div>
            </div>

            <div class="faq-category">
                <div class="category-header">
                    <h3><i class="fas fa-cog"></i> Teknik Sorular</h3>
                    <p>Teknik konular hakkında sorular</p>
                </div>
                <div class="faq-list">
                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Mobil cihazlarda kullanabilir miyim?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Evet, platform tamamen mobil uyumludur. Telefon veya tabletinizden rahatlıkla kullanabilirsiniz.
                        </div>
                    </div>


                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>Hangi tarayıcıları destekliyor?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Chrome, Firefox, Safari, Edge gibi modern tarayıcıların tümünü destekliyoruz.
                        </div>
                    </div>

                    <div class="faq-item">
                        <div class="faq-question" onclick="toggleFAQ(this)">
                            <h4>İnternet bağlantısı kesilirse ne olur?</h4>
                            <i class="fas fa-chevron-down faq-icon"></i>
                        </div>
                        <div class="faq-answer">
                            Bağlantı kesilirse, tekrar bağlandığınızda kaldığınız yerden devam edebilirsiniz. Cevaplarınız otomatik olarak kaydedilir.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="quick-links">
            <h3><i class="fas fa-link"></i> Hızlı Linkler</h3>
            <div class="quick-links-grid">
                <a href="help.php" class="quick-link">
                    <i class="fas fa-question-circle"></i> Yardım Merkezi
                </a>
                <a href="contact.php" class="quick-link">
                    <i class="fas fa-envelope"></i> İletişim
                </a>
                <a href="login.php" class="quick-link">
                    <i class="fas fa-sign-in-alt"></i> Giriş Yap
                </a>
                <a href="index.php" class="quick-link">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </div>
        </div>
    </div>

    <script>
        function toggleFAQ(element) {
            const answer = element.nextElementSibling;
            const isActive = element.classList.contains('active');
            
            // Close all other FAQs
            document.querySelectorAll('.faq-question').forEach(q => {
                q.classList.remove('active');
                q.nextElementSibling.classList.remove('show');
            });
            
            // Toggle current FAQ
            if (!isActive) {
                element.classList.add('active');
                answer.classList.add('show');
            }
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const faqItems = document.querySelectorAll('.faq-item');
            
            faqItems.forEach(item => {
                const question = item.querySelector('.faq-question h4').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>

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
