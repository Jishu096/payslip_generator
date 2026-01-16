<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Accountant';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-calculator"></i> Accountant Portal</h3>
        <p>Financial Management</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>accountant/accountant_dashboard.php" class="<?php echo $currentPage === 'accountant_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/payroll_management.php" class="<?php echo $currentPage === 'payroll_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Payroll Management</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/payroll.php" class="<?php echo $currentPage === 'payroll.php' ? 'active' : ''; ?>">
            <i class="fas fa-calculator"></i>
            <span>Process Payroll</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/generate_payslip.php" class="<?php echo $currentPage === 'generate_payslip.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Generate Payslip</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/payslips.php" class="<?php echo $currentPage === 'payslips.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice"></i>
            <span>View Payslips</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/salary_structure.php" class="<?php echo $currentPage === 'salary_structure.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-bill-wave"></i>
            <span>Salary Structure</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/manage_salary_config.php" class="<?php echo $currentPage === 'manage_salary_config.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Salary Configuration</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/generate_attendance_statement.php" class="<?php echo $currentPage === 'generate_attendance_statement.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-excel"></i>
            <span>Attendance Statement</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/manage_statement_officials.php" class="<?php echo $currentPage === 'manage_statement_officials.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Statement Officials</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/financial_reports.php" class="<?php echo $currentPage === 'financial_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Financial Reports</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/bank_file.php" class="<?php echo $currentPage === 'bank_file.php' ? 'active' : ''; ?>">
            <i class="fas fa-university"></i>
            <span>Bank File</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <i class="fas fa-user-circle"></i>
            <span><?php echo htmlspecialchars($username); ?></span>
        </div>
        <a href="<?php echo $baseURL; ?>auth/login.php?logout=1" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>
