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
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/approvals.php" class="<?php echo $currentPage === 'approvals.php' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i>
            <span>Approvals</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>director/reports.php" class="<?php echo $currentPage === 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Reports</span>
        </a>
    </div>
</div>
