<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="employees.php" class="<?php echo $current_page == 'employees.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Employees</a></li>
        <li><a href="create_user.php" class="<?php echo $current_page == 'create_user.php' ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Create User</a></li>
        <li><a href="manage_users.php" class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Manage Users</a></li>
        <li><a href="departments.php" class="<?php echo $current_page == 'departments.php' ? 'active' : ''; ?>"><i class="fas fa-building"></i> Departments</a></li>
        <li><a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
</aside>
