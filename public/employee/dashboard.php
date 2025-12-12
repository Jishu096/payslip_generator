<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has employee role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!$hasEmployeeRole && $_SESSION['role'] !== 'employee') {
    header("Location: ../auth/login.php");
    exit;
}

// Load DB Connection
require_once __DIR__ . "/../../app/Config/database.php";
require_once __DIR__ . "/../../app/Models/Employee.php";
require_once __DIR__ . "/../../app/Helpers/RBACHelper.php";

$db = new Database();
$conn = $db->connect();

$userId = $_SESSION['user_id'] ?? null;
$employeeName = $_SESSION['employee_name'] ?? "Employee";
$employeeId = $_SESSION['employee_id'] ?? "";
$hasMultipleRoles = $_SESSION['has_multiple_roles'] ?? false;

// Fetch employee details
if ($employeeId) {
    $empModel = new Employee();
    $emp = $empModel->getEmployeeById($employeeId);
    if ($emp) {
        $employeeName = $emp['full_name'] ?? $employeeName;
        $employeeEmail = $emp['email'] ?? '';
        $employeeDesignation = $emp['designation'] ?? '';
        $employeeDepartment = $emp['department_name'] ?? '';
        $employeeBasicSalary = $emp['basic_salary'] ?? 0;
    }
}

// Avatar first letter
$avatarLetter = strtoupper(substr($employeeName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
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
            padding: 40px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .header-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .header p {
            font-size: 16px;
            opacity: 0.95;
            margin-top: 8px;
        }

        .user-section {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 700;
            border: 3px solid rgba(255, 255, 255, 0.3);
        }

        .user-info h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .user-info p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        .logout-btn {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
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
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.purple {
            border-top: 4px solid #667eea;
        }

        .stat-card.blue {
            border-top: 4px solid #3b82f6;
        }

        .stat-card.green {
            border-top: 4px solid #10b981;
        }

        .stat-card.orange {
            border-top: 4px solid #f59e0b;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card.purple .stat-icon {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .stat-card.blue .stat-icon {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .stat-card.green .stat-icon {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .stat-card.orange .stat-icon {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }

        .quick-actions {
            margin-bottom: 30px;
        }

        .quick-actions h2 {
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 20px;
        }

        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .action-btn {
            background: white;
            padding: 20px;
            border-radius: 12px;
            text-decoration: none;
            color: #2d3748;
            border: 2px solid #e2e8f0;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 15px;
            font-weight: 600;
        }

        .action-btn:hover {
            border-color: #667eea;
            background: #f7fafc;
            transform: translateY(-2px);
        }

        .action-btn i {
            font-size: 24px;
            color: #667eea;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 25px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            font-weight: 600;
            color: #718096;
            font-size: 14px;
        }

        .info-value {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }

        .calendar-placeholder {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        .calendar-placeholder i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .calendar-placeholder p {
            font-size: 16px;
            font-weight: 500;
            margin: 10px 0;
        }

        .multi-role-badge {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            color: white;
            font-size: 13px;
            font-weight: 600;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                padding: 25px;
            }

            .header h1 {
                font-size: 24px;
            }

            .header-top {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-section {
                width: 100%;
                justify-content: space-between;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .actions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-top">
                <div>
                    <h1><i class="fas fa-tachometer-alt"></i> Welcome back, <?= htmlspecialchars($employeeName) ?>!</h1>
                    <p>Here's what's happening with your account today</p>
                </div>

                <?php if ($hasMultipleRoles): ?>
                    <div class="multi-role-badge">
                        <i class="fas fa-crown"></i> Multi-Role: <?= count($userRoles) ?> roles
                    </div>
                <?php endif; ?>
            </div>

            <div class="user-section">
                <div style="display: flex; align-items: center; gap: 15px;">
                    <div class="user-avatar"><?= $avatarLetter ?></div>
                    <div class="user-info">
                        <h3><?= htmlspecialchars($employeeName) ?></h3>
                        <p>Employee ID: <?= htmlspecialchars($employeeId) ?></p>
                    </div>
                </div>
                <a href="../auth/logout.php" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stat-value">₹<?= number_format($employeeBasicSalary ?? 0) ?></div>
                <div class="stat-label">Basic Salary</div>
            </div>

            <div class="stat-card blue">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-value">22 Days</div>
                <div class="stat-label">Days Present This Month</div>
            </div>

            <div class="stat-card green">
                <div class="stat-icon">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div class="stat-value">12</div>
                <div class="stat-label">Leave Balance</div>
            </div>

            <div class="stat-card orange">
                <div class="stat-icon">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-value">8</div>
                <div class="stat-label">Total Payslips</div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
            <div class="actions-grid">
                <a href="employee_profile.php" class="action-btn">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
                <a href="view_payslips.php" class="action-btn">
                    <i class="fas fa-file-invoice"></i>
                    <span>View Payslips</span>
                </a>
                <a href="attendance.php" class="action-btn">
                    <i class="fas fa-calendar-check"></i>
                    <span>Attendance</span>
                </a>
                <a href="edit_profile.php" class="action-btn">
                    <i class="fas fa-edit"></i>
                    <span>Edit Profile</span>
                </a>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Personal Information -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-id-card"></i>
                    <h3>Personal Information</h3>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-label">Employee ID</span>
                        <span class="info-value"><?= htmlspecialchars($employeeId) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Full Name</span>
                        <span class="info-value"><?= htmlspecialchars($employeeName) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Email</span>
                        <span class="info-value"><?= htmlspecialchars($employeeEmail ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Designation</span>
                        <span class="info-value"><?= htmlspecialchars($employeeDesignation ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Department</span>
                        <span class="info-value"><?= htmlspecialchars($employeeDepartment ?? 'N/A') ?></span>
                    </div>
                </div>
            </div>

            <!-- Attendance Overview -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-calendar-alt"></i>
                    <h3>Attendance Overview</h3>
                </div>
                <div class="card-body">
                    <div class="calendar-placeholder">
                        <i class="fas fa-calendar"></i>
                        <p>Attendance calendar coming soon</p>
                        <p style="font-size: 12px; margin-top: 10px; color: #718096;">Track your daily attendance and leave history</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
