<?php
/**
 * Sınav Yönetim Sınıfı
 */

class ExamManager {
    private $questions;
    private $examConfig;
    private $studentAnswers = [];
    private $startTime;
    private $endTime;

    public function __construct($questions = []) {
        $this->questions = $questions;
    }

    /**
     * Sınav konfigürasyonu oluştur
     */
    public function createExam($config) {
        $this->examConfig = [
            'id' => uniqid('exam_'),
            'title' => $config['title'] ?? 'Karma Sınav',
            'questions' => $config['questions'] ?? [],
            'timeLimit' => $config['timeLimit'] ?? 60, // dakika
            'negativeMarking' => $config['negativeMarking'] ?? false,
            'shuffleQuestions' => $config['shuffleQuestions'] ?? true,
            'shuffleOptions' => $config['shuffleOptions'] ?? true,
            'showFeedback' => $config['showFeedback'] ?? false,
            'seed' => $config['seed'] ?? mt_rand(),
            'created_at' => time()
        ];

        // Soruları karıştır
        if ($this->examConfig['shuffleQuestions']) {
            mt_srand($this->examConfig['seed']);
            shuffle($this->examConfig['questions']);
        }

        return $this->examConfig;
    }

    /**
     * Sınavı başlat
     */
    public function startExam($studentName = '') {
        $this->startTime = time();
        $this->studentAnswers = [];
        
        $_SESSION['exam'] = $this->examConfig;
        $_SESSION['exam_start_time'] = $this->startTime;
        $_SESSION['student_name'] = $studentName;
        $_SESSION['student_answers'] = $this->studentAnswers;
        
        return true;
    }

    /**
     * Öğrenci cevabını kaydet
     */
    public function saveAnswer($questionId, $answer, $timeSpent = 0) {
        if (!isset($_SESSION['student_answers'])) {
            $_SESSION['student_answers'] = [];
        }

        $_SESSION['student_answers'][$questionId] = [
            'answer' => $answer,
            'time_spent' => $timeSpent,
            'answered_at' => time()
        ];

        return true;
    }

    /**
     * Sınavı bitir ve sonuçları hesapla
     */
    public function finishExam() {
        $this->endTime = time();
        $results = $this->calculateResults();
        
        $_SESSION['exam_results'] = $results;
        $_SESSION['exam_finished'] = true;
        
        return $results;
    }

