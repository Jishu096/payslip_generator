<nav class="navbar">
    <div class="navbar-brand">
        <div class="navbar-toggle" onclick="document.querySelector('.sidebar').classList.toggle('active')" style="margin-right: 15px;">
            <i class="fas fa-bars"></i>
        </div>
        <i class="fas fa-user-tie"></i>
        <span>Director Portal</span>
    </div>
    <div class="navbar-right">
        <div class="user-info">
            <span><?= htmlspecialchars($_SESSION['username'] ?? 'Director') ?></span>
            <div class="user-avatar"><?= strtoupper(substr($_SESSION['username'] ?? 'D', 0, 1)) ?></div>
        </div>
        <a href="../auth/logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
</nav>
