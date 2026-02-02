<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has administrator role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAdminRole = in_array('administrator', $userRoles);

if (!$hasAdminRole && $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Automatically create finalization reminder notification on 25th
if ((int)date('d') === 25) {
    include_once __DIR__ . '/../api/create_finalization_reminders.php';
}

// Automatically check for holiday reminders (runs daily)
include_once __DIR__ . '/../api/create_holiday_reminders.php';

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Models/LeaveRequest.php';
$db = new Database();
$conn = $db->connect();

// Get pending leave approvals count
$leaveModel = new LeaveRequest();
$pendingLeaves = $leaveModel->getPendingCount();

// Fetch attendance finalization workflow statistics
// Total finalizations this month
$stmt = $conn->query("SELECT COUNT(*) as count FROM attendance_finalization_log 
                      WHERE MONTH(finalized_at) = MONTH(CURRENT_DATE) 
                      AND YEAR(finalized_at) = YEAR(CURRENT_DATE)");
$finalizationsThisMonth = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending finalization count (HR verified but not finalized)
$stmt = $conn->query("SELECT COUNT(DISTINCT CONCAT(MONTH(date), '-', YEAR(date))) as count 
                      FROM attendance 
                      WHERE workflow_status = 'hr_verified'");
$pendingFinalization = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total attendance months finalized
$stmt = $conn->query("SELECT COUNT(DISTINCT CONCAT(month, '-', year)) as count FROM attendance_finalization_log");
$finalizedMonths = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Latest finalization status
$stmt = $conn->query("SELECT month, year, finalized_at, finalized_by 
                      FROM attendance_finalization_log 
                      ORDER BY finalized_at DESC LIMIT 1");
$latestFinalization = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent finalizations (last 5)
$stmt = $conn->query("SELECT afl.*, u.username as finalized_by_name 
                      FROM attendance_finalization_log afl
                      LEFT JOIN users u ON afl.finalized_by = u.user_id 
                      ORDER BY afl.finalized_at DESC LIMIT 5");
$recentFinalizations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly finalization trend (last 6 months)
$stmt = $conn->query("SELECT 
                        CONCAT(month, ' ', year) as period,
                        COUNT(*) as count,
                        SUM(CASE WHEN is_locked = 1 THEN 1 ELSE 0 END) as locked_count
                      FROM attendance_finalization_log 
                      WHERE finalized_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                      GROUP BY month, year, period
                      ORDER BY finalized_at DESC 
                      LIMIT 6");
$monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        /* Dashboard Header */
        .dashboard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .dashboard-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-date {
            text-align: right;
            background: rgba(255,255,255,0.15);
            padding: 15px 25px;
            border-radius: 12px;
        }

        .header-date .date {
            font-size: 24px;
            font-weight: 700;
        }

        .header-date .day {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.purple::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.yellow::before { background: linear-gradient(90deg, #eab308, #ca8a04); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .stat-sublabel {
            font-size: 12px;
            color: var(--muted);
            margin-top: 5px;
            opacity: 0.8;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.yellow .stat-icon { background: linear-gradient(135deg, #eab308, #ca8a04); }

        .stat-card.yellow {
            cursor: pointer;
        }

        .stat-footer {
            background: rgba(234, 179, 8, 0.1);
            padding: 10px;
            text-align: center;
            border-radius: 0 0 12px 12px;
            margin: 20px -25px -25px -25px;
        }

        .stat-footer span {
            color: #ca8a04;
            font-weight: 600;
            font-size: 12px;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .card {
            background: white;
            border-radius: 16px;
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

        .card-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 i {
            color: #667eea;
        }

        .card-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: all 0.3s ease;
        }

        .card-link:hover {
            color: #764ba2;
        }

        /* Employee Item / List */
        .upload-list {
            padding: 15px;
        }

        .employee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-radius: 12px;
            transition: all 0.2s ease;
            margin-bottom: 10px;
        }

        .employee-item:hover {
            background: #f8fafc;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .employee-avatar {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }

        .employee-dept {
            font-size: 12px;
            color: var(--muted);
            margin-top: 3px;
        }

        /* Badges */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-warning {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        /* Department Stats / Progress Bars */
        .dept-stats {
            padding: 20px 25px;
        }

        .dept-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .dept-name {
            width: 100px;
            font-size: 13px;
            font-weight: 500;
            color: var(--text);
        }

        .dept-progress {
            flex: 1;
            height: 10px;
            background: #f1f5f9;
            border-radius: 10px;
            overflow: hidden;
        }

        .dept-progress-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .dept-count {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            min-width: 60px;
            text-align: right;
        }

        /* Notification Bell */
        .notification-bell-container {
            position: relative;
        }

        .notification-bell {
            background: rgba(255,255,255,0.2);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 12px;
            cursor: pointer;
            color: white;
            font-size: 20px;
            transition: all 0.3s ease;
        }

        .notification-bell:hover {
            background: rgba(255,255,255,0.3);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ef4444;
            color: white;
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 10px;
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.15);
            width: 350px;
            z-index: 1000;
            overflow: hidden;
        }

        .notification-dropdown-header {
            padding: 20px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-dropdown-header h3 {
            font-size: 16px;
            color: var(--text);
        }

        .mark-all-read {
            background: none;
            border: none;
            color: #667eea;
            font-size: 13px;
            cursor: pointer;
            font-weight: 500;
        }

        .notification-list {
            max-height: 300px;
            overflow-y: auto;
        }

        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: var(--muted);
        }

        .notification-empty i {
            font-size: 40px;
            margin-bottom: 10px;
            opacity: 0.5;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .header-date {
                text-align: center;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-tachometer-alt"></i> Admin Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Manage your organization efficiently.</p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <div class="notification-bell-container">
                    <button class="notification-bell" onclick="toggleNotificationDropdown()">
                        <i class="fas fa-bell"></i>
                    </button>
                    <span class="notification-count" id="notificationCount" style="display: none;">0</span>
                    <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                        <div class="notification-dropdown-header">
                            <h3>Notifications</h3>
                            <button class="mark-all-read" onclick="markAllAsRead()">Mark all read</button>
                        </div>
                        <div class="notification-list" id="notificationList">
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>No new notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-date">
                    <div class="date"><?php echo date('d M Y'); ?></div>
                    <div class="day"><?php echo date('l'); ?></div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $finalizationsThisMonth; ?></div>
                        <div class="stat-label">Finalizations This Month</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-lock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingFinalization; ?></div>
                        <div class="stat-label">Pending Finalization</div>
                        <div class="stat-sublabel">HR verified, awaiting lock</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $finalizedMonths; ?></div>
                        <div class="stat-label">Finalized Months</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div>
                        <?php if ($latestFinalization): ?>
                            <div class="stat-value" style="font-size: 20px;">
                                <?php echo htmlspecialchars($latestFinalization['month'] . ' ' . $latestFinalization['year']); ?>
                            </div>
                            <div class="stat-label">Latest Finalization</div>
                            <div style="margin-top: 8px;">
                                <span class="badge badge-success">LOCKED</span>
                            </div>
                        <?php else: ?>
                            <div class="stat-value">--</div>
                            <div class="stat-label">No Finalizations Yet</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card yellow" onclick="window.location.href='leave_approvals.php'">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingLeaves; ?></div>
                        <div class="stat-label">Pending Leave Approvals</div>
                        <div class="stat-sublabel">Click to review and approve</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-clock"></i>
                    </div>
                </div>
                <?php if ($pendingLeaves > 0): ?>
                    <div class="stat-footer">
                        <span><i class="fas fa-exclamation-circle"></i> Action Required</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Finalizations -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Finalizations</h3>
                    <a href="attendance_finalize.php" class="card-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="upload-list">
                    <?php if (!empty($recentFinalizations)): ?>
                        <?php foreach ($recentFinalizations as $finalization): ?>
                            <div class="employee-item">
                                <div class="employee-info">
                                    <div class="employee-avatar" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                        <i class="fas fa-lock"></i>
                                    </div>
                                    <div class="employee-details">
                                        <div class="employee-name"><?php echo htmlspecialchars($finalization['month'] . ' ' . $finalization['year']); ?></div>
                                        <div class="employee-dept">
                                            <?php echo date('d M Y', strtotime($finalization['finalized_at'])); ?> • 
                                            <?php echo htmlspecialchars($finalization['finalized_by_name'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo $finalization['is_locked'] ? 'success' : 'warning'; ?>">
                                    <?php echo $finalization['is_locked'] ? 'LOCKED' : 'UNLOCKED'; ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--muted); padding: 40px 20px;">
                            <i class="fas fa-inbox" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No finalizations found</p>
                            <a href="attendance_finalize.php" style="color: #667eea; text-decoration: none; font-weight: 500;">Finalize HR-verified attendance →</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Monthly Trend -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Finalization Trend</h3>
                </div>
                <div class="dept-stats">
                    <?php if (!empty($monthlyTrend)): ?>
                        <?php 
                        $maxTrendCount = max(array_column($monthlyTrend, 'count'));
                        foreach ($monthlyTrend as $trend): ?>
                            <div class="dept-bar">
                                <div class="dept-name"><?php echo htmlspecialchars($trend['period']); ?></div>
                                <div class="dept-progress">
                                    <div class="dept-progress-fill" style="width: <?php echo $maxTrendCount > 0 ? ($trend['count'] / $maxTrendCount * 100) : 0; ?>%; background: linear-gradient(90deg, #10b981, #059669);"></div>
                                </div>
                                <div class="dept-count">
                                    <?php echo $trend['locked_count']; ?>/<?php echo $trend['count']; ?>
                                    <small style="color: var(--muted); font-weight: 400; font-size: 11px;">locked</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; color: var(--muted); padding: 40px 20px;">
                            <i class="fas fa-chart-bar" style="font-size: 40px; margin-bottom: 15px; opacity: 0.5;"></i>
                            <p>No trend data available yet</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/notification_popup.php'; ?>

</body>
</html>
