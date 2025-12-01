<?php
class Badges {
    private string $badgesFile;
    private string $userBadgesFile;
    private string $practiceResultsFile;

    public function __construct(
        string $badgesFile = __DIR__ . '/data/badges.json',
        string $userBadgesFile = __DIR__ . '/data/user_badges.json',
        string $practiceResultsFile = __DIR__ . '/data/practice_results.json'
    ) {
        $this->badgesFile = $badgesFile;
        $this->userBadgesFile = $userBadgesFile;
        $this->practiceResultsFile = $practiceResultsFile;
    }

    public function loadBadges(): array {
        if (!file_exists($this->badgesFile)) return [];
        return json_decode(file_get_contents($this->badgesFile), true) ?? [];
    }

    public function loadUserBadges(): array {
        if (!file_exists($this->userBadgesFile)) return [];
        return json_decode(file_get_contents($this->userBadgesFile), true) ?? [];
    }

    public function saveUserBadges(array $data): void {
        file_put_contents($this->userBadgesFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function getUserStats(string $userId): array {
        $results = [];
        if (file_exists($this->practiceResultsFile)) {
            $all = json_decode(file_get_contents($this->practiceResultsFile), true) ?? [];
            foreach ($all as $r) {
                if (($r['student_id'] ?? '') === $userId) {
                    $results[] = $r;
                }
            }
        }

        $totalSessions = count($results);
        $totalQuestions = array_sum(array_map(fn($r) => (int)($r['total'] ?? 0), $results));
        $highScoreSessions = 0;
        $longSessions = 0;
        $distinctCategories = [];
        $distinctBanks = [];
        $bestStreak = $this->calculateLoginStreak($results);
        $hardSuccess = 0;
        $sessionBestQuestions = 0;
        $progressiveImprovement = 0;

        $categoryToLastScore = [];
        foreach ($results as $r) {
            $score = (float)($r['score'] ?? 0);
            if ($score >= 90) $highScoreSessions++;
            if ((int)($r['duration'] ?? 0) >= 1800) $longSessions++; // 30 dakika+
            $cat = (string)($r['category'] ?? 'Genel');
            $bank = (string)($r['bank'] ?? 'Genel');
            $distinctCategories[$cat] = true;
            $distinctBanks[$bank] = true;

            // En iyi oturum (çözülen soru)
            $sessionBestQuestions = max($sessionBestQuestions, (int)($r['total'] ?? 0));

            if (isset($categoryToLastScore[$cat])) {
                if ($score > $categoryToLastScore[$cat]) {
                    $progressiveImprovement++;
                }
            }
            $categoryToLastScore[$cat] = $score;
        }

        return [
            'total_sessions' => $totalSessions,
            'total_questions' => $totalQuestions,
            'high_score_sessions' => $highScoreSessions,
            'long_sessions' => $longSessions,
            'distinct_categories' => count(array_keys($distinctCategories)),
            'distinct_banks' => count(array_keys($distinctBanks)),
            'best_streak' => $bestStreak,
            'hard_success' => $hardSuccess,
            'session_best_questions' => $sessionBestQuestions,
            'progressive_improvement' => $progressiveImprovement,
        ];
    }

    private function calculateLoginStreak(array $results): int {
        if (empty($results)) return 0;
        $days = [];
        foreach ($results as $r) {
            $d = substr((string)($r['completed_at'] ?? ''), 0, 10);
            if ($d) $days[$d] = true;
        }
        $uniqueDays = array_keys($days);
        sort($uniqueDays);
        $best = 0; $cur = 0; $prev = null;
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
        $userBadges = $this->loadUserBadges();
        $stats = $this->getUserStats($userId);

        if (!isset($userBadges[$userId])) $userBadges[$userId] = [];
        $awardedNow = [];

        foreach ($badges as $badge) {
            $key = $badge['key'];
            $levels = $badge['levels'];
            $currentLevel = (int)($userBadges[$userId][$key]['level'] ?? 0);
            $progress = $this->getProgressForBadge($badge, $stats);

            $newLevel = $currentLevel;
            foreach ($levels as $i => $threshold) {
                $levelIndex = $i + 1;
                if ($progress >= (int)$threshold) {
                    $newLevel = max($newLevel, $levelIndex);
                }
            }

            if ($newLevel > $currentLevel) {
                $userBadges[$userId][$key] = [
                    'level' => $newLevel,
                    'awarded_at' => date('Y-m-d H:i:s')
                ];
                $awardedNow[] = [
                    'key' => $key,
                    'name' => $badge['name'],
                    'icon' => $badge['icon'],
                    'level' => $newLevel,
                ];
            }
        }

        if (!empty($awardedNow)) {
            $this->saveUserBadges($userBadges);
        }

        return $awardedNow;
    }

    private function getProgressForBadge(array $badge, array $stats): int|float {
        $metric = $badge['metric'];
        return (int)($stats[$metric] ?? 0);
    }
}
?>

