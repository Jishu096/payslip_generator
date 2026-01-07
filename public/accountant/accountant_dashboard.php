<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has accountant role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!$hasAccountantRole && $_SESSION['role'] !== 'accountant') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Models/Employee.php";
require_once __DIR__ . '/../../app/Config/database.php';

$employeeModel = new Employee();
$db = getDBConnection();

$username = $_SESSION['username'] ?? 'Accountant';
$totalEmployees = count($employeeModel->getAllEmployees());

// Payroll and payslip stats
$stmt = $db->query("SELECT SUM(basic_salary) AS total_payroll FROM employees");
$totalPayroll = (float)($stmt->fetchColumn() ?? 0);

$payslipCount = (int)$db->query("SELECT COUNT(*) FROM payslips")->fetchColumn();
$payslipMonthCount = (int)$db->query("
    SELECT COUNT(*) 
    FROM payslips 
    WHERE MONTH(generated_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(generated_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

$recentPayslipsStmt = $db->prepare("
    SELECT 
        ps.payslip_id,
        ps.generated_at,
        e.full_name,
        e.designation,
        d.department_name,
        pr.net_salary,
        pr.gross_salary
    FROM payslips ps
    JOIN payroll pr ON ps.payroll_id = pr.payroll_id
    JOIN employees e ON ps.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY ps.generated_at DESC
    LIMIT 6
");
$recentPayslipsStmt->execute();
$recentPayslips = $recentPayslipsStmt->fetchAll(PDO::FETCH_ASSOC);

$monthLabel = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Payroll System</title>
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

        .table-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            font-size: 13px;
            font-weight: 600;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 14px;
            color: #2d3748;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .badge {
            display: inline-block;
            padding: 5px 12px;
            background: #e6fffa;
            color: #0f766e;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
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
            <h1><i class="fas fa-calculator"></i> Accountant Dashboard</h1>
            <p>Payroll Control Center - <?php echo htmlspecialchars($monthLabel); ?></p>
            <div class="user-info">
                <div class="user-avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <div>
                    <div style="font-weight: 600; font-size: 16px;"><?php echo htmlspecialchars($username); ?></div>
                    <div style="opacity: 0.9; font-size: 14px;">Accountant</div>
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-users"></i> Total Employees</div>
                <div class="stat-value"><?php echo $totalEmployees; ?></div>
                <div class="stat-info">Active headcount</div>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-rupee-sign"></i> Monthly Payroll</div>
                <div class="stat-value">₹<?php echo number_format($totalPayroll, 0); ?></div>
                <div class="stat-info">Base salary total</div>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-file-invoice"></i> Total Payslips</div>
                <div class="stat-value"><?php echo $payslipCount; ?></div>
                <div class="stat-badge"><i class="fas fa-check-circle"></i> <?php echo $payslipMonthCount; ?> this month</div>
            </div>

            <div class="stat-card">
                <div class="stat-label"><i class="fas fa-calendar"></i> Current Period</div>
                <div class="stat-value"><?php echo date('M Y'); ?></div>
                <div class="stat-info"><?php echo date('l, d M Y'); ?></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="actions-section">
            <div class="section-title"><i class="fas fa-bolt"></i> Quick Actions</div>
            <div class="actions-grid">
                <a href="generate_payslip.php" class="action-btn">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <div class="action-text">
                        <h3>Generate Payslip</h3>
                        <p>Create new payslips</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="payroll_management.php" class="action-btn">
                    <i class="fas fa-money-bill-wave"></i>
                    <div class="action-text">
                        <h3>Manage Payroll</h3>
                        <p>Adjust salary & bonuses</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="financial_reports.php" class="action-btn">
                    <i class="fas fa-chart-pie"></i>
                    <div class="action-text">
                        <h3>Financial Reports</h3>
                        <p>View analytics</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="../admin/leave_approvals.php" class="action-btn">
                    <i class="fas fa-umbrella-beach"></i>
                    <div class="action-text">
                        <h3>Leave Approvals (HR)</h3>
                        <p>Review leave requests</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>

                <a href="../admin/employees.php" class="action-btn">
                    <i class="fas fa-users-cog"></i>
                    <div class="action-text">
                        <h3>Employee Directory</h3>
                        <p>Manage profiles</p>
                    </div>
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Recent Payslips -->
        <div class="table-section">
            <div class="section-title"><i class="fas fa-clock"></i> Recent Payslips</div>
            <table>
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Net Salary</th>
                        <th>Generated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentPayslips) === 0): ?>
                        <tr><td colspan="5" style="text-align: center; color: #a0aec0;">No payslips generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPayslips as $row): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($row['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($row['designation']); ?></td>
                                <td><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge">₹<?php echo number_format($row['net_salary'], 2); ?></span></td>
                                <td style="color: #718096;"><?php echo date('d M Y, H:i', strtotime($row['generated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Logout Button -->
    <a href="../auth/logout.php" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</body>
</html>
