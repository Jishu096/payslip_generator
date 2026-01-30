<?php
session_start();

// Check if user has hr_officer role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasHRRole = in_array('hr_officer', $userRoles);

if (!isset($_SESSION['user_id']) || (!$hasHRRole && $_SESSION['role'] !== 'hr_officer')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'HR Officer';

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Get filter parameters
$month = $_GET['month'] ?? date('F');
$year = $_GET['year'] ?? date('Y');
$departmentId = $_GET['department'] ?? '';

// Get attendance summary
$query = "SELECT 
    e.employee_id,
    e.full_name,
    e.employee_code,
    d.department_name,
    COUNT(DISTINCT a.date) as total_days,
    SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present_days,
    SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
    SUM(CASE WHEN a.status = 'Leave' THEN 1 ELSE 0 END) as leave_days,
    a.workflow_status
FROM employees e
LEFT JOIN departments d ON e.department_id = d.department_id
LEFT JOIN attendance a ON e.employee_id = a.employee_id 
    AND a.month = :month 
    AND a.year = :year
WHERE e.status = 'active'
" . ($departmentId ? "AND e.department_id = :department_id" : "") . "
GROUP BY e.employee_id, e.full_name, e.employee_code, d.department_name, a.workflow_status
ORDER BY d.department_name, e.full_name";

$stmt = $db->prepare($query);
$params = [':month' => $month, ':year' => $year];
if ($departmentId) {
    $params[':department_id'] = $departmentId;
}
$stmt->execute($params);
$attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$deptStmt = $db->query("SELECT department_id, department_name FROM departments WHERE deleted_at IS NULL ORDER BY department_name");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - HR Officer Portal</title>
    <?php include 'includes/hr_officer_styles.php'; ?>
    <style>
        .filters-bar {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
        }

        .filter-group select {
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn-filter {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: auto;
        }

        .btn-export {
            padding: 10px 20px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            margin-top: auto;
            margin-left: auto;
        }

        .stats-summary {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid var(--accent);
        }

        table {
            width: 100%;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }

        thead {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-draft {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-hr_verified {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_officer_navbar.php'; ?>
    <?php include 'includes/hr_officer_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-chart-bar"></i> Attendance Reports</h1>
            <p>Monthly attendance summaries</p>
        </div>

        <!-- Filters -->
        <form method="GET" class="filters-bar">
            <div class="filter-group">
                <label>Month</label>
                <select name="month">
                    <?php foreach(['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'] as $m): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Year</label>
                <select name="year">
                    <?php for($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="filter-group">
                <label>Department</label>
                <select name="department">
                    <option value="">All Departments</option>
                    <?php foreach($departments as $dept): ?>
                        <option value="<?php echo $dept['department_id']; ?>" <?php echo $departmentId == $dept['department_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dept['department_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn-filter"><i class="fas fa-filter"></i> Filter</button>
            <button type="button" class="btn-export" onclick="exportToExcel()"><i class="fas fa-file-excel"></i> Export</button>
        </form>

        <!-- Statistics -->
        <div class="stats-summary">
            <div class="stat-box">
                <h3><?php echo count($attendanceData); ?></h3>
                <p>Total Employees</p>
            </div>
            <div class="stat-box">
                <h3><?php echo array_sum(array_column($attendanceData, 'present_days')); ?></h3>
                <p>Total Present Days</p>
            </div>
            <div class="stat-box">
                <h3><?php echo array_sum(array_column($attendanceData, 'absent_days')); ?></h3>
                <p>Total Absent Days</p>
            </div>
            <div class="stat-box">
                <h3><?php echo array_sum(array_column($attendanceData, 'leave_days')); ?></h3>
                <p>Total Leave Days</p>
            </div>
        </div>

        <!-- Data Table -->
        <div style="background: white; padding: 20px; border-radius: 12px;">
            <table id="attendanceTable">
                <thead>
                    <tr>
                        <th>Employee Code</th>
                        <th>Employee Name</th>
                        <th>Department</th>
                        <th>Total Days</th>
                        <th>Present</th>
                        <th>Absent</th>
                        <th>Leave</th>
                        <th>Attendance %</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($attendanceData as $row): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['employee_code']); ?></td>
                            <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['department_name']); ?></td>
                            <td><?php echo $row['total_days']; ?></td>
                            <td><?php echo $row['present_days']; ?></td>
                            <td><?php echo $row['absent_days']; ?></td>
                            <td><?php echo $row['leave_days']; ?></td>
                            <td>
                                <?php 
                                $percentage = $row['total_days'] > 0 ? ($row['present_days'] / $row['total_days']) * 100 : 0;
                                echo number_format($percentage, 1) . '%';
                                ?>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $row['workflow_status'] ?? 'draft'; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $row['workflow_status'] ?? 'draft')); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/hr_officer_scripts.php'; ?>
    <script>
        function exportToExcel() {
            const month = '<?php echo $month; ?>';
            const year = '<?php echo $year; ?>';
            const dept = '<?php echo $departmentId; ?>';
            window.location.href = `api/export_attendance_report.php?month=${month}&year=${year}&department=${dept}`;
        }
    </script>
</body>
</html>
