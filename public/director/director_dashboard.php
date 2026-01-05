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

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            font-weight: 700;
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
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .stat-info {
            font-size: 13px;
            color: #a0aec0;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            background: #e6fffa;
            color: #0f766e;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 8px;
        }

        .stat-badge.warning {
            background: #fef3c7;
            color: #b45309;
        }

        .stat-badge.danger {
            background: #fee2e2;
            color: #dc2626;
        }

        .actions-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 15px;
        }

        .action-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 20px;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            color: #2d3748;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            border-color: #667eea;
            background: white;
            transform: translateX(5px);
        }

        .action-btn i {
            font-size: 24px;
            color: #667eea;
        }

        .action-text {
            flex: 1;
            margin-left: 15px;
        }

        .action-text h3 {
            font-size: 15px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .action-text p {
            font-size: 12px;
            color: #718096;
        }

        .badge-notification {
            background: #ef4444;
            color: white;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
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
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }

            .logout-btn {
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-crown"></i> Director Dashboard</h1>
            <p>Manage approvals and company operations</p>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 16px;"><?php echo htmlspecialchars($username); ?></div>
                    <div style="opacity: 0.9; font-size: 14px;">Director</div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-users"></i> Total Employees</div>
                <div class="stat-value"><?php echo $totalEmployees; ?></div>
                <div class="stat-info">Active employees</div>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-hand-holding-usd"></i> Pending Salary Requests</div>
                <div class="stat-value"><?php echo $pendingRequests; ?></div>
                <?php if ($pendingRequests > 0): ?>
                    <div class="stat-badge warning"><i class="fas fa-exclamation-circle"></i> Needs Review</div>
                <?php else: ?>
                    <div class="stat-info">No pending requests</div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-user-check"></i> Pending Role Changes</div>
                <div class="stat-value"><?php echo $pendingRoleRequests; ?></div>
                <?php if ($pendingRoleRequests > 0): ?>
                    <div class="stat-badge warning"><i class="fas fa-clock"></i> Awaiting Action</div>
                <?php else: ?>
                    <div class="stat-info">No pending changes</div>
                <?php endif; ?>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-check-circle"></i> Approved This Month</div>
                <div class="stat-value"><?php echo $approvedRequests; ?></div>
                <div class="stat-info">Total approvals</div>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-times-circle"></i> Rejected Requests</div>
                <div class="stat-value"><?php echo $rejectedRequests; ?></div>
                <div class="stat-info">Declined requests</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-section">
            <div class="section-title"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="actions-grid">
                <a href="salary_approvals.php" class="action-btn">
                    <i class="fas fa-hand-holding-usd"></i>
                    <div class="action-text">
                        <h3>Salary Approvals <?php if ($pendingRequests > 0): ?><span class="badge-notification"><?php echo $pendingRequests; ?></span><?php endif; ?></h3>
                        <p>Review & approve salary changes</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="role_approvals.php" class="action-btn">
                    <i class="fas fa-user-check"></i>
                    <div class="action-text">
                        <h3>Role Changes <?php if ($pendingRoleRequests > 0): ?><span class="badge-notification"><?php echo $pendingRoleRequests; ?></span><?php endif; ?></h3>
                        <p>Review & approve role changes</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="employees.php" class="action-btn">
                    <i class="fas fa-users"></i>
                    <div class="action-text">
                        <h3>View Employees</h3>
                        <p>Browse all employees</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="reports.php" class="action-btn">
                    <i class="fas fa-chart-bar"></i>
                    <div class="action-text">
                        <h3>View Reports</h3>
                        <p>Analytics & reports</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="approvals.php" class="action-btn">
                    <i class="fas fa-check-square"></i>
                    <div class="action-text">
                        <h3>All Approvals</h3>
                        <p>Review all request types</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="departments.php" class="action-btn">
                    <i class="fas fa-building"></i>
                    <div class="action-text">
                        <h3>Departments</h3>
                        <p>Department management</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Logout Button -->
    <a href="../auth/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>

</body>
</html>