    /**
     * Sonuçları hesapla
     */
    private function calculateResults() {
        $totalQuestions = count($this->examConfig['questions']);
        $correctAnswers = 0;
        $wrongAnswers = 0;
        $unanswered = 0;
        $totalPoints = 0;
        $earnedPoints = 0;
        $timeSpent = $this->endTime - $this->startTime;
        
        $questionResults = [];
        $categoryStats = [];
        $difficultyStats = [];

        foreach ($this->examConfig['questions'] as $question) {
            $questionId = $question['id'];
            $totalPoints += $question['points'];
            
            $studentAnswer = $_SESSION['student_answers'][$questionId] ?? null;
            
            if (!$studentAnswer || empty($studentAnswer['answer'])) {
                $unanswered++;
                $questionResults[] = [
                    'question' => $question,
                    'student_answer' => null,
                    'correct' => false,
                    'points' => 0,
                    'time_spent' => 0
                ];
            } else {
                $isCorrect = $this->checkAnswer($question, $studentAnswer['answer']);
                
                if ($isCorrect) {
                    $correctAnswers++;
                    $earnedPoints += $question['points'];
                } else {
                    $wrongAnswers++;
                    if ($this->examConfig['negativeMarking']) {
                        $earnedPoints -= ($question['points'] * 0.25); // %25 ceza
                    }
                }

                $questionResults[] = [
                    'question' => $question,
                    'student_answer' => $studentAnswer['answer'],
                    'correct' => $isCorrect,
                    'points' => $isCorrect ? $question['points'] : ($this->examConfig['negativeMarking'] ? -($question['points'] * 0.25) : 0),
                    'time_spent' => $studentAnswer['time_spent'] ?? 0
                ];

                // Kategori istatistikleri
                $category = $question['category'];
                if (!isset($categoryStats[$category])) {
                    $categoryStats[$category] = ['total' => 0, 'correct' => 0, 'points' => 0];
                }
                $categoryStats[$category]['total']++;
                if ($isCorrect) {
                    $categoryStats[$category]['correct']++;
                    $categoryStats[$category]['points'] += $question['points'];
                }

                // Zorluk istatistikleri
                $difficulty = $question['difficulty'];
                if (!isset($difficultyStats[$difficulty])) {
                    $difficultyStats[$difficulty] = ['total' => 0, 'correct' => 0, 'points' => 0];
                }
                $difficultyStats[$difficulty]['total']++;
                if ($isCorrect) {
                    $difficultyStats[$difficulty]['correct']++;
                    $difficultyStats[$difficulty]['points'] += $question['points'];
                }
            }
        }

        $percentage = $totalQuestions > 0 ? round(($correctAnswers / $totalQuestions) * 100, 2) : 0;
        $averageTime = $totalQuestions > 0 ? round($timeSpent / $totalQuestions, 2) : 0;

        return [
            'exam_id' => $this->examConfig['id'],
            'student_name' => $_SESSION['student_name'] ?? '',
            'total_questions' => $totalQuestions,
            'correct_answers' => $correctAnswers,
            'wrong_answers' => $wrongAnswers,
            'unanswered' => $unanswered,
            'total_points' => $totalPoints,
            'earned_points' => max(0, $earnedPoints), // Negatif puanları 0'a çek
            'percentage' => $percentage,
            'time_spent' => $timeSpent,
            'average_time_per_question' => $averageTime,
            'start_time' => $this->startTime,
            'end_time' => $this->endTime,
            'question_results' => $questionResults,
            'category_stats' => $categoryStats,
            'difficulty_stats' => $difficultyStats,
            'config' => $this->examConfig
        ];
    }

