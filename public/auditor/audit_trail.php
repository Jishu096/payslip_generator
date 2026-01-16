<?php
session_start();

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Auditor';
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get filter parameters
$userFilter = $_GET['user'] ?? '';
$actionFilter = $_GET['action'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$searchTerm = $_GET['search'] ?? '';

// Build query
$sql = "SELECT 
            al.log_id,
            al.user_id,
            u.username,
            u.role,
            al.action,
            al.log_time,
            al.ip_address
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.user_id
        WHERE al.log_time BETWEEN ? AND ?";

$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];

if ($userFilter) {
    $sql .= " AND al.user_id = ?";
    $params[] = $userFilter;
}

if ($actionFilter) {
    $sql .= " AND al.action LIKE ?";
    $params[] = '%' . $actionFilter . '%';
}

if ($searchTerm) {
    $sql .= " AND (al.action LIKE ? OR u.username LIKE ?)";
    $params[] = '%' . $searchTerm . '%';
    $params[] = '%' . $searchTerm . '%';
}

$sql .= " ORDER BY al.log_time DESC LIMIT 1000";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $logs = [];
    $error = "Error fetching audit logs: " . $e->getMessage();
}

// Get users for filter
try {
    $usersStmt = $conn->query("SELECT user_id, username, role FROM users WHERE is_active = 1 ORDER BY username ASC");
    $users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}

// Get action types
try {
    $actionsStmt = $conn->query("SELECT DISTINCT SUBSTRING_INDEX(action, ' ', 1) as action_type FROM audit_log ORDER BY action_type ASC");
    $actionTypes = $actionsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $actionTypes = [];
}

// Calculate stats
$stats = [
    'total' => count($logs),
    'users' => count(array_unique(array_column($logs, 'user_id'))),
    'actions' => count(array_unique(array_column($logs, 'action'))),
    'today' => count(array_filter($logs, fn($l) => date('Y-m-d', strtotime($l['log_time'])) === date('Y-m-d')))
];

