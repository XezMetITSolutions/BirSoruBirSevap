<?php
session_start();
// Cache kontrol - her zaman güncel görünüm
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Erişim kontrolü (öğrenci, öğretmen veya yönetici)
if (!$auth->hasRole('student') && !$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];

// Kategorileri grupla ve temizle
$groupedCategories = [];
foreach ($banks as $bank) {
    $bankCategories = $categories[$bank] ?? [];
    $groupedCategories[$bank] = [];
    
    foreach ($bankCategories as $category) {
        // Tüm dosya uzantılarını ve gereksiz kelimeleri temizle
        $cleanCategory = preg_replace('/_json\.json$|\.json$|_questions\.json$|_sorulari\.json$|_full\.json$|_full$/', '', $category);
        
        // Sayısal aralıkları kaldır
        $cleanCategory = preg_replace('/_(\d+)_(\d+)_json$/', '', $cleanCategory);
        $cleanCategory = preg_replace('/_(\d+)_(\d+)$/', '', $cleanCategory);
        $cleanCategory = preg_replace('/_(\d+)$/', '', $cleanCategory);
        
        // Alt çizgileri boşlukla değiştir
        $cleanCategory = str_replace('_', ' ', $cleanCategory);
        
        // Özel isim dönüşümleri
        $cleanCategory = str_replace('igmg', '', $cleanCategory); // IGMG yazısını kaldır
        $cleanCategory = str_replace('itikat', 'İtikat', $cleanCategory);
        $cleanCategory = str_replace('ahlak', 'Ahlak', $cleanCategory);
        $cleanCategory = str_replace('ibadet', 'İbadet', $cleanCategory);
        $cleanCategory = str_replace('siyer', 'Siyer', $cleanCategory);
        $cleanCategory = str_replace('musiki', 'Musiki', $cleanCategory);
        $cleanCategory = str_replace('teskilat', 'Teşkilat', $cleanCategory);
        $cleanCategory = str_replace('hadis', 'Hadis', $cleanCategory);
        $cleanCategory = str_replace('hitabet', 'Hitabet', $cleanCategory);
        $cleanCategory = str_replace('insan haklari', 'İnsan Hakları', $cleanCategory);
        $cleanCategory = str_replace('islam tarihi', 'İslam Tarihi', $cleanCategory);
        $cleanCategory = str_replace('tasavvuf', 'Tasavvuf', $cleanCategory);
        $cleanCategory = str_replace('tefsir', 'Tefsir', $cleanCategory);
        $cleanCategory = str_replace('turkce', 'Türkçe', $cleanCategory);
        
        // "Sorulari" kelimesini kaldır
        $cleanCategory = str_replace('sorulari', '', $cleanCategory);
        $cleanCategory = str_replace('soruları', '', $cleanCategory);
        $cleanCategory = str_replace('sorular', '', $cleanCategory);
        
        // "Dersleri" kelimesini düzelt
        $cleanCategory = str_replace('dersleri', 'Dersleri', $cleanCategory);
        
        // Başlık formatına çevir
        $cleanCategory = ucwords($cleanCategory);
        
        // Çift boşlukları tek boşluğa çevir
        $cleanCategory = preg_replace('/\s+/', ' ', $cleanCategory);
        
        // Boşlukları temizle
        $cleanCategory = trim($cleanCategory);
        
        // Debug için orijinal kategori ismini logla
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            error_log("Original: $category -> Cleaned: $cleanCategory");
        }
        
        // Aynı konuyu birleştir
        if (!in_array($cleanCategory, $groupedCategories[$bank])) {
            $groupedCategories[$bank][] = $cleanCategory;
        }
    }
}

