<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Auditor';

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
            <i class="fas fa-shield-alt sidebar-icon"></i>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <p>Auditor Portal</p>
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
