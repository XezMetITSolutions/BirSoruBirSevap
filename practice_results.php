<?php
/**
 * Alıştırma Sonuçları
 */

require_once 'auth.php';
require_once 'config.php';
require_once 'Badges.php';

$auth = Auth::getInstance();

// Erişim kontrolü (Öğrenci, Öğretmen veya Süper Admin)
if (!$auth->hasRole('student') && !$auth->hasRole('teacher') && !$auth->hasRole('superadmin')) {
    header('Location: login.php');
    exit;
}

$user = $auth->getUser();

// POST verilerini al
$input = json_decode(file_get_contents('php://input'), true);
if ($input) {
    $_SESSION['practice_results'] = $input;
}

$results = $_SESSION['practice_results'] ?? null;

// Dashboard linkini belirle
$dashboardLink = 'index.php';
if ($auth->hasRole('teacher')) {
    $dashboardLink = 'teacher/dashboard.php';
} elseif ($auth->hasRole('student')) {
    $dashboardLink = 'student/dashboard.php';
}

if (!$results) {
    header('Location: ' . $dashboardLink);
    exit;
}

$answers = $results['answers'] ?? [];
$duration = $results['duration'] ?? 0;
$questions = $results['questions'] ?? [];

// Eğer answers boşsa, session'dan tekrar dene
if (empty($answers) && isset($_SESSION['practice_answers'])) {
    $answers = $_SESSION['practice_answers'];
}

// Sonuçları hesapla
$correct = 0;
$total = count($questions);
$detailedResults = [];

foreach ($questions as $index => $question) {
    $userAnswer = $answers[$index] ?? null;
    $correctAnswer = $question['answer'];
    $isCorrect = false;
    
    
    // Cevap kontrolü
    if ($question['type'] === 'short_answer') {
        $isCorrect = checkShortAnswer($userAnswer, $correctAnswer);
    } elseif ($question['type'] === 'true_false') {
        $isCorrect = checkTrueFalse($userAnswer, $correctAnswer);
    } else {
        // Çoktan seçmeli sorular için
        $isCorrect = checkMultipleChoice($userAnswer, $correctAnswer);
    }
    
    // Debug için geçici log
    if ($index < 3) { // İlk 3 soru için debug
        error_log("DEBUG - Q$index: User='$userAnswer', Correct='$correctAnswer', Result=" . ($isCorrect ? 'TRUE' : 'FALSE'));
    }
    
    
    if ($isCorrect) {
        $correct++;
    }
    
    $detailedResults[] = [
        'question' => $question,
        'userAnswer' => $userAnswer,
        'correctAnswer' => $correctAnswer,
        'isCorrect' => $isCorrect
    ];
}

$score = $total > 0 ? round(($correct / $total) * 100, 1) : 0;

// Ayarları al ve eksik meta bilgileri türet
$practiceSettings = $_SESSION['practice_settings'] ?? [];
$derivedBank = $results['bank'] ?? ($practiceSettings['bank'] ?? ($questions[0]['bank'] ?? 'Genel'));
$derivedCategory = $results['category'] ?? ($practiceSettings['category'] ?? ($questions[0]['category'] ?? 'Genel'));
$derivedDifficulty = $results['difficulty'] ?? ($practiceSettings['difficulty'] ?? ($questions[0]['difficulty'] ?? 'Belirtilmemiş'));

// Alıştırma sonucunu veritabanına kaydet
$practiceResult = [
    'student_id' => $user['username'] ?? $user['name'] ?? 'unknown',
    'student_name' => $user['name'] ?? 'Bilinmeyen',
    'score' => $score,
    'correct' => $correct,
    'wrong' => $total - $correct,
    'total' => $total,
    'duration' => $duration,
    'completed_at' => date('Y-m-d H:i:s'),
    'bank' => $derivedBank,
    'category' => $derivedCategory,
    'difficulty' => $derivedDifficulty,
    'answers' => $answers,
    'detailed_results' => $detailedResults
];

