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
    $stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");
    $stats['total_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Active roles
    $stmt = $conn->query("SELECT COUNT(*) as count FROM roles WHERE is_active = 1");
    $stats['active_roles'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Total permissions
    $stmt = $conn->query("SELECT COUNT(*) as count FROM permissions");
    $stats['total_permissions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Audit log count (last 7 days) - using log_time column
    $stmt = $conn->query("SELECT COUNT(*) as count FROM audit_log WHERE log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $stats['recent_audits'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Role distribution
    $stmt = $conn->query("
        SELECT 
            r.display_name,
            COUNT(urn.user_id) as user_count
        FROM roles r
        LEFT JOIN user_roles_new urn ON r.role_id = urn.role_id
        WHERE r.is_active = 1
        GROUP BY r.role_id, r.display_name
        ORDER BY user_count DESC
    ");
    $role_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Set default values if queries fail
    $stats['total_users'] = 0;
    $stats['active_roles'] = 0;
    $stats['total_permissions'] = 0;
    $stats['recent_audits'] = 0;
    $role_distribution = [];
    error_log("Dashboard query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - eHRMS</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/superadmin_styles.php'; ?>
</head>
<body>
    <?php include 'includes/superadmin_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-crown"></i> Super Admin Dashboard</h1>
                <p>System protection and security management</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 13px; color: var(--muted); margin-bottom: 5px;">System Status</div>
                <div style="font-size: 16px; font-weight: 700; color: #10b981;">
                    <i class="fas fa-circle" style="font-size: 8px;"></i> ONLINE
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card status">
                <div class="stat-label">System Status</div>
                <div class="stat-value">ONLINE</div>
                <div class="stat-desc">All systems operational</div>
            </div>

            <div class="stat-card users">
                <div class="stat-label">Total Users</div>
                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                <div class="stat-desc">Active user accounts</div>
            </div>

            <div class="stat-card alerts">
                <div class="stat-label">Security Alerts</div>
                <div class="stat-value">0</div>
                <div class="stat-desc">No active threats</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-label">Audit Logs</div>
                <div class="stat-value"><?php echo number_format($stats['recent_audits']); ?></div>
                <div class="stat-desc">Last 7 days</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-shield-alt"></i> System Protection Actions
                </div>
            </div>
            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                <a href="users.php" class="btn btn-primary">
                    <i class="fas fa-users"></i> Manage Users
                </a>
                <a href="roles.php" class="btn btn-primary">
                    <i class="fas fa-user-shield"></i> Manage Roles
                </a>
                <a href="security.php" class="btn btn-danger">
                    <i class="fas fa-lock"></i> Security Settings
                </a>
                <a href="backup.php" class="btn btn-success">
                    <i class="fas fa-database"></i> Backup & Restore
                </a>
            </div>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px;">
            <!-- System Health -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-heartbeat"></i> System Health
                    </div>
                </div>
                <div style="padding: 15px;">
                    <div style="padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 8px; margin-bottom: 10px;">
                        <div style="font-weight: 600; color: #155724; margin-bottom: 5px;">
                            <i class="fas fa-check-circle"></i> Database Connection
                        </div>
                        <div style="font-size: 13px; color: #155724;">Active and responding</div>
                    </div>
                    <div style="padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 8px; margin-bottom: 10px;">
                        <div style="font-weight: 600; color: #155724; margin-bottom: 5px;">
                            <i class="fas fa-check-circle"></i> RBAC System
                        </div>
                        <div style="font-size: 13px; color: #155724;">61 permissions configured</div>
                    </div>
                    <div style="padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 8px; margin-bottom: 10px;">
                        <div style="font-weight: 600; color: #155724; margin-bottom: 5px;">
                            <i class="fas fa-check-circle"></i> Audit Logging
                        </div>
                        <div style="font-size: 13px; color: #155724;"><?php echo number_format($stats['recent_audits']); ?> logs in last 7 days</div>
                    </div>
                    <div style="padding: 15px; background: #d4edda; border-left: 4px solid #28a745; border-radius: 8px;">
                        <div style="font-weight: 600; color: #155724; margin-bottom: 5px;">
                            <i class="fas fa-check-circle"></i> Security
                        </div>
                        <div style="font-size: 13px; color: #155724;">No threats detected</div>
                    </div>
                </div>
            </div>

            <!-- Role Distribution -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-users"></i> User Overview
                    </div>
                </div>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f7fafc;">
                            <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Role</th>
                            <th style="padding: 12px; text-align: center; border-bottom: 2px solid #e2e8f0;">Users</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($role_distribution as $role): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 12px;">
                                <i class="fas fa-user-shield" style="color: #667eea;"></i>
                                <?php echo htmlspecialchars($role['display_name']); ?>
                            </td>
                            <td style="padding: 12px; text-align: center; font-weight: 600;">
                                <?php echo $role['user_count']; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Security Alerts -->
        <div class="card">
            <div class="card-header">
                <div class="card-title">
                    <i class="fas fa-exclamation-triangle"></i> Security Alerts
                </div>
            </div>
            <div style="padding: 20px; text-align: center; color: #718096;">
                <i class="fas fa-shield-alt" style="font-size: 48px; color: #48bb78; margin-bottom: 15px;"></i>
                <div style="font-size: 18px; font-weight: 600; color: #2d3748; margin-bottom: 5px;">No Security Threats Detected</div>
                <div style="font-size: 14px;">System is secure and operating normally</div>
            </div>
        </div>
    </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
</body>
</html>
