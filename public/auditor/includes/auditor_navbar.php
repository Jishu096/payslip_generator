<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    .navbar {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        height: 70px;
        background: white;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        z-index: 999;
    }

    .navbar-brand {
        font-size: 24px;
        font-weight: 700;
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .navbar-user {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-info {
        text-align: right;
    }

    .user-name {
        font-weight: 600;
        color: #1e293b;
        font-size: 14px;
    }

    .user-role {
        font-size: 12px;
        color: #64748b;
    }

    .logout-btn {
        padding: 8px 20px;
        background: linear-gradient(135deg, #8b5cf6, #6d28d9);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 500;
        text-decoration: none;
        display: inline-block;
        transition: all 0.3s;
    }

    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
    }

    body {
        padding-top: 70px;
    }
</style>

<nav class="navbar">
    <div class="navbar-brand">
        <i class="fas fa-shield-alt"></i>
        <span>Auditor Portal</span>
    </div>
    <div class="navbar-user">
        <div class="user-info">
            <div class="user-name"><?php echo htmlspecialchars($username); ?></div>
            <div class="user-role">Auditor</div>
        </div>
        <a href="<?php echo $baseURL; ?>auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i> Logout
        </a>
    </div>
</nav>
