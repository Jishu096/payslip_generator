<?php
$baseURL = "/payslip_generator/public/";
$username = $_SESSION['username'] ?? 'Director';
?>

<style>
    .top-navbar {
        background: white;
        height: 70px;
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        z-index: 999;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .nav-welcome {
        font-size: 18px;
        font-weight: 600;
        color: var(--text);
    }

    .nav-user {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .nav-user-info {
        text-align: right;
    }

    .nav-user-name {
        font-weight: 600;
        color: var(--text);
        font-size: 14px;
    }

    .nav-user-role {
        font-size: 12px;
        color: var(--muted);
    }

    .logout-btn {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logout-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .main-content {
        margin-top: 70px;
    }

    @media (max-width: 768px) {
        .top-navbar {
            left: 0;
        }
    }
</style>

<div class="top-navbar">
    <div class="nav-welcome">
        Welcome back, <span style="color: var(--accent);"><?php echo htmlspecialchars($username); ?></span>
    </div>
    
    <div class="nav-user">
        <div class="nav-user-info">
            <div class="nav-user-name"><?php echo htmlspecialchars($username); ?></div>
            <div class="nav-user-role">Director</div>
        </div>
        <a href="<?php echo $baseURL; ?>auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            Logout
        </a>
    </div>
</div>