// Sonuçları dosyaya kaydet
$resultsFile = 'data/practice_results.json';
$allResults = [];
if (file_exists($resultsFile)) {
    $allResults = json_decode(file_get_contents($resultsFile), true) ?? [];
}

$allResults[] = $practiceResult;
file_put_contents($resultsFile, json_encode($allResults, JSON_PRETTY_PRINT));

// Rozet değerlendirme ve kazanım
$badges = new Badges();
$awardedBadges = $badges->evaluateAndAward($practiceResult['student_id']);

function checkShortAnswer($userAnswer, $correctAnswer) {
    if (!$userAnswer) return false;
    
    $userAnswer = trim(strtolower($userAnswer));
    $correctAnswer = trim(strtolower($correctAnswer));
    
    // Türkçe karakterleri normalize et
    $userAnswer = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $userAnswer);
    $correctAnswer = str_replace(['ı', 'ğ', 'ü', 'ş', 'ö', 'ç'], ['i', 'g', 'u', 's', 'o', 'c'], $correctAnswer);
    
    return $userAnswer === $correctAnswer;
}

function checkTrueFalse($userAnswer, $correctAnswer) {
    if ($userAnswer === null) return false;
    
    
    // Kullanıcı cevabını normalize et
    $userBool = false;
    if (is_bool($userAnswer)) {
        $userBool = $userAnswer;
    } elseif (is_string($userAnswer)) {
        $userBool = in_array(strtolower($userAnswer), ['true', 'doğru', '1', 'evet', 'yes']);
    } elseif (is_numeric($userAnswer)) {
        $userBool = $userAnswer == 1;
    }
    
    // Doğru cevabı normalize et
    $correctBool = false;
    if (is_bool($correctAnswer)) {
        $correctBool = $correctAnswer;
    } elseif (is_string($correctAnswer)) {
        $correctBool = in_array(strtolower($correctAnswer), ['true', 'doğru', '1', 'evet', 'yes']);
    } elseif (is_numeric($correctAnswer)) {
        $correctBool = $correctAnswer == 1;
    }
    
    return $userBool === $correctBool;
}

function extractChoice($answer) {
    if (is_array($answer)) {
        return $answer;
    }
    
    $answer = trim($answer);
    
    // Eğer zaten sadece harf ise (A, B, C, D)
    if (preg_match('/^[A-D]$/', $answer)) {
        return $answer;
    }
    
    // Eğer "A) Cevap" formatında ise, harfi çıkar
    if (preg_match('/^([A-D])\)/', $answer, $matches)) {
        return $matches[1];
    }
    
    // Eğer "A. Cevap" formatında ise, harfi çıkar
    if (preg_match('/^([A-D])\./', $answer, $matches)) {
        return $matches[1];
    }
    
    // Eğer "A - Cevap" formatında ise, harfi çıkar
    if (preg_match('/^([A-D])\s*[-–]/', $answer, $matches)) {
        return $matches[1];
    }
    
    // Eğer hiçbiri değilse, orijinal cevabı döndür
    return $answer;
}

function checkMultipleChoice($userAnswer, $correctAnswer) {
    if ($userAnswer === null) return false;
    
    // Eğer doğru cevap array ise, ilk elemanı al
    if (is_array($correctAnswer)) {
        $correctAnswer = $correctAnswer[0] ?? '';
    }
    
    // Eğer kullanıcı cevabı sayısal index ise (0, 1, 2, 3), harfe çevir
    if (is_numeric($userAnswer)) {
        $userAnswer = chr(65 + (int)$userAnswer); // 0=A, 1=B, 2=C, 3=D
    }
    
    // Eğer doğru cevap sayısal index ise, harfe çevir
    if (is_numeric($correctAnswer)) {
        $correctAnswer = chr(65 + (int)$correctAnswer); // 0=A, 1=B, 2=C, 3=D
    }
    
    // Karşılaştırma yap
    $result = (strtoupper($userAnswer) === strtoupper($correctAnswer));
    
    // Debug için
    error_log("DEBUG checkMultipleChoice: user='$userAnswer', correct='$correctAnswer', match=" . ($result ? 'YES' : 'NO'));
    
    return $result;
}

