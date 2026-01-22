<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Employee';

require_once "../../app/Models/Attendance.php";
$attendance = new Attendance();
$rows = $attendance->getAttendanceByEmployee($_SESSION['employee_id']);

// Calculate summary
$totalDays = count($rows);
$presentDays = 0;
$absentDays = 0;
$leaveDays = 0;

foreach ($rows as $r) {
    $status = strtolower($r['status'] ?? '');
    if ($status === 'present') $presentDays++;
    elseif ($status === 'absent') $absentDays++;
    elseif ($status === 'leave') $leaveDays++;
}

$attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Payroll System</title>
    <?php include 'includes/employee_styles.php'; ?>
</head>
<body>
    <?php include 'includes/employee_navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Attendance</span>
            </div>
            <h1><i class="fas fa-calendar-check"></i> My Attendance</h1>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card">
                <div class="summary-label">Attendance Rate</div>
                <div class="summary-value"><?= $attendancePercentage ?>%</div>
                <div class="summary-subtext">Overall attendance</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Present Days</div>
                <div class="summary-value success"><?= $presentDays ?></div>
                <div class="summary-subtext">Total present</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Absent Days</div>
                <div class="summary-value danger"><?= $absentDays ?></div>
                <div class="summary-subtext">Total absent</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Leave Days</div>
                <div class="summary-value warning"><?= $leaveDays ?></div>
                <div class="summary-subtext">Total leaves</div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2>Attendance Records</h2>
            </div>

            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Attendance Records</h3>
                    <p>Your attendance records will appear here once they are marked.</p>
                </div>
            <?php else: ?>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($rows as $r): 
                                $date = $r['date'] ?? '';
                                $dayName = date('l', strtotime($date));
                                $formattedDate = date('M d, Y', strtotime($date));
                                $status = strtolower($r['status'] ?? 'unknown');
                            ?>
                            <tr>
                                <td>
                                    <span class="date-text">
                                        <i class="far fa-calendar"></i> <?= $formattedDate ?>
                                    </span>
                                </td>
                                <td><?= $dayName ?></td>
                                <td>
                                    <?php if ($status === 'present'): ?>
                                        <span class="status-badge status-present">
                                            <i class="fas fa-check"></i> Present
                                        </span>
                                    <?php elseif ($status === 'absent'): ?>
                                        <span class="status-badge status-absent">
                                            <i class="fas fa-times"></i> Absent
                                        </span>
                                    <?php elseif ($status === 'leave'): ?>
                                        <span class="status-badge status-leave">
                                            <i class="fas fa-umbrella-beach"></i> Leave
                                        </span>
                                    <?php else: ?>
                                        <span class="status-badge" style="background: #f1f5f9; color: #64748b;">
                                            <?= htmlspecialchars(ucfirst($r['status'] ?? 'Unknown')) ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>