<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/Attendance.php";
require_once "../../app/Models/Department.php";

$db = getDBConnection();
$employeeModel = new Employee();
$attendanceModel = new Attendance();
$departmentModel = new Department($db);

// Get filter parameters
$filterEmployee = $_GET['employee_id'] ?? '';
$filterDepartment = $_GET['department_id'] ?? '';
$filterMonth = $_GET['month'] ?? date('Y-m');
$filterStatus = $_GET['status'] ?? '';

// Get all employees and departments for filters
$allEmployees = $employeeModel->getAllEmployees();
$allDepartments = $departmentModel->getAllDepartments();

// Calculate date range from month
$startDate = $filterMonth . '-01';
$endDate = date('Y-m-t', strtotime($startDate));

// Build query for attendance report
$sql = "SELECT 
            a.attendance_id,
            a.employee_id,
            a.date,
            a.status,
            e.full_name as employee_name,
            e.designation,
            e.employment_type,
            d.department_name
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE a.date BETWEEN :start_date AND :end_date";

$params = [
    ':start_date' => $startDate,
    ':end_date' => $endDate
];

if ($filterEmployee) {
    $sql .= " AND a.employee_id = :employee_id";
    $params[':employee_id'] = $filterEmployee;
}

if ($filterDepartment) {
    $sql .= " AND e.department_id = :department_id";
    $params[':department_id'] = $filterDepartment;
}

if ($filterStatus) {
    $sql .= " AND a.status = :status";
    $params[':status'] = $filterStatus;
}

$sql .= " ORDER BY a.date DESC, employee_name ASC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate summary statistics
$totalRecords = count($attendanceRecords);
$presentCount = 0;
$absentCount = 0;
$leaveCount = 0;
$holidayCount = 0;

foreach ($attendanceRecords as $record) {
    switch (strtolower($record['status'])) {
        case 'present':
            $presentCount++;
            break;
        case 'absent':
            $absentCount++;
            break;
        case 'leave':
            $leaveCount++;
            break;
        case 'holiday':
            $holidayCount++;
            break;
    }
}

