<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has accountant role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!$hasAccountantRole && $_SESSION['role'] !== 'accountant') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';

// Fetch reports data
$selectedReport = $_GET['report'] ?? 'summary';

// Payroll by department
$deptStmt = $db->prepare("
    SELECT 
        d.department_id,
        d.department_name,
        COUNT(e.employee_id) as employee_count,
        SUM(e.basic_salary) as total_salary,
        AVG(e.basic_salary) as avg_salary
    FROM departments d
    LEFT JOIN employees e ON d.department_id = e.department_id
    GROUP BY d.department_id, d.department_name
    ORDER BY total_salary DESC
");
$deptStmt->execute();
$deptData = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

// All employees report
$empStmt = $db->prepare("
    SELECT 
        e.employee_id,
        e.full_name,
        e.designation,
        d.department_name,
        e.employment_type,
        e.basic_salary,
        e.email,
        u.role
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON e.employee_id = u.employee_id
    ORDER BY e.full_name ASC
");
$empStmt->execute();
$empData = $empStmt->fetchAll(PDO::FETCH_ASSOC);

// Salary statistics
$statsStmt = $db->prepare("
    SELECT 
        COUNT(*) as total_employees,
        SUM(basic_salary) as total_payroll,
        AVG(basic_salary) as avg_salary,
        MIN(basic_salary) as min_salary,
        MAX(basic_salary) as max_salary
    FROM employees
");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Monthly payroll statistics
$monthlyStmt = $db->prepare("
    SELECT 
        pr.month,
        pr.year,
        COUNT(ps.payslip_id) as payslips_count,
        SUM(pr.gross_salary) as total_gross,
        SUM(pr.total_deductions) as total_deductions,
        SUM(pr.net_salary) as total_net
    FROM payslips ps
    JOIN payroll pr ON ps.payroll_id = pr.payroll_id
    GROUP BY pr.year, pr.month
    ORDER BY pr.year DESC, pr.month DESC
    LIMIT 12
");
$monthlyStmt->execute();
$monthlyData = $monthlyStmt->fetchAll(PDO::FETCH_ASSOC);

// Deduction breakdown
$deductionStmt = $db->prepare("
    SELECT 
        SUM(tax_deduction) as total_tax,
        SUM(pf_deduction) as total_epf,
        SUM(nps_deduction) as total_nps,
        SUM(professional_tax) as total_pt,
        SUM(other_deductions) as total_other,
        SUM(total_deductions) as grand_total
    FROM payroll
");
$deductionStmt->execute();
$deductions = $deductionStmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Financial Reports - Accountant Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .report-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .report-tab {
            padding: 12px 20px;
            background: white;
            border: 2px solid transparent;
            color: var(--muted);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .report-tab:hover { 
            border-color: var(--accent); 
            color: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
        }
        
        .report-tab.active { 
            background: linear-gradient(135deg, var(--accent), var(--accent-2)); 
            color: white;
            border-color: var(--accent);
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.3);
        }

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
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.15);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            color: var(--accent);
            font-size: 22px;
        }

        .stat-card h4 { 
            font-size: 13px; 
            margin-bottom: 8px; 
            color: var(--muted); 
            font-weight: 600;
        }
        
        .stat-card .value { 
            font-size: 32px; 
            font-weight: 700; 
            color: var(--text); 
            margin-bottom: 5px;
            line-height: 1;
        }
        
        .stat-sub { 
            color: var(--muted); 
            font-size: 12px; 
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .chart-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .chart-title { 
            font-weight: 700; 
            margin-bottom: 20px; 
            font-size: 18px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .chart-title i {
            color: var(--accent);
        }

        .table-wrapper {
            background: white;
            border-radius: 15px;
            overflow: hidden;
        .table-wrapper {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            margin-bottom: 30px;
        }

        .table-header {
            padding: 25px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h3 i {
            color: var(--accent);
        }

        .report-table { 
            width: 100%; 
            border-collapse: collapse; 
        }
        
        .report-table thead {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }
        
        .report-table th { 
            padding: 16px 20px; 
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }
        
        .report-table td { 
            padding: 18px 20px; 
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }
        
        .report-table tbody tr {
            transition: all 0.2s ease;
        }
        
        .report-table tr:hover { 
            background: rgba(102, 126, 234, 0.03); 
        }

        .currency { 
            color: var(--success); 
            font-weight: 700; 
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-employment {
            background: rgba(102, 126, 234, 0.1);
            color: var(--accent);
        }

        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .role-accountant { 
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899; 
        }
        
        .role-director { 
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6; 
        }
        
        .role-administrator { 
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444; 
        }
        
        .role-employee { 
            background: rgba(100, 116, 139, 0.1);
            color: var(--muted); 
        }

        .export-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .filter-section { 
            display: flex; 
            gap: 10px; 
            margin-bottom: 20px; 
            flex-wrap: wrap; 
        }
        
        .filter-section input,
        .filter-section select {
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text);
            font-size: 14px;
            min-width: 180px;
            transition: all 0.2s ease;
        }

        .filter-section input:focus,
        .filter-section select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .section-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .section-title i {
            color: var(--accent);
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-section {
                width: 100%;
            }

            .filter-section input,
            .filter-section select {
                width: 100%;
            }

            .report-table {
                font-size: 13px;
            }

            .report-table th,
            .report-table td {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <main class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Financial Reports</h1>
                <p>Comprehensive payroll analytics and financial insights</p>
            </div>
        </div>

        <!-- Report Tabs -->
        <div class="report-tabs">
            <button class="report-tab <?php echo $selectedReport === 'summary' ? 'active' : ''; ?>" onclick="location.href='?report=summary'">
                <i class="fas fa-chart-pie"></i> Summary
            </button>
            <button class="report-tab <?php echo $selectedReport === 'payroll' ? 'active' : ''; ?>" onclick="location.href='?report=payroll'">
                <i class="fas fa-money-bill-wave"></i> Payroll
            </button>
            <button class="report-tab <?php echo $selectedReport === 'department' ? 'active' : ''; ?>" onclick="location.href='?report=department'">
                <i class="fas fa-building"></i> Departments
            </button>
            <button class="report-tab <?php echo $selectedReport === 'employees' ? 'active' : ''; ?>" onclick="location.href='?report=employees'">
                <i class="fas fa-users"></i> Employees
            </button>
            <button class="report-tab <?php echo $selectedReport === 'deductions' ? 'active' : ''; ?>" onclick="location.href='?report=deductions'">
                <i class="fas fa-receipt"></i> Deductions
            </button>
        </div>

        <?php if ($selectedReport === 'summary'): ?>
            <!-- Summary Report -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h4>Total Employees</h4>
                    <div class="value"><?php echo $stats['total_employees']; ?></div>
                    <div class="stat-sub">Active workforce</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-indian-rupee-sign"></i>
                    </div>
                    <h4>Monthly Payroll</h4>
                    <div class="value">₹<?php echo number_format($stats['total_payroll'], 0); ?></div>
                    <div class="stat-sub">Base salary total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <h4>Average Salary</h4>
                    <div class="value">₹<?php echo number_format($stats['avg_salary'], 0); ?></div>
                    <div class="stat-sub">Per employee</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-exchange-alt"></i>
                    </div>
                    <h4>Salary Range</h4>
                    <div class="value" style="font-size: 20px;">₹<?php echo number_format($stats['min_salary'], 0); ?> - ₹<?php echo number_format($stats['max_salary'], 0); ?></div>
                    <div class="stat-sub">Min to Max range</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice-dollar"></i>
                    </div>
                    <h4>Total Payslips</h4>
                    <div class="value"><?php 
                        $totalPayslips = $db->query("SELECT COUNT(*) FROM payslips")->fetchColumn();
                        echo $totalPayslips;
                    ?></div>
                    <div class="stat-sub">Generated to date</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <h4>Current Period</h4>
                    <div class="value" style="font-size: 24px;"><?php echo date('M Y'); ?></div>
                    <div class="stat-sub"><?php echo date('d M, l'); ?></div>
                </div>
            </div>

            <!-- Monthly Trend Chart -->
            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-chart-line"></i> Monthly Gross Salary Trend</div>
                <canvas id="trendChart" height="80"></canvas>
            </div>

            <div style="margin-bottom: 20px;">
                <a href="?report=summary&export=pdf" class="export-btn">
                    <i class="fas fa-file-pdf"></i> Export PDF
                </a>
            </div>

        <?php elseif ($selectedReport === 'payroll'): ?>
            <!-- Payroll Report -->
            <h2 class="section-title"><i class="fas fa-money-bill-wave"></i> Payroll Analytics</h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-wallet"></i>
                    </div>
                    <h4>Total Gross Salary</h4>
                    <div class="value" style="color: var(--success);">₹<?php 
                        $totalGross = $db->query("SELECT SUM(gross_salary) FROM payroll")->fetchColumn();
                        echo number_format($totalGross, 0);
                    ?></div>
                    <div class="stat-sub">All time earnings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-minus-circle"></i>
                    </div>
                    <h4>Total Deductions</h4>
                    <div class="value" style="color: #ef4444;">₹<?php 
                        $totalDed = $db->query("SELECT SUM(total_deductions) FROM payroll")->fetchColumn();
                        echo number_format($totalDed, 0);
                    ?></div>
                    <div class="stat-sub">All time deductions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h4>Total Net Salary</h4>
                    <div class="value currency">₹<?php 
                        $totalNet = $db->query("SELECT SUM(net_salary) FROM payroll")->fetchColumn();
                        echo number_format($totalNet, 0);
                    ?></div>
                    <div class="stat-sub">Take-home amount</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <h4>Payslips Generated</h4>
                    <div class="value"><?php echo $db->query("SELECT COUNT(*) FROM payslips")->fetchColumn(); ?></div>
                    <div class="stat-sub">Total processed</div>
                </div>
            </div>

            <div class="table-wrapper">
                <div class="table-header">
                    <h3 style="margin: 0;"><i class="fas fa-history"></i> Recent Payroll Records</h3>
                    <a href="?report=payroll&export=csv" class="export-btn"><i class="fas fa-download"></i> CSV</a>
                </div>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th style="text-align: right;">Payslips</th>
                            <th style="text-align: right;">Gross Salary</th>
                            <th style="text-align: right;">Deductions</th>
                            <th style="text-align: right;">Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($monthlyData as $month): ?>
                            <tr>
                                <td><strong><?php echo $month['month'] . ' ' . $month['year']; ?></strong></td>
                                <td style="text-align: right;"><?php echo $month['payslips_count']; ?></td>
                                <td style="text-align: right;" class="currency">₹<?php echo number_format($month['total_gross'], 2); ?></td>
                                <td style="text-align: right; color: #f87171;">-₹<?php echo number_format($month['total_deductions'], 2); ?></td>
                                <td style="text-align: right;" class="currency">₹<?php echo number_format($month['total_net'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($selectedReport === 'department'): ?>
            <!-- Department Report -->
            <h2 class="section-title"><i class="fas fa-building"></i> Department-wise Payroll</h2>

            <div class="table-wrapper">
                <div class="table-header">
                    <h3 style="margin: 0;">Payroll by Department</h3>
                    <a href="?report=department&export=csv" class="export-btn"><i class="fas fa-download"></i> CSV</a>
                </div>
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th style="text-align: right;">Employees</th>
                            <th style="text-align: right;">Total Salary</th>
                            <th style="text-align: right;">Average</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($deptData as $dept): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['department_name']); ?></strong></td>
                                <td style="text-align: right;"><?php echo $dept['employee_count']; ?></td>
                                <td style="text-align: right;" class="currency">₹<?php echo number_format($dept['total_salary'], 2); ?></td>
                                <td style="text-align: right;" class="currency">₹<?php echo number_format($dept['avg_salary'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($selectedReport === 'employees'): ?>
            <!-- Employee Report -->
            <h2 class="section-title"><i class="fas fa-users"></i> Employee Details</h2>

            <div class="filter-section">
                <input type="text" id="searchInput" placeholder="Search employees...">
                <select id="deptFilter">
                    <option value="">All Departments</option>
                    <?php
                    $depts = array_unique(array_map(function($e) { return $e['department_name']; }, $empData));
                    sort($depts);
                    foreach($depts as $dept) {
                        if ($dept) echo "<option value='$dept'>$dept</option>";
                    }
                    ?>
                </select>
                <a href="?report=employees&export=csv" class="export-btn"><i class="fas fa-download"></i> CSV</a>
            </div>

            <div class="table-wrapper">
                <table class="report-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Designation</th>
                            <th>Department</th>
                            <th>Type</th>
                            <th style="text-align: right;">Salary</th>
                            <th>Role</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($empData as $emp): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                                <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                                <td><span class="badge badge-employment"><i class="fas fa-briefcase"></i> <?php echo $emp['employment_type']; ?></span></td>
                                <td style="text-align: right;" class="currency">₹<?php echo number_format($emp['basic_salary'], 2); ?></td>
                                <td><span class="role-badge role-<?php echo strtolower($emp['role'] ?? 'employee'); ?>"><i class="fas fa-user-shield"></i> <?php echo ucfirst($emp['role'] ?? 'Employee'); ?></span></td>
                                <td style="font-size: 12px; color: var(--muted);"><?php echo htmlspecialchars($emp['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($selectedReport === 'deductions'): ?>
            <!-- Deductions Report -->
            <h2 class="section-title"><i class="fas fa-receipt"></i> Deduction Summary</h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-receipt"></i>
                    </div>
                    <h4>Income Tax (TDS)</h4>
                    <div class="value" style="color: #ef4444;">₹<?php echo number_format($deductions['total_tax'], 2); ?></div>
                    <div class="stat-sub">Tax deductions</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-piggy-bank"></i>
                    </div>
                    <h4>EPF Contribution</h4>
                    <div class="value" style="color: #ef4444;">₹<?php echo number_format($deductions['total_epf'], 2); ?></div>
                    <div class="stat-sub">12% of basic salary</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-university"></i>
                    </div>
                    <h4>NPS Contribution</h4>
                    <div class="value" style="color: #ef4444;">₹<?php echo number_format($deductions['total_nps'], 2); ?></div>
                    <div class="stat-sub">10% of basic salary</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-id-badge"></i>
                    </div>
                    <h4>Professional Tax</h4>
                    <div class="value" style="color: #ef4444;">₹<?php echo number_format($deductions['total_pt'], 2); ?></div>
                    <div class="stat-sub">State tax</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-hand-holding-usd"></i>
                    </div>
                    <h4>Other Deductions</h4>
                    <div class="value" style="color: #ef4444;">₹<?php echo number_format($deductions['total_other'], 2); ?></div>
                    <div class="stat-sub">Miscellaneous</div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); color: #ef4444;">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h4>Total Deductions</h4>
                    <div class="value" style="color: #ef4444;">₹<?php echo number_format($deductions['grand_total'], 2); ?></div>
                    <div class="stat-sub">Combined total</div>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-chart-pie"></i> Deduction Breakdown</div>
                <div style="max-width: 500px; margin: 0 auto;">
                    <canvas id="deductionChart"></canvas>
                </div>
            </div>

        <?php endif; ?>
    </main>

    <script>
        // Filtering for employees
        const searchInput = document.getElementById('searchInput');
        const deptFilter = document.getElementById('deptFilter');

        if (searchInput && deptFilter) {
            function filterTable() {
                const searchTerm = searchInput.value.toLowerCase();
                const deptVal = deptFilter.value.toLowerCase();
                const tbody = document.querySelector('table tbody');
                if (!tbody) return;

                Array.from(tbody.getElementsByTagName('tr')).forEach(row => {
                    const name = row.cells[0] ? row.cells[0].textContent.toLowerCase() : '';
                    const dept = row.cells[2] ? row.cells[2].textContent.toLowerCase() : '';
                    const match = name.includes(searchTerm) && (!deptVal || dept.includes(deptVal));
                    row.style.display = match ? '' : 'none';
                });
            }

            searchInput.addEventListener('input', filterTable);
            deptFilter.addEventListener('change', filterTable);
        }

        // Trend chart
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($m) { return $m['month'] . ' ' . $m['year']; }, array_reverse($monthlyData))); ?>,
                    datasets: [{
                        label: 'Gross Salary',
                        data: <?php echo json_encode(array_map(function($m) { return round($m['total_gross']); }, array_reverse($monthlyData))); ?>,
                        borderColor: '#0ea5e9',
                        backgroundColor: 'rgba(14,165,233,0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(31,41,55,0.5)' } },
                        x: { ticks: { color: '#9ca3af' }, grid: { color: 'rgba(31,41,55,0.5)' } }
                    }
                }
            });
        }

        // Deduction pie chart
        const dedCtx = document.getElementById('deductionChart');
        if (dedCtx) {
            new Chart(dedCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Tax (TDS)', 'EPF', 'NPS', 'Profession Tax', 'Other'],
                    datasets: [{
                        data: [
                            <?php echo $deductions['total_tax']; ?>,
                            <?php echo $deductions['total_epf']; ?>,
                            <?php echo $deductions['total_nps']; ?>,
                            <?php echo $deductions['total_pt']; ?>,
                            <?php echo $deductions['total_other']; ?>
                        ],
                        backgroundColor: ['#ef4444', '#f97316', '#f59e0b', '#667eea', '#10b981']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    aspectRatio: 2,
                    plugins: {
                        legend: { 
                            position: 'right',
                            labels: { 
                                color: '#1e293b',
                                padding: 15,
                                font: {
                                    size: 13,
                                    weight: '500'
                                }
                            } 
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
