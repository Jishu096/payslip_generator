<nav class="navbar">
    <div class="navbar-brand">
        <i class="fas fa-bars navbar-toggle" id="sidebarToggle"></i>
        <i class="fas fa-building"></i>
        <span><?php 
            require_once __DIR__ . '/../../../app/Config/database.php';
            $db = new Database();
            $conn = $db->connect();
            $stmt = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
            $company = $stmt->fetch(PDO::FETCH_ASSOC);
            echo htmlspecialchars($company['setting_value'] ?? 'Enterprise Payroll Solutions');
        ?></span>
    </div>
    <div class="navbar-right">
        <div class="user-info">
            <div class="user-avatar">
                <i class="fas fa-user"></i>
            </div>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
        <a href="../auth/login.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</nav>
