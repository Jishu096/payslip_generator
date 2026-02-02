<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// CSV exports
if (isset($_GET['export'])) {
    $type = $_GET['export'];
    $filename = $type . "_report_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');

    switch ($type) {
        case 'payroll':
            fputcsv($out, ['Full Name', 'Email', 'Phone', 'Designation', 'Department', 'Employment Type', 'Basic Salary']);
            $stmt = $conn->query("SELECT e.full_name, e.email, e.phone, e.designation, d.department_name, e.employment_type, e.basic_salary
                                   FROM employees e
                                   LEFT JOIN departments d ON e.department_id = d.department_id
                                   ORDER BY e.full_name ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, $row);
            }
            break;

        case 'employees':
            fputcsv($out, ['Full Name', 'Email', 'Phone', 'Designation', 'Department', 'Employment Type', 'Basic Salary', 'Created At']);
            $stmt = $conn->query("SELECT e.full_name, e.email, e.phone, e.designation, d.department_name, e.employment_type, e.basic_salary, e.created_at
                                   FROM employees e
                                   LEFT JOIN departments d ON e.department_id = d.department_id
                                   ORDER BY e.created_at DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, $row);
            }
            break;

        case 'departments':
            fputcsv($out, ['Department', 'Employees', 'Average Salary']);
            $stmt = $conn->query("SELECT d.department_name, COUNT(e.employee_id) as employee_count, AVG(e.basic_salary) as avg_salary
                                   FROM departments d
                                   LEFT JOIN employees e ON d.department_id = e.department_id
                                   GROUP BY d.department_id
                                   ORDER BY employee_count DESC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, [$row['department_name'], $row['employee_count'], number_format($row['avg_salary'] ?? 0, 2)]);
            }
            break;

        case 'salary_ranges':
            fputcsv($out, ['Range', 'Employees']);
            $stmt = $conn->query("SELECT 
                                  SUM(CASE WHEN basic_salary < 25000 THEN 1 ELSE 0 END) as below_25k,
                                  SUM(CASE WHEN basic_salary >= 25000 AND basic_salary < 50000 THEN 1 ELSE 0 END) as range_25_50k,
                                  SUM(CASE WHEN basic_salary >= 50000 AND basic_salary < 100000 THEN 1 ELSE 0 END) as range_50_100k,
                                  SUM(CASE WHEN basic_salary >= 100000 THEN 1 ELSE 0 END) as above_100k
                                  FROM employees");
            $ranges = $stmt->fetch(PDO::FETCH_ASSOC);
            fputcsv($out, ['Below ₹25,000', $ranges['below_25k'] ?? 0]);
            fputcsv($out, ['₹25,000 - ₹50,000', $ranges['range_25_50k'] ?? 0]);
            fputcsv($out, ['₹50,000 - ₹100,000', $ranges['range_50_100k'] ?? 0]);
            fputcsv($out, ['Above ₹100,000', $ranges['above_100k'] ?? 0]);
            break;

        case 'attendance':
            fputcsv($out, ['Employee Name', 'Department', 'Date', 'Status']);
            $stmt = $conn->query("SELECT e.full_name, d.department_name, a.date, a.status
                                   FROM attendance a
                                   JOIN employees e ON a.employee_id = e.employee_id
                                   LEFT JOIN departments d ON e.department_id = d.department_id
                                   ORDER BY a.date DESC, e.full_name ASC");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                fputcsv($out, $row);
            }
            break;

        default:
            fputcsv($out, ['Unsupported report type']);
            break;
    }

    fclose($out);
    exit;
}

// Fetch statistics for reports
// Total employees
$stmt = $conn->query("SELECT COUNT(*) as count FROM employees");
$totalEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total departments
$stmt = $conn->query("SELECT COUNT(*) as count FROM departments");
$totalDepartments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total users
$stmt = $conn->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Average salary
$stmt = $conn->query("SELECT AVG(basic_salary) as avg_salary FROM employees");
$avgSalary = $stmt->fetch(PDO::FETCH_ASSOC)['avg_salary'] ?? 0;

// Total monthly payroll
$stmt = $conn->query("SELECT SUM(basic_salary) as total FROM employees");
$totalPayroll = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