    /**
     * Cevabı kontrol et
     */
    private function checkAnswer($question, $studentAnswer) {
        $correctAnswers = $question['answer'];
        
        if ($question['type'] === 'mcq') {
            // Çoktan seçmeli sorular
            if (is_array($studentAnswer)) {
                sort($studentAnswer);
                sort($correctAnswers);
                return $studentAnswer === $correctAnswers;
            } else {
                return in_array($studentAnswer, $correctAnswers);
            }
        } elseif ($question['type'] === 'true_false') {
            // Doğru/Yanlış sorular
            $normalizedStudent = $this->normalizeTrueFalse($studentAnswer);
            $normalizedCorrect = array_map([$this, 'normalizeTrueFalse'], $correctAnswers);
            return in_array($normalizedStudent, $normalizedCorrect);
        } elseif ($question['type'] === 'short_answer') {
            // Kısa cevap sorular
            $normalizedStudent = $this->normalizeText($studentAnswer);
            foreach ($correctAnswers as $correctAnswer) {
                if ($this->normalizeText($correctAnswer) === $normalizedStudent) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    /**
     * Metni normalize et (Türkçe karakterler için)
     */
    private function normalizeText($text) {
        $text = mb_strtolower(trim($text), 'UTF-8');
        
        // Türkçe karakterleri normalize et
        $replacements = [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'Ç' => 'c', 'Ğ' => 'g', 'İ' => 'i', 'Ö' => 'o', 'Ş' => 's', 'Ü' => 'u'
        ];
        
        return strtr($text, $replacements);
    }

    /**
     * Doğru/Yanlış cevaplarını normalize et
     */
    private function normalizeTrueFalse($value) {
        $value = $this->normalizeText($value);
        
        $trueValues = ['true', 'doğru', 'evet', 'yes', '1'];
        $falseValues = ['false', 'yanlış', 'hayır', 'no', '0'];
        
        if (in_array($value, $trueValues)) {
            return 'true';
        } elseif (in_array($value, $falseValues)) {
            return 'false';
        }
        
        return $value;
    }

    /**
     * Sınav süresini kontrol et
     */
    public function isTimeUp() {
        if (!isset($_SESSION['exam_start_time']) || !isset($this->examConfig['timeLimit'])) {
            return false;
        }
        
        $timeLimit = $this->examConfig['timeLimit'] * 60; // dakikayı saniyeye çevir
        $elapsed = time() - $_SESSION['exam_start_time'];
        
        return $elapsed >= $timeLimit;
    }

    /**
     * Kalan süreyi hesapla
     */
    public function getRemainingTime() {
        if (!isset($_SESSION['exam_start_time']) || !isset($this->examConfig['timeLimit'])) {
            return 0;
        }
        
        $timeLimit = $this->examConfig['timeLimit'] * 60;
        $elapsed = time() - $_SESSION['exam_start_time'];
        $remaining = $timeLimit - $elapsed;
        
        return max(0, $remaining);
    }

    /**
     * Sınav durumunu kontrol et
     */
    public function isExamActive() {
        return isset($_SESSION['exam']) && 
               !isset($_SESSION['exam_finished']) && 
               !$this->isTimeUp();
    }

    /**
     * Sınavı sıfırla
     */
    public function resetExam() {
        unset($_SESSION['exam']);
        unset($_SESSION['exam_start_time']);
        unset($_SESSION['student_name']);
        unset($_SESSION['student_answers']);
        unset($_SESSION['exam_results']);
        unset($_SESSION['exam_finished']);
    }

    /**
     * CSV formatında sonuçları dışa aktar
     */
    public function exportToCSV($results) {
        $csv = "Sınav ID,Öğrenci Adı,Toplam Soru,Doğru,Yanlış,Boş,Toplam Puan,Kazanılan Puan,Yüzde,Süre (sn),Ortalama Süre (sn)\n";
        $csv .= sprintf("%s,%s,%d,%d,%d,%d,%.2f,%.2f,%.2f,%d,%.2f\n",
            $results['exam_id'],
            $results['student_name'],
            $results['total_questions'],
            $results['correct_answers'],
            $results['wrong_answers'],
            $results['unanswered'],
            $results['total_points'],
            $results['earned_points'],
            $results['percentage'],
            $results['time_spent'],
            $results['average_time_per_question']
        );

        $csv .= "\nSoru Detayları\n";
        $csv .= "Soru ID,Soru Metni,Kategori,Zorluk,Öğrenci Cevabı,Doğru Cevap,Doğru/Yanlış,Puan,Süre (sn)\n";
        
        foreach ($results['question_results'] as $result) {
            $question = $result['question'];
            $studentAnswer = is_array($result['student_answer']) ? 
                implode(',', $result['student_answer']) : 
                ($result['student_answer'] ?? '');
            $correctAnswer = implode(',', $question['answer']);
            
            $csv .= sprintf("%s,\"%s\",%s,%d,\"%s\",\"%s\",%s,%.2f,%d\n",
                $question['id'],
                str_replace('"', '""', $question['text']),
                $question['category'],
                $question['difficulty'],
                str_replace('"', '""', $studentAnswer),
                str_replace('"', '""', $correctAnswer),
                $result['correct'] ? 'Doğru' : 'Yanlış',
                $result['points'],
                $result['time_spent']
            );
        }

        return $csv;
    }

    /**
     * Öğrenci için sınavları getir
     */
    public function getExamsForStudent($studentUsername) {
        // Şimdilik boş array döndür, gerçek uygulamada veritabanından gelecek
        return [];
    }

    /**
     * Sınav sonuçlarını getir
     */
    public function getExamResults($examId, $studentUsername) {
        // Şimdilik boş array döndür, gerçek uygulamada veritabanından gelecek
        return [];
    }
}
?>
