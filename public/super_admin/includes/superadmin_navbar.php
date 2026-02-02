<?php
$baseURL = "/payslip_generator/public/";
$currentPage = basename($_SERVER['PHP_SELF']);
$username = $_SESSION['username'] ?? 'Super Admin';

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

// Get notification counts for Super Admin
$notifications = [];
$notificationCount = 0;

try {
    // Failed login attempts (last 24 hours)
    $failedLogins = $connNav->query("SELECT COUNT(*) as count FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch(PDO::FETCH_ASSOC);
    if ($failedLogins && $failedLogins['count'] > 0) {
        $notifications[] = [
            'type' => 'warning',
            'icon' => 'fa-exclamation-triangle',
            'title' => 'Failed Login Attempts',
            'message' => $failedLogins['count'] . ' failed login attempts in last 24 hours',
            'time' => 'Security Alert'
        ];
        $notificationCount += 1;
    }
    
    // Pending role changes (if table exists)
    $pendingRoles = $connNav->query("SELECT COUNT(*) as count FROM role_change_requests WHERE status = 'pending'")->fetch(PDO::FETCH_ASSOC);
    if ($pendingRoles && $pendingRoles['count'] > 0) {
        $notifications[] = [
            'type' => 'info',
            'icon' => 'fa-user-tag',
            'title' => 'Pending Role Changes',
            'message' => $pendingRoles['count'] . ' role change requests awaiting approval',
            'time' => 'Action Required'
        ];
        $notificationCount += $pendingRoles['count'];
    }
    
    // Recent new users (last 7 days)
    $newUsers = $connNav->query("SELECT COUNT(*) as count FROM users WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetch(PDO::FETCH_ASSOC);
    if ($newUsers && $newUsers['count'] > 0) {
        $notifications[] = [
            'type' => 'success',
            'icon' => 'fa-user-plus',
            'title' => 'New Users',
            'message' => $newUsers['count'] . ' new users registered this week',
            'time' => 'Last 7 days'
        ];
    }
    
    // Check notification logs for failed emails
    $failedEmails = $connNav->query("SELECT COUNT(*) as count FROM notification_logs WHERE status = 'failed' AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)")->fetch(PDO::FETCH_ASSOC);
    if ($failedEmails && $failedEmails['count'] > 0) {
        $notifications[] = [
            'type' => 'danger',
            'icon' => 'fa-envelope-open',
            'title' => 'Email Delivery Issues',
            'message' => $failedEmails['count'] . ' emails failed to send',
            'time' => 'Last 24 hours'
        ];
        $notificationCount += 1;
    }
} catch (Exception $e) {
    // Tables might not exist yet
}

// Add default notification if none
if (empty($notifications)) {
    $notifications[] = [
        'type' => 'success',
        'icon' => 'fa-check-circle',
        'title' => 'All Systems Normal',
        'message' => 'No issues detected',
        'time' => 'Just now'
    ];
}
?>

<!-- Top Navigation Bar -->
<div class="top-navbar">
    <div class="top-navbar-left">
        <button class="mobile-menu-toggle" onclick="toggleSidebar()">
            <i class="fas fa-bars"></i>
        </button>
        <span class="page-title"><?php echo ucfirst(str_replace(['_', '.php'], [' ', ''], $currentPage)); ?></span>
    </div>
    <div class="top-navbar-right">
        <!-- Notification Bell -->
        <div class="notification-wrapper">
            <button class="notification-btn" onclick="toggleNotifications()">
                <i class="fas fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                <?php endif; ?>
            </button>
            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <h4><i class="fas fa-bell"></i> Notifications</h4>
                    <span class="notification-count"><?php echo count($notifications); ?> alerts</span>
                </div>
                <div class="notification-list">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notification-item <?php echo $notif['type']; ?>">
                            <div class="notification-icon <?php echo $notif['type']; ?>">
                                <i class="fas <?php echo $notif['icon']; ?>"></i>
                            </div>
                            <div class="notification-content">
                                <h5><?php echo htmlspecialchars($notif['title']); ?></h5>
                                <p><?php echo htmlspecialchars($notif['message']); ?></p>
                                <span class="notification-time"><?php echo htmlspecialchars($notif['time']); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <a href="<?php echo $baseURL; ?>super_admin/security.php" class="notification-footer">
                    View Security Dashboard <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
        
        <!-- User Menu -->
        <div class="user-menu">
            <span class="user-greeting">Welcome,</span>
            <span class="user-name"><?php echo htmlspecialchars($username); ?></span>
            <i class="fas fa-crown" style="color: #f59e0b; margin-left: 8px;"></i>
        </div>
    </div>
</div>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" onclick="toggleSidebar()"></div>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($companyLogo && file_exists(__DIR__ . '/../../assets/images/' . $companyLogo)): ?>
            <img src="<?php echo $baseURL; ?>assets/images/<?php echo htmlspecialchars($companyLogo); ?>" alt="Logo" class="sidebar-logo">
        <?php else: ?>
            <i class="fas fa-crown sidebar-icon"></i>
        <?php endif; ?>
        <h3><?php echo htmlspecialchars($companyName); ?></h3>
        <p>Super Admin Portal</p>
    </div>
    
    <div class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>super_admin/dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-shield-alt"></i>
            <span>Dashboard</span>
        </a>
        
        <div style="margin: 20px 15px 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
            System Protection
        </div>
        
        <a href="<?php echo $baseURL; ?>super_admin/users.php" class="<?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Users</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>super_admin/roles.php" class="<?php echo $currentPage === 'roles.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>Roles</span>
        </a>
        
        <a href="<?php echo $baseURL; ?>super_admin/security.php" class="<?php echo $currentPage === 'security.php' ? 'active' : ''; ?>">
            <i class="fas fa-lock"></i>
            <span>Security</span>
        </a>
        
        <div style="margin: 20px 15px 10px; padding-bottom: 10px; border-bottom: 1px solid rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 1px;">
            Configuration
        </div>
        
        <a href="<?php echo $baseURL; ?>super_admin/settings.php" class="<?php echo $currentPage === 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>System Settings</span>
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

function toggleNotifications() {
    const dropdown = document.getElementById('notificationDropdown');
    dropdown.classList.toggle('show');
}

// Close notifications when clicking outside
document.addEventListener('click', function(event) {
    const wrapper = document.querySelector('.notification-wrapper');
    const dropdown = document.getElementById('notificationDropdown');
    if (wrapper && !wrapper.contains(event.target)) {
        dropdown.classList.remove('show');
    }
});

if (window.innerWidth <= 768) {
    document.querySelectorAll('.sidebar-menu a').forEach(link => {
        link.addEventListener('click', () => {
            document.getElementById('sidebar').classList.remove('active');
            document.querySelector('.sidebar-overlay').classList.remove('active');
        });
    });
}
</script>