// Department wise distribution
$stmt = $conn->query("SELECT d.department_name, COUNT(e.employee_id) as count, AVG(e.basic_salary) as avg_salary
                      FROM departments d
                      LEFT JOIN employees e ON d.department_id = e.department_id
                      GROUP BY d.department_id
                      ORDER BY count DESC");
$deptDistribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Salary ranges
$stmt = $conn->query("SELECT 
                      SUM(CASE WHEN basic_salary < 25000 THEN 1 ELSE 0 END) as below_25k,
                      SUM(CASE WHEN basic_salary >= 25000 AND basic_salary < 50000 THEN 1 ELSE 0 END) as range_25_50k,
                      SUM(CASE WHEN basic_salary >= 50000 AND basic_salary < 100000 THEN 1 ELSE 0 END) as range_50_100k,
                      SUM(CASE WHEN basic_salary >= 100000 THEN 1 ELSE 0 END) as above_100k
                      FROM employees");
$salaryRanges = $stmt->fetch(PDO::FETCH_ASSOC);

// Attendance stats
$stmt = $conn->query("SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
                      SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent,
                      SUM(CASE WHEN status = 'leave' THEN 1 ELSE 0 END) as on_leave
                      FROM attendance 
                      WHERE MONTH(date) = MONTH(CURRENT_DATE()) AND YEAR(date) = YEAR(CURRENT_DATE())");
$attendanceStats = $stmt->fetch(PDO::FETCH_ASSOC);
$attendanceRate = ($attendanceStats['total'] > 0) ? round(($attendanceStats['present'] / $attendanceStats['total']) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - Admin Portal</title>
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

        /* Section Title */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin: 35px 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        /* Reports Grid */
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .report-card-header {
            padding: 25px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
            border-bottom: 1px solid #f1f5f9;
        }

        .report-icon {
            width: 55px;
            height: 55px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            flex-shrink: 0;
        }

        .report-icon.purple { background: linear-gradient(135deg, #667eea, #764ba2); }
        .report-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .report-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .report-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .report-icon.pink { background: linear-gradient(135deg, #ec4899, #db2777); }

        .report-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 8px 0;
        }

        .report-info p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
            line-height: 1.5;
        }

        .report-card-body {
            padding: 20px 25px;
            background: #f8fafc;
        }

        .report-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .report-stat {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .report-stat-label {
            font-size: 13px;
            color: var(--muted);
        }

        .report-stat-value {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .report-card-footer {
            padding: 20px 25px;
            display: flex;
            gap: 12px;
        }

        .report-btn {
            flex: 1;
            padding: 12px 16px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .report-btn.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .report-btn.secondary {
            background: #e2e8f0;
            color: var(--text);
        }

        .report-btn:hover {
            transform: translateY(-2px);
        }

        .report-btn.primary:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Analysis Cards */
        .analysis-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .analysis-header {
            padding: 20px 25px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .analysis-header h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .analysis-header h3 i {
            color: #667eea;
        }

        .analysis-body {
            padding: 25px;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 14px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 14px 20px;
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

        .progress-bar {
            height: 8px;
            background: #e2e8f0;
            border-radius: 4px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 4px;
            transition: width 0.5s ease;
        }

        /* Salary Range Cards */
        .salary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
        }

        .salary-card {
            padding: 25px;
            border-radius: 14px;
            color: white;
            transition: all 0.3s ease;
        }

        .salary-card:hover {
            transform: translateY(-5px);
        }

        .salary-card.purple { background: linear-gradient(135deg, #667eea, #764ba2); }
        .salary-card.green { background: linear-gradient(135deg, #10b981, #059669); }
        .salary-card.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .salary-card.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        .salary-card-label {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .salary-card-value {
            font-size: 36px;
            font-weight: 700;
        }

        .salary-card-sub {
            font-size: 13px;
            opacity: 0.8;
            margin-top: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                padding: 30px;
            }

            .reports-grid {
                grid-template-columns: 1fr;
            }

            .salary-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .report-stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <p>Comprehensive reports and data analytics for your organization</p>
        </div>

        <!-- Available Reports -->
        <h2 class="section-title"><i class="fas fa-folder-open"></i> Available Reports</h2>
        
        <div class="reports-grid">
            <!-- Payroll Summary Report -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon purple">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div class="report-info">
                        <h3>Payroll Summary</h3>
                        <p>Complete salary distribution and payroll analysis by department</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="report-stats">
                        <div class="report-stat">
                            <span class="report-stat-label">Monthly Payroll</span>
                            <span class="report-stat-value">₹<?= number_format($totalPayroll, 0) ?></span>
                        </div>
                        <div class="report-stat">
                            <span class="report-stat-label">Average Salary</span>
                            <span class="report-stat-value">₹<?= number_format($avgSalary, 0) ?></span>
                        </div>
                    </div>
                </div>
                <div class="report-card-footer">
                    <a href="payroll_report.php" class="report-btn primary">
                        <i class="fas fa-eye"></i> View Report
                    </a>
                    <a href="reports.php?export=payroll" class="report-btn secondary">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
            </div>

            <!-- Employee Report -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="report-info">
                        <h3>Employee Directory</h3>
                        <p>Complete employee listing with contact and employment details</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="report-stats">
                        <div class="report-stat">
                            <span class="report-stat-label">Total Employees</span>
                            <span class="report-stat-value"><?= $totalEmployees ?></span>
                        </div>
                        <div class="report-stat">
                            <span class="report-stat-label">System Users</span>
                            <span class="report-stat-value"><?= $totalUsers ?></span>
                        </div>
                    </div>
                </div>
                <div class="report-card-footer">
                    <a href="employees.php" class="report-btn primary">
                        <i class="fas fa-eye"></i> View Report
                    </a>
                    <a href="reports.php?export=employees" class="report-btn secondary">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
            </div>

            <!-- Attendance Report -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon blue">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="report-info">
                        <h3>Attendance Reports</h3>
                        <p>Detailed attendance tracking with filters and visualization</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="report-stats">
                        <div class="report-stat">
                            <span class="report-stat-label">This Month</span>
                            <span class="report-stat-value"><?= $attendanceStats['total'] ?? 0 ?> records</span>
                        </div>
                        <div class="report-stat">
                            <span class="report-stat-label">Attendance Rate</span>
                            <span class="report-stat-value"><?= $attendanceRate ?>%</span>
                        </div>
                    </div>
                </div>
                <div class="report-card-footer">
                    <a href="attendance_reports.php" class="report-btn primary">
                        <i class="fas fa-eye"></i> View Report
                    </a>
                    <a href="reports.php?export=attendance" class="report-btn secondary">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
            </div>

            <!-- Department Report -->
            <div class="report-card">
                <div class="report-card-header">
                    <div class="report-icon orange">
                        <i class="fas fa-building"></i>
                    </div>
                    <div class="report-info">
                        <h3>Department Analysis</h3>
                        <p>Department-wise employee distribution and budget allocation</p>
                    </div>
                </div>
                <div class="report-card-body">
                    <div class="report-stats">
                        <div class="report-stat">
                            <span class="report-stat-label">Departments</span>
                            <span class="report-stat-value"><?= $totalDepartments ?></span>
                        </div>
                        <div class="report-stat">
                            <span class="report-stat-label">Avg Dept Size</span>
                            <span class="report-stat-value"><?= $totalDepartments > 0 ? round($totalEmployees / $totalDepartments, 1) : 0 ?></span>
                        </div>
                    </div>
                </div>
                <div class="report-card-footer">
                    <a href="departments.php" class="report-btn primary">
                        <i class="fas fa-eye"></i> View Report
                    </a>
                    <a href="reports.php?export=departments" class="report-btn secondary">
                        <i class="fas fa-download"></i> CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Department Analysis Table -->
        <div class="analysis-card">
            <div class="analysis-header">
                <h3><i class="fas fa-chart-bar"></i> Department-wise Employee & Salary Analysis</h3>
            </div>
            <div class="analysis-body">
                <table>
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Employees</th>
                            <th>Average Salary</th>
                            <th>Distribution</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptDistribution as $dept): 
                            $percent = $totalEmployees > 0 ? ($dept['count'] / $totalEmployees) * 100 : 0;
                        ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($dept['department_name'] ?? 'Unassigned') ?></strong>
                                </td>
                                <td>
                                    <span style="background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1)); color: #667eea; padding: 4px 12px; border-radius: 6px; font-weight: 600;">
                                        <?= $dept['count'] ?>
                                    </span>
                                </td>
                                <td>₹<?= number_format($dept['avg_salary'] ?? 0, 0) ?></td>
                                <td style="width: 200px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div class="progress-bar" style="flex: 1;">
                                            <div class="progress-fill" style="width: <?= $percent ?>%;"></div>
                                        </div>
                                        <span style="color: var(--muted); font-size: 13px; min-width: 45px;">
                                            <?= round($percent, 1) ?>%
                                        </span>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Salary Range Distribution -->
        <h2 class="section-title"><i class="fas fa-coins"></i> Salary Range Distribution</h2>
        
        <div class="salary-grid">
            <div class="salary-card purple">
                <div class="salary-card-label">Below ₹25,000</div>
                <div class="salary-card-value"><?= $salaryRanges['below_25k'] ?? 0 ?></div>
                <div class="salary-card-sub">Employees</div>
            </div>
            <div class="salary-card green">
                <div class="salary-card-label">₹25K - ₹50K</div>
                <div class="salary-card-value"><?= $salaryRanges['range_25_50k'] ?? 0 ?></div>
                <div class="salary-card-sub">Employees</div>
            </div>
            <div class="salary-card orange">
                <div class="salary-card-label">₹50K - ₹100K</div>
                <div class="salary-card-value"><?= $salaryRanges['range_50_100k'] ?? 0 ?></div>
                <div class="salary-card-sub">Employees</div>
            </div>
            <div class="salary-card blue">
                <div class="salary-card-label">Above ₹100K</div>
                <div class="salary-card-value"><?= $salaryRanges['above_100k'] ?? 0 ?></div>
                <div class="salary-card-sub">Employees</div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

</body>
</html>
