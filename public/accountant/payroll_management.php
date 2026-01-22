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
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
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

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 8px;
        }

        .stat-detail {
            font-size: 12px;
            color: var(--muted);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid var(--border);
        }

        .quick-actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .action-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border: 1px solid rgba(102, 126, 234, 0.2);
            border-radius: 12px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .action-card:hover {
            transform: translateX(5px);
            border-color: var(--accent);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
        }

        .action-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            flex-shrink: 0;
        }

        .action-content h3 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .action-content p {
            font-size: 12px;
            color: var(--muted);
        }

        .data-table-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            overflow: hidden;
        }

        .table-toolbar {
            padding: 25px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
        }

        .toolbar-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .toolbar-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .toolbar-title i {
            color: var(--accent);
        }

        .filter-controls {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-controls input,
        .filter-controls select {
            padding: 10px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text);
            font-size: 14px;
            min-width: 180px;
            transition: all 0.2s ease;
        }

        .filter-controls input:focus,
        .filter-controls select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }

        .data-table th {
            padding: 16px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .data-table td {
            padding: 18px 20px;
            color: var(--text);
            font-size: 14px;
            border-bottom: 1px solid rgba(226, 232, 240, 0.8);
        }

        .data-table tbody tr {
            transition: all 0.2s ease;
        }

        .data-table tbody tr:hover {
            background: rgba(102, 126, 234, 0.03);
        }

        .employee-name {
            font-weight: 600;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .employee-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
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

        .badge-salary {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
            font-weight: 700;
        }

        .badge-role {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .badge-role.admin {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .badge-role.director {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-role.accountant {
            background: rgba(236, 72, 153, 0.1);
            color: #ec4899;
        }

        .success-alert {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .success-alert i {
            font-size: 20px;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .filter-controls {
                width: 100%;
            }

            .filter-controls input,
            .filter-controls select {
                width: 100%;
            }

            .data-table {
                font-size: 13px;
            }

            .data-table th,
            .data-table td {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-calculator"></i> Payroll Management</h1>
                <p>Manage employee salaries, bonuses, and deductions</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="success-alert">
                <i class="fas fa-check-circle"></i>
                <span>Salary updated successfully!</span>
            </div>
        <?php elseif ($updated): ?>
            <div class="success-alert">
                <i class="fas fa-check-circle"></i>
                <span>Payroll entry updated successfully!</span>
            </div>
        <?php elseif ($error): ?>
            <div class="success-alert" style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1)); border-color: rgba(239, 68, 68, 0.3); color: #dc2626;">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
            </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-label">Total Employees</div>
                <div class="stat-value"><?php echo $totalEmployees; ?></div>
                <div class="stat-detail">Across <?php echo $departmentCount; ?> departments</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
                <div class="stat-label">Total Monthly Payroll</div>
                <div class="stat-value">₹<?php echo number_format($totalPayroll, 0); ?></div>
                <div class="stat-detail">Avg: ₹<?php echo number_format($avgBasic, 0); ?> • Top: ₹<?php echo number_format($maxBasic, 0); ?></div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-label">Active Accountants</div>
                <div class="stat-value">
                    <?php 
                    $accountants = array_filter($employees, function($emp) {
                        return $emp['role'] === 'accountant';
                    });
                    echo count($accountants);
                    ?>
                </div>
                <div class="stat-detail">With portal access</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-label">Current Period</div>
                <div class="stat-value"><?php echo date('M Y'); ?></div>
                <div class="stat-detail"><?php echo date('d M, l'); ?></div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions-grid">
            <a class="action-card" href="generate_payslip.php">
                <div class="action-icon">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="action-content">
                    <h3>Generate Payslip</h3>
                    <p>Create employee payslips with auto-calculations</p>
                </div>
            </a>
            
            <a class="action-card" href="financial_reports.php">
                <div class="action-icon">
                    <i class="fas fa-chart-line"></i>
                </div>
                <div class="action-content">
                    <h3>Financial Reports</h3>
                    <p>View analytics and download reports</p>
                </div>
            </a>
            
            <a class="action-card" href="../admin/employees.php">
                <div class="action-icon">
                    <i class="fas fa-user-edit"></i>
                </div>
                <div class="action-content">
                    <h3>Manage Employees</h3>
                    <p>Update salary and employee records</p>
                </div>
            </a>
        </div>

        <!-- Payroll Data Table -->
        <div class="data-table-container">
            <div class="table-toolbar">
                <div class="toolbar-top">
                    <h2 class="toolbar-title">
                        <i class="fas fa-table-list"></i>
                        Employee Payroll Records
                    </h2>
                </div>
                
                <div class="filter-controls">
                    <input type="text" id="searchInput" placeholder="🔍 Search employees...">
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
                    <button id="sortSalary" type="button" class="filter-btn">
                        <i class="fas fa-sort-amount-down"></i>
                        Sort by Salary
                    </button>
                </div>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Designation</th>
                        <th>Department</th>
                        <th>Employment Type</th>
                        <th>Basic Salary</th>
                        <th>Role</th>
                        <th>Contact</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($employees as $emp): 
                        $initials = strtoupper(substr($emp['full_name'], 0, 1));
                        $role = strtolower($emp['role'] ?? 'employee');
                        $roleClass = ($role === 'administrator') ? 'admin' : $role;
                    ?>
                        <tr>
                            <td>
                                <div class="employee-name">
                                    <div class="employee-avatar"><?php echo $initials; ?></div>
                                    <strong><?php echo htmlspecialchars($emp['full_name']); ?></strong>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                            <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                            <td>
                                <span class="badge badge-employment">
                                    <i class="fas fa-briefcase"></i>
                                    <?php echo htmlspecialchars($emp['employment_type']); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-salary">
                                    ₹<?php echo number_format($emp['basic_salary'], 2); ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-role <?php echo $roleClass; ?>">
                                    <i class="fas fa-user-shield"></i>
                                    <?php echo ucfirst($emp['role'] ?? 'Employee'); ?>
                                </span>
                            </td>
                            <td style="font-size: 13px; color: var(--muted);">
                                <?php echo htmlspecialchars($emp['email']); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Enhanced filtering and sorting
        const searchInput = document.getElementById('searchInput');
        const departmentFilter = document.getElementById('departmentFilter');
        const roleFilter = document.getElementById('roleFilter');
        const typeFilter = document.getElementById('typeFilter');
        const sortSalaryBtn = document.getElementById('sortSalary');
        const table = document.querySelector('.data-table tbody');
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
            sortSalaryBtn.innerHTML = sortDesc 
                ? '<i class="fas fa-sort-amount-down"></i> Sort by Salary' 
                : '<i class="fas fa-sort-amount-up"></i> Sort by Salary';
            
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