function formatTime($seconds) {
    $minutes = floor($seconds / 60);
    $seconds = $seconds % 60;
    return sprintf('%02d:%02d', $minutes, $seconds);
}

function getAnswerText($answer, $question) {
    if ($answer === null) return 'Cevaplanmadı';
    
    // Soru tipini belirle
    $questionType = $question['type'] ?? 'mcq';
    
    // Kısa cevap için
    if ($questionType === 'short_answer') {
        return is_string($answer) ? $answer : (string)$answer;
    }
    
    // Doğru/Yanlış için
    if ($questionType === 'true_false') {
        if (is_bool($answer)) {
            return $answer ? 'Doğru' : 'Yanlış';
        }
        if (is_string($answer)) {
            return in_array(strtolower($answer), ['true', 'doğru', '1']) ? 'Doğru' : 'Yanlış';
        }
        if (is_numeric($answer)) {
            return $answer == 1 ? 'Doğru' : 'Yanlış';
        }
        return 'Geçersiz';
    }
    
    // Çoktan seçmeli için - options kontrolü
    $options = $question['options'] ?? [];
    
    if (empty($options)) {
        // Eğer options yoksa, answer'ı direkt döndür
        return is_string($answer) ? $answer : (string)$answer;
    }
    
    // Eğer cevap zaten "A) İçerik" formatındaysa, direkt döndür
    if (is_string($answer) && preg_match('/^[A-D]\)\s/', $answer)) {
        return $answer;
    }
    
    // Eğer cevap array ise, tek eleman al
    if (is_array($answer)) {
        $answer = $answer[0] ?? '';
    }
    
    // String cevabı (A, B, C, D) sayısal index'e çevir
    if (is_string($answer) && preg_match('/^[A-D]$/i', $answer)) {
        $index = ord(strtoupper($answer)) - ord('A');
        $optionText = '';
        
        // Options dizisini kontrol et - hem sayısal hem de string key'ler için
        if (isset($options[$index])) {
            $optionText = $options[$index];
        } elseif (isset($options[(string)$index])) {
            $optionText = $options[(string)$index];
        } elseif (isset($options[$answer])) {
            $optionText = $options[$answer];
        } else {
            // Eğer hiçbir şekilde bulunamazsa, options dizisini tarayalım
            $optionKeys = array_keys($options);
            if (in_array($index, $optionKeys) || in_array((string)$index, $optionKeys)) {
                $optionText = $options[$index] ?? $options[(string)$index];
            }
        }
        
        // Eğer option text bulunduysa, harf + içerik formatında döndür
        if (!empty($optionText)) {
            return strtoupper($answer) . ') ' . $optionText;
        } else {
            // Son çare olarak sadece harf olarak döndür
            return strtoupper($answer);
        }
    }
    
    // Eğer cevap tek bir değer ise (direkt index)
    if (isset($options[$answer])) {
        // Eğer sayısal index ise, harf formatında göster
        if (is_numeric($answer)) {
            $letter = chr(65 + (int)$answer); // 0=A, 1=B, 2=C, 3=D
            return $letter . ') ' . $options[$answer];
        }
        return $options[$answer];
    }
    
    // Eğer cevap sayısal index ise
    if (is_numeric($answer)) {
        $index = (int)$answer;
        if (isset($options[$index])) {
            $letter = chr(65 + $index); // 0=A, 1=B, 2=C, 3=D
            return $letter . ') ' . $options[$index];
        } else {
            // Eğer options'ta index yoksa, seçenek numarası olarak döndür
            return "Seçenek " . ($index + 1);
        }
    }
    
    // Son çare olarak answer'ı string'e çevir
    return is_string($answer) ? $answer : (string)$answer;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alıştırma Sonuçları - Bir Soru Bir Sevap</title>
    <style>
        :root {
            --primary-color: #068567;
            --primary-dark: #055a4a;
            --primary-light: #077a5f;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --white: #ffffff;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --border-radius: 12px;
            --border-radius-lg: 20px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: 1.5rem 0;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="10" cy="60" r="0.5" fill="rgba(255,255,255,0.05)"/><circle cx="90" cy="40" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            opacity: 0.3;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo img {
            height: 3rem;
            width: auto;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
        }

        .logo h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .logo p {
            opacity: 0.9;
            font-size: 0.9rem;
            font-weight: 400;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-avatar {
            width: 2.5rem;
            height: 2.5rem;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.1rem;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }

        .back-btn {
            background: rgba(255,255,255,0.15);
            border: 1px solid rgba(255,255,255,0.3);
            color: var(--white);
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius-lg);
            text-decoration: none;
            font-weight: 500;
            transition: var(--transition);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.25);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .results-header {
            background: var(--white);
            padding: 3rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .results-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light), var(--primary-color));
        }

        .results-title {
            font-size: 2.5rem;
            color: var(--dark-color);
            margin-bottom: 1.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .score-display {
            font-size: 4.5rem;
            font-weight: 800;
            margin: 1.5rem 0;
            position: relative;
            display: inline-block;
        }

        .score-display::after {
            content: '%';
            font-size: 0.6em;
            opacity: 0.7;
            margin-left: 0.2em;
        }

        .score-excellent {
            color: var(--success-color);
            text-shadow: 0 2px 4px rgba(40, 167, 69, 0.3);
        }

        .score-good {
            color: var(--warning-color);
            text-shadow: 0 2px 4px rgba(255, 193, 7, 0.3);
        }

        .score-poor {
            color: var(--danger-color);
            text-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }

        .stat-card {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius);
            text-align: center;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-lg);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            display: block;
        }

        .stat-label {
            color: var(--secondary-color);
            font-size: 0.9rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn {
            background: linear-gradient(135deg, var(--primary-color), var(--primary-dark));
            color: var(--white);
            border: none;
            padding: 1rem 2rem;
            border-radius: var(--border-radius-lg);
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            transition: var(--transition);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-lg);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--secondary-color), #5a6268);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #1e7e34);
        }

        .btn-success:hover {
            background: #229954;
        }

        .detailed-results {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--border-radius-lg);
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .detailed-results::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light), var(--primary-color));
        }

        .section-title {
            font-size: 1.75rem;
            color: var(--dark-color);
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
            font-weight: 700;
            position: relative;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, var(--primary-color), var(--primary-light));
        }

        .question-item {
            border: 1px solid #e9ecef;
            border-radius: var(--border-radius);
            margin-bottom: 1.5rem;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: var(--transition);
            background: var(--white);
        }

        .question-item:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .question-header {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e9ecef;
        }

        .question-number {
            font-weight: 700;
            color: var(--primary-color);
            font-size: 1.1rem;
        }

        .question-status {
            padding: 0.5rem 1rem;
            border-radius: var(--border-radius-lg);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-correct {
            background: linear-gradient(135deg, #d4edda, #c3e6cb);
            color: #155724;
            box-shadow: 0 2px 4px rgba(21, 87, 36, 0.2);
        }

        .status-incorrect {
            background: linear-gradient(135deg, #f8d7da, #f5c6cb);
            color: #721c24;
            box-shadow: 0 2px 4px rgba(114, 28, 36, 0.2);
        }

        .question-content {
            padding: 2rem;
        }

        .question-text {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
            color: var(--dark-color);
            line-height: 1.6;
            font-weight: 500;
        }

        .answer-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .answer-box {
            padding: 1.5rem;
            border-radius: var(--border-radius);
            position: relative;
            overflow: hidden;
        }

        .answer-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
        }

        .user-answer {
            background: linear-gradient(135deg, #e3f2fd, #f3e5f5);
            border: 1px solid #bbdefb;
        }

        .user-answer::before {
            background: linear-gradient(180deg, #2196f3, #1976d2);
        }

        .correct-answer {
            background: linear-gradient(135deg, #e8f5e8, #f1f8e9);
            border: 1px solid #c8e6c9;
        }

        .correct-answer::before {
            background: linear-gradient(180deg, #4caf50, #388e3c);
        }

        .answer-label {
            font-weight: 700;
            margin-bottom: 0.75rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--dark-color);
        }

        .answer-text {
            color: var(--dark-color);
            font-weight: 500;
            line-height: 1.5;
        }

        .explanation {
            background: linear-gradient(135deg, #fff3cd, #ffeaa7);
            border: 1px solid #ffeaa7;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-top: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .explanation::before {
            content: '💡';
            position: absolute;
            top: 1rem;
            right: 1rem;
            font-size: 1.5rem;
            opacity: 0.3;
        }

        .explanation h4 {
            color: #856404;
            margin-bottom: 0.75rem;
            font-weight: 700;
            font-size: 1rem;
        }

        .explanation p {
            color: #856404;
            line-height: 1.6;
            font-weight: 500;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .results-header {
                padding: 2rem 1.5rem;
            }
            
            .results-title {
                font-size: 2rem;
            }
            
            .score-display {
                font-size: 3.5rem;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 300px;
                justify-content: center;
            }
            
            .answer-section {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .question-content {
                padding: 1.5rem;
            }
            
            .question-header {
                padding: 1rem 1.5rem;
                flex-direction: column;
                gap: 0.5rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .logo h1 {
                font-size: 1.5rem;
            }
            
            .results-title {
                font-size: 1.75rem;
            }
            
            .score-display {
                font-size: 3rem;
            }
            
            .stat-number {
                font-size: 2rem;
            }
        }

        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .question-item {
            animation: fadeInUp 0.6s ease-out;
        }

        .question-item:nth-child(even) {
            animation-delay: 0.1s;
        }

        .question-item:nth-child(odd) {
            animation-delay: 0.2s;
        }

        /* Scrollbar Styling */
        ::-webkit-scrollbar {
            width: 8px;
        }

        ::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb {
            background: linear-gradient(180deg, var(--primary-color), var(--primary-dark));
            border-radius: 4px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: linear-gradient(180deg, var(--primary-dark), var(--primary-color));
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="logo.png" alt="Bir Soru Bir Sevap Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p id="pageTitle">Alıştırma Sonuçları</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar">
                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                </div>
                <div>
                    <div><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.8em; opacity: 0.8;" id="userRole">
                        <?php 
                        if ($auth->hasRole('teacher')) echo 'Öğretmen';
                        elseif ($auth->hasRole('superadmin')) echo 'Yönetici';
                        else echo 'Öğrenci';
                        ?>
                    </div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="<?php echo $dashboardLink; ?>" class="back-btn" id="btnDashboard">
                    <span>←</span>
                    <span id="dashboardText">Dashboard</span>
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="results-header">
            <div style="text-align: center; margin-bottom: 2rem;">
                <div style="font-size: 4rem; margin-bottom: 1rem;">
                    <?php 
                    if ($score >= 80) {
                        echo "🎉";
                    } elseif ($score >= 60) {
                        echo "👍";
                    } else {
                        echo "💪";
                    }
                    ?>
                </div>
                <h2 class="results-title" id="resultsTitle">Alıştırma Tamamlandı!</h2>
            </div>
            
            <div class="score-display <?php 
                echo $score >= 80 ? 'score-excellent' : 
                    ($score >= 60 ? 'score-good' : 'score-poor'); 
            ?>">
                <?php echo $score; ?>
            </div>
            
            <p style="color: var(--secondary-color); font-size: 1.2rem; font-weight: 500; margin-top: 1rem;" id="scoreMessage">
                <?php 
                if ($score >= 80) {
                    echo "Harika! Çok başarılı bir performans gösterdiniz!";
                } elseif ($score >= 60) {
                    echo "İyi bir performans! Biraz daha çalışarak daha da iyileşebilirsiniz!";
                } else {
                    echo "Daha fazla çalışmaya ihtiyacınız var. Pes etmeyin!";
                }
                ?>
            </p>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $correct; ?></div>
                    <div class="stat-label" id="statLabel1">Doğru Cevap</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total - $correct; ?></div>
                    <div class="stat-label" id="statLabel2">Yanlış Cevap</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $total; ?></div>
                    <div class="stat-label" id="statLabel3">Toplam Soru</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo formatTime($duration); ?></div>
                    <div class="stat-label" id="statLabel4">Süre</div>
                </div>
            </div>

            <div class="actions">
                <a href="practice.php" class="btn" id="btnRetry">
                    <span>🔄</span>
                    <span id="retryText">Tekrar Dene</span>
                </a>
                <a href="<?php echo $dashboardLink; ?>" class="btn btn-secondary" id="btnDashboard2">
                    <span>📊</span>
                    <span id="dashboardText2">Dashboard</span>
                </a>
                <button onclick="exportResults()" class="btn btn-success" id="btnExport">
                    <span>📥</span>
                    <span id="exportText">Sonuçları İndir</span>
                </button>
            </div>
            <?php if (!empty($awardedBadges)) : ?>
            <div id="badge-toast" style="position: fixed; right: 20px; bottom: 20px; z-index: 9999;">
                <?php foreach ($awardedBadges as $b): ?>
                <div style="background: #fff; border-left: 6px solid #068567; box-shadow: 0 10px 20px rgba(0,0,0,0.15); padding: 16px 18px; border-radius: 10px; min-width: 280px; margin-top: 8px; display: flex; align-items: center; gap: 12px;">
                    <div style="font-size: 1.5rem; color: #068567;">
                        <i class="fas <?php echo htmlspecialchars($b['icon']); ?>"></i>
                    </div>
                    <div>
                        <div style="font-weight: 700; color: #2c3e50;" id="badgeTitle">Yeni Rozet: <?php echo htmlspecialchars($b['name']); ?> (<span id="levelText">Seviye</span> <?php echo (int)$b['level']; ?>)</div>
                        <div style="color: #64748b; font-size: 0.9rem;" id="badgeDesc">Tebrikler! Başarılarınız için bir rozet kazandınız.</div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <script>
                setTimeout(() => {
                    const toast = document.getElementById('badge-toast');
                    if (toast) toast.remove();
                }, 6000);
            </script>
            <?php endif; ?>
        </div>

        <div class="detailed-results">
            <h3 class="section-title" id="sectionTitle">📋 Detaylı Sonuçlar</h3>
            
            <?php foreach ($detailedResults as $index => $result): ?>
                <div class="question-item">
                    <div class="question-header">
                        <div class="question-number" id="questionNumber">Soru <?php echo $index + 1; ?></div>
                        <div class="question-status <?php echo $result['isCorrect'] ? 'status-correct' : 'status-incorrect'; ?>">
                            <?php echo $result['isCorrect'] ? '✅ <span id="correctText">Doğru</span>' : '❌ <span id="wrongText">Yanlış</span>'; ?>
                        </div>
                    </div>
                    <div class="question-content">
                        <div class="question-text">
                            <?php echo htmlspecialchars($result['question']['text']); ?>
                        </div>
                        
                        <div class="answer-section">
                            <div class="answer-box user-answer">
                                <div class="answer-label" id="userAnswerLabel">Sizin Cevabınız:</div>
                                <div class="answer-text">
                                    <?php 
                                    $userAnswerText = getAnswerText($result['userAnswer'], $result['question']);
                                    echo htmlspecialchars(is_string($userAnswerText) ? $userAnswerText : 'Geçersiz cevap');
                                    ?>
                                </div>
                            </div>
                            
                            <div class="answer-box correct-answer">
                                <div class="answer-label" id="correctAnswerLabel">Doğru Cevap:</div>
                                <div class="answer-text">
                                    <?php 
                                    $correctAnswerText = getAnswerText($result['correctAnswer'], $result['question']);
                                    echo htmlspecialchars(is_string($correctAnswerText) ? $correctAnswerText : 'Geçersiz cevap');
                                    ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($result['question']['explanation'])): ?>
                            <div class="explanation">
                                <h4>💡 Açıklama:</h4>
                                <p><?php echo htmlspecialchars($result['question']['explanation']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        function exportResults() {
            const data = {
                score: <?php echo $score; ?>,
                correct: <?php echo $correct; ?>,
                total: <?php echo $total; ?>,
                duration: <?php echo $duration; ?>,
                date: new Date().toLocaleDateString('tr-TR'),
                results: <?php echo json_encode($detailedResults); ?>
            };
            
            const csv = generateCSV(data);
            downloadCSV(csv, 'alistirma-sonuclari.csv');
        }
        
        function generateCSV(data) {
            let csv = 'Soru,Doğru/Yanlış,Sizin Cevabınız,Doğru Cevap,Açıklama\n';
            
            data.results.forEach((result, index) => {
                const question = result.question;
                const userAnswer = getAnswerText(result.userAnswer, question);
                const correctAnswer = getAnswerText(result.correctAnswer, question);
                const status = result.isCorrect ? 'Doğru' : 'Yanlış';
                const explanation = question.explanation || '';
                
                csv += `"Soru ${index + 1}","${status}","${userAnswer}","${correctAnswer}","${explanation}"\n`;
            });
            
            return csv;
        }
        
        function getAnswerText(answer, question) {
            if (answer === null) return 'Cevaplanmadı';
            
            if (question.type === 'short_answer') {
                return answer;
            }
            
            if (Array.isArray(answer)) {
                return answer.map(index => {
                    if (question.options[index]) {
                        const letter = typeof index === 'number' ? String.fromCharCode(65 + index) : index;
                        return `${letter}) ${question.options[index]}`;
                    } else {
                        return `Seçenek ${typeof index === 'number' ? index + 1 : index}`;
                    }
                }).join(', ');
            }
            
            // String cevabı (A, B, C, D) sayısal index'e çevir
            if (typeof answer === 'string' && /^[A-D]$/i.test(answer)) {
                const index = answer.toUpperCase().charCodeAt(0) - 'A'.charCodeAt(0);
                let optionText = '';
                
                // Options dizisini kontrol et - hem sayısal hem de string key'ler için
                if (question.options[index]) {
                    optionText = question.options[index];
                } else if (question.options[answer]) {
                    optionText = question.options[answer];
                } else if (question.options[answer.toUpperCase()]) {
                    optionText = question.options[answer.toUpperCase()];
                }
                
                if (optionText) {
                    return `${answer.toUpperCase()}) ${optionText}`;
                } else {
                    return answer.toUpperCase();
                }
            }
            
            if (question.options[answer]) {
                if (typeof answer === 'number') {
                    const letter = String.fromCharCode(65 + answer);
                    return `${letter}) ${question.options[answer]}`;
                }
                return question.options[answer];
            }
            
            return `Seçenek ${typeof answer === 'number' ? answer + 1 : answer}`;
        }
        
        function downloadCSV(csv, filename) {
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Alıştırma Sonuçları', userRole:'Öğrenci', dashboard:'Dashboard',
                resultsTitle:'Alıştırma Tamamlandı!', scoreMessage:'Harika! Çok başarılı bir performans gösterdiniz!',
                statLabel1:'Doğru Cevap', statLabel2:'Yanlış Cevap', statLabel3:'Toplam Soru', statLabel4:'Süre',
                retryText:'Tekrar Dene', dashboardText2:'Dashboard', exportText:'Sonuçları İndir',
                badgeTitle:'Yeni Rozet:', levelText:'Seviye', badgeDesc:'Tebrikler! Başarılarınız için bir rozet kazandınız.',
                sectionTitle:'📋 Detaylı Sonuçlar', questionNumber:'Soru', correctText:'Doğru', wrongText:'Yanlış',
                userAnswerLabel:'Sizin Cevabınız:', correctAnswerLabel:'Doğru Cevap:',
                scoreMessage1:'Harika! Çok başarılı bir performans gösterdiniz!',
                scoreMessage2:'İyi bir performans! Biraz daha çalışarak daha da iyileşebilirsiniz!',
                scoreMessage3:'Daha fazla çalışmaya ihtiyacınız var. Pes etmeyin!'
            };
            const de = {
                pageTitle:'Übungsergebnisse', userRole:'Schüler', dashboard:'Dashboard',
                resultsTitle:'Übung abgeschlossen!', scoreMessage:'Großartig! Sie haben eine sehr erfolgreiche Leistung gezeigt!',
                statLabel1:'Richtige Antwort', statLabel2:'Falsche Antwort', statLabel3:'Gesamt Fragen', statLabel4:'Zeit',
                retryText:'Nochmal versuchen', dashboardText2:'Dashboard', exportText:'Ergebnisse herunterladen',
                badgeTitle:'Neues Abzeichen:', levelText:'Stufe', badgeDesc:'Glückwunsch! Sie haben ein Abzeichen für Ihre Erfolge verdient.',
                sectionTitle:'📋 Detaillierte Ergebnisse', questionNumber:'Frage', correctText:'Richtig', wrongText:'Falsch',
                userAnswerLabel:'Ihre Antwort:', correctAnswerLabel:'Richtige Antwort:',
                scoreMessage1:'Großartig! Sie haben eine sehr erfolgreiche Leistung gezeigt!',
                scoreMessage2:'Gute Leistung! Mit etwas mehr Übung können Sie sich noch weiter verbessern!',
                scoreMessage3:'Sie brauchen mehr Übung. Geben Sie nicht auf!'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                // User role is dynamic in PHP
                setText('#dashboardText', d.dashboard);
                setText('#resultsTitle', d.resultsTitle);
                setText('#statLabel1', d.statLabel1);
                setText('#statLabel2', d.statLabel2);
                setText('#statLabel3', d.statLabel3);
                setText('#statLabel4', d.statLabel4);
                setText('#retryText', d.retryText);
                setText('#dashboardText2', d.dashboardText2);
                setText('#exportText', d.exportText);
                setText('#badgeTitle', d.badgeTitle);
                setText('#levelText', d.levelText);
                setText('#badgeDesc', d.badgeDesc);
                setText('#sectionTitle', d.sectionTitle);
                setText('#questionNumber', d.questionNumber);
                setText('#correctText', d.correctText);
                setText('#wrongText', d.wrongText);
                setText('#userAnswerLabel', d.userAnswerLabel);
                setText('#correctAnswerLabel', d.correctAnswerLabel);
                
                // Skor mesajını güncelle
                const scoreMessage = document.getElementById('scoreMessage');
                if (scoreMessage) {
                    const score = parseInt(scoreMessage.textContent.match(/\d+/)?.[0] || '0');
                    if (score >= 80) {
                        scoreMessage.textContent = d.scoreMessage1;
                    } else if (score >= 60) {
                        scoreMessage.textContent = d.scoreMessage2;
                    } else {
                        scoreMessage.textContent = d.scoreMessage3;
                    }
                }
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_practice_results', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_practice_results')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_practice_results')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>
