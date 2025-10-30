<?php
session_start();
// Cache kontrol - her zaman güncel görünüm
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';
// Not: Bu sayfa PDF üretmez; TCPDF gereksizdir ve yanlış path 500 hatasına yol açar

$auth = Auth::getInstance();

// Öğrenci kontrolü
if (!$auth->hasRole('student')) {
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
            max-height: min(60vh, 450px);
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
    <!-- Modern override styles -->
    <style>
        :root { --primary:#0aa07c; --primary-dark:#067a5f; --ink:#0f172a; --muted:#64748b; --panel:#ffffff; --border:#eef2f7; }
        body { font-family:'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: linear-gradient(135deg,var(--primary) 0%, var(--primary-dark) 100%); color:var(--ink); }
        .header { background: rgba(255,255,255,.9); backdrop-filter: blur(12px); border-bottom:1px solid rgba(2,6,23,.06); }
        .header-content { padding:14px 20px; }
        .logo h1 { color:var(--ink); font-weight:800; }
        .logo p { color:var(--muted); }
        .user-avatar { background: linear-gradient(135deg,var(--primary),var(--primary-dark)); font-weight:800; }
        .container { padding: 28px 20px; }
        .page-title { font-size:2.4rem; font-weight:800; letter-spacing:.2px; color:#fff; text-shadow:0 12px 40px rgba(0,0,0,.25); }
        .page-subtitle { color:rgba(255,255,255,.95); }
        .setup-card { background:var(--panel); border:1px solid var(--border); border-radius:20px; box-shadow:0 30px 60px rgba(2,6,23,.12); padding:26px; }
        /* Kategori ve ayarları dikey (senkrecht) hizala */
        .setup-grid { gap:28px; }
        .section-title { font-size:1.25rem; font-weight:800; color:var(--ink); margin-bottom:16px; }
        .category-grid { gap:14px; max-height:min(60vh, 430px); padding-right:8px; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); }
        .category-item { border:1px solid #e5e7eb; border-radius:16px; padding:18px; }
        .category-item:hover { border-color:var(--primary); box-shadow:0 14px 28px rgba(2,6,23,.06); background:#fff; }
        .category-item.selected { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; border-color:transparent; box-shadow:0 16px 36px rgba(6,122,95,.25); }
        .subcategory-list { margin-top:12px; display:grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap:10px; padding-left:0; }
        .subcategory-item { background:#f8fafc; border:1px solid var(--border); border-radius:12px; margin:0; }
        /* Başlıklar ve büyük metinler için akışkan tipografi */
        .page-title { font-size: clamp(1.8rem, 2.5vw + 1rem, 3rem); }
        .page-subtitle { font-size: clamp(.95rem, .5vw + .75rem, 1.2rem); }
        .section-title { font-size: clamp(1.1rem, .6vw + 1rem, 1.4rem); }
        .start-button { font-size: clamp(1rem, .6vw + .9rem, 1.25rem); padding: clamp(12px, 1.5vw, 18px); }
        .form-group select { font-size: clamp(.95rem, .5vw + .85rem, 1.1rem); padding: clamp(12px, 1.4vw, 14px); }
        /* Görsel ve header düzeni */
        .header-content { flex-wrap: wrap; gap: 12px 16px; }
        /* Logo boyutunu header içinde sabitle */
        .header .logo img { height: 48px !important; max-height: 48px !important; width: auto !important; }
        /* Güvenli alan iç boşlukları (notch alanları) */
        body { padding-left: env(safe-area-inset-left); padding-right: env(safe-area-inset-right); }
        .header-content { padding-left: calc(20px + env(safe-area-inset-left)); padding-right: calc(20px + env(safe-area-inset-right)); }
        /* Hareket azaltma tercihi olanlar için animasyonları hafiflet */
        @media (prefers-reduced-motion: reduce) {
            * { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; scroll-behavior: auto !important; }
        }
        @media (max-width: 900px){ .subcategory-list { grid-template-columns: repeat(2, minmax(0,1fr)); } }
        @media (max-width: 600px){ .subcategory-list { grid-template-columns: 1fr; } }
        /* Grid kolonu: küçükte tek sütun, orta-büyükte iki sütun */
        @media (min-width: 1024px){ .setup-grid { grid-template-columns: 1.1fr 1fr; } }
        .subcategory-item:hover { background:#ecfeff; border-color:#a7f3d0; }
        .subcategory-item.selected { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); color:#fff; border-color:transparent; }
        .form-group label { color:var(--ink); font-weight:800; margin-bottom:8px; font-size:1rem; }
        .form-group select { border-radius:12px; border:2px solid #e5e7eb; padding:14px; }
        .form-group select:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(10,160,124,.12); }
        .start-button { background:linear-gradient(135deg,var(--primary),var(--primary-dark)); border-radius:14px; padding:16px; font-weight:800; letter-spacing:.2px; box-shadow:0 20px 40px rgba(6,122,95,.35); }
        .start-button:hover:not(:disabled) { transform: translateY(-2px); }
        /* Yapışkan özet ve başlat butonu çubuğu */
        .footer-bar { display:flex; align-items:center; gap:10px; padding:10px 12px; border-top:1px solid var(--border); margin-top: 14px; }
        .footer-bar .badge { background:#f8fafc; border:1px solid var(--border); border-radius:999px; padding:8px 12px; font-size:.9rem; color:var(--ink); }
        .footer-bar .spacer { flex:1; }
        @media (max-width: 1024px){
            .footer-bar { position: sticky; bottom: 10px; background: rgba(255,255,255,.95); backdrop-filter: blur(10px); border:1px solid var(--border); border-radius: 14px; box-shadow: 0 10px 28px rgba(2,6,23,.10); padding: 10px; }
            .start-button { width:100%; padding:14px; border-radius:12px; }
        }
        /* Büyük ekranlarda kartın maksimum genişliği hoş görünüm için */
        @media (min-width: 1280px){ .container { max-width: 1200px; margin-left:auto; margin-right:auto; } }
        /* Search input on top of category list */
        #searchCategory { width:100%; padding:14px 16px; border:2px solid #e5e7eb; border-radius:14px; font-size:1rem; outline:none; transition:.2s; }
        #searchCategory:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(10,160,124,.12); }

        /* Hamburger ve mobil menü */
        .hamburger-btn { display:none; background: rgba(6, 132, 102, 0.12); border:2px solid #068466; color:#068466; padding:10px 14px; border-radius:12px; font-weight:700; cursor:pointer; }
        .hamburger-icon { width:20px; height:2px; background:#068466; position:relative; display:block; }
        .hamburger-icon::before, .hamburger-icon::after { content:""; position:absolute; left:0; width:20px; height:2px; background:#068466; }
        .hamburger-icon::before { top:-6px; }
        .hamburger-icon::after { top:6px; }
        #mobileMenu { display:none; position:fixed; top:64px; left:0; right:0; background:#fff; border-top:1px solid var(--border); box-shadow:0 18px 40px rgba(2,6,23,.15); z-index:1000; padding:14px 20px; }
        #mobileMenu .menu-title { font-weight:800; color:var(--ink); margin-bottom:6px; }
        #mobileMenu .hint { font-size:.9rem; color:#64748b; }
        #mobileMenu .menu-item { display:flex; align-items:center; justify-content:space-between; padding:12px 0; border-bottom:1px solid #f1f5f9; }
        #mobileMenu .menu-item:last-child { border-bottom:none; }
        #langToggleMobile { background: rgba(6, 132, 102, 0.1); border: 2px solid #068466; color:#068466; padding:10px 16px; border-radius:12px; font-weight:700; cursor:pointer; }
        @media (max-width: 768px){
            /* Mobil için tamamen yeni tasarım */
            .setup-grid, .category-grid, .setup-card { display:none !important; }
            #mobileSimple { 
                display:block !important; 
                background: white;
                border-radius: 20px;
                padding: 20px;
                margin: 20px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            }
            #langToggle, .back-btn { display:none; }
            .hamburger-btn { display:inline-flex; align-items:center; gap:8px; }
            .header-content { padding-top:16px; padding-bottom:16px; }
            .container { margin-top: 12px; padding: 0; }
            .page-header { margin-bottom: 20px; }
            .page-title { font-size: 1.8em; }
            /* Mobil basit arayüz iyileştirmeleri */
            #mobileSimple select { padding: 14px; border-radius: 12px; font-size: 1.05rem; }
            #mobileSimple .start-button, #mStart { width:100%; padding: 16px; border-radius: 12px; }
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
                    <p id="pageTitle">Alıştırma Ayarları</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div style="font-weight: 600; color: #2c3e50;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.9em; color: #7f8c8d;" id="userRole"><?php echo htmlspecialchars($user['role']); ?></div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(6, 132, 102, 0.1); border: 2px solid #068466; color: #068466; padding: 10px 20px; border-radius: 25px; text-decoration: none; transition: all 0.3s ease; font-weight: 600; cursor: pointer;">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBack">← Geri Dön</a>
                <button id="hamburgerBtn" class="hamburger-btn" aria-label="Menü"><span class="hamburger-icon"></span></button>
            </div>
        </div>
    </div>

    <!-- Mobil Menü -->
    <div id="mobileMenu" role="dialog" aria-modal="true" aria-hidden="true">
        <div class="menu-item">
            <span class="menu-title" id="mobileUserName"><?php echo htmlspecialchars($user['name']); ?></span>
        </div>
        <div class="menu-item">
            <span id="mobileUserRoleLabel">Rol</span>
            <span id="mobileUserRoleValue"><?php echo htmlspecialchars($user['role']); ?></span>
        </div>
        <div class="menu-item">
            <span id="mobileLangLabel">Dil</span>
            <button id="langToggleMobile">DE</button>
        </div>
        <div class="menu-item">
            <a href="dashboard.php" class="back-btn" id="btnBackMobile" style="display:inline-block">← Geri Dön</a>
        </div>
        <div class="hint">Dil seçimi bu menüde. Hamburger butonuna tekrar dokunarak kapatabilirsiniz.</div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="mainTitle">🎯 Alıştırma Ayarları</h1>
            <p class="page-subtitle" id="mainSubtitle">Konu seçin ve alıştırma ayarlarınızı yapın</p>
        </div>

        <!-- Mobil Sade Arayüz (yalnızca küçük ekranlarda görünür) -->
        <div id="mobileSimple" style="display:none;" role="region" aria-labelledby="mTitle">
            <h2 class="section-title" id="mTitle">📚 Konu Seçimi</h2>
            <div class="form-group">
                <label for="mBank" id="mBankLabel">🏷️ Banka</label>
                <select id="mBank" aria-describedby="mBankLabel" style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:10px; font-size:1em; background:white;">
                    <option value="">Banka seçin...</option>
                </select>
            </div>
            <div class="form-group">
                <label for="mCategory" id="mCategoryLabel">📖 Konu</label>
                <select id="mCategory" aria-describedby="mCategoryLabel" style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:10px; font-size:1em; background:white;">
                    <option value="">Önce banka seçin</option>
                </select>
            </div>
            <div class="form-group">
                <label for="mCount" id="mCountLabel">🔢 Soru Sayısı</label>
                <select id="mCount" aria-describedby="mCountLabel" style="width:100%; padding:12px; border:2px solid #e9ecef; border-radius:10px; font-size:1em; background:white;">
                    <option value="5">5 Soru</option>
                    <option value="10" selected>10 Soru</option>
                    <option value="15">15 Soru</option>
                    <option value="20">20 Soru</option>
                    <option value="25">25 Soru</option>
                    <option value="30">30 Soru</option>
                    <option value="50">50 Soru</option>
                </select>
            </div>
            <button class="start-button" id="mStart" aria-label="Alıştırmayı başlat" style="width:100%; padding:15px; background:linear-gradient(135deg, #068466 0%, #0a9d7a 100%); color:white; border:none; border-radius:12px; font-size:1.1em; font-weight:600; cursor:pointer; margin-top:20px;">🚀 Başla</button>
        </div>

        <div class="setup-card">
            <div class="setup-grid">
                <!-- Sol Taraf: Konu Seçimi -->
                <div>
                    <h2 class="section-title" id="sectionTitle1">📚 Konu Seçimi</h2>
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
                                <p id="noQuestionsLoaded">Henüz soru bankası yüklenmedi.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Sağ Taraf: Alıştırma Ayarları -->
                <div>
                    <h2 class="section-title" id="sectionTitle2">⚙️ Alıştırma Ayarları</h2>
                    
                    <div class="form-group">
                        <label for="questionCount" id="questionCountLabel">🔢 Soru Sayısı</label>
                        <select id="questionCount" onchange="updateStartButton()">
                            <option value="5" id="q5">5 Soru</option>
                            <option value="10" selected id="q10">10 Soru</option>
                            <option value="15" id="q15">15 Soru</option>
                            <option value="20" id="q20">20 Soru</option>
                            <option value="25" id="q25">25 Soru</option>
                            <option value="30" id="q30">30 Soru</option>
                            <option value="50" id="q50">50 Soru</option>
                        </select>
                    </div>
                    
                    
                    <!-- Soruları karıştır seçeneği kaldırıldı; sistem daima karıştırır -->
                    
                    
                    <div class="footer-bar">
                        <span class="badge" id="summaryBank">🏷️ Banka: —</span>
                        <span class="badge" id="summaryCategory">📖 Konu: —</span>
                        <span class="badge" id="summaryCount">🔢 Soru: 10</span>
                        <div class="spacer"></div>
                        <button class="start-button" id="startBtn" onclick="startPractice()" disabled>
                            🚀 Alıştırmaya Başla
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Alıştırma Ayarları', userRole:'Öğrenci', back:'← Geri Dön',
                mainTitle:'🎯 Alıştırma Ayarları', mainSubtitle:'Konu seçin ve alıştırma ayarlarınızı yapın',
                sectionTitle1:'📚 Konu Seçimi', sectionTitle2:'⚙️ Alıştırma Ayarları',
                searchPlaceholder:'Konu ara...', noQuestionsLoaded:'Henüz soru bankası yüklenmedi.',
                questionCountLabel:'🔢 Soru Sayısı', q5:'5 Soru', q10:'10 Soru', q15:'15 Soru',
                q20:'20 Soru', q25:'25 Soru', q30:'30 Soru', q50:'50 Soru',
                summaryBank:'🏷️ Banka: —', summaryCategory:'📖 Konu: —', summaryCount:'🔢 Soru: 10',
                startBtn:'🚀 Alıştırmaya Başla', startBtnWithCategory:'🚀 {category} Alıştırmasına Başla',
                mobileRole:'Rol', mobileLang:'Dil'
            };
            const de = {
                pageTitle:'Übungseinstellungen', userRole:'Schüler', back:'← Zurück',
                mainTitle:'🎯 Übungseinstellungen', mainSubtitle:'Wählen Sie ein Thema und konfigurieren Sie Ihre Übungseinstellungen',
                sectionTitle1:'📚 Themenauswahl', sectionTitle2:'⚙️ Übungseinstellungen',
                searchPlaceholder:'Thema suchen...', noQuestionsLoaded:'Noch keine Fragendatenbank geladen.',
                questionCountLabel:'🔢 Anzahl Fragen', q5:'5 Fragen', q10:'10 Fragen', q15:'15 Fragen',
                q20:'20 Fragen', q25:'25 Fragen', q30:'30 Fragen', q50:'50 Fragen',
                summaryBank:'🏷️ Bank: —', summaryCategory:'📖 Thema: —', summaryCount:'🔢 Fragen: 10',
                startBtn:'🚀 Übung starten', startBtnWithCategory:'🚀 {category} Übung starten',
                mobileRole:'Rolle', mobileLang:'Sprache'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            function setPlaceholder(sel, text){ const el=document.querySelector(sel); if(el) el.placeholder=text; }
            
            function apply(lang){ 
                const d=lang==='de'?de:tr; 
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnBack', d.back);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#sectionTitle1', d.sectionTitle1);
                setText('#sectionTitle2', d.sectionTitle2);
                setPlaceholder('#searchCategory', d.searchPlaceholder);
                setText('#noQuestionsLoaded', d.noQuestionsLoaded);
                setText('#questionCountLabel', d.questionCountLabel);
                setText('#q5', d.q5);
                setText('#q10', d.q10);
                setText('#q15', d.q15);
                setText('#q20', d.q20);
                setText('#q25', d.q25);
                setText('#q30', d.q30);
                setText('#q50', d.q50);
                setText('#summaryBank', d.summaryBank);
                setText('#summaryCategory', d.summaryCategory);
                setText('#summaryCount', d.summaryCount);
                setText('#startBtn', d.startBtn);
                
                const toggle=document.getElementById('langToggle'); 
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE'); 
                const toggleMobile=document.getElementById('langToggleMobile');
                if(toggleMobile) toggleMobile.textContent=(lang==='de'?'TR':'DE');
                setText('#mobileUserRoleLabel', d.mobileRole);
                setText('#mobileLangLabel', d.mobileLang);
                setText('#btnBackMobile', d.back);
                localStorage.setItem('lang_practice_setup', lang); 
            }
            
            document.addEventListener('DOMContentLoaded', function(){ 
                const lang=localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr'; 
                apply(lang); 
                const toggle=document.getElementById('langToggle'); 
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
                const toggleMobile=document.getElementById('langToggleMobile');
                if(toggleMobile){
                    toggleMobile.addEventListener('click', function(){
                        const next=(localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr';
                        apply(next);
                    });
                }
                const burger=document.getElementById('hamburgerBtn');
                const menu=document.getElementById('mobileMenu');
                if(burger && menu){
                    burger.addEventListener('click', function(){
                        const visible=getComputedStyle(menu).display!=='none';
                        // Header yüksekliğine göre dinamik konum
                        const header=document.querySelector('.header');
                        if(header){
                            const rect=header.getBoundingClientRect();
                            menu.style.top = `${rect.bottom + 4}px`;
                        }
                        menu.style.display=visible?'none':'block';
                        menu.setAttribute('aria-hidden', visible?'true':'false');
                    });
                    // Menü dışına tıklayınca kapat
                    document.addEventListener('click', function(e){
                        if(menu.style.display==='block' && !menu.contains(e.target) && !burger.contains(e.target)){
                            menu.style.display='none';
                            menu.setAttribute('aria-hidden','true');
                        }
                    });
                }

                // Mobil sade arayüz doldurma
                const mBank=document.getElementById('mBank');
                const mCategory=document.getElementById('mCategory');
                const mCount=document.getElementById('mCount');
                const mStart=document.getElementById('mStart');
                try{
                    // Bankaları doldur
                    const bankItems=[...document.querySelectorAll('.category-item')].map(el=>el.getAttribute('data-bank'));
                    if(mBank && bankItems.length){
                        mBank.innerHTML = '<option value="">—</option>' + bankItems.map(b=>`<option value="${b}">${b}</option>`).join('');
                    }
                    // Banka değişince kategori doldur
                    if(mBank && mCategory){
                        mBank.addEventListener('change', function(){
                            const val=this.value; selectedBank=val; // global ile senkron
                            // Orijinal listeden kategorileri çek
                            const container=document.querySelector(`.category-item[data-bank="${val}"] .subcategory-list`);
                            const cats=container? [...container.querySelectorAll('.subcategory-item')].map(x=>x.getAttribute('data-category')) : [];
                            mCategory.innerHTML = '<option value="">—</option>' + cats.map(c=>`<option value="${c}">${c}</option>`).join('');
                            selectedCategory='';
                            updateStartButton();
                        });
                    }
                    if(mCategory){
                        mCategory.addEventListener('change', function(){ selectedCategory=this.value||''; updateStartButton(); });
                    }
                    if(mCount){
                        mCount.addEventListener('change', function(){ const qc=document.getElementById('questionCount'); if(qc){ qc.value=this.value; } updateStartButton(); });
                    }
                    if(mStart){
                        mStart.addEventListener('click', function(){
                            const qc=document.getElementById('questionCount'); if(qc && mCount){ qc.value=mCount.value; }
                            startPractice();
                        });
                    }
                    // Metinleri dile göre güncelle
                    const d= (localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr')==='de';
                    const t = d ? {
                        title:'📚 Themenauswahl', bank:'🏷️ Bank', cat:'📖 Thema', count:'🔢 Anzahl Fragen', start:'🚀 Starten'
                    } : {
                        title:'📚 Konu Seçimi', bank:'🏷️ Banka', cat:'📖 Konu', count:'🔢 Soru Sayısı', start:'🚀 Başla'
                    };
                    const setTx=(sel, text)=>{ const el=document.querySelector(sel); if(el) el.innerText=text; };
                    setTx('#mTitle', t.title); setTx('#mBankLabel', t.bank); setTx('#mCategoryLabel', t.cat); setTx('#mCountLabel', t.count);
                    const ms=document.getElementById('mStart'); if(ms) ms.textContent=t.start;
                }catch(err){ console.warn('Mobile simple UI init error', err); }
            });
        })();

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
            // başlangıç özeti
            updateStartButton();
        });

        // Kategori arama
        function filterCategories(term) {
            term = (term || '').toLowerCase();
            document.querySelectorAll('.subcategory-item').forEach(item => {
                const txt = (item.textContent || '').toLowerCase();
                item.style.display = txt.includes(term) ? 'block' : 'none';
            });
        }

        // Bank seçimi (toggle destekli - tekrar tıklayınca kapanır)
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function(e) {
                // Alt kategori tıklaması değilse bank seç/kapat
                if (!e.target.classList.contains('subcategory-item')) {
                    // Eğer zaten seçili ise kapat
                    if (this.classList.contains('selected')) {
                        this.classList.remove('selected');
                        const subcategoryList = this.querySelector('.subcategory-list');
                        if (subcategoryList) {
                            subcategoryList.style.display = 'none';
                        }
                        selectedBank = '';
                        selectedCategory = '';
                        updateStartButton();
                        return;
                    }
                    // Aksi halde seç
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
            const sb = document.getElementById('summaryBank');
            if (sb) {
                const lang = localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr';
                const prefix = lang === 'de' ? '🏷️ Bank: ' : '🏷️ Banka: ';
                sb.textContent = prefix + selectedBank;
            }
            
            // Alt kategorileri göster
            const subcategoryList = element.querySelector('.subcategory-list');
            if (subcategoryList) {
                subcategoryList.style.display = 'grid';
            }
            
            // Kategori seçimini sıfırla
            selectedCategory = '';
            const sc = document.getElementById('summaryCategory');
            if (sc) {
                const lang = localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr';
                const prefix = lang === 'de' ? '📖 Thema: —' : '📖 Konu: —';
                sc.textContent = prefix;
            }
            
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
            const sc2 = document.getElementById('summaryCategory');
            if (sc2) {
                const lang = localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr';
                const prefix = lang === 'de' ? '📖 Thema: ' : '📖 Konu: ';
                sc2.textContent = prefix + selectedCategory;
            }
            
            // Butonu güncelle
            updateStartButton();
        }

        function updateStartButton() {
            const startBtn = document.getElementById('startBtn');
            // Soru sayısı özeti güncelle
            const q = document.getElementById('questionCount').value;
            const qc = document.getElementById('summaryCount');
            if (qc) {
                const lang = localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr';
                const prefix = lang === 'de' ? '🔢 Fragen: ' : '🔢 Soru: ';
                qc.textContent = prefix + q;
            }
            if (selectedBank) {
                startBtn.disabled = false;
                const lang = localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr';
                if (selectedCategory) {
                    const text = lang === 'de' ? `🚀 ${selectedCategory} Übung starten` : `🚀 ${selectedCategory} Alıştırmasına Başla`;
                    startBtn.textContent = text;
                } else {
                    const text = lang === 'de' ? '🚀 Übung starten' : '🚀 Alıştırmaya Başla';
                    startBtn.textContent = text;
                }
            } else {
                startBtn.disabled = true;
                const lang = localStorage.getItem('lang_practice_setup')||localStorage.getItem('lang')||'tr';
                const text = lang === 'de' ? '🚀 Übung starten' : '🚀 Alıştırmaya Başla';
                startBtn.textContent = text;
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
</html>