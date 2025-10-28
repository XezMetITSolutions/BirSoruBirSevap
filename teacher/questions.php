<?php
/**
 * Öğretmen - Soru Yönetimi
 */

require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();

// Öğretmen kontrolü
if (!$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$error = '';
$success = '';

// Soru ekleme
if ($_POST['action'] ?? '' === 'add_question') {
    $questionText = trim($_POST['question_text'] ?? '');
    $questionType = $_POST['question_type'] ?? 'mcq';
    $options = $_POST['options'] ?? [];
    $correctAnswer = $_POST['correct_answer'] ?? '';
    $explanation = trim($_POST['explanation'] ?? '');
    $difficulty = $_POST['difficulty'] ?? 'medium';
    $category = trim($_POST['category'] ?? '');
    
    if (empty($questionText) || empty($correctAnswer)) {
        $error = 'Soru metni ve doğru cevap gereklidir.';
    } else {
        // Soru ekleme işlemi (gerçek uygulamada veritabanına kaydedilir)
        $success = 'Soru başarıyla eklendi.';
    }
}

// Soru silme
if ($_POST['action'] ?? '' === 'delete_question') {
    $questionId = $_POST['question_id'] ?? '';
    if (!empty($questionId)) {
        $success = 'Soru başarıyla silindi.';
    }
}

// Örnek sorular
$questions = [
    [
        'id' => 1,
        'text' => 'Türkiye\'nin başkenti neresidir?',
        'type' => 'mcq',
        'options' => ['İstanbul', 'Ankara', 'İzmir', 'Bursa'],
        'correct_answer' => 'Ankara',
        'explanation' => 'Türkiye\'nin başkenti 1923 yılından beri Ankara\'dır.',
        'difficulty' => 'easy',
        'category' => 'Coğrafya',
        'created_at' => '2024-01-10'
    ],
    [
        'id' => 2,
        'text' => '2 + 2 = 4 doğru mudur?',
        'type' => 'true_false',
        'options' => ['Doğru', 'Yanlış'],
        'correct_answer' => 'Doğru',
        'explanation' => '2 + 2 = 4 matematiksel olarak doğrudur.',
        'difficulty' => 'easy',
        'category' => 'Matematik',
        'created_at' => '2024-01-09'
    ],
    [
        'id' => 3,
        'text' => 'Photosentez nedir?',
        'type' => 'short_answer',
        'options' => [],
        'correct_answer' => 'Bitkilerin güneş ışığını kullanarak besin üretmesi',
        'explanation' => 'Photosentez, bitkilerin güneş ışığını kullanarak karbondioksit ve suyu glikoz ve oksijene dönüştürmesidir.',
        'difficulty' => 'medium',
        'category' => 'Fen Bilgisi',
        'created_at' => '2024-01-08'
    ]
];
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Soru Yönetimi - Öğretmen</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 2.5rem;
        }

        .header p {
            color: #7f8c8d;
            font-size: 1.1rem;
        }

        .nav-breadcrumb {
            background: rgba(255, 255, 255, 0.9);
            padding: 15px 30px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .nav-breadcrumb a {
            color: #667eea;
            text-decoration: none;
            margin-right: 10px;
        }

        .nav-breadcrumb a:hover {
            text-decoration: underline;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
        }

        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .card h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 1.8rem;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #2c3e50;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e1e8ed;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group textarea {
            resize: vertical;
            min-height: 100px;
        }

        .options-container {
            display: none;
        }

        .options-container.show {
            display: block;
        }

        .option-input {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .option-input input {
            flex: 1;
        }

        .option-input button {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
        }

        .add-option-btn {
            background: #27ae60;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 5px;
            cursor: pointer;
            margin-top: 10px;
        }

        .btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }
        
        .lang-toggle {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-left: 10px;
        }
        
        .lang-toggle:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        }

        .btn-danger:hover {
            box-shadow: 0 8px 25px rgba(231, 76, 60, 0.3);
        }

        .questions-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .question-item {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }

        .question-item h4 {
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .question-item p {
            color: #7f8c8d;
            margin-bottom: 15px;
        }

        .question-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .question-type {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .type-mcq {
            background: #e3f2fd;
            color: #1976d2;
        }

        .type-true_false {
            background: #e8f5e8;
            color: #388e3c;
        }

        .type-short_answer {
            background: #fff3e0;
            color: #f57c00;
        }

        .difficulty-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .difficulty-easy {
            background: #d4edda;
            color: #155724;
        }

        .difficulty-medium {
            background: #fff3cd;
            color: #856404;
        }

        .difficulty-hard {
            background: #f8d7da;
            color: #721c24;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 500;
        }

        .alert-error {
            background: #fee;
            color: #c0392b;
            border: 1px solid #f5c6cb;
        }

        .alert-success {
            background: #efe;
            color: #27ae60;
            border: 1px solid #c3e6cb;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.9);
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
        }

        .stat-card h3 {
            font-size: 2rem;
            color: #667eea;
            margin-bottom: 5px;
        }

        .stat-card p {
            color: #7f8c8d;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .content-grid { grid-template-columns: 1fr; }
            .header { padding: 20px; }
            .header h1 { font-size: 1.6rem; }
            .lang-toggle { padding: 6px 10px; border-radius: 12px; }
        }
        @media (max-width: 420px) {
            .header { padding: 16px; }
            .header h1 { font-size: 1.35rem; }
            .lang-toggle { padding: 5px 8px; font-size: .85rem; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 id="pageTitle">❓ Soru Yönetimi</h1>
            <p id="pageSubtitle">Sorularınızı oluşturun ve yönetin</p>
            <button id="langToggle" class="lang-toggle">DE</button>
        </div>

        <div class="nav-breadcrumb" id="breadcrumb">
            <a href="dashboard.php" id="breadcrumbDashboard">Dashboard</a> > <span id="breadcrumbCurrent">Soru Yönetimi</span>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><?php echo count($questions); ?></h3>
                <p id="statTotal">Toplam Soru</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($questions, fn($q) => $q['type'] === 'mcq')); ?></h3>
                <p id="statMcq">Çoktan Seçmeli</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($questions, fn($q) => $q['type'] === 'true_false')); ?></h3>
                <p id="statTrueFalse">Doğru/Yanlış</p>
            </div>
            <div class="stat-card">
                <h3><?php echo count(array_filter($questions, fn($q) => $q['type'] === 'short_answer')); ?></h3>
                <p id="statShortAnswer">Kısa Cevap</p>
            </div>
        </div>

        <div class="content-grid">
            <div class="card">
                <h2 id="addQuestionTitle">➕ Yeni Soru Ekle</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_question">
                    
                    <div class="form-group">
                        <label for="question_text" id="labelQuestionText">Soru Metni</label>
                        <textarea id="question_text" name="question_text" required></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="question_type" id="labelQuestionType">Soru Tipi</label>
                        <select id="question_type" name="question_type" onchange="toggleOptions()">
                            <option value="mcq">Çoktan Seçmeli</option>
                            <option value="true_false">Doğru/Yanlış</option>
                            <option value="short_answer">Kısa Cevap</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="category" id="labelCategory">Kategori</label>
                        <input type="text" id="category" name="category" placeholder="Örn: Matematik, Fen Bilgisi">
                    </div>
                    
                    <div class="form-group">
                        <label for="difficulty" id="labelDifficulty">Zorluk</label>
                        <select id="difficulty" name="difficulty">
                            <option value="easy">Kolay</option>
                            <option value="medium" selected>Orta</option>
                            <option value="hard">Zor</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="correct_answer" id="labelCorrectAnswer">Doğru Cevap</label>
                        <input type="text" id="correct_answer" name="correct_answer" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="explanation" id="labelExplanation">Açıklama</label>
                        <textarea id="explanation" name="explanation"></textarea>
                    </div>
                    
                    <button type="submit" class="btn" id="btnAddQuestion">Soru Ekle</button>
                </form>
            </div>

            <div class="card">
                <h2 id="questionListTitle">📋 Soru Listesi</h2>
                <div class="questions-list">
                    <?php foreach ($questions as $question): ?>
                        <div class="question-item">
                            <div class="question-meta">
                                <div>
                                    <span class="question-type type-<?php echo $question['type']; ?>">
                                        <?php
                                        switch($question['type']) {
                                            case 'mcq': echo 'Çoktan Seçmeli'; break;
                                            case 'true_false': echo 'Doğru/Yanlış'; break;
                                            case 'short_answer': echo 'Kısa Cevap'; break;
                                        }
                                        ?>
                                    </span>
                                    <span class="difficulty-badge difficulty-<?php echo $question['difficulty']; ?>">
                                        <?php echo ucfirst($question['difficulty']); ?>
                                    </span>
                                </div>
                                <small><?php echo $question['created_at']; ?></small>
                            </div>
                            
                            <h4><?php echo htmlspecialchars($question['text']); ?></h4>
                            
                            <?php if (!empty($question['options'])): ?>
                                <p><strong>Seçenekler:</strong> <?php echo implode(', ', $question['options']); ?></p>
                            <?php endif; ?>
                            
                            <p><strong>Doğru Cevap:</strong> <?php echo htmlspecialchars($question['correct_answer']); ?></p>
                            
                            <?php if (!empty($question['explanation'])): ?>
                                <p><strong>Açıklama:</strong> <?php echo htmlspecialchars($question['explanation']); ?></p>
                            <?php endif; ?>
                            
                            <p><strong>Kategori:</strong> <?php echo htmlspecialchars($question['category']); ?></p>
                            
                            <div style="margin-top: 15px;">
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="delete_question">
                                    <input type="hidden" name="question_id" value="<?php echo $question['id']; ?>">
                                    <button type="submit" class="btn btn-danger" 
                                            onclick="return confirm('Bu soruyu silmek istediğinizden emin misiniz?')" id="btnDelete">
                                        Sil
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'❓ Soru Yönetimi', pageSubtitle:'Sorularınızı oluşturun ve yönetin',
                breadcrumbDashboard:'Dashboard', breadcrumbCurrent:'Soru Yönetimi',
                statTotal:'Toplam Soru', statMcq:'Çoktan Seçmeli', statTrueFalse:'Doğru/Yanlış', statShortAnswer:'Kısa Cevap',
                addQuestionTitle:'➕ Yeni Soru Ekle', questionListTitle:'📋 Soru Listesi',
                labelQuestionText:'Soru Metni', labelQuestionType:'Soru Tipi', labelCategory:'Kategori', labelDifficulty:'Zorluk',
                labelCorrectAnswer:'Doğru Cevap', labelExplanation:'Açıklama', btnAddQuestion:'Soru Ekle', btnDelete:'Sil',
                questionTypes: {
                    mcq: 'Çoktan Seçmeli',
                    true_false: 'Doğru/Yanlış',
                    short_answer: 'Kısa Cevap'
                },
                difficulties: {
                    easy: 'Kolay',
                    medium: 'Orta',
                    hard: 'Zor'
                },
                confirmDelete:'Bu soruyu silmek istediğinizden emin misiniz?'
            };
            const de = {
                pageTitle:'❓ Fragenverwaltung', pageSubtitle:'Erstellen und verwalten Sie Ihre Fragen',
                breadcrumbDashboard:'Dashboard', breadcrumbCurrent:'Fragenverwaltung',
                statTotal:'Gesamt Fragen', statMcq:'Multiple Choice', statTrueFalse:'Richtig/Falsch', statShortAnswer:'Kurze Antwort',
                addQuestionTitle:'➕ Neue Frage hinzufügen', questionListTitle:'📋 Fragenliste',
                labelQuestionText:'Fragetext', labelQuestionType:'Fragetyp', labelCategory:'Kategorie', labelDifficulty:'Schwierigkeit',
                labelCorrectAnswer:'Richtige Antwort', labelExplanation:'Erklärung', btnAddQuestion:'Frage hinzufügen', btnDelete:'Löschen',
                questionTypes: {
                    mcq: 'Multiple Choice',
                    true_false: 'Richtig/Falsch',
                    short_answer: 'Kurze Antwort'
                },
                difficulties: {
                    easy: 'Einfach',
                    medium: 'Mittel',
                    hard: 'Schwer'
                },
                confirmDelete:'Sind Sie sicher, dass Sie diese Frage löschen möchten?'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setPlaceholder(sel, text){ const el=document.querySelector(sel); if(el) el.placeholder=text; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#pageSubtitle', d.pageSubtitle);
                setText('#breadcrumbDashboard', d.breadcrumbDashboard);
                setText('#breadcrumbCurrent', d.breadcrumbCurrent);
                setText('#statTotal', d.statTotal);
                setText('#statMcq', d.statMcq);
                setText('#statTrueFalse', d.statTrueFalse);
                setText('#statShortAnswer', d.statShortAnswer);
                setText('#addQuestionTitle', d.addQuestionTitle);
                setText('#questionListTitle', d.questionListTitle);
                setText('#labelQuestionText', d.labelQuestionText);
                setText('#labelQuestionType', d.labelQuestionType);
                setText('#labelCategory', d.labelCategory);
                setText('#labelDifficulty', d.labelDifficulty);
                setText('#labelCorrectAnswer', d.labelCorrectAnswer);
                setText('#labelExplanation', d.labelExplanation);
                setText('#btnAddQuestion', d.btnAddQuestion);
                
                // Soru tipi seçeneklerini güncelle
                const questionTypeSelect = document.getElementById('question_type');
                if (questionTypeSelect) {
                    Array.from(questionTypeSelect.options).forEach(option => {
                        option.textContent = d.questionTypes[option.value] || option.textContent;
                    });
                }
                
                // Zorluk seçeneklerini güncelle
                const difficultySelect = document.getElementById('difficulty');
                if (difficultySelect) {
                    Array.from(difficultySelect.options).forEach(option => {
                        option.textContent = d.difficulties[option.value] || option.textContent;
                    });
                }
                
                // Sil butonlarını güncelle
                document.querySelectorAll('#btnDelete').forEach(btn => {
                    btn.textContent = d.btnDelete;
                    btn.setAttribute('onclick', `return confirm('${d.confirmDelete}')`);
                });
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent=(lang==='de'?'TR':'DE');
                localStorage.setItem('lang_questions', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_questions')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_questions')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();

        function toggleOptions() {
            const questionType = document.getElementById('question_type').value;
            const optionsContainer = document.getElementById('options-container');
            
            if (questionType === 'mcq') {
                optionsContainer.classList.add('show');
            } else {
                optionsContainer.classList.remove('show');
            }
        }
    </script>
</body>
</html>
