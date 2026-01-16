<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Administrator';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-user-shield"></i> Admin Portal</h3>
        <p>System Management</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>admin/admin_dashboard.php" class="<?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/upload_attendance.php" class="<?php echo $currentPage === 'upload_attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-upload"></i>
            <span>Upload Attendance</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/employees.php" class="<?php echo $currentPage === 'employees.php' || $currentPage === 'add_employee.php' || $currentPage === 'edit_employee.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Employees</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/departments.php" class="<?php echo $currentPage === 'departments.php' || $currentPage === 'add_department.php' || $currentPage === 'edit_department.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Departments</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/holidays_nielit.php" class="<?php echo $currentPage === 'holidays_nielit.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Holiday Calendar</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/create_user.php" class="<?php echo $currentPage === 'create_user.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>Create User</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/manage_users.php" class="<?php echo $currentPage === 'manage_users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users-cog"></i>
            <span>Manage Users</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/manage_user_roles.php" class="<?php echo $currentPage === 'manage_user_roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>User Roles</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
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
