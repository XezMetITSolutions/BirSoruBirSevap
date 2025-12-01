<?php
/**
 * Sınav PDF Çıktısı
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Eğitmen kontrolü
if (!$auth->hasRole('teacher')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Öğretmenin şube bilgisini al (öncelik: institution → branch → class_section)
$teacherSection = $user['institution'] ?? ($user['branch'] ?? ($user['class_section'] ?? 'Genel'));

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$banks = $_SESSION['banks'] ?? [];

// Debug: Soru yükleme durumunu kontrol et
if (empty($questions) || empty($categories) || empty($banks)) {
    error_log("QuestionLoader Debug - Questions: " . count($questions) . ", Categories: " . count($categories) . ", Banks: " . count($banks));
    error_log("QuestionLoader Errors: " . print_r($questionLoader->getErrors(), true));
    
    // Test için manuel kategoriler ekle
    if (empty($banks)) {
        $banks = ['Temel Bilgiler 1', 'Temel Bilgiler 2', 'Temel Bilgiler 3'];
    }
    if (empty($categories)) {
        $categories = [
            'Temel Bilgiler 1' => ['İtikat', 'Ahlak', 'İbadet', 'Siyer', 'Musiki', 'Teşkilat'],
            'Temel Bilgiler 2' => ['İtikat', 'Ahlak', 'İbadet', 'Siyer', 'Musiki', 'Teşkilat'],
            'Temel Bilgiler 3' => ['İtikat', 'Ahlak', 'İbadet', 'Siyer', 'Hadis', 'Hitabet', 'İnsan Hakları', 'İslam Tarihi', 'Tasavvuf', 'Tefsir', 'Türkçe']
        ];
    }
}

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
        $cleanCategory = str_replace('igmg', '', $cleanCategory);
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
        
        // Aynı konuyu birleştir
        if (!in_array($cleanCategory, $groupedCategories[$bank])) {
            $groupedCategories[$bank][] = $cleanCategory;
        }
    }
}

// PDF ayarlarını yükle
$pdfSettings = [
    'logo_size' => 20,
    'logo_position' => 'left',
    'header_font_size' => 12,
    'question_font_size' => 10,
    'option_font_size' => 9,
    'line_spacing' => 1.2,
    'margin_top' => 30,
    'margin_bottom' => 25,
    'margin_left' => 15,
    'margin_right' => 15,
    'show_student_info' => true,
    'show_answer_key' => true,
    'page_numbers' => true,
    'show_date' => true,
    'show_teacher_info' => true,
    'question_numbering' => 'numeric',
    'option_style' => 'letters',
    'header_style' => 'line',
    'footer_style' => 'simple',
];

// Kaydedilmiş ayarları yükle
if (file_exists('pdf_settings.json')) {
    $savedSettings = json_decode(file_get_contents('pdf_settings.json'), true);
    if ($savedSettings) {
        $pdfSettings = array_merge($pdfSettings, $savedSettings);
    }
}

// PDF oluşturma işlemi
$pdfGenerated = false;
$errorMessage = '';

// TCPDF yolunu otomatik bul (vendor ve dahili klasör destekli)
if (!function_exists('resolveTcpdfPath')) {
    function resolveTcpdfPath() {
        $candidates = [
            __DIR__ . '/../TCPDF-main/tcpdf.php',
            __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
            __DIR__ . '/../vendor/tcpdf/tcpdf.php',
            __DIR__ . '/../vendor/autoload.php', // composer autoload
        ];
        foreach ($candidates as $path) {
            if (file_exists($path)) return $path;
        }
        return null;
    }
}

// Geçici: TCPDF akışı devre dışı
if (false && $_POST) {
    $examTitle = $_POST['exam_title'] ?? '';
    $questionCount = (int)($_POST['question_count'] ?? 10);
    $selectedCategories = $_POST['categories'] ?? [];
    $examDescription = $_POST['exam_description'] ?? '';
    $questionType = $_POST['question_type'] ?? 'random';
    
    if (empty($examTitle) || empty($questionCount)) {
        $errorMessage = 'Lütfen sınav başlığı ve soru sayısını girin.';
    } elseif ($questionType !== 'custom' && empty($selectedCategories)) {
        $errorMessage = 'Lütfen en az bir konu seçin.';
    } else {
        // Soruları yükle ve filtrele
        $questionLoader = new QuestionLoader();
        $questionLoader->loadQuestions();
        
        $selectedQuestions = [];
        
        if ($questionType === 'custom') {
            // Özel sorular için
            $customQuestions = $_POST['custom_questions'] ?? [];
            foreach ($customQuestions as $customQuestion) {
                if (!empty($customQuestion['question'])) {
                    $selectedQuestions[] = [
                        'id' => 'custom_' . uniqid(),
                        'question' => $customQuestion['question'],
                        'type' => $customQuestion['type'] ?? 'mcq',
                        'options' => $customQuestion['options'] ?? [],
                        'correct_answer' => $customQuestion['correct_answer'] ?? '',
                        'explanation' => $customQuestion['explanation'] ?? '',
                        'difficulty' => 'Orta',
                        'bank' => 'Özel',
                        'category' => 'Özel Sorular'
                    ];
                }
            }
        } else {
            // Rastgele veya manuel seçim için
            $filteredQuestions = [];
            
            if ($questionType === 'manual') {
                // Manuel seçim için seçilen soruları al
                $selectedQuestionIds = $_POST['selected_questions'] ?? [];
                $allQuestions = $_SESSION['all_questions'] ?? [];
                
                foreach ($allQuestions as $question) {
                    if (in_array($question['id'], $selectedQuestionIds)) {
                        $filteredQuestions[] = $question;
                    }
                }
            } else {
                // Rastgele için kategori desteği
                foreach ($selectedCategories as $categoryData) {
                    $parts = explode('|', $categoryData);
                    $bank = $parts[0] ?? '';
                    $category = $parts[1] ?? '';
                    
                    $categoryQuestions = $questionLoader->getFilteredQuestions([
                        'bank' => $bank,
                        'category' => $category,
                        'count' => 999 // Tüm soruları al
                    ]);
                    
                    $filteredQuestions = array_merge($filteredQuestions, $categoryQuestions);
                }
                
                // Sınav sorularını karıştır ve seç
                shuffle($filteredQuestions);
            }
            
            $selectedQuestions = array_slice($filteredQuestions, 0, $questionCount);
        }
        
        if (count($selectedQuestions) > 0) {
            // PDF oluştur
            $tcpdfPath = resolveTcpdfPath();
            if ($tcpdfPath) {
                require_once $tcpdfPath;
                // Autoload yüklendiyse ve TCPDF sınıfı gelmediyse doğrudan tcpdf.php dosyasını da dene
                if (!class_exists('TCPDF', false)) {
                    $altCandidates = [
                        __DIR__ . '/../TCPDF-main/tcpdf.php',
                        __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php',
                        __DIR__ . '/../vendor/tcpdf/tcpdf.php',
                    ];
                    foreach ($altCandidates as $alt) {
                        if (file_exists($alt)) { require_once $alt; break; }
                    }
                }
                
                try {
                $orientation = defined('PDF_PAGE_ORIENTATION') ? PDF_PAGE_ORIENTATION : 'P';
                $unit = defined('PDF_UNIT') ? PDF_UNIT : 'mm';
                $format = defined('PDF_PAGE_FORMAT') ? PDF_PAGE_FORMAT : 'A4';
                $pdf = new TCPDF($orientation, $unit, $format, true, 'UTF-8', false);
                
                // PDF ayarları
                $pdf->SetCreator('Bir Soru Bir Sevap');
                $pdf->SetAuthor($user['name']);
                $pdf->SetTitle($examTitle);
                $pdf->SetSubject('Sınav PDF');
                
                // Sayfa ayarları
                $pdf->SetMargins($pdfSettings['margin_left'], $pdfSettings['margin_top'], $pdfSettings['margin_right']);
                $pdf->SetHeaderMargin(10);
                $pdf->SetFooterMargin(15);
                $pdf->SetAutoPageBreak(TRUE, $pdfSettings['margin_bottom']);
                
                // Header ve Footer ayarları
                $pdf->setPrintHeader(true);
                $pdf->setPrintFooter(true);
                
                // Font ayarları
                $pdf->SetFont('dejavusans', '', 10);
                
                // Header fonksiyonu
                $pdf->setHeaderCallback(function($pdf) use ($examTitle, $pdfSettings) {
                    // Logo
                    if ($pdfSettings['logo_position'] !== 'none') {
                        $logoPath = '../logo.png';
                        if (file_exists($logoPath)) {
                            $x = 15; // Sol
                            if ($pdfSettings['logo_position'] === 'center') {
                                $x = 95; // Orta
                            } elseif ($pdfSettings['logo_position'] === 'right') {
                                $x = 175; // Sağ
                            }
                            $pdf->Image($logoPath, $x, 8, $pdfSettings['logo_size'], 0, 'PNG');
                        }
                    }
                    
                    // Başlık
                    $pdf->SetFont('dejavusans', 'B', $pdfSettings['header_font_size']);
                    $pdf->SetY(10);
                    $pdf->Cell(0, 8, $examTitle, 0, 1, 'C');
                    
                    // Header stili
                    if ($pdfSettings['header_style'] === 'line') {
                        $pdf->SetLineWidth(0.5);
                        $pdf->Line(15, 25, 195, 25);
                    } elseif ($pdfSettings['header_style'] === 'box') {
                        $pdf->SetLineWidth(1);
                        $pdf->Rect(15, 5, 180, 20);
                    }
                });
                
                // Footer fonksiyonu
                $pdf->setFooterCallback(function($pdf) use ($pdfSettings) {
                    if ($pdfSettings['page_numbers']) {
                        $pdf->SetY(-15);
                        $pdf->SetFont('dejavusans', '', 8);
                        
                        if ($pdfSettings['footer_style'] === 'detailed') {
                            $footerText = 'Sayfa ' . $pdf->getAliasNumPage() . ' / ' . $pdf->getAliasNbPages() . ' | ' . date('d.m.Y H:i') . ' | Bir Soru Bir Sevap';
                        } else {
                            $footerText = 'Sayfa ' . $pdf->getAliasNumPage() . ' / ' . $pdf->getAliasNbPages();
                        }
                        
                        $pdf->Cell(0, 10, $footerText, 0, 0, 'C');
                    }
                });
                
                // Sayfa ekle
                $pdf->AddPage();
                
                // Sınav bilgileri
                if ($pdfSettings['show_teacher_info']) {
                    $pdf->SetFont('dejavusans', '', 10);
                    $pdf->Cell(0, 6, 'Eğitmen: ' . $user['name'], 0, 1);
                    $pdf->Cell(0, 6, 'Soru Sayısı: ' . count($selectedQuestions), 0, 1);
                    if ($pdfSettings['show_date']) {
                        $pdf->Cell(0, 6, 'Tarih: ' . date('d.m.Y H:i'), 0, 1);
                    }
                    if (!empty($examDescription)) {
                        $pdf->Cell(0, 6, 'Açıklama: ' . $examDescription, 0, 1);
                    }
                    $pdf->Ln(10);
                }
                
                // Öğrenci bilgileri için boş alan
                if ($pdfSettings['show_student_info']) {
                    $pdf->SetFont('dejavusans', 'B', 12);
                    $pdf->Cell(0, 8, 'ÖĞRENCİ BİLGİLERİ', 0, 1);
                    $pdf->SetFont('dejavusans', '', 10);
                    $pdf->Cell(40, 6, 'Ad Soyad:', 0, 0);
                    $pdf->Cell(60, 6, '_________________', 0, 0);
                    $pdf->Cell(40, 6, 'Sınıf:', 0, 0);
                    $pdf->Cell(0, 6, '_________________', 0, 1);
                    $pdf->Cell(40, 6, 'Numara:', 0, 0);
                    $pdf->Cell(60, 6, '_________________', 0, 0);
                    $pdf->Cell(40, 6, 'Tarih:', 0, 0);
                    $pdf->Cell(0, 6, '_________________', 0, 1);
                    $pdf->Ln(10);
                }
                
                // Sorular
                $pdf->SetFont('dejavusans', 'B', 12);
                $pdf->Cell(0, 8, 'SORULAR', 0, 1);
                $pdf->Ln(5);
                
                foreach ($selectedQuestions as $index => $question) {
                    // Soru numarası
                    $questionNumber = $index + 1;
                    if ($pdfSettings['question_numbering'] === 'alphabetic') {
                        $questionNumber = chr(64 + $questionNumber);
                    }
                    
                    // Soru metni
                    $pdf->SetFont('dejavusans', 'B', $pdfSettings['question_font_size']);
                    $pdf->Cell(0, 6, $questionNumber . '. ' . $question['question'], 0, 1);
                    $pdf->Ln(2);
                    
                    // Seçenekler
                    if (isset($question['options']) && is_array($question['options'])) {
                        $pdf->SetFont('dejavusans', '', $pdfSettings['option_font_size']);
                        foreach ($question['options'] as $optionIndex => $option) {
                            if ($pdfSettings['option_style'] === 'letters') {
                                $optionLabel = chr(65 + $optionIndex) . ')'; // A, B, C, D
                            } elseif ($pdfSettings['option_style'] === 'numbers') {
                                $optionLabel = ($optionIndex + 1) . ')'; // 1, 2, 3, 4
                            } else {
                                $optionLabel = '• '; // Bullet
                            }
                            $pdf->Cell(10, 5, $optionLabel, 0, 0);
                            $pdf->Cell(0, 5, $option, 0, 1);
                        }
                    } elseif ($question['type'] === 'true_false') {
                        $pdf->SetFont('dejavusans', '', $pdfSettings['option_font_size']);
                        if ($pdfSettings['option_style'] === 'letters') {
                            $pdf->Cell(10, 5, 'A)', 0, 0);
                            $pdf->Cell(0, 5, 'Doğru', 0, 1);
                            $pdf->Cell(10, 5, 'B)', 0, 0);
                            $pdf->Cell(0, 5, 'Yanlış', 0, 1);
                        } else {
                            $pdf->Cell(10, 5, '1)', 0, 0);
                            $pdf->Cell(0, 5, 'Doğru', 0, 1);
                            $pdf->Cell(10, 5, '2)', 0, 0);
                            $pdf->Cell(0, 5, 'Yanlış', 0, 1);
                        }
                    } elseif ($question['type'] === 'short_answer') {
                        $pdf->SetFont('dejavusans', '', $pdfSettings['option_font_size']);
                        $pdf->Cell(0, 5, 'Cevap: _________________', 0, 1);
                    }
                    
                    $pdf->Ln(8);
                }
                
                // Cevaplar sayfası
                if ($pdfSettings['show_answer_key']) {
                    $pdf->AddPage();
                    $pdf->SetFont('dejavusans', 'B', 16);
                    $pdf->Cell(0, 10, 'CEVAP ANAHTARI', 0, 1, 'C');
                    $pdf->Ln(10);
                    
                    $pdf->SetFont('dejavusans', 'B', 12);
                    $pdf->Cell(0, 8, 'DOĞRU CEVAPLAR', 0, 1);
                    $pdf->Ln(5);
                    
                    foreach ($selectedQuestions as $index => $question) {
                        $questionNumber = $index + 1;
                        if ($pdfSettings['question_numbering'] === 'alphabetic') {
                            $questionNumber = chr(64 + $questionNumber);
                        }
                        
                        $correctAnswer = '';
                        
                        if (isset($question['correct_answer'])) {
                            if (is_numeric($question['correct_answer'])) {
                                if ($pdfSettings['option_style'] === 'letters') {
                                    $correctAnswer = chr(65 + (int)$question['correct_answer']);
                                } else {
                                    $correctAnswer = (int)$question['correct_answer'] + 1;
                                }
                            } else {
                                $correctAnswer = $question['correct_answer'];
                            }
                        } elseif (isset($question['options']) && is_array($question['options'])) {
                            $correctAnswer = $pdfSettings['option_style'] === 'letters' ? 'A' : '1';
                        }
                        
                        $pdf->SetFont('dejavusans', '', 10);
                        $pdf->Cell(20, 5, $questionNumber . '.', 0, 0);
                        $pdf->Cell(0, 5, $correctAnswer, 0, 1);
                    }
                }
                
                // PDF'i indir
                $filename = 'sinav_' . date('Y-m-d_H-i-s') . '.pdf';
                $pdf->Output($filename, 'D');
                exit;
                
                } catch (Exception $e) {
                    $errorMessage = 'PDF oluşturulurken hata oluştu: ' . $e->getMessage() . ' • Tanılama: teacher/pdf_debug.php';
                    error_log('[exam_pdf] TCPDF Exception: ' . $e->getMessage());
                }
            } else {
                $errorMessage = 'TCPDF bulunamadı. Lütfen "TCPDF-main" klasörünün mevcut olduğundan veya "composer install" çalıştırıldığından emin olun. Tanılama için: teacher/pdf_debug.php';
            }
        } else {
            $errorMessage = 'Seçilen kategorilerde yeterli soru bulunamadı.';
        }
    }
}


// Öğretmen bölümünü al
$teacherSection = $user['institution'] ?? $user['branch'] ?? 'Bilinmiyor';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PDF Sınav Oluştur - Bir Soru Bir Sevap</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #089473;
            --primary-dark: #068466;
            --secondary: #6c757d;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #3b82f6;
            --light: #f8f9fa;
            --dark: #2c3e50;
            --white: #ffffff;
            --gray: #6c757d;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 30px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .logo h1 {
            color: var(--primary);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .logo p {
            color: var(--gray);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        /* Uzun kullanıcı adını taşırmadan kısalt */
        .user-info > div {
            max-width: 45vw;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            background: rgba(255, 255, 255, 0.1);
            border: 2px solid rgba(255, 255, 255, 0.3);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9em;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            color: white;
            transform: translateY(-1px);
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 20px;
            text-decoration: none;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .page-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: white;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }

        .page-subtitle {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 400;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary);
        }
        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 1rem;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
            color: var(--dark);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(8, 148, 115, 0.1);
            transform: translateY(-1px);
        }
        
        .btn {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(8, 148, 115, 0.4);
        }
        
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
        }
        
        .btn-secondary:hover {
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }
        
        .category-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .category-item {
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            padding: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
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
            background: linear-gradient(90deg, transparent, rgba(8, 148, 115, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .category-item:hover::before {
            left: 100%;
        }
        
        .category-item:hover {
            border-color: var(--primary);
            background: rgba(8, 148, 115, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.2);
        }
        
        .category-item.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.1) 0%, rgba(8, 148, 115, 0.05) 100%);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }
        
        .category-item input[type="checkbox"] {
            margin-right: 0.5rem;
            transform: scale(1.2);
        }
        
        .category-item label {
            cursor: pointer;
            font-weight: 500;
            color: var(--dark);
        }
        
        .error-message {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
            border-left: 4px solid #dc2626;
        }
        
        .success-message {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
            border-left: 4px solid #10b981;
        }
        
        .custom-questions-section {
            display: none;
        }
        
        .custom-question-item {
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            background: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .custom-question-item:hover {
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
            transform: translateY(-2px);
        }
        
        .option-inputs > div {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 12px;
            background: #f8f9fa;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .option-inputs > div::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(8, 148, 115, 0.1), transparent);
            transition: left 0.5s ease;
        }
        
        .option-inputs > div:hover::before {
            left: 100%;
        }
        
        .option-inputs > div:hover {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.05) 0%, rgba(8, 148, 115, 0.02) 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.2);
        }
        
        .option-inputs input[type="radio"]:checked + span {
            color: var(--primary);
            font-weight: 700;
        }
        
        .option-inputs > div:has(input[type="radio"]:checked) {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.1) 0%, rgba(8, 148, 115, 0.05) 100%);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }
        
        .question-type-section {
            margin-bottom: 30px;
        }

        .question-type-options {
            display: grid;
            grid-template-columns: 1fr;
            gap: 15px;
        }

        .question-type-option {
            display: flex;
            align-items: center;
            padding: 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
            overflow: hidden;
        }

        .question-type-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(8, 148, 115, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .question-type-option:hover::before {
            left: 100%;
        }

        .question-type-option:hover {
            border-color: var(--primary);
            background: rgba(8, 148, 115, 0.05);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.2);
        }

        .question-type-option input[type="radio"] {
            margin-right: 15px;
            transform: scale(1.3);
        }

        .question-type-option input[type="radio"]:checked + div {
            color: var(--primary);
        }

        .question-type-option:has(input[type="radio"]:checked) {
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.1) 0%, rgba(8, 148, 115, 0.05) 100%);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }

        .question-type-option div:first-child {
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .question-type-option div:last-child {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .category-selection {
            margin-bottom: 30px;
        }

        .bank-section { margin-bottom: 12px; }

        .bank-title {
            font-size: 1.05em;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 6px;
            padding: 12px 16px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 12px;
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            justify-content: space-between;
            cursor: pointer;
        }
        .bank-title .chev { font-size: .9em; opacity: .7; transition: transform .2s ease; }
        .bank-title.open .chev { transform: rotate(180deg); }
        .bank-categories { display: none; }

        .category-item {
            padding: 20px;
            border: 2px solid #e1e8ed;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
            overflow: hidden;
            margin-bottom: 15px;
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
            border-color: var(--primary);
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.05) 0%, rgba(8, 148, 115, 0.02) 100%);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(8, 148, 115, 0.15);
        }

        .category-item.selected {
            border-color: var(--primary);
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(8, 148, 115, 0.3);
        }

        .category-checkbox {
            display: none;
        }

        .category-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            font-weight: 500;
        }

        .category-checkbox:checked + .category-label {
            color: white;
        }

        .custom-checkbox {
            width: 20px;
            height: 20px;
            border: 2px solid #e1e8ed;
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .category-checkbox:checked + .category-label .custom-checkbox {
            background: white;
            border-color: white;
        }

        .category-checkbox:checked + .category-label .custom-checkbox::after {
            content: '✓';
            color: var(--primary);
            font-weight: bold;
            font-size: 14px;
        }

        .expand-icon {
            font-size: 1.2em;
            transition: transform 0.3s ease;
        }

        .questions-container {
            display: none;
            padding: 0 15px 15px 15px;
            border-top: 1px solid #e1e8ed;
            margin-top: 10px;
        }

        .questions-loading {
            text-align: center;
            padding: 20px;
            color: #7f8c8d;
        }

        .questions-list {
            display: none;
        }

        .question-item {
            padding: 10px;
            border: 1px solid #e1e8ed;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .question-item:hover {
            border-color: var(--primary);
            background: rgba(8, 148, 115, 0.05);
        }

        .question-item input[type="checkbox"]:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .question-item:has(input[type="checkbox"]:disabled) {
            opacity: 0.6;
            background: #f8f9fa;
            cursor: not-allowed;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .container { padding: 10px; }
            .form-container { padding: 20px; }
            .header { padding: 14px 0; }
            .header-content { gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display: none; }
            .user-avatar { width: 40px; height: 40px; font-size: 1rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div class="logo">
                    <h1>Bir Soru Bir Sevap</h1>
                    <p id="pageTitle">PDF Sınav Oluştur</p>
                </div>
                <div class="user-info">
                    <a href="dashboard.php" class="back-btn" style="margin-right: 15px;">
                        <i class="fas fa-arrow-left"></i>
                        Dashboard'a Dön
                    </a>
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                    </div>
                    <div>
                        <div><?php echo htmlspecialchars($user['name']); ?></div>
                        <div style="font-size: 0.8em; opacity: 0.8;" id="userRole">Eğitmen</div>
                    </div>
                    <button id="langToggle" class="logout-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.15);">DE</button>
                    <a href="../logout.php" class="logout-btn" id="btnLogout">Çıkış</a>
                </div>
            </div>
        </div>

        <div class="page-header">
            <h2 class="page-title" id="mainTitle">📄 PDF Sınav Oluştur</h2>
            <p class="page-subtitle" id="mainSubtitle">Sınavı PDF olarak indirin ve kağıt-kalem ile yapın</p>
        </div>

        <?php if ($errorMessage): ?>
            <div class="error-message"><?php echo htmlspecialchars($errorMessage); ?></div>
        <?php endif; ?>

        <div class="form-container">
            <form method="POST" action="exam_pdf_print.php">
                <div class="form-grid">
                <!-- Sol Taraf: Sınav Bilgileri -->
                <div>
                    <h2 class="section-title" id="sectionTitle1">📋 Sınav Bilgileri</h2>
                    
                    <div class="form-group">
                        <label for="exam_title" id="labelExamTitle">📝 Sınav Başlığı</label>
                        <input type="text" id="exam_title" name="exam_title" required value="<?php echo htmlspecialchars($_POST['exam_title'] ?? ''); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="exam_description" id="labelExamDescription">📄 Açıklama (İsteğe Bağlı)</label>
                        <textarea id="exam_description" name="exam_description" rows="3"><?php echo htmlspecialchars($_POST['exam_description'] ?? ''); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="question_count" id="labelQuestionCount">🔢 Soru Sayısı</label>
                        <select id="question_count" name="question_count" required>
                            <option value="5">5 Soru</option>
                            <option value="10" selected>10 Soru</option>
                            <option value="15">15 Soru</option>
                            <option value="20">20 Soru</option>
                            <option value="25">25 Soru</option>
                            <option value="30">30 Soru</option>
                            <option value="50">50 Soru</option>
                        </select>
                    </div>
                    
                </div>

                <!-- Sağ Taraf: Soru Türü ve Konu Seçimi -->
                <div>
                    <h2 class="section-title" id="sectionTitle2">📚 Soru Türü ve Konu Seçimi</h2>
                    
                    <div class="question-type-section" style="margin-bottom: 30px;">
                        <h3 style="color: #2c3e50; margin-bottom: 15px; font-size: 1.2em;" id="questionTypeTitle">Soru Türü Seçin:</h3>
                        <div class="question-type-options" style="display: grid; grid-template-columns: 1fr; gap: 15px;">
                            <label class="question-type-option">
                                <input type="radio" name="question_type" value="random" checked>
                                <div><div id="option1Title">🎲 Rastgele Sorular</div><div id="option1Desc">Seçilen kategorilerden rastgele sorular</div></div>
                            </label>
                            <label class="question-type-option">
                                <input type="radio" name="question_type" value="manual">
                                <div><div id="option2Title">✋ Manuel Seçim</div><div id="option2Desc">Kategorilerden manuel olarak soru seçimi</div></div>
                            </label>
                            <label class="question-type-option">
                                <input type="radio" name="question_type" value="custom">
                                <div><div id="option3Title">✏️ Özel Sorular</div><div id="option3Desc">Kendi sorularınızı ve seçeneklerinizi yazın</div></div>
                            </label>
                        </div>
                    </div>

                    <div id="categorySelection">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <h3 style="color: #2c3e50; font-size: 1.2em; margin: 0;" id="categoryTitle">Kategoriler:</h3>
                            <div id="selectedQuestionsCount" style="background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%); color: white; padding: 8px 16px; border-radius: 20px; font-weight: 600; display: none;">
                                <span id="selectedCount">0</span> soru seçildi
                            </div>
                        </div>
                        <div class="category-grid">
                            <?php if (!empty($groupedCategories)): ?>
                                <?php foreach ($groupedCategories as $bank => $bankCategories): ?>
                                    <div class="bank-section">
                                        <div class="bank-title"><span><?php echo htmlspecialchars($bank); ?></span><span class="chev">▼</span></div>
                                        <div class="bank-categories">
                                        <?php foreach ($bankCategories as $category): ?>
                                            <div class="category-item" data-category="<?php echo htmlspecialchars($bank . '|' . $category); ?>">
                                                <div class="category-header" style="display: flex; align-items: center; gap: 12px; cursor: pointer; padding: 15px;">
                                                    <input type="checkbox" 
                                                           id="category_<?php echo md5($bank . $category); ?>" 
                                                           name="categories[]" 
                                                           value="<?php echo htmlspecialchars($bank . '|' . $category); ?>"
                                                           class="category-checkbox">
                                                    <label for="category_<?php echo md5($bank . $category); ?>" class="category-label" style="flex: 1; cursor: pointer;">
                                                        <span class="custom-checkbox"></span>
                                                        <?php echo htmlspecialchars($category); ?>
                                                    </label>
                                                    <span class="expand-icon" style="font-size: 1.2em; transition: transform 0.3s ease;">▼</span>
                                                </div>
                                                <div class="questions-container" style="display: none; padding: 0 15px 15px 15px; border-top: 1px solid #e1e8ed; margin-top: 10px;">
                                                    <div class="questions-loading" style="text-align: center; padding: 20px; color: #7f8c8d;">
                                                        <div class="loading-text">Sorular yükleniyor...</div>
                                                    </div>
                                                    <div class="questions-list" style="display: none;">
                                                        <!-- Sorular buraya dinamik olarak yüklenecek -->
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div style="text-align: center; padding: 40px; color: #7f8c8d;">
                                    <p id="noQuestionsText">Henüz soru bankası yüklenmedi.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="customQuestionsSection" class="custom-questions-section" style="display: none;">
                        <h3 id="customQuestionsTitle">Özel Sorularınızı Ekleyin:</h3>
                        <div id="customQuestionsContainer"></div>
                        <button type="button" id="addCustomQuestion" class="btn">➕ Yeni Soru Ekle</button>
                    </div>
                </div>
            </div>

                <div style="text-align: center; margin-top: 2rem; display:flex; gap:12px; justify-content:center; flex-wrap:wrap;">
                    <button type="submit" class="btn" id="btnPrintHtml">🖨️ Yazdır / PDF Kaydet</button>
                    <a href="dashboard.php" class="btn btn-secondary">← Geri Dön</a>
                </div>
            </form>
        </div>

        <!-- Geçmiş PDF Sınavlar -->
        <?php 
            $printsMetaPath = __DIR__ . '/../data/exam_prints.json';
            $prints = file_exists($printsMetaPath) ? (json_decode(file_get_contents($printsMetaPath), true) ?: []) : [];
            // Bu öğretmene ait olanları filtrele
            $myPrints = array_values(array_filter($prints, function($p) use ($user){ return ($p['teacher'] ?? '') === ($user['name'] ?? ''); }));
            usort($myPrints, function($a,$b){ return strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''); });
        ?>
        <div class="form-container" style="margin-top: 18px;">
            <h2 class="section-title">📄 Geçmiş PDF Sınavlar</h2>
            <?php if (empty($myPrints)): ?>
                <div style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:12px;padding:16px;color:#64748b;">Henüz kayıt yok.</div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:1fr;gap:10px;">
                    <?php foreach ($myPrints as $p): ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff;">
                            <div>
                                <div style="font-weight:700;color:#0f172a;"><?php echo htmlspecialchars($p['title'] ?? 'Sınav'); ?></div>
                                <div style="font-size:.9rem;color:#475569;">Oluşturma: <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($p['created_at'] ?? 'now'))); ?> • Soru: <?php echo (int)($p['questions'] ?? 0); ?></div>
                            </div>
                            <div style="display:flex;gap:8px;">
                                <a href="../<?php echo htmlspecialchars($p['file']); ?>" target="_blank" class="btn">🖨️ Aç / Yazdır</a>
                                <button type="button" class="btn btn-secondary" onclick="removePrint('<?php echo htmlspecialchars($p['file']); ?>', this)">🗑️ Sil</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function removePrint(file, btn){
            if(!confirm('Bu kaydı silmek istiyor musunuz?')) return;
            fetch('exam_prints_remove.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:'file='+encodeURIComponent(file) })
            .then(()=>{ const card = btn.closest('div[style*="justify-content:space-between"]'); if(card) card.remove(); })
            .catch(()=>{});
        }
        // Soru türü değiştirme
        document.querySelectorAll('input[name="question_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const categorySelection = document.getElementById('categorySelection');
                const customQuestionsSection = document.getElementById('customQuestionsSection');
                
                if (this.value === 'custom') {
                    categorySelection.style.display = 'none';
                    customQuestionsSection.style.display = 'block';
                } else {
                    categorySelection.style.display = 'block';
                    customQuestionsSection.style.display = 'none';
                }
            });
        });

        // Kategori seçimi
        document.querySelectorAll('.category-item').forEach(item => {
            item.addEventListener('click', function() {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                
                if (checkbox.checked) {
                    this.classList.add('selected');
                } else {
                    this.classList.remove('selected');
                }
            });
        });

        // Özel soru ekleme
        let customQuestionCount = 0;
        document.getElementById('addCustomQuestion').addEventListener('click', function() {
            customQuestionCount++;
            const container = document.getElementById('customQuestionsContainer');
            
            const questionDiv = document.createElement('div');
            questionDiv.className = 'custom-question-item';
            questionDiv.style.cssText = 'border: 2px solid #e1e8ed; border-radius: 15px; padding: 20px; margin-bottom: 20px; background: white;';
            
            questionDiv.innerHTML = `
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                    <h4 style="margin: 0; color: #2c3e50;">Soru ${customQuestionCount}</h4>
                    <button type="button" onclick="removeCustomQuestion(this)" style="background: #dc3545; color: white; border: none; padding: 5px 10px; border-radius: 5px; cursor: pointer;">Sil</button>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Soru Metni:</label>
                    <textarea name="custom_questions[${customQuestionCount}][question]" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;" placeholder="Sorunuzu yazın..."></textarea>
                </div>
                <div style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Soru Tipi:</label>
                    <select name="custom_questions[${customQuestionCount}][type]" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="mcq">Çoktan Seçmeli</option>
                        <option value="true_false">Doğru/Yanlış</option>
                        <option value="short_answer">Kısa Cevap</option>
                    </select>
                </div>
                <div id="options_${customQuestionCount}" class="options-container">
                    <label style="display: block; margin-bottom: 10px; font-weight: 600; color: #2c3e50;">Seçenekler (Doğru cevabı işaretleyin):</label>
                    <div class="option-inputs">
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="0" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">A)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="Seçenek 1" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="1" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">B)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="Seçenek 2" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="2" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">C)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="Seçenek 3" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 12px; padding: 10px; border: 2px solid #e1e8ed; border-radius: 8px; background: #f8f9fa; transition: all 0.3s ease;">
                            <input type="radio" name="custom_questions[${customQuestionCount}][correct]" value="3" required style="transform: scale(1.2);">
                            <span style="font-weight: 600; color: #2c3e50; min-width: 20px;">D)</span>
                            <input type="text" name="custom_questions[${customQuestionCount}][options][]" placeholder="Seçenek 4" required style="flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 1em;">
                        </div>
                    </div>
                </div>
                <div style="margin-top: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 600;">Açıklama (İsteğe Bağlı):</label>
                    <textarea name="custom_questions[${customQuestionCount}][explanation]" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; resize: vertical;" placeholder="Doğru cevabın açıklaması..."></textarea>
                </div>
            `;
            
            container.appendChild(questionDiv);
        });

        // Özel soru silme
        function removeCustomQuestion(button) {
            button.closest('.custom-question-item').remove();
        }

        // Soru türü değiştirme
        document.querySelectorAll('input[name="question_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const categorySelection = document.getElementById('categorySelection');
                const customQuestionsSection = document.getElementById('customQuestionsSection');
                const selectedQuestionsCount = document.getElementById('selectedQuestionsCount');
                
                if (this.value === 'custom') {
                    categorySelection.style.display = 'none';
                    customQuestionsSection.style.display = 'block';
                    if (selectedQuestionsCount) selectedQuestionsCount.style.display = 'none';
                } else if (this.value === 'manual') {
                    categorySelection.style.display = 'block';
                    customQuestionsSection.style.display = 'none';
                    if (selectedQuestionsCount) selectedQuestionsCount.style.display = 'block';
                    updateSelectedQuestionsCount();
                } else {
                    categorySelection.style.display = 'block';
                    customQuestionsSection.style.display = 'none';
                    if (selectedQuestionsCount) selectedQuestionsCount.style.display = 'none';
                }
            });
        });

        // Konu seçimi için JavaScript
        document.querySelectorAll('.category-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const categoryItem = this.closest('.category-item');
                if (this.checked) {
                    categoryItem.classList.add('selected');
                } else {
                    categoryItem.classList.remove('selected');
                }
            });
        });

        // Accordion fonksiyonları
        document.addEventListener('DOMContentLoaded', function() {
            // Banka (Temel Bilgiler 1-2-3) akordeonları: varsayılan KAPALI
            document.querySelectorAll('.bank-section').forEach(section => {
                const title = section.querySelector('.bank-title');
                const wrap = section.querySelector('.bank-categories');
                if (!title || !wrap) return;
                // başlangıç: kapalı
                wrap.style.display = 'none';
                title.classList.remove('open');
                title.addEventListener('click', function(){
                    const isOpen = wrap.style.display === 'block';
                    wrap.style.display = isOpen ? 'none' : 'block';
                    this.classList.toggle('open', !isOpen);
                });
            });

            // Kategori başlığına tıklama
            document.querySelectorAll('.category-header').forEach(header => {
                header.addEventListener('click', function(e) {
                    // Checkbox'a tıklanmışsa accordion'u açma
                    if (e.target.type === 'checkbox' || e.target.tagName === 'INPUT') {
                        return;
                    }
                    
                    const categoryItem = this.closest('.category-item');
                    const questionsContainer = categoryItem.querySelector('.questions-container');
                    const expandIcon = this.querySelector('.expand-icon');
                    const questionType = document.querySelector('input[name="question_type"]:checked').value;
                    
                    // Sadece manuel seçimde accordion çalışsın
                    if (questionType !== 'manual') {
                        return;
                    }
                    
                    if (questionsContainer.style.display === 'none') {
                        // Aç
                        questionsContainer.style.display = 'block';
                        expandIcon.style.transform = 'rotate(180deg)';
                        
                        // Soruları yükle
                        loadQuestionsForCategory(categoryItem);
                    } else {
                        // Kapat
                        questionsContainer.style.display = 'none';
                        expandIcon.style.transform = 'rotate(0deg)';
                    }
                });
            });
        });

        // Kategori sorularını yükle
        function loadQuestionsForCategory(categoryItem) {
            const questionsContainer = categoryItem.querySelector('.questions-container');
            const loadingDiv = questionsContainer.querySelector('.questions-loading');
            const questionsList = questionsContainer.querySelector('.questions-list');
            const category = categoryItem.dataset.category;
            
            // Loading göster
            loadingDiv.style.display = 'block';
            questionsList.style.display = 'none';
            
            // AJAX ile soruları yükle
            fetch('get_questions_by_category.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'category=' + encodeURIComponent(category)
            })
            .then(response => response.json())
            .then(data => {
                loadingDiv.style.display = 'none';
                questionsList.style.display = 'block';
                
                if (data.success && data.questions.length > 0) {
                    questionsList.innerHTML = '';
                    data.questions.forEach((question, index) => {
                        const questionDiv = document.createElement('div');
                        questionDiv.className = 'question-item';
                        questionDiv.style.cssText = 'padding: 10px; border: 1px solid #e1e8ed; border-radius: 8px; margin-bottom: 10px; background: #f8f9fa;';
                        
                        questionDiv.innerHTML = `
                            <label style="display: flex; align-items: flex-start; gap: 10px; cursor: pointer;">
                                <input type="checkbox" name="selected_questions[]" value="${question.id}" style="margin-top: 5px;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; margin-bottom: 5px; color: #2c3e50;">
                                        Soru ${index + 1}: ${question.question}
                                    </div>
                                    <div style="font-size: 0.9em; color: #6c757d;">
                                        Tip: ${getQuestionTypeText(question.type)} | Zorluk: ${question.difficulty || 'Belirtilmemiş'}
                                    </div>
                                </div>
                            </label>
                        `;
                        
                        questionsList.appendChild(questionDiv);
                    });
                } else {
                    const currentLang = localStorage.getItem('lang_exam_pdf')||localStorage.getItem('lang')||'tr';
                    const noQuestionsText = currentLang === 'de' ? 'Keine Fragen in dieser Kategorie gefunden.' : 'Bu kategoride soru bulunamadı.';
                    questionsList.innerHTML = `<div style="text-align: center; padding: 20px; color: #7f8c8d;">${noQuestionsText}</div>`;
                }
            })
            .catch(error => {
                loadingDiv.style.display = 'none';
                questionsList.style.display = 'block';
                const currentLang = localStorage.getItem('lang_exam_pdf')||localStorage.getItem('lang')||'tr';
                const errorText = currentLang === 'de' ? 'Fehler beim Laden der Fragen.' : 'Sorular yüklenirken hata oluştu.';
                questionsList.innerHTML = `<div style="text-align: center; padding: 20px; color: #dc3545;">${errorText}</div>`;
                console.error('Error:', error);
            });
        }

        // Soru tipi metnini döndür
        function getQuestionTypeText(type) {
            const types = {
                'mcq': 'Çoktan Seçmeli',
                'true_false': 'Doğru/Yanlış',
                'short_answer': 'Kısa Cevap'
            };
            return types[type] || type;
        }

        // Seçilen soru sayısını güncelle
        function updateSelectedQuestionsCount() {
            const selectedQuestions = document.querySelectorAll('input[name="selected_questions[]"]:checked');
            const countElement = document.getElementById('selectedCount');
            const countContainer = document.getElementById('selectedQuestionsCount');
            const questionCountSelect = document.getElementById('question_count');
            const currentLang = localStorage.getItem('lang_exam_pdf')||localStorage.getItem('lang')||'tr';
            
            if (countElement && countContainer) {
                const tr = { 
                    selectedQuestionsText: 'soru seçildi',
                    requiredQuestionsText: 'soru gerekli'
                };
                const de = { 
                    selectedQuestionsText: 'Fragen ausgewählt',
                    requiredQuestionsText: 'Fragen erforderlich'
                };
                const d = currentLang === 'de' ? de : tr;
                
                const requiredCount = questionCountSelect ? parseInt(questionCountSelect.value) : 0;
                const selectedCount = selectedQuestions.length;
                
                countElement.textContent = selectedCount;
                
                // Renk kodlaması
                if (selectedCount === requiredCount) {
                    countContainer.style.background = 'linear-gradient(135deg, #28a745 0%, #20c997 100%)';
                } else if (selectedCount > requiredCount) {
                    countContainer.style.background = 'linear-gradient(135deg, #ffc107 0%, #fd7e14 100%)';
                } else {
                    countContainer.style.background = 'linear-gradient(135deg, #6c757d 0%, #495057 100%)';
                }
                
                countContainer.innerHTML = `<span id="selectedCount">${selectedCount}</span> / ${requiredCount} ${d.selectedQuestionsText}`;
                
                if (selectedCount > 0 || requiredCount > 0) {
                    countContainer.style.display = 'block';
                } else {
                    countContainer.style.display = 'none';
                }
            }
        }

        // Soru seçimi değişikliklerini dinle
        document.addEventListener('change', function(e) {
            if (e.target.name === 'selected_questions[]') {
                const questionCountSelect = document.getElementById('question_count');
                const requiredCount = questionCountSelect ? parseInt(questionCountSelect.value) : 0;
                const selectedQuestions = document.querySelectorAll('input[name="selected_questions[]"]:checked');
                
                // Eğer maksimum sayıya ulaşıldıysa, diğer checkbox'ları devre dışı bırak
                if (selectedQuestions.length >= requiredCount) {
                    document.querySelectorAll('input[name="selected_questions[]"]:not(:checked)').forEach(checkbox => {
                        checkbox.disabled = true;
                    });
                } else {
                    // Eğer sayı azaldıysa, tüm checkbox'ları aktif et
                    document.querySelectorAll('input[name="selected_questions[]"]').forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                }
                
                updateSelectedQuestionsCount();
            } else if (e.target.id === 'question_count') {
                // Soru sayısı değiştiğinde güncelle
                const questionType = document.querySelector('input[name="question_type"]:checked');
                if (questionType && questionType.value === 'manual') {
                    // Tüm checkbox'ları aktif et
                    document.querySelectorAll('input[name="selected_questions[]"]').forEach(checkbox => {
                        checkbox.disabled = false;
                    });
                    updateSelectedQuestionsCount();
                }
            }
        });

        // Form gönderimi öncesi kontrol
        document.querySelector('form').addEventListener('submit', function(e) {
            const questionType = document.querySelector('input[name="question_type"]:checked').value;
            const currentLang = localStorage.getItem('lang_exam_pdf')||localStorage.getItem('lang')||'tr';
            
            if (questionType === 'custom') {
                // Özel sorular için kontrol
                const customQuestions = document.querySelectorAll('.custom-question-item');
                if (customQuestions.length === 0) {
                    e.preventDefault();
                    alert('Lütfen en az bir özel soru ekleyin!');
                    return false;
                }
                
                // Özel soruları form'a ekle
                customQuestions.forEach((questionDiv, index) => {
                    const questionText = questionDiv.querySelector('textarea[name*="[question]"]').value;
                    const questionType = questionDiv.querySelector('select[name*="[type]"]').value;
                    const options = Array.from(questionDiv.querySelectorAll('input[name*="[options][]"]')).map(input => input.value);
                    const correctAnswer = questionDiv.querySelector('input[name*="[correct]"]:checked').value;
                    const explanation = questionDiv.querySelector('textarea[name*="[explanation]"]').value;
                    
                    // Hidden input olarak ekle
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `custom_questions[${index}][question]`;
                    hiddenInput.value = questionText;
                    this.appendChild(hiddenInput);
                    
                    const hiddenType = document.createElement('input');
                    hiddenType.type = 'hidden';
                    hiddenType.name = `custom_questions[${index}][type]`;
                    hiddenType.value = questionType;
                    this.appendChild(hiddenType);
                    
                    const hiddenCorrect = document.createElement('input');
                    hiddenCorrect.type = 'hidden';
                    hiddenCorrect.name = `custom_questions[${index}][correct_answer]`;
                    hiddenCorrect.value = correctAnswer;
                    this.appendChild(hiddenCorrect);
                    
                    const hiddenExplanation = document.createElement('input');
                    hiddenExplanation.type = 'hidden';
                    hiddenExplanation.name = `custom_questions[${index}][explanation]`;
                    hiddenExplanation.value = explanation;
                    this.appendChild(hiddenExplanation);
                    
                    options.forEach((option, optionIndex) => {
                        const hiddenOption = document.createElement('input');
                        hiddenOption.type = 'hidden';
                        hiddenOption.name = `custom_questions[${index}][options][${optionIndex}]`;
                        hiddenOption.value = option;
                        this.appendChild(hiddenOption);
                    });
                });
            } else if (questionType === 'manual') {
                // Manuel seçim için soru kontrolü
                const selectedQuestions = document.querySelectorAll('input[name="selected_questions[]"]:checked');
                const questionCountSelect = document.getElementById('question_count');
                const requiredCount = questionCountSelect ? parseInt(questionCountSelect.value) : 0;
                
                if (selectedQuestions.length !== requiredCount) {
                    e.preventDefault();
                    const alertText = currentLang === 'de' 
                        ? `Bitte wählen Sie genau ${requiredCount} Fragen aus! (Aktuell: ${selectedQuestions.length})`
                        : `Lütfen tam olarak ${requiredCount} soru seçin! (Şu an: ${selectedQuestions.length})`;
                    alert(alertText);
                    return false;
                }
            } else {
                // Rastgele için kategori kontrolü
                const selectedCategories = document.querySelectorAll('input[name="categories[]"]:checked');
                if (selectedCategories.length === 0) {
                    e.preventDefault();
                    const alertText = currentLang === 'de' ? 'Bitte wählen Sie mindestens ein Thema aus!' : 'Lütfen en az bir konu seçin!';
                    alert(alertText);
                    return false;
                }
            }
        });
    </script>
</body>
</html>
