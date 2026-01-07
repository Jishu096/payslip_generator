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

$employeeModel = new Employee();
$attendanceModel = new Attendance();

// Get filters
$filterEmployee = $_GET['employee_id'] ?? '';
$filterMonth = $_GET['month'] ?? date('Y-m');

// Get all employees for filter
$allEmployees = $employeeModel->getAllEmployees();

// Calculate calendar data
$year = date('Y', strtotime($filterMonth . '-01'));
$month = date('m', strtotime($filterMonth . '-01'));
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);
$monthName = date('F Y', $firstDay);

// Get attendance data for the month
$db = getDBConnection();

if ($filterEmployee) {
    $sql = "SELECT 
                DATE(a.date) as attendance_date,
                a.status,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name
            FROM attendance a
            JOIN employees e ON a.employee_id = e.employee_id
            WHERE a.employee_id = :employee_id
            AND YEAR(a.date) = :year
            AND MONTH(a.date) = :month";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':employee_id' => $filterEmployee,
        ':year' => $year,
        ':month' => $month
    ]);
} else {
    $sql = "SELECT 
                DATE(a.date) as attendance_date,
                a.status,
                CONCAT(e.first_name, ' ', e.last_name) as employee_name,
                COUNT(*) as count
            FROM attendance a
            JOIN employees e ON a.employee_id = e.employee_id
            WHERE YEAR(a.date) = :year
            AND MONTH(a.date) = :month
            GROUP BY DATE(a.date), a.status";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':year' => $year,
        ':month' => $month
    ]);
}

$attendanceData = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $date = $row['attendance_date'];
    if (!isset($attendanceData[$date])) {
        $attendanceData[$date] = [];
    }
    $attendanceData[$date][] = $row;
}

// Calculate summary
$totalPresent = 0;
$totalAbsent = 0;
$totalLeave = 0;
$totalHoliday = 0;

