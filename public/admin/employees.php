<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';

// Get filter parameters
$filterDepartment = $_GET['department'] ?? '';
$filterDesignation = $_GET['designation'] ?? '';
$filterEmploymentType = $_GET['employment_type'] ?? '';
$filterStatus = $_GET['status'] ?? '';

// Build query with filters
$employeeModel = new Employee();
$db = getDBConnection();

$query = "SELECT e.*, d.department_name 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.department_id 
          WHERE 1=1";
$params = [];

if ($filterDepartment) {
    $query .= " AND e.department_id = ?";
    $params[] = $filterDepartment;
}
if ($filterDesignation) {
    $query .= " AND e.designation LIKE ?";
    $params[] = "%$filterDesignation%";
}
if ($filterEmploymentType) {
    $query .= " AND e.employment_type = ?";
    $params[] = $filterEmploymentType;
}
if ($filterStatus) {
    $query .= " AND e.status = ?";
    $params[] = $filterStatus;
}

$query .= " ORDER BY e.employee_id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all departments for filter dropdown
$deptStmt = $db->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

$success = isset($_GET['success']);
$deleted = isset($_GET['deleted']);
$updated = isset($_GET['updated']);
$salary_pending = isset($_GET['salary_pending']);
$role_pending = isset($_GET['role_pending']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Enterprise Payroll Solutions</title>
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
            --border-light: #f0f0f0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --hover-bg: #f8f9fa;
            --input-bg: #ffffff;
            --input-border: #e0e0e0;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --badge-success-bg: #d4edda;
            --badge-success-text: #155724;
            --badge-danger-bg: #f8d7da;
            --badge-danger-text: #721c24;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1f36;
            --bg-secondary: #232946;
            --bg-tertiary: #2d3250;
            --text-primary: #fffffe;
            --text-secondary: #b8c1ec;
            --text-tertiary: #a0a8d4;
            --border-color: #3d4263;
            --border-light: #353a5c;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
            --hover-bg: #2d3250;
            --input-bg: #232946;
            --input-border: #3d4263;
            --badge-success-bg: rgba(52, 211, 153, 0.15);
            --badge-success-text: #34d399;
            --badge-danger-bg: rgba(239, 68, 68, 0.15);
            --badge-danger-text: #ef4444;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 15px;
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .theme-toggle i {
            font-size: 18px;
            color: var(--text-primary);
            transition: transform 0.3s ease;
        }

        .theme-toggle:hover i {
            transform: rotate(20deg);
        }

        .employee-table {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .table-header {
            padding: 25px 30px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            background: var(--bg-secondary);
        }

        .table-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            flex: 1;
            min-width: 200px;
        }

        .search-box {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box input {
            padding: 12px 18px;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            min-width: 250px;
            flex: 1;
            background: var(--input-bg);
            color: var(--text-primary);
            font-family: 'Manrope', sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box input::placeholder {
            color: var(--text-tertiary);
        }

        .table-wrapper {
            overflow-x: auto;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead {
            background: var(--bg-secondary);
            position: sticky;
            top: 0;
            z-index: 10;
        }

        th {
            padding: 16px 14px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            white-space: nowrap;
            border-bottom: 2px solid var(--border-color);
        }

        td {
            padding: 16px 14px;
            border-top: 1px solid var(--border-light);
            color: var(--text-secondary);
            font-size: 14px;
        }

        tr {
            transition: background 0.2s ease;
        }

        tr:hover {
            background: var(--hover-bg);
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-active { 
            background: var(--badge-success-bg); 
            color: var(--badge-success-text); 
        }
        
        .badge-inactive { 
            background: var(--badge-danger-bg); 
            color: var(--badge-danger-text); 
        }

        .badge-on_leave {
            background: rgba(251, 191, 36, 0.15);
            color: #f59e0b;
        }

        [data-theme="dark"] .badge-on_leave {
            background: rgba(251, 191, 36, 0.2);
            color: #fbbf24;
        }

        .filters-section {
            padding: 20px 30px;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
            min-width: 150px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .filter-group select,
        .filter-group input {
            padding: 8px 12px;
            border: 2px solid var(--input-border);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-primary);
            font-size: 14px;
            font-family: 'Manrope', sans-serif;
            transition: all 0.3s ease;
        }

        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
            padding-top: 20px;
        }

        .btn-filter {
            padding: 8px 18px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-reset {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
        }

        .btn-export {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            color: white;
        }

        .btn-export-excel {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }

        .btn-export-pdf {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }

        /* Quick View Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: var(--bg-primary);
            margin: 3% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideDown 0.3s ease;
            border: 1px solid var(--border-color);
        }

        @keyframes slideDown {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            padding: 25px 30px;
            background: var(--gradient-primary);
            color: white;
            border-radius: 16px 16px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-header h2 {
            margin: 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 24px;
        }

        .close {
            color: white;
            font-size: 32px;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s ease;
            line-height: 1;
        }

        .close:hover {
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
            max-height: 70vh;
            overflow-y: auto;
        }

        .employee-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-item label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-item .value {
            font-size: 15px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .detail-item.full-width {
            grid-column: 1 / -1;
        }

        .employee-name-clickable {
            cursor: pointer;
            color: #667eea;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .employee-name-clickable:hover {
            color: #764ba2;
            text-decoration: underline;
        }

        .action-btns {
            display: flex;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 8px 12px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 38px;
            height: 38px;
        }

        .btn-view {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .btn-edit {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            color: white;
        }

        .btn-delete {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .btn-sm i {
            pointer-events: none;
        }

        .add-btn {
            background: var(--gradient-primary);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            border: 2px solid;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert i {
            font-size: 18px;
        }

        .alert-success {
            background: #e8f7ee;
            border-color: #b6e0c5;
            color: #1b6b3d;
        }

        [data-theme="dark"] .alert-success {
            background: rgba(52, 211, 153, 0.15);
            border-color: rgba(52, 211, 153, 0.3);
            color: #34d399;
        }

        .alert-warning {
            background: #fff8e1;
            border-color: #ffb74d;
            color: #e65100;
        }

        [data-theme="dark"] .alert-warning {
            background: rgba(251, 191, 36, 0.15);
            border-color: rgba(251, 191, 36, 0.3);
            color: #fbbf24;
        }

        .alert-info {
            background: #e8f5ff;
            border-color: #b3d9e8;
            color: #0c5377;
        }

        [data-theme="dark"] .alert-info {
            background: rgba(59, 130, 246, 0.15);
            border-color: rgba(59, 130, 246, 0.3);
            color: #3b82f6;
        }

        .alert-error {
            background: #fff4e5;
            border-color: #ffd8a8;
            color: #b35c00;
        }

        [data-theme="dark"] .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        @media (max-width: 1200px) {
            th, td {
                padding: 12px 10px;
                font-size: 13px;
            }
            .btn-sm {
                padding: 6px 10px;
                min-width: 34px;
                height: 34px;
            }
        }

        @media (max-width: 768px) {
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            .table-header h2 {
                width: 100%;
                margin: 0;
            }
            .search-box {
                width: 100%;
                flex-direction: column;
            }
            .search-box input {
                width: 100%;
                min-width: unset;
            }
            .add-btn {
                width: 100%;
                justify-content: center;
            }
            .theme-toggle {
                top: 10px;
                right: 10px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Employees Management</h1>
            <p>View and manage all employee records</p>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Employee added successfully.</span>
            </div>
        <?php elseif ($updated && $salary_pending && $role_pending): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i>
                <span>Employee updated successfully. <strong>Salary change and role change requests sent to Director for approval.</strong></span>
            </div>
        <?php elseif ($updated && $salary_pending): ?>
            <div class="alert alert-warning">
                <i class="fas fa-clock"></i>
                <span>Employee updated successfully. <strong>Salary change request sent to Director for approval.</strong></span>
            </div>
        <?php elseif ($updated && $role_pending): ?>
            <div class="alert alert-info">
                <i class="fas fa-clock"></i>
                <span>Employee updated successfully. <strong>Role change request sent to Director for approval.</strong></span>
            </div>
        <?php elseif ($updated): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Employee updated successfully.</span>
            </div>
        <?php elseif ($deleted): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Employee deleted successfully.</span>
            </div>
        <?php elseif ($error === 'user_create_failed'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Employee saved, but user account creation failed.</span>
            </div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Delete failed. Please try again.</span>
            </div>
        <?php elseif ($error === 'update_failed'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Update failed. Please review the inputs.</span>
            </div>
        <?php elseif ($error === 'missing_id' || $error === 'not_found'): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span>Employee not found.</span>
            </div>
        <?php endif; ?>

        <div class="employee-table">
            <div class="table-header">
                <h2>All Employees (<?php echo count($employees); ?>)</h2>
                <div class="search-box">
                    <input type="text" placeholder="Search employees..." id="searchInput">
                    <button class="btn-export btn-export-excel" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> Export Excel
                    </button>
                    <button class="btn-export btn-export-pdf" onclick="exportToPDF()">
                        <i class="fas fa-file-pdf"></i> Export PDF
                    </button>
                    <a href="add_employee.php" class="add-btn">
                        <i class="fas fa-plus"></i> Add Employee
                    </a>
                </div>
            </div>

            <!-- Filters Section -->
            <div class="filters-section">
                <div class="filter-group">
                    <label>Department</label>
                    <select id="filterDepartment" name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $filterDepartment == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Designation</label>
                    <input type="text" id="filterDesignation" placeholder="e.g., Manager" value="<?php echo htmlspecialchars($filterDesignation); ?>">
                </div>

                <div class="filter-group">
                    <label>Employment Type</label>
                    <select id="filterEmploymentType" name="employment_type">
                        <option value="">All Types</option>
                        <option value="permanent" <?php echo $filterEmploymentType === 'permanent' ? 'selected' : ''; ?>>Permanent</option>
                        <option value="contract" <?php echo $filterEmploymentType === 'contract' ? 'selected' : ''; ?>>Contract</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Status</label>
                    <select id="filterStatus" name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $filterStatus === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $filterStatus === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="on_leave" <?php echo $filterStatus === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button class="btn-filter" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                    <button class="btn-filter btn-reset" onclick="resetFilters()">
                        <i class="fas fa-redo"></i> Reset
                    </button>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Address</th>
                        <th>City / State</th>
                        <th>Emergency Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                                <td>
                                    <a href="javascript:void(0)" class="employee-name-clickable" onclick='showEmployeeDetails(<?php echo json_encode($emp); ?>)'>
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                <td><?php echo htmlspecialchars($emp['department_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                                <td><?php echo htmlspecialchars($emp['address'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(($emp['city'] ?? '') . (isset($emp['state']) && $emp['state'] !== '' ? ' / ' . $emp['state'] : '')); ?></td>
                                <td><?php echo htmlspecialchars($emp['emergency_contact_phone'] ?? ''); ?></td>
                                <td>
                                    <?php 
                                    $status = $emp['status'] ?? 'active';
                                    $statusClass = 'badge-' . $status;
                                    $statusText = ucwords(str_replace('_', ' ', $status));
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td>
                                    <div class="action-btns">
                                        <a class="btn-sm btn-view" href="employee_profile.php?id=<?php echo urlencode($emp['employee_id']); ?>" title="View Profile">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a class="btn-sm btn-edit" href="edit_employee.php?id=<?php echo urlencode($emp['employee_id']); ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a class="btn-sm btn-delete confirm-delete" href="../index.php?page=delete-employee&id=<?php echo urlencode($emp['employee_id']); ?>" data-name="<?php echo htmlspecialchars($emp['full_name']); ?>" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align:center; padding:20px; color:#7f8c8d;">No employees found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- Quick View Modal -->
        <div id="employeeModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2><i class="fas fa-user"></i> Employee Details</h2>
                    <span class="close" onclick="closeModal()">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="employee-details" id="modalEmployeeDetails">
                        <!-- Details will be populated by JavaScript -->
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        // Load saved theme
        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('adminTheme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const table = document.querySelector('table tbody');
        const rows = table.querySelectorAll('tr');

        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Delete confirmation
        document.querySelectorAll('.confirm-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var name = this.getAttribute('data-name') || 'this employee';
                if (!confirm('Are you sure you want to delete ' + name + '?')) {
                    e.preventDefault();
                }
            });
        });

        // Filter Functions
        function applyFilters() {
            const department = document.getElementById('filterDepartment').value;
            const designation = document.getElementById('filterDesignation').value;
            const employmentType = document.getElementById('filterEmploymentType').value;
            const status = document.getElementById('filterStatus').value;

            const params = new URLSearchParams();
            if (department) params.append('department', department);
            if (designation) params.append('designation', designation);
            if (employmentType) params.append('employment_type', employmentType);
            if (status) params.append('status', status);

            window.location.href = 'employees.php' + (params.toString() ? '?' + params.toString() : '');
        }

        function resetFilters() {
            window.location.href = 'employees.php';
        }

        // Export to Excel
        function exportToExcel() {
            const table = document.querySelector('table');
            let html = '<table>';
            
            // Get headers
            html += '<thead><tr>';
            table.querySelectorAll('thead th').forEach((th, index) => {
                if (index < table.querySelectorAll('thead th').length - 1) { // Skip Actions column
                    html += '<th>' + th.textContent + '</th>';
                }
            });
            html += '</tr></thead><tbody>';
            
            // Get visible rows only
            table.querySelectorAll('tbody tr').forEach(tr => {
                if (tr.style.display !== 'none') {
                    html += '<tr>';
                    tr.querySelectorAll('td').forEach((td, index) => {
                        if (index < tr.querySelectorAll('td').length - 1) { // Skip Actions column
                            html += '<td>' + td.textContent.trim() + '</td>';
                        }
                    });
                    html += '</tr>';
                }
            });
            html += '</tbody></table>';
            
            const blob = new Blob(['\ufeff', html], {
                type: 'application/vnd.ms-excel'
            });
            
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = 'employees_' + new Date().toISOString().split('T')[0] + '.xls';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }

        // Export to PDF
        function exportToPDF() {
            window.print();
        }

        // Print styles for PDF export
        const style = document.createElement('style');
        style.textContent = `
            @media print {
                body * {
                    visibility: hidden;
                }
                .employee-table, .employee-table * {
                    visibility: visible;
                }
                .employee-table {
                    position: absolute;
                    left: 0;
                    top: 0;
                    width: 100%;
                }
                .theme-toggle, .search-box, .filters-section, .action-btns, .page-header {
                    display: none !important;
                }
                table {
                    border-collapse: collapse;
                    width: 100%;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 8px;
                    text-align: left;
                }
            }
        `;
        document.head.appendChild(style);

        // Quick View Modal Functions
        const modal = document.getElementById('employeeModal');

        function showEmployeeDetails(employee) {
            const detailsContainer = document.getElementById('modalEmployeeDetails');
            const status = employee.status || 'active';
            const statusClass = 'badge-' + status;
            const statusText = status.replace('_', ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
            
            detailsContainer.innerHTML = `
                <div class="detail-item full-width">
                    <label><i class="fas fa-id-badge"></i> Employee ID</label>
                    <div class="value">${employee.employee_id}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-user"></i> Full Name</label>
                    <div class="value">${employee.full_name}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-envelope"></i> Email</label>
                    <div class="value">${employee.email}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-phone"></i> Phone</label>
                    <div class="value">${employee.phone}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-briefcase"></i> Designation</label>
                    <div class="value">${employee.designation}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-building"></i> Department</label>
                    <div class="value">${employee.department_name || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-id-card"></i> Employment Type</label>
                    <div class="value">${employee.employment_type.charAt(0).toUpperCase() + employee.employment_type.slice(1)}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-toggle-on"></i> Status</label>
                    <div class="value"><span class="badge ${statusClass}">${statusText}</span></div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-dollar-sign"></i> Basic Salary</label>
                    <div class="value">₹${parseFloat(employee.basic_salary).toLocaleString('en-IN')}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-calendar-alt"></i> Join Date</label>
                    <div class="value">${employee.join_date || '—'}</div>
                </div>
                <div class="detail-item full-width">
                    <label><i class="fas fa-map-marker-alt"></i> Address</label>
                    <div class="value">${employee.address || '—'}, ${employee.city || '—'}, ${employee.state || '—'} - ${employee.pincode || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-user-shield"></i> Emergency Contact</label>
                    <div class="value">${employee.emergency_contact_name || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-phone-alt"></i> Emergency Phone</label>
                    <div class="value">${employee.emergency_contact_phone || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-id-card"></i> Aadhaar Number</label>
                    <div class="value">${employee.aadhaar_no || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-id-badge"></i> PAN Number</label>
                    <div class="value">${employee.pan_no || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-university"></i> Bank Account</label>
                    <div class="value">${employee.bank_account_no || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-barcode"></i> IFSC Code</label>
                    <div class="value">${employee.ifsc_code || '—'}</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-briefcase"></i> Experience</label>
                    <div class="value">${employee.experience_years || '0'} years</div>
                </div>
                <div class="detail-item">
                    <label><i class="fas fa-calendar-check"></i> Last Appraisal</label>
                    <div class="value">${employee.last_appraisal_date || '—'}</div>
                </div>
                ${employee.remarks ? `
                <div class="detail-item full-width">
                    <label><i class="fas fa-comment-dots"></i> Remarks</label>
                    <div class="value">${employee.remarks}</div>
                </div>
                ` : ''}
            `;
            
            modal.style.display = 'block';
        }

        function closeModal() {
            modal.style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape' && modal.style.display === 'block') {
                closeModal();
            }
        });
    </script>

</body>
</html>
