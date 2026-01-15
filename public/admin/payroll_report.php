<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
$conn = getDBConnection();

$username = $_SESSION['username'] ?? 'Admin';

// Fetch payroll summary data
$stmt = $conn->query("SELECT e.*, d.department_name 
                      FROM employees e 
                      LEFT JOIN departments d ON e.department_id = d.department_id 
                      ORDER BY e.basic_salary DESC");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$totalEmployees = count($employees);
$totalPayroll = 0;
$departmentPayroll = [];

foreach ($employees as $emp) {
    $salary = $emp['basic_salary'] ?? 0;
    $totalPayroll += $salary;
    
    $dept = $emp['department_name'] ?? 'Unassigned';
    if (!isset($departmentPayroll[$dept])) {
        $departmentPayroll[$dept] = [
            'total' => 0,
            'count' => 0,
            'employees' => []
        ];
    }
    $departmentPayroll[$dept]['total'] += $salary;
    $departmentPayroll[$dept]['count']++;
    $departmentPayroll[$dept]['employees'][] = $emp;
}

// Sort by total payroll
arsort($departmentPayroll);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Summary Report - Admin</title>
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

        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        :root

        body {
            font-family: "Roboto", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-family: "Roboto", sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-3px);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border-left: 4px solid #667eea;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-tertiary);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h2 {
            font-family: "Roboto", sans-serif;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .department-section {
            margin-bottom: 30px;
        }

        .department-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .department-info h3 {
            font-size: 20px;
            margin-bottom: 8px;
        }

        .department-stats {
            display: flex;
            gap: 30px;
            font-size: 14px;
            opacity: 0.9;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--bg-secondary);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background: var(--bg-secondary);
        }

        .salary-amount {
            font-weight: 600;
            color: #667eea;
        }

        .total-row {
            background: var(--bg-secondary);
            font-weight: 600;
            border-top: 2px solid var(--border-color);
        }

        .total-row td {
            padding: 20px 16px;
        }

        .print-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .print-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        @media print {
            .back-btn, .print-btn {
                display: none;
            }
            body {
                background: white;
            }
            .card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .department-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .department-stats {
                margin-top: 10px;
                gap: 15px;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->

    <div class="container">
        <a href="reports.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Reports
        </a>

        <div class="header">
            <h1><i class="fas fa-chart-bar"></i> Payroll Summary Report</h1>
            <p>Complete salary distribution and payroll analysis by department</p>
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $totalEmployees; ?></div>
                <div class="stat-label"><i class="fas fa-users"></i> Total Employees</div>
            </div>
            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-value" style="color: #10b981;">₹<?php echo number_format($totalPayroll, 2); ?></div>
                <div class="stat-label"><i class="fas fa-wallet"></i> Total Monthly Payroll</div>
            </div>
            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-value" style="color: #f59e0b;">₹<?php echo number_format($totalPayroll / ($totalEmployees ?: 1), 2); ?></div>
                <div class="stat-label"><i class="fas fa-calculator"></i> Average Salary</div>
            </div>
            <div class="stat-card" style="border-left-color: #3b82f6;">
                <div class="stat-value" style="color: #3b82f6;"><?php echo count($departmentPayroll); ?></div>
                <div class="stat-label"><i class="fas fa-sitemap"></i> Departments</div>
            </div>
        </div>

        <!-- Department Breakdown -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-building"></i> Department Payroll Breakdown</h2>
                <button class="print-btn" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>

            <?php foreach ($departmentPayroll as $deptName => $deptData): ?>
                <div class="department-section">
                    <div class="department-header">
                        <div class="department-info">
                            <h3><?php echo htmlspecialchars($deptName); ?></h3>
                            <div class="department-stats">
                                <div><strong><?php echo $deptData['count']; ?></strong> Employees</div>
                                <div>Total: <strong>₹<?php echo number_format($deptData['total'], 2); ?></strong></div>
                                <div>Avg: <strong>₹<?php echo number_format($deptData['total'] / $deptData['count'], 2); ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Employee Name</th>
                                    <th>Designation</th>
                                    <th>Employment Type</th>
                                    <th>Basic Salary</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $deptTotal = 0;
                                foreach ($deptData['employees'] as $emp): 
                                    $deptTotal += $emp['basic_salary'] ?? 0;
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong><br>
                                            <small style="color: var(--text-tertiary);"><?php echo htmlspecialchars($emp['email']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                                        <td>
                                            <span style="background: rgba(102, 126, 234, 0.1); color: #667eea; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                <?php echo ucfirst($emp['employment_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="salary-amount">₹<?php echo number_format($emp['basic_salary'], 2); ?></span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="total-row">
                                    <td colspan="3">Department Total</td>
                                    <td>₹<?php echo number_format($deptTotal, 2); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Grand Total -->
        <div class="card">
            <div class="table-responsive">
                <table>
                    <tfoot>
                        <tr class="total-row" style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);">
                            <td colspan="3" style="font-size: 16px;">
                                <i class="fas fa-check-circle"></i> GRAND TOTAL MONTHLY PAYROLL
                            </td>
                            <td style="font-size: 18px; color: #667eea;">
                                ₹<?php echo number_format($totalPayroll, 2); ?>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Report Info -->
        <div class="card" style="background: var(--bg-secondary); border: 1px dashed var(--border-color);">
            <p style="color: var(--text-tertiary); font-size: 13px;">
                <i class="fas fa-info-circle"></i>
                Report generated on <?php echo date('M d, Y \a\t h:i A'); ?> | 
                Total Employees: <?php echo $totalEmployees; ?> | 
                Total Departments: <?php echo count($departmentPayroll); ?>
            </p>
        </div>
    </div>

</body>
</html>
