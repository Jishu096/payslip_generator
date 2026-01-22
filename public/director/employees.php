<?php
session_start();

// Check if user has director role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Director';

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';

$employeeModel = new Employee();
$db = getDBConnection();

// Get all employees with department info
$query = "SELECT e.*, d.department_name 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.department_id 
          ORDER BY e.full_name ASC";
$stmt = $db->query($query);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$deptStmt = $db->query("SELECT * FROM departments ORDER BY department_name");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Director Portal</title>
    <?php include 'includes/director_styles.php'; ?>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header h1 {
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-card .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-card .stat-label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .search-filter-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box {
            flex: 1;
            min-width: 250px;
            position: relative;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
        }

        .filter-select {
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            background: white;
            min-width: 180px;
        }

        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .employee-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .employee-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .employee-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }

        .employee-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .employee-info h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 4px;
        }

        .employee-info .emp-code {
            font-size: 12px;
            color: var(--muted);
            font-weight: 500;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .detail-row {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
        }

        .detail-row i {
            width: 20px;
            color: var(--accent);
        }

        .detail-row .label {
            color: var(--muted);
            min-width: 80px;
        }

        .detail-row .value {
            color: var(--text);
            font-weight: 500;
        }

        .employee-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .salary-badge {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-active {
            background: #d1fae5;
            color: #065f46;
        }

        .status-inactive {
            background: #fee2e2;
            color: #991b1b;
        }

        .view-toggle {
            display: flex;
            gap: 10px;
            background: #f7fafc;
            padding: 5px;
            border-radius: 8px;
        }

        .view-toggle button {
            padding: 8px 15px;
            border: none;
            background: transparent;
            color: var(--muted);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .view-toggle button.active {
            background: white;
            color: var(--accent);
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .table-view {
            display: none;
        }

        .table-view.active {
            display: block;
        }

        .grid-view {
            display: none;
        }

        .grid-view.active {
            display: grid;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        thead {
            background: #f7fafc;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        th {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            font-size: 14px;
            color: var(--text);
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        @media (max-width: 768px) {
            .employee-grid {
                grid-template-columns: 1fr;
            }
            
            .search-filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                min-width: 100%;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Employee Directory</h1>
            <div class="view-toggle">
                <button class="toggle-grid active" onclick="switchView('grid')">
                    <i class="fas fa-th"></i> Cards
                </button>
                <button class="toggle-table" onclick="switchView('table')">
                    <i class="fas fa-table"></i> Table
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-value"><?php echo count($employees); ?></div>
                <div class="stat-label">Total Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-value">
                    <?php echo count(array_filter($employees, fn($e) => $e['status'] === 'active')); ?>
                </div>
                <div class="stat-label">Active Employees</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value">
                    <?php
                    $deptQuery = "SELECT COUNT(*) as dept_count FROM departments WHERE deleted_at IS NULL";
                    $deptStmt = $db->query($deptQuery);
                    $deptCount = $deptStmt->fetch(PDO::FETCH_ASSOC)['dept_count'];
                    echo $deptCount;
                    ?>
                </div>
                <div class="stat-label">Departments</div>
            </div>
        </div>

        <!-- Search & Filter Bar -->
        <div class="search-filter-bar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name, email, or designation..." onkeyup="filterEmployees()">
            </div>
            <select id="departmentFilter" class="filter-select" onchange="filterEmployees()">
                <option value="">All Departments</option>
                <?php
                $deptQuery = "SELECT department_id, department_name FROM departments WHERE deleted_at IS NULL ORDER BY department_name";
                $deptStmt = $db->query($deptQuery);
                $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($departments as $dept):
                ?>
                <option value="<?php echo $dept['department_id']; ?>"><?php echo htmlspecialchars($dept['department_name']); ?></option>
                <?php endforeach; ?>
            </select>
            <select id="statusFilter" class="filter-select" onchange="filterEmployees()">
                <option value="">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>

        <!-- Grid View -->
        <div class="employee-grid grid-view active" id="gridView">
            <?php foreach ($employees as $emp): ?>
            <div class="employee-card" 
                 data-name="<?php echo strtolower($emp['full_name']); ?>"
                 data-email="<?php echo strtolower($emp['email']); ?>"
                 data-designation="<?php echo strtolower($emp['designation']); ?>"
                 data-department="<?php echo $emp['department_id']; ?>"
                 data-status="<?php echo $emp['status']; ?>">
                <div class="employee-header">
                    <div class="employee-avatar">
                        <?php 
                        $nameParts = explode(' ', $emp['full_name']);
                        echo strtoupper(substr($nameParts[0], 0, 1));
                        if (count($nameParts) > 1) {
                            echo strtoupper(substr($nameParts[count($nameParts)-1], 0, 1));
                        }
                        ?>
                    </div>
                    <div class="employee-info">
                        <h3><?php echo htmlspecialchars($emp['full_name']); ?></h3>
                        <div class="emp-code"><?php echo htmlspecialchars($emp['employee_code'] ?? 'EMP' . str_pad($emp['employee_id'], 3, '0', STR_PAD_LEFT)); ?></div>
                    </div>
                </div>
                <div class="employee-details">
                    <div class="detail-row">
                        <i class="fas fa-briefcase"></i>
                        <span class="label">Position:</span>
                        <span class="value"><?php echo htmlspecialchars($emp['designation']); ?></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-building"></i>
                        <span class="label">Department:</span>
                        <span class="value"><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-envelope"></i>
                        <span class="label">Email:</span>
                        <span class="value"><?php echo htmlspecialchars($emp['email']); ?></span>
                    </div>
                    <div class="detail-row">
                        <i class="fas fa-phone"></i>
                        <span class="label">Phone:</span>
                        <span class="value"><?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                <div class="employee-footer">
                    <div class="salary-badge">
                        ₹<?php echo number_format($emp['basic_salary'], 0); ?>
                    </div>
                    <span class="status-badge status-<?php echo $emp['status']; ?>">
                        <?php echo ucfirst($emp['status']); ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Table View -->
        <div class="table-view" id="tableView">
            <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                <table>
                    <thead>
                        <tr>
                            <th>Employee ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Designation</th>
                            <th>Status</th>
                            <th>Salary</th>
                        </tr>
                    </thead>
                    <tbody id="tableBody">
                        <?php foreach ($employees as $emp): ?>
                        <tr data-name="<?php echo strtolower($emp['full_name']); ?>"
                            data-email="<?php echo strtolower($emp['email']); ?>"
                            data-designation="<?php echo strtolower($emp['designation']); ?>"
                            data-department="<?php echo $emp['department_id']; ?>"
                            data-status="<?php echo $emp['status']; ?>">
                            <td>#<?php echo htmlspecialchars($emp['employee_code'] ?? 'EMP' . str_pad($emp['employee_id'], 3, '0', STR_PAD_LEFT)); ?></td>
                            <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                            <td>
                                <span class="status-badge status-<?php echo $emp['status']; ?>">
                                    <?php echo ucfirst($emp['status']); ?>
                                </span>
                            </td>
                            <td>₹<?php echo number_format($emp['basic_salary'], 2); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php include 'includes/director_scripts.php'; ?>
    <script>
        // View switcher
        function switchView(view) {
            const gridView = document.getElementById('gridView');
            const tableView = document.getElementById('tableView');
            const gridBtn = document.querySelector('.toggle-grid');
            const tableBtn = document.querySelector('.toggle-table');

            if (view === 'grid') {
                gridView.classList.add('active');
                tableView.classList.remove('active');
                gridBtn.classList.add('active');
                tableBtn.classList.remove('active');
            } else {
                gridView.classList.remove('active');
                tableView.classList.add('active');
                gridBtn.classList.remove('active');
                tableBtn.classList.add('active');
            }
        }

        // Filter employees
        function filterEmployees() {
            const searchInput = document.getElementById('searchInput').value.toLowerCase();
            const departmentFilter = document.getElementById('departmentFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;

            // Filter grid view cards
            const cards = document.querySelectorAll('.employee-card');
            cards.forEach(card => {
                const name = card.dataset.name || '';
                const email = card.dataset.email || '';
                const designation = card.dataset.designation || '';
                const department = card.dataset.department || '';
                const status = card.dataset.status || '';

                const matchesSearch = name.includes(searchInput) || 
                                     email.includes(searchInput) || 
                                     designation.includes(searchInput);
                const matchesDepartment = !departmentFilter || department === departmentFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesDepartment && matchesStatus) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });

            // Filter table view rows
            const rows = document.querySelectorAll('#tableBody tr');
            rows.forEach(row => {
                const name = row.dataset.name || '';
                const email = row.dataset.email || '';
                const designation = row.dataset.designation || '';
                const department = row.dataset.department || '';
                const status = row.dataset.status || '';

                const matchesSearch = name.includes(searchInput) || 
                                     email.includes(searchInput) || 
                                     designation.includes(searchInput);
                const matchesDepartment = !departmentFilter || department === departmentFilter;
                const matchesStatus = !statusFilter || status === statusFilter;

                if (matchesSearch && matchesDepartment && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>
