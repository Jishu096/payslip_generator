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
    <?php include '../admin/includes/admin_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card-simple {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .stat-card-simple h4 { color: #718096; font-size: 13px; text-transform: uppercase; margin-bottom: 5px; }
        .stat-card-simple .value { font-size: 26px; font-weight: 700; color: #2d3748; }
        
        .filter-bar {
            background: white;
            padding: 15px;
            border-radius: 12px 12px 0 0;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-bar input, .filter-bar select {
            padding: 8px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
        }
        .filter-bar button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>
    <?php include 'includes/accountant_sidebar.php'; ?>
    
    <main class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-money-bill-wave"></i> Payroll Management</h1>
            <p>Manage employee salaries, bonuses, and deductions</p>
        </div>

        <?php if ($success): ?>
            <div style="background:#f0fff4; color:#2f855a; padding:15px; border-radius:8px; margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Salary updated successfully.
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card-simple">
                <h4>Total Employees</h4>
                <div class="value"><?= $totalEmployees ?></div>
            </div>
            <div class="stat-card-simple">
                <h4>Monthly Payroll</h4>
                <div class="value" style="color: #059669;">₹<?= number_format($totalPayroll) ?></div>
                <div style="font-size:12px; color:#A0AEC0">Avg: ₹<?= number_format($avgBasic) ?></div>
            </div>
            <div class="stat-card-simple">
                 <h4>Departments</h4>
                 <div class="value"><?= $departmentCount ?></div>
            </div>
        </div>

        <!-- Table Container -->
        <div class="glass-card" style="padding: 0; overflow: hidden;">
            <div class="filter-bar">
                <input type="text" id="searchInput" placeholder="Search employees...">
                <select id="departmentFilter">
                    <option value="">All Departments</option>
                    <?php
                    $depts = array_unique(array_map(function($e) { return $e['department_name']; }, $employees));
                    sort($depts);
                    foreach($depts as $dept) { if ($dept) echo "<option value='$dept'>$dept</option>"; }
                    ?>
                </select>
                <select id="typeFilter">
                    <option value="">All Types</option>
                    <option value="Full-time">Full-time</option>
                    <option value="Part-time">Part-time</option>
                    <option value="Contract">Contract</option>
                </select>
                <button id="sortSalary" type="button"><i class="fas fa-sort-amount-down"></i> Sort Salary</button>
            </div>

            <table style="width: 100%;">
                <thead>
                    <tr style="background: #f7fafc;">
                        <th style="padding: 15px; text-align: left; color: #718096; font-size: 13px;">Employee Name</th>
                        <th style="padding: 15px; text-align: left; color: #718096;">Department</th>
                        <th style="padding: 15px; text-align: left; color: #718096;">Type</th>
                        <th style="padding: 15px; text-align: left; color: #718096;">Basic Salary</th>
                        <th style="padding: 15px; text-align: left; color: #718096;">Action</th>
                    </tr>
                </thead>
                <tbody id="employeeTableBody">
                    <?php foreach($employees as $emp): ?>
                        <tr style="border-bottom: 1px solid #edf2f7;">
                            <td style="padding: 15px;">
                                <div style="font-weight: 600; color: #2d3748;"><?= htmlspecialchars($emp['full_name']) ?></div>
                                <div style="font-size: 12px; color: #718096;"><?= htmlspecialchars($emp['designation']) ?></div>
                            </td>
                            <td style="padding: 15px; color: #4a5568;"><?= htmlspecialchars($emp['department_name'] ?? 'N/A') ?></td>
                            <td style="padding: 15px;"><span style="background: #e6fffa; color: #0f766e; padding: 4px 8px; border-radius: 10px; font-size: 12px;"><?= htmlspecialchars($emp['employment_type']) ?></span></td>
                            <td style="padding: 15px; font-weight: 600; color: #059669;">₹<?= number_format($emp['basic_salary'], 2) ?></td>
                            <td style="padding: 15px;">
                                <a href="generate_payslip.php?employee_id=<?= $emp['employee_id'] ?>" style="color: #667eea; font-weight: 600; font-size: 13px;">Payslip <i class="fas fa-arrow-right"></i></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Filtering
        const searchInput = document.getElementById('searchInput');
        const departmentFilter = document.getElementById('departmentFilter');
        const typeFilter = document.getElementById('typeFilter');
        const sortSalaryBtn = document.getElementById('sortSalary');
        const tableBody = document.getElementById('employeeTableBody');
        let sortDesc = true;

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const deptVal = departmentFilter.value.toLowerCase();
            const typeVal = typeFilter.value.toLowerCase();

            Array.from(tableBody.getElementsByTagName('tr')).forEach(row => {
                const name = row.cells[0].textContent.toLowerCase();
                const dept = row.cells[1].textContent.toLowerCase();
                const type = row.cells[2].textContent.toLowerCase();

                const match = name.includes(searchTerm) 
                            && (!deptVal || dept.includes(deptVal))
                            && (!typeVal || type.includes(typeVal));
                
                row.style.display = match ? '' : 'none';
            });
        }

        function sortBySalary() {
            const rows = Array.from(tableBody.querySelectorAll('tr'));
            rows.sort((a, b) => {
                const aVal = parseFloat(a.cells[3].textContent.replace(/[^0-9.-]+/g, '')) || 0;
                const bVal = parseFloat(b.cells[3].textContent.replace(/[^0-9.-]+/g, '')) || 0;
                return sortDesc ? bVal - aVal : aVal - bVal;
            });
            sortDesc = !sortDesc;
            rows.forEach(r => tableBody.appendChild(r));
        }

        searchInput.addEventListener('input', filterTable);
        departmentFilter.addEventListener('change', filterTable);
        typeFilter.addEventListener('change', filterTable);
        sortSalaryBtn.addEventListener('click', sortBySalary);
    </script>
</body>
</html>
