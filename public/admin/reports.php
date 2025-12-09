<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .reports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
        }

        .report-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px;
            transition: all 0.3s ease;
        }

        .report-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .report-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .report-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .report-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .report-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .report-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .report-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        .report-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 10px;
        }

        .report-card p {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
        }

        .report-actions {
            display: flex;
            gap: 10px;
        }

        .btn-view, .btn-download {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-view {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-download {
            background: #ecf0f1;
            color: #2c3e50;
        }

        .btn-view:hover, .btn-download:hover {
            transform: translateY(-2px);
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px;
            margin-top: 30px;
        }

        .chart-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .chart-placeholder {
            height: 300px;
            background: linear-gradient(135deg, #667eea20 0%, #764ba220 100%);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #7f8c8d;
        }

        tbody tr:hover {
            background: #f8f9fa !important;
        }

        .btn-download:hover {
            background: #d5dbdb;
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Reports & Analytics</h1>
            <p>View and download various payroll and employee reports</p>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon blue">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <div>
                        <h3>Payroll Summary</h3>
                    </div>
                </div>
                <p>Total monthly payroll expense and salary distribution across departments.</p>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #7f8c8d;">Total Monthly Payroll:</span>
                        <strong style="color: #2c3e50; font-size: 18px;">₹<?php echo number_format($totalPayroll, 2); ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7f8c8d;">Average Salary:</span>
                        <strong style="color: #2c3e50; font-size: 18px;">₹<?php echo number_format($avgSalary, 2); ?></strong>
                    </div>
                </div>
                <div class="report-actions">
                    <a href="payroll_report.php" class="btn-view" style="text-decoration: none; color: white;"><i class="fas fa-eye"></i> View</a>
                    <a href="reports.php?export=payroll" class="btn-download" style="text-decoration: none; color: #2c3e50;"><i class="fas fa-download"></i> CSV</a>
                </div>
            </div>

            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon green">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <h3>Employee Report</h3>
                    </div>
                </div>
                <p>Complete employee directory with contact information, departments, and employment details.</p>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #7f8c8d;">Total Employees:</span>
                        <strong style="color: #2c3e50; font-size: 18px;"><?php echo $totalEmployees; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7f8c8d;">Total Users:</span>
                        <strong style="color: #2c3e50; font-size: 18px;"><?php echo $totalUsers; ?></strong>
                    </div>
                </div>
                <div class="report-actions">
                    <a href="employees.php" class="btn-view" style="text-decoration: none; color: white;"><i class="fas fa-eye"></i> View</a>
                    <a href="reports.php?export=employees" class="btn-download" style="text-decoration: none; color: #2c3e50;"><i class="fas fa-download"></i> CSV</a>
                </div>
            </div>

            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon orange">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h3>Department Report</h3>
                    </div>
                </div>
                <p>Department-wise employee distribution, budget allocation, and salary analysis.</p>
                <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                        <span style="color: #7f8c8d;">Total Departments:</span>
                        <strong style="color: #2c3e50; font-size: 18px;"><?php echo $totalDepartments; ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: #7f8c8d;">Avg Dept Size:</span>
                        <strong style="color: #2c3e50; font-size: 18px;"><?php echo $totalDepartments > 0 ? round($totalEmployees / $totalDepartments, 1) : 0; ?></strong>
                    </div>
                </div>
                <div class="report-actions">
                    <a href="departments.php" class="btn-view" style="text-decoration: none; color: white;"><i class="fas fa-eye"></i> View</a>
                    <a href="reports.php?export=departments" class="btn-download" style="text-decoration: none; color: #2c3e50;"><i class="fas fa-download"></i> CSV</a>
                </div>
            </div>

            <div class="report-card">
                <div class="report-header">
                    <div class="report-icon purple">
                        <i class="fas fa-building"></i>
                    </div>
                    <div>
                        <h3>Salary Distribution</h3>
                    </div>
                </div>
                <p>Analyze employee salary ranges and distribution across different salary brackets.</p>
                <div style="background: #f8f9fa; padding: 12px; border-radius: 8px; margin-bottom: 15px; font-size: 13px;">
                    <div style="margin-bottom: 8px;"><span style="color: #7f8c8d;">Below ₹25K:</span> <strong><?php echo $salaryRanges['below_25k'] ?? 0; ?></strong></div>
                    <div style="margin-bottom: 8px;"><span style="color: #7f8c8d;">₹25-50K:</span> <strong><?php echo $salaryRanges['range_25_50k'] ?? 0; ?></strong></div>
                    <div style="margin-bottom: 8px;"><span style="color: #7f8c8d;">₹50-100K:</span> <strong><?php echo $salaryRanges['range_50_100k'] ?? 0; ?></strong></div>
                    <div><span style="color: #7f8c8d;">Above ₹100K:</span> <strong><?php echo $salaryRanges['above_100k'] ?? 0; ?></strong></div>
                </div>
                <div class="report-actions">
                    <button class="btn-view"><i class="fas fa-eye"></i> View</button>
                    <a href="reports.php?export=salary_ranges" class="btn-download" style="text-decoration: none; color: #2c3e50;"><i class="fas fa-download"></i> CSV</a>
                </div>
            </div>
        </div>

        <!-- Department Analysis -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-bar"></i> Department-wise Employee & Salary Analysis</h3>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #f8f9fa; border-bottom: 2px solid #e0e0e0;">
                            <th style="padding: 12px; text-align: left; color: #2c3e50; font-weight: 600;">Department</th>
                            <th style="padding: 12px; text-align: center; color: #2c3e50; font-weight: 600;">Employees</th>
                            <th style="padding: 12px; text-align: right; color: #2c3e50; font-weight: 600;">Avg Salary</th>
                            <th style="padding: 12px; text-align: right; color: #2c3e50; font-weight: 600;">% of Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deptDistribution as $dept): ?>
                            <tr style="border-bottom: 1px solid #e0e0e0;">
                                <td style="padding: 12px; color: #2c3e50;"><?php echo htmlspecialchars($dept['department_name'] ?? 'Unassigned'); ?></td>
                                <td style="padding: 12px; text-align: center; color: #667eea; font-weight: 600;"><?php echo $dept['count']; ?></td>
                                <td style="padding: 12px; text-align: right; color: #2c3e50;">₹<?php echo number_format($dept['avg_salary'] ?? 0, 2); ?></td>
                                <td style="padding: 12px; text-align: right; color: #7f8c8d;">
                                    <?php echo $totalEmployees > 0 ? round(($dept['count'] / $totalEmployees) * 100, 1) : 0; ?>%
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Salary Distribution Chart -->
        <div class="chart-card">
            <h3><i class="fas fa-chart-pie"></i> Salary Range Distribution</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-top: 20px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px; border-radius: 8px; color: white;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Below ₹25,000</div>
                    <div style="font-size: 28px; font-weight: 600; margin-bottom: 5px;"><?php echo $salaryRanges['below_25k'] ?? 0; ?></div>
                    <div style="font-size: 12px; opacity: 0.8;">Employees</div>
                </div>
                <div style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); padding: 20px; border-radius: 8px; color: white;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">₹25K - ₹50K</div>
                    <div style="font-size: 28px; font-weight: 600; margin-bottom: 5px;"><?php echo $salaryRanges['range_25_50k'] ?? 0; ?></div>
                    <div style="font-size: 12px; opacity: 0.8;">Employees</div>
                </div>
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); padding: 20px; border-radius: 8px; color: white;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">₹50K - ₹100K</div>
                    <div style="font-size: 28px; font-weight: 600; margin-bottom: 5px;"><?php echo $salaryRanges['range_50_100k'] ?? 0; ?></div>
                    <div style="font-size: 12px; opacity: 0.8;">Employees</div>
                </div>
                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); padding: 20px; border-radius: 8px; color: white;">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 8px;">Above ₹100K</div>
                    <div style="font-size: 28px; font-weight: 600; margin-bottom: 5px;"><?php echo $salaryRanges['above_100k'] ?? 0; ?></div>
                    <div style="font-size: 12px; opacity: 0.8;">Employees</div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');

        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
                sidebar.classList.toggle('collapsed');
                mainContent.classList.toggle('expanded');
            });
        }

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (sidebar && !sidebar.contains(event.target) && sidebarToggle && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>

</body>
</html>
