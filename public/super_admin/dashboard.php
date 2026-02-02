<?php
session_start();

// Check if user is Super Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/PermissionHelper.php';

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

// Verify super admin permission
if (!$perm->hasPermission('system.backup')) {
    header("Location: ../auth/login.php?error=unauthorized");
    exit;
}

$username = $_SESSION['username'] ?? 'Super Admin';

// Fetch dashboard statistics with error handling
$stats = [];

try {
    // Total users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL OR deleted_at IS NULL");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Active users
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
    $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Inactive users
    $stats['inactive_users'] = $stats['total_users'] - $stats['active_users'];

    // Active roles
    $stmt = $conn->query("SELECT COUNT(*) as count FROM roles WHERE is_active = 1");
    $stats['active_roles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total permissions
    $stmt = $conn->query("SELECT COUNT(*) as count FROM permissions");
    $stats['total_permissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Audit log count (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM audit_log WHERE log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_audits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Audit log count (today)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM audit_log WHERE DATE(log_time) = CURDATE()");
    $stats['today_audits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Failed logins (last 24 hours)
    $stmt = $conn->query("SELECT COUNT(*) as count FROM login_attempts WHERE success = 0 AND attempt_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $stats['failed_logins'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0;

    // Role distribution
    $stmt = $conn->query("
        SELECT 
            r.display_name,
            r.role_name,
            COUNT(urn.user_id) as user_count
        FROM roles r
        LEFT JOIN user_roles_new urn ON r.role_id = urn.role_id
        WHERE r.is_active = 1
        GROUP BY r.role_id, r.display_name, r.role_name
        ORDER BY user_count DESC
    ");
    $role_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent audit logs
    $stmt = $conn->query("
        SELECT 
            al.log_id,
            al.user_id,
            u.username,
            al.action,
            al.log_time
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.log_time DESC
        LIMIT 8
    ");
    $recent_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent users
    $stmt = $conn->query("
        SELECT user_id, username, email, role, is_active, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $recent_users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $stats['total_users'] = 0;
    $stats['active_users'] = 0;
    $stats['inactive_users'] = 0;
    $stats['active_roles'] = 0;
    $stats['total_permissions'] = 0;
    $stats['recent_audits'] = 0;
    $stats['today_audits'] = 0;
    $stats['failed_logins'] = 0;
    $role_distribution = [];
    $recent_logs = [];
    $recent_users = [];
    error_log("Dashboard query error: " . $e->getMessage());
}

// Get role icon based on role name
function getRoleIcon($roleName) {
    $icons = [
        'super_admin' => 'fa-crown',
        'administrator' => 'fa-user-shield',
        'accountant' => 'fa-calculator',
        'director' => 'fa-briefcase',
        'employee' => 'fa-user',
        'hr_officer' => 'fa-users-cog',
        'auditor' => 'fa-search-dollar'
    ];
    return $icons[$roleName] ?? 'fa-user-tag';
}

// Get action badge class
function getActionBadge($action) {
    if (strpos($action, 'create') !== false || strpos($action, 'add') !== false) {
        return 'badge-success';
    } elseif (strpos($action, 'update') !== false || strpos($action, 'edit') !== false) {
        return 'badge-info';
    } elseif (strpos($action, 'delete') !== false || strpos($action, 'remove') !== false) {
        return 'badge-danger';
    } elseif (strpos($action, 'login') !== false) {
        return 'badge-warning';
    } else {
        return 'badge-secondary';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - eHRMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/superadmin_styles.php'; ?>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .page-header::after {
            content: '';
            position: absolute;
            bottom: -60%;
            right: 20%;
            width: 200px;
            height: 200px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 15px;
            margin: 0;
        }

        .system-status {
            text-align: right;
            position: relative;
            z-index: 1;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            padding: 12px 20px;
            border-radius: 12px;
            font-weight: 600;
        }

        .status-dot {
            width: 10px;
            height: 10px;
            background: #10b981;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.1); }
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .stat-card .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 16px;
        }

        .stat-card.users .stat-icon {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
            color: #3b82f6;
        }

        .stat-card.roles .stat-icon {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #10b981;
        }

        .stat-card.logs .stat-icon {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
            color: #f59e0b;
        }

        .stat-card.security .stat-icon {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: #ef4444;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 12px;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            padding-top: 12px;
            border-top: 1px solid #f1f5f9;
        }

        .stat-trend.up {
            color: #10b981;
        }

        .stat-trend.down {
            color: #ef4444;
        }

        .stat-trend.neutral {
            color: #64748b;
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 20px;
            background: white;
            border-radius: 16px;
            text-decoration: none;
            color: #1e293b;
            font-weight: 600;
            font-size: 14px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .action-btn:hover {
            border-color: #667eea;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.2);
        }

        .action-btn .action-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .action-btn.users .action-icon {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .action-btn.roles .action-icon {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .action-btn.security .action-icon {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .action-btn.settings .action-icon {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: #667eea;
        }

        .view-all {
            font-size: 13px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: gap 0.3s;
        }

        .view-all:hover {
            gap: 8px;
        }

        .card-body {
            padding: 20px 25px;
        }

        /* Role Distribution */
        .role-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .role-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .role-item:last-child {
            border-bottom: none;
        }

        .role-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .role-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 16px;
        }

        .role-name {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }

        .role-count {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Activity Feed */
        .activity-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .activity-item {
            display: flex;
            gap: 14px;
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
            flex-shrink: 0;
        }

        .activity-content {
            flex: 1;
            min-width: 0;
        }

        .activity-text {
            font-size: 13px;
            color: #1e293b;
            margin-bottom: 4px;
        }

        .activity-text strong {
            color: #667eea;
        }

        .activity-time {
            font-size: 12px;
            color: #94a3b8;
        }

        .activity-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-success {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-info {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-danger {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .badge-warning {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .badge-secondary {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
        }

        /* System Health */
        .health-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .health-item {
            background: #f8fafc;
            padding: 18px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: all 0.3s;
        }

        .health-item:hover {
            background: #f1f5f9;
        }

        .health-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .health-icon.success {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
        }

        .health-icon.warning {
            background: rgba(245, 158, 11, 0.15);
            color: #f59e0b;
        }

        .health-icon.danger {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .health-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .health-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        /* Recent Users Table */
        .users-mini-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-mini-table th {
            text-align: left;
            padding: 12px 0;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #f1f5f9;
        }

        .users-mini-table td {
            padding: 14px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .users-mini-table tr:last-child td {
            border-bottom: none;
        }

        .user-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge-small {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge-small.active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-badge-small.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        /* Full Width Card */
        .full-width {
            grid-column: 1 / -1;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .quick-actions {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            .system-status {
                text-align: center;
            }
            .health-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/superadmin_navbar.php'; ?>

    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-crown"></i> Super Admin Dashboard</h1>
                <p>System protection, security management & monitoring</p>
            </div>
            <div class="system-status">
                <div class="status-badge">
                    <span class="status-dot"></span>
                    All Systems Operational
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card users">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-label">Total Users</div>
                <div class="stat-trend up">
                    <i class="fas fa-circle"></i>
                    <?php echo $stats['active_users']; ?> active, <?php echo $stats['inactive_users']; ?> inactive
                </div>
            </div>

            <div class="stat-card roles">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['active_roles']); ?></div>
                <div class="stat-label">Active Roles</div>
                <div class="stat-trend neutral">
                    <i class="fas fa-key"></i>
                    <?php echo $stats['total_permissions']; ?> permissions configured
                </div>
            </div>

            <div class="stat-card logs">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['recent_audits']); ?></div>
                <div class="stat-label">Audit Logs (7 days)</div>
                <div class="stat-trend neutral">
                    <i class="fas fa-clock"></i>
                    <?php echo $stats['today_audits']; ?> today
                </div>
            </div>

            <div class="stat-card security">
                <div class="stat-icon">
                    <i class="fas fa-shield-alt"></i>
                </div>
                <div class="stat-value"><?php echo number_format($stats['failed_logins']); ?></div>
                <div class="stat-label">Failed Logins (24h)</div>
                <div class="stat-trend <?php echo $stats['failed_logins'] > 10 ? 'down' : 'up'; ?>">
                    <i class="fas fa-<?php echo $stats['failed_logins'] > 10 ? 'exclamation-triangle' : 'check-circle'; ?>"></i>
                    <?php echo $stats['failed_logins'] > 10 ? 'Review recommended' : 'Normal activity'; ?>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="users.php" class="action-btn users">
                <div class="action-icon">
                    <i class="fas fa-users-cog"></i>
                </div>
                <span>Manage Users</span>
            </a>
            <a href="roles.php" class="action-btn roles">
                <div class="action-icon">
                    <i class="fas fa-user-tag"></i>
                </div>
                <span>Role Management</span>
            </a>
            <a href="security.php" class="action-btn security">
                <div class="action-icon">
                    <i class="fas fa-lock"></i>
                </div>
                <span>Security & Logs</span>
            </a>
            <a href="settings.php" class="action-btn settings">
                <div class="action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <span>System Settings</span>
            </a>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Role Distribution -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-chart-pie"></i>
                        Role Distribution
                    </div>
                    <a href="roles.php" class="view-all">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <ul class="role-list">
                        <?php foreach ($role_distribution as $role): ?>
                        <li class="role-item">
                            <div class="role-info">
                                <div class="role-icon">
                                    <i class="fas <?php echo getRoleIcon($role['role_name']); ?>"></i>
                                </div>
                                <span class="role-name"><?php echo htmlspecialchars($role['display_name']); ?></span>
                            </div>
                            <span class="role-count"><?php echo $role['user_count']; ?> users</span>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($role_distribution)): ?>
                        <li class="role-item">
                            <div class="role-info">
                                <span class="role-name" style="color: #94a3b8;">No roles configured</span>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Recent Activity -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-history"></i>
                        Recent Activity
                    </div>
                    <a href="security.php" class="view-all">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <ul class="activity-list">
                        <?php foreach ($recent_logs as $log): ?>
                        <li class="activity-item">
                            <div class="activity-avatar">
                                <?php echo strtoupper(substr($log['username'] ?? 'S', 0, 1)); ?>
                            </div>
                            <div class="activity-content">
                                <div class="activity-text">
                                    <strong><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></strong>
                                    <span class="activity-badge <?php echo getActionBadge($log['action']); ?>">
                                        <?php echo htmlspecialchars($log['action']); ?>
                                    </span>
                                </div>
                                <div class="activity-time">
                                    <i class="far fa-clock"></i>
                                    <?php echo date('M d, Y H:i', strtotime($log['log_time'])); ?>
                                </div>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <?php if (empty($recent_logs)): ?>
                        <li class="activity-item">
                            <div class="activity-content">
                                <div class="activity-text" style="color: #94a3b8;">No recent activity</div>
                            </div>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- System Health -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-heartbeat"></i>
                        System Health
                    </div>
                </div>
                <div class="card-body">
                    <div class="health-grid">
                        <div class="health-item">
                            <div class="health-icon success">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="health-info">
                                <h5>Database</h5>
                                <p>Connected & responding</p>
                            </div>
                        </div>
                        <div class="health-item">
                            <div class="health-icon success">
                                <i class="fas fa-user-shield"></i>
                            </div>
                            <div class="health-info">
                                <h5>RBAC System</h5>
                                <p><?php echo $stats['total_permissions']; ?> permissions active</p>
                            </div>
                        </div>
                        <div class="health-item">
                            <div class="health-icon success">
                                <i class="fas fa-file-alt"></i>
                            </div>
                            <div class="health-info">
                                <h5>Audit Logging</h5>
                                <p>Recording all events</p>
                            </div>
                        </div>
                        <div class="health-item">
                            <div class="health-icon <?php echo $stats['failed_logins'] > 10 ? 'warning' : 'success'; ?>">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="health-info">
                                <h5>Security Status</h5>
                                <p><?php echo $stats['failed_logins'] > 10 ? 'Review logins' : 'No threats detected'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Users -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-user-plus"></i>
                        Recent Users
                    </div>
                    <a href="users.php" class="view-all">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body">
                    <table class="users-mini-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_users as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-cell">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars($user['username']); ?></div>
                                            <div style="font-size: 12px; color: #94a3b8;"><?php echo htmlspecialchars($user['email'] ?? '-'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td style="text-transform: capitalize;"><?php echo htmlspecialchars($user['role']); ?></td>
                                <td>
                                    <span class="status-badge-small <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                        <i class="fas fa-circle" style="font-size: 6px;"></i>
                                        <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($recent_users)): ?>
                            <tr>
                                <td colspan="3" style="text-align: center; color: #94a3b8;">No users found</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
</body>
</html>
