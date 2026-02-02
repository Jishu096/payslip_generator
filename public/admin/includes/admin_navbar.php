<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Administrator';

// Load company settings for branding
require_once __DIR__ . '/../../../app/Config/database.php';
$dbNav = new Database();
$connNav = $dbNav->connect();
$companyName = 'NIELIT e-HRMS';
$companyLogo = 'e-HRMS logo.png';
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
            <i class="fas fa-user-shield sidebar-icon"></i>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <p>Admin Portal</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>admin/admin_dashboard.php" class="<?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/attendance_finalize.php" class="<?php echo $currentPage === 'attendance_finalize.php' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i>
            <span>Finalize Attendance</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>admin/attendance_export.php" class="<?php echo $currentPage === 'attendance_export.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-export"></i>
            <span>Export Attendance</span>
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
        
        <a href="<?php echo $baseURL; ?>admin/leave_approvals.php" class="<?php echo $currentPage === 'leave_approvals.php' ? 'active' : ''; ?>">
            <i class="fas fa-calendar-check"></i>
            <span>Leave Approvals</span>
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

<script>
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    sidebar.classList.toggle('active');
    overlay.classList.toggle('active');
}

// Close sidebar when clicking menu item on mobile
if (window.innerWidth <= 768) {
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
        });
    });
}
</script>
