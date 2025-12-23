<?php
require_once '../auth.php';
require_once '../config.php';

$auth = Auth::getInstance();
if (!$auth->hasRole('teacher')) {
    header('Location: ../login.php');
    exit;
}
$user = $auth->getUser();

// Get the exam ID from URL
$examFile = $_GET['file'] ?? '';
if (empty($examFile)) {
    header('Location: exam_pdf.php');
    exit;
}

// Load exam metadata
$metaPath = __DIR__ . '/../data/exam_prints.json';
$prints = file_exists($metaPath) ? (json_decode(file_get_contents($metaPath), true) ?: []) : [];

// Find the specific exam
$exam = null;
foreach ($prints as $p) {
    if (($p['file'] ?? '') === $examFile) {
        // Check if this exam belongs to the current teacher
        if (($p['teacher'] ?? '') === ($user['name'] ?? '')) {
            $exam = $p;
            break;
        }
    }
}

if (!$exam) {
    header('Location: exam_pdf.php');
    exit;
}

// Eğer questions_data yoksa (eski kayıtlar için), bildiri göster
$questions = $exam['questions_data'] ?? [];
$isOldExam = empty($questions);

// Eski sınavlar için bilgi mesajı
$oldExamMessage = $isOldExam ? 
    "Bu sınav eski bir kayıt olduğu için detaylı cevap anahtarı mevcut değil. Sadece yeni oluşturulan sınavlar için cevap anahtarı özelliği kullanılabilir." : 
    "";

