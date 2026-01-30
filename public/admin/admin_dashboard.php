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
    <script>
        // Force refresh - clear cache
        if (performance.navigation.type === 1) {
            location.reload(true);
        }
    </script>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="dashboard-header">
            <div>
                <h1><i class="fas fa-lock"></i> Attendance Finalization Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Finalize and export verified attendance data.</p>
            </div>
            <div class="notification-bell-container">
                <button class="notification-bell" onclick="toggleNotificationDropdown()" type="button">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count" style="display: none;">0</span>
                </button>
                <!-- Notification Dropdown -->
                <div id="notificationDropdown" class="notification-dropdown" style="display: none;">
                    <div class="notification-dropdown-header">
                        <h3>Notifications</h3>
                        <button onclick="markAllAsRead()" class="mark-all-read">Mark all as read</button>
                    </div>
                    <div id="notificationList" class="notification-list">
                        <div class="notification-empty">
                            <i class="fas fa-bell-slash"></i>
                            <p>No new notifications</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Workflow Statistics -->
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
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div>
                        <?php if ($latestFinalization): ?>
                            <div class="stat-value" style="font-size: 18px; font-weight: 600;">
                                <?php echo htmlspecialchars($latestFinalization['month'] . ' ' . $latestFinalization['year']); ?>
                            </div>
                            <div class="stat-label">Latest Finalization</div>
                            <div style="margin-top: 8px;">
                                <span class="badge badge-success" style="font-size: 11px;">
                                    LOCKED
                                </span>
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
            
            <div class="stat-card yellow" style="cursor: pointer;" onclick="window.location.href='leave_approvals.php'">
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
                    <div class="stat-footer" style="background: rgba(245, 158, 11, 0.15); padding: 8px; text-align: center; border-radius: 0 0 15px 15px; margin: 15px -25px -20px -25px;">
                        <span style="color: #f59e0b; font-weight: 600; font-size: 12px;">
                            <i class="fas fa-exclamation-circle"></i> Action Required
                        </span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="content-grid" style="margin-bottom: 30px;">
            <div class="card" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="fas fa-lock" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3 style="color: white; margin-bottom: 10px;">Finalize Attendance</h3>
                    <p style="opacity: 0.95; margin-bottom: 20px;">Lock HR-verified attendance months</p>
                    <a href="attendance_finalize.php" style="padding: 12px 30px; background: white; color: #667eea; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <i class="fas fa-lock"></i> Finalize Now
                    </a>
                </div>
            </div>

            <div class="card" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border: none;">
                <div style="text-align: center; padding: 20px 0;">
                    <i class="fas fa-file-export" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <h3 style="color: white; margin-bottom: 10px;">Export Attendance</h3>
                    <p style="opacity: 0.95; margin-bottom: 20px;">Export finalized data for Accountant</p>
                    <a href="attendance_export.php" style="padding: 12px 30px; background: white; color: #11998e; text-decoration: none; border-radius: 8px; font-weight: 600; display: inline-block; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                        <i class="fas fa-file-export"></i> Export Now
                    </a>
                </div>
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
                        <p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No finalizations found. Finalize HR-verified attendance!</p>
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
                                    <small style="color: var(--text-tertiary); font-weight: 400; font-size: 11px; margin-left: 4px;">locked</small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No trend data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Workflow Info Card -->
        <div class="card" style="background: #f0f4ff; border: 2px solid #c7d2fe;">
            <div style="display: flex; align-items: start; gap: 20px;">
                <div style="min-width: 50px; height: 50px; background: linear-gradient(135deg, #667eea, #764ba2); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: white; font-size: 24px;">
                    <i class="fas fa-info-circle"></i>
                </div>
                <div>
                    <h3 style="color: #667eea; margin-bottom: 12px; font-size: 20px;">Attendance Upload Workflow</h3>
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">1</span>
                            <span style="color: var(--text-primary); font-weight: 500;">Admin uploads monthly absentee PDF statement</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">2</span>
                            <span style="color: var(--text-primary); font-weight: 500;">System saves file and sets status to <strong>UPLOADED</strong></span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">3</span>
                            <span style="color: var(--text-primary); font-weight: 500;">HR Officer receives notification to verify data</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: #667eea; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;">4</span>
                            <span style="color: var(--text-primary); font-weight: 500;">HR Officer converts PDF to table and verifies accuracy</span>
                        </div>
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <span style="background: #10b981; color: white; width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 13px;"><i class="fas fa-check"></i></span>
                            <span style="color: var(--text-primary); font-weight: 500;">Verified data ready for payroll processing</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/notification_popup.php'; ?>

    <script>
    </script>

</body>
</html>
