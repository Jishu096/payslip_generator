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

$db = new Database();
$conn = $db->connect();

$userId = $_SESSION['user_id'] ?? null;
$employeeName = $_SESSION['employee_name'] ?? "Employee";
$employeeId = $_SESSION['employee_id'] ?? "";

// Fetch employee details
$employeeEmail = '';
$employeeDesignation = '';
$employeeDepartment = '';
$employeeBasicSalary = 0;
$employeeCode = '';

if ($employeeId) {
    $empModel = new Employee();
    $emp = $empModel->getEmployeeById($employeeId);
    if ($emp) {
        $employeeName = $emp['full_name'] ?? $employeeName;
        $employeeEmail = $emp['email'] ?? '';
        $employeeDesignation = $emp['designation'] ?? '';
        $employeeDepartment = $emp['department_name'] ?? '';
        $employeeBasicSalary = $emp['basic_salary'] ?? 0;
        $employeeCode = $emp['employee_code'] ?? '';
    }
}

// Get payslip count
try {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM payslips WHERE employee_id = ?");
    $stmt->execute([$employeeId]);
    $payslipCount = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $payslipCount = 0;
}

// Get attendance stats (current month)
try {
    $stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
            SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leaves
        FROM attendance 
        WHERE employee_id = ? 
        AND MONTH(date) = MONTH(CURRENT_DATE())
        AND YEAR(date) = YEAR(CURRENT_DATE())
    ");
    $stmt->execute([$employeeId]);
    $attendance = $stmt->fetch(PDO::FETCH_ASSOC);
    $attendanceTotal = $attendance['total'] ?? 0;
    $attendancePresent = $attendance['present'] ?? 0;
    $attendanceAbsent = $attendance['absent'] ?? 0;
    $attendanceLeaves = $attendance['leaves'] ?? 0;
} catch (Exception $e) {
    $attendanceTotal = 0;
    $attendancePresent = 0;
    $attendanceAbsent = 0;
    $attendanceLeaves = 0;
}

