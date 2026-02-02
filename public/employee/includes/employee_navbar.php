<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['employee_name'] ?? $_SESSION['username'] ?? 'Employee';

// Load company settings for branding
require_once __DIR__ . '/../../../app/Config/database.php';
$dbNav = new Database();
$connNav = $dbNav->connect();
$companyName = 'NIELIT e-HRMS';
$companyLogo = 'NIELIT-Preview.png';
try {
    $settingsQuery = $connNav->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_logo')");
    while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'company_name') $companyName = $row['setting_value'];
        if ($row['setting_key'] === 'company_logo') $companyLogo = $row['setting_value'];
    }
} catch (Exception $e) { /* Use defaults */ }
?>

<!-- Mobile Menu Toggle -->
<button class="mobile-menu-toggle" onclick="toggleSidebar()">
    <i class="fas fa-bars"></i>
</button>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($companyLogo && file_exists(__DIR__ . '/../../assets/images/' . $companyLogo)): ?>
            <img src="<?php echo $baseURL; ?>assets/images/<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo" class="sidebar-logo">
        <?php else: ?>
            <i class="fas fa-user sidebar-icon"></i>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <p>Employee Portal</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>employee/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <div style="margin: 20px 15px 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
            Payroll & Compensation
        </div>
        
        <a href="<?php echo $baseURL; ?>employee/view_payslips.php" class="<?php echo $currentPage === 'view_payslips.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>My Payslips</span>
        </a>
        
        <div style="margin: 20px 15px 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
            Attendance & Leave
        </div>
        
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
        
        <div style="margin: 20px 15px 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
            My Account
        </div>
        
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
