<?php
/**
 * Öğretmen - Soru Bankası Yönetimi
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../QuestionLoader.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

// Soruları yükle
$questionLoader = new QuestionLoader();
$questionLoader->loadQuestions();

$questions = $_SESSION['all_questions'] ?? [];
$categories = $_SESSION['categories'] ?? [];
$allBanks = $_SESSION['banks'] ?? [];

// Banka erişim filtreleme
$userRole = $user['role'] ?? 'teacher';
$userInstitution = $user['institution'] ?? $user['branch'] ?? '';
$banks = array_filter($allBanks, function($bank) use ($questionLoader, $userInstitution, $userRole) {
    return $questionLoader->isBankAccessible($bank, $userInstitution, $userRole);
});

// Filtreleme parametreleri
$selectedBank = $_GET['bank'] ?? '';
$selectedCategory = $_GET['category'] ?? '';
$selectedDifficulty = $_GET['difficulty'] ?? '';
$searchTerm = $_GET['search'] ?? '';

// Filtrelenmiş sorular
$filteredQuestions = $questions;

if ($selectedBank) {
    $filteredQuestions = array_filter($filteredQuestions, function($q) use ($selectedBank) {
        return $q['bank'] === $selectedBank;
    });
}

if ($selectedCategory) {
    $filteredQuestions = array_filter($filteredQuestions, function($q) use ($selectedCategory) {
        return $q['category'] === $selectedCategory;
    });
}

if ($selectedDifficulty) {
    $filteredQuestions = array_filter($filteredQuestions, function($q) use ($selectedDifficulty) {
        return $q['difficulty'] == $selectedDifficulty;
    });
}

if ($searchTerm) {
    $filteredQuestions = array_filter($filteredQuestions, function($q) use ($searchTerm) {
        return stripos($q['text'], $searchTerm) !== false;
    });
}

// İstatistikler
$totalQuestions = count($questions);
$filteredCount = count($filteredQuestions);
$bankStats = [];
foreach ($banks as $bank) {
    $bankQuestions = array_filter($questions, function($q) use ($bank) {
        return $q['bank'] === $bank;
    });
    $bankStats[$bank] = count($bankQuestions);
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Bankası - Bir Soru Bir Sevap</title>
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
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
            gap: 12px;
        }

        /* Uzun kullanıcı adı ellipsis */
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

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
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            text-align: center;
            margin-bottom: 40px;
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

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-number {
            font-size: 2.5em;
            font-weight: 800;
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .stat-label {
            color: #2c3e50;
            font-weight: 600;
            font-size: 1.1em;
        }

        .main-content {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(10px);
            border-radius: 25px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.15);
        }

        .filters-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 1.1em;
        }

        .filter-group select,
        .filter-group input {
            padding: 12px 15px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1em;
            transition: all 0.3s ease;
            background: white;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #068567;
            box-shadow: 0 0 0 3px rgba(6, 133, 103, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 25px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-size: 1em;
        }

        .btn-primary {
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(6, 132, 102, 0.3);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .btn-outline {
            background: transparent;
            color: #068466;
            border: 2px solid #068466;
        }

        .btn-outline:hover {
            background: #068466;
            color: white;
        }
        
        .lang-toggle {
            background: rgba(6, 133, 103, 0.1);
            border: 2px solid #068466;
            color: #068466;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        
        .lang-toggle:hover {
            background: #068466;
            color: white;
        }

        .questions-section {
            margin-top: 30px;
        }

        .section-title {
            font-size: 1.8em;
            color: #2c3e50;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
        }

        .section-title::after {
            content: '';
            flex: 1;
            height: 3px;
            background: linear-gradient(135deg, #068466 0%, #0a9d7a 100%);
            border-radius: 2px;
        }

        .questions-grid {
            display: grid;
            gap: 20px;
        }

        .question-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            border: 2px solid transparent;
            transition: all 0.3s ease;
            position: relative;
        }

        .question-card:hover {
            border-color: #068567;
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(6, 133, 103, 0.15);
        }

        .question-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .question-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .meta-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85em;
            font-weight: 600;
        }

        .meta-bank {
            background: linear-gradient(135deg, #068567 0%, #055a4a 100%);
            color: white;
        }

        .meta-category {
            background: #e3f2fd;
            color: #1976d2;
        }

        .meta-difficulty {
            background: #fff3e0;
            color: #f57c00;
        }

        .question-text {
            font-size: 1.1em;
            line-height: 1.6;
            color: #2c3e50;
            margin-bottom: 15px;
        }

        .question-options {
            margin-bottom: 15px;
        }

        .option-item {
            padding: 8px 12px;
            margin: 5px 0;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #e9ecef;
            font-size: 0.95em;
        }

        .option-item.correct {
            background: #d4edda;
            border-left-color: #28a745;
            color: #155724;
        }

        .question-explanation {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #068466;
            font-size: 0.95em;
            color: #495057;
            margin-top: 15px;
        }

        .question-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            justify-content: flex-end;
        }

        .btn-sm {
            padding: 8px 15px;
            font-size: 0.9em;
        }

        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 30px;
        }

        .pagination a,
        .pagination span {
            padding: 10px 15px;
            border-radius: 8px;
            text-decoration: none;
            color: #2c3e50;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #068466;
            color: white;
            border-color: #068466;
        }

        .pagination .current {
            background: #068466;
            color: white;
            border-color: #068466;
        }

        .no-results {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .no-results h3 {
            font-size: 1.5em;
            margin-bottom: 15px;
            color: #495057;
        }

        .bank-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }

        .bank-stat {
            background: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .bank-stat h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .bank-stat .count {
            font-size: 2em;
            font-weight: bold;
            color: #068466;
        }

        @media (max-width: 768px) {
            .header { padding: 16px 0; }
            .header-content { padding: 0 12px; flex-wrap: wrap; gap: 10px; }
            .logo img { height: 40px; }
            .logo p { display:none; }
            .logo h1 { font-size: 1.25rem; }
            .user-avatar { width: 34px; height: 34px; }
            .lang-toggle, .back-btn { padding: 6px 10px; border-radius: 10px; font-size: .9rem; }
            .user-info > div { max-width: 60vw; }
            .filters-grid {
                grid-template-columns: 1fr;
            }

            .filter-actions {
                flex-direction: column;
                align-items: stretch;
            }

            .question-header {
                flex-direction: column;
                gap: 10px;
            }

            .question-actions {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 420px) {
            .header { padding: 12px 0; }
            .header-content { padding: 0 10px; }
            .logo img { height: 34px; }
            .logo h1 { font-size: 1.1rem; }
            .user-avatar { width: 30px; height: 30px; }
            .lang-toggle, .back-btn { padding: 5px 8px; font-size: .85rem; }
            .user-info { gap: 8px; }
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
                    <p>Soru Bankası Yönetimi</p>
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
                <button id="langToggle" class="lang-toggle">DE</button>
                <a href="dashboard.php" class="back-btn" id="btnBack">← Geri Dön</a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title" id="pageTitle">📚 Soru Bankası</h1>
            <p class="page-subtitle" id="pageSubtitle">Tüm soruları görüntüleyin, filtreleyin ve yönetin</p>
        </div>

        <!-- İstatistikler -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $totalQuestions; ?></div>
                <div class="stat-label" id="statTotal">Toplam Soru</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $filteredCount; ?></div>
                <div class="stat-label" id="statFiltered">Filtrelenmiş</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($banks); ?></div>
                <div class="stat-label" id="statBanks">Soru Bankası</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo array_sum(array_map('count', $categories)); ?></div>
                <div class="stat-label" id="statCategories">Kategori</div>
            </div>
        </div>

        <div class="main-content">
            <!-- Filtreler -->
            <div class="filters-section">
                <h3 style="margin-bottom: 20px; color: #2c3e50;">🔍 Filtreler</h3>
                <form method="GET" class="filters-form">
                    <div class="filters-grid">
                        <div class="filter-group">
                            <label for="bank">Soru Bankası</label>
                            <select id="bank" name="bank">
                                <option value="">Tüm Bankalar</option>
                                <?php foreach ($banks as $bank): ?>
                                    <option value="<?php echo htmlspecialchars($bank); ?>" 
                                            <?php echo $selectedBank === $bank ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($bank); ?> (<?php echo $bankStats[$bank]; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="category">Kategori</label>
                            <select id="category" name="category">
                                <option value="">Tüm Kategoriler</option>
                                <?php if ($selectedBank && isset($categories[$selectedBank])): ?>
                                    <?php foreach ($categories[$selectedBank] as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" 
                                                <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <?php foreach ($categories as $bank => $bankCategories): ?>
                                        <?php foreach ($bankCategories as $category): ?>
                                            <option value="<?php echo htmlspecialchars($category); ?>" 
                                                    <?php echo $selectedCategory === $category ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($category); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="difficulty">Zorluk</label>
                            <select id="difficulty" name="difficulty">
                                <option value="">Tüm Zorluklar</option>
                                <option value="1" <?php echo $selectedDifficulty === '1' ? 'selected' : ''; ?>>1 - Çok Kolay</option>
                                <option value="2" <?php echo $selectedDifficulty === '2' ? 'selected' : ''; ?>>2 - Kolay</option>
                                <option value="3" <?php echo $selectedDifficulty === '3' ? 'selected' : ''; ?>>3 - Orta</option>
                                <option value="4" <?php echo $selectedDifficulty === '4' ? 'selected' : ''; ?>>4 - Zor</option>
                                <option value="5" <?php echo $selectedDifficulty === '5' ? 'selected' : ''; ?>>5 - Çok Zor</option>
                            </select>
                        </div>

                        <div class="filter-group">
                            <label for="search">Arama</label>
                            <input type="text" id="search" name="search" 
                                   placeholder="Soru metninde ara..." 
                                   value="<?php echo htmlspecialchars($searchTerm); ?>">
                        </div>
                    </div>

                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">🔍 Filtrele</button>
                        <a href="question_bank.php" class="btn btn-outline">🔄 Temizle</a>
                        <a href="questions.php" class="btn btn-secondary">➕ Yeni Soru Ekle</a>
                    </div>
                </form>
            </div>

            <!-- Soru Bankası İstatistikleri -->
            <?php if (!empty($bankStats)): ?>
            <div class="bank-stats">
                <h3 style="margin-bottom: 15px; color: #2c3e50; grid-column: 1 / -1;">📊 Banka İstatistikleri</h3>
                <?php foreach ($bankStats as $bank => $count): ?>
                <div class="bank-stat">
                    <h4><?php echo htmlspecialchars($bank); ?></h4>
                    <div class="count"><?php echo $count; ?></div>
                    <div style="color: #6c757d; font-size: 0.9em;">soru</div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Debug Bilgileri (sadece geliştirme aşamasında) -->
            <?php if (isset($_GET['debug']) && $_GET['debug'] === '1'): ?>
            <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 20px; font-family: monospace; font-size: 0.9em;">
                <h4>🔍 Debug Bilgileri:</h4>
                <p><strong>Toplam Soru Sayısı:</strong> <?php echo count($questions); ?></p>
                <p><strong>Filtrelenmiş Soru Sayısı:</strong> <?php echo count($filteredQuestions); ?></p>
                <?php if (!empty($filteredQuestions)): ?>
                <p><strong>İlk Soru Yapısı:</strong></p>
                <pre><?php echo htmlspecialchars(json_encode($filteredQuestions[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Sorular Listesi -->
            <div class="questions-section">
                <h2 class="section-title">📝 Sorular (<?php echo $filteredCount; ?>)</h2>

                <?php if (empty($filteredQuestions)): ?>
                    <div class="no-results">
                        <h3>🔍 Sonuç Bulunamadı</h3>
                        <p>Seçilen kriterlere uygun soru bulunamadı. Filtreleri değiştirmeyi deneyin.</p>
                    </div>
                <?php else: ?>
                    <div class="questions-grid">
                        <?php foreach ($filteredQuestions as $index => $question): ?>
                        <div class="question-card">
                            <div class="question-header">
                                <div class="question-meta">
                                    <span class="meta-badge meta-bank"><?php echo htmlspecialchars($question['bank']); ?></span>
                                    <span class="meta-badge meta-category"><?php echo htmlspecialchars($question['category']); ?></span>
                                    <span class="meta-badge meta-difficulty">Zorluk: <?php echo $question['difficulty']; ?></span>
                                </div>
                                <div style="color: #6c757d; font-size: 0.9em;">
                                    #<?php echo $index + 1; ?>
                                </div>
                            </div>

                            <div class="question-text">
                                <?php echo htmlspecialchars($question['text'] ?? 'Soru metni bulunamadı'); ?>
                            </div>

                            <?php if (!empty($question['options']) && is_array($question['options'])): ?>
                            <div class="question-options">
                                <?php foreach ($question['options'] as $option): ?>
                                    <?php 
                                    $optionText = is_array($option) ? $option['text'] : $option;
                                    $isCorrect = false;
                                    if (is_array($question['answer'])) {
                                        $isCorrect = in_array($option['key'] ?? $option, $question['answer']);
                                    } else {
                                        $isCorrect = ($option['key'] ?? $option) === $question['answer'];
                                    }
                                    ?>
                                <div class="option-item <?php echo $isCorrect ? 'correct' : ''; ?>">
                                    <?php echo htmlspecialchars($optionText); ?>
                                    <?php if ($isCorrect): ?>
                                        <span style="float: right; color: #28a745; font-weight: bold;">✓</span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <?php if (!empty($question['explanation'])): ?>
                            <div class="question-explanation">
                                <strong>💡 Açıklama:</strong> <?php echo htmlspecialchars($question['explanation'] ?? ''); ?>
                            </div>
                            <?php endif; ?>

                            <div class="question-actions">
                                <button class="btn btn-outline btn-sm" onclick="editQuestion(<?php echo $question['id'] ?? $index; ?>)">
                                    ✏️ Düzenle
                                </button>
                                <button class="btn btn-secondary btn-sm" onclick="duplicateQuestion(<?php echo $question['id'] ?? $index; ?>)">
                                    📋 Kopyala
                                </button>
                                <button class="btn btn-outline btn-sm" onclick="deleteQuestion(<?php echo $question['id'] ?? $index; ?>)">
                                    🗑️ Sil
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Banka değiştiğinde kategorileri güncelle
        document.getElementById('bank').addEventListener('change', function() {
            const categorySelect = document.getElementById('category');
            const selectedBank = this.value;
            
            // Kategorileri temizle
            categorySelect.innerHTML = '<option value="">Tüm Kategoriler</option>';
            
            if (selectedBank) {
                const categories = <?php echo json_encode($categories); ?>;
                if (categories[selectedBank]) {
                    categories[selectedBank].forEach(category => {
                        const option = document.createElement('option');
                        option.value = category;
                        option.textContent = category;
                        categorySelect.appendChild(option);
                    });
                }
            } else {
                // Tüm kategorileri göster
                const allCategories = <?php echo json_encode(array_unique(array_merge(...array_values($categories)))); ?>;
                allCategories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    categorySelect.appendChild(option);
                });
            }
        });

        // Soru işlemleri
        function editQuestion(questionId) {
            // Soru düzenleme sayfasına yönlendir
            window.location.href = `questions.php?edit=${questionId}`;
        }

        function duplicateQuestion(questionId) {
            if (confirm('Bu soruyu kopyalamak istediğinizden emin misiniz?')) {
                // Soru kopyalama işlemi
                fetch('questions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=duplicate&question_id=${questionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Soru başarıyla kopyalandı!');
                        location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu');
                });
            }
        }

        function deleteQuestion(questionId) {
            if (confirm('Bu soruyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!')) {
                // Soru silme işlemi
                fetch('questions.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete&question_id=${questionId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Soru başarıyla silindi!');
                        location.reload();
                    } else {
                        alert('Hata: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Bir hata oluştu');
                });
            }
        }

        // TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'📚 Soru Bankası', pageSubtitle:'Tüm soruları görüntüleyin, filtreleyin ve yönetin',
                userRole:'Eğitmen', btnBack:'← Geri Dön',
                statTotal:'Toplam Soru', statFiltered:'Filtrelenmiş', statBanks:'Soru Bankası', statCategories:'Kategori',
                filtersTitle:'🔍 Filtreler', labelBank:'Soru Bankası', labelCategory:'Kategori', labelDifficulty:'Zorluk', labelSearch:'Arama',
                allBanks:'Tüm Bankalar', allCategories:'Tüm Kategoriler', allDifficulties:'Tüm Zorluklar',
                difficulty1:'1 - Çok Kolay', difficulty2:'2 - Kolay', difficulty3:'3 - Orta', difficulty4:'4 - Zor', difficulty5:'5 - Çok Zor',
                searchPlaceholder:'Soru metninde ara...', btnFilter:'🔍 Filtrele', btnClear:'🔄 Temizle', btnAddQuestion:'➕ Yeni Soru Ekle',
                bankStatsTitle:'📊 Banka İstatistikleri', questionsTitle:'📝 Sorular', questionCount:'soru',
                confirmDelete:'Bu soruyu silmek istediğinizden emin misiniz? Bu işlem geri alınamaz!',
                successDelete:'Soru başarıyla silindi!', errorDelete:'Hata: ', errorGeneral:'Bir hata oluştu'
            };
            const de = {
                pageTitle:'📚 Fragensammlung', pageSubtitle:'Zeigen, filtern und verwalten Sie alle Fragen',
                userRole:'Lehrpersonal', btnBack:'← Zurück',
                statTotal:'Gesamt Fragen', statFiltered:'Gefiltert', statBanks:'Fragensammlungen', statCategories:'Kategorien',
                filtersTitle:'🔍 Filter', labelBank:'Fragensammlung', labelCategory:'Kategorie', labelDifficulty:'Schwierigkeit', labelSearch:'Suche',
                allBanks:'Alle Sammlungen', allCategories:'Alle Kategorien', allDifficulties:'Alle Schwierigkeiten',
                difficulty1:'1 - Sehr einfach', difficulty2:'2 - Einfach', difficulty3:'3 - Mittel', difficulty4:'4 - Schwer', difficulty5:'5 - Sehr schwer',
                searchPlaceholder:'In Fragetext suchen...', btnFilter:'🔍 Filtern', btnClear:'🔄 Löschen', btnAddQuestion:'➕ Neue Frage hinzufügen',
                bankStatsTitle:'📊 Sammlungsstatistiken', questionsTitle:'📝 Fragen', questionCount:'Fragen',
                confirmDelete:'Sind Sie sicher, dass Sie diese Frage löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden!',
                successDelete:'Frage erfolgreich gelöscht!', errorDelete:'Fehler: ', errorGeneral:'Ein Fehler ist aufgetreten'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setPlaceholder(sel, text){ const el=document.querySelector(sel); if(el) el.placeholder=text; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#pageSubtitle', d.pageSubtitle);
                setText('#userRole', d.userRole);
                setText('#btnBack', d.btnBack);
                setText('#statTotal', d.statTotal);
                setText('#statFiltered', d.statFiltered);
                setText('#statBanks', d.statBanks);
                setText('#statCategories', d.statCategories);
                setText('#filtersTitle', d.filtersTitle);
                setText('#labelBank', d.labelBank);
                setText('#labelCategory', d.labelCategory);
                setText('#labelDifficulty', d.labelDifficulty);
                setText('#labelSearch', d.labelSearch);
                setText('#btnFilter', d.btnFilter);
                setText('#btnClear', d.btnClear);
                setText('#btnAddQuestion', d.btnAddQuestion);
                setText('#bankStatsTitle', d.bankStatsTitle);
                setText('#questionsTitle', d.questionsTitle);
                setPlaceholder('#search', d.searchPlaceholder);
                
                // Select option'larını güncelle
                const bankSelect = document.getElementById('bank');
                if (bankSelect) {
                    const firstOption = bankSelect.querySelector('option[value=""]');
                    if (firstOption) firstOption.textContent = d.allBanks;
                }
                
                const categorySelect = document.getElementById('category');
                if (categorySelect) {
                    const firstOption = categorySelect.querySelector('option[value=""]');
                    if (firstOption) firstOption.textContent = d.allCategories;
                }
                
                const difficultySelect = document.getElementById('difficulty');
                if (difficultySelect) {
                    const firstOption = difficultySelect.querySelector('option[value=""]');
                    if (firstOption) firstOption.textContent = d.allDifficulties;
                    
                    const options = difficultySelect.querySelectorAll('option');
                    options.forEach(option => {
                        switch(option.value) {
                            case '1': option.textContent = d.difficulty1; break;
                            case '2': option.textContent = d.difficulty2; break;
                            case '3': option.textContent = d.difficulty3; break;
                            case '4': option.textContent = d.difficulty4; break;
                            case '5': option.textContent = d.difficulty5; break;
                        }
                    });
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_question_bank', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_question_bank')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_question_bank')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();

        // Sayfa yüklendiğinde kategorileri ayarla
        document.addEventListener('DOMContentLoaded', function() {
            const bankSelect = document.getElementById('bank');
            const categorySelect = document.getElementById('category');
            const selectedBank = bankSelect.value;
            
            if (selectedBank) {
                const categories = <?php echo json_encode($categories); ?>;
                if (categories[selectedBank]) {
                    const currentLang = localStorage.getItem('lang_question_bank')||localStorage.getItem('lang')||'tr';
                    const allCategoriesText = currentLang === 'de' ? 'Alle Kategorien' : 'Tüm Kategoriler';
                    categorySelect.innerHTML = `<option value="">${allCategoriesText}</option>`;
                    categories[selectedBank].forEach(category => {
                        const option = document.createElement('option');
                        option.value = category;
                        option.textContent = category;
                        if (category === '<?php echo $selectedCategory; ?>') {
                            option.selected = true;
                        }
                        categorySelect.appendChild(option);
                    });
                }
            }
        });
    </script>
</body>
</html>