// Get recent payslips
try {
    $stmt = $conn->prepare("
        SELECT 
            ps.payslip_id,
            ps.generated_at,
            pr.month,
            pr.year,
            pr.gross_salary,
            pr.net_salary
        FROM payslips ps
        JOIN payroll pr ON ps.payroll_id = pr.payroll_id
        WHERE ps.employee_id = ?
        ORDER BY ps.generated_at DESC
        LIMIT 5
    ");
    $stmt->execute([$employeeId]);
    $recentPayslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentPayslips = [];
}

// Get leave requests
try {
    $stmt = $conn->prepare("
        SELECT COUNT(*) 
        FROM leave_requests 
        WHERE employee_id = ? 
        AND status = 'Pending'
    ");
    $stmt->execute([$employeeId]);
    $pendingLeaves = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $pendingLeaves = 0;
}

$avatarLetter = strtoupper(substr($employeeName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Dashboard</title>
    <?php include 'includes/employee_styles.php'; ?>
    <style>
        .profile-banner {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            padding: 40px;
            border-radius: 15px;
            margin-bottom: 30px;
            color: white;
            display: flex;
            align-items: center;
            gap: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            font-weight: 700;
            border: 4px solid rgba(255, 255, 255, 0.3);
        }

        .profile-info h1 {
            font-size: 32px;
            margin-bottom: 8px;
        }

        .profile-meta {
            display: flex;
            gap: 25px;
            font-size: 14px;
            opacity: 0.95;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 4px solid var(--accent);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.present { border-left-color: #10b981; }
        .stat-card.leaves { border-left-color: #f59e0b; }
        .stat-card.payslips { border-left-color: #3b82f6; }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-icon.salary { background: #e0e7ff; color: var(--accent); }
        .stat-icon.present { background: #d1fae5; color: #10b981; }
        .stat-icon.leaves { background: #fef3c7; color: #f59e0b; }
        .stat-icon.payslips { background: #dbeafe; color: #3b82f6; }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-desc {
            font-size: 12px;
            color: var(--muted);
        }

        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
        }

        .data-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .card-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: var(--accent);
        }

        .view-all-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .view-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .payslip-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .payslip-item:last-child {
            margin-bottom: 0;
        }

        .payslip-period {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 3px;
        }

        .payslip-date {
            font-size: 12px;
            color: var(--muted);
        }

        .payslip-amount {
            text-align: right;
        }

        .payslip-amount-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
        }

        .payslip-amount-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--success);
        }

        .quick-actions {
            display: grid;
            gap: 12px;
        }

        .action-btn {
            background: white;
            padding: 18px;
            border-radius: 12px;
            text-decoration: none;
            color: var(--text);
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .action-btn:hover {
            transform: translateX(5px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }

        .action-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .action-content h3 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .action-content p {
            font-size: 12px;
            color: var(--muted);
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .profile-banner {
                flex-direction: column;
                text-align: center;
            }

            .profile-meta {
                flex-direction: column;
                gap: 10px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/employee_navbar.php'; ?>
    <?php include 'includes/employee_sidebar.php'; ?>

    <div class="main-content">
        <!-- Profile Banner -->
        <div class="profile-banner">
            <div class="profile-avatar"><?php echo $avatarLetter; ?></div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($employeeName); ?></h1>
                <div class="profile-meta">
                    <div class="profile-meta-item">
                        <i class="fas fa-briefcase"></i>
                        <span><?php echo htmlspecialchars($employeeDesignation); ?></span>
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-building"></i>
                        <span><?php echo htmlspecialchars($employeeDepartment); ?></span>
                    </div>
                    <div class="profile-meta-item">
                        <i class="fas fa-id-badge"></i>
                        <span><?php echo htmlspecialchars($employeeCode); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon salary">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-label">Basic Salary</div>
                <div class="stat-value">₹<?php echo number_format($employeeBasicSalary, 2); ?></div>
                <div class="stat-desc">Monthly basic pay</div>
            </div>

            <div class="stat-card present">
                <div class="stat-icon present">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-label">Present Days</div>
                <div class="stat-value"><?php echo $attendancePresent; ?></div>
                <div class="stat-desc">This month</div>
            </div>

            <div class="stat-card leaves">
                <div class="stat-icon leaves">
                    <i class="fas fa-plane-departure"></i>
                </div>
                <div class="stat-label">Leave Days</div>
                <div class="stat-value"><?php echo $attendanceLeaves; ?></div>
                <div class="stat-desc">This month</div>
            </div>

            <div class="stat-card payslips">
                <div class="stat-icon payslips">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-label">Total Payslips</div>
                <div class="stat-value"><?php echo $payslipCount; ?></div>
                <div class="stat-desc">All time</div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Payslips -->
            <div class="data-card">
                <div class="card-header">
                    <h2><i class="fas fa-history"></i> Recent Payslips</h2>
                    <a href="view_payslips.php" class="view-all-btn">
                        View All <i class="fas fa-arrow-right"></i>
                    </a>
                </div>

                <?php if (empty($recentPayslips)): ?>
                    <div style="text-align: center; padding: 40px; color: var(--muted);">
                        <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                        <h3>No Payslips Yet</h3>
                        <p>Your payslips will appear here</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($recentPayslips as $payslip): ?>
                        <div class="payslip-item">
                            <div class="payslip-info">
                                <div class="payslip-period"><?php echo $payslip['month'] . ' ' . $payslip['year']; ?></div>
                                <div class="payslip-date">Generated: <?php echo date('d M Y', strtotime($payslip['generated_at'])); ?></div>
                            </div>
                            <div class="payslip-amount">
                                <div class="payslip-amount-label">Net Salary</div>
                                <div class="payslip-amount-value">₹<?php echo number_format($payslip['net_salary'], 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Quick Actions -->
            <div class="data-card">
                <div class="card-header">
                    <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
                </div>

                <div class="quick-actions">
                    <a href="view_payslips.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                        <div class="action-content">
                            <h3>My Payslips</h3>
                            <p><?php echo $payslipCount; ?> payslips</p>
                        </div>
                    </a>

                    <a href="attendance.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-calendar-check"></i>
                        </div>
                        <div class="action-content">
                            <h3>Attendance</h3>
                            <p>View my attendance</p>
                        </div>
                    </a>

                    <a href="leave_management.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-plane-departure"></i>
                        </div>
                        <div class="action-content">
                            <h3>Leave Requests</h3>
                            <p><?php echo $pendingLeaves; ?> pending</p>
                        </div>
                    </a>

                    <a href="employee_profile.php" class="action-btn">
                        <div class="action-icon">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="action-content">
                            <h3>My Profile</h3>
                            <p>View & edit profile</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>
