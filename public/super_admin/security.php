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

// Fetch recent audit logs
$logsStmt = $conn->query("
    SELECT 
        al.log_id,
        al.user_id,
        u.username,
        al.action,
        al.log_time
    FROM audit_log al
    LEFT JOIN users u ON al.user_id = u.user_id
    ORDER BY al.log_time DESC
    LIMIT 100
");
$auditLogs = $logsStmt->fetchAll(PDO::FETCH_ASSOC);

// Security stats
$statsStmt = $conn->query("
    SELECT 
        (SELECT COUNT(*) FROM audit_log WHERE log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as logs_24h,
        (SELECT COUNT(*) FROM audit_log WHERE action LIKE 'user.%' AND log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as user_actions_7d,
        (SELECT COUNT(*) FROM audit_log WHERE action LIKE 'role.%' AND log_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as role_actions_7d,
        (SELECT COUNT(DISTINCT user_id) FROM audit_log WHERE log_time >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as active_users_24h
");
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security & Audit Logs - Super Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/superadmin_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .stat-card .icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }
        .stat-card .content h3 {
            font-size: 28px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .stat-card .content p {
            color: #64748b;
            font-size: 14px;
        }
        .logs-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .logs-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .logs-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        .logs-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .logs-table tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }
        .badge-create {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-update {
            background: #dbeafe;
            color: #1e40af;
        }
        .badge-delete {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-view {
            background: #e0e7ff;
            color: #4338ca;
        }
        .badge-assign {
            background: #fef3c7;
            color: #92400e;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters select,
        .filters input {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .filters label {
            font-size: 14px;
            font-weight: 600;
            color: #334155;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .section-header h2 {
            font-size: 20px;
            color: #1e293b;
        }
    </style>
</head>
<body>
    <?php include 'includes/superadmin_navbar.php'; ?>
    
    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-shield-alt"></i> Security & Audit Logs</h1>
                <p>Monitor system activity and security events</p>
            </div>
        </div>

            <!-- Security Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['logs_24h']); ?></h3>
                        <p>Logs (24 Hours)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['user_actions_7d']); ?></h3>
                        <p>User Actions (7 Days)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-user-tag"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['role_actions_7d']); ?></h3>
                        <p>Role Changes (7 Days)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['active_users_24h']); ?></h3>
                        <p>Active Users (24h)</p>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filters">
                <label>Filter by Action:</label>
                <select id="actionFilter">
                    <option value="">All Actions</option>
                    <option value="user.">User Actions</option>
                    <option value="role.">Role Actions</option>
                    <option value="system.">System Actions</option>
                    <option value="audit.">Audit Actions</option>
                </select>

                <label>Search:</label>
                <input type="text" id="searchInput" placeholder="Search logs..." style="flex: 1; min-width: 200px;">

                <button class="btn btn-secondary" onclick="resetFilters()">
                    <i class="fas fa-redo"></i> Reset
                </button>
            </div>

            <!-- Audit Logs Table -->
            <div class="section-header">
                <h2>Recent Activity</h2>
            </div>

            <div class="logs-table">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="logsTableBody">
                        <?php foreach ($auditLogs as $log): ?>
                        <tr data-action="<?php echo htmlspecialchars($log['action']); ?>">
                            <td><?php echo date('M d, Y H:i:s', strtotime($log['log_time'])); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></strong>
                                <span style="color: #64748b; font-size: 11px;">(ID: <?php echo $log['user_id']; ?>)</span>
                            </td>
                            <td>
                                <?php
                                $action = $log['action'];
                                $badgeClass = 'badge-view';
                                if (strpos($action, 'create') !== false) $badgeClass = 'badge-create';
                                elseif (strpos($action, 'update') !== false) $badgeClass = 'badge-update';
                                elseif (strpos($action, 'delete') !== false || strpos($action, 'revoke') !== false) $badgeClass = 'badge-delete';
                                elseif (strpos($action, 'assign') !== false) $badgeClass = 'badge-assign';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($action); ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
    <script>
        const searchInput = document.getElementById('searchInput');
        const actionFilter = document.getElementById('actionFilter');
        const tableBody = document.getElementById('logsTableBody');
        const rows = tableBody.getElementsByTagName('tr');

        function filterLogs() {
            const searchTerm = searchInput.value.toLowerCase();
            const actionTerm = actionFilter.value.toLowerCase();

            Array.from(rows).forEach(row => {
                const action = row.dataset.action.toLowerCase();
                const details = row.dataset.details.toLowerCase();
                const rowText = row.textContent.toLowerCase();
matchesSearch = searchTerm === '' || rowText.includes(searchTerm);
                const matchesAction = actionTerm === '' || action.includes(actionTerm);

                row.style.display = (matchesSearch && matchesAction) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterLogs);
        actionFilter.addEventListener('change', filterLogs);

        function resetFilters() {
            searchInput.value = '';
            actionFilter.value = '';
            filterLogs();
        }
    </script>
</body>
</html>
