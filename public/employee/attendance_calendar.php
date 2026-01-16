<?php
session_start();

// Auth Check
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/Attendance.php";
require_once "../../app/Config/database.php";

$db = getDBConnection();
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

// Navigation
$prevMonth = date('Y-m', strtotime($filterMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($filterMonth . '-01 +1 month'));

// Fetch holidays
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

// Fetch approved leave requests
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
                           AND ((YEAR(lr.start_date) = :year1 AND MONTH(lr.start_date) = :month1)
                                OR (YEAR(lr.end_date) = :year2 AND MONTH(lr.end_date) = :month2)
                                OR (lr.start_date <= :month_start AND lr.end_date >= :month_end))");
$leaveStmt->execute([
    ':year1' => $year, 
    ':month1' => $month,
    ':year2' => $year,
    ':month2' => $month,
    ':month_start' => $month_start,
    ':month_end' => $month_end
]);
$leaveRequests = $leaveStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Attendance
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
            case 'present': $totalPresent++; break;
            case 'absent': $totalAbsent++; break;
            case 'leave': $totalLeave++; break;
        }
    }
}

// Page Setup
$pageTitle = "Attendance Calendar";
include 'includes/header.php';
?>

<!-- Header Actions -->
<div class="calendar-header">
    <div class="filter-group">
        <a href="?month=<?= $prevMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>" class="btn btn-outline" title="Previous Month">
            <i class="fas fa-chevron-left"></i>
        </a>
        <h2 style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); min-width: 200px; text-align: center;">
            <?= htmlspecialchars($monthName) ?>
        </h2>
        <a href="?month=<?= $nextMonth ?><?= $filterEmployee ? '&employee_id=' . $filterEmployee : '' ?>" class="btn btn-outline" title="Next Month">
            <i class="fas fa-chevron-right"></i>
        </a>
    </div>

    <form method="GET" action="" class="filter-group">
        <input type="month" name="month" value="<?= htmlspecialchars($filterMonth) ?>" class="form-select">
        
        <select name="employee_id" class="form-select">
            <option value="">All Employees</option>
            <?php foreach ($allEmployees as $emp): ?>
            <option value="<?= $emp['employee_id'] ?>" <?= $filterEmployee == $emp['employee_id'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($emp['full_name']) ?>
            </option>
            <?php endforeach; ?>
        </select>
        
        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i> Apply
        </button>
    </form>
</div>

<!-- Stats Summary -->
<div class="stats-grid" style="margin-bottom: 2rem;">
    <div class="glass-card bg-green" style="padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
             <span>Present</span>
             <span style="font-weight:700; font-size:1.5rem;"><?= $totalPresent ?></span>
        </div>
    </div>
    <div class="glass-card bg-pink" style="padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
             <span>Absent</span>
             <span style="font-weight:700; font-size:1.5rem;"><?= $totalAbsent ?></span>
        </div>
    </div>
     <div class="glass-card bg-indigo" style="padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
             <span>Leave</span>
             <span style="font-weight:700; font-size:1.5rem;"><?= $totalLeave ?></span>
        </div>
    </div>
    <div class="glass-card bg-orange" style="padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
             <span>Holidays</span>
             <span style="font-weight:700; font-size:1.5rem;"><?= $totalHoliday ?></span>
        </div>
    </div>
</div>

<!-- Calendar Grid -->
<div class="calendar-wrapper">
    <div class="calendar-grid">
        <div class="cal-header-cell">Sun</div>
        <div class="cal-header-cell">Mon</div>
        <div class="cal-header-cell">Tue</div>
        <div class="cal-header-cell">Wed</div>
        <div class="cal-header-cell">Thu</div>
        <div class="cal-header-cell">Fri</div>
        <div class="cal-header-cell">Sat</div>

        <!-- Empty cells -->
        <?php for ($i = 0; $i < $dayOfWeek; $i++): ?>
            <div class="cal-day empty" style="background:transparent; border:none; box-shadow:none;"></div>
        <?php endfor; ?>

        <!-- Days -->
        <?php for ($day = 1; $day <= $daysInMonth; $day++): 
            $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $day);
            $isToday = $currentDate === date('Y-m-d');
            $dayAttendance = $attendanceData[$currentDate] ?? [];
            $isHoliday = isset($holidays[$currentDate]);
            $holidayInfo = $isHoliday ? $holidays[$currentDate] : null;

            // Leaves on this day
            $leavesOnDate = [];
            foreach ($leaveRequests as $leave) {
                if ($currentDate >= $leave['start_date'] && $currentDate <= $leave['end_date']) {
                    $leavesOnDate[] = $leave;
                }
            }
        ?>
            <div class="cal-day <?= $isToday ? 'today' : '' ?>">
                <div class="day-number"><?= $day ?></div>
                
                <?php if ($isHoliday): ?>
                    <div class="cal-event holiday" title="<?= htmlspecialchars($holidayInfo['holiday_name']) ?>">
                        <i class="fas fa-gift"></i> <?= substr(htmlspecialchars($holidayInfo['holiday_name']), 0, 15) ?>
                    </div>
                <?php endif; ?>

                <?php foreach ($leavesOnDate as $leave): ?>
                    <div class="cal-event leave" title="<?= htmlspecialchars($leave['employee_name']) ?> (<?= $leave['leave_type'] ?>)">
                        <i class="fas fa-umbrella-beach"></i> <?= substr(htmlspecialchars($leave['employee_name']), 0, 10) ?>
                    </div>
                <?php endforeach; ?>

                <?php foreach ($dayAttendance as $record): ?>
                    <?php if (strtolower($record['status']) === 'present'): ?>
                        <div class="cal-event present" title="<?= $record['employee_name'] ?>">
                             P - <?= substr($record['employee_name'], 0, 10) ?>
                        </div>
                    <?php elseif (strtolower($record['status']) === 'absent'): ?>
                         <div class="cal-event absent" title="<?= $record['employee_name'] ?>">
                             A - <?= substr($record['employee_name'], 0, 10) ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endfor; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
