<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../auth/login.php");
    exit;
}

// Load DB Connection
require_once __DIR__ . "/../../app/Config/database.php";
require_once __DIR__ . "/../../app/Models/Employee.php";

$db = new Database();
$conn = $db->connect();

$userId = $_SESSION['user_id'] ?? null;
$employeeName = $_SESSION['employee_name'] ?? "Employee";
$employeeId = $_SESSION['employee_id'] ?? "";

// Fetch employee details if not in session
if (empty($employeeName) && $employeeId) {
    $empModel = new Employee();
    $emp = $empModel->getEmployeeById($employeeId);
    if ($emp) {
        $employeeName = $emp['full_name'];
    }
}

// Avatar first letter
$avatarLetter = strtoupper(substr($employeeName, 0, 1));

// Detect active page
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee Dashboard</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: #f5f6fa;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            padding: 20px;
            color: #fff;
        }

        .sidebar h3 {
            text-align: center;
            margin-bottom: 35px;
            font-weight: 700;
        }

        .sidebar a {
            display: block;
            padding: 12px 15px;
            color: #fff;
            text-decoration: none;
            border-radius: 6px;
            margin-bottom: 12px;
            transition: 0.2s ease-in-out;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.25);
        }

        .active-link {
            background: rgba(255,255,255,0.35) !important;
            font-weight: 600;
        }

        /* Main */
        .main {
            margin-left: 270px;
            padding: 30px;
        }

        /* Top Bar */
        .top-bar {
            background: #fff;
            padding: 15px 25px;
            border-radius: 10px;
            box-shadow: 0px 3px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* Avatar */
        .avatar {
            width: 42px;
            height: 42px;
            background: #6c5ce7;
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-weight: bold;
            font-size: 20px;
            margin-right: 15px;
        }

        /* Cards */
        .card-box {
            background: #fff;
            padding: 22px;
            border-radius: 12px;
            box-shadow: 0px 2px 6px rgba(0,0,0,0.1);
            transition: 0.3s;
        }

        .card-box:hover {
            transform: translateY(-4px);
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }

        /* Calendar Placeholder */
        .calendar-box {
            background: #fff;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0px 2px 6px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar">
    <h3><i class="fas fa-file-invoice-dollar"></i> Payslip</h3>

    <a href="employee_dashboard.php"
       class="<?= ($currentPage == 'employee_dashboard.php') ? 'active-link' : '' ?>">
       <i class="fas fa-home"></i> Dashboard
    </a>

    <a href="employee_profile.php"
       class="<?= ($currentPage == 'employee_profile.php') ? 'active-link' : '' ?>">
       <i class="fas fa-user"></i> My Profile
    </a>

    <a href="view_payslips.php"
       class="<?= ($currentPage == 'view_payslips.php') ? 'active-link' : '' ?>">
       <i class="fas fa-file-invoice"></i> Payslips
    </a>

    <a href="attendance.php"
       class="<?= ($currentPage == 'attendance.php') ? 'active-link' : '' ?>">
       <i class="fas fa-calendar-check"></i> Attendance
    </a>
    <a href="edit_profile.php"
        class="<?= ($currentPage == 'edit_profile.php') ? 'active-link' : '' ?>">
        <i class="fas fa-edit"></i> Edit Profile
    </a>

</div>

<!-- Main Content -->
<div class="main">

    <!-- Top Bar -->
    <div class="top-bar">
        <h4>Welcome, <?= htmlspecialchars($employeeName) ?></h4>

        <div class="d-flex align-items-center">

            <div class="avatar"><?= $avatarLetter ?></div>

            <span style="font-size: 14px; margin-right: 15px;">
                ID: <strong><?= htmlspecialchars($employeeId) ?></strong>
            </span>

            <a href="../auth/logout.php" class="btn btn-danger btn-sm">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </div>

    <!-- Dashboard Cards -->
    <div class="grid">

        <div class="card-box">
            <div class="text-primary" style="font-size: 30px;"><i class="fas fa-money-bill-wave"></i></div>
            <h4>₹45,000</h4>
            <p>Current Month Salary</p>
        </div>

        <div class="card-box">
            <div class="text-success" style="font-size: 30px;"><i class="fas fa-calendar-check"></i></div>
            <h4>22 Days</h4>
            <p>Days Present This Month</p>
        </div>

        <div class="card-box">
            <div class="text-warning" style="font-size: 30px;"><i class="fas fa-calendar-times"></i></div>
            <h4>12</h4>
            <p>Leave Balance</p>
        </div>

        <div class="card-box">
            <div class="text-info" style="font-size: 30px;"><i class="fas fa-file-invoice"></i></div>
            <h4>8 Payslips</h4>
            <p>Total Payslips Generated</p>
        </div>

    </div>

    <!-- Attendance Calendar Placeholder -->
    <div class="calendar-box">
        <h5><i class="fas fa-calendar-alt"></i> Attendance Calendar</h5>
        <p style="color: #666;">(Calendar UI will be added later — ready for integration)</p>
    </div>

</div>

</body>
</html>
