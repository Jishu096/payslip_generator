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

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Fetch attendance workflow statistics
// Total uploads this month
$stmt = $conn->query("SELECT COUNT(*) as count FROM attendance_uploads 
                      WHERE MONTH(uploaded_at) = MONTH(CURRENT_DATE) 
                      AND YEAR(uploaded_at) = YEAR(CURRENT_DATE)");
$uploadsThisMonth = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Pending verification count
$stmt = $conn->query("SELECT COUNT(*) as count FROM attendance_uploads WHERE status = 'UPLOADED'");
$pendingVerification = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total attendance months processed
$stmt = $conn->query("SELECT COUNT(DISTINCT CONCAT(month, '-', year)) as count FROM attendance_uploads");
$attendanceMonths = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Latest upload status
$stmt = $conn->query("SELECT status, uploaded_at, month, year FROM attendance_uploads 
                      ORDER BY uploaded_at DESC LIMIT 1");
$latestUpload = $stmt->fetch(PDO::FETCH_ASSOC);

// Recent uploads (last 5)
$stmt = $conn->query("SELECT au.*, u.username as uploaded_by_name 
                      FROM attendance_uploads au
                      LEFT JOIN users u ON au.uploaded_by = u.user_id 
                      ORDER BY au.uploaded_at DESC LIMIT 5");
$recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Monthly upload trend (last 6 months)
$stmt = $conn->query("SELECT 
                        CONCAT(month, ' ', year) as period,
                        COUNT(*) as count,
                        SUM(CASE WHEN status = 'VERIFIED' THEN 1 ELSE 0 END) as verified_count
                      FROM attendance_uploads 
                      WHERE uploaded_at >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
                      GROUP BY month, year, period
                      ORDER BY uploaded_at DESC 
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
            <h1><i class="fas fa-calendar-check"></i> Attendance Management Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Manage attendance uploads and workflow.</p>
        </div>

        <!-- Attendance Workflow Statistics -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $uploadsThisMonth; ?></div>
                        <div class="stat-label">Uploads This Month</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-upload"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingVerification; ?></div>
                        <div class="stat-label">Pending Verification</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $attendanceMonths; ?></div>
                        <div class="stat-label">Attendance Months</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div>
                        <?php if ($latestUpload): ?>
                            <div class="stat-value" style="font-size: 18px; font-weight: 600;">
                                <?php echo htmlspecialchars($latestUpload['month'] . ' ' . $latestUpload['year']); ?>
                            </div>
                            <div class="stat-label">Latest Upload</div>
                            <div style="margin-top: 8px;">
                                <span class="badge badge-<?php echo $latestUpload['status'] === 'VERIFIED' ? 'success' : 'info'; ?>" style="font-size: 11px;">
                                    <?php echo htmlspecialchars($latestUpload['status']); ?>
                                </span>
                            </div>
                        <?php else: ?>
                            <div class="stat-value">--</div>
                            <div class="stat-label">No Uploads Yet</div>
                        <?php endif; ?>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Action: Upload New Statement -->
        <div class="card" style="margin-bottom: 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <h2 style="font-size: 24px; margin-bottom: 10px; color: white;"><i class="fas fa-cloud-upload-alt"></i> Ready to Upload?</h2>
                    <p style="opacity: 0.95; font-size: 15px;">Upload the monthly absentee statement PDF for HR Officer verification</p>
                </div>
                <a href="upload_attendance.php" style="padding: 15px 30px; background: white; color: #667eea; text-decoration: none; border-radius: 10px; font-weight: 600; font-size: 16px; transition: transform 0.2s; display: inline-block;" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
                    <i class="fas fa-upload"></i> Upload Statement
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Uploads -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-history"></i> Recent Uploads</h3>
                    <a href="upload_attendance.php" class="card-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="upload-list">
                    <?php if (!empty($recentUploads)): ?>
                        <?php foreach ($recentUploads as $upload): ?>
                            <div class="employee-item">
                                <div class="employee-info">
                                    <div class="employee-avatar" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                        <i class="fas fa-file-pdf"></i>
                                    </div>
                                    <div class="employee-details">
                                        <div class="employee-name"><?php echo htmlspecialchars($upload['month'] . ' ' . $upload['year']); ?></div>
                                        <div class="employee-dept">
                                            <?php echo date('d M Y', strtotime($upload['uploaded_at'])); ?> • 
                                            <?php echo htmlspecialchars($upload['uploaded_by_name'] ?? 'Unknown'); ?>
                                        </div>
                                    </div>
                                </div>
                                <span class="badge badge-<?php echo $upload['status'] === 'VERIFIED' ? 'success' : ($upload['status'] === 'REJECTED' ? 'danger' : 'info'); ?>">
                                    <?php echo htmlspecialchars($upload['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No uploads found. Upload your first attendance statement!</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Monthly Trend -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-line"></i> Monthly Upload Trend</h3>
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
                                    <?php echo $trend['verified_count']; ?>/<?php echo $trend['count']; ?>
                                    <small style="color: var(--text-tertiary); font-weight: 400; font-size: 11px; margin-left: 4px;">verified</small>
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

    <script>
    </script>

</body>
</html>
