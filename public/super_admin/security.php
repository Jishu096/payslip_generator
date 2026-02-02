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
if (!$perm->hasPermission('audit.view')) {
    header("Location: ../auth/login.php?error=unauthorized");
    exit;
}

$username = $_SESSION['username'] ?? 'Super Admin';

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

// Filters
$filterUser = $_GET['user'] ?? '';
$filterAction = $_GET['action'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Build query with filters
$where = [];
$params = [];

if ($filterUser) {
    $where[] = "(u.username LIKE :user OR al.user_id = :user_id)";
    $params[':user'] = "%$filterUser%";
    $params[':user_id'] = $filterUser;
}
if ($filterAction) {
    $where[] = "al.action LIKE :action";
    $params[':action'] = "%$filterAction%";
}
if ($filterDate) {
    $where[] = "DATE(al.log_time) = :date";
    $params[':date'] = $filterDate;
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Count total logs
$countStmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    $whereClause
");
$countStmt->execute($params);
$totalLogs = $countStmt->fetch()['total'];
$totalPages = ceil($totalLogs / $perPage);

// Fetch logs
$logsStmt = $conn->prepare("
    SELECT 
        al.log_id,
        al.user_id,
        u.username,
        al.action,
        al.log_time
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    $whereClause
    ORDER BY al.log_time DESC
    LIMIT $perPage OFFSET $offset
");
$logsStmt->execute($params);
$auditLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Security stats
$stats = [
    'logs_24h' => 0,
    'logins_today' => 0,
    'failed_logins' => 0,
    'active_users' => 0,
    'critical_actions' => 0
];

try {
    $statsStmt = $conn->query("
        SELECT 
            (SELECT COUNT(*) FROM audit_log WHERE log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h,
            (SELECT COUNT(*) FROM audit_log WHERE action = 'user.login' AND log_time >= CURDATE()) as logins_today,
            (SELECT COUNT(*) FROM audit_log WHERE action = 'user.login_failed' AND log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as failed_logins,
            (SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_users,
            (SELECT COUNT(*) FROM audit_log WHERE action IN ('user.delete', 'role.delete', 'permission.revoke') AND log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as critical_actions
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Stats table might not have all columns
}

// Get unique actions for filter dropdown
$actionsStmt = $conn->query("SELECT DISTINCT action FROM audit_log ORDER BY action");
$uniqueActions = $actionsStmt->fetchAll(PDO::FETCH_COLUMN);

// Activity timeline (hourly for last 24 hours)
$timelineData = [];
try {
    $timelineStmt = $conn->query("
        SELECT 
            DATE_FORMAT(log_time, '%Y-%m-%d %H:00') as hour,
            COUNT(*) as count
        FROM audit_log
        WHERE log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY hour
        ORDER BY hour
    ");
    $timelineData = $timelineStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Action type distribution
$actionDist = [];
try {
    $distStmt = $conn->query("
        SELECT 
            SUBSTRING_INDEX(action, '.', 1) as category,
            COUNT(*) as count
        FROM audit_log
        WHERE log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY category
        ORDER BY count DESC
        LIMIT 6
    ");
    $actionDist = $distStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Audit Logs - Super Admin</title>
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

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-white {
            background: white;
            color: #667eea;
        }

        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        /* Stats Row */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
            color: #3b82f6;
        }

        .stat-icon.green {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #10b981;
        }

        .stat-icon.red {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: #ef4444;
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.15));
            color: #8b5cf6;
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
            color: #f59e0b;
        }

        .stat-info h3 {
            font-size: 26px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .stat-info p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 25px;
        }

        /* Analytics Sidebar */
        .analytics-sidebar {
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        .analytics-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .analytics-card h3 {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .analytics-card h3 i {
            color: #667eea;
        }

        /* Activity Chart */
        .activity-bars {
            display: flex;
            gap: 4px;
            height: 120px;
            align-items: flex-end;
        }

        .activity-bar {
            flex: 1;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 4px 4px 0 0;
            min-height: 8px;
            transition: all 0.3s;
            position: relative;
        }

        .activity-bar:hover {
            opacity: 0.8;
        }

        .activity-bar::after {
            content: attr(data-count);
            position: absolute;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: #64748b;
            opacity: 0;
            transition: opacity 0.3s;
            white-space: nowrap;
        }

        .activity-bar:hover::after {
            opacity: 1;
        }

        .time-labels {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
        }

        .time-labels span {
            font-size: 11px;
            color: #94a3b8;
        }

        /* Action Distribution */
        .action-dist-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .action-dist-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .action-dist-icon {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }

        .action-dist-icon.user { background: rgba(59, 130, 246, 0.1); color: #3b82f6; }
        .action-dist-icon.role { background: rgba(139, 92, 246, 0.1); color: #8b5cf6; }
        .action-dist-icon.audit { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .action-dist-icon.permission { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .action-dist-icon.system { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .action-dist-info {
            flex: 1;
        }

        .action-dist-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px 0;
            text-transform: capitalize;
        }

        .action-dist-bar {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            overflow: hidden;
        }

        .action-dist-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 3px;
        }

        .action-dist-count {
            font-size: 14px;
            font-weight: 600;
            color: #64748b;
        }

        /* Logs Section */
        .logs-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .logs-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .logs-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logs-title i {
            color: #667eea;
        }

        /* Filters */
        .filters {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 10px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 13px;
            transition: all 0.3s;
            min-width: 160px;
        }

        .filter-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn-filter {
            padding: 10px 18px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-clear {
            padding: 10px 18px;
            background: #f1f5f9;
            color: #64748b;
            border: none;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-clear:hover {
            background: #e2e8f0;
        }

        /* Logs Table */
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }

        .logs-table th {
            background: #f8fafc;
            padding: 14px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .logs-table td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .logs-table tr:hover {
            background: #fafbfc;
        }

        /* User Cell */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 38px;
            height: 38px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 14px;
            font-weight: 600;
        }

        .user-avatar.system {
            background: linear-gradient(135deg, #64748b, #475569);
        }

        .user-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 2px 0;
        }

        .user-info p {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        /* Action Badge */
        .action-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .action-badge.login {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .action-badge.logout {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .action-badge.create {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .action-badge.update {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .action-badge.delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .action-badge.view {
            background: rgba(100, 116, 139, 0.1);
            color: #64748b;
        }

        .action-badge.failed {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        /* Time Cell */
        .time-cell {
            font-size: 13px;
            color: #64748b;
        }

        .time-cell .date {
            font-weight: 600;
            color: #334155;
        }

        /* IP Badge */
        .ip-badge {
            font-size: 12px;
            padding: 4px 10px;
            background: #f1f5f9;
            border-radius: 6px;
            color: #64748b;
            font-family: 'Monaco', 'Consolas', monospace;
        }

        /* Pagination */
        .pagination {
            padding: 20px 25px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .pagination-info {
            font-size: 14px;
            color: #64748b;
        }

        .pagination-controls {
            display: flex;
            gap: 8px;
        }

        .page-btn {
            width: 38px;
            height: 38px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .page-btn:hover, .page-btn.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .page-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h4 {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 8px;
        }

        /* Responsive */
        @media (max-width: 1400px) {
            .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            .analytics-sidebar {
                flex-direction: row;
            }
            .analytics-card {
                flex: 1;
            }
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
            .analytics-sidebar {
                flex-direction: column;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            .filters {
                width: 100%;
            }
            .filter-input {
                flex: 1;
            }
            .logs-table {
                display: block;
                overflow-x: auto;
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
                <h1><i class="fas fa-shield-alt"></i> Security & Audit Logs</h1>
                <p>Monitor system activity and track all security-related events</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-white" onclick="exportLogs()">
                    <i class="fas fa-download"></i> Export Logs
                </button>
            </div>
        </div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['logs_24h'] ?? 0); ?></h3>
                    <p>Logs (24h)</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-sign-in-alt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['logins_today'] ?? 0); ?></h3>
                    <p>Logins Today</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['failed_logins'] ?? 0); ?></h3>
                    <p>Failed Logins</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['active_users'] ?? 0); ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-bolt"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo number_format($stats['critical_actions'] ?? 0); ?></h3>
                    <p>Critical Actions</p>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Analytics Sidebar -->
            <div class="analytics-sidebar">
                <!-- Activity Timeline -->
                <div class="analytics-card">
                    <h3><i class="fas fa-chart-bar"></i> Activity (24h)</h3>
                    <div class="activity-bars">
                        <?php
                        $maxCount = max(array_column($timelineData, 'count') ?: [1]);
                        foreach ($timelineData as $item):
                            $height = ($item['count'] / $maxCount) * 100;
                        ?>
                        <div class="activity-bar" style="height: <?php echo max(8, $height); ?>%;" data-count="<?php echo $item['count']; ?> events"></div>
                        <?php endforeach; ?>
                        <?php if (empty($timelineData)): ?>
                        <div style="text-align: center; color: #94a3b8; width: 100%; padding: 20px;">No data</div>
                        <?php endif; ?>
                    </div>
                    <div class="time-labels">
                        <span>24h ago</span>
                        <span>Now</span>
                    </div>
                </div>

                <!-- Action Distribution -->
                <div class="analytics-card">
                    <h3><i class="fas fa-pie-chart"></i> Actions (7 days)</h3>
                    <div class="action-dist-list">
                        <?php
                        $maxDist = max(array_column($actionDist, 'count') ?: [1]);
                        foreach ($actionDist as $dist):
                            $width = ($dist['count'] / $maxDist) * 100;
                            $iconClass = strtolower($dist['category']);
                        ?>
                        <div class="action-dist-item">
                            <div class="action-dist-icon <?php echo $iconClass; ?>">
                                <i class="fas fa-<?php 
                                    $icons = [
                                        'user' => 'user',
                                        'role' => 'user-tag',
                                        'audit' => 'clipboard-list',
                                        'permission' => 'key',
                                        'system' => 'cog'
                                    ];
                                    echo $icons[$iconClass] ?? 'circle';
                                ?>"></i>
                            </div>
                            <div class="action-dist-info">
                                <h5><?php echo htmlspecialchars($dist['category']); ?></h5>
                                <div class="action-dist-bar">
                                    <div class="action-dist-fill" style="width: <?php echo $width; ?>%;"></div>
                                </div>
                            </div>
                            <span class="action-dist-count"><?php echo number_format($dist['count']); ?></span>
                        </div>
                        <?php endforeach; ?>
                        <?php if (empty($actionDist)): ?>
                        <p style="text-align: center; color: #94a3b8; padding: 20px;">No data</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Logs Section -->
            <div class="logs-card">
                <div class="logs-header">
                    <div class="logs-title">
                        <i class="fas fa-scroll"></i>
                        Audit Log (<?php echo number_format($totalLogs); ?> entries)
                    </div>
                    <form class="filters" method="GET" action="">
                        <input type="text" name="user" class="filter-input" placeholder="Search user..." value="<?php echo htmlspecialchars($filterUser); ?>">
                        <select name="action" class="filter-input">
                            <option value="">All Actions</option>
                            <?php foreach ($uniqueActions as $action): ?>
                            <option value="<?php echo htmlspecialchars($action); ?>" <?php echo $filterAction === $action ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($action); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="date" name="date" class="filter-input" value="<?php echo htmlspecialchars($filterDate); ?>">
                        <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Filter</button>
                        <a href="security.php" class="btn-clear"><i class="fas fa-times"></i> Clear</a>
                    </form>
                </div>

                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Action</th>
                            <th>Time</th>
                            <th>IP Address</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($auditLogs)): ?>
                        <tr>
                            <td colspan="4">
                                <div class="empty-state">
                                    <i class="fas fa-clipboard-list"></i>
                                    <h4>No Logs Found</h4>
                                    <p>Try adjusting your filters or check back later</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($auditLogs as $log): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar <?php echo empty($log['username']) ? 'system' : ''; ?>">
                                        <?php echo $log['username'] ? strtoupper(substr($log['username'], 0, 1)) : '<i class="fas fa-robot"></i>'; ?>
                                    </div>
                                    <div class="user-info">
                                        <h4><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></h4>
                                        <p>ID: <?php echo $log['user_id'] ?? 'N/A'; ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <?php
                                $actionType = 'view';
                                if (strpos($log['action'], 'login') !== false) $actionType = 'login';
                                elseif (strpos($log['action'], 'logout') !== false) $actionType = 'logout';
                                elseif (strpos($log['action'], 'create') !== false) $actionType = 'create';
                                elseif (strpos($log['action'], 'update') !== false || strpos($log['action'], 'edit') !== false) $actionType = 'update';
                                elseif (strpos($log['action'], 'delete') !== false) $actionType = 'delete';
                                elseif (strpos($log['action'], 'failed') !== false) $actionType = 'failed';
                                ?>
                                <span class="action-badge <?php echo $actionType; ?>">
                                    <i class="fas fa-<?php 
                                        $actionIcons = [
                                            'login' => 'sign-in-alt',
                                            'logout' => 'sign-out-alt',
                                            'create' => 'plus',
                                            'update' => 'edit',
                                            'delete' => 'trash',
                                            'view' => 'eye',
                                            'failed' => 'times'
                                        ];
                                        echo $actionIcons[$actionType];
                                    ?>"></i>
                                    <?php echo htmlspecialchars($log['action']); ?>
                                </span>
                            </td>
                            <td>
                                <div class="time-cell">
                                    <span class="date"><?php echo date('M d, Y', strtotime($log['log_time'])); ?></span><br>
                                    <?php echo date('h:i:s A', strtotime($log['log_time'])); ?>
                                </div>
                            </td>
                            <td>
                                <span class="ip-badge">—</span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <div class="pagination-info">
                        Showing <?php echo $offset + 1; ?> - <?php echo min($offset + $perPage, $totalLogs); ?> of <?php echo number_format($totalLogs); ?> entries
                    </div>
                    <div class="pagination-controls">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&user=<?php echo urlencode($filterUser); ?>&action=<?php echo urlencode($filterAction); ?>&date=<?php echo urlencode($filterDate); ?>" class="page-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        for ($i = $startPage; $i <= $endPage; $i++):
                        ?>
                        <a href="?page=<?php echo $i; ?>&user=<?php echo urlencode($filterUser); ?>&action=<?php echo urlencode($filterAction); ?>&date=<?php echo urlencode($filterDate); ?>" class="page-btn <?php echo $i === $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&user=<?php echo urlencode($filterUser); ?>&action=<?php echo urlencode($filterAction); ?>&date=<?php echo urlencode($filterDate); ?>" class="page-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
    
    <script>
        function exportLogs() {
            // Build export URL with current filters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            window.location.href = 'api/export_audit_logs.php?' + params.toString();
        }

        // Auto-refresh every 30 seconds
        setTimeout(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
