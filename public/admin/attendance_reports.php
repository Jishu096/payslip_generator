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
            CONCAT(e.first_name, ' ', e.last_name) as employee_name,
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .breadcrumb i {
            margin: 0 8px;
            font-size: 10px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }

        .stat-card.success {
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
        }

        .stat-card.danger {
            background: linear-gradient(135deg, #f56565, #e53e3e);
            color: white;
            border: none;
        }

        .stat-card.warning {
            background: linear-gradient(135deg, #ed8936, #dd6b20);
            color: white;
            border: none;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            margin-bottom: 25px;
        }

        .card-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .filters {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group select,
        .form-group input {
            padding: 10px 15px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s;
        }

        .form-group select:focus,
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: #48bb78;
            color: white;
        }

        .btn-success:hover {
            background: #38a169;
        }

        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f7fafc;
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
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.absent {
            background: #fed7d7;
            color: #742a2a;
        }

        .status-badge.leave {
            background: #bee3f8;
            color: #2c5282;
        }

        .status-badge.holiday {
            background: #e2e8f0;
            color: #2d3748;
        }

        .chart-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 10px;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="breadcrumb">
                <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Attendance Reports</span>
            </div>
            <h1><i class="fas fa-chart-bar"></i> Attendance Reports</h1>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-clipboard-list"></i>
                </div>
                <div class="stat-number"><?= $totalRecords ?></div>
                <div class="stat-label">Total Records</div>
            </div>

            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-number"><?= $presentCount ?></div>
                <div class="stat-label">Present Days</div>
            </div>

            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-times"></i>
                </div>
                <div class="stat-number"><?= $absentCount ?></div>
                <div class="stat-label">Absent Days</div>
            </div>

            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-number"><?= $leaveCount ?></div>
                <div class="stat-label">Leave Days</div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background: #e2e8f0; color: #2d3748;">
                    <i class="fas fa-percentage"></i>
                </div>
                <div class="stat-number" style="color: #2d3748;"><?= $attendanceRate ?>%</div>
                <div class="stat-label" style="color: #718096;">Attendance Rate</div>
            </div>
        </div>

        <!-- Filters Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-filter"></i>
                    Filter Reports
                </h2>
                <div class="btn-group">
                    <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success">
                        <i class="fas fa-download"></i> Export CSV
                    </a>
                    <button onclick="window.print()" class="btn btn-primary">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>

            <div class="card-body">
                <form method="GET" action="">
                    <div class="filters">
                        <div class="form-group">
                            <label for="month">
                                <i class="far fa-calendar"></i> Month
                            </label>
                            <input type="month" 
                                   id="month" 
                                   name="month" 
                                   value="<?= htmlspecialchars($filterMonth) ?>"
                                   max="<?= date('Y-m') ?>">
                        </div>

                        <div class="form-group">
                            <label for="employee_id">
                                <i class="fas fa-user"></i> Employee
                            </label>
                            <select id="employee_id" name="employee_id">
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
                            <label for="department_id">
                                <i class="fas fa-building"></i> Department
                            </label>
                            <select id="department_id" name="department_id">
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
                            <label for="status">
                                <i class="fas fa-flag"></i> Status
                            </label>
                            <select id="status" name="status">
                                <option value="">All Status</option>
                                <option value="present" <?= $filterStatus === 'present' ? 'selected' : '' ?>>Present</option>
                                <option value="absent" <?= $filterStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                                <option value="leave" <?= $filterStatus === 'leave' ? 'selected' : '' ?>>Leave</option>
                                <option value="holiday" <?= $filterStatus === 'holiday' ? 'selected' : '' ?>>Holiday</option>
                            </select>
                        </div>

                        <div class="form-group" style="align-self: flex-end;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Chart Card -->
        <?php if ($totalRecords > 0): ?>
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-chart-pie"></i>
                    Attendance Distribution
                </h2>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Records Table -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-table"></i>
                    Attendance Records
                </h2>
                <span style="color: #718096; font-size: 14px;">
                    <?= date('F Y', strtotime($filterMonth)) ?>
                </span>
            </div>

            <div class="card-body">
                <?php if (empty($attendanceRecords)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No Records Found</h3>
                        <p>No attendance records found for the selected filters.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Type</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendanceRecords as $record): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($record['date'])) ?></td>
                                <td><?= htmlspecialchars($record['employee_name']) ?></td>
                                <td><?= htmlspecialchars($record['department_name'] ?? 'N/A') ?></td>
                                <td><?= htmlspecialchars($record['designation']) ?></td>
                                <td><?= ucfirst($record['employment_type']) ?></td>
                                <td>
                                    <span class="status-badge <?= strtolower($record['status']) ?>">
                                        <?= ucfirst($record['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <a href="admin_dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>

    <?php if ($totalRecords > 0): ?>
    <script>
        const ctx = document.getElementById('attendanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: ['Present', 'Absent', 'Leave', 'Holiday'],
                datasets: [{
                    data: [<?= $presentCount ?>, <?= $absentCount ?>, <?= $leaveCount ?>, <?= $holidayCount ?>],
                    backgroundColor: [
                        '#48bb78',
                        '#f56565',
                        '#4299e1',
                        '#a0aec0'
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 14,
                                family: 'Roboto'
                            }
                        }
                    },
                    tooltip: {
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
