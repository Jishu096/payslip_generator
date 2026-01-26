<?php
session_start();

// Check if user has director role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Director';

require_once __DIR__ . '/../../app/Config/database.php';
$conn = getDBConnection();

// Get statistics
$totalEmployees = $conn->query("SELECT COUNT(*) FROM employees")->fetchColumn();
$totalDepartments = $conn->query("SELECT COUNT(*) FROM departments")->fetchColumn();
$totalPayroll = $conn->query("SELECT SUM(basic_salary) FROM employees WHERE status='active'")->fetchColumn() ?? 0;

// Department-wise employee count
$deptStats = $conn->query("
    SELECT d.department_name, COUNT(e.employee_id) as count 
    FROM departments d 
    LEFT JOIN employees e ON d.department_id = e.department_id 
    GROUP BY d.department_id, d.department_name 
    ORDER BY count DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Recent salary approvals
$recentApprovals = $conn->query("
    SELECT scr.*, e.full_name, e.designation 
    FROM salary_change_requests scr
    JOIN employees e ON scr.employee_id = e.employee_id
    ORDER BY scr.request_date DESC
    LIMIT 10
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Director Dashboard</title>
    <?php include 'includes/director_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-line"></i> Analytics & Reports</h1>
            <p>Company overview and statistics.</p>
        </div>

        <div class="live-stats-container">
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-blue text-white">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $totalEmployees; ?></h4>
                    <span>Total Employees</span>
                </div>
            </div>
            
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-purple text-white">
                     <i class="fas fa-building"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $totalDepartments; ?></h4>
                    <span>Departments</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-text">
                    <h4>₹<?php echo number_format($totalPayroll, 0); ?></h4>
                    <span>Total Payroll</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-chart-pie"></i> Department Distribution</h3>
            </div>
            <div class="card-body">
                <div style="margin-top: 10px;">
                    <?php 
                    $maxCount = !empty($deptStats) ? max(array_column($deptStats, 'count')) : 1;
                    foreach ($deptStats as $dept): 
                    ?>
                        <div style="display: flex; align-items: center; gap: 15px; padding: 12px 0; border-bottom: 1px solid #edf2f7;">
                            <div style="width: 150px; font-weight: 500; font-size: 14px;"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                            <div style="flex: 1; height: 8px; background: #f7fafc; border-radius: 10px; overflow: hidden;">
                                <div style="height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: <?php echo $maxCount > 0 ? ($dept['count'] / $maxCount * 100) : 0; ?>%;"></div>
                            </div>
                            <div style="font-weight: 700; color: #667eea; width: 40px; text-align: right;"><?php echo $dept['count']; ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-history"></i> Recent Approval Activity</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Date</th>
                                <th>Current</th>
                                <th>Requested</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentApprovals)): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center; padding: 40px; color: #a0aec0;">
                                        <i class="fas fa-chart-bar" style="font-size: 48px; opacity: 0.2; margin-bottom: 20px;"></i>
                                        <p>No recent approvals</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentApprovals as $approval): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($approval['full_name']); ?></strong></td>
                                        <td style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($approval['designation']); ?></td>
                                        <td style="font-size: 13px; white-space: nowrap;"><?php echo date('d M Y', strtotime($approval['request_date'])); ?></td>
                                        <td style="font-family: monospace;">₹<?php echo number_format($approval['current_salary'], 0); ?></td>
                                        <td style="font-family: monospace;">₹<?php echo number_format($approval['new_salary'], 0); ?></td>
                                        <td>
                                            <?php 
                                                $statusClass = 'badge-warning';
                                                if($approval['status'] === 'approved') $statusClass = 'badge-success';
                                                if($approval['status'] === 'rejected') $statusClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>"><?php echo ucfirst($approval['status']); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
