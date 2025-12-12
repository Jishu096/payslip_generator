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

// Define salary ranges
$ranges = [
    ['min' => 0, 'max' => 25000, 'label' => 'Below ₹25K', 'color' => '#FF6B6B'],
    ['min' => 25000, 'max' => 50000, 'label' => '₹25K - ₹50K', 'color' => '#4ECDC4'],
    ['min' => 50000, 'max' => 100000, 'label' => '₹50K - ₹100K', 'color' => '#45B7D1'],
    ['min' => 100000, 'max' => 200000, 'label' => '₹100K - ₹200K', 'color' => '#96CEB4'],
    ['min' => 200000, 'max' => PHP_INT_MAX, 'label' => 'Above ₹200K', 'color' => '#DDA15E']
];

// Fetch employees with all details
$stmt = $conn->query("SELECT e.employee_id, e.full_name, e.email, e.designation, e.basic_salary, d.department_name
                      FROM employees e
                      LEFT JOIN departments d ON e.department_id = d.department_id
                      ORDER BY e.basic_salary DESC");
$allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Categorize employees by salary range
$rangeData = [];
foreach ($ranges as $range) {
    $rangeData[$range['label']] = [
        'employees' => [],
        'count' => 0,
        'totalSalary' => 0,
        'avgSalary' => 0,
        'color' => $range['color'],
        'percentage' => 0
    ];
}

foreach ($allEmployees as $emp) {
    foreach ($ranges as $range) {
        if ($emp['basic_salary'] >= $range['min'] && $emp['basic_salary'] < $range['max']) {
            $rangeData[$range['label']]['employees'][] = $emp;
            $rangeData[$range['label']]['count']++;
            $rangeData[$range['label']]['totalSalary'] += $emp['basic_salary'];
            break;
        }
    }
}

// Calculate average salaries and percentages
$totalEmployees = count($allEmployees);
foreach ($rangeData as $label => &$data) {
    if ($data['count'] > 0) {
        $data['avgSalary'] = $data['totalSalary'] / $data['count'];
        $data['percentage'] = ($data['count'] / $totalEmployees) * 100;
    }
}

// Fetch statistics
$stmt = $conn->query("SELECT AVG(basic_salary) as avg_salary, MIN(basic_salary) as min_salary, MAX(basic_salary) as max_salary FROM employees");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Department-wise salary average
$stmt = $conn->query("SELECT d.department_name, AVG(e.basic_salary) as avg_salary, COUNT(e.employee_id) as emp_count
                      FROM departments d
                      LEFT JOIN employees e ON d.department_id = e.department_id
                      GROUP BY d.department_id
                      ORDER BY avg_salary DESC");
$deptSalaries = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Distribution Analysis - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .salary-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .salary-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .salary-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .salary-header-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .back-btn {
            padding: 10px 18px;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .back-btn:hover {
            transform: translateX(-2px);
            box-shadow: var(--card-shadow);
        }

        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-card.gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }

        .stat-card.gradient .stat-label {
            color: rgba(255,255,255,0.8);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-tertiary);
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .stat-subtext {
            font-size: 12px;
            opacity: 0.7;
        }

        /* Salary Range Distribution */
        .distribution-section {
            background-color: var(--bg-primary);
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
            box-shadow: var(--card-shadow);
        }

        .section-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }

        .section-title i {
            font-size: 24px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .range-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .range-card {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            border-left: 4px solid;
            transition: all 0.3s ease;
            position: relative;
        }

        .range-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--card-shadow);
        }

        .range-label {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .range-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 15px;
        }

        .range-stat {
            display: flex;
            flex-direction: column;
        }

        .range-stat-label {
            font-size: 12px;
            color: var(--text-tertiary);
            margin-bottom: 4px;
            font-weight: 500;
        }

        .range-stat-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 18px;
            font-weight: 700;
        }

        .range-progress-bar {
            width: 100%;
            height: 8px;
            background-color: var(--bg-tertiary);
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 8px;
        }

        .range-progress {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .range-percentage {
            font-size: 12px;
            color: var(--text-tertiary);
            font-weight: 500;
        }

        /* Salary Range Table */
        .range-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .range-table thead {
            background-color: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .range-table th {
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-primary);
        }

        .range-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .range-table tbody tr:hover {
            background-color: var(--bg-secondary);
        }

        .dept-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .salary-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .salary-badge.high {
            background-color: #dcfce7;
            color: #166534;
        }


        .salary-badge.medium {
            background-color: #fef3c7;
            color: #92400e;
        }


        .salary-badge.low {
            background-color: #fee2e2;
            color: #991b1b;
        }


        /* Department Salary Comparison */
        .dept-comparison {
            background-color: var(--bg-secondary);
            border-radius: 12px;
            padding: 20px;
            margin-top: 20px;
        }

        .dept-row {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }

        .dept-row:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .dept-name-col {
            min-width: 150px;
            font-weight: 600;
        }

        .dept-bar-container {
            flex: 1;
            margin: 0 20px;
        }

        .dept-bar {
            width: 100%;
            height: 24px;
            background-color: var(--bg-tertiary);
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .dept-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            transition: width 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 11px;
            font-weight: 600;
        }

        .dept-salary {
            min-width: 100px;
            text-align: right;
            font-family: 'Space Grotesk', sans-serif;
            font-weight: 600;
        }

        /* Actions */
        .section-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
        }

        .btn-secondary:hover {
            background-color: var(--border-color);
        }

        /* Footer */
        .report-footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-tertiary);
            font-size: 12px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .salary-container {
                padding: 20px 15px;
            }

            .salary-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .salary-header h1 {
                font-size: 24px;
            }

            .range-cards {
                grid-template-columns: 1fr;
            }

            .range-stats {
                grid-template-columns: 1fr;
            }

            .distribution-section {
                padding: 20px;
            }

            .range-table {
                font-size: 12px;
            }

            .range-table th,
            .range-table td {
                padding: 8px;
            }

            .dept-row {
                flex-wrap: wrap;
            }

            .dept-bar-container {
                margin: 10px 0;
                min-width: 100%;
            }
        }

        @media print {
            .salary-header-controls,
            .section-actions,
            .back-btn {
                display: none;
            }

            body {
                background-color: white;
            }

            .salary-container {
                max-width: 100%;
            }

            .stat-card,
            .distribution-section,
            .range-card {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }
    </style>
</head>
<body>
    <div class="salary-container">
        <!-- Header -->
        <div class="salary-header">
            <div>
                <h1><i class="fas fa-chart-bar"></i> Salary Distribution Analysis</h1>
            </div>
            <div class="salary-header-controls">
                <button class="back-btn" onclick="window.history.back()">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                
            </div>
        </div>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card gradient">
                <div class="stat-label">Total Employees</div>
                <div class="stat-value"><?php echo number_format($totalEmployees); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Average Salary</div>
                <div class="stat-value">₹<?php echo number_format((int)$stats['avg_salary'], 0); ?></div>
                <div class="stat-subtext">Monthly payroll average</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Minimum Salary</div>
                <div class="stat-value">₹<?php echo number_format((int)$stats['min_salary'], 0); ?></div>
                <div class="stat-subtext">Lowest in organization</div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Maximum Salary</div>
                <div class="stat-value">₹<?php echo number_format((int)$stats['max_salary'], 0); ?></div>
                <div class="stat-subtext">Highest in organization</div>
            </div>
        </div>

        <!-- Salary Range Distribution -->
        <div class="distribution-section">
            <div class="section-title">
                <i class="fas fa-sitemap"></i> Salary Range Distribution
            </div>

            <!-- Range Cards -->
            <div class="range-cards">
                <?php foreach ($rangeData as $label => $data): ?>
                <div class="range-card" style="border-left-color: <?php echo $data['color']; ?>;">
                    <div class="range-label" style="color: <?php echo $data['color']; ?>">
                        <?php echo $label; ?>
                    </div>
                    <div class="range-stats">
                        <div class="range-stat">
                            <span class="range-stat-label">Employees</span>
                            <span class="range-stat-value"><?php echo $data['count']; ?></span>
                        </div>
                        <div class="range-stat">
                            <span class="range-stat-label">Avg Salary</span>
                            <span class="range-stat-value">₹<?php echo number_format((int)$data['avgSalary'], 0); ?></span>
                        </div>
                    </div>
                    <div class="range-progress-bar">
                        <div class="range-progress" style="width: <?php echo $data['percentage']; ?>%; background-color: <?php echo $data['color']; ?>;"></div>
                    </div>
                    <div class="range-percentage">
                        <?php echo number_format($data['percentage'], 1); ?>% of workforce
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Detailed Range Table -->
            <table class="range-table">
                <thead>
                    <tr>
                        <th>Salary Range</th>
                        <th>Employees</th>
                        <th>Total Payroll</th>
                        <th>Average Salary</th>
                        <th>% of Workforce</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rangeData as $label => $data): ?>
                    <tr>
                        <td>
                            <span class="salary-badge" style="background-color: <?php echo $data['color']; ?>20; color: <?php echo $data['color']; ?>;">
                                <?php echo $label; ?>
                            </span>
                        </td>
                        <td><strong><?php echo $data['count']; ?></strong></td>
                        <td>₹<?php echo number_format((int)$data['totalSalary'], 0); ?></td>
                        <td>₹<?php echo number_format((int)$data['avgSalary'], 0); ?></td>
                        <td><strong><?php echo number_format($data['percentage'], 1); ?>%</strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Department Salary Comparison -->
        <div class="distribution-section">
            <div class="section-title">
                <i class="fas fa-building"></i> Department Salary Comparison
            </div>

            <table class="range-table">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Employees</th>
                        <th>Average Salary</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($deptSalaries as $dept): ?>
                    <tr>
                        <td><span class="dept-name"><?php echo $dept['department_name'] ?: 'Unassigned'; ?></span></td>
                        <td><strong><?php echo $dept['emp_count']; ?></strong></td>
                        <td>₹<?php echo number_format((int)($dept['avg_salary'] ?? 0), 0); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="dept-comparison">
                <div style="font-weight: 600; margin-bottom: 20px; color: var(--text-primary);">Average Salary by Department (Visual)</div>
                <?php 
                $maxSalary = max(array_map(function($d) { return $d['avg_salary'] ?? 0; }, $deptSalaries));
                foreach ($deptSalaries as $dept): 
                    $percentage = ($maxSalary > 0) ? (($dept['avg_salary'] ?? 0) / $maxSalary) * 100 : 0;
                ?>
                <div class="dept-row">
                    <div class="dept-name-col"><?php echo $dept['department_name'] ?: 'Unassigned'; ?></div>
                    <div class="dept-bar-container">
                        <div class="dept-bar">
                            <div class="dept-bar-fill" style="width: <?php echo $percentage; ?>%;">
                                <?php if ($percentage > 15): ?>
                                    ₹<?php echo number_format((int)($dept['avg_salary'] ?? 0), 0); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="dept-salary">₹<?php echo number_format((int)($dept['avg_salary'] ?? 0), 0); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Employee Details by Range -->
        <div class="distribution-section">
            <div class="section-title">
                <i class="fas fa-users"></i> Employee Details by Salary Range
            </div>

            <?php foreach ($rangeData as $label => $data): ?>
                <?php if ($data['count'] > 0): ?>
                <div style="margin-bottom: 30px; padding-bottom: 30px; border-bottom: 1px solid var(--border-color);">
                    <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: <?php echo $data['color']; ?>; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-block; width: 12px; height: 12px; background-color: <?php echo $data['color']; ?>; border-radius: 3px;"></span>
                        <?php echo $label; ?> (<?php echo $data['count']; ?> employees)
                    </h3>
                    <table class="range-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Email</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Basic Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($data['employees'] as $emp): ?>
                            <tr>
                                <td><strong><?php echo $emp['full_name']; ?></strong></td>
                                <td><?php echo $emp['email']; ?></td>
                                <td><?php echo $emp['designation']; ?></td>
                                <td><?php echo $emp['department_name'] ?: 'Unassigned'; ?></td>
                                <td>
                                    <strong>₹<?php echo number_format((int)$emp['basic_salary'], 0); ?></strong>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <!-- Actions -->
        <div class="distribution-section">
            <div class="section-title">
                <i class="fas fa-download"></i> Export & Actions
            </div>
            <div class="section-actions">
                <button class="btn btn-primary" onclick="window.print();">
                    <i class="fas fa-print"></i> Print Report
                </button>
                <a href="reports.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
            </div>
        </div>

        <!-- Footer -->
        <div class="report-footer">
            <p>Report generated on <?php echo date('F j, Y \a\t g:i A'); ?></p>
            <p>© Payroll Management System - All Rights Reserved</p>
        </div>
    </div>

    <script>
    </script>
</body>
</html>
