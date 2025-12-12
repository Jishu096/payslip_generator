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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --sidebar-bg: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            --sidebar-hover: rgba(255,255,255,0.15);
            --sidebar-active: rgba(255,255,255,0.25);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Roboto", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Sidebar */
        .sidebar {
            width: 280px;
            height: 100vh;
            background: var(--sidebar-bg);
            position: fixed;
            padding: 30px 20px;
            color: #fff;
            overflow-y: auto;
            z-index: 100;
            transition: all 0.3s ease;
        }

        .sidebar-header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 25px;
            border-bottom: 2px solid rgba(255,255,255,0.1);
        }

        .sidebar-header h3 {
            font-family: "Roboto", sans-serif;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .sidebar-header p {
            font-size: 13px;
            opacity: 0.9;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 18px;
            color: #fff;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link i {
            width: 24px;
            font-size: 18px;
            margin-right: 12px;
        }

        .nav-link:hover {
            background: var(--sidebar-hover);
            transform: translateX(5px);
        }

        .nav-link.active {
            background: var(--sidebar-active);
            font-weight: 600;
        }

        /* Main Content */
        .main-content {
            margin-left: 280px;
            padding: 30px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }

        /* Theme Toggle */

        /* Top Bar */
        .top-bar {
            background: var(--bg-primary);
            padding: 25px 30px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            border: 1px solid var(--border-color);
        }

        .top-bar-left h1 {
            font-family: "Roboto", sans-serif;
            font-size: 28px;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .top-bar-left p {
            color: var(--text-tertiary);
            font-size: 14px;
        }

        .top-bar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            background: var(--bg-secondary);
            border-radius: 50px;
            border: 2px solid var(--border-color);
        }

        .avatar {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-weight: 700;
            font-size: 20px;
        }

        .user-details h4 {
            font-size: 15px;
            color: var(--text-primary);
            margin-bottom: 2px;
        }

        .user-details p {
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .btn-logout {
            padding: 12px 24px;
            background: #e74c3c;
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-logout:hover {
            background: #c0392b;
            transform: translateY(-2px);
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
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
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 20px;
        }

        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .stat-value {
            font-family: "Roboto", sans-serif;
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-tertiary);
            font-weight: 500;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 24px;
        }

        .content-card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .content-card h3 {
            font-family: "Roboto", sans-serif;
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: var(--text-tertiary);
            font-size: 14px;
        }

        .info-value {
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }

        .calendar-placeholder {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-tertiary);
        }

        .calendar-placeholder i {
            font-size: 64px;
            opacity: 0.3;
            margin-bottom: 20px;
        }

        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .top-bar-right {
                flex-direction: column;
            }

            .user-info {
                justify-content: center;
            }

            .btn-logout {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-wallet"></i> Payroll</h3>
            <p>Employee Portal</p>
        </div>

        <nav>
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-home"></i> Dashboard
            </a>

            <a href="employee_profile.php" class="nav-link">
                <i class="fas fa-user"></i> My Profile
            </a>

            <a href="view_payslips.php" class="nav-link">
                <i class="fas fa-file-invoice"></i> Payslips
            </a>

            <a href="attendance.php" class="nav-link">
                <i class="fas fa-calendar-check"></i> Attendance
            </a>

            <a href="edit_profile.php" class="nav-link">
                <i class="fas fa-edit"></i> Edit Profile
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="top-bar-left">
                <h1>Welcome back, <?= htmlspecialchars($employeeName) ?>! 👋</h1>
                <p>Here's what's happening with your account today<?php if ($hasMultipleRoles) echo " <span style='font-size: 12px; color: #667eea; font-weight: 600;'>[Multi-Role User]</span>"; ?></p>
            </div>

            <div class="top-bar-right">
                <?php if ($hasMultipleRoles): ?>
                <div style="padding: 12px 20px; background: #667eea; border-radius: 50px; color: white; font-size: 13px; font-weight: 600;">
                    <i class="fas fa-crown"></i> Multi-Role: <?= count($userRoles) ?> roles
                </div>
                <?php endif; ?>

                <div class="user-info">
                    <div class="avatar"><?= $avatarLetter ?></div>
                    <div class="user-details">
                        <h4><?= htmlspecialchars($employeeName) ?></h4>
                        <p>ID: <?= htmlspecialchars($employeeId) ?></p>
                    </div>
                </div>

                <a href="../auth/logout.php" class="btn-logout">
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

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Personal Information -->
            <div class="content-card">
                <h3><i class="fas fa-id-card"></i> Personal Information</h3>
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

            <!-- Attendance Calendar -->
            <div class="content-card">
                <h3><i class="fas fa-calendar-alt"></i> Attendance Overview</h3>
                <div class="calendar-placeholder">
                    <i class="fas fa-calendar"></i>
                    <p>Attendance calendar coming soon</p>
                    <p style="font-size: 12px; margin-top: 10px;">Track your daily attendance and leave history</p>
                </div>
            </div>
        </div>

    </div>

</body>
</html>
