<?php
/**
 * Rozetler Sayfası: Tüm rozetlerin tanıtımı ve kriterler
 */

require_once '../auth.php';
require_once '../config.php';
require_once '../Badges.php';

$auth = Auth::getInstance();

// Öğrenci veya superadmin erişimi
if (!$auth->hasRole('student') && !$auth->hasRole('superadmin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getUser();

$badgesCore = new Badges();
$badgeDefs = $badgesCore->loadBadges();

// Kullanıcının mevcut rozetleri
$userBadgesAll = [];
if (file_exists('../data/user_badges.json')) {
    $userBadgesAll = json_decode(file_get_contents('../data/user_badges.json'), true) ?? [];
}
$userId = $user['username'] ?? $user['name'] ?? 'unknown';
$myBadges = $userBadgesAll[$userId] ?? [];

// Ölçüt etiket ve açıklamaları (TR)
$metricLabels = [
    'total_sessions' => 'Toplam Alıştırma',
    'total_questions' => 'Çözülen Soru',
    'high_score_sessions' => '90+ Puanlı Alıştırma',
    'best_streak' => 'Ardışık Gün',
    'long_sessions' => '30+ dk Oturum',
    'distinct_categories' => 'Farklı Konu',
    'distinct_banks' => 'Farklı Banka',
    // 'hard_success' kaldırıldı
    'session_best_questions' => 'Tek Oturumda En Çok Soru',
    'progressive_improvement' => 'Üst Üste Gelişim'
];

$metricDescriptions = [
    'total_sessions' => 'Tamamladığınız alıştırma sayısı.',
    'total_questions' => 'Toplam çözdüğünüz soru adedi.',
    'high_score_sessions' => '%90 ve üzeri puan aldığınız alıştırma sayısı.',
    'best_streak' => 'Giriş yapıp çalışma yaptığınız ardışık gün sayısı.',
    'long_sessions' => 'En az 30 dakika süren çalışma oturumu sayısı.',
    'distinct_categories' => 'Alıştırma yaptığınız farklı konu sayısı.',
    'distinct_banks' => 'Çalıştığınız farklı soru bankası sayısı.',
    'session_best_questions' => 'Bir çalışma oturumunda çözdüğünüz en yüksek soru adedi.',
    'progressive_improvement' => 'Aynı konuda üst üste puanınızı artırdığınız kez sayısı.'
];
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rozetler - Bir Soru Bir Sevap</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #068567;
            --primary-dark: #055a4a;
            --primary-light: #089b76;
            --secondary: #f8f9fa;
            --dark: #2c3e50;
            --gray: #64748b;
            --white: #ffffff;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --radius: 16px;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            color: var(--dark);
        }

        .header { background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); color: var(--white); padding: 1.5rem 0; box-shadow: var(--shadow-lg); }
        .header-content { max-width: 1200px; margin: 0 auto; padding: 0 2rem; display: flex; justify-content: space-between; align-items: center; }
        .logo { display: flex; align-items: center; gap: 1rem; }
        .logo img { height: 3rem; width: auto; }
        .user-info { display: flex; align-items: center; gap: 0.75rem; }
        .user-info > div { max-width: 45vw; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .user-avatar { width: 2.5rem; height: 2.5rem; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 600; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .back-btn { display: inline-flex; align-items: center; gap: .5rem; color: var(--primary); text-decoration: none; margin-bottom: 1.5rem; font-weight: 600; }

        .page-card { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); padding: 2rem; margin-bottom: 1.5rem; }
        .page-title { font-size: 1.75rem; font-weight: 800; margin-bottom: .5rem; }
        .page-sub { color: var(--gray); }

        .badge-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(260px, 1fr)); gap: 1rem; }
        .badge-card { background: var(--secondary); border-radius: 14px; padding: 1.25rem; box-shadow: var(--shadow); display: flex; gap: 1rem; align-items: flex-start; }
        .badge-icon { width: 3rem; height: 3rem; display: flex; align-items: center; justify-content: center; border-radius: 10px; background: #e8faf5; color: var(--primary); font-size: 1.4rem; }
        .badge-title { font-weight: 800; margin-bottom: .25rem; display: flex; align-items: center; gap: .5rem; }
        .info-icon { color: var(--gray); cursor: help; }
        .tooltip { position: relative; display: inline-block; }
        .tooltip .tooltip-text { visibility: hidden; opacity: 0; transition: opacity .2s ease; position: absolute; bottom: 125%; left: 50%; transform: translateX(-50%); background: #111827; color: #fff; padding: .5rem .75rem; border-radius: .5rem; white-space: nowrap; font-size: .85rem; box-shadow: var(--shadow-lg); }
        .tooltip:hover .tooltip-text { visibility: visible; opacity: 1; }
        .badge-meta { color: var(--gray); font-size: .9rem; margin-top: .25rem; }
        .levels { display: flex; gap: .5rem; margin-top: .5rem; }
        .level { background: #fff; border: 1px solid #e9ecef; border-radius: 10px; padding: .35rem .5rem; font-size: .85rem; }
        .level.active { border-color: var(--primary); color: var(--primary); font-weight: 700; }

        /* Timeline */
        .timeline { position: relative; margin: 2rem 0; }
        .timeline::before { content: ''; position: absolute; left: 18px; top: 0; bottom: 0; width: 2px; background: #e2e8f0; }
        .timeline-item { position: relative; display: flex; gap: 1rem; margin-bottom: 1rem; }
        .timeline-marker { position: relative; z-index: 1; width: 36px; height: 36px; border-radius: 50%; background: #e8faf5; color: var(--primary); display: flex; align-items: center; justify-content: center; box-shadow: var(--shadow); }
        .timeline-card { flex: 1; background: var(--white); border-radius: 12px; box-shadow: var(--shadow); padding: 1rem; }
        .timeline-title { font-weight: 800; margin-bottom: .25rem; }
        .timeline-sub { color: var(--gray); font-size: .9rem; }
        .progressbar { height: 8px; background: #e9ecef; border-radius: 6px; overflow: hidden; margin-top: .5rem; }
        .progressbar > span { display: block; height: 100%; background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%); width: 0; transition: width .6s ease; }

        @media (max-width: 768px) { .container { padding: 1rem; } }
        @media (max-width: 768px) {
            .badge-grid { grid-template-columns: 1fr; }
            .badge-card { padding: 1rem; }
            .header-content { flex-direction: column; gap: 0.75rem; text-align: center; }
            .logo img { height: 2.25rem; }
        }
    </style>
    <script>
        function metricToText(metric) {
            const map = {
                total_sessions: 'Toplam Alıştırma',
                total_questions: 'Çözülen Soru',
                high_score_sessions: '90+ Puanlı Alıştırma',
                best_streak: 'Ardışık Gün',
                long_sessions: '30+ dk Oturum',
                distinct_categories: 'Farklı Konu',
                distinct_banks: 'Farklı Banka',
                hard_success: 'Zor Sorularda Başarı',
                progressive_improvement: 'Üst Üste Gelişim'
            };
            return map[metric] || metric;
        }
    </script>
</head>
<body>
    <div class="header">
        <div class="header-content">
            <div class="logo">
                <img src="../logo.png" alt="Logo">
                <div>
                    <h1>Bir Soru Bir Sevap</h1>
                    <p id="pageTitle">Rozetler</p>
                </div>
            </div>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($user['name'], 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600;"><?php echo htmlspecialchars($user['name']); ?></div>
                    <div style="font-size: 0.875rem; opacity: 0.8;" id="userRole">Öğrenci</div>
                </div>
                <button id="langToggle" class="back-btn" style="margin-right: 0.5rem; background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 8px 16px; border-radius: 20px; text-decoration: none; transition: all 0.3s ease; cursor: pointer;">DE</button>
                <a href="dashboard.php" class="back-btn" style="color: #fff; text-decoration: none;" id="btnDashboard">
                    ← Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="container">
        <a href="dashboard.php" class="back-btn" id="btnBackToDashboard">← Dashboard'a Dön</a>

        <div class="page-card">
            <div class="page-title" id="mainTitle">🏅 Tüm Rozetler</div>
            <div class="page-sub" id="mainSubtitle">Rozetlerin açıklamalarını, seviyelerini ve kazanım koşullarını burada bulabilirsiniz.</div>
        </div>

        <?php
            // Timeline: önce kazanılan rozetleri tarihe göre sırala, sonra kazanılmayanlar
            $awarded = [];
            $locked = [];
            foreach ($badgeDefs as $bd) {
                $key = $bd['key'];
                if (isset($myBadges[$key])) {
                    $awarded[] = $bd + ['_awarded_at' => $myBadges[$key]['awarded_at'] ?? null, '_level' => (int)($myBadges[$key]['level'] ?? 0)];
                } else {
                    $locked[] = $bd;
                }
            }
            usort($awarded, function($a, $b) { return strcmp($b['_awarded_at'] ?? '', $a['_awarded_at'] ?? ''); });
        ?>

        <div class="page-card">
            <div class="page-title" id="awardedTitle">🎖 Kazanılan Rozetler (Zaman Çizelgesi)</div>
            <div class="timeline">
                <?php if (empty($awarded)): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"><i class="fas fa-award"></i></div>
                        <div class="timeline-card">
                            <div class="timeline-title" id="noBadgesTitle">Henüz rozet yok</div>
                            <div class="timeline-sub" id="noBadgesDesc">Alıştırma yaptıkça rozetler burada görünecek.</div>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($awarded as $badge): ?>
                    <div class="timeline-item">
                        <div class="timeline-marker"><i class="fas <?php echo htmlspecialchars($badge['icon']); ?>"></i></div>
                        <div class="timeline-card">
                            <div class="timeline-title"><?php echo htmlspecialchars($badge['name']); ?> • <span id="levelText">Seviye</span> <?php echo (int)$badge['_level']; ?></div>
                            <div class="timeline-sub"><span id="earnedText">Kazanım:</span> <?php echo htmlspecialchars($badge['_awarded_at'] ?? '—'); ?> • <span id="criteriaText">Ölçüt:</span> <?php echo htmlspecialchars($metricLabels[$badge['metric']] ?? $badge['metric']); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="page-card">
            <div class="page-title" id="lockedTitle">🔓 Henüz Kazanılmayan Rozetler</div>
            <div class="badge-grid">
            <?php foreach ($locked as $badge): 
                $key = $badge['key'];
                $levels = $badge['levels'] ?? [];
                $metric = $badge['metric'] ?? '';
                $my = $myBadges[$key] ?? null;
                $myLevel = (int)($my['level'] ?? 0);
                $awardedAt = $my['awarded_at'] ?? null;
            ?>
            <div class="badge-card">
                <div class="badge-icon"><i class="fas <?php echo htmlspecialchars($badge['icon']); ?>"></i></div>
                <div>
                    <div class="badge-title">
                        <?php echo htmlspecialchars($badge['name']); ?>
                        <span class="tooltip info-icon"><i class="fas fa-info-circle"></i>
                            <span class="tooltip-text"><?php echo htmlspecialchars($metricDescriptions[$metric] ?? ''); ?></span>
                        </span>
                    </div>
                    <div class="badge-meta"><span id="criteriaLabel">Ölçüt:</span> <strong><?php echo htmlspecialchars($metricLabels[$metric] ?? $metric); ?></strong> • <span id="notEarnedText">Henüz kazanılmadı</span></div>
                    <div class="levels">
                        <?php for ($i = 0; $i < count($levels); $i++): $lvl = $i + 1; ?>
                            <div class="level <?php echo ($myLevel >= $lvl ? 'active' : ''); ?>">
                                <span id="levelLabel">Seviye</span> <?php echo $lvl; ?>: ≥ <?php echo (int)$levels[$i]; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                    <?php 
                        // Basit ilerleme çubuğu (mevcut değeri istatistiklerden tahmini hesapla)
                        $stats = $badgesCore->getUserStats($userId);
                        $current = (int)($stats[$metric] ?? 0);
                        $nextThreshold = 0;
                        foreach ($levels as $th) { if ($current < (int)$th) { $nextThreshold = (int)$th; break; } }
                        if ($nextThreshold === 0 && !empty($levels)) { $nextThreshold = (int)end($levels); }
                        $percent = $nextThreshold > 0 ? min(100, round(($current / $nextThreshold) * 100)) : 0;
                    ?>
                    <div class="progressbar"><span style="width: <?php echo $percent; ?>%"></span></div>
                    <div class="badge-meta"><span id="progressLabel">İlerleme:</span> <?php echo $current; ?> / <?php echo $nextThreshold; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Kapsamlı TR/DE dil desteği
        (function(){
            const tr = {
                pageTitle:'Rozetler', userRole:'Öğrenci', dashboard:'← Dashboard', backToDashboard:'← Dashboard\'a Dön',
                mainTitle:'🏅 Tüm Rozetler', mainSubtitle:'Rozetlerin açıklamalarını, seviyelerini ve kazanım koşullarını burada bulabilirsiniz.',
                awardedTitle:'🎖 Kazanılan Rozetler (Zaman Çizelgesi)', noBadgesTitle:'Henüz rozet yok',
                noBadgesDesc:'Alıştırma yaptıkça rozetler burada görünecek.', levelText:'Seviye', earnedText:'Kazanım:',
                criteriaText:'Ölçüt:', lockedTitle:'🔓 Henüz Kazanılmayan Rozetler', criteriaLabel:'Ölçüt:',
                notEarnedText:'Henüz kazanılmadı', levelLabel:'Seviye', progressLabel:'İlerleme:'
            };
            const de = {
                pageTitle:'Abzeichen', userRole:'Schüler', dashboard:'← Dashboard', backToDashboard:'← Zum Dashboard',
                mainTitle:'🏅 Alle Abzeichen', mainSubtitle:'Hier finden Sie Beschreibungen, Stufen und Bedingungen für den Erwerb von Abzeichen.',
                awardedTitle:'🎖 Verdiente Abzeichen (Zeitlinie)', noBadgesTitle:'Noch keine Abzeichen',
                noBadgesDesc:'Abzeichen werden hier angezeigt, wenn Sie üben.', levelText:'Stufe', earnedText:'Erworben:',
                criteriaText:'Kriterium:', lockedTitle:'🔓 Noch nicht verdiente Abzeichen', criteriaLabel:'Kriterium:',
                notEarnedText:'Noch nicht verdient', levelLabel:'Stufe', progressLabel:'Fortschritt:'
            };
            
            function setText(sel, text){ const el=document.querySelector(sel); if(el) el.innerText=text; }
            function setHTML(sel, html){ const el=document.querySelector(sel); if(el) el.innerHTML=html; }
            
            function apply(lang){
                const d = lang==='de'?de:tr;
                setText('#pageTitle', d.pageTitle);
                setText('#userRole', d.userRole);
                setText('#btnDashboard', d.dashboard);
                setText('#btnBackToDashboard', d.backToDashboard);
                setText('#mainTitle', d.mainTitle);
                setText('#mainSubtitle', d.mainSubtitle);
                setText('#awardedTitle', d.awardedTitle);
                setText('#noBadgesTitle', d.noBadgesTitle);
                setText('#noBadgesDesc', d.noBadgesDesc);
                setText('#levelText', d.levelText);
                setText('#earnedText', d.earnedText);
                setText('#criteriaText', d.criteriaText);
                setText('#lockedTitle', d.lockedTitle);
                setText('#criteriaLabel', d.criteriaLabel);
                setText('#notEarnedText', d.notEarnedText);
                setText('#levelLabel', d.levelLabel);
                setText('#progressLabel', d.progressLabel);
                
                const toggle=document.getElementById('langToggle');
                if(toggle) toggle.textContent = (lang==='de'?'TR':'DE');
                localStorage.setItem('lang_badges', lang);
            }
            
            document.addEventListener('DOMContentLoaded', function(){
                const lang = localStorage.getItem('lang_badges')||localStorage.getItem('lang')||'tr';
                apply(lang);
                const toggle=document.getElementById('langToggle');
                if(toggle){ 
                    toggle.addEventListener('click', function(){ 
                        const next=(localStorage.getItem('lang_badges')||localStorage.getItem('lang')||'tr')==='tr'?'de':'tr'; 
                        apply(next); 
                    }); 
                }
            });
        })();
    </script>
</body>
</html>