foreach ($attendanceData as $date => $records) {
    foreach ($records as $record) {
        switch (strtolower($record['status'])) {
            case 'present':
                $totalPresent += $filterEmployee ? 1 : ($record['count'] ?? 1);
                break;
            case 'absent':
                $totalAbsent += $filterEmployee ? 1 : ($record['count'] ?? 1);
                break;
            case 'leave':
                $totalLeave += $filterEmployee ? 1 : ($record['count'] ?? 1);
                break;
            case 'holiday':
                $totalHoliday += $filterEmployee ? 1 : ($record['count'] ?? 1);
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Calendar - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            max-width: 1100px;
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
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.present {
            background: #48bb78;
        }

        .stat-icon.absent {
            background: #f56565;
        }

        .stat-icon.leave {
            background: #4299e1;
        }

        .stat-icon.holiday {
            background: #718096;
        }

        .stat-details {
            flex: 1;
        }

        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
        }

        .stat-label {
            font-size: 12px;
            color: #718096;
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
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 200px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group select,
        .form-group input {
            width: 100%;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
        }

        .calendar-header {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: 700;
            font-size: 13px;
            border-radius: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .calendar-day {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            min-height: 70px;
            position: relative;
            transition: all 0.3s;
            cursor: pointer;
        }

        .calendar-day:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
            transform: translateY(-2px);
        }

        .calendar-day.empty {
            background: #f7fafc;
            cursor: default;
        }

        .calendar-day.empty:hover {
            border-color: #e2e8f0;
            box-shadow: none;
            transform: none;
        }

        .calendar-day.today {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }

        .day-number {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .calendar-day.today .day-number {
            color: #667eea;
        }

        .day-status {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .status-dot {
            width: 100%;
            height: 4px;
            border-radius: 2px;
            transition: all 0.3s;
        }

        .status-dot.present {
            background: #48bb78;
        }

        .status-dot.absent {
            background: #f56565;
        }

        .status-dot.leave {
            background: #4299e1;
        }

        .status-dot.holiday {
            background: #718096;
        }

        .status-count {
            font-size: 11px;
            color: #718096;
            margin-top: 4px;
        }

        .legend {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 20px;
            background: #f7fafc;
            border-radius: 8px;
            margin-top: 20px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
        }

        .legend-dot {
            width: 16px;
            height: 16px;
            border-radius: 4px;
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

        .month-nav {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .month-nav a {
            padding: 8px 16px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: #2d3748;
            font-weight: 600;
            transition: all 0.3s;
        }

        .month-nav a:hover {
            border-color: #667eea;
            color: #667eea;
        }

        .month-title {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
        }

        @media (max-width: 968px) {
            .calendar {
                gap: 5px;
            }

            .calendar-day {
                min-height: 80px;
                padding: 8px;
            }

            .day-number {
                font-size: 14px;
            }
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .filters {
                flex-direction: column;
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
                <span>Attendance Calendar</span>
            </div>
            <h1><i class="fas fa-calendar-alt"></i> Attendance Calendar</h1>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon present">
                    <i class="fas fa-check"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-number"><?= $totalPresent ?></div>
                    <div class="stat-label">Present</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon absent">
                    <i class="fas fa-times"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-number"><?= $totalAbsent ?></div>
                    <div class="stat-label">Absent</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon leave">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-number"><?= $totalLeave ?></div>
                    <div class="stat-label">Leave</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon holiday">
                    <i class="fas fa-umbrella-beach"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-number"><?= $totalHoliday ?></div>
                    <div class="stat-label">Holiday</div>
                </div>
            </div>
        </div>

        <!-- Calendar Card -->
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-calendar"></i>
                    Calendar View
                </h2>
                <div class="month-nav">
                    <?php
                    $prevMonth = date('Y-m', strtotime($filterMonth . '-01 -1 month'));
                    $nextMonth = date('Y-m', strtotime($filterMonth . '-01 +1 month'));
                    $currentMonth = date('Y-m');
                    ?>
                    <a href="?month=<?= $prevMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <span class="month-title"><?= $monthName ?></span>
                    <?php if ($nextMonth <= $currentMonth): ?>
                    <a href="?month=<?= $nextMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body">
                <!-- Filters -->
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

                        <div class="form-group" style="align-self: flex-end;">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-filter"></i> Apply Filter
                            </button>
                        </div>
                    </div>
                </form>

                <!-- Calendar Grid -->
                <div class="calendar">
                    <!-- Day Headers -->
                    <div class="calendar-header">Sun</div>
                    <div class="calendar-header">Mon</div>
                    <div class="calendar-header">Tue</div>
                    <div class="calendar-header">Wed</div>
                    <div class="calendar-header">Thu</div>
                    <div class="calendar-header">Fri</div>
                    <div class="calendar-header">Sat</div>

                    <!-- Empty cells before first day -->
                    <?php for ($i = 0; $i < $dayOfWeek; $i++): ?>
                        <div class="calendar-day empty"></div>
                    <?php endfor; ?>

                    <!-- Days of month -->
                    <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                        $isToday = $currentDate === date('Y-m-d');
                        $dayRecords = $attendanceData[$currentDate] ?? [];
                        
                        // Group by status
                        $statusCounts = ['present' => 0, 'absent' => 0, 'leave' => 0, 'holiday' => 0];
                        foreach ($dayRecords as $record) {
                            $status = strtolower($record['status']);
                            if ($filterEmployee) {
                                $statusCounts[$status]++;
                            } else {
                                $statusCounts[$status] += ($record['count'] ?? 1);
                            }
                        }
                    ?>
                        <div class="calendar-day <?= $isToday ? 'today' : '' ?>" 
                             title="<?= date('l, F j, Y', strtotime($currentDate)) ?>">
                            <div class="day-number"><?= $day ?></div>
                            <?php if (!empty($dayRecords)): ?>
                                <div class="day-status">
                                    <?php if ($statusCounts['present'] > 0): ?>
                                        <div class="status-dot present" title="Present: <?= $statusCounts['present'] ?>"></div>
                                    <?php endif; ?>
                                    <?php if ($statusCounts['absent'] > 0): ?>
                                        <div class="status-dot absent" title="Absent: <?= $statusCounts['absent'] ?>"></div>
                                    <?php endif; ?>
                                    <?php if ($statusCounts['leave'] > 0): ?>
                                        <div class="status-dot leave" title="Leave: <?= $statusCounts['leave'] ?>"></div>
                                    <?php endif; ?>
                                    <?php if ($statusCounts['holiday'] > 0): ?>
                                        <div class="status-dot holiday" title="Holiday: <?= $statusCounts['holiday'] ?>"></div>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$filterEmployee): ?>
                                    <div class="status-count">
                                        <?= array_sum($statusCounts) ?> records
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endfor; ?>
                </div>

                <!-- Legend -->
                <div class="legend">
                    <div class="legend-item">
                        <div class="legend-dot present"></div>
                        <span>Present</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot absent"></div>
                        <span>Absent</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot leave"></div>
                        <span>Leave</span>
                    </div>
                    <div class="legend-item">
                        <div class="legend-dot holiday"></div>
                        <span>Holiday</span>
                    </div>
                    <div class="legend-item">
                        <div style="width: 16px; height: 16px; border: 2px solid #667eea; border-radius: 4px;"></div>
                        <span>Today</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <a href="admin_dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>
</body>
</html>
