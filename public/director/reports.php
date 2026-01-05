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
$db = new Database();
$conn = $db->connect();

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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Roboto", sans-serif;
            background: #ffffff;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .header p {
            font-size: 16px;
            opacity: 0.95;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
            margin-bottom: 10px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }

        .content-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-container {
            margin-top: 20px;
        }

        .dept-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .dept-bar:last-child {
            border-bottom: none;
        }

        .dept-name {
            flex: 1;
            font-weight: 500;
        }

        .dept-progress {
            flex: 2;
            height: 8px;
            background: #f7fafc;
            border-radius: 10px;
            overflow: hidden;
        }

        .dept-progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
        }

        .dept-count {
            font-weight: 600;
            color: #667eea;
            min-width: 40px;
            text-align: right;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            background: #f7fafc;
            font-size: 13px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #e6fffa;
            color: #0f766e;
        }

        .badge-warning {
            background: #fef3c7;
            color: #b45309;
        }

        .badge-danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .logout-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="director_dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <h1><i class="fas fa-chart-bar"></i> Analytics & Reports</h1>
            <p>Company overview and statistics</p>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-users"></i> Total Employees</div>
                <div class="stat-value"><?php echo $totalEmployees; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-building"></i> Departments</div>
                <div class="stat-value"><?php echo $totalDepartments; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-rupee-sign"></i> Total Payroll</div>
                <div class="stat-value">₹<?php echo number_format($totalPayroll, 0); ?></div>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-chart-pie"></i> Department Distribution
            </div>
            <div class="chart-container">
                <?php 
                $maxCount = !empty($deptStats) ? max(array_column($deptStats, 'count')) : 1;
                foreach ($deptStats as $dept): 
                ?>
                    <div class="dept-bar">
                        <div class="dept-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                        <div class="dept-progress">
                            <div class="dept-progress-fill" style="width: <?php echo $maxCount > 0 ? ($dept['count'] / $maxCount * 100) : 0; ?>%"></div>
                        </div>
                        <div class="dept-count"><?php echo $dept['count']; ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="content-card">
            <div class="section-title">
                <i class="fas fa-history"></i> Recent Approval Activity
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Request Date</th>
                        <th>Current Salary</th>
                        <th>Requested Salary</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recentApprovals)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px; color: #a0aec0;">
                                No recent approvals
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($recentApprovals as $approval): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($approval['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($approval['designation']); ?></td>
                                <td><?php echo date('d M Y', strtotime($approval['request_date'])); ?></td>
                                <td>₹<?php echo number_format($approval['current_salary'], 2); ?></td>
                                <td>₹<?php echo number_format($approval['requested_salary'], 2); ?></td>
                                <td>
                                    <?php if ($approval['status'] === 'approved'): ?>
                                        <span class="badge badge-success">Approved</span>
                                    <?php elseif ($approval['status'] === 'rejected'): ?>
                                        <span class="badge badge-danger">Rejected</span>
                                    <?php else: ?>
                                        <span class="badge badge-warning">Pending</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <a href="../auth/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</body>
</html>
