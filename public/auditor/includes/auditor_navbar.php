<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Auditor';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-shield-alt"></i> Auditor Portal</h3>
        <p>Compliance & Oversight</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>auditor/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>auditor/attendance_reports.php" class="<?php echo $currentPage === 'attendance_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i>
            <span>Attendance Reports</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>auditor/payroll_reports.php" class="<?php echo $currentPage === 'payroll_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Payroll Reports</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>auditor/approval_history.php" class="<?php echo $currentPage === 'approval_history.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Approval History</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>auditor/audit_trail.php" class="<?php echo $currentPage === 'audit_trail.php' ? 'active' : ''; ?>">
            <i class="fas fa-list-alt"></i>
            <span>Audit Trail</span>
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
