<?php
/**
 * Soru Yükleme ve Yönetim Sınıfı
 */

class QuestionLoader {
    private $rootDir;
    private $maxDepth;
    private $questions = [];
    private $categories = [];
    private $banks = [];
    private $errors = [];
    private $restrictedBanks = [
        'İslami İlimler' => ['IQRA Vorarlberg', 'IQRA Tirol'],
        'İslamiİlimler' => ['IQRA Vorarlberg', 'IQRA Tirol']
    ];


    public function __construct($rootDir = null, $maxDepth = null) {
        // Mutlak yol oluştur
        $defaultDir = defined('ROOT_DIR') ? ROOT_DIR : 'Sorular';
        if (!is_dir($defaultDir)) {
            // Göreceli yol çalışmıyorsa, mutlak yol dene
            $defaultDir = __DIR__ . DIRECTORY_SEPARATOR . 'Sorular';
        }
        $this->rootDir = $rootDir ?? $defaultDir;
        $this->maxDepth = $maxDepth ?? (defined('MAX_SCAN_DEPTH') ? MAX_SCAN_DEPTH : 5);
    }

    /**
     * Soruları yükle (Veritabanından, yoksa dosyadan)
     */
    public function loadQuestions() {
        // Önce veritabanını kontrol et
        // GitHub dağıtımı için dosya öncelikli yükleme
        $this->loadFromFiles();
        return;

        // Önce veritabanını kontrol et (Devre dışı bırakıldı - Dosya sistemi esas alınacak)
        /*
        if ($this->loadFromDatabase()) {
            return;
        }
        */

        // Veritabanı boşsa veya hata varsa dosyadan yükle
        $this->loadFromFiles();
    }

    /**
     * Veritabanından soruları yükle
     */
    private function loadFromDatabase() {
        require_once 'database.php';
        try {
            $db = Database::getInstance();
            $conn = $db->getConnection();
            
            // Tablo var mı kontrol et
            $stmt = $conn->query("SHOW TABLES LIKE 'questions'");
            if ($stmt->rowCount() == 0) {
                return false;
            }
            
            $stmt = $conn->query("SELECT * FROM questions ORDER BY bank, category, id");
            $dbQuestions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($dbQuestions)) {
                return false;
            }
            
            foreach ($dbQuestions as $q) {
                // JSON alanları decode et
                $options = json_decode($q['options'], true) ?? [];
                $answer = json_decode($q['answer'], true) ?? [];
                $media = json_decode($q['media'], true) ?? [];
                $tags = json_decode($q['tags'], true) ?? [];
                
                $processedQ = [
                    'id' => $q['question_uid'],
                    'type' => $q['type'],
                    'question' => $q['question_text'],
                    'text' => $q['question_text'],
                    'options' => $options,
                    'answer' => $answer,
                    'explanation' => $q['explanation'],
                    'difficulty' => (int)$q['difficulty'],
                    'points' => (int)$q['points'],
                    'media' => $media,
                    'tags' => $tags,
                    'bank' => $q['bank'],
                    'category' => $q['category'],
                    'source' => 'database'
                ];
                
                $this->questions[] = $processedQ;
                
                // Banka ve kategori listelerini güncelle
                if (!in_array($q['bank'], $this->banks)) {
                    $this->banks[] = $q['bank'];
                }
                if (!isset($this->categories[$q['bank']])) {
                    $this->categories[$q['bank']] = [];
                }
                if (!in_array($q['category'], $this->categories[$q['bank']])) {
                    $this->categories[$q['bank']][] = $q['category'];
                }
            }
            
            // Session'a kaydet
            $_SESSION['all_questions'] = $this->questions;
            $_SESSION['categories'] = $this->categories;
            $_SESSION['banks'] = $this->banks;
            
            return true;
            
        } catch (Exception $e) {
            $this->errors[] = "Veritabanı yükleme hatası: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Dosyadan soruları yükle (Eski yöntem)
     */
    public function loadFromFiles() {
        // Klasör varlığını kontrol et
        if (!is_dir($this->rootDir)) {
            $this->errors[] = "Soru klasörü bulunamadı: {$this->rootDir}";
            
            // Alternatif yolları dene
            $alternativePaths = [
                __DIR__ . DIRECTORY_SEPARATOR . 'Sorular',
                dirname(__DIR__) . DIRECTORY_SEPARATOR . 'Sorular',
                getcwd() . DIRECTORY_SEPARATOR . 'Sorular',
                'Sorular'
            ];
            
            foreach ($alternativePaths as $path) {
                if (is_dir($path)) {
                    $this->rootDir = $path;
                    $this->errors[] = "Alternatif yol bulundu: {$path}";
                    break;
                }
            }
            
            // Hala bulunamadıysa fallback yükle
            if (!is_dir($this->rootDir)) {
                $this->errors[] = "Hiçbir alternatif yol bulunamadı. Mevcut dizin: " . getcwd();
                $this->loadFallbackQuestions();
                return;
            }
        }

        $this->scanDirectory($this->rootDir, 0);
        
        if (empty($this->questions)) {
            $this->errors[] = "Hiç soru bulunamadı - Klasör: {$this->rootDir}";
            $this->loadFallbackQuestions();
        }

        // Debug bilgileri
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->errors[] = "Debug: {$this->rootDir} klasörü taranıyor...";
            $this->errors[] = "Debug: " . count($this->questions) . " soru yüklendi";
            $this->errors[] = "Debug: " . count($this->banks) . " banka bulundu";
        }

        // Oturumda sakla
        $_SESSION['all_questions'] = $this->questions;
        $_SESSION['categories'] = $this->categories;
        $_SESSION['banks'] = $this->banks;
        $_SESSION['question_errors'] = $this->errors;
    }

