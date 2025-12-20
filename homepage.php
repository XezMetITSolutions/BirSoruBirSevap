<?php
/**
 * Ultra Modern Anasayfa - Bir Soru Bir Sevap
 */

require_once 'config.php';
require_once 'QuestionLoader.php';

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: 0');

foreach ($_COOKIE as $cookieName => $cookieValue) {
    if ($cookieName === session_name()) {
        continue;
    }
    setcookie($cookieName, '', time() - 3600, '/');
}

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];

// İstatistikler
$totalQuestions = count($questions);
$totalBanks = count($banks);
$totalCategories = array_sum(array_map('count', $categories));
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bir Soru Bir Sevap - Modern Eğitim Platformu</title>
    
    <!-- PWA Meta Tags -->
    <meta name="theme-color" content="#068567">
    <meta name="description" content="Modern, kullanıcı dostu bir soru-cevap platformu. Öğrenciler için alıştırma modu, öğretmenler için sınav oluşturma sistemi.">
    <link rel="manifest" href="/manifest.json">
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Bir Soru Bir Sevap">
    
    <!-- Icons -->
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="shortcut icon" href="logo.png">
    
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --primary-light: #089b76;
            --secondary: #f8f9fa;
            --accent: #f59e0b;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #2c3e50;
            --light: #f8fafc;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            --gradient-primary: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            --gradient-secondary: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            --gradient-accent: linear-gradient(135deg, #f59e0b 0%, #f97316 100%);
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
            overflow-x: hidden;
            background: var(--light);
        }

        /* Navigation */
        .navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            z-index: 1000;
            padding: 1rem 0;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border-bottom: 1px solid rgba(226, 232, 240, 0.5);
        }

        .navbar.scrolled {
            box-shadow: var(--shadow-lg);
            padding: 0.75rem 0;
        }

        .nav-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.5rem;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .nav-logo:hover {
            transform: scale(1.05);
        }

        .nav-logo img {
            height: 2.5rem;
            width: auto;
            border-radius: 0.5rem;
        }

        .nav-menu {
            display: flex;
            gap: 2rem;
            list-style: none;
            align-items: center;
        }

        .nav-menu a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.95rem;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-menu a:hover {
            color: var(--primary);
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--gradient-primary);
            transition: width 0.3s ease;
        }

        .nav-menu a:hover::after {
            width: 100%;
        }

        .nav-cta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: none;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: var(--white);
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: var(--white);
            color: var(--primary);
            border: 2px solid var(--primary);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--white);
            transform: translateY(-2px);
        }

        /* Responsive Tweaks */
        @media (max-width: 1024px) {
            .nav-container { padding: 0 1rem; }
            .features-container, .cta-container { padding: 0 1rem; }
        }

        @media (max-width: 768px) {
            .nav-menu { display: none; }
            .mobile-menu-toggle { display: inline-flex; align-items:center; justify-content:center; }
            .nav-menu.active { display: flex; flex-direction: column; gap: 0.75rem; padding: 0.75rem 0; }
            .section-title { font-size: 1.5rem; }
            .features-grid { grid-template-columns: 1fr; }
            /* Mobil sadeleştirme */
            .floating-cards { display: none; }
            .stats { display: none; }
            .features { display: none; }
            .footer-container { grid-template-columns: 1fr; gap: 1.5rem; }
            .hero-container { grid-template-columns: 1fr; gap: 1.75rem; text-align:center; }
            .hero-visual { display: none; }
        }

        @media (max-width: 480px) {
            .nav-logo img { height: 2rem; }
            .btn { padding: 0.65rem 1rem; font-size: 0.9rem; }
            .stat-number { font-size: 1.25rem; }
            .feature-card { padding: 1rem; }
        }

        .mobile-menu-toggle {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--dark);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }

        .mobile-menu-toggle:hover {
            background: var(--secondary);
        }

        /* Mobil Menu Sidebar */
        .mobile-menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            z-index: 1099;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .mobile-menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .mobile-menu-sidebar {
            position: fixed;
            top: 0;
            right: -300px;
            bottom: 0;
            width: 300px;
            background: var(--white);
            z-index: 1100;
            box-shadow: -5px 0 25px rgba(0,0,0,0.15);
            transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
        }

        .mobile-menu-sidebar.active {
            transform: translateX(-300px);
        }

        .mobile-menu-header {
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--gray-light);
        }

        .mobile-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            text-decoration: none;
            font-weight: 800;
            font-size: 1.1rem;
            color: var(--primary);
        }

        .mobile-logo img {
            height: 2rem;
            width: auto;
        }

        .mobile-menu-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray);
            cursor: pointer;
            padding: 0.5rem;
            transition: color 0.3s ease;
        }

        .mobile-menu-close:hover {
            color: var(--danger);
        }

        .mobile-menu-content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        .mobile-nav-list {
            list-style: none;
            margin-bottom: 2rem;
        }

        .mobile-nav-link {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }

        .mobile-nav-link i {
            width: 1.5rem;
            text-align: center;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .mobile-nav-link:hover {
            background: var(--secondary);
            color: var(--primary);
            transform: translateX(5px);
        }

        .mobile-menu-footer {
            margin-top: auto;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-light);
        }

        .w-full {
            width: 100%;
            justify-content: center;
        }

        .mb-3 {
            margin-bottom: 0.75rem;
        }

        /* Hero Section */
        .hero {
            min-height: 100vh;
            display: flex;
            align-items: center;
            position: relative;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/><circle cx="400" cy="700" r="120" fill="url(%23a)"/></svg>');
            opacity: 0.3;
        }

        .hero-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 4rem;
            align-items: center;
            position: relative;
            z-index: 2;
        }

        .hero-content h1 {
            font-size: clamp(2.5rem, 5vw, 4rem);
            font-weight: 900;
            color: var(--white);
            margin-bottom: 1.5rem;
            line-height: 1.1;
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .hero-content .highlight {
            background: linear-gradient(45deg, #fbbf24, #f59e0b);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-content p {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
            line-height: 1.7;
            max-width: 32rem;
        }

        .hero-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .hero-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .floating-cards {
            position: relative;
            width: 100%;
            max-width: 400px;
        }

        .floating-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: var(--shadow-xl);
            position: relative;
            animation: float 6s ease-in-out infinite;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .floating-card:nth-child(1) {
            animation-delay: 0s;
            z-index: 3;
        }

        .floating-card:nth-child(2) {
            animation-delay: -2s;
            margin-top: -1rem;
            margin-left: 1rem;
            z-index: 2;
        }

        .floating-card:nth-child(3) {
            animation-delay: -4s;
            margin-top: -2rem;
            margin-left: -1rem;
            z-index: 1;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(2deg); }
        }

        .card-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
            display: block;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .card-text {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* Stats Section */
        .stats {
            background: var(--white);
            padding: 5rem 0;
        }

        .stats-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
            border-radius: 1.5rem;
            background: var(--gradient-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid var(--gray-light);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 3rem;
            font-weight: 900;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            font-size: 1.125rem;
            color: var(--dark);
            font-weight: 600;
        }

        /* Features Section */
        .features {
            background: var(--secondary);
            padding: 6rem 0;
        }

        .features-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.25rem;
            color: var(--gray);
            max-width: 42rem;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 2rem;
        }

        .feature-card {
            background: var(--white);
            padding: 2.5rem;
            border-radius: 1.5rem;
            box-shadow: var(--shadow);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--gray-light);
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-lg);
        }

        .feature-icon {
            font-size: 3rem;
            margin-bottom: 1.5rem;
            display: block;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .feature-text {
            color: var(--gray);
            line-height: 1.7;
        }

        /* CTA Section */
        .cta {
            background: var(--gradient-primary);
            padding: 6rem 0;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .cta::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1000 1000"><defs><radialGradient id="a" cx="50%" cy="50%"><stop offset="0%" stop-color="%23ffffff" stop-opacity="0.1"/><stop offset="100%" stop-color="%23ffffff" stop-opacity="0"/></radialGradient></defs><circle cx="200" cy="200" r="100" fill="url(%23a)"/><circle cx="800" cy="300" r="150" fill="url(%23a)"/></svg>');
            opacity: 0.3;
        }

        .cta-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 0 1.5rem;
            position: relative;
            z-index: 2;
        }

        .cta h2 {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 800;
            color: var(--white);
            margin-bottom: 1rem;
        }

        .cta p {
            font-size: 1.25rem;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 2rem;
        }

        /* Footer */
        .footer {
            background: var(--dark);
            color: var(--white);
            padding: 4rem 0 2rem;
        }

        .footer-container {
            max-width: 1280px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 1fr;
            gap: 3rem;
        }

        .footer-brand h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .footer-brand img {
            height: 2rem;
            width: auto;
        }

        .footer-brand p {
            color: var(--gray);
            line-height: 1.7;
            margin-bottom: 1.5rem;
        }

        .footer-section h4 {
            font-size: 1.125rem;
            margin-bottom: 1.5rem;
            color: var(--white);
        }

        .footer-section ul {
            list-style: none;
        }

        .footer-section ul li {
            margin-bottom: 0.75rem;
        }

        .footer-section ul li a {
            color: var(--gray);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .footer-section ul li a:hover {
            color: var(--primary-light);
        }

        .footer-bottom {
            border-top: 1px solid #334155;
            margin-top: 3rem;
            padding-top: 2rem;
            text-align: center;
            color: var(--gray);
        }

        /* Animations */
        .fade-in {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
        }

        .fade-in.visible {
            opacity: 1;
            transform: translateY(0);
        }

        /* Mobile Menu */
        .nav-menu.active {
            display: flex;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--white);
            flex-direction: column;
            padding: 1rem;
            box-shadow: var(--shadow-lg);
            border-radius: 0 0 1rem 1rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .hero-container {
                grid-template-columns: 1fr;
                gap: 3rem;
                text-align: center;
            }

            .hero-visual {
                order: -1;
            }

            .footer-container {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }

            .mobile-menu-toggle {
                display: block;
            }

            .nav-cta {
                display: none;
            }

            .hero-container {
                padding: 0 1rem;
                gap: 2rem;
            }

            .hero-content h1 {
                font-size: 2.5rem;
            }

            .hero-content p {
                font-size: 1.1rem;
            }

            .hero-actions {
                justify-content: center;
            }

            .btn {
                width: 100%;
                max-width: 280px;
                justify-content: center;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-card {
                padding: 1.5rem;
            }

            .stat-number {
                font-size: 2.5rem;
            }

            .footer-container {
                grid-template-columns: 1fr;
                gap: 2rem;
                text-align: center;
            }

            .floating-cards {
                max-width: 300px;
            }

            .floating-card {
                padding: 1.5rem;
            }
        }

        @media (max-width: 480px) {
            .nav-container {
                padding: 0 1rem;
            }

            .hero-container {
                padding: 0 1rem;
                gap: 1.5rem;
            }

            .hero-content h1 {
                font-size: 2rem;
                margin-bottom: 1rem;
            }

            .hero-content p {
                font-size: 1rem;
                margin-bottom: 1.5rem;
            }

            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .stat-card {
                padding: 1.25rem;
            }

            .stat-number {
                font-size: 2rem;
            }

            .stat-label {
                font-size: 1rem;
            }

            .features-container,
            .stats-container,
            .cta-container {
                padding: 0 1rem;
            }

            .section-title {
                font-size: 1.75rem;
            }

            .section-subtitle {
                font-size: 1rem;
            }

            .feature-card {
                padding: 1.5rem;
            }

            .feature-icon {
                font-size: 2.5rem;
            }

            .feature-title {
                font-size: 1.25rem;
            }

            .cta h2 {
                font-size: 1.75rem;
            }

            .cta p {
                font-size: 1rem;
            }

            .floating-cards {
                max-width: 250px;
            }

            .floating-card {
                padding: 1.25rem;
            }

            .card-icon {
                font-size: 2rem;
            }

            .card-title {
                font-size: 1.1rem;
            }

            .card-text {
                font-size: 0.9rem;
            }
        }

        @media (max-width: 360px) {
            .hero-content h1 {
                font-size: 1.75rem;
            }

            .stat-number {
                font-size: 1.75rem;
            }

            .section-title {
                font-size: 1.5rem;
            }

            .cta h2 {
                font-size: 1.5rem;
            }
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        /* Loading animation */
        .loading {
            opacity: 0;
            animation: fadeIn 0.6s ease-in forwards;
        }

        @keyframes fadeIn {
            to {
            opacity: 1;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar" id="navbar">
        <div class="nav-container">
            <a href="index.php" class="nav-logo">
                <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
                Bir Soru Bir Sevap
            </a>
            <ul class="nav-menu">
                <li><a href="#features" id="navFeatures">Özellikler</a></li>
                <li><a href="#stats" id="navStats">İstatistikler</a></li>
                <li><a href="#about" id="navAbout">Hakkımızda</a></li>
                <li><a href="contact.php" id="navContact">İletişim</a></li>
            </ul>
            <div class="nav-cta">
                <button id="langToggle" class="btn btn-secondary" style="padding:0.5rem 0.9rem;">DE</button>
                <a href="login.php" class="btn btn-primary" id="heroStartBtn">Başla</a>
            </div>
            <button class="mobile-menu-toggle" id="mobileMenuToggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>
    <!-- Mobile Menu Overlay -->
    <div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
    
    <!-- Mobile Menu Sidebar -->
    <div class="mobile-menu-sidebar" id="mobileMenuSidebar">
        <div class="mobile-menu-header">
            <a href="index.php" class="mobile-logo">
                <img src="logo.png" alt="Logo">
                <span>Bir Soru Bir Sevap</span>
            </a>
            <button class="mobile-menu-close" id="mobileMenuClose">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="mobile-menu-content">
            <ul class="mobile-nav-list">
                <li>
                    <a href="#features" class="mobile-nav-link">
                        <i class="fas fa-star"></i>
                        <span data-i18n="navFeatures">Özellikler</span>
                    </a>
                </li>
                <li>
                    <a href="#stats" class="mobile-nav-link">
                        <i class="fas fa-chart-bar"></i>
                        <span data-i18n="navStats">İstatistikler</span>
                    </a>
                </li>
                <li>
                    <a href="#about" class="mobile-nav-link">
                        <i class="fas fa-info-circle"></i>
                        <span data-i18n="navAbout">Hakkımızda</span>
                    </a>
                </li>
                <li>
                    <a href="contact.php" class="mobile-nav-link">
                        <i class="fas fa-envelope"></i>
                        <span data-i18n="navContact">İletişim</span>
                    </a>
                </li>
            </ul>
            
            <div class="mobile-menu-footer">
                <button id="langToggleMobile" class="btn btn-secondary w-full mb-3">
                    <i class="fas fa-globe"></i> <span>DE</span>
                </button>
                <a href="login.php" class="btn btn-primary w-full">
                    <i class="fas fa-sign-in-alt"></i> <span data-i18n="heroStartBtn">Başla</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-container">
        <div class="hero-content">
                <h1 id="heroTitle">Temel Bilgilerle Öğren, Güvenle İlerle</h1>
                <p id="heroSubtitle">3 seviyeli kapsamlı soru bankası: Temel Bilgiler 1, 2 ve 3. İtikad, İbadet, Siyer, Ahlak, Tefsir, Hadis ve daha fazlası. Eğitim uzmanları tarafından hazırlanmış kaliteli sorular.</p>
                <div class="hero-actions">
                    <a href="login.php" class="btn btn-primary" id="heroCtaBtn">
                        <i class="fas fa-rocket"></i>
                        Hemen Başla
                    </a>
                </div>
            </div>
            <div class="hero-visual">
                <div class="floating-cards">
                <div class="floating-card">
                    <div class="card-icon">📚</div>
                    <div class="card-title" id="floatTitle1">Temel Bilgiler 1</div>
                    <div class="card-text" id="floatText1">İtikad, İbadet, Siyer, Ahlak</div>
                </div>
                <div class="floating-card">
                    <div class="card-icon">📖</div>
                    <div class="card-title" id="floatTitle2">Temel Bilgiler 2</div>
                    <div class="card-text" id="floatText2">IGMG müfredatına uygun</div>
                </div>
                <div class="floating-card">
                    <div class="card-icon">📘</div>
                    <div class="card-title" id="floatTitle3">Temel Bilgiler 3</div>
                    <div class="card-text" id="floatText3">Tefsir, Hadis, Tasavvuf</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats" id="stats">
        <div class="stats-container">
            <div class="stats-grid">
                <div class="stat-card fade-in">
                    <div class="stat-number"><?php echo $totalQuestions; ?>+</div>
                    <div class="stat-label" id="statLabel1">Kaliteli Soru</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number">15+</div>
                    <div class="stat-label" id="statLabel3">Ders Konusu</div>
                </div>
                <div class="stat-card fade-in">
                    <div class="stat-number">100%</div>
                    <div class="stat-label" id="statLabel4">Müfredat Uyumlu</div>
                </div>
            </div>
        </div>
    </section>

    <!-- Badges Section -->
    <?php 
        $badgesFile = __DIR__ . '/data/badges.json';
        $badges = file_exists($badgesFile) ? (json_decode(file_get_contents($badgesFile), true) ?? []) : [];
    ?>
    <section class="features" id="badges" style="background: var(--gradient-secondary);">
        <div class="features-container">
            <div class="section-header fade-in">
                <h2 class="section-title" id="badgesTitle">Rozetler Sistemi</h2>
                <p class="section-subtitle" id="badgesSubtitle">Başarılarınızı rozetlerle taçlandırın. Şartları tamamlayın, seviyeleri yükseltin.</p>
            </div>
            <div class="features-grid">
                <?php foreach ($badges as $badge): 
                    $titleTr = $badge['name'] ?? '';
                    $titleDe = $badge['de_name'] ?? ($badge['de']['name'] ?? $titleTr);
                    $descTr  = $badge['description'] ?? '';
                    $descDe  = $badge['de_description'] ?? ($badge['de']['description'] ?? $descTr);
                    $critTr  = $badge['criteria'] ?? '';
                    $critDe  = $badge['de_criteria'] ?? ($badge['de']['criteria'] ?? $critTr);
                ?>
                <div class="feature-card fade-in">
                    <div class="feature-icon"><i class="fas <?php echo htmlspecialchars($badge['icon']); ?>"></i></div>
                    <div class="feature-title badge-title" data-tr="<?php echo htmlspecialchars($titleTr); ?>" data-de="<?php echo htmlspecialchars($titleDe); ?>"><?php echo htmlspecialchars($titleTr); ?></div>
                    <div class="feature-text">
                        <span class="badge-desc" data-tr="<?php echo htmlspecialchars($descTr); ?>" data-de="<?php echo htmlspecialchars($descDe); ?>"><?php echo htmlspecialchars($descTr); ?></span><br>
                        <small><span data-i18n="criteriaLabel">Koşullar:</span> <span class="badge-crit" data-tr="<?php echo htmlspecialchars($critTr); ?>" data-de="<?php echo htmlspecialchars($critDe); ?>"><?php echo htmlspecialchars($critTr); ?></span></small>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <div class="features-container">
            <div class="section-header fade-in">
                <h2 class="section-title" id="featuresTitle">Temel Bilgiler Müfredatı ile Güçlendirilmiş</h2>
                <p class="section-subtitle" id="featuresSubtitle">3 seviyeli kapsamlı soru bankası ve modern eğitim özellikleri</p>
            </div>
            <div class="features-grid">
                <div class="feature-card fade-in">
                    <div class="feature-icon">📚</div>
                    <div class="feature-title" id="featTitle1">Temel Bilgiler 1</div>
                    <div class="feature-text" id="featText1">İtikad, İbadet, Siyer, Ahlak, Musiki ve Teşkilat derslerinden 500+ kaliteli soru. Temel seviye öğrenciler için mükemmel başlangıç.</div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">📖</div>
                    <div class="feature-title" id="featTitle2">Temel Bilgiler 2</div>
                    <div class="feature-text" id="featText2">IGMG müfredatına uygun gelişmiş sorular. İtikad, İbadet, Siyer, Ahlak, Musiki ve Teşkilat konularında derinlemesine öğrenme.</div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">📘</div>
                    <div class="feature-title" id="featTitle3">Temel Bilgiler 3</div>
                    <div class="feature-text" id="featText3">İleri seviye konular: Tefsir, Hadis, Tasavvuf, Hitabet, İnsan Hakları ve İslam Tarihi. Uzmanlaşma için ideal içerik.</div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">🎯</div>
                    <div class="feature-title" id="featTitle4">Kaliteli Soru Bankası</div>
                    <div class="feature-text" id="featText4">Eğitim uzmanları tarafından hazırlanmış, müfredata uygun, çoktan seçmeli sorular. Her seviyeye uygun zorluk dereceleri.</div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">📊</div>
                    <div class="feature-title" id="featTitle5">Detaylı Analitik</div>
                    <div class="feature-text" id="featText5">Öğrenci performansını takip edin. Hangi konularda güçlü, hangilerinde gelişim gerekiyor? Detaylı raporlarla öğrenin.</div>
                </div>
                <div class="feature-card fade-in">
                    <div class="feature-icon">⚡</div>
                    <div class="feature-title" id="featTitle6">Hızlı ve Kolay</div>
                    <div class="feature-text" id="featText6">Anında sınav oluşturun, otomatik değerlendirme yapın. Eğitmenler için zaman tasarrufu, öğrenciler için etkili öğrenme.</div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="cta-container">
            <h2 class="fade-in" id="ctaTitle">Temel Bilgiler Müfredatı ile Eğitimi Dönüştürün</h2>
            <p class="fade-in" id="ctaSubtitle">3 seviyeli kapsamlı soru bankası, kaliteli içerik ve modern eğitim araçları. Öğrenciler ve eğitmenler için mükemmel çözüm!</p>
            <a href="login.php" class="btn btn-primary fade-in" id="ctaBtn">
                <i class="fas fa-star"></i>
                Hemen Başla
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="footer-container">
            <div class="footer-brand">
                <h3>
                    <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
                    Bir Soru Bir Sevap
                </h3>
                <p>Modern eğitim platformu ile öğrenmeyi eğlenceli hale getirin. Öğrenciler için alıştırmalar, öğretmenler için güçlü araçlar.</p>
            </div>
            <div class="footer-section">
                <h4 id="footFeaturesTitle">Özellikler</h4>
                <ul>
                    <li><a href="login.php" id="footFeature1">Soru Bankası</a></li>
                    <li><a href="login.php" id="footFeature2">Sınav Oluşturma</a></li>
                    <li><a href="login.php" id="footFeature3">Analitik Raporlar</a></li>
                    <li><a href="login.php" id="footFeature4">Mobil Uyumluluk</a></li>
                </ul>
            </div>
            <div class="footer-section">
                <h4 id="footSupportTitle">Destek</h4>
                <ul>
                    <li><a href="help.php" id="footSupport1">Yardım Merkezi</a></li>
                    <li><a href="contact.php" id="footSupport2">İletişim</a></li>
                    <li><a href="faq.php" id="footSupport3">SSS</a></li>
                    <li><a href="support.php" id="footSupport4">Teknik Destek</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2025 Bir Soru Bir Sevap. Tüm hakları saklıdır.</p>
            <p style="margin-top: 0.5rem; font-size: 0.875rem; opacity: 0.8;">
                Design and Coding by <a href="https://www.xezmet.at" target="_blank" style="color: var(--primary-light); text-decoration: none;">XezMet IT-Solutions</a>
            </p>
        </div>
    </footer>

    <script>
        // Dil toggle (TR/DE) - localStorage.lang
        (function(){
            const tr = {
                heroStartBtn: 'Başla',
                heroTitle: 'Temel Bilgilerle Öğren, Güvenle İlerle',
                heroSubtitle: '3 seviyeli kapsamlı soru bankası: Temel Bilgiler 1, 2 ve 3. İtikat, İbadet, Siyer, Ahlak, Tefsir, Hadis ve daha fazlası. Eğitim Başkanlığı tarafından hazırlanmış kaliteli sorular.',
                badgesTitle: 'Rozetler Sistemi',
                badgesSubtitle: 'Başarılarınızı rozetlerle taçlandırın. Şartları tamamlayın, seviyeleri yükseltin.',
                featuresTitle: 'Temel Bilgiler Müfredatıyla Desteklenen Akıllı Öğrenme',
                featuresSubtitle: '3 seviyeli kapsamlı soru bankası ve modern eğitim özellikleri',
                heroCtaBtn: 'Hemen Başla',
                ctaTitle: 'Temel Bilgiler Müfredatı ile Eğitimi Dönüştürün',
                ctaSubtitle: '3 seviyeli kapsamlı soru bankası, kaliteli içerik ve modern eğitim araçları. Öğrenciler ve eğitmenler için mükemmel çözüm!',
                ctaBtn: 'Hemen Başla',
                navFeatures:'Özellikler', navStats:'İstatistikler', navAbout:'Hakkımızda', navContact:'İletişim',
                stat1:'Kaliteli Soru', stat2:'Seviye', stat3:'Ders Konusu', stat4:'Müfredat Uyumlu',
                footFeaturesTitle:'Özellikler', footF1:'Soru Bankası', footF2:'Sınav Oluşturma', footF3:'Analitik Raporlar', footF4:'Mobil Uyumluluk',
                footSupportTitle:'Destek', footS1:'Yardım Merkezi', footS2:'İletişim', footS3:'SSS', footS4:'Teknik Destek',
                floatTitle1:'Temel Bilgiler 1', floatText1:'İtikat, İbadet, Siyer, Ahlak, Musiki, Teşkilat',
                floatTitle2:'Temel Bilgiler 2', floatText2:'İtikat, İbadet, Siyer, Ahlak, Musiki, Teşkilat',
                floatTitle3:'Temel Bilgiler 3', floatText3:'Tefsir, Hadis, Tasavvuf, Hitabet, İnsan Hakları, İslam Tarihi',
                featTitle1:'Temel Bilgiler 1', featText1:'İtikat, İbadet, Siyer, Ahlak, Musiki ve Teşkilat derslerinden 500+ kaliteli soru. Temel seviye öğrenciler için mükemmel başlangıç.',
                featTitle2:'Temel Bilgiler 2', featText2:'İtikat, İbadet, Siyer, Ahlak, Musiki ve Teşkilat konularında sorular. Derinlemesine öğrenme.',
                featTitle3:'Temel Bilgiler 3', featText3:'Tefsir, Hadis, Tasavvuf, Hitabet, İnsan Hakları ve İslam Tarihi gibi ileri seviye konular. Uzmanlaşma için ideal içerik.',
                featTitle4:'Kaliteli Soru Bankası', featText4:'Eğitim Başkanlığı tarafından hazırlanmış, müfredata uygun, çoktan seçmeli sorular. Her seviyeye uygun zorluk dereceleri.',
                featTitle5:'Detaylı Analitik', featText5:'Öğrenci performansını takip edin. Hangi konularda güçlü, hangilerinde gelişim gerekiyor? Detaylı raporlarla öğrenin.',
                featTitle6:'Hızlı ve Kolay', featText6:'Anında sınav oluşturun, otomatik değerlendirme yapın. Eğitmenler için zaman tasarrufu, öğrenciler için etkili öğrenme.',
                criteriaLabel:'Koşullar:'
            };
            const de = {
                heroStartBtn: 'Start',
                heroTitle: 'Lernen mit Grundkenntnissen, sicher voranschreiten',
                heroSubtitle: 'Umfassende Fragenbank in 3 Stufen: Grundkenntnisse 1, 2 und 3. Glaube, Gebet, Sīra, Moral, Exegese, Hadith und mehr. Vom Bildungspräsidium vorbereitete hochwertige Fragen.',
                badgesTitle: 'Abzeichensystem',
                badgesSubtitle: 'Krönen Sie Ihre Erfolge mit Abzeichen. Erfüllen Sie die Bedingungen, steigen Sie im Level auf.',
                featuresTitle: 'Gestärkt durch den Lehrplan der Grundkenntnisse',
                featuresSubtitle: 'Umfassende 3-stufige Fragenbank und moderne Lernfunktionen',
                heroCtaBtn: 'Jetzt starten',
                ctaTitle: 'Verwandeln Sie Bildung mit dem Grundkenntnisse-Lehrplan',
                ctaSubtitle: 'Umfassende 3-stufige Fragenbank, hochwertige Inhalte und moderne Lernwerkzeuge. Perfekt für Schüler und Lehrkräfte!',
                ctaBtn: 'Jetzt starten',
                navFeatures:'Funktionen', navStats:'Statistiken', navAbout:'Über uns', navContact:'Kontakt',
                stat1:'Hochwertige Fragen', stat2:'Stufen', stat3:'Fächer', stat4:'Lehrplankonform',
                footFeaturesTitle:'Funktionen', footF1:'Fragenbank', footF2:'Prüfung erstellen', footF3:'Analytische Berichte', footF4:'Mobilfreundlich',
                footSupportTitle:'Support', footS1:'Hilfezentrum', footS2:'Kontakt', footS3:'FAQ', footS4:'Technischer Support',
                floatTitle1:'Grundkenntnisse 1', floatText1:'Glaube, Gebet, Sīra, Moral, Musik, Organisation',
                floatTitle2:'Grundkenntnisse 2', floatText2:'Glaube, Gebet, Sīra, Moral, Musik, Organisation',
                floatTitle3:'Grundkenntnisse 3', floatText3:'Exegese, Hadith, Mystik, Rhetorik, Menschenrechte, Islamische Geschichte',
                featTitle1:'Grundkenntnisse 1', featText1:'Über 500 hochwertige Fragen aus Glaube, Gebet, Sīra, Moral, Musik und Organisation. Perfekter Einstieg für Anfänger.',
                featTitle2:'Grundkenntnisse 2', featText2:'Fragen zu Glaube, Gebet, Sīra, Moral, Musik und Organisation. Vertieftes Lernen.',
                featTitle3:'Grundkenntnisse 3', featText3:'Fortgeschrittene Themen: Exegese, Hadith, Mystik, Rhetorik, Menschenrechte und Islamische Geschichte. Ideal zur Spezialisierung.',
                featTitle4:'Hochwertige Fragenbank', featText4:'Von Experten erstellte, lehrplankonforme Multiple-Choice-Fragen. Schwierigkeitsgrade für jedes Niveau.',
                featTitle5:'Detaillierte Analytik', featText5:'Verfolgen Sie die Leistung der Schüler. Welche Themen sind stark, wo ist Entwicklung nötig? Lernen Sie es mit Berichten.',
                featTitle6:'Schnell & Einfach', featText6:'Erstellen Sie sofort Prüfungen und bewerten Sie automatisch. Zeitersparnis für Lehrkräfte, effektives Lernen für Schüler.',
                criteriaLabel:'Kriterien:'
            };
            function applyLang(lang){
                const dict = lang === 'de' ? de : tr;
                const set = (id, val)=>{ const el = document.getElementById(id); if (el) el.innerHTML = val; };
                set('heroStartBtn', dict.heroStartBtn);
                set('heroTitle', dict.heroTitle);
                set('heroSubtitle', dict.heroSubtitle);
                set('badgesTitle', dict.badgesTitle);
                set('badgesSubtitle', dict.badgesSubtitle);
                set('featuresTitle', dict.featuresTitle);
                set('featuresSubtitle', dict.featuresSubtitle);
                set('heroCtaBtn', '<i class="fas fa-rocket"></i> ' + dict.heroCtaBtn);
                set('ctaTitle', dict.ctaTitle);
                set('ctaSubtitle', dict.ctaSubtitle);
                set('ctaBtn', '<i class="fas fa-star"></i> ' + dict.ctaBtn);
                set('navFeatures', dict.navFeatures);
                set('navStats', dict.navStats);
                set('navAbout', dict.navAbout);
                set('navContact', dict.navContact);
                set('statLabel1', dict.stat1);
                set('statLabel2', dict.stat2);
                set('statLabel3', dict.stat3);
                set('statLabel4', dict.stat4);
                set('footFeaturesTitle', dict.footFeaturesTitle);
                set('footFeature1', dict.footF1);
                set('footFeature2', dict.footF2);
                set('footFeature3', dict.footF3);
                set('footFeature4', dict.footF4);
                set('footSupportTitle', dict.footSupportTitle);
                set('footSupport1', dict.footS1);
                set('footSupport2', dict.footS2);
                set('footSupport3', dict.footS3);
                set('footSupport4', dict.footS4);
                set('floatTitle1', dict.floatTitle1);
                set('floatText1', dict.floatText1);
                set('floatTitle2', dict.floatTitle2);
                set('floatText2', dict.floatText2);
                set('floatTitle3', dict.floatTitle3);
                set('floatText3', dict.floatText3);
                set('featTitle1', dict.featTitle1);
                set('featText1', dict.featText1);
                set('featTitle2', dict.featTitle2);
                set('featText2', dict.featText2);
                set('featTitle3', dict.featTitle3);
                set('featText3', dict.featText3);
                set('featTitle4', dict.featTitle4);
                set('featText4', dict.featText4);
                set('featTitle5', dict.featTitle5);
                set('featText5', dict.featText5);
                set('featTitle6', dict.featTitle6);
                set('featText6', dict.featText6);
                // Rozet kartları koşul etiketi
                document.querySelectorAll('[data-i18n="criteriaLabel"]').forEach(function(el){ el.textContent = dict.criteriaLabel; });
                // Rozet kartları: eğer data-tr/data-de varsa onları uygula
                document.querySelectorAll('.badge-title').forEach(function(el){
                    const val = (lang==='de' ? el.getAttribute('data-de') : el.getAttribute('data-tr')) || el.textContent;
                    if (val) el.textContent = val;
                });
                document.querySelectorAll('.badge-desc').forEach(function(el){
                    const val = (lang==='de' ? el.getAttribute('data-de') : el.getAttribute('data-tr')) || el.textContent;
                    if (val) el.textContent = val;
                });
                document.querySelectorAll('.badge-crit').forEach(function(el){
                    const val = (lang==='de' ? el.getAttribute('data-de') : el.getAttribute('data-tr')) || el.textContent;
                    if (val) el.textContent = val;
                });
                const toggle = document.getElementById('langToggle');
                if (toggle) toggle.textContent = lang === 'de' ? 'TR' : 'DE';
                // Rozet başlık/açıklamaları için örnek çeviri eşlemesi
                document.querySelectorAll('.features-grid .feature-card .badge-title').forEach(function(el){
                    const title = el.textContent.trim().toLowerCase();
                    const map = {
                        'hilal':'halbmond','kabe':'kaaba','ilim':'wissen','tesbih':'gebetskette','sabir':'geduld','kalem':'stift','kitap':'buch','azim':'entschlossenheit','ihsan':'exzellenz'
                    };
                    if (lang==='de' && map[title]) el.textContent = map[title].charAt(0).toUpperCase() + map[title].slice(1);
                });
                localStorage.setItem('lang', lang);

                // DE seçiliyken Türkçe kalan metinleri dinamik çevir (DeepL)
                if (lang === 'de') {
                    // data-de olmayan ya da boş olan metinleri topla
                    const toTranslate = [];
                    const nodes = [];
                    document.querySelectorAll('[data-i18n], .badge-title, .badge-desc, .badge-crit').forEach(function(el){
                        const hasDe = el.getAttribute('data-de');
                        const trText = el.getAttribute('data-tr') || el.textContent;
                        if (!hasDe || hasDe.trim() === '') {
                            const txt = (trText || '').trim();
                            if (txt) {
                                toTranslate.push(txt);
                                nodes.push(el);
                            }
                        }
                    });
                    if (toTranslate.length) {
                        fetch('translate.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ texts: toTranslate, target: 'DE', use_cache: true, write_cache: true })
                        }).then(r=>r.json()).then(j=>{
                            if (j && Array.isArray(j.translations)) {
                                j.translations.forEach(function(text, idx){
                                    const el = nodes[idx];
                                    if (el && text) {
                                        el.setAttribute('data-de', text);
                                        el.textContent = text;
                                    }
                                });
                            }
                        }).catch(()=>{});
                    }
                }
            }
            document.addEventListener('DOMContentLoaded', function(){
                const saved = localStorage.getItem('lang') || 'tr';
                applyLang(saved);
                const toggle = document.getElementById('langToggle');
                if (toggle) toggle.addEventListener('click', function(){
                    const next = (localStorage.getItem('lang') || 'tr') === 'tr' ? 'de' : 'tr';
                    applyLang(next);
                });
                const toggleMobile = document.getElementById('langToggleMobile');
                if (toggleMobile) toggleMobile.addEventListener('click', function(){
                    const next = (localStorage.getItem('lang') || 'tr') === 'tr' ? 'de' : 'tr';
                    applyLang(next);
                });
                // Drawer etiketleri diline göre
                const syncDrawerLabels = (lang)=>{ 
                    const t=document.querySelector('#mobileMenuSidebar #langToggleMobile span'); 
                    if(t) t.textContent = (lang==='de'?'TR':'DE'); 
                };
                // applyLang sonuna wrapper
                const _apply = applyLang;
                applyLang = function(lang){ _apply(lang); syncDrawerLabels(lang); };
            });
        })();
        // Navbar scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Scroll animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('visible');
                }
            });
        }, observerOptions);

        document.querySelectorAll('.fade-in').forEach(el => {
            observer.observe(el);
        });

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Counter animation for stats
        function animateCounter(element, target, duration = 2000) {
            const original = element.textContent.trim();
            const hasPlus = original.endsWith('+');
            const hasPercent = original.endsWith('%');
            const suffix = hasPlus ? '+' : (hasPercent ? '%' : '');
            let pure = original.replace(/[^0-9]/g, '');
            let numericTarget = parseInt(pure || '0', 10);
            if (!isNaN(target) && target > 0) {
                numericTarget = target;
            }
            let start = 0;
            const increment = numericTarget / (duration / 16);
            function updateCounter() {
                start += increment;
                if (start < numericTarget) {
                    element.textContent = Math.floor(start) + suffix;
                    requestAnimationFrame(updateCounter);
                } else {
                    element.textContent = numericTarget + suffix;
                }
            }
            updateCounter();
        }

        // Animate counters when they come into view
        const counterObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const counter = entry.target.querySelector('.stat-number');
                    // Sonekleri koruyarak animasyon yap
                    animateCounter(counter, NaN);
                    counterObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.5 });

        document.querySelectorAll('.stat-card').forEach(item => {
            counterObserver.observe(item);
        });

        // Mobile menu toggle
        // Mobile menu toggle
        const mobileMenuToggle = document.getElementById('mobileMenuToggle');
        const mobileMenuClose = document.getElementById('mobileMenuClose');
        const mobileMenuSidebar = document.getElementById('mobileMenuSidebar');
        const mobileMenuOverlay = document.getElementById('mobileMenuOverlay');

        function toggleMenu() {
            const isActive = mobileMenuSidebar.classList.contains('active');
            if (isActive) {
                mobileMenuSidebar.classList.remove('active');
                mobileMenuOverlay.classList.remove('active');
                document.body.style.overflow = '';
            } else {
                mobileMenuSidebar.classList.add('active');
                mobileMenuOverlay.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        }

        if (mobileMenuToggle) mobileMenuToggle.addEventListener('click', toggleMenu);
        if (mobileMenuClose) mobileMenuClose.addEventListener('click', toggleMenu);
        if (mobileMenuOverlay) mobileMenuOverlay.addEventListener('click', toggleMenu);

        // Close menu when clicking a link
        document.querySelectorAll('.mobile-nav-link').forEach(link => {
            link.addEventListener('click', () => {
                if (mobileMenuSidebar.classList.contains('active')) {
                    toggleMenu();
                }
            });
        });

        // Loading animation
        document.addEventListener('DOMContentLoaded', function() {
            document.body.classList.add('loading');
        });
    </script>
<script>
    // Her anasayfa yüklemesinde tarayıcı depolarını temizle
    (function () {
        const clearClientStorage = async () => {
            try {
                if (window.localStorage) {
                    localStorage.clear();
                }
                if (window.sessionStorage) {
                    sessionStorage.clear();
                }

                if ('serviceWorker' in navigator) {
                    const regs = await navigator.serviceWorker.getRegistrations();
                    for (const reg of regs) {
                        await reg.unregister();
                    }
                }

                if (window.caches && typeof caches.keys === 'function') {
                    const cacheNames = await caches.keys();
                    await Promise.all(cacheNames.map((cacheName) => caches.delete(cacheName)));
                }

                const cookies = document.cookie ? document.cookie.split(';') : [];
                cookies.forEach((cookie) => {
                    const eqPos = cookie.indexOf('=');
                    const name = eqPos > -1 ? cookie.substr(0, eqPos).trim() : cookie.trim();
                    if (name) {
                        document.cookie = name + '=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/';
                    }
                });
            } catch (error) {
                console.error('Tarayıcı verileri temizlenirken hata oluştu:', error);
            }
        };

        document.addEventListener('DOMContentLoaded', clearClientStorage);
    })();
</script>
    
    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then((registration) => {
                        console.log('SW registered: ', registration);
                    })
                    .catch((registrationError) => {
                        console.log('SW registration failed: ', registrationError);
                    });
            });
            
            // PWA Install Prompt
            let deferredPrompt;
            
            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                
                // Install butonu gösterebilirsiniz
                const installButton = document.getElementById('install-pwa-button');
                if (installButton) {
                    installButton.style.display = 'block';
                    installButton.addEventListener('click', async () => {
                        deferredPrompt.prompt();
                        const { outcome } = await deferredPrompt.userChoice;
                        console.log(`User response: ${outcome}`);
                        deferredPrompt = null;
                        installButton.style.display = 'none';
                    });
                }
            });
            
            window.addEventListener('appinstalled', () => {
                console.log('PWA was installed');
                deferredPrompt = null;
            });
        }
    </script>
    <script src="js/cookie-consent.js"></script>
</body>
</html>