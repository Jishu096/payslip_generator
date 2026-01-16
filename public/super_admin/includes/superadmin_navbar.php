<nav class="navbar">
    <div class="navbar-brand">
        <i class="fas fa-bars navbar-toggle" id="sidebarToggle"></i>
        <i class="fas fa-crown"></i>
        <span>Super Admin Panel</span>
    </div>
    <div class="navbar-right">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user-shield"></i>
            </div>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
        <a href="../auth/login.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>
