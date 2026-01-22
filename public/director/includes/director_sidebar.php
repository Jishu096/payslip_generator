<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Director';
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <i class="fas fa-user-tie"></i>
        </div>
        <h3>Director Portal</h3>
        <p>Management & Oversight</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>director/director_dashboard.php" class="menu-item <?php echo $currentPage === 'director_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <div class="menu-divider">Approvals</div>
        
        <a href="<?php echo $baseURL; ?>director/salary_approvals.php" class="menu-item <?php echo $currentPage === 'salary_approvals.php' ? 'active' : ''; ?>">
            <i class="fas fa-dollar-sign"></i>
            <span>Salary Changes</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/role_approvals.php" class="menu-item <?php echo $currentPage === 'role_approvals.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>Role Changes</span>
        </a>
        
        <div class="menu-divider">Management</div>
        
        <a href="<?php echo $baseURL; ?>director/departments.php" class="menu-item <?php echo $currentPage === 'departments.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Departments</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/employees.php" class="menu-item <?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Employees</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/reports.php" class="menu-item <?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <a href="<?php echo $baseURL; ?>auth/logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
