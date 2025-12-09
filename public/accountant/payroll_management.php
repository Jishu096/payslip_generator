<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';

// Fetch all employees with salary details
$stmt = $db->prepare("
    SELECT 
        e.employee_id,
        e.full_name,
        e.designation,
        e.department_id,
        d.department_name,
        e.basic_salary,
        e.employment_type,
        e.email,
        u.role
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN users u ON e.employee_id = u.employee_id
    ORDER BY e.full_name ASC
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Derived metrics for summary cards
$totalEmployees = count($employees);
$totalPayroll = array_reduce($employees, function($sum, $emp) {
    return $sum + (float)($emp['basic_salary'] ?? 0);
}, 0);
$avgBasic = $totalEmployees > 0 ? $totalPayroll / $totalEmployees : 0;
$maxBasic = $totalEmployees > 0 ? max(array_column($employees, 'basic_salary')) : 0;
$departments = array_filter(array_unique(array_map(function($e) { return $e['department_name']; }, $employees)));
$departmentCount = count($departments);

$success = isset($_GET['success']);
$updated = isset($_GET['updated']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Management - Accountant Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
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

        a { color: inherit; text-decoration: none; }

        .main-content {
            padding: 24px;
            margin-left: 260px;
        }

        .page-header h1 { font-family: 'Space Grotesk', sans-serif; letter-spacing: 0.2px; }
        .page-header p { color: var(--muted); }

        .total-summary {
            background: transparent;
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 14px;
        }

        .summary-item {
            padding: 16px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        .summary-item h4 {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .summary-item .value {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .summary-sub { color: var(--muted); font-size: 12px; margin-top: 4px; }

        .payroll-table {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
            overflow: hidden;
        }

        .table-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: rgba(14,165,233,0.08);
            color: var(--text);
        }

        .table-header h2 {
            font-size: 18px;
            margin: 0;
            flex: 1;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            text-align: left;
        }

        th {
            color: var(--muted);
            font-size: 12px;
            letter-spacing: 0.3px;
            text-transform: uppercase;
        }

        td { color: var(--text); font-size: 14px; }

        tr:hover { background: rgba(255,255,255,0.02); }

        .filter-box {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-box input,
        .filter-box select {
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: #0b1325;
            color: var(--text);
            font-size: 13px;
            min-width: 160px;
        }

        .filter-box button {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #0b1221;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
        }

        .role-badge {
            padding: 4px 10px;
            border-radius: 14px;
            font-size: 11px;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.05);
        }

        .role-accountant { color: var(--accent-2); }
        .role-director { color: #60a5fa; }
        .role-administrator { color: #fca5a5; }
        .role-employee { color: var(--muted); }

        .tag {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 12px;
            background: rgba(14,165,233,0.12);
            color: var(--accent);
        }

        .success-banner {
            background: rgba(52,211,153,0.15);
            color: #bbf7d0;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 16px;
            border: 1px solid rgba(52,211,153,0.3);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 12px;
            margin-bottom: 16px;
        }

        .action {
            background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(34,211,238,0.12));
            border: 1px solid var(--border);
            color: var(--text);
            padding: 14px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .action:hover { transform: translateY(-2px); border-color: rgba(34,211,238,0.4); }

        .muted { color: var(--muted); font-size: 13px; }
    </style>
</head>
<body>
    <?php include 'includes/accountant_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-calculator"></i> Payroll Management</h1>
            <p>Manage employee salaries, bonuses, and deductions</p>
        </div>

        <?php if ($success): ?>
            <div class="success-banner">
                <i class="fas fa-check-circle"></i> Salary updated successfully.
            </div>
        <?php elseif ($updated): ?>
            <div class="success-banner">
                <i class="fas fa-check-circle"></i> Payroll entry updated.
            </div>
        <?php elseif ($error): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="total-summary">
            <div class="summary-item">
                <h4><i class="fas fa-users"></i> Total Employees</h4>
                <div class="value"><?php echo $totalEmployees; ?></div>
                <div class="summary-sub">Across <?php echo $departmentCount; ?> departments</div>
            </div>
            <div class="summary-item">
                <h4><i class="fas fa-indian-rupee-sign"></i> Total Monthly Payroll (Basic)</h4>
                <div class="value">₹<?php echo number_format($totalPayroll, 2); ?></div>
                <div class="summary-sub">Avg basic: ₹<?php echo number_format($avgBasic, 2); ?> • Top: ₹<?php echo number_format($maxBasic, 2); ?></div>
            </div>
            <div class="summary-item">
                <h4><i class="fas fa-briefcase"></i> Active Accountants</h4>
                <div class="value">
                    <?php 
                    $accountants = array_filter($employees, function($emp) {
                        return $emp['role'] === 'accountant';
                    });
                    echo count($accountants);
                    ?>
                </div>
                <div class="summary-sub">With portal access</div>
            </div>
            <div class="summary-item">
                <h4><i class="fas fa-calendar"></i> Current Month</h4>
                <div class="value"><?php echo date('M Y'); ?></div>
                <div class="summary-sub">Today: <?php echo date('d M, l'); ?></div>
            </div>
        </div>

        <div class="card quick-actions">
            <a class="action" href="generate_payslip.php">
                <div>
                    <div style="font-weight: 700;">Generate Payslip</div>
                    <div class="muted">Auto-calc with current rates</div>
                </div>
                <i class="fas fa-arrow-right"></i>
            </a>
            <a class="action" href="financial_reports.php">
                <div>
                    <div style="font-weight: 700;">Financial Reports</div>
                    <div class="muted">Download and share</div>
                </div>
                <i class="fas fa-arrow-right"></i>
            </a>
            <a class="action" href="../admin/employees.php">
                <div>
                    <div style="font-weight: 700;">Employee Directory</div>
                    <div class="muted">Update salary records</div>
                </div>
                <i class="fas fa-arrow-right"></i>
            </a>
        </div>

        <!-- Payroll Table -->
        <div class="payroll-table">
            <div class="table-header">
                <h2><i class="fas fa-table"></i> Employee Payroll Details</h2>
                <div class="filter-box">
                    <input type="text" id="searchInput" placeholder="Search employees...">
                    <select id="departmentFilter">
                        <option value="">All Departments</option>
                        <?php
                        $depts = array_unique(array_map(function($e) { return $e['department_name']; }, $employees));
                        sort($depts);
                        foreach($depts as $dept) {
                            if ($dept) echo "<option value='$dept'>$dept</option>";
                        }
                        ?>
                    </select>
                    <select id="roleFilter">
                        <option value="">All Roles</option>
                        <option value="accountant">Accountant</option>
                        <option value="director">Director</option>
                        <option value="administrator">Administrator</option>
                        <option value="employee">Employee</option>
                    </select>
                    <select id="typeFilter">
                        <option value="">All Types</option>
                        <option value="Full-time">Full-time</option>
                        <option value="Part-time">Part-time</option>
                        <option value="Contract">Contract</option>
                    </select>
                    <button id="sortSalary" type="button">Sort by Salary</button>
                </div>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Employment Type</th>
                        <th>Basic Salary</th>
                        <th>Role</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $emp): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                            <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                            <td><span class="tag"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($emp['employment_type']); ?></span></td>
                            <td><span class="tag" style="color:#34d399;background:rgba(52,211,153,0.15);">₹<?php echo number_format($emp['basic_salary'], 2); ?></span></td>
                            <td>
                                <span class="role-badge role-<?php echo strtolower($emp['role'] ?? 'employee'); ?>">
                                    <i class="fas fa-user-shield"></i> <?php echo ucfirst($emp['role'] ?? 'Employee'); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Filtering and sorting
        const searchInput = document.getElementById('searchInput');
        const departmentFilter = document.getElementById('departmentFilter');
        const roleFilter = document.getElementById('roleFilter');
        const typeFilter = document.getElementById('typeFilter');
        const sortSalaryBtn = document.getElementById('sortSalary');
        const table = document.querySelector('table tbody');
        let sortDesc = true;

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const deptFilter = departmentFilter.value.toLowerCase();
            const role = roleFilter.value.toLowerCase();
            const empType = typeFilter.value.toLowerCase();

            Array.from(table.getElementsByTagName('tr')).forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const dept = row.cells[2].textContent.toLowerCase();
                const type = row.cells[3].textContent.toLowerCase();
                const roleVal = row.cells[5].textContent.toLowerCase();
                const matchesSearch = name.includes(searchTerm);
                const matchesDept = !deptFilter || dept.includes(deptFilter);
                const matchesType = !empType || type.includes(empType);
                const matchesRole = !role || roleVal.includes(role);
                row.style.display = (matchesSearch && matchesDept && matchesType && matchesRole) ? '' : 'none';
            });
        }

        function sortBySalary() {
            const rows = Array.from(table.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const aVal = parseFloat(a.cells[4].textContent.replace(/[^0-9.-]+/g, '')) || 0;
                const bVal = parseFloat(b.cells[4].textContent.replace(/[^0-9.-]+/g, '')) || 0;
                return sortDesc ? bVal - aVal : aVal - bVal;
            });
            sortDesc = !sortDesc;
            rows.forEach(r => table.appendChild(r));
        }

        searchInput.addEventListener('input', filterTable);
        departmentFilter.addEventListener('change', filterTable);
        roleFilter.addEventListener('change', filterTable);
        typeFilter.addEventListener('change', filterTable);
        sortSalaryBtn.addEventListener('click', sortBySalary);
    </script>
</body>
</html>