    /**
     * Klasörü rekürsif olarak tara
     */
    private function scanDirectory($dir, $depth) {
        if ($depth > $this->maxDepth) {
            return;
        }

        // RecursiveCallbackFilterIterator sorun çıkarabilir, direkt fallback kullan
        $this->scanDirectoryFallback($dir, $depth);
    }

    /**
     * Fallback klasör tarama metodu (eski PHP sürümleri için)
     */
    private function scanDirectoryFallback($dir, $depth) {
        if ($depth > $this->maxDepth) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            $this->errors[] = "Debug: Klasör okunamadı: {$dir}";
            return;
        }

        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            $this->errors[] = "Debug: {$dir} klasöründe " . count($files) . " dosya bulundu";
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $fullPath = $dir . DIRECTORY_SEPARATOR . $file;
            
            // Gizli dosyaları atla
            if (strpos($file, '.') === 0) {
                continue;
            }
            
            // Engellenen desenleri kontrol et
            $skip = false;
            $blockedPatterns = defined('BLOCKED_PATTERNS') ? BLOCKED_PATTERNS : ['..', '.git', '.env', 'config'];
            foreach ($blockedPatterns as $pattern) {
                if (strpos($fullPath, $pattern) !== false) {
                    $skip = true;
                    break;
                }
            }
            
            if ($skip) {
                continue;
            }