$questions = $exam['questions_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cevap Anahtarı - <?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary: #089473;
            --primary-dark: #068466;
            --success: #10b981;
            --white: #ffffff;
            --dark: #2c3e50;
            --gray: #6c757d;
            --light: #f8f9fa;
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
            padding: 20px;
        }

        .container {
            max-width: 900px;
            margin: 0 auto;
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

        h1 {
            color: var(--primary);
            font-size: 2rem;
            margin-bottom: 5px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            text-decoration: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.3);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(8, 148, 115, 0.4);
        }

        .exam-info {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .exam-info h2 {
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1.5rem;
        }

        .exam-info p {
            color: var(--gray);
            margin: 5px 0;
        }

        .answers-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 30px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 3px solid var(--primary);
        }

        .answer-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .answer-item {
            background: linear-gradient(135deg, rgba(8, 148, 115, 0.1) 0%, rgba(8, 148, 115, 0.05) 100%);
            border: 2px solid var(--primary);
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s ease;
        }

        .answer-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.2);
        }

        .question-number {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .correct-answer {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--success);
            text-align: center;
            padding: 10px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 8px;
        }

        .detailed-answers {
            margin-top: 30px;
        }

        .detailed-answer {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px solid #e1e8ed;
            transition: all 0.3s ease;
        }

        .detailed-answer:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(8, 148, 115, 0.1);
        }

        .question-text {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
            font-size: 1.05rem;
        }

        .options-list {
            margin: 10px 0;
            padding-left: 20px;
        }

        .option {
            padding: 8px;
            margin: 5px 0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .option.correct {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.2) 0%, rgba(16, 185, 129, 0.1) 100%);
            border: 2px solid var(--success);
            font-weight: 700;
            color: var(--success);
        }

        .option-label {
            font-weight: 600;
            margin-right: 8px;
        }

        .print-btn {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(108, 117, 125, 0.3);
            text-decoration: none;
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.4);
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }
            .header, .back-btn, .print-btn {
                display: none;
            }
            .container {
                max-width: 100%;
            }
        }

        @media (max-width: 768px) {
            .answer-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
            .header-content {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <div>
                    <h1>🔑 Cevap Anahtarı</h1>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button onclick="window.print()" class="print-btn">
                        <i class="fas fa-print"></i> Yazdır
                    </button>
                    <a href="exam_pdf.php" class="back-btn">
                        <i class="fas fa-arrow-left"></i> Geri Dön
                    </a>
                </div>
            </div>
        </div>

        <div class="exam-info">
            <h2><?php echo htmlspecialchars($exam['title'] ?? 'Sınav'); ?></h2>
            <p><strong>Eğitmen:</strong> <?php echo htmlspecialchars($exam['teacher'] ?? ''); ?></p>
            <p><strong>Oluşturma Tarihi:</strong> <?php echo htmlspecialchars(date('d.m.Y H:i', strtotime($exam['created_at'] ?? 'now'))); ?></p>
            <p><strong>Toplam Soru:</strong> <?php echo count($questions); ?></p>
        </div>

        <?php if ($isOldExam): ?>
            <div style="background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%); color: #78350f; padding: 20px; border-radius: 20px; margin-bottom: 20px; box-shadow: 0 10px 25px rgba(251, 191, 36, 0.3); border: 1px solid rgba(245, 158, 11, 0.3);">
                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 10px;">
                    <i class="fas fa-exclamation-triangle" style="font-size: 1.5rem;"></i>
                    <h3 style="margin: 0; font-size: 1.2rem;">Eski Sınav Kaydı</h3>
                </div>
                <p style="margin: 0; font-size: 0.95rem; line-height: 1.5;">
                    <?php echo htmlspecialchars($oldExamMessage); ?>
                </p>
                <p style="margin: 10px 0 0; font-size: 0.9rem; opacity: 0.9;">
                    💡 <strong>İpucu:</strong> Yeni oluşturacağınız sınavlar için cevap anahtarı özelliği otomatik olarak çalışacaktır.
                </p>
            </div>
        <?php endif; ?>

        <div class="answers-container">
            <?php if (!$isOldExam && count($questions) > 0): ?>
            <h2 class="section-title">Hızlı Cevap Anahtarı</h2>
            <div class="answer-grid">
                <?php foreach ($questions as $index => $question): 
                    $correctAnswer = '';
                    
                    // Determine correct answer
                    if (isset($question['correct_answer'])) {
                        if (is_numeric($question['correct_answer'])) {
                            // Convert numeric index to letter
                            $correctAnswer = chr(65 + (int)$question['correct_answer']);
                        } else {
                            $correctAnswer = $question['correct_answer'];
                        }
                    } elseif (isset($question['options']) && is_array($question['options'])) {
                        // Default to A if no correct answer specified
                        $correctAnswer = 'A';
                    }
                ?>
                    <div class="answer-item">
                        <div class="question-number">Soru <?php echo $index + 1; ?></div>
                        <div class="correct-answer"><?php echo htmlspecialchars($correctAnswer); ?></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <h2 class="section-title">Detaylı Cevaplar</h2>
            <div class="detailed-answers">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="detailed-answer">
                        <div class="question-number">Soru <?php echo $index + 1; ?></div>
                        <div class="question-text"><?php echo htmlspecialchars($question['question']); ?></div>
                        
                        <?php if (!empty($question['options']) && is_array($question['options'])): ?>
                            <div class="options-list">
                                <?php 
                                $correctIndex = is_numeric($question['correct_answer'] ?? -1) 
                                    ? (int)$question['correct_answer'] 
                                    : -1;
                                
                                foreach ($question['options'] as $optionIndex => $option): 
                                    $optionText = is_array($option) && isset($option['text']) 
                                        ? $option['text'] 
                                        : (string)$option;
                                    $isCorrect = $optionIndex === $correctIndex;
                                    $label = chr(65 + $optionIndex);
                                ?>
                                    <div class="option <?php echo $isCorrect ? 'correct' : ''; ?>">
                                        <span class="option-label"><?php echo $label; ?>)</span>
                                        <?php echo htmlspecialchars($optionText); ?>
                                        <?php if ($isCorrect): ?>
                                            <i class="fas fa-check-circle" style="color: var(--success); margin-left: 10px;"></i>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif (($question['type'] ?? '') === 'true_false'): ?>
                            <div class="options-list">
                                <div class="option <?php echo ($question['correct_answer'] ?? '') === '0' ? 'correct' : ''; ?>">
                                    <span class="option-label">A)</span> Doğru
                                    <?php if (($question['correct_answer'] ?? '') === '0'): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success); margin-left: 10px;"></i>
                                    <?php endif; ?>
                                </div>
                                <div class="option <?php echo ($question['correct_answer'] ?? '') === '1' ? 'correct' : ''; ?>">
                                    <span class="option-label">B)</span> Yanlış
                                    <?php if (($question['correct_answer'] ?? '') === '1'): ?>
                                        <i class="fas fa-check-circle" style="color: var(--success); margin-left: 10px;"></i>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php elseif (($question['type'] ?? '') === 'short_answer'): ?>
                            <div style="margin-top: 10px; padding: 10px; background: rgba(16, 185, 129, 0.1); border-radius: 8px;">
                                <strong>Cevap:</strong> <?php echo htmlspecialchars($question['correct_answer'] ?? 'Belirtilmemiş'); ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($question['explanation'])): ?>
                            <div style="margin-top: 15px; padding: 10px; background: #f8f9fa; border-left: 4px solid var(--primary); border-radius: 4px;">
                                <strong>Açıklama:</strong> <?php echo htmlspecialchars($question['explanation']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #6b7280;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 20px; opacity: 0.5;"></i>
                    <p style="font-size: 1.1rem; margin: 0;">Cevap anahtarı verisi mevcut değil</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