$attendanceRate = $totalRecords > 0 ? round(($presentCount / $totalRecords) * 100, 1) : 0;

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_report_' . $filterMonth . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Date', 'Employee Name', 'Department', 'Designation', 'Employment Type', 'Status']);
    
    foreach ($attendanceRecords as $record) {
        fputcsv($output, [
            $record['date'],
            $record['employee_name'],
            $record['department_name'] ?? 'N/A',
            $record['designation'],
            ucfirst($record['employment_type']),
            ucfirst($record['status'])
        ]);
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header h1 i {
            margin-right: 12px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
        }

        .header-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .header-btn.primary {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .header-btn.success {
            background: #10b981;
            color: white;
        }

        .header-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.purple::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.red::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            font-weight: 500;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.red .stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        /* Filter Card */
        .filter-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .filter-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f1f5f9;
        }

        .filter-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .filter-header h2 i {
            color: #667eea;
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group label i {
            color: #667eea;
        }

        .form-group select,
        .form-group input {
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s ease;
            background: #f8fafc;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        /* Chart & Table Cards */
        .content-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 30px;
        }

        .card-header {
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #f1f5f9;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 i {
            color: #667eea;
        }

        .card-header .badge {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .card-body {
            padding: 25px;
        }

        .chart-container {
            max-width: 400px;
            margin: 0 auto;
        }

        /* Table Styles */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f8fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid #f1f5f9;
            color: var(--text);
            font-size: 14px;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-badge.present {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #059669;
        }

        .status-badge.absent {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: #dc2626;
        }

        .status-badge.leave {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
            color: #2563eb;
        }

        .status-badge.holiday {
            background: linear-gradient(135deg, rgba(107, 114, 128, 0.15), rgba(75, 85, 99, 0.15));
            color: #4b5563;
        }

        .employee-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .employee-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 2px 0;
        }

        .employee-info p {
            font-size: 12px;
            color: var(--muted);
            margin: 0;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .empty-state-icon i {
            font-size: 32px;
            color: #667eea;
        }

        .empty-state h3 {
            font-size: 20px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 14px;
        }

        /* Print Styles */
        @media print {
            .page-header,
            .filter-card,
            .sidebar,
            .navbar {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .content-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 30px;
            }

            .header-actions {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Attendance Reports</h1>
                <p>Analyze attendance patterns and generate detailed reports</p>
            </div>
            <div class="header-actions">
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="header-btn success">
                    <i class="fas fa-download"></i> Export CSV
                </a>
                <button onclick="window.print()" class="header-btn primary">
                    <i class="fas fa-print"></i> Print
                </button>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $totalRecords ?></div>
                        <div class="stat-label">Total Records</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $presentCount ?></div>
                        <div class="stat-label">Present Days</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card red">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $absentCount ?></div>
                        <div class="stat-label">Absent Days</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $leaveCount ?></div>
                        <div class="stat-label">Leave Days</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-minus"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $attendanceRate ?>%</div>
                        <div class="stat-label">Attendance Rate</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-percentage"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Card -->
        <div class="filter-card">
            <div class="filter-header">
                <h2><i class="fas fa-filter"></i> Filter Reports</h2>
            </div>
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label><i class="far fa-calendar"></i> Month</label>
                        <input type="month" 
                               name="month" 
                               value="<?= htmlspecialchars($filterMonth) ?>"
                               max="<?= date('Y-m') ?>">
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Employee</label>
                        <select name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($allEmployees as $emp): ?>
                                <option value="<?= $emp['employee_id'] ?>" 
                                        <?= $filterEmployee == $emp['employee_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-building"></i> Department</label>
                        <select name="department_id">
                            <option value="">All Departments</option>
                            <?php foreach ($allDepartments as $dept): ?>
                                <option value="<?= $dept['department_id'] ?>" 
                                        <?= $filterDepartment == $dept['department_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dept['department_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-flag"></i> Status</label>
                        <select name="status">
                            <option value="">All Status</option>
                            <option value="present" <?= $filterStatus === 'present' ? 'selected' : '' ?>>Present</option>
                            <option value="absent" <?= $filterStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                            <option value="leave" <?= $filterStatus === 'leave' ? 'selected' : '' ?>>Leave</option>
                            <option value="holiday" <?= $filterStatus === 'holiday' ? 'selected' : '' ?>>Holiday</option>
                        </select>
                    </div>

                    <div class="form-group" style="justify-content: flex-end;">
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-search"></i> Apply Filters
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- Chart Card -->
        <?php if ($totalRecords > 0): ?>
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-chart-pie"></i> Attendance Distribution</h2>
                <span class="badge"><?= date('F Y', strtotime($filterMonth)) ?></span>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Records Table -->
        <div class="content-card">
            <div class="card-header">
                <h2><i class="fas fa-table"></i> Attendance Records</h2>
                <span class="badge"><?= $totalRecords ?> records</span>
            </div>

            <?php if (empty($attendanceRecords)): ?>
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h3>No Records Found</h3>
                        <p>No attendance records found for the selected filters.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceRecords as $record): 
                                $nameParts = explode(' ', $record['employee_name']);
                                $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                            ?>
                            <tr>
                                <td>
                                    <strong><?= date('d', strtotime($record['date'])) ?></strong>
                                    <span style="color: var(--muted); font-size: 12px;"><?= date('M Y', strtotime($record['date'])) ?></span>
                                </td>
                                <td>
                                    <div class="employee-cell">
                                        <div class="employee-avatar"><?= $initials ?></div>
                                        <div class="employee-info">
                                            <h4><?= htmlspecialchars($record['employee_name']) ?></h4>
                                            <p><?= htmlspecialchars($record['designation']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($record['department_name'] ?? 'N/A') ?></td>
                                <td>
                                    <span style="background: rgba(102, 126, 234, 0.1); color: #667eea; padding: 4px 10px; border-radius: 6px; font-size: 12px; font-weight: 500;">
                                        <?= ucfirst($record['employment_type']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge <?= strtolower($record['status']) ?>">
                                        <?= ucfirst($record['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <?php if ($totalRecords > 0): ?>
    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Present', 'Absent', 'Leave', 'Holiday'],
                datasets: [{
                    data: [<?= $presentCount ?>, <?= $absentCount ?>, <?= $leaveCount ?>, <?= $holidayCount ?>],
                    backgroundColor: [
                        '#10b981',
                        '#ef4444',
                        '#3b82f6',
                        '#6b7280'
                    ],
                    borderWidth: 0,
                    hoverOffset: 10
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                cutout: '65%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle',
                            font: {
                                size: 13,
                                family: 'Roboto'
                            }
                        }
                    },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        padding: 12,
                        titleFont: { size: 14, family: 'Roboto' },
                        bodyFont: { size: 13, family: 'Roboto' },
                        cornerRadius: 8,
                        callbacks: {
                            label: function(context) {
                                const total = <?= $totalRecords ?>;
                                const value = context.parsed;
                                const percentage = ((value / total) * 100).toFixed(1);
                                return context.label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>
