<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Director';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-user-tie"></i> Director Portal</h3>
        <p>Approval & Management</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>director/director_dashboard.php" class="<?php echo $currentPage === 'director_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/salary_approvals.php" class="<?php echo $currentPage === 'salary_approvals.php' ? 'active' : ''; ?>">
            <i class="fas fa-dollar-sign"></i>
            <span>Salary Approvals</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/role_approvals.php" class="<?php echo $currentPage === 'role_approvals.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tag"></i>
            <span>Role Approvals</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/employees.php" class="<?php echo $currentPage === 'employees.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Employees</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/departments.php" class="<?php echo $currentPage === 'departments.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Departments</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
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
