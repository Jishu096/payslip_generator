<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['employee_name'] ?? $_SESSION['username'] ?? 'Employee';
?>

<div class="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-id-badge"></i> Employee Portal</h3>
        <p>My Workspace</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>employee/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/view_payslips.php" class="<?php echo $currentPage === 'view_payslips.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice"></i>
            <span>My Payslips</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/attendance.php" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>My Attendance</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/leave_management.php" class="<?php echo $currentPage === 'leave_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-plane-departure"></i>
            <span>Leave Requests</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/employee_profile.php" class="<?php echo $currentPage === 'employee_profile.php' || $currentPage === 'edit_profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user"></i>
            <span>My Profile</span>
        </a>
    </div>
</div>
