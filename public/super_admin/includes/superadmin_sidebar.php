<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
        <li><a href="dashboard.php" class="<?php echo $current_page == 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i> Dashboard
        </a></li>
        
        <li style="margin-top: 20px; padding: 10px 25px; color: #a0aec0; font-size: 12px; font-weight: 600; text-transform: uppercase;">
            System Protection
        </li>
        
        <li><a href="users.php" class="<?php echo $current_page == 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i> Users
        </a></li>
        
        <li><a href="roles.php" class="<?php echo $current_page == 'roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i> Roles
        </a></li>
        
        <li><a href="security.php" class="<?php echo $current_page == 'security.php' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i> Security
        </a></li>
        
        <li><a href="backup.php" class="<?php echo $current_page == 'backup.php' ? 'active' : ''; ?>">
            <i class="fas fa-database"></i> Backup
        </a></li>
    </ul>
</aside>