// URL'den bank seçimi
$selectedBank = $_GET['bank'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alıştırma Ayarları - Bir Soru Bir Sevap</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate" />
    <meta http-equiv="Pragma" content="no-cache" />
    <meta http-equiv="Expires" content="0" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            box-shadow: 0 2px 20px rgba(0,0,0,0.1);
        }
        
        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .logo img {
            height: 50px;
            width: auto;
        }
        
        .logo h1 {
            font-size: 1.8em;
            margin-bottom: 5px;
            color: #2c3e50;
        }
        
        .logo p {
            color: #7f8c8d;
            font-size: 0.9em;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
        }
        
        .back-btn {
            background: rgba(6, 132, 102, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        
        .back-btn:hover {
            background: #068466;
            color: white;
            transform: translateY(-2px);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        
        .page-header {
            text-align: center;
            margin-bottom: 50px;
        }
        
        .page-title {
            font-size: 3em;
            color: white;
            margin-bottom: 15px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            font-weight: 300;
        }
        
        .page-subtitle {
            font-size: 1.2em;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 30px;
        }
        
        .setup-card {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 45px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
            margin-bottom: 30px;
        }
        
        .setup-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 50px;
        }
        
        .section-title {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            position: relative;
        }
        
        .section-title::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 60px;
            height: 3px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 2px;
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 18px;
            max-height: 450px;
            overflow-y: auto;
            padding-right: 15px;
        }
        
        .category-grid::-webkit-scrollbar {
            width: 6px;
        }
        
        .category-grid::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .category-grid::-webkit-scrollbar-thumb {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 3px;
        }
        
        .category-item {
            padding: 25px;
            border: 2px solid #e1e8ed;
            border-radius: 18px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        .category-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            transition: left 0.5s;
        }
        
        .category-item:hover::before {
            left: 100%;
        }
        
        .category-item:hover {
            border-color: #068466;
            background: linear-gradient(135deg, #f0f9f7 0%, #e6f7f2 100%);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(6, 132, 102, 0.2);
        }
        
        .category-item.selected {
            border-color: #068466;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(6, 132, 102, 0.3);
        }
        
        .category-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 1.3em;
            font-weight: 600;
        }
        
        .category-item.selected h4 {
            color: white;
        }
        
        .category-item p {
            color: #7f8c8d;
            font-size: 1em;
        }
        
        .category-item.selected p {
            color: rgba(255, 255, 255, 0.9);
        }
        
        .subcategory-list {
            margin-top: 15px;
            padding-left: 20px;
        }
        
        .subcategory-item {
            padding: 12px 18px;
            margin: 8px 0;
            background: rgba(255, 255, 255, 0.8);
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
            font-size: 0.95em;
            color: #2c3e50;
        }
        
        .subcategory-item:hover {
            background: rgba(6, 132, 102, 0.1);
            border-color: #068466;
            transform: translateX(5px);
        }
        
        .subcategory-item.selected {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(6, 132, 102, 0.3);
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 12px;
            font-weight: 600;
            color: #2c3e50;
            font-size: 1.2em;
        }
        
        .form-group select {
            width: 100%;
            padding: 18px;
            border: 2px solid #e9ecef;
            border-radius: 15px;
            font-size: 1.1em;
            background: white;
            transition: all 0.3s ease;
            appearance: none;
            background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="%23068466" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6,9 12,15 18,9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 20px;
            padding-right: 50px;
        }
        
        .form-group select:focus {
            outline: none;
            border-color: #068466;
            box-shadow: 0 0 0 3px rgba(6, 132, 102, 0.1);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 18px;
            padding: 16px 18px;
            background: #fff;
            border-radius: 14px;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            box-shadow: 0 6px 16px rgba(0,0,0,0.06);
        }
        
        .checkbox-group:hover {
            background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
            transform: translateY(-1px);
        }
        
        /* Toggle switch */
        .switch {
            position: relative;
            display: inline-block;
            width: 52px;
            height: 28px;
        }
        .switch input { display: none; }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #e5e7eb;
            transition: .3s;
            border-radius: 999px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 22px; width: 22px;
            left: 3px; top: 3px;
            background: #fff;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 4px 10px rgba(0,0,0,.15);
        }
        .switch input:checked + .slider {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
        }
        .switch input:checked + .slider:before {
            transform: translateX(24px);
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: 600;
            color: #111827;
            font-size: 1.05em;
        }
        
        .start-button {
            width: 100%;
            padding: 25px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
            border: none;
            border-radius: 18px;
            font-size: 1.4em;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 25px;
            position: relative;
            overflow: hidden;
        }
        
        .start-button::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .start-button:hover::before {
            left: 100%;
        }
        
        .start-button:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 20px 40px rgba(6, 132, 102, 0.4);
        }
        
        .start-button:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        @media (max-width: 768px) {
            .setup-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .page-title {
                font-size: 2.2em;
            }
            
            .setup-card {
                padding: 30px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p>Alıştırma Ayarları</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <a href="dashboard.php" class="back-btn">← Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">🎯 Alıştırma Ayarları</h1>
            <p class="page-subtitle">Konu seçin ve alıştırma ayarlarınızı yapın</p>
        </div>

        <div class="setup-card">
            <div class="setup-grid">
                <!-- Sol Taraf: Konu Seçimi -->
                <div>
                    <h2 class="section-title">📚 Konu Seçimi</h2>
                    <div style="margin-bottom:16px;">
                        <input id="searchCategory" type="text" placeholder="Konu ara..." style="width:100%;padding:14px 16px;border:2px solid #e9ecef;border-radius:14px;font-size:1rem;outline:none;transition:border .2s;" oninput="filterCategories(this.value)">
                    </div>
                    
                    <!-- Debug bilgileri -->
                    <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 0.9em;">
                        <h4>🔍 Debug Bilgileri:</h4>
                        <?php foreach ($banks as $bank): ?>
                            <div style="margin-bottom: 10px;">
                                <strong><?php echo htmlspecialchars($bank); ?>:</strong><br>
                                <?php 
                                $bankCategories = $categories[$bank] ?? [];
                                foreach ($bankCategories as $category): 
                                ?>
                                    <span style="color: #666;"><?php echo htmlspecialchars($category); ?></span><br>
                                <?php endforeach; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="category-grid">
                        <?php if (!empty($groupedCategories)): ?>
                            <?php foreach ($groupedCategories as $bank => $bankCategories): ?>
                                <div class="category-item" data-bank="<?php echo htmlspecialchars($bank); ?>">
                                    <h4><?php echo htmlspecialchars($bank); ?></h4>
                                    <p><?php echo count($bankCategories); ?> konu mevcut</p>
                                    
                                    <!-- Alt kategoriler -->
                                    <div class="subcategory-list" style="margin-top: 15px; display: none;">
                                        <?php foreach ($bankCategories as $category): ?>
                                            <div class="subcategory-item" data-category="<?php echo htmlspecialchars($category); ?>">
                                                <?php echo htmlspecialchars($category); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                                <p>Henüz soru bankası yüklenmedi.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sağ Taraf: Alıştırma Ayarları -->
                <div>
                    <h2 class="section-title">⚙️ Alıştırma Ayarları</h2>
                    
                    <div class="form-group">
                        <label for="questionCount">🔢 Soru Sayısı</label>
                        <select id="questionCount">
                            <option value="5">5 Soru</option>
                            <option value="10" selected>10 Soru</option>
                            <option value="15">15 Soru</option>
                            <option value="20">20 Soru</option>
                            <option value="25">25 Soru</option>
                            <option value="30">30 Soru</option>
                            <option value="50">50 Soru</option>
                        </select>
                    </div>
                    
                    
                    <!-- Soruları karıştır seçeneği kaldırıldı; sistem daima karıştırır -->
                    
                    
                    <button class="start-button" id="startBtn" onclick="startPractice()" disabled>
                        🚀 Alıştırmaya Başla
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedBank = '';
        let selectedCategory = '';

        // Sayfa yüklendiğinde URL'den bank seçimi
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const bankParam = urlParams.get('bank');
            
            if (bankParam) {
                const bankItem = document.querySelector(`[data-bank="${bankParam}"]`);
                if (bankItem) {
                    selectBank(bankItem);
                }
            }
        });

        // Kategori arama
        function filterCategories(term) {
            term = (term || '').toLowerCase();
            document.querySelectorAll('.subcategory-item').forEach(item => {
                const txt = (item.textContent || '').toLowerCase();
                item.style.display = txt.includes(term) ? 'block' : 'none';
            });
        }

        // Bank seçimi
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Alt kategori tıklaması değilse bank seç
                if (!e.target.classList.contains('subcategory-item')) {
                    selectBank(this);
                }
            });
        });

        // Alt kategori seçimi
        document.querySelectorAll('.subcategory-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.stopPropagation(); // Bank seçimini engelle
                selectCategory(this);
            });
        });

        function selectBank(element) {
            // Önceki seçimi kaldır
            document.querySelectorAll('.category-item').forEach(item => {
                item.classList.remove('selected');
                // Alt kategorileri gizle
                const subcategoryList = item.querySelector('.subcategory-list');
                if (subcategoryList) {
                    subcategoryList.style.display = 'none';
                }
            });
            
            // Alt kategorileri de temizle
            document.querySelectorAll('.subcategory-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Yeni seçimi yap
            element.classList.add('selected');
            selectedBank = element.dataset.bank;
            
            // Alt kategorileri göster
            const subcategoryList = element.querySelector('.subcategory-list');
            if (subcategoryList) {
                subcategoryList.style.display = 'block';
            }
            
            // Kategori seçimini sıfırla
            selectedCategory = '';
            
            // Butonu güncelle
            updateStartButton();
        }

        function selectCategory(element) {
            // Önceki kategori seçimini kaldır
            document.querySelectorAll('.subcategory-item').forEach(item => {
                item.classList.remove('selected');
            });
            
            // Yeni kategori seçimini yap
            element.classList.add('selected');
            selectedCategory = element.dataset.category;
            
            // Butonu güncelle
            updateStartButton();
        }

        function updateStartButton() {
            const startBtn = document.getElementById('startBtn');
            if (selectedBank) {
                startBtn.disabled = false;
                if (selectedCategory) {
                    startBtn.textContent = `🚀 ${selectedCategory} Alıştırmasına Başla`;
                } else {
                    startBtn.textContent = '🚀 Alıştırmaya Başla';
                }
            } else {
                startBtn.disabled = true;
                startBtn.textContent = '🚀 Alıştırmaya Başla';
            }
        }

        function startPractice() {
            // Ayarları al
            const questionCount = document.getElementById('questionCount').value;
            // Doğru cevap gösterimi kaldırıldı; karıştırma her zaman açık
            // Zamanlayıcı her zaman pratik sayfasında gösterilir
            
            // URL oluştur
            let url = 'practice.php?';
            let params = [];
            
            if (selectedBank) {
                params.push('bank=' + encodeURIComponent(selectedBank));
            }
            if (selectedCategory) {
                params.push('category=' + encodeURIComponent(selectedCategory));
            }
            if (questionCount) {
                params.push('count=' + questionCount);
            }
            // show_correct parametresi kaldırıldı
            // shuffle parametresi gönderilmez (varsayılan true)
            // timer parametresi kaldırıldı (pratikte daima görünür)
            
            url += params.join('&');
            window.location.href = url;
        }
    </script>
</body>
</html>bi