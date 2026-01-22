<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';

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

// Fetch holidays for the current month
$holidayStmt = $db->prepare("SELECT holiday_date, holiday_name, holiday_type 
                              FROM holidays 
                              WHERE YEAR(holiday_date) = :year 
                              AND MONTH(holiday_date) = :month 
                              AND is_active = 1");
$holidayStmt->execute([':year' => $year, ':month' => $month]);
$holidays = [];
while ($holiday = $holidayStmt->fetch(PDO::FETCH_ASSOC)) {
    $holidays[$holiday['holiday_date']] = $holiday;
}

// Fetch approved leave requests that overlap with the current month
$month_start = "$year-$month-01";
$month_end = date('Y-m-t', strtotime($month_start));

$leaveStmt = $db->prepare("SELECT lr.employee_id, 
                                  lr.start_date, 
                                  lr.end_date,
                                  lr.leave_type,
                                  lr.status,
                                  e.full_name as employee_name,
                                  DATEDIFF(lr.end_date, lr.start_date) + 1 as leave_days
                           FROM leave_requests lr
                           JOIN employees e ON lr.employee_id = e.employee_id
                           WHERE lr.status = 'approved'
                           AND ((YEAR(lr.start_date) = :year AND MONTH(lr.start_date) = :month)
                                OR (YEAR(lr.end_date) = :year AND MONTH(lr.end_date) = :month)
                                OR (lr.start_date <= :month_start AND lr.end_date >= :month_end))");
$leaveStmt->execute([
    ':year' => $year, 
    ':month' => $month,
    ':month_start' => $month_start,
    ':month_end' => $month_end
]);
$leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT 
            DATE(a.date) as attendance_date,
            a.employee_id,
            e.full_name as employee_name,
            a.status
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE YEAR(a.date) = :year
        AND MONTH(a.date) = :month";

if ($filterEmployee) {
    $sql .= " AND a.employee_id = :employee_id";
}

$sql .= " ORDER BY a.date, e.full_name";

$stmt = $db->prepare($sql);
$params = [':year' => $year, ':month' => $month];
if ($filterEmployee) {
    $params[':employee_id'] = $filterEmployee;
}
$stmt->execute($params);

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
$totalHoliday = count($holidays);

foreach ($attendanceData as $date => $records) {
    foreach ($records as $record) {
        switch (strtolower($record['status'])) {
            case 'present':
                $totalPresent++;
                break;
            case 'absent':
                $totalAbsent++;
                break;
            case 'leave':
                $totalLeave++;
                break;
        }
    }
}

// Previous and next month navigation
$prevMonth = date('Y-m', strtotime($filterMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($filterMonth . '-01 +1 month'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Calendar - Employee Portal</title>
    <?php include 'includes/employee_styles.php'; ?>
    <style>
        .calendar-wrapper {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
        }
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }
        .month-navigation h2 {
            font-size: 24px;
            color: #1e293b;
            margin: 0;
        }
        .month-nav-buttons {
            display: flex;
            gap: 10px;
        }
        .month-nav-buttons a, .btn {
            padding: 8px 16px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        .month-nav-buttons a:hover, .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 10px;
            margin-top: 20px;
        }
        .calendar-day-header {
            text-align: center;
            padding: 10px;
            font-weight: 600;
            color: #667eea;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .calendar-day {
            min-height: 80px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            background: white;
            transition: all 0.3s;
        }
        .calendar-day:hover {
            border-color: #667eea;
            box-shadow: 0 2px 10px rgba(102, 126, 234, 0.1);
        }
        .calendar-day.empty {
            background: #f8f9fa;
            opacity: 0.5;
        }
        .day-number {
            font-weight: 600;
            margin-bottom: 5px;
            color: #1e293b;
        }
        .day-status {
            font-size: 11px;
            padding: 3px 8px;
            border-radius: 10px;
            display: inline-block;
            font-weight: 600;
        }
        .status-present {
            background: #dcfce7;
            color: #166534;
        }
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }
        .status-leave {
            background: #fef3c7;
            color: #92400e;
        }
        .legend {
            display: flex;
            gap: 20px;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }
        .legend-box {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <?php include 'includes/employee_navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Attendance Calendar</span>
            </div>
            <h1><i class="fas fa-calendar-alt"></i> Attendance Calendar</h1>
        </div>

        <div class="calendar-wrapper">
            <div class="calendar-header">
                <div class="month-navigation">
                    <h2><?= htmlspecialchars($monthName) ?></h2>
                </div>
                <div class="month-nav-buttons">
                    <a href="?month=<?= $prevMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="?month=<?= $nextMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <form method="GET" action="" style="margin: 20px 0;">
                <div style="display: flex; gap: 15px; align-items: end;">
                    <div>
                        <label for="month" style="display: block; margin-bottom: 5px; font-weight: 500;">
                            <i class="far fa-calendar"></i> Month
                        </label>
                        <input type="month" 
                               id="month" 
                               name="month" 
                               value="<?= htmlspecialchars($filterMonth) ?>"
                               style="padding: 8px; border: 2px solid #e2e8f0; border-radius: 5px;">
                    </div>
                    <div>
                        <label for="employee_id" style="display: block; margin-bottom: 5px; font-weight: 500;">
                            <i class="fas fa-user"></i> Employee
                        </label>
                        <select id="employee_id" 
                                name="employee_id"
                                style="padding: 8px; border: 2px solid #e2e8f0; border-radius: 5px; min-width: 200px;">
                            <option value="">All Employees</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['employee_id'] ?>" <?= $filterEmployee == $emp['employee_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" style="padding: 8px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 5px; cursor: pointer;">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>

            <div class="stats-row">
                <div class="stat-card present">
                    <div class="stat-info">
                        <h3>Present Days</h3>
                        <p><?= $totalPresent ?></p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                <div class="stat-card absent">
                    <div class="stat-info">
                        <h3>Absent Days</h3>
                        <p><?= $totalAbsent ?></p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
                <div class="stat-card leave">
                    <div class="stat-info">
                        <h3>Leave Days</h3>
                        <p><?= $totalLeave ?></p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                </div>
                <div class="stat-card holiday">
                    <div class="stat-info">
                        <h3>Holidays</h3>
                        <p><?= $totalHoliday ?></p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="calendar-container">
            <div class="calendar">
                <!-- Day headers -->
                <div class="calendar-day header">Sun</div>
                <div class="calendar-day header">Mon</div>
                <div class="calendar-day header">Tue</div>
                <div class="calendar-day header">Wed</div>
                <div class="calendar-day header">Thu</div>
                <div class="calendar-day header">Fri</div>
                <div class="calendar-day header">Sat</div>

                <!-- Empty cells for days before month starts -->
                <?php for ($i = 0; $i < $dayOfWeek; $i++): ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>

                <!-- Calendar days -->
                <?php for ($day = 1; $day <= $daysInMonth; $day++): 
                    $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $isToday = $currentDate === date('Y-m-d');
                    $dayAttendance = $attendanceData[$currentDate] ?? [];
                    
                    // Check if this date is a holiday
                    $isHoliday = isset($holidays[$currentDate]);
                    $holidayInfo = $isHoliday ? $holidays[$currentDate] : null;
                    
                    // Check if any approved leave falls on this date
                    $leavesOnDate = [];
                    foreach ($leaveRequests as $leave) {
                        if ($currentDate >= $leave['start_date'] && $currentDate <= $leave['end_date']) {
                            $leavesOnDate[] = $leave;
                        }
                    }
                ?>
                    <div class="calendar-day <?= $isToday ? 'today' : '' ?> <?= $isHoliday ? 'has-holiday' : '' ?>" 
                         title="<?= $isHoliday ? htmlspecialchars($holidayInfo['holiday_name']) : '' ?>">
                        <div class="day-number"><?= $day ?></div>
                        
                        <!-- Display holiday badge -->
                        <?php if ($isHoliday): ?>
                            <div class="holiday-badge" title="<?= htmlspecialchars($holidayInfo['holiday_name']) ?>">
                                <i class="fas fa-gift"></i> <?= htmlspecialchars($holidayInfo['holiday_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display leave badges -->
                        <?php foreach ($leavesOnDate as $leave): ?>
                            <div class="leave-badge" title="<?= htmlspecialchars($leave['employee_name']) ?> - <?= ucfirst($leave['leave_type']) ?> Leave">
                                <i class="fas fa-umbrella-beach"></i> 
                                <?= htmlspecialchars($leave['employee_name']) ?> (<?= $leave['leave_days'] ?> day<?= $leave['leave_days'] > 1 ? 's' : '' ?>)
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- Display attendance status dots -->
                        <?php if (!empty($dayAttendance)): ?>
                            <div class="attendance-indicators">
                                <?php foreach ($dayAttendance as $record): ?>
                                    <div class="status-indicator <?= strtolower($record['status']) ?>" 
                                         title="<?= htmlspecialchars($record['employee_name']) ?> - <?= ucfirst($record['status']) ?>"></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>

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
                    <span>On Leave</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot holiday"></div>
                    <span>Holiday</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-gift" style="color: #f39c12; margin-right: 5px;"></i>
                    <span>Govt Holiday</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-umbrella-beach" style="color: #4299e1; margin-right: 5px;"></i>
                    <span>Approved Leave</span>
                </div>
            </div>
            
            <!-- Holidays List -->
            <?php if (!empty($holidays)): ?>
            <div style="margin-top: 30px; padding: 20px; background: #fff8e1; border-left: 4px solid #f39c12; border-radius: 8px;">
                <h3 style="margin-bottom: 15px; color: #e67e22;">
                    <i class="fas fa-calendar-star"></i> Government Holidays in <?= $monthName ?>
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px;">
                    <?php foreach ($holidays as $holiday): ?>
                        <div style="padding: 10px; background: white; border-radius: 6px; border: 1px solid #f39c12;">
                            <strong style="color: #e67e22;"><?= date('M d, Y', strtotime($holiday['holiday_date'])) ?></strong><br>
                            <?= htmlspecialchars($holiday['holiday_name']) ?>
                            <?php if ($holiday['holiday_type'] === 'optional'): ?>
                                <span style="font-size: 10px; background: #ffeaa7; padding: 2px 6px; border-radius: 3px; margin-left: 5px;">Optional</span>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>
