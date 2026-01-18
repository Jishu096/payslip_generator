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
    <?php include '../admin/includes/admin_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
    <style>
        .report-tab-btn {
            background: rgba(255, 255, 255, 0.5);
            border: 1px solid rgba(255,255,255,0.3);
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            color: #4a5568;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        .report-tab-btn:hover { background: white; transform: translateY(-2px); }
        .report-tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            box-shadow: 0 4px 6px rgba(102, 126, 234, 0.25);
        }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .stat-card-custom { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-card-custom h4 { font-size: 13px; color: #718096; margin-bottom: 5px; text-transform: uppercase; }
        .stat-card-custom .value { font-size: 24px; font-weight: 700; color: #2d3748; }
        
        .currency-pos { color: #10b981; }
        .currency-neg { color: #ef4444; }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>
    <?php include 'includes/accountant_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Financial Reports</h1>
            <p>Comprehensive payroll analytics and financial insights</p>
        </div>

        <!-- Report Tabs -->
        <div style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
            <a href="?report=summary" class="report-tab-btn <?php echo $selectedReport === 'summary' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i> Summary
            </a>
            <a href="?report=payroll" class="report-tab-btn <?php echo $selectedReport === 'payroll' ? 'active' : ''; ?>">
                <i class="fas fa-money-bill-wave"></i> Payroll History
            </a>
            <a href="?report=department" class="report-tab-btn <?php echo $selectedReport === 'department' ? 'active' : ''; ?>">
                <i class="fas fa-building"></i> Departments
            </a>
            <a href="?report=employees" class="report-tab-btn <?php echo $selectedReport === 'employees' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i> Employees
            </a>
            <a href="?report=deductions" class="report-tab-btn <?php echo $selectedReport === 'deductions' ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i> Deductions
            </a>
        </div>

        <?php if ($selectedReport === 'summary'): ?>
            <div class="stats-grid">
                <div class="stat-card-custom">
                    <h4>Total Employees</h4>
                    <div class="value"><?php echo $stats['total_employees']; ?></div>
                </div>
                <div class="stat-card-custom">
                    <h4>Monthly Payroll</h4>
                    <div class="value currency-pos">₹<?php echo number_format($stats['total_payroll'], 0); ?></div>
                </div>
                <div class="stat-card-custom">
                    <h4>Avg. Salary</h4>
                    <div class="value">₹<?php echo number_format($stats['avg_salary'], 0); ?></div>
                </div>
                <div class="stat-card-custom">
                    <h4>Salary Range</h4>
                    <div class="value" style="font-size: 18px;">₹<?php echo number_format($stats['min_salary']/1000, 1); ?>k - ₹<?php echo number_format($stats['max_salary']/1000, 1); ?>k</div>
                </div>
            </div>

            <!-- Chart -->
            <div class="glass-card" style="padding: 25px; margin-bottom: 20px;">
                <h3 style="margin-bottom: 15px; color: #4a5568;">Monthly Gross Salary Trend</h3>
                <canvas id="trendChart" height="80"></canvas>
            </div>

        <?php elseif ($selectedReport === 'payroll'): ?>
            <div class="glass-card" style="padding: 25px;">
                <h3 style="margin-bottom: 15px; display: flex; justify-content: space-between;">
                    Recent Payroll Records
                    <button class="btn" onclick="window.print()">Print</button>
                </h3>
                <table style="width: 100%;">
                    <thead>
                        <th>Month</th>
                        <th>Payslips</th>
                        <th>Gross Salary</th>
                        <th>Deductions</th>
                        <th>Net Salary</th>
                    </thead>
                    <tbody>
                        <?php foreach($monthlyData as $row): ?>
                        <tr>
                            <td><strong><?= $row['month'] . ' ' . $row['year'] ?></strong></td>
                            <td><?= $row['payslips_count'] ?></td>
                            <td class="currency-pos">₹<?= number_format($row['total_gross']) ?></td>
                            <td class="currency-neg">-₹<?= number_format($row['total_deductions']) ?></td>
                            <td style="font-weight: bold;">₹<?= number_format($row['total_net']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($selectedReport === 'deductions'): ?>
            <div class="stats-grid">
                <div class="stat-card-custom">
                    <h4><i class="fas fa-receipt"></i> Income Tax (TDS)</h4>
                    <div class="value currency-neg">₹<?php echo number_format($deductions['total_tax'], 0); ?></div>
                </div>
                <div class="stat-card-custom">
                    <h4><i class="fas fa-piggy-bank"></i> EPF</h4>
                    <div class="value currency-neg">₹<?php echo number_format($deductions['total_epf'], 0); ?></div>
                </div>
                <!-- Add more deduction cards as needed -->
                 <div class="stat-card-custom">
                    <h4>Total Deductions</h4>
                    <div class="value currency-neg">₹<?php echo number_format($deductions['grand_total'], 0); ?></div>
                </div>
            </div>
            <div class="glass-card" style="padding: 25px;">
                 <h3 style="margin-bottom: 15px; color: #4a5568;">Deduction Breakdown</h3>
                 <div style="height: 300px; display: flex; justify-content: center;">
                    <canvas id="deductionChart"></canvas>
                 </div>
            </div>
            
        <?php elseif ($selectedReport === 'department'): ?>
            <div class="glass-card" style="padding: 25px;">
                <table>
                    <thead>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Total Payroll</th>
                    </thead>
                    <tbody>
                        <?php foreach($deptData as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['department_name']) ?></strong></td>
                            <td><?= $d['employee_count'] ?></td>
                            <td>₹<?= number_format($d['total_salary']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
        <?php elseif ($selectedReport === 'employees'): ?>
             <div class="glass-card" style="padding: 25px;">
                <h3 style="margin-bottom: 20px;">Employee Database</h3>
                <table>
                    <thead>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Salary</th>
                    </thead>
                    <tbody>
                         <?php foreach($empData as $e): ?>
                        <tr>
                            <td><?= htmlspecialchars($e['full_name']) ?></td>
                            <td><?= htmlspecialchars($e['designation']) ?></td>
                            <td>₹<?= number_format($e['basic_salary']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
             </div>
        <?php endif; ?>

    </main>
    
    <script>
        // Restoring Charts from Original Code
        // Trend Chart
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: <?php echo json_encode(array_map(function($m) { return $m['month'] . ' ' . $m['year']; }, array_reverse($monthlyData))); ?>,
                    datasets: [{
                        label: 'Gross Salary',
                        data: <?php echo json_encode(array_map(function($m) { return round($m['total_gross']); }, array_reverse($monthlyData))); ?>,
                        borderColor: '#667eea',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.3,
                        fill: true
                    }]
                },
                options: { responsive: true }
            });
        }
        
        // Deduction Chart
        const dedCtx = document.getElementById('deductionChart');
        if (dedCtx) {
            new Chart(dedCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Tax', 'EPF', 'NPS', 'PT', 'Other'],
                    datasets: [{
                        data: [
                            <?php echo $deductions['total_tax']; ?>,
                            <?php echo $deductions['total_epf']; ?>,
                            <?php echo $deductions['total_nps']; ?>,
                            <?php echo $deductions['total_pt']; ?>,
                            <?php echo $deductions['total_other']; ?>
                        ],
                        backgroundColor: ['#f87171', '#fb923c', '#fbbf24', '#60a5fa', '#34d399']
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        }
    </script>
</body>
</html>
