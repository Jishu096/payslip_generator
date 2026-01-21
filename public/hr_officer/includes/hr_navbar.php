<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Accountant';
?>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <h3><i class="fas fa-user-tie"></i> HR Officer Portal</h3>
        <p>Attendance & Leave Management</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>hr_officer/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>hr_officer/verify_attendance.php" class="<?php echo $currentPage === 'verify_attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-check-circle"></i>
            <span>Verify Attendance</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>hr_officer/leave_management.php" class="<?php echo $currentPage === 'leave_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-plane-departure"></i>
            <span>Leave Management</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>hr_officer/manual_entry.php" class="<?php echo $currentPage === 'manual_entry.php' ? 'active' : ''; ?>">
            <i class="fas fa-edit"></i>
            <span>Manual Entry</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>hr_officer/employee_records.php" class="<?php echo $currentPage === 'employee_records.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Employee Records</span>
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
