<?php
session_start();

// Allow all authenticated users to view the company-wide attendance calendar
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

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
require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Get holidays for the month
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

// Get leave requests for the month
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
    ':month_start' => sprintf('%04d-%02d-01', $year, $month),
    ':month_end' => date('Y-m-t', $firstDay)
]);

$leaveRequests = [];
while ($leave = $leaveStmt->fetch(PDO::FETCH_ASSOC)) {
    $leaveRequests[] = $leave;
}

// Attendance Query
if ($filterEmployee) {
    $sql = "SELECT 
                DATE(a.date) as attendance_date,
                a.status,
                e.full_name as employee_name
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
                e.full_name as employee_name,
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

// Summary Calculation
$totalPresent = 0;
$totalAbsent = 0;
$totalLeave = 0;
$totalHoliday = count($holidays);

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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .calendar-wrapper {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
        }

        .calendar-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .cal-nav-btn {
            background: #f8f9fa;
            border: 1px solid #e2e8f0;
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.2s;
        }

        .cal-nav-btn:hover {
            border-color: #667eea;
            color: #667eea;
            background: white;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
        }

        .cal-head {
            text-align: center;
            font-weight: 700;
            color: #718096;
            padding: 10px;
            text-transform: uppercase;
            font-size: 13px;
        }

        .cal-day {
            min-height: 120px;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 10px;
            transition: all 0.3s ease;
            position: relative;
            background: white;
            display: flex;
            flex-direction: column;
        }

        .cal-day:not(.empty):hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.08);
            border-color: #667eea;
            z-index: 2;
        }

        .cal-day.today {
            border: 2px solid #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.02), rgba(118, 75, 162, 0.02));
        }

        .cal-day.empty {
            background: #f8f9fa;
            border: none;
        }

        .day-num {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .cal-day.today .day-num {
            color: #667eea;
        }

        .events-list {
            display: flex;
            flex-direction: column;
            gap: 4px;
            flex: 1;
        }

        .badge-event {
            font-size: 11px;
            padding: 3px 6px;
            border-radius: 4px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 4px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .badge-holiday { background: #fefcbf; color: #b7791f; border: 1px solid #f6e05e; }
        .badge-present { background: #c6f6d5; color: #22543d; border: 1px solid #9ae6b4; }
        .badge-absent { background: #fed7d7; color: #742a2a; border: 1px solid #feb2b2; }
        .badge-leave { background: #bee3f8; color: #2c5282; border: 1px solid #90cdf4; }
        .badge-gov { background: #fdebd0; color: #9c640c; border: 1px solid #fbd38d; }

        @media (max-width: 768px) {
            .calendar-grid { display: flex; flex-direction: column; }
            .cal-day { min-height: auto; }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1>Attendance Calendar</h1>
                <p>Overview of employee attendance and leaves for <?= $monthName ?></p>
            </div>

            <!-- Stats Overview -->
            <div class="stats-overview">
                <div class="glass-card" style="padding: 20px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #c6f6d5; color: #22543d; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-check"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 24px; font-weight: 700; margin: 0; color: #2d3748;"><?= $totalPresent ?></h3>
                        <span style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 500;">Present</span>
                    </div>
                </div>
                <div class="glass-card" style="padding: 20px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #fed7d7; color: #742a2a; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-times"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 24px; font-weight: 700; margin: 0; color: #2d3748;"><?= $totalAbsent ?></h3>
                        <span style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 500;">Absent</span>
                    </div>
                </div>
                <div class="glass-card" style="padding: 20px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #bee3f8; color: #2c5282; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-umbrella-beach"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 24px; font-weight: 700; margin: 0; color: #2d3748;"><?= $totalLeave ?></h3>
                        <span style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 500;">Leave</span>
                    </div>
                </div>
                <div class="glass-card" style="padding: 20px; display: flex; align-items: center; gap: 15px;">
                    <div style="width: 50px; height: 50px; background: #fefcbf; color: #b7791f; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 24px;">
                        <i class="fas fa-gift"></i>
                    </div>
                    <div>
                        <h3 style="font-size: 24px; font-weight: 700; margin: 0; color: #2d3748;"><?= $totalHoliday ?></h3>
                        <span style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 500;">Holidays</span>
                    </div>
                </div>
            </div>

            <!-- Calendar -->
            <div class="calendar-wrapper">
                <div class="calendar-toolbar">
                    <form method="GET" style="display: flex; gap: 15px; flex-wrap: wrap; flex: 1;">
                        <div style="position: relative;">
                            <i class="far fa-calendar" style="position: absolute; left: 12px; top: 11px; color: #a0aec0;"></i>
                            <input type="month" name="month" value="<?= $filterMonth ?>" 
                                   style="padding: 8px 12px 8px 35px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit;">
                        </div>
                        <div style="position: relative;">
                             <i class="fas fa-user" style="position: absolute; left: 12px; top: 11px; color: #a0aec0;"></i>
                             <select name="employee_id" style="padding: 8px 12px 8px 35px; border-radius: 8px; border: 1px solid #e2e8f0; font-family: inherit; min-width: 200px;">
                                <option value="">All Employees</option>
                                <?php foreach($allEmployees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>" <?= $filterEmployee == $emp['employee_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                             </select>
                        </div>
                        <button type="submit" class="cal-nav-btn" style="background: #667eea; color: white; border: none;">Apply</button>
                    </form>

                    <div style="display: flex; gap: 10px;">
                        <?php
                            $prev = date('Y-m', strtotime($filterMonth . ' -1 month'));
                            $next = date('Y-m', strtotime($filterMonth . ' +1 month'));
                        ?>
                        <a href="?month=<?= $prev ?><?= $filterEmployee ? '&employee_id='.$filterEmployee : '' ?>" class="cal-nav-btn">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <span style="font-size: 18px; font-weight: 700; display: flex; align-items: center;"><?= $monthName ?></span>
                        <a href="?month=<?= $next ?><?= $filterEmployee ? '&employee_id='.$filterEmployee : '' ?>" class="cal-nav-btn">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                    </div>
                </div>

                <div class="calendar-grid">
                    <div class="cal-head">Sun</div>
                    <div class="cal-head">Mon</div>
                    <div class="cal-head">Tue</div>
                    <div class="cal-head">Wed</div>
                    <div class="cal-head">Thu</div>
                    <div class="cal-head">Fri</div>
                    <div class="cal-head">Sat</div>

                    <!-- Empty -->
                    <?php for($i=0; $i<$dayOfWeek; $i++): ?>
                        <div class="cal-day empty"></div>
                    <?php endfor; ?>

                    <!-- Days -->
                    <?php for($d=1; $d<=$daysInMonth; $d++): 
                        $currentDate = sprintf('%04d-%02d-%02d', $year, $month, $d);
                        $isToday = $currentDate === date('Y-m-d');
                        $dayData = $attendanceData[$currentDate] ?? [];
                        
                        // Check Holiday
                        $isHoliday = isset($holidays[$currentDate]);
                        $holidayInfo = $holidays[$currentDate] ?? null;

                        // Check Leave
                        $leaves = [];
                        foreach($leaveRequests as $lr) {
                            if($currentDate >= $lr['start_date'] && $currentDate <= $lr['end_date']) {
                                $leaves[] = $lr;
                            }
                        }

                        // Counts
                        $counts = ['present'=>0, 'absent'=>0, 'leave'=>0, 'holiday'=>0];
                        foreach($dayData as $rec) {
                            $st = strtolower($rec['status']);
                            if($filterEmployee) $counts[$st]++;
                            else $counts[$st] += ($rec['count'] ?? 1);
                        }
                    ?>
                        <div class="cal-day <?= $isToday ? 'today' : '' ?>">
                            <div class="day-num"><?= $d ?></div>
                            <div class="events-list">
                                <?php if($isHoliday): ?>
                                    <div class="badge-event badge-gov" title="<?= $holidayInfo['holiday_name'] ?>">
                                        <i class="fas fa-gift"></i> <?= substr($holidayInfo['holiday_name'], 0, 15) ?>
                                    </div>
                                <?php endif; ?>

                                <?php foreach($leaves as $l): ?>
                                    <div class="badge-event badge-leave" title="<?= $l['employee_name'] ?> (<?= $l['leave_type'] ?>)">
                                        <i class="fas fa-user-clock"></i> <?= $filterEmployee ? $l['leave_type'] : substr($l['employee_name'],0,10) ?>
                                    </div>
                                <?php endforeach; ?>

                                <!-- Attendance Badges -->
                                <?php if($counts['present'] > 0): ?>
                                    <div class="badge-event badge-present">
                                        <i class="fas fa-check"></i> Present: <?= $counts['present'] ?>
                                    </div>
                                <?php endif; ?>
                                <?php if($counts['absent'] > 0): ?>
                                    <div class="badge-event badge-absent">
                                        <i class="fas fa-times"></i> Absent: <?= $counts['absent'] ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
