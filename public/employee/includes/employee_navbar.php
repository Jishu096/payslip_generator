<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['employee_name'] ?? $_SESSION['username'] ?? 'Employee';
?>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-user"></i> Employee Portal</h3>
        <p>Self Service & Records</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>employee/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/view_payslips.php" class="<?php echo $currentPage === 'view_payslips.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>My Payslips</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/attendance.php" class="<?php echo $currentPage === 'attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>My Attendance</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/attendance_calendar.php" class="<?php echo $currentPage === 'attendance_calendar.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-alt"></i>
            <span>Attendance Calendar</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/leave_management.php" class="<?php echo $currentPage === 'leave_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-umbrella-beach"></i>
            <span>Leave Management</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>employee/employee_profile.php" class="<?php echo $currentPage === 'employee_profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>My Profile</span>
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

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

if (window.innerWidth <= 768) {
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
        });
    });
}
</script>
