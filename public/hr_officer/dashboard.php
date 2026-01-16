<?php
session_start();

// Multi-role authentication support
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasHRRole = in_array('hr_officer', $userRoles);

if (!isset($_SESSION['user_id']) || (!$hasHRRole && $_SESSION['role'] !== 'hr_officer')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'HR Officer';

require_once __DIR__ . '/../../app/Config/database.php';
$conn = getDBConnection();

// Fetch dashboard statistics
$stats = [];

// Pending verifications - attendance sheets awaiting HR verification
try {
    $stmt = $conn->query("
        SELECT COUNT(DISTINCT date) as count 
        FROM attendance 
        WHERE verification_status = 'Pending' OR verification_status IS NULL
    ");
    $stats['pending_verifications'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $stats['pending_verifications'] = 0;
}

// Attendance issues - missing entries, disputes
try {
    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE (status = 'Absent' AND leave_type IS NULL) 
        OR remarks LIKE '%dispute%'
        AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ");
    $stats['attendance_issues'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $stats['attendance_issues'] = 0;
}

// Disputes - attendance disputes flagged by employees
try {
    $stmt = $conn->query("
        SELECT COUNT(*) as count 
        FROM attendance 
        WHERE remarks LIKE '%dispute%' AND date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stats['disputes'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $stats['disputes'] = 0;
}

// Total employees
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'Active'");
    $stats['total_employees'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
} catch (Exception $e) {
    $stats['total_employees'] = 0;
}

// Recent uploaded attendance sheets (pending verification)
try {
    $stmt = $conn->query("
        SELECT 
            date,
            COUNT(*) as total_entries,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_count,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_count,
            verification_status
        FROM attendance
        WHERE verification_status = 'Pending' OR verification_status IS NULL
        GROUP BY date, verification_status
        ORDER BY date DESC
        LIMIT 5
    ");
    $pendingSheets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingSheets = [];
}

// Recent attendance issues
try {
    $stmt = $conn->query("
        SELECT 
            a.attendance_id,
            e.full_name,
            e.department,
            a.date,
            a.status,
            a.remarks
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE (a.status = 'Absent' AND a.leave_type IS NULL) 
        OR a.remarks LIKE '%dispute%'
        ORDER BY a.date DESC
        LIMIT 5
    ");
    $attendanceIssues = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $attendanceIssues = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Officer Dashboard</title>
    <?php include 'includes/hr_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }
        .stat-card .icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 30px;
        }
        .stat-card .content h3 {
            font-size: 32px;
            color: #1e293b;
            margin-bottom: 5px;
        }
        .stat-card .content p {
            color: #64748b;
            font-size: 14px;
        }
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .action-btn {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }
        .action-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
        .action-btn i {
            font-size: 32px;
        }
        .action-btn span {
            font-size: 15px;
            font-weight: 600;
        }
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th {
            background: #f8fafc;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #64748b;
            border-bottom: 2px solid #e2e8f0;
        }
        table td {
            padding: 12px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        table tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .badge-pending {
            background: #fef3c7;
            color: #92400e;
        }
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_navbar.php'; ?>
    
    <div class="container">
        <?php include 'includes/hr_sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-tachometer-alt"></i> HR Officer Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Verify attendance and manage workforce records.</p>
            </div>

            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['pending_verifications']); ?></h3>
                        <p>Pending Verifications</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['attendance_issues']); ?></h3>
                        <p>Attendance Issues</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-flag"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['disputes']); ?></h3>
                        <p>Disputes</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="content">
                        <h3><?php echo number_format($stats['total_employees']); ?></h3>
                        <p>Active Employees</p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <h2 style="margin-bottom: 15px; color: #1e293b;">Quick Actions</h2>
            <div class="quick-actions">
                <a href="verify_attendance.php" class="action-btn">
                    <i class="fas fa-check-double"></i>
                    <span>Verify Attendance</span>
                </a>
                <a href="leave_management.php" class="action-btn">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Leave Management</span>
                </a>
                <a href="manual_entry.php" class="action-btn">
                    <i class="fas fa-edit"></i>
                    <span>Manual Entry</span>
                </a>
                <a href="employee_records.php" class="action-btn">
                    <i class="fas fa-folder-open"></i>
                    <span>Employee Records</span>
                </a>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Pending Attendance Sheets -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-file-alt"></i> Pending Attendance Sheets
                        </div>
                        <a href="verify_attendance.php" style="color: #667eea; text-decoration: none; font-size: 14px;">View All →</a>
                    </div>
                    <?php if (!empty($pendingSheets)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Total</th>
                                    <th>Present</th>
                                    <th>Absent</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pendingSheets as $sheet): ?>
                                <tr>
                                    <td><strong><?php echo date('M d, Y', strtotime($sheet['date'])); ?></strong></td>
                                    <td><?php echo $sheet['total_entries']; ?></td>
                                    <td style="color: #10b981;"><?php echo $sheet['present_count']; ?></td>
                                    <td style="color: #ef4444;"><?php echo $sheet['absent_count']; ?></td>
                                    <td>
                                        <span class="badge badge-pending">
                                            <?php echo $sheet['verification_status'] ?? 'Pending'; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #64748b; padding: 20px;">
                            <i class="fas fa-check-circle" style="font-size: 48px; color: #10b981; margin-bottom: 10px; display: block;"></i>
                            All attendance sheets verified!
                        </p>
                    <?php endif; ?>
                </div>

                <!-- Attendance Issues -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">
                            <i class="fas fa-exclamation-circle"></i> Recent Issues
                        </div>
                    </div>
                    <?php if (!empty($attendanceIssues)): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Date</th>
                                    <th>Issue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceIssues as $issue): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($issue['full_name']); ?></strong>
                                        <br><small style="color: #64748b;"><?php echo htmlspecialchars($issue['department']); ?></small>
                                    </td>
                                    <td><?php echo date('M d', strtotime($issue['date'])); ?></td>
                                    <td>
                                        <?php if (stripos($issue['remarks'], 'dispute') !== false): ?>
                                            <span class="badge" style="background: #fee2e2; color: #991b1b;">Dispute</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #fef3c7; color: #92400e;">Missing Leave</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p style="text-align: center; color: #64748b; padding: 20px;">No attendance issues</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/hr_scripts.php'; ?>
</body>
</html>
