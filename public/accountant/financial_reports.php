<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'accountant') {
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
    <style>
        :root {
            --bg: #0b1221;
            --card: #0f172a;
            --muted: #9ca3af;
            --text: #e5e7eb;
            --accent: #0ea5e9;
            --accent-2: #22d3ee;
            --border: #1f2937;
            --success: #34d399;
            --warn: #fbbf24;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.06), transparent 25%),
                        radial-gradient(circle at 80% 0%, rgba(14,165,233,0.08), transparent 30%),
                        var(--bg);
            color: var(--text);
        }

        .main-content { padding: 24px; margin-left: 260px; }
        .page-header h1 { font-family: 'Space Grotesk', sans-serif; letter-spacing: 0.2px; }
        .page-header p { color: var(--muted); }

        .report-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .report-tab {
            padding: 12px 16px;
            background: var(--card);
            border: 1px solid var(--border);
            color: var(--muted);
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            transition: all 0.2s ease;
        }

        .report-tab:hover { border-color: var(--accent); color: var(--accent); }
        .report-tab.active { background: linear-gradient(135deg, var(--accent), var(--accent-2)); color: #0b1221; }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: var(--card);
            border: 1px solid var(--border);
            padding: 16px;
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        .stat-card h4 { font-size: 12px; margin-bottom: 6px; color: var(--muted); text-transform: uppercase; }
        .stat-card .value { font-size: 24px; font-weight: 700; color: var(--text); }
        .stat-sub { color: var(--muted); font-size: 12px; margin-top: 4px; }

        .chart-container {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        .chart-title { font-weight: 700; margin-bottom: 14px; font-size: 15px; }

        .table-wrapper {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            margin-bottom: 20px;
        }

        .table-header {
            padding: 16px 18px;
            background: rgba(14,165,233,0.08);
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }

        .report-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .report-table th, .report-table td { padding: 12px 10px; border-bottom: 1px solid var(--border); text-align: left; }
        .report-table th { background: rgba(14,165,233,0.08); color: var(--muted); font-size: 11px; text-transform: uppercase; }
        .report-table tr:hover { background: rgba(255,255,255,0.02); }

        .currency { color: var(--success); font-weight: 700; }

        .role-badge {
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 700;
            background: rgba(255,255,255,0.05);
        }

        .role-accountant { color: var(--accent-2); }
        .role-director { color: #60a5fa; }
        .role-administrator { color: #f87171; }
        .role-employee { color: var(--muted); }

        .export-btn {
            background: linear-gradient(135deg, var(--success), #10b981);
            color: #0b1221;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .filter-section { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
        .filter-section input,
        .filter-section select {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #0b1325;
            color: var(--text);
            font-size: 13px;
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Financial Reports</h1>
            <p>Comprehensive payroll analytics and financial insights</p>
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
                    <h4><i class="fas fa-users"></i> Total Employees</h4>
                    <div class="value"><?php echo $stats['total_employees']; ?></div>
                    <div class="stat-sub">Active workforce</div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-indian-rupee-sign"></i> Monthly Payroll</h4>
                    <div class="value">₹<?php echo number_format($stats['total_payroll'], 0); ?></div>
                    <div class="stat-sub">Base salary total</div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-chart-line"></i> Average Salary</h4>
                    <div class="value">₹<?php echo number_format($stats['avg_salary'], 0); ?></div>
                    <div class="stat-sub">Per employee</div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-exchange-alt"></i> Salary Range</h4>
                    <div class="value" style="font-size: 16px;">₹<?php echo number_format($stats['min_salary'], 0); ?></div>
                    <div class="stat-sub">Min - Max: ₹<?php echo number_format($stats['max_salary'], 0); ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-file-invoice-dollar"></i> Total Payslips</h4>
                    <div class="value"><?php 
                        $totalPayslips = $db->query("SELECT COUNT(*) FROM payslips")->fetchColumn();
                        echo $totalPayslips;
                    ?></div>
                    <div class="stat-sub">Generated</div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-calendar"></i> This Month</h4>
                    <div class="value"><?php echo date('M Y'); ?></div>
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
            <h2 style="margin-bottom: 16px;"><i class="fas fa-money-bill-wave"></i> Payroll Analytics</h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4>Total Gross Salary (All Time)</h4>
                    <div class="value currency" style="color: var(--success);">₹<?php 
                        $totalGross = $db->query("SELECT SUM(gross_salary) FROM payroll")->fetchColumn();
                        echo number_format($totalGross, 0);
                    ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Deductions (All Time)</h4>
                    <div class="value" style="color: #f87171;">-₹<?php 
                        $totalDed = $db->query("SELECT SUM(total_deductions) FROM payroll")->fetchColumn();
                        echo number_format($totalDed, 0);
                    ?></div>
                </div>
                <div class="stat-card">
                    <h4>Total Net Salary (All Time)</h4>
                    <div class="value currency">₹<?php 
                        $totalNet = $db->query("SELECT SUM(net_salary) FROM payroll")->fetchColumn();
                        echo number_format($totalNet, 0);
                    ?></div>
                </div>
                <div class="stat-card">
                    <h4>Payslips Generated</h4>
                    <div class="value"><?php echo $db->query("SELECT COUNT(*) FROM payslips")->fetchColumn(); ?></div>
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
            <h2 style="margin-bottom: 16px;"><i class="fas fa-building"></i> Department-wise Payroll</h2>

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
            <h2 style="margin-bottom: 16px;"><i class="fas fa-users"></i> Employee Details</h2>

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
                                <td><span style="font-size: 11px; background: rgba(14,165,233,0.12); color: var(--accent); padding: 4px 8px; border-radius: 6px;"><?php echo $emp['employment_type']; ?></span></td>
                                <td style="text-align: right;" class="currency">₹<?php echo number_format($emp['basic_salary'], 2); ?></td>
                                <td><span class="role-badge role-<?php echo strtolower($emp['role'] ?? 'employee'); ?>"><?php echo ucfirst($emp['role'] ?? 'Employee'); ?></span></td>
                                <td style="font-size: 12px; color: var(--muted);"><?php echo htmlspecialchars($emp['email']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        <?php elseif ($selectedReport === 'deductions'): ?>
            <!-- Deductions Report -->
            <h2 style="margin-bottom: 16px;"><i class="fas fa-receipt"></i> Deduction Summary</h2>

            <div class="stats-grid">
                <div class="stat-card">
                    <h4><i class="fas fa-receipt"></i> Income Tax (TDS)</h4>
                    <div class="value" style="color: #f87171;">₹<?php echo number_format($deductions['total_tax'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-piggy-bank"></i> EPF (12%)</h4>
                    <div class="value" style="color: #f87171;">₹<?php echo number_format($deductions['total_epf'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-university"></i> NPS (10%)</h4>
                    <div class="value" style="color: #f87171;">₹<?php echo number_format($deductions['total_nps'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-id-badge"></i> Professional Tax</h4>
                    <div class="value" style="color: #f87171;">₹<?php echo number_format($deductions['total_pt'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-hand-holding-usd"></i> Other Deductions</h4>
                    <div class="value" style="color: #f87171;">₹<?php echo number_format($deductions['total_other'], 2); ?></div>
                </div>
                <div class="stat-card">
                    <h4><i class="fas fa-sum"></i> Total Deductions</h4>
                    <div class="value" style="color: #f87171; font-size: 20px;">₹<?php echo number_format($deductions['grand_total'], 2); ?></div>
                </div>
            </div>

            <div class="chart-container">
                <div class="chart-title"><i class="fas fa-chart-pie"></i> Deduction Breakdown</div>
                <canvas id="deductionChart"></canvas>
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
                        backgroundColor: ['#f87171', '#fb923c', '#fbbf24', '#60a5fa', '#34d399']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: '#e5e7eb' } }
                    }
                }
            });
        }
    </script>
</body>
</html>
