<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$user = Auth::getInstance()->getUser();
$initial = $user ? strtoupper(substr($user['name'], 0, 1)) : '?';
$userName = $user ? htmlspecialchars($user['name']) : 'Eğitim Başkanı';
$userBranch = $user['branch'] ?? $user['institution'] ?? '';
?>
<!-- Sidebar -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="../logo.png" alt="BSBS Logo">
        <h1>Bir Soru<br>Bir Sevap</h1>
    </div>
    
    <nav class="nav-menu">
        <a href="dashboard.php" class="nav-item <?php echo $currentPage == 'dashboard.php' ? 'active' : ''; ?>" id="navDash">
            <i class="fas fa-home"></i>
            <span>Panel</span>
        </a>
        <a href="users.php" class="nav-item <?php echo $currentPage == 'users.php' ? 'active' : ''; ?>" id="navUsers">
            <i class="fas fa-users"></i>
            <span>Kullanıcılar</span>
        </a>
        <a href="exams.php" class="nav-item <?php echo $currentPage == 'exams.php' ? 'active' : ''; ?>" id="navExams">
            <i class="fas fa-file-alt"></i>
            <span>Sınavlar</span>
        </a>
        <a href="results.php" class="nav-item <?php echo $currentPage == 'results.php' ? 'active' : ''; ?>" id="navResults">
            <i class="fas fa-chart-bar"></i>
            <span>Sonuçlar</span>
        </a>
        <a href="../index.php" class="nav-item" id="navHome">
            <i class="fas fa-external-link-alt"></i>
            <span>Siteyi Görüntüle</span>
        </a>
    </nav>

    <div class="sidebar-footer">
        <div class="user-mini">
            <div class="avatar"><?php echo $initial; ?></div>
            <div style="flex:1; overflow:hidden;">
                <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><?php echo $userName; ?></div>
                <div style="font-size:0.8rem; color:var(--text-muted);">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($userBranch); ?>
                </div>
            </div>
        </div>
        <a href="../logout.php" class="action-btn" style="justify-content:center; width:100%; color:#fca5a5; border-color:rgba(239,68,68,0.2);" id="btnLogout">
            <i class="fas fa-sign-out-alt"></i> Çıkış Yap
        </a>
    </div>
</aside>

