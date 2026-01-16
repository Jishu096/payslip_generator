<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Super Admin';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-crown"></i> Super Admin Portal</h3>
        <p>System Protection & Control</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>super_admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div style="margin: 20px 15px 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
            System Protection
        </div>
        
        <a href="<?php echo $baseURL; ?>super_admin/users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Users</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>super_admin/roles.php" class="<?php echo $currentPage === 'roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>Roles</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>super_admin/security.php" class="<?php echo $currentPage === 'security.php' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i>
            <span>Security</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
        <a href="<?php echo $baseURL; ?>index.php?page=logout" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
