<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Director';

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
            <i class="fas fa-user-tie sidebar-icon"></i>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <p>Director Portal</p>
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
