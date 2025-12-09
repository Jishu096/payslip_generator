<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Director Dashboard - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
        }

        .sidebar h3 {
            margin-bottom: 30px;
            font-size: 20px;
            text-align: center;
        }

        .sidebar a {
            display: block;
            color: white;
            text-decoration: none;
            padding: 12px 15px;
            margin-bottom: 10px;
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .sidebar a:hover {
            background: rgba(255,255,255,0.2);
            transform: translateX(5px);
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .header h1 {
            color: #2c3e50;
            font-size: 28px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 5px solid #667eea;
        }

        .stat-card h3 {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #2c3e50;
        }

        .logout-btn {
            background: #e74c3c;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: #c0392b;
        }
    </style>
</head>
<body>

    <div class="sidebar">
        <h3><i class="fas fa-crown"></i> Director</h3>
        <a href="director_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
        <a href="salary_approvals.php">
            <i class="fas fa-hand-holding-usd"></i> Salary Approvals
            <?php if ($pendingRequests > 0): ?>
                <span style="background: #ff9800; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; margin-left: 5px;">
                    <?php echo $pendingRequests; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="role_approvals.php">
            <i class="fas fa-user-check"></i> Role Changes
            <?php if ($pendingRoleRequests > 0): ?>
                <span style="background: #0c5377; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; margin-left: 5px;">
                    <?php echo $pendingRoleRequests; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="role_approvals.php">
            <i class="fas fa-user-check"></i> Role Changes
            <?php if ($pendingRoleRequests > 0): ?>
                <span style="background: #0c5377; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; margin-left: 5px;">
                    <?php echo $pendingRoleRequests; ?>
                </span>
            <?php endif; ?>
        </a>
        <a href="approvals.php"><i class="fas fa-check-circle"></i> Approvals</a>
        <a href="../admin/employees.php"><i class="fas fa-users"></i> Employees</a>
        <a href="../admin/reports.php"><i class="fas fa-chart-bar"></i> Reports</a>
        <hr style="margin: 20px 0; opacity: 0.3;">
        <a href="../index.php?page=logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>

    <div class="main-content">
        <div class="header">
            <div>
                <h1><i class="fas fa-crown"></i> Director Dashboard</h1>
                <p style="color: #7f8c8d; margin-top: 5px;">Manage employee approvals and company operations</p>
            </div>
            <div class="user-info">
                <div>
                    <p style="color: #2c3e50; font-weight: 600;"><?php echo htmlspecialchars($username); ?></p>
                    <p style="color: #7f8c8d; font-size: 12px;">Director</p>
                </div>
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <h3><i class="fas fa-users"></i> Total Employees</h3>
                <div class="value"><?php echo $totalEmployees; ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid <?php echo $pendingRequests > 0 ? '#ff9800' : '#2ecc71'; ?>;">
                <h3><i class="fas fa-hand-holding-usd"></i> Pending Salary Requests</h3>
                <div class="value" style="color: <?php echo $pendingRequests > 0 ? '#ff9800' : '#2ecc71'; ?>;"><?php echo $pendingRequests; ?></div>
            </div>
            <div class="stat-card" style="border-left: 4px solid <?php echo $pendingRoleRequests > 0 ? '#0c5377' : '#2ecc71'; ?>;">
                <h3><i class="fas fa-user-check"></i> Pending Role Changes</h3>
                <div class="value" style="color: <?php echo $pendingRoleRequests > 0 ? '#0c5377' : '#2ecc71'; ?>;"><?php echo $pendingRoleRequests; ?></div>
            </div>
            <div class="stat-card">
                <h3><i class="fas fa-check-circle"></i> Total Approvals</h3>
                <div class="value"><?php 
                    $stmt = $db->prepare("SELECT COUNT(*) as approved_count FROM salary_change_requests WHERE status = 'approved'");
                    $stmt->execute();
                    echo $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];
                ?></div>
            </div>
        </div>

        <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
            <h3><i class="fas fa-tasks"></i> Quick Actions</h3>
            <p style="color: #7f8c8d; margin: 15px 0;">
                <a href="salary_approvals.php" style="color: #667eea; text-decoration: none;">Review Salary Changes</a> • 
                <a href="role_approvals.php" style="color: #667eea; text-decoration: none;">Review Role Changes</a> • 
                <a href="approvals.php" style="color: #667eea; text-decoration: none;">Review Approvals</a> • 
                <a href="../admin/employees.php" style="color: #667eea; text-decoration: none;">View Employees</a> • 
                <a href="../admin/reports.php" style="color: #667eea; text-decoration: none;">View Reports</a>
            </p>
        </div>
    </div>

</body>
</html>
