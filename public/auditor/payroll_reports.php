<?php
session_start();

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Auditor';
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get filter parameters
$month = $_GET['month'] ?? date('F');
$year = $_GET['year'] ?? date('Y');
$departmentFilter = $_GET['department'] ?? '';
$employeeFilter = $_GET['employee'] ?? '';

// Build query
$sql = "SELECT 
            p.payroll_id,
            p.employee_id,
            e.full_name,
            e.employee_code,
            d.department_name,
            p.month,
            p.year,
            p.basic,
            p.da_amount,
            p.hra_amount,
            p.ta_amount,
            p.da_on_ta,
            p.bonus,
            p.gross_salary,
            p.tax_deduction,
            p.pf_deduction,
            p.nps_deduction,
            p.professional_tax,
            p.other_deductions,
            p.total_deductions,
            p.net_salary,
            p.created_at
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE 1=1";

$params = [];

if ($month && $month !== 'All') {
    $sql .= " AND p.month = ?";
    $params[] = $month;
}

if ($year && $year !== 'All') {
    $sql .= " AND p.year = ?";
    $params[] = $year;
}

if ($departmentFilter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $departmentFilter;
}

if ($employeeFilter) {
    $sql .= " AND p.employee_id = ?";
    $params[] = $employeeFilter;
}

$sql .= " ORDER BY p.year DESC, p.month DESC, e.full_name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $records = [];
    $error = "Error fetching payroll: " . $e->getMessage();
}

// Get departments
try {
    $deptStmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

// Get employees
try {
    $empStmt = $conn->query("SELECT employee_id, full_name, employee_code FROM employees ORDER BY full_name ASC");
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// Calculate stats
$stats = [
    'total' => count($records),
    'total_gross' => array_sum(array_column($records, 'gross_salary')),
    'total_deductions' => array_sum(array_column($records, 'total_deductions')),
    'total_net' => array_sum(array_column($records, 'net_salary'))
];

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reports - Auditor Portal</title>
    <?php include 'includes/auditor_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.gross { border-left-color: #10b981; }
        .stat-card.deductions { border-left-color: #ef4444; }
        .stat-card.net { border-left-color: #3b82f6; }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.gross .stat-value {
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.deductions .stat-value {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.net .stat-value {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--muted);
            font-size: 13px;
        }

        .filter-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .filter-btn, .export-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .export-btn {
            background: #10b981;
            color: white;
        }

        .filter-btn:hover, .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .table-card {
            background: white;
            border-radius: 10px;
            overflow-x: auto;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .table-header {
            padding: 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .data-table thead {
            background: #f8fafc;
        }

        .data-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .data-table td {
            padding: 12px 15px;
            border-top: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .amount {
            text-align: right;
            font-weight: 600;
        }

        .amount.positive {
            color: #10b981;
        }

        .amount.negative {
            color: #ef4444;
        }
    </style>
</head>
<body>
    <?php include 'includes/auditor_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-file-invoice-dollar"></i> Payroll Reports</h1>
                <p>Comprehensive salary and payroll analysis</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 13px; color: var(--muted); margin-bottom: 5px;">Period</div>
                <div style="font-size: 16px; font-weight: 700; color: var(--accent);"><?php echo $month . ' ' . $year; ?></div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stat-card gross">
                <div class="stat-label">Total Gross</div>
                <div class="stat-value">₹<?php echo number_format($stats['total_gross'], 2); ?></div>
            </div>
            <div class="stat-card deductions">
                <div class="stat-label">Total Deductions</div>
                <div class="stat-value">₹<?php echo number_format($stats['total_deductions'], 2); ?></div>
            </div>
            <div class="stat-card net">
                <div class="stat-label">Total Net</div>
                <div class="stat-value">₹<?php echo number_format($stats['total_net'], 2); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month">
                        <option value="All">All Months</option>
                        <?php foreach ($months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year">
                        <option value="All">All Years</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Employee</label>
                    <select name="employee">
                        <option value="">All Employees</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>" <?php echo $employeeFilter == $emp['employee_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo $emp['employee_code']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo count($records); ?> Records Found</h3>
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>

            <?php if (empty($records)): ?>
                <div style="text-align: center; padding: 60px; color: var(--muted);">
                    <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                    <h3>No Records Found</h3>
                    <p>Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="payrollTable">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Employee</th>
                            <th>Code</th>
                            <th>Department</th>
                            <th>Basic</th>
                            <th>DA</th>
                            <th>HRA</th>
                            <th>TA</th>
                            <th>Bonus</th>
                            <th>Gross</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><strong><?php echo $record['month'] . ' ' . $record['year']; ?></strong></td>
                                <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($record['employee_code']); ?></td>
                                <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                <td class="amount">₹<?php echo number_format($record['basic'], 2); ?></td>
                                <td class="amount">₹<?php echo number_format($record['da_amount'], 2); ?></td>
                                <td class="amount">₹<?php echo number_format($record['hra_amount'], 2); ?></td>
                                <td class="amount">₹<?php echo number_format($record['ta_amount'], 2); ?></td>
                                <td class="amount">₹<?php echo number_format($record['bonus'], 2); ?></td>
                                <td class="amount positive">₹<?php echo number_format($record['gross_salary'], 2); ?></td>
                                <td class="amount negative">₹<?php echo number_format($record['total_deductions'], 2); ?></td>
                                <td class="amount" style="font-weight: 700; color: #3b82f6;">₹<?php echo number_format($record['net_salary'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/auditor_scripts.php'; ?>
    <script>
        const recordsData = <?php echo json_encode($records); ?>;

        function exportToCSV() {
            if (recordsData.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['Period', 'Employee', 'Code', 'Department', 'Basic', 'DA', 'HRA', 'TA', 'DA on TA', 'Bonus', 'Gross', 'Tax', 'PF', 'NPS', 'Prof. Tax', 'Other', 'Total Deductions', 'Net Salary'];
            const rows = recordsData.map(r => [
                `${r.month} ${r.year}`,
                r.full_name,
                r.employee_code,
                r.department_name || '',
                r.basic,
                r.da_amount,
                r.hra_amount,
                r.ta_amount,
                r.da_on_ta,
                r.bonus,
                r.gross_salary,
                r.tax_deduction,
                r.pf_deduction,
                r.nps_deduction,
                r.professional_tax,
                r.other_deductions,
                r.total_deductions,
                r.net_salary
            ]);

            let csv = headers.join(',') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `payroll_report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
