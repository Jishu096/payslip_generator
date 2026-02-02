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
$db = new Database();
$conn = $db->connect();

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
$highestSalary = 0;
$lowestSalary = PHP_INT_MAX;

foreach ($employees as $emp) {
    $salary = $emp['basic_salary'] ?? 0;
    $totalPayroll += $salary;
    
    if ($salary > $highestSalary) $highestSalary = $salary;
    if ($salary < $lowestSalary && $salary > 0) $lowestSalary = $salary;
    
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

if ($lowestSalary == PHP_INT_MAX) $lowestSalary = 0;

// Sort by total payroll
arsort($departmentPayroll);

$avgSalary = $totalEmployees > 0 ? $totalPayroll / $totalEmployees : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Summary Report - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header h1 i {
            margin-right: 12px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .header-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .header-btn.primary {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .header-btn.secondary {
            background: white;
            color: #667eea;
        }

        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
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
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-value.currency {
            font-size: 24px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            font-weight: 500;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        /* Department Cards */
        .department-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

        .department-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .department-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .department-title {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .department-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }

        .department-title h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 5px 0;
        }

        .department-title p {
            font-size: 14px;
            opacity: 0.9;
            margin: 0;
        }

        .department-stats {
            display: flex;
            gap: 30px;
        }

        .dept-stat {
            text-align: center;
        }

        .dept-stat-value {
            font-size: 24px;
            font-weight: 700;
            display: block;
        }

        .dept-stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text);
            font-size: 14px;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .employee-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 2px 0;
        }

        .employee-info p {
            font-size: 12px;
            color: var(--muted);
            margin: 0;
        }

        .salary-amount {
            font-weight: 700;
            color: #10b981;
            font-size: 15px;
        }

        .emp-type-badge {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
            padding: 5px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }

        .total-row {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }

        .total-row td {
            font-weight: 700;
            color: var(--text);
            padding: 18px 20px;
            border-top: 2px solid #e2e8f0;
        }

        /* Grand Total Card */
        .grand-total-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }

        .grand-total-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .grand-total-icon {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }

        .grand-total-text h3 {
            font-size: 16px;
            font-weight: 500;
            opacity: 0.9;
            margin: 0 0 5px 0;
        }

        .grand-total-text p {
            font-size: 36px;
            font-weight: 700;
            margin: 0;
        }

        .report-meta {
            background: rgba(255,255,255,0.15);
            padding: 15px 25px;
            border-radius: 10px;
            font-size: 13px;
        }

        .report-meta p {
            margin: 5px 0;
            opacity: 0.9;
        }

        /* Print Styles */
        @media print {
            .page-header,
            .sidebar,
            .navbar,
            .header-actions {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .department-card,
            .grand-total-card {
                box-shadow: none;
                border: 1px solid #ddd;
                break-inside: avoid;
            }

            .department-header {
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 30px;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .department-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .department-stats {
                justify-content: center;
            }

            .grand-total-card {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-file-invoice-dollar"></i> Payroll Summary Report</h1>
                <p>Complete salary distribution and payroll analysis by department</p>
            </div>
            <div class="header-actions">
                <a href="reports.php" class="header-btn secondary">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
                <button onclick="window.print()" class="header-btn primary">
                    <i class="fas fa-print"></i> Print Report
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $totalEmployees ?></div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value currency">₹<?= number_format($totalPayroll, 0) ?></div>
                        <div class="stat-label">Total Monthly Payroll</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value currency">₹<?= number_format($avgSalary, 0) ?></div>
                        <div class="stat-label">Average Salary</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calculator"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= count($departmentPayroll) ?></div>
                        <div class="stat-label">Departments</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-sitemap"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Department Breakdown -->
        <?php foreach ($departmentPayroll as $deptName => $deptData): 
            $deptAvg = $deptData['count'] > 0 ? $deptData['total'] / $deptData['count'] : 0;
            $deptPercent = $totalPayroll > 0 ? ($deptData['total'] / $totalPayroll) * 100 : 0;
        ?>
        <div class="department-card">
            <div class="department-header">
                <div class="department-title">
                    <div class="department-icon">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h3><?= htmlspecialchars($deptName) ?></h3>
                        <p><?= number_format($deptPercent, 1) ?>% of total payroll</p>
                    </div>
                </div>
                <div class="department-stats">
                    <div class="dept-stat">
                        <span class="dept-stat-value"><?= $deptData['count'] ?></span>
                        <span class="dept-stat-label">Employees</span>
                    </div>
                    <div class="dept-stat">
                        <span class="dept-stat-value">₹<?= number_format($deptData['total'], 0) ?></span>
                        <span class="dept-stat-label">Total</span>
                    </div>
                    <div class="dept-stat">
                        <span class="dept-stat-value">₹<?= number_format($deptAvg, 0) ?></span>
                        <span class="dept-stat-label">Average</span>
                    </div>
                </div>
            </div>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Designation</th>
                            <th>Employment Type</th>
                            <th style="text-align: right;">Basic Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $deptTotal = 0;
                        foreach ($deptData['employees'] as $emp): 
                            $deptTotal += $emp['basic_salary'] ?? 0;
                            $nameParts = explode(' ', $emp['full_name']);
                            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                        ?>
                            <tr>
                                <td>
                                    <div class="employee-cell">
                                        <div class="employee-avatar"><?= $initials ?></div>
                                        <div class="employee-info">
                                            <h4><?= htmlspecialchars($emp['full_name']) ?></h4>
                                            <p><?= htmlspecialchars($emp['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($emp['designation']) ?></td>
                                <td>
                                    <span class="emp-type-badge">
                                        <?= ucfirst($emp['employment_type']) ?>
                                    </span>
                                </td>
                                <td style="text-align: right;">
                                    <span class="salary-amount">₹<?= number_format($emp['basic_salary'], 2) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr class="total-row">
                            <td colspan="3">
                                <i class="fas fa-calculator" style="color: #667eea; margin-right: 8px;"></i>
                                Department Total
                            </td>
                            <td style="text-align: right; color: #667eea;">
                                ₹<?= number_format($deptTotal, 2) ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Grand Total Card -->
        <div class="grand-total-card">
            <div class="grand-total-info">
                <div class="grand-total-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="grand-total-text">
                    <h3>GRAND TOTAL MONTHLY PAYROLL</h3>
                    <p>₹<?= number_format($totalPayroll, 2) ?></p>
                </div>
            </div>
            <div class="report-meta">
                <p><i class="fas fa-calendar"></i> Generated: <?= date('M d, Y \a\t h:i A') ?></p>
                <p><i class="fas fa-users"></i> Employees: <?= $totalEmployees ?></p>
                <p><i class="fas fa-building"></i> Departments: <?= count($departmentPayroll) ?></p>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

</body>
</html>
