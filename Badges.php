<?php
require_once 'database.php';

class Badges {
    private string $badgesFile;
    private $db;
    private $conn;

    public function __construct(string $badgesFile = __DIR__ . '/data/badges.json') {
        $this->badgesFile = $badgesFile;
        $this->db = Database::getInstance();
        $this->conn = $this->db->getConnection();
    }

    public function loadBadges(): array {
        if (!file_exists($this->badgesFile)) return [];
        return json_decode(file_get_contents($this->badgesFile), true) ?? [];
    }

    public function loadUserBadges(string $userId = null): array {
        // Eğer userId verilmezse, tüm kullanıcıların rozetlerini döndür (eski yapıya uyumluluk için gerekirse)
        // Ancak veritabanı yapısında genellikle tek kullanıcı için çekilir.
        // Geriye dönük uyumluluk için array yapısını koruyalım: [username => [badge_key => ['level'=>x, 'awarded_at'=>...]]]
        
        $sql = "SELECT * FROM user_badges";
        $params = [];
        
        if ($userId) {
            $sql .= " WHERE username = :username";
            $params[':username'] = $userId;
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $result = [];
            foreach ($rows as $row) {
                $u = $row['username'];
                $b = $row['badge_name']; // veritabanında badge_name olarak tutuluyor, bu key olmalı
                
                if (!isset($result[$u])) $result[$u] = [];
                
                // Veritabanında level sütunu yoksa varsayılan 1 kabul edelim veya json'dan parse edelim
                // user_badges tablosu: id, username, badge_name, earned_at
                // Level bilgisi eksik olabilir. Eğer level takibi gerekiyorsa tabloya level eklenmeli.
                // Şimdilik level bilgisini badge_name içinden veya ayrı bir logic ile çözmeliyiz.
                // VEYA tabloya level sütunu eklemeliyiz. Mevcut sql yapısında level yoktu.
                // Hemen kontrol edelim: user_badges tablosunda level yok.
                // Ancak Badges.php mantığı level üzerine kurulu.
                // Bu durumda level sütununu eklemek en doğrusu.
                
                $level = $row['level'] ?? 1; // Eğer sütun eklersek burası çalışır
                
                $result[$u][$b] = [
                    'level' => $level,
                    'awarded_at' => $row['earned_at']
                ];
            }
            
            return $result;
        } catch (Exception $e) {
            error_log("Load user badges error: " . $e->getMessage());
            return [];
        }
    }

    public function getUserStats(string $userId): array {
        // practice_results tablosundan istatistikleri çek
        $stats = [
            'total_sessions' => 0,
            'total_questions' => 0,
            'high_score_sessions' => 0,
            'long_sessions' => 0,
            'distinct_categories' => 0,
            'distinct_banks' => 0,
            'best_streak' => 0,
            'hard_success' => 0,
            'session_best_questions' => 0,
            'progressive_improvement' => 0,
        ];

        try {
            $stmt = $this->conn->prepare("SELECT * FROM practice_results WHERE username = :username ORDER BY created_at ASC");
            $stmt->execute([':username' => $userId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stats['total_sessions'] = count($results);
            $stats['total_questions'] = array_sum(array_column($results, 'total_questions'));
            
            $distinctCategories = [];
            $distinctBanks = [];
            $categoryToLastScore = [];
            
            foreach ($results as $r) {
                $score = (float)$r['score'];
                $duration = (int)$r['time_taken'];
                $total = (int)$r['total_questions'];
                $cat = $r['category'] ?? 'Genel';
                $bank = $r['bank'] ?? 'Genel';
                
                if ($score >= 90) $stats['high_score_sessions']++;
                if ($duration >= 1800) $stats['long_sessions']++;
                
                $distinctCategories[$cat] = true;
                $distinctBanks[$bank] = true;
                
                $stats['session_best_questions'] = max($stats['session_best_questions'], $total);
                
                if (isset($categoryToLastScore[$cat])) {
                    if ($score > $categoryToLastScore[$cat]) {
                        $stats['progressive_improvement']++;
                    }
                }
                $categoryToLastScore[$cat] = $score;
            }
            
            $stats['distinct_categories'] = count($distinctCategories);
            $stats['distinct_banks'] = count($distinctBanks);
            $stats['best_streak'] = $this->calculateLoginStreak($results);
            
        } catch (Exception $e) {
            error_log("Get user stats error: " . $e->getMessage());
        }

        return $stats;
    }

    private function calculateLoginStreak(array $results): int {
        if (empty($results)) return 0;
        $days = [];
        foreach ($results as $r) {
            $date = $r['created_at'] ?? '';
            if ($date) {
                $d = substr($date, 0, 10);
                $days[$d] = true;
            }
        }
        $uniqueDays = array_keys($days);
        sort($uniqueDays);
        
        $best = 0; 
        $cur = 0; 
        $prev = null;
        
        foreach ($uniqueDays as $d) {
            $t = strtotime($d);
            if ($prev !== null && $t - $prev === 86400) {
                $cur++;
            } else {
                $cur = 1;
            }
            $best = max($best, $cur);
            $prev = $t;
        }
        return $best;
    }

    public function evaluateAndAward(string $userId): array {
        $badges = $this->loadBadges();
        // Sadece bu kullanıcının rozetlerini yükle
        $allUserBadges = $this->loadUserBadges($userId);
        $userBadges = $allUserBadges[$userId] ?? [];
        
        $stats = $this->getUserStats($userId);
        $awardedNow = [];

        foreach ($badges as $badge) {
            $key = $badge['key'];
            $levels = $badge['levels'];
            $currentLevel = (int)($userBadges[$key]['level'] ?? 0);
            $progress = $this->getProgressForBadge($badge, $stats);

            $newLevel = $currentLevel;
            foreach ($levels as $i => $threshold) {
                $levelIndex = $i + 1;
                if ($progress >= (int)$threshold) {
                    $newLevel = max($newLevel, $levelIndex);
                }
            }

            if ($newLevel > $currentLevel) {
                // Yeni seviye kazanıldı, veritabanına kaydet
                $this->saveUserBadge($userId, $key, $newLevel);
                
                $awardedNow[] = [
                    'key' => $key,
                    'name' => $badge['name'],
                    'icon' => $badge['icon'],
                    'level' => $newLevel,
                ];
            }
        }

        return $awardedNow;
    }

    private function saveUserBadge(string $userId, string $badgeName, int $level): void {
        try {
            // Önce var mı kontrol et
            $stmt = $this->conn->prepare("SELECT id FROM user_badges WHERE username = :username AND badge_name = :badge_name");
            $stmt->execute([':username' => $userId, ':badge_name' => $badgeName]);
            $exists = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($exists) {
                // Güncelle (seviye artmış olabilir)
                // Not: user_badges tablosunda level sütunu olmalı. Yoksa ekleyeceğiz.
                $stmt = $this->conn->prepare("UPDATE user_badges SET level = :level, earned_at = NOW() WHERE id = :id");
                $stmt->execute([':level' => $level, ':id' => $exists['id']]);
            } else {
                // Ekle
                $stmt = $this->conn->prepare("INSERT INTO user_badges (username, badge_name, level, earned_at) VALUES (:username, :badge_name, :level, NOW())");
                $stmt->execute([':username' => $userId, ':badge_name' => $badgeName, ':level' => $level]);
            }
        } catch (Exception $e) {
            error_log("Save user badge error: " . $e->getMessage());
        }
    }

    private function getProgressForBadge(array $badge, array $stats): int|float {
        $metric = $badge['metric'];
        return (int)($stats[$metric] ?? 0);
    }
}
?>

