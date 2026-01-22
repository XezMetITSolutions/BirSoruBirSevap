<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arlberg Kayıt Sistemi - Ana Sayfa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
            color: white;
        }

        .header img {
            height: 80px;
            margin-bottom: 20px;
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.3));
        }

        .header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .cards-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            transition: all 0.3s ease;
            text-align: center;
        }

        .card:hover {
            transform: translateY(-10px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.4);
        }

        .card-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
        }

        .student-card .card-icon {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
        }

        .instructor-card .card-icon {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
        }

        .card h2 {
            font-size: 1.8rem;
            margin-bottom: 15px;
            color: #1e293b;
        }

        .card p {
            color: #64748b;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .card ul {
            text-align: left;
            margin-bottom: 25px;
            color: #475569;
        }

        .card ul li {
            margin-bottom: 10px;
            padding-left: 25px;
            position: relative;
        }

        .card ul li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #22c55e;
            font-weight: bold;
        }

        .btn {
            display: inline-block;
            padding: 15px 35px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            color: white;
        }

        .btn-student {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            box-shadow: 0 4px 15px rgba(6, 133, 103, 0.3);
        }

        .btn-student:hover {
            box-shadow: 0 6px 20px rgba(6, 133, 103, 0.5);
            transform: translateY(-2px);
        }

        .btn-instructor {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3);
        }

        .btn-instructor:hover {
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.5);
            transform: translateY(-2px);
        }

        .info-box {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }

        .info-box h3 {
            color: #1e293b;
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        .info-box p {
            color: #64748b;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .info-box .highlight {
            background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-top: 20px;
            font-weight: 600;
        }

        .footer {
            text-align: center;
            margin-top: 40px;
            color: white;
            opacity: 0.8;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2rem;
            }

            .cards-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
            <h1>Arlberg Bölgesi Kayıt Sistemi</h1>
            <p>Öğrenci ve Eğitmen Kayıt Portalı</p>
        </div>

        <div class="cards-container">
            <!-- Öğrenci Kartı -->
            <div class="card student-card">
                <div class="card-icon">
                    <i class="fas fa-user-graduate"></i>
                </div>
                <h2>Öğrenci Kaydı</h2>
                <p>Arlberg bölgesi öğrencileri için hızlı ve kolay kayıt sistemi.</p>
                <ul>
                    <li>Otomatik kullanıcı adı oluşturma</li>
                    <li>13 farklı şube seçeneği</li>
                    <li>Anında kayıt onayı</li>
                    <li>Mobil uyumlu arayüz</li>
                </ul>
                <a href="register_arlberg.php" class="btn btn-student">
                    <i class="fas fa-user-plus"></i> Öğrenci Kaydı
                </a>
            </div>

            <!-- Eğitmen Kartı -->
            <div class="card instructor-card">
                <div class="card-icon">
                    <i class="fas fa-chalkboard-teacher"></i>
                </div>
                <h2>Eğitmen Kaydı</h2>
                <p>Arlberg bölgesi eğitmenleri için özel kayıt sistemi.</p>
                <ul>
                    <li>Özel "egitmen" kullanıcı adı</li>
                    <li>E-posta doğrulaması</li>
                    <li>Teacher rolü ile tam erişim</li>
                    <li>Gelişmiş yetkilendirme</li>
                </ul>
                <a href="register_arlberg_instructor.php" class="btn btn-instructor">
                    <i class="fas fa-user-tie"></i> Eğitmen Kaydı
                </a>
            </div>
        </div>

        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Önemli Bilgiler</h3>
            <p>
                <strong>Arlberg Bölgesi Şubeleri:</strong> IQRA Bludenz, IQRA Bregenz, IQRA Dornbirn, 
                IQRA Feldkirch, IQRA Hall in Tirol, IQRA Innsbruck, IQRA Jenbach, IQRA Lustenau, 
                IQRA Radfeld, IQRA Reutte, IQRA Vomp, IQRA Wörgl, IQRA Zirl
            </p>
            <p>
                <strong>Kullanıcı Adı Formatı:</strong><br>
                • Öğrenciler: <code>soyad.ad</code> (örn: yilmaz.ahm)<br>
                • Eğitmenler: <code>soyad.ad</code> (örn: yilmaz.ahm) - Aynı format, farklı rol
            </p>
            <div class="highlight">
                <i class="fas fa-key"></i> Standart Şifre: <strong>iqra2025#</strong>
                <br><small>İlk girişte şifrenizi değiştirmeniz önerilir.</small>
            </div>
        </div>

        <div class="footer">
            <p>
                <i class="fas fa-sign-in-alt"></i> 
                Zaten hesabınız var mı? 
                <a href="login.php" style="color: white; text-decoration: underline;">Giriş Yap</a>
            </p>
            <p style="margin-top: 20px; font-size: 0.9rem;">
                © 2026 Bir Soru Bir Sevap - Arlberg Bölgesi
            </p>
        </div>
    </div>
</body>
</html>
