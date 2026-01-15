<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/Attendance.php";

$employeeModel = new Employee();
$attendanceModel = new Attendance();

// Get all employees
$employees = $employeeModel->getAllEmployees();

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    $date = $_POST['attendance_date'] ?? date('Y-m-d');
    $attendanceData = $_POST['attendance'] ?? [];
    
    $savedCount = 0;
    foreach ($attendanceData as $employeeId => $status) {
        if ($attendanceModel->markAttendance($employeeId, $date, $status)) {
            $savedCount++;
        }
    }
    
    if ($savedCount > 0) {
        $success = true;
    } else {
        $error = "Failed to save attendance records.";
    }
}

// Get today's attendance if exists
$today = $_GET['date'] ?? date('Y-m-d');
$todayAttendance = [];

foreach ($employees as $emp) {
    $records = $attendanceModel->getAttendanceByDateRange($emp['employee_id'], $today, $today);
    if (!empty($records)) {
        $todayAttendance[$emp['employee_id']] = $records[0]['status'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance - Admin Portal</title>
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
            font-family: 'Roboto', sans-serif;
            background: #f7fafc;
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

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .breadcrumb i {
            margin: 0 8px;
            font-size: 10px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .card-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .alert i {
            font-size: 18px;
        }

        .toolbar {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select {
            padding: 10px 15px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #718096;
            color: white;
        }

        .btn-secondary:hover {
            background: #4a5568;
        }

        .btn-mark-all {
            background: #48bb78;
            color: white;
        }

        .btn-mark-all:hover {
            background: #38a169;
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
        }

        .attendance-table thead {
            background: #f7fafc;
        }

        .attendance-table th {
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .attendance-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        .attendance-table tbody tr:hover {
            background: #f7fafc;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
        }

        .employee-name {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
        }

        .employee-designation {
            font-size: 12px;
            color: #718096;
        }

        .status-select {
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            min-width: 120px;
        }

        .status-select:focus {
            outline: none;
            border-color: #667eea;
        }

        .status-select.present {
            background: #c6f6d5;
            border-color: #48bb78;
            color: #22543d;
        }

        .status-select.absent {
            background: #fed7d7;
            border-color: #f56565;
            color: #742a2a;
        }

        .status-select.leave {
            background: #bee3f8;
            border-color: #4299e1;
            color: #2c5282;
        }

        .status-select.holiday {
            background: #e2e8f0;
            border-color: #718096;
            color: #2d3748;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            border: 2px solid #e2e8f0;
            text-align: center;
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: #667eea;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .attendance-table {
                font-size: 13px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="breadcrumb">
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Manage Attendance</span>
            </div>
            <h1><i class="fas fa-calendar-check"></i> Manage Attendance</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Attendance saved successfully for <?= date('F d, Y', strtotime($date)) ?>!</span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Main Card -->
        <form method="POST" action="">
            <div class="card">
                <div class="card-header">
                    <h2>
                        <i class="fas fa-users"></i>
                        Mark Attendance
                    </h2>
                    <div style="display: flex; gap: 10px;">
                        <button type="button" class="btn btn-mark-all" onclick="markAllPresent()">
                            <i class="fas fa-check-double"></i> Mark All Present
                        </button>
                        <button type="submit" name="save_attendance" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Attendance
                        </button>
                    </div>
                </div>

                <div class="card-body">
                    <!-- Toolbar -->
                    <div class="toolbar">
                        <div class="form-group" style="flex: 1; max-width: 250px;">
                            <label for="attendance_date">
                                <i class="far fa-calendar"></i> Select Date
                            </label>
                            <input type="date" 
                                   id="attendance_date" 
                                   name="attendance_date" 
                                   value="<?= htmlspecialchars($today) ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   onchange="window.location.href='?date='+this.value">
                        </div>

                        <div class="form-group" style="flex: 1; max-width: 200px;">
                            <label>
                                <i class="fas fa-info-circle"></i> Today
                            </label>
                            <div style="padding: 10px; background: #f7fafc; border-radius: 8px; font-weight: 600;">
                                <?= date('F d, Y', strtotime($today)) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-number"><?= count($employees) ?></div>
                            <div class="stat-label">Total Employees</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="present-count">0</div>
                            <div class="stat-label">Present</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="absent-count">0</div>
                            <div class="stat-label">Absent</div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-number" id="leave-count">0</div>
                            <div class="stat-label">On Leave</div>
                        </div>
                    </div>

                    <!-- Attendance Table -->
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th style="width: 180px;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $index = 1;
                            foreach ($employees as $emp): 
                                $currentStatus = $todayAttendance[$emp['employee_id']] ?? 'present';
                            ?>
                            <tr>
                                <td><?= $index++ ?></td>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            <?php 
                                                $nameParts = explode(' ', $emp['full_name']);
                                                $initials = strtoupper(substr($nameParts[0], 0, 1));
                                                if (count($nameParts) > 1) {
                                                    $initials .= strtoupper(substr($nameParts[count($nameParts) - 1], 0, 1));
                                                }
                                                echo $initials;
                                            ?>
                                        </div>
                                        <div class="employee-details">
                                            <div class="employee-name">
                                                <?= htmlspecialchars($emp['full_name']) ?>
                                            </div>
                                            <div class="employee-designation">
                                                <?= htmlspecialchars($emp['email']) ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($emp['department_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($emp['designation']) ?></td>
                                <td>
                                    <select name="attendance[<?= $emp['employee_id'] ?>]" 
                                            class="status-select <?= strtolower($currentStatus) ?>"
                                            onchange="updateStatusClass(this); updateStats();">
                                        <option value="present" <?= $currentStatus === 'present' ? 'selected' : '' ?>>Present</option>
                                        <option value="absent" <?= $currentStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                                        <option value="leave" <?= $currentStatus === 'leave' ? 'selected' : '' ?>>Leave</option>
                                        <option value="holiday" <?= $currentStatus === 'holiday' ? 'selected' : '' ?>>Holiday</option>
                                    </select>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </form>
    </div>

    <!-- Back Button -->
    <a href="admin_dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script>
        function updateStatusClass(select) {
            const value = select.value;
            select.className = 'status-select ' + value;
        }

        function markAllPresent() {
            const selects = document.querySelectorAll('.status-select');
            selects.forEach(select => {
                select.value = 'present';
                updateStatusClass(select);
            });
            updateStats();
        }

        function updateStats() {
            const selects = document.querySelectorAll('.status-select');
            let present = 0, absent = 0, leave = 0;
            
            selects.forEach(select => {
                const val = select.value;
                if (val === 'present') present++;
                else if (val === 'absent') absent++;
                else if (val === 'leave') leave++;
            });

            document.getElementById('present-count').textContent = present;
            document.getElementById('absent-count').textContent = absent;
            document.getElementById('leave-count').textContent = leave;
        }

        // Initialize stats on page load
        document.addEventListener('DOMContentLoaded', updateStats);
    </script>
</body>
</html>