// Group by action type
$actionGroups = [];
foreach ($logs as $log) {
    $actionType = strtolower(explode(' ', $log['action'])[0]);
    if (!isset($actionGroups[$actionType])) {
        $actionGroups[$actionType] = 0;
    }
    $actionGroups[$actionType]++;
}
arsort($actionGroups);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Trail - Auditor</title>
    <?php include 'includes/auditor_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
        }

        .stat-card.users { border-left-color: #3b82f6; }
        .stat-card.actions { border-left-color: #10b981; }
        .stat-card.today { border-left-color: #f59e0b; }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--muted);
            font-size: 13px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .filter-btn, .export-btn, .clear-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .export-btn {
            background: #10b981;
            color: white;
        }

        .clear-btn {
            background: #64748b;
            color: white;
        }

        .filter-btn:hover, .export-btn:hover, .clear-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .content-row {
            display: grid;
            grid-template-columns: 1fr 300px;
            gap: 20px;
        }

        .table-card {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f8fafc;
            position: sticky;
            top: 0;
        }

        .data-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 12px 15px;
            border-top: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .action-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: #e0e7ff;
            color: #3730a3;
        }

        .role-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .sidebar-widget {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 20px;
        }

        .widget-title {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .action-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .action-list-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .action-list-item:last-child {
            border-bottom: none;
        }

        .action-name {
            font-size: 13px;
            color: var(--text);
            font-weight: 600;
        }

        .action-count {
            font-size: 13px;
            color: var(--accent);
            font-weight: 700;
        }

        .table-container {
            max-height: 600px;
            overflow-y: auto;
        }

        @media (max-width: 1200px) {
            .content-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/auditor_navbar.php'; ?>
    <?php include 'includes/auditor_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-clipboard-list"></i> Audit Trail</h1>
                <p>Comprehensive system activity logs</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Logs</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stat-card users">
                <div class="stat-label">Unique Users</div>
                <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
            </div>
            <div class="stat-card actions">
                <div class="stat-label">Action Types</div>
                <div class="stat-value"><?php echo number_format($stats['actions']); ?></div>
            </div>
            <div class="stat-card today">
                <div class="stat-label">Today's Logs</div>
                <div class="stat-value"><?php echo number_format($stats['today']); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="filter-group">
                    <label>User</label>
                    <select name="user">
                        <option value="">All Users</option>
                        <?php foreach ($users as $user): ?>
                            <option value="<?php echo $user['user_id']; ?>" <?php echo $userFilter == $user['user_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['username']); ?> (<?php echo $user['role']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Action Type</label>
                    <select name="action">
                        <option value="">All Actions</option>
                        <?php foreach ($actionTypes as $type): ?>
                            <option value="<?php echo htmlspecialchars($type['action_type']); ?>" <?php echo $actionFilter === $type['action_type'] ? 'selected' : ''; ?>>
                                <?php echo ucfirst(htmlspecialchars($type['action_type'])); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Search action or username" value="<?php echo htmlspecialchars($searchTerm); ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Content -->
        <div class="content-row">
            <div class="table-card">
                <div class="table-header">
                    <h3><?php echo count($logs); ?> Audit Logs</h3>
                    <div>
                        <button class="export-btn" onclick="exportToCSV()" style="margin-right: 10px;">
                            <i class="fas fa-download"></i> Export CSV
                        </button>
                        <button class="clear-btn" onclick="if(confirm('Clear all filters?')) window.location.href='audit_trail.php'">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>

                <?php if (empty($logs)): ?>
                    <div style="text-align: center; padding: 60px; color: var(--muted);">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                        <h3>No Audit Logs</h3>
                        <p>Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="data-table" id="auditTable">
                            <thead>
                                <tr>
                                    <th>Timestamp</th>
                                    <th>User</th>
                                    <th>Role</th>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td style="white-space: nowrap;">
                                            <strong><?php echo date('d M Y', strtotime($log['log_time'])); ?></strong><br>
                                            <span style="font-size: 12px; color: var(--muted);"><?php echo date('h:i:s A', strtotime($log['log_time'])); ?></span>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($log['username'] ?? 'System'); ?></strong></td>
                                        <td><span class="role-badge"><?php echo htmlspecialchars($log['role'] ?? 'system'); ?></span></td>
                                        <td><span class="action-badge"><?php echo htmlspecialchars($log['action']); ?></span></td>
                                        <td style="font-family: monospace; font-size: 12px;"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Sidebar -->
            <div>
                <div class="sidebar-widget">
                    <div class="widget-title">Top Actions</div>
                    <ul class="action-list">
                        <?php 
                        $topActions = array_slice($actionGroups, 0, 10, true);
                        if (empty($topActions)): ?>
                            <li style="text-align: center; color: var(--muted); padding: 20px 0;">No data</li>
                        <?php else: ?>
                            <?php foreach ($topActions as $action => $count): ?>
                                <li class="action-list-item">
                                    <span class="action-name"><?php echo ucfirst(htmlspecialchars($action)); ?></span>
                                    <span class="action-count"><?php echo number_format($count); ?></span>
                                </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <div class="sidebar-widget">
                    <div class="widget-title">Quick Stats</div>
                    <ul class="action-list">
                        <li class="action-list-item">
                            <span class="action-name">Date Range</span>
                            <span class="action-count"><?php echo (strtotime($endDate) - strtotime($startDate)) / 86400 + 1; ?> days</span>
                        </li>
                        <li class="action-list-item">
                            <span class="action-name">Avg per Day</span>
                            <span class="action-count"><?php echo number_format($stats['total'] / max(1, (strtotime($endDate) - strtotime($startDate)) / 86400 + 1), 1); ?></span>
                        </li>
                        <li class="action-list-item">
                            <span class="action-name">Showing</span>
                            <span class="action-count"><?php echo count($logs); ?> / 1000</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/auditor_scripts.php'; ?>
    <script>
        const logsData = <?php echo json_encode($logs); ?>;

        function exportToCSV() {
            if (logsData.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['Timestamp', 'User', 'Role', 'Action', 'IP Address'];
            const rows = logsData.map(l => [
                l.log_time,
                l.username || 'System',
                l.role || 'system',
                l.action,
                l.ip_address || ''
            ]);

            let csv = headers.join(',') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `audit_trail_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
