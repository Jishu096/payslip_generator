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

// Get audit statistics
try {
    // Audit logs count (last 30 days)
    $logsStmt = $conn->query("
        SELECT COUNT(*) as total_logs 
        FROM audit_log 
        WHERE log_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $logsCount = $logsStmt->fetch(PDO::FETCH_ASSOC)['total_logs'];

    // Salary reports count (payroll records last 30 days)
    $salaryStmt = $conn->query("
        SELECT COUNT(*) as total_payrolls 
        FROM payroll 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $salaryCount = $salaryStmt->fetch(PDO::FETCH_ASSOC)['total_payrolls'];

    // Recent audit logs (last 15)
    $recentLogsStmt = $conn->query("
        SELECT 
            al.log_id,
            al.user_id,
            u.username,
            al.action,
            al.log_time
        FROM audit_log al
        LEFT JOIN users u ON al.user_id = u.user_id
        ORDER BY al.log_time DESC
        LIMIT 15
    ");
    $recentLogs = $recentLogsStmt->fetchAll(PDO::FETCH_ASSOC);

    // Recent salary reports (payroll records last 10)
    $recentSalaryStmt = $conn->query("
        SELECT 
            p.payroll_id,
            p.employee_id,
            e.full_name,
            p.basic,
            p.gross_salary,
            p.net_salary,
            p.month,
            p.year
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $recentSalary = $recentSalaryStmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $logsCount = 0;
    $salaryCount = 0;
    $recentLogs = [];
    $recentSalary = [];
    $error = "Error fetching audit data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Auditor Dashboard</title>
    <?php include 'includes/auditor_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.logs { border-left-color: #8b5cf6; }
        .stat-card.financial { border-left-color: #10b981; }
        .stat-card.attendance { border-left-color: #f59e0b; }
        .stat-card.users { border-left-color: #3b82f6; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card.logs .stat-icon {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .stat-card.financial .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .stat-card.attendance .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .stat-card.users .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
        }

        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .card-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h3 {
            margin: 0;
            font-size: 18px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: var(--accent);
        }

        .card-body {
            padding: 20px;
        }

        .log-item {
            padding: 15px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: start;
            transition: background 0.2s;
        }

        .log-item:last-child {
            border-bottom: none;
        }

        .log-item:hover {
            background: #f8fafc;
        }

        .log-info {
            flex: 1;
        }

        .log-user {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .log-action {
            font-size: 14px;
            color: var(--muted);
        }

        .log-time {
            font-size: 12px;
            color: var(--muted);
            text-align: right;
        }

        .action-chart {
            margin-top: 15px;
        }

        .action-item {
            margin-bottom: 15px;
        }

        .action-header {
            display: flex;
            justify-content: space-between;
            margin-bottom: 6px;
        }

        .action-name {
            font-size: 14px;
            font-weight: 500;
            color: var(--text);
        }

        .action-count {
            font-size: 14px;
            font-weight: 600;
            color: var(--accent);
        }

        .action-bar {
            height: 8px;
            background: #f1f5f9;
            border-radius: 4px;
            overflow: hidden;
        }

        .action-fill {
            height: 100%;
            background: linear-gradient(90deg, #8b5cf6, #6d28d9);
            border-radius: 4px;
            transition: width 0.3s;
        }

        .view-all-btn {
            display: block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #8b5cf6, #6d28d9);
            color: white;
            text-align: center;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s;
            margin-top: 15px;
        }

        .view-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <?php include 'includes/auditor_navbar.php'; ?>
    <?php include 'includes/auditor_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Audit Dashboard</h1>
                <p>Monitor system activity and compliance</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error" style="padding: 15px; background: #fee2e2; color: #991b1b; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card logs">
                <div class="stat-icon">
                    <i class="fas fa-list-alt"></i>
                </div>
                <div class="stat-label">Audit Logs (30 Days)</div>
                <div class="stat-value"><?php echo number_format($logsCount); ?></div>
            </div>

            <div class="stat-card financial">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-label">Salary Reports (30 Days)</div>
                <div class="stat-value"><?php echo number_format($salaryCount); ?></div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Audit Logs -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-list-alt"></i> Audit Logs</h3>
                    <a href="<?php echo $baseURL; ?>auditor/audit_trail.php" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 500;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($recentLogs)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activity</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentLogs as $log): ?>
                            <div class="log-item">
                                <div class="log-info">
                                    <div class="log-user"><?php echo htmlspecialchars($log['username'] ?? 'Unknown User'); ?></div>
                                    <div class="log-action"><?php echo htmlspecialchars($log['action']); ?></div>
                                </div>
                                <div class="log-time">
                                    <?php echo date('M d, H:i', strtotime($log['log_time'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Salary Reports -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-file-invoice-dollar"></i> Salary Reports</h3>
                    <a href="<?php echo $baseURL; ?>auditor/payroll_reports.php" style="color: var(--accent); text-decoration: none; font-size: 14px; font-weight: 500;">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($recentSalary)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No salary reports</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentSalary as $salary): ?>
                            <div class="log-item">
                                <div class="log-info">
                                    <div class="log-user"><?php echo htmlspecialchars($salary['full_name']); ?></div>
                                    <div class="log-action">
                                        <?php echo date('F Y', strtotime($salary['year'] . '-' . $salary['month'] . '-01')); ?> - 
                                        ₹<?php echo number_format($salary['net_salary'], 2); ?>
                                    </div>
                                </div>
                                <div class="log-time">
                                    ID: <?php echo $salary['payroll_id']; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/auditor_scripts.php'; ?>
</body>
</html>
