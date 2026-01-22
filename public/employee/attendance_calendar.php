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

        <!-- Calendar Wrapper -->
        <div class="calendar-wrapper">
            <!-- Calendar Header with Navigation -->
            <div class="calendar-header">
                <div class="month-navigation">
                    <h2><i class="fas fa-calendar-day"></i> <?= htmlspecialchars($monthName) ?></h2>
                </div>
                <div class="month-nav-buttons">
                    <a href="?month=<?= $prevMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="?month=<?= date('Y-m') ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        <i class="fas fa-calendar-check"></i> Today
                    </a>
                    <a href="?month=<?= $nextMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>
            
            <!-- Filters Form -->
            <form method="GET" action="" class="filter-form">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="month">
                            <i class="far fa-calendar"></i> Select Month
                        </label>
                        <input type="month" 
                               id="month" 
                               name="month" 
                               value="<?= htmlspecialchars($filterMonth) ?>"
                               required>
                    </div>
                    <div class="filter-group">
                        <label for="employee_id">
                            <i class="fas fa-user"></i> Select Employee
                        </label>
                        <select id="employee_id" name="employee_id">
                            <option value="">All Employees</option>
                            <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= $emp['employee_id'] ?>" <?= $filterEmployee == $emp['employee_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($emp['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-search"></i> Apply Filter
                    </button>
                </div>
            </form>

            <!-- Summary Statistics -->
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

        <!-- Calendar Grid Container -->
        <div class="calendar-container">
            <div class="calendar">
                <!-- Day Headers -->
                <div class="calendar-day header">Sunday</div>
                <div class="calendar-day header">Monday</div>
                <div class="calendar-day header">Tuesday</div>
                <div class="calendar-day header">Wednesday</div>
                <div class="calendar-day header">Thursday</div>
                <div class="calendar-day header">Friday</div>
                <div class="calendar-day header">Saturday</div>

                <!-- Empty cells for days before month starts -->
                <?php for ($i = 0; $i < $dayOfWeek; $i++): ?>
                    <div class="calendar-day empty"></div>
                <?php endfor; ?>

                <!-- Calendar Days -->
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
                         title="<?= $isHoliday ? htmlspecialchars($holidayInfo['holiday_name']) : date('F d, Y', strtotime($currentDate)) ?>">
                        <div class="day-number">
                            <?= $day ?>
                            <?php if ($isToday): ?>
                                <i class="fas fa-star" style="font-size: 10px; color: var(--accent);"></i>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Display Holiday Badge -->
                        <?php if ($isHoliday): ?>
                            <div class="holiday-badge" title="<?= htmlspecialchars($holidayInfo['holiday_name']) ?>">
                                <i class="fas fa-gift"></i> <?= htmlspecialchars(strlen($holidayInfo['holiday_name']) > 15 ? substr($holidayInfo['holiday_name'], 0, 15) . '...' : $holidayInfo['holiday_name']) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display Leave Badges -->
                        <?php foreach (array_slice($leavesOnDate, 0, 2) as $leave): ?>
                            <div class="leave-badge" title="<?= htmlspecialchars($leave['employee_name']) ?> - <?= ucfirst($leave['leave_type']) ?> Leave">
                                <i class="fas fa-umbrella-beach"></i> 
                                <?= htmlspecialchars(strlen($leave['employee_name']) > 12 ? substr($leave['employee_name'], 0, 12) . '...' : $leave['employee_name']) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($leavesOnDate) > 2): ?>
                            <div class="leave-badge" title="<?= count($leavesOnDate) - 2 ?> more leave(s)">
                                <i class="fas fa-plus"></i> <?= count($leavesOnDate) - 2 ?> more
                            </div>
                        <?php endif; ?>
                        
                        <!-- Display Attendance Status Indicators -->
                        <?php if (!empty($dayAttendance)): ?>
                            <div class="attendance-indicators">
                                <?php foreach (array_slice($dayAttendance, 0, 10) as $record): ?>
                                    <div class="status-indicator <?= strtolower($record['status']) ?>" 
                                         title="<?= htmlspecialchars($record['employee_name']) ?> - <?= ucfirst($record['status']) ?>"></div>
                                <?php endforeach; ?>
                                <?php if (count($dayAttendance) > 10): ?>
                                    <div class="status-indicator" style="background: #cbd5e0;" 
                                         title="<?= count($dayAttendance) - 10 ?> more records">+<?= count($dayAttendance) - 10 ?></div>
                                <?php endif; ?>
                            </div>
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
                    <span>On Leave</span>
                </div>
                <div class="legend-item">
                    <div class="legend-dot holiday"></div>
                    <span>Holiday</span>
                </div>
                <div class="legend-item">
                    <i class="fas fa-star" style="color: var(--accent); font-size: 14px;"></i>
                    <span>Today</span>
                </div>
            </div>
            
            <!-- Holidays List Section -->
            <?php if (!empty($holidays)): ?>
            <div class="holiday-section">
                <h3>
                    <i class="fas fa-calendar-star"></i> Government Holidays in <?= $monthName ?>
                </h3>
                <div class="holidays-grid">
                    <?php foreach ($holidays as $holiday): ?>
                        <div class="holiday-item">
                            <div class="holiday-date">
                                <i class="far fa-calendar-alt"></i> <?= date('l, M d, Y', strtotime($holiday['holiday_date'])) ?>
                            </div>
                            <div class="holiday-name">
                                <?= htmlspecialchars($holiday['holiday_name']) ?>
                                <?php if ($holiday['holiday_type'] === 'optional'): ?>
                                    <span class="optional-tag">OPTIONAL</span>
                                <?php endif; ?>
                            </div>
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
