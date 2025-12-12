<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has director role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Models/Employee.php";
require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$employeeModel = new Employee();

$username = $_SESSION['username'] ?? 'Director';
$totalEmployees = count($employeeModel->getAllEmployees());

// Get pending salary change requests count
$stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM salary_change_requests WHERE status = 'pending'");
$stmt->execute();
$pendingRequests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

// Get pending role change requests count
$stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM role_change_requests WHERE status = 'pending'");
$stmt->execute();
$pendingRoleRequests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

// Get approved requests count
$stmt = $db->prepare("SELECT COUNT(*) as approved_count FROM salary_change_requests WHERE status = 'approved'");
$stmt->execute();
$approvedRequests = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];

// Get rejected requests count
$stmt = $db->prepare("SELECT COUNT(*) as rejected_count FROM salary_change_requests WHERE status = 'rejected'");
$stmt->execute();
$rejectedRequests = $stmt->fetch(PDO::FETCH_ASSOC)['rejected_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Director Dashboard - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --gradient-green: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-orange: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            --gradient-red: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        :root

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .dashboard-header p {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .user-card {
            display: flex;
            align-items: center;
            gap: 15px;
            background: var(--bg-primary);
            padding: 20px;
            border-radius: 15px;
            box-shadow: var(--card-shadow);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }

        .user-details h3 {
            margin-bottom: 4px;
            font-size: 16px;
        }

        .user-details p {
            font-size: 13px;
            color: var(--text-tertiary);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.purple::before { background: var(--gradient-primary); }
        .stat-card.blue::before { background: var(--gradient-blue); }
        .stat-card.green::before { background: var(--gradient-green); }
        .stat-card.orange::before { background: var(--gradient-orange); }
        .stat-card.red::before { background: var(--gradient-red); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.purple .stat-icon { background: var(--gradient-primary); }
        .stat-card.blue .stat-icon { background: var(--gradient-blue); }
        .stat-card.green .stat-icon { background: var(--gradient-green); }
        .stat-card.orange .stat-icon { background: var(--gradient-orange); }
        .stat-card.red .stat-icon { background: var(--gradient-red); }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-tertiary);
            font-weight: 500;
        }

        .card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }

        .action-btn {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
            padding: 15px 20px;
            border-radius: 12px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-btn:hover {
            background: var(--gradient-primary);
            color: white;
            border-color: var(--gradient-primary);
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 20px;
        }

        .badge-notification {
            display: inline-block;
            background: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
        }

        .logout-btn {
            background: var(--gradient-red);
            color: white;
            border: none;
            padding: 12px 20px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        @media (max-width: 768px) {
            .header-top {
                flex-direction: column;
            }

            .dashboard-header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->

    <div class="container">
        <!-- Header Top -->
        <div class="header-top">
            <div class="dashboard-header">
                <h1><i class="fas fa-crown"></i> Director Dashboard</h1>
                <p>Manage approvals and company operations</p>
            </div>
            <div class="user-card">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <div class="user-details">
                    <h3><?php echo htmlspecialchars($username); ?></h3>
                    <p>Director Role</p>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $totalEmployees; ?></div>
                        <div class="stat-label"><i class="fas fa-users"></i> Total Employees</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingRequests; ?></div>
                        <div class="stat-label"><i class="fas fa-hand-holding-usd"></i> Pending Salary Requests</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $pendingRoleRequests; ?></div>
                        <div class="stat-label"><i class="fas fa-user-check"></i> Pending Role Changes</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $approvedRequests; ?></div>
                        <div class="stat-label"><i class="fas fa-check-circle"></i> Approved This Month</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $rejectedRequests; ?></div>
                        <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected Requests</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Card -->
        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
            </div>
            <div class="quick-actions">
                <a href="salary_approvals.php" class="action-btn">
                    <i class="fas fa-hand-holding-usd"></i>
                    <div>
                        <div>Salary Approvals</div>
                        <small style="opacity: 0.7;">Review & approve salary changes</small>
                    </div>
                    <?php if ($pendingRequests > 0): ?>
                        <span class="badge-notification"><?php echo $pendingRequests; ?></span>
                    <?php endif; ?>
                </a>
                <a href="role_approvals.php" class="action-btn">
                    <i class="fas fa-user-check"></i>
                    <div>
                        <div>Role Changes</div>
                        <small style="opacity: 0.7;">Review & approve role changes</small>
                    </div>
                    <?php if ($pendingRoleRequests > 0): ?>
                        <span class="badge-notification"><?php echo $pendingRoleRequests; ?></span>
                    <?php endif; ?>
                </a>
                <a href="../admin/employees.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <div>
                        <div>View Employees</div>
                        <small style="opacity: 0.7;">Browse all employees</small>
                    </div>
                </a>
                <a href="../admin/reports.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <div>
                        <div>View Reports</div>
                        <small style="opacity: 0.7;">Analytics & reports</small>
                    </div>
                </a>
                <a href="approvals.php" class="action-btn">
                    <i class="fas fa-check-square"></i>
                    <div>
                        <div>All Approvals</div>
                        <small style="opacity: 0.7;">Review all request types</small>
                    </div>
                </a>
                <a href="../admin/departments.php" class="action-btn">
                    <i class="fas fa-building"></i>
                    <div>
                        <div>Departments</div>
                        <small style="opacity: 0.7;">Department management</small>
                    </div>
                </a>
            </div>
        </div>
    </div>

</body>
</html>