            if (is_dir($fullPath)) {
                $this->scanDirectoryFallback($fullPath, $depth + 1);
            } elseif (is_file($fullPath) && in_array(pathinfo($file, PATHINFO_EXTENSION), defined('ALLOWED_EXTENSIONS') ? ALLOWED_EXTENSIONS : ['json'])) {
                $this->loadQuestionFile($fullPath);
            }
        }
    }

    /**
     * JSON soru dosyasını yükle
     */
    private function loadQuestionFile($filePath) {
        try {
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $this->errors[] = "Debug: Dosya yükleniyor: " . basename($filePath);
            }
            $content = file_get_contents($filePath);
            if ($content === false) {
                $this->errors[] = "Dosya okunamadı: " . basename($filePath);
                return;
            }

            $data = json_decode($content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->errors[] = "JSON hatası: " . basename($filePath) . " - " . json_last_error_msg();
                return;
            }

            // Dosya yolu bilgilerini çıkar
            $relativePath = str_replace($this->rootDir . DIRECTORY_SEPARATOR, '', $filePath);
            $pathParts = explode(DIRECTORY_SEPARATOR, $relativePath);
            
            $bank = $pathParts[0] ?? 'Bilinmeyen';
            $fileName = $pathParts[1] ?? 'Genel';
            
            // Dosya adından kategori çıkar
            $category = $this->extractCategoryFromFileName($fileName);
            
            // Bank ve kategori listelerini güncelle
            if (!in_array($bank, $this->banks)) {
                $this->banks[] = $bank;
            }
            if (!isset($this->categories[$bank])) {
                $this->categories[$bank] = [];
            }
            if (!in_array($category, $this->categories[$bank])) {
                $this->categories[$bank][] = $category;
            }

            // Soruları işle
            if (isset($data['questions']) && is_array($data['questions'])) {
                foreach ($data['questions'] as $index => $question) {
                    $processedQuestion = $this->processQuestion($question, $bank, $category, $relativePath, $index);
                    if ($processedQuestion) {
                        $this->questions[] = $processedQuestion;
                    }
                }
            } elseif (isset($data['question'])) {
                // Tek soru formatı
                $processedQuestion = $this->processQuestion($data, $bank, $category, $relativePath, 0);
                if ($processedQuestion) {
                    $this->questions[] = $processedQuestion;
                }
            } else {
                // JSON formatı tanınmadı
                $this->errors[] = "Bilinmeyen JSON formatı: " . basename($filePath);
            }

        } catch (Exception $e) {
            $this->errors[] = "Dosya işleme hatası: " . basename($filePath) . " - " . $e->getMessage();
        }
    }

    /**
     * Soruyu standart formata dönüştür
     */
    private function processQuestion($question, $bank, $category, $source, $index) {
        try {
            // Temel alanları kontrol et - hem 'question' hem 'text' alanlarını kontrol et
            $questionText = $question['question'] ?? $question['text'] ?? '';
            if (empty($questionText)) {
                $this->errors[] = "Soru metni bulunamadı: " . json_encode($question);
                return null;
            }

            // Soru tipini belirle
            $type = 'mcq'; // Varsayılan
            if (isset($question['type'])) {
                $type = $question['type'];
            } elseif (isset($question['options']) && is_array($question['options'])) {
                $type = 'mcq';
            } else {
                $type = 'short_answer';
            }

            // ID oluştur
            $id = $question['id'] ?? uniqid('q_', true);

            // Seçenekleri işle (MCQ için) - basit array olarak
            $options = [];
            if ($type === 'mcq' && isset($question['options'])) {
                if (is_array($question['options'])) {
                    foreach ($question['options'] as $key => $value) {
                        if (is_array($value) && isset($value['text'])) {
                            $options[] = $value['text'];
                        } else {
                            $options[] = $value;
                        }
                    }
                }
            }
            
            // Eğer options boşsa ama question'da options varsa, onu kullan
            if (empty($options) && isset($question['options']) && is_array($question['options'])) {
                foreach ($question['options'] as $key => $value) {
                    $options[] = $value;
                }
            }

            // Doğru cevabı işle
            $answer = [];
            if (isset($question['correct_answer'])) {
                if (is_array($question['correct_answer'])) {
                    $answer = $question['correct_answer'];
                } else {
                    $answer = [$question['correct_answer']];
                }
            }

            return [
                'id' => $id,
                'type' => $type,
                'question' => $questionText, // Hem 'question' hem 'text' alanlarını destekle
                'text' => $questionText, // Geriye uyumluluk için
                'options' => $options,
                'answer' => $answer,
                'explanation' => $question['explanation'] ?? '',
                'topic' => $question['topic'] ?? '',
                'tags' => $question['tags'] ?? [],
                'difficulty' => $question['difficulty'] ?? 1,
                'points' => $question['points'] ?? 1,
                'timeLimit' => $question['timeLimit'] ?? null,
                'media' => $question['media'] ?? ['image' => '', 'audio' => '', 'video' => ''],
                'bank' => $bank,
                'category' => $category,
                'source' => $source
            ];

        } catch (Exception $e) {
            $this->errors[] = "Soru işleme hatası: " . $e->getMessage();
            return null;
        }
    }

    /**
     * Dosya adından kategori çıkar
     */
    private function extractCategoryFromFileName($fileName) {
        // Dosya uzantısını kaldır
        $fileName = pathinfo($fileName, PATHINFO_FILENAME);
        
        // Kategori eşleştirmeleri
        $categoryMap = [
            'itikat' => 'İtikat',
            'ahlak' => 'Ahlak',
            'ibadet' => 'İbadet',
            'siyer' => 'Siyer',
            'musiki' => 'Musiki',
            'teskilat' => 'Teşkilat',
            'hadis' => 'Hadis',
            'hitabet' => 'Hitabet',
            'insan_haklari' => 'İnsan Hakları',
            'islam_tarihi' => 'İslam Tarihi',
            'tasavvuf' => 'Tasavvuf',
            'tefsir' => 'Tefsir',
            'turkce' => 'Türkçe',
            'akaid' => 'Akaid',
            'kelam' => 'Kelam',
            'fikih' => 'Fıkıh',
            'din_psikolojisi' => 'Din Psikolojisi',
            'din_sosyolojisi' => 'Din Sosyolojisi',
            'din_egitimi' => 'Din Eğitimi',
            'temel_esaslar' => 'Temel Esaslar',
            'arapca' => 'Arapça'
        ];
        
        // Dosya adını küçük harfe çevir ve eşleştir
        $lowerFileName = strtolower($fileName);
        
        foreach ($categoryMap as $key => $value) {
            if (strpos($lowerFileName, $key) !== false) {
                // Özel durumlar için tam eşleşme kontrolü veya daha spesifik kontroller eklenebilir
                if ($key === 'islam_tarihi' && strpos($lowerFileName, 'islam_tarihi_1') !== false) return 'İslam Tarihi I';
                if ($key === 'islam_tarihi' && strpos($lowerFileName, 'islam_tarihi_2') !== false) return 'İslam Tarihi II';
                return $value;
            }
        }
        
        // Eşleşme bulunamazsa dosya adını döndür
        return ucfirst($fileName);
    }

    /**
     * Fallback soruları yükle (soru bulunamadığında)
     */
    private function loadFallbackQuestions() {
        $this->questions = [
            [
                'id' => 'fallback_1',
                'type' => 'mcq',
                'text' => 'İslam dininin temel kaynağı nedir?',
                'options' => [
                    ['key' => 'A', 'text' => 'Kur\'an-ı Kerim'],
                    ['key' => 'B', 'text' => 'Hadisler'],
                    ['key' => 'C', 'text' => 'İcma'],
                    ['key' => 'D', 'text' => 'Kıyas']
                ],
                'answer' => ['A'],
                'explanation' => 'İslam dininin temel kaynağı Kur\'an-ı Kerim\'dir.',
                'topic' => 'Temel Bilgiler',
                'tags' => ['islam', 'kaynak'],
                'difficulty' => 1,
                'points' => 1,
                'timeLimit' => null,
                'media' => ['image' => '', 'audio' => '', 'video' => ''],
                'bank' => 'Örnek Sorular',
                'category' => 'Genel',
                'source' => 'fallback.json'
            ],
            [
                'id' => 'fallback_2',
                'type' => 'mcq',
                'text' => 'Peygamberimiz Hz. Muhammed (s.a.v.) hangi yılda doğmuştur?',
                'options' => [
                    ['key' => 'A', 'text' => '570'],
                    ['key' => 'B', 'text' => '571'],
                    ['key' => 'C', 'text' => '572'],
                    ['key' => 'D', 'text' => '573']
                ],
                'answer' => ['B'],
                'explanation' => 'Peygamberimiz Hz. Muhammed (s.a.v.) 571 yılında Mekke\'de doğmuştur.',
                'topic' => 'Siyer',
                'tags' => ['peygamber', 'doğum'],
                'difficulty' => 2,
                'points' => 1,
                'timeLimit' => null,
                'media' => ['image' => '', 'audio' => '', 'video' => ''],
                'bank' => 'Örnek Sorular',
                'category' => 'Genel',
                'source' => 'fallback.json'
            ]
        ];

        $this->banks = ['Örnek Sorular'];
        $this->categories = ['Örnek Sorular' => ['Genel']];
    }

    /**
     * Getter metodları
     */
    public function getQuestions() {
        return $this->questions;
    }

    public function getCategories() {
        return $this->categories;
    }

    public function getBanks() {
        return $this->banks;
    }

    public function getErrors() {
        return $this->errors;
    }

    /**
     * Filtrelenmiş soruları getir
     */
    public function getFilteredQuestions($filters = []) {
        $questions = $this->questions;

        if (isset($filters['bank']) && !empty($filters['bank'])) {
            $questions = array_filter($questions, function($q) use ($filters) {
                return $q['bank'] === $filters['bank'];
            });
        }

        if (isset($filters['category']) && !empty($filters['category'])) {
            $questions = array_filter($questions, function($q) use ($filters) {
                return $q['category'] === $filters['category'];
            });
        }

        if (isset($filters['difficulty']) && !empty($filters['difficulty'])) {
            $questions = array_filter($questions, function($q) use ($filters) {
                return $q['difficulty'] == $filters['difficulty'];
            });
        }

        if (isset($filters['type']) && !empty($filters['type'])) {
            $questions = array_filter($questions, function($q) use ($filters) {
                return $q['type'] === $filters['type'];
            });
        }

        if (isset($filters['tags']) && !empty($filters['tags'])) {
            $questions = array_filter($questions, function($q) use ($filters) {
                return !empty(array_intersect($q['tags'], $filters['tags']));
            });
        }

        return array_values($questions);
    }

    /**
     * Banka erişim kontrolü
     */
    public function isBankAccessible($bank, $userInstitution, $userRole) {
        // Admin ve superadmin her bankaya erişebilir
        if ($userRole === 'superadmin' || $userRole === 'admin') return true;
        
        // Kısıtlı banka listesinde yoksa herkese açık
        if (!isset($this->restrictedBanks[$bank])) return true;
        
        return in_array($userInstitution, $this->restrictedBanks[$bank]);
    }
}
?>
