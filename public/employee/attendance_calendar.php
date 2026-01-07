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
        case 'holiday':
            $totalHoliday++;
            break;
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
    <title>Company Attendance Calendar - e-HRMS</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .header h1 {
            color: #667eea;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-buttons {
            display: flex;
            gap: 10px;
        }

        .nav-buttons a {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: transform 0.2s;
        }

        .nav-buttons a:hover {
            transform: translateY(-2px);
        }

        .calendar-header {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .month-navigation {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .month-navigation h2 {
            color: #667eea;
            font-size: 28px;
        }

        .month-nav-buttons {
            display: flex;
            gap: 10px;
        }

        .month-nav-buttons a {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: transform 0.2s;
        }

        .month-nav-buttons a:hover:not(.disabled) {
            transform: translateY(-2px);
        }

        .month-nav-buttons a.disabled {
            opacity: 0.5;
            pointer-events: none;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .stat-card.present { background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); }
        .stat-card.absent { background: linear-gradient(135deg, #f56565 0%, #e53e3e 100%); }
        .stat-card.leave { background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%); }
        .stat-card.holiday { background: linear-gradient(135deg, #718096 0%, #4a5568 100%); }
        .stat-card.rate { background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%); }

        .stat-info h3 {
            font-size: 14px;
            font-weight: 400;
            margin-bottom: 5px;
        }

        .stat-info p {
            font-size: 28px;
            font-weight: 700;
        }

        .stat-icon {
            font-size: 40px;
            opacity: 0.5;
        }

        .calendar-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .calendar {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin-top: 20px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            position: relative;
        }

        .calendar-day.header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-weight: 600;
            border: none;
            aspect-ratio: auto;
            padding: 15px 10px;
        }

        .calendar-day.empty {
            border: none;
            background: transparent;
        }

        .calendar-day.today {
            background: linear-gradient(135deg, #fef5e7 0%, #fdebd0 100%);
            border-color: #f39c12;
            font-weight: 600;
        }

        .calendar-day .day-number {
            font-size: 18px;
            font-weight: 500;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .calendar-day.has-holiday {
            background: linear-gradient(135deg, #fef5e7 0%, #fdebd0 100%);
            border-color: #f39c12;
        }

        .holiday-badge {
            font-size: 9px;
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 3px 5px;
            border-radius: 4px;
            margin: 3px 0;
            display: flex;
            align-items: center;
            gap: 3px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .leave-badge {
            font-size: 9px;
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
            padding: 2px 4px;
            border-radius: 3px;
            margin: 2px 0;
            display: flex;
            align-items: center;
            gap: 3px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 100%;
        }

        .attendance-indicators {
            display: flex;
            flex-wrap: wrap;
            gap: 3px;
            margin-top: 5px;
            justify-content: center;
        }

        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .status-indicator.present { background: #48bb78; }
        .status-indicator.absent { background: #f56565; }
        .status-indicator.leave { background: #4299e1; }
        .status-indicator.holiday { background: #718096; }

        .calendar-day:hover:not(.header):not(.empty) {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
            transform: translateY(-2px);
        }

        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .legend-dot.present { background: #48bb78; }
        .legend-dot.absent { background: #f56565; }
        .legend-dot.leave { background: #4299e1; }
        .legend-dot.holiday { background: #718096; }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .month-navigation {
                flex-direction: column;
                gap: 15px;
            }

            .stats-row {
                grid-template-columns: 1fr;
            }

            .calendar {
                gap: 5px;
            }

            .calendar-day {
                padding: 5px;
            }

            .calendar-day .day-number {
                font-size: 14px;
            }

            .legend {
                flex-wrap: wrap;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>
                <i class="fas fa-calendar-alt"></i>
                Company Attendance Calendar
            </h1>
            <div class="nav-buttons">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <a href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
            </div>
        </div>

        <div class="calendar-header">
            <div class="month-navigation">
                <h2><?= htmlspecialchars($monthName) ?></h2>
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
</body>
</html>
