<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/Attendance.php";
require_once "../../app/Models/Department.php";
require_once __DIR__ . '/../../app/Config/database.php';

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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Override/Additions to admin_styles for specific needs */
        .stats-grid-reports {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card-glass {
            background: rgba(255,255,255,0.9);
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-radius: 16px;
            padding: 24px;
            border: 1px solid rgba(255,255,255,0.5);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }
        
        .stat-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .stat-icon-wrapper {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
            line-height: 1;
        }

        .stat-label {
            font-size: 13px;
            color: #718096;
            margin-top: 5px;
        }
        
        .chart-section {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }
        
        .table-glass {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            overflow: hidden;
            border: 1px solid #e2e8f0;
        }

        .table-glass th {
            background: #f8f9fa;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            font-size: 12px;
            padding: 16px 24px;
        }

        .table-glass td {
            padding: 16px 24px;
            color: #2d3748;
            border-bottom: 1px solid #edf2f7;
        }

        .table-glass tr:last-child td {
            border-bottom: none;
        }

        @media (max-width: 992px) {
            .chart-section { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1>Attendance Reports</h1>
                <p>Detailed analytics and attendance records for <?= date('F Y', strtotime($filterMonth)) ?></p>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid-reports">
                <div class="stat-card-glass">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value"><?= $totalRecords ?></div>
                            <div class="stat-label">TOTAL RECORDS</div>
                        </div>
                        <div class="stat-icon-wrapper bg-gradient-purple" style="color: white;">
                             <i class="fas fa-clipboard-list"></i>
                        </div>
                    </div>
                </div>

                <div class="stat-card-glass">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" style="color: #48bb78;"><?= $presentCount ?></div>
                            <div class="stat-label">PRESENT</div>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #c6f6d5; color: #22543d;">
                             <i class="fas fa-check"></i>
                        </div>
                    </div>
                </div>

                 <div class="stat-card-glass">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" style="color: #f56565;"><?= $absentCount ?></div>
                            <div class="stat-label">ABSENT</div>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #fed7d7; color: #742a2a;">
                             <i class="fas fa-times"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card-glass">
                    <div class="stat-header">
                        <div>
                            <div class="stat-value" style="color: #2d3748;"><?= $attendanceRate ?>%</div>
                            <div class="stat-label">ATTENDANCE RATE</div>
                        </div>
                        <div class="stat-icon-wrapper" style="background: #edf2f7; color: #4a5568;">
                             <i class="fas fa-percentage"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="chart-section">
                <!-- Records List -->
                <div class="card" style="margin-bottom: 0;">
                    <div class="card-header" style="background: white; padding-bottom: 0; border: none;">
                         <h3><i class="fas fa-table" style="color: #667eea; margin-right: 10px;"></i> Detailed Records</h3>
                         <div style="display: flex; gap: 10px;">
                            <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="glass-btn btn" style="font-size: 13px; color: #2d3748;">
                                <i class="fas fa-download"></i> CSV
                            </a>
                            <button onclick="window.print()" class="glass-btn btn" style="font-size: 13px; color: #2d3748;">
                                <i class="fas fa-print"></i> Print
                            </button>
                         </div>
                    </div>
                    <div class="card-body" style="padding-top: 15px;">
                         <?php if (empty($attendanceRecords)): ?>
                            <div class="empty-state" style="text-align: center; padding: 40px; color: #a0aec0;">
                                <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 20px;"></i>
                                <p>No records found for the selected filters.</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
                                <table class="table-glass">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Employee</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendanceRecords as $record): ?>
                                        <tr>
                                            <td><?= date('M d, Y', strtotime($record['date'])) ?></td>
                                            <td>
                                                <div style="font-weight: 500;"><?= htmlspecialchars($record['employee_name']) ?></div>
                                                <div style="font-size: 12px; color: #718096;"><?= htmlspecialchars($record['designation']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($record['department_name'] ?? '-') ?></td>
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
                </div>

                <!-- Filters & Chart Sidebar -->
                <div style="display: flex; flex-direction: column; gap: 20px;">
                    <!-- Filter Card -->
                    <div class="card">
                        <div class="card-header" style="background: white; border-bottom: 1px solid #f7fafc;">
                            <h3 style="font-size: 16px;"><i class="fas fa-filter" style="color: #667eea;"></i> Filters</h3>
                        </div>
                        <div class="card-body">
                            <form method="GET">
                                <div style="display: flex; flex-direction: column; gap: 15px;">
                                    <div class="form-group">
                                        <label>Month</label>
                                        <input type="month" name="month" value="<?= htmlspecialchars($filterMonth) ?>">
                                    </div>
                                    <div class="form-group">
                                        <label>Department</label>
                                        <select name="department_id">
                                            <option value="">All Departments</option>
                                            <?php foreach ($allDepartments as $dept): ?>
                                                <option value="<?= $dept['department_id'] ?>" <?= $filterDepartment == $dept['department_id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($dept['department_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label>Status</label>
                                        <select name="status">
                                            <option value="">All Status</option>
                                            <option value="present" <?= $filterStatus === 'present' ? 'selected' : '' ?>>Present</option>
                                            <option value="absent" <?= $filterStatus === 'absent' ? 'selected' : '' ?>>Absent</option>
                                            <option value="leave" <?= $filterStatus === 'leave' ? 'selected' : '' ?>>Leave</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">Apply Filters</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Chart Card -->
                    <?php if ($totalRecords > 0): ?>
                    <div class="card">
                         <div class="card-body">
                            <h4 style="margin-bottom: 15px; font-size: 16px; text-align: center; color: #4a5568;">Distribution</h4>
                            <div style="position: relative; height: 250px;">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                         </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

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
                        '#48bb78', // Present - Green
                        '#f56565', // Absent - Red
                        '#4299e1', // Leave - Blue
                        '#cbd5e0'  // Holiday - Grey
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            usePointStyle: true,
                            padding: 20,
                            font: {
                                size: 12,
                                family: 'Roboto'
                            }
                        }
                    }
                },
                cutout: '70%'
            }
        });
    </script>
    <?php endif; ?>
</body>
</html>
