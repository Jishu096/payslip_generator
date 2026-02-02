<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Accountant';

// Load company settings for branding
require_once __DIR__ . '/../../../app/Config/database.php';
$dbNav = new Database();
$connNav = $dbNav->connect();
$companyName = 'NIELIT e-HRMS';
$companyLogo = 'NIELIT-Preview.png';

// Check for new finalized attendance
$hasNewAttendance = false;
try {
    $settingsQuery = $connNav->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('company_name', 'company_logo')");
    while ($row = $settingsQuery->fetch(PDO::FETCH_ASSOC)) {
        if ($row['setting_key'] === 'company_name') $companyName = $row['setting_value'];
        if ($row['setting_key'] === 'company_logo') $companyLogo = $row['setting_value'];
    }
    
    // Check for unread attendance notifications
    $userId = $_SESSION['user_id'] ?? 0;
    if ($userId) {
        $notifStmt = $connNav->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'attendance_finalized' AND is_read = 0");
        $notifStmt->execute([$userId]);
        $hasNewAttendance = $notifStmt->fetchColumn() > 0;
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
            <i class="fas fa-calculator sidebar-icon"></i>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <p>Accountant Portal</p>
    </div>
    
    <div class="sidebar-menu">
        <!-- Dashboard -->
        <a href="<?php echo $baseURL; ?>accountant/accountant_dashboard.php" class="<?php echo $currentPage === 'accountant_dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        
        <!-- Stage 1: Attendance -->
        <div class="menu-section-title">
            <i class="fas fa-calendar-check"></i> Stage 1: Attendance
        </div>
        
        <a href="<?php echo $baseURL; ?>accountant/finalized_attendance.php" class="<?php echo $currentPage === 'finalized_attendance.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Finalized Attendance</span>
            <?php if ($hasNewAttendance): ?>
                <span class="menu-badge new">NEW</span>
            <?php endif; ?>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/generate_attendance_statement.php" class="<?php echo $currentPage === 'generate_attendance_statement.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-excel"></i>
            <span>Attendance Statement</span>
        </a>
        
        <!-- Stage 2 & 3: Payroll Processing -->
        <div class="menu-section-title">
            <i class="fas fa-calculator"></i> Stage 2-3: Payroll
        </div>
        
        <a href="<?php echo $baseURL; ?>accountant/salary_structure.php" class="<?php echo $currentPage === 'salary_structure.php' ? 'active' : ''; ?>">
            <i class="fas fa-wallet"></i>
            <span>Salary Structure</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/manage_salary_config.php" class="<?php echo $currentPage === 'manage_salary_config.php' ? 'active' : ''; ?>">
            <i class="fas fa-sliders-h"></i>
            <span>Salary Configuration</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/generate_payslip.php" class="<?php echo $currentPage === 'generate_payslip.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Generate Payslip</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/payslips.php" class="<?php echo $currentPage === 'payslips.php' ? 'active' : ''; ?>">
            <i class="fas fa-folder-open"></i>
            <span>View Payslips</span>
        </a>
        
        <!-- Stage 5: Disbursement -->
        <div class="menu-section-title">
            <i class="fas fa-university"></i> Stage 5: Disburse
        </div>
        
        <a href="<?php echo $baseURL; ?>accountant/bank_file.php" class="<?php echo $currentPage === 'bank_file.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-export"></i>
            <span>Generate Bank File</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>accountant/payroll_management.php" class="<?php echo $currentPage === 'payroll_management.php' ? 'active' : ''; ?>">
            <i class="fas fa-money-check-alt"></i>
            <span>Payroll Records</span>
        </a>
        
        <!-- Stage 6: Reports -->
        <div class="menu-section-title">
            <i class="fas fa-chart-pie"></i> Stage 6: Reports
        </div>
        
        <a href="<?php echo $baseURL; ?>accountant/financial_reports.php" class="<?php echo $currentPage === 'financial_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Financial Reports</span>
        </a>
        
        <!-- Settings -->
        <div class="menu-section-title">
            <i class="fas fa-cog"></i> Settings
        </div>
        
        <a href="<?php echo $baseURL; ?>accountant/manage_statement_officials.php" class="<?php echo $currentPage === 'manage_statement_officials.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-tie"></i>
            <span>Statement Officials</span>
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

<style>
.menu-section-title {
    margin: 20px 15px 10px;
    padding: 8px 10px;
    border-radius: 8px;
    background: rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.7);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.menu-section-title i {
    font-size: 12px;
    opacity: 0.8;
}

.menu-badge {
    margin-left: auto;
    padding: 3px 8px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
}

.menu-badge.new {
    background: linear-gradient(135deg, #10b981, #059669);
    color: white;
    animation: pulse-badge 2s infinite;
}

@keyframes pulse-badge {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.1); }
}

.sidebar-menu a {
    position: relative;
}
</style>

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
