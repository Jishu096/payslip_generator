<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Employee';
$loggedInEmployeeId = $_SESSION['employee_id'] ?? null;

if (!$loggedInEmployeeId) {
    die("Error: No employee ID found in session.");
}

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/Attendance.php";

$employeeModel = new Employee();
$attendanceModel = new Attendance();

// Get employee details
$employee = $employeeModel->getEmployeeById($loggedInEmployeeId);

// Get filter month
$filterMonth = $_GET['month'] ?? date('Y-m');

// Calculate calendar data
$year = date('Y', strtotime($filterMonth . '-01'));
$month = date('m', strtotime($filterMonth . '-01'));
$firstDay = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth = date('t', $firstDay);
$dayOfWeek = date('w', $firstDay);
$monthName = date('F Y', $firstDay);

// Get attendance data for the month
$db = getDBConnection();

$sql = "SELECT 
            DATE(a.date) as attendance_date,
            a.status
        FROM attendance a
        WHERE a.employee_id = :employee_id
        AND YEAR(a.date) = :year
        AND MONTH(a.date) = :month";

$stmt = $db->prepare($sql);
$stmt->execute([
    ':employee_id' => $loggedInEmployeeId,
    ':year' => $year,
    ':month' => $month
]);

$attendanceData = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $date = $row['attendance_date'];
    $attendanceData[$date] = $row['status'];
}

// Calculate summary
$totalPresent = 0;
$totalAbsent = 0;
$totalLeave = 0;
$totalHoliday = 0;

foreach ($attendanceData as $date => $status) {
    switch (strtolower($status)) {
        case 'present':
            $totalPresent++;
            break;
        case 'absent':
            $totalAbsent++;
            break;
        case 'leave':
            $totalLeave++;
            break;
        case 'holiday':
            $totalHoliday++;
            break;
    }
}

// Calculate attendance percentage
$totalDays = count($attendanceData);
$attendanceRate = $totalDays > 0 ? round(($totalPresent / $totalDays) * 100, 2) : 0;

// Previous and next month navigation
$prevMonth = date('Y-m', strtotime($filterMonth . '-01 -1 month'));
$nextMonth = date('Y-m', strtotime($filterMonth . '-01 +1 month'));
$currentMonth = date('Y-m');
$canGoNext = $nextMonth <= $currentMonth; // Don't allow future months
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance Calendar - e-HRMS</title>
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
            max-width: 1200px;
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
            gap: 10px;
            margin-top: 20px;
        }

        .calendar-day {
            aspect-ratio: 1;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
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

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-top: 5px;
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
                My Attendance Calendar
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
                    <a href="?month=<?= $prevMonth ?>">
                        <i class="fas fa-chevron-left"></i> Previous
                    </a>
                    <a href="?month=<?= $currentMonth ?>" class="<?= $filterMonth === $currentMonth ? 'disabled' : '' ?>">
                        <i class="fas fa-calendar-day"></i> Current Month
                    </a>
                    <a href="?month=<?= $nextMonth ?>" class="<?= !$canGoNext ? 'disabled' : '' ?>">
                        Next <i class="fas fa-chevron-right"></i>
                    </a>
                </div>
            </div>

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
                <div class="stat-card rate">
                    <div class="stat-info">
                        <h3>Attendance Rate</h3>
                        <p><?= $attendanceRate ?>%</p>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-chart-line"></i>
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
                    $status = $attendanceData[$currentDate] ?? null;
                ?>
                    <div class="calendar-day <?= $isToday ? 'today' : '' ?>" title="<?= $status ? ucfirst($status) : 'No record' ?>">
                        <div class="day-number"><?= $day ?></div>
                        <?php if ($status): ?>
                            <div class="status-indicator <?= strtolower($status) ?>"></div>
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
            </div>
        </div>
    </div>
</body>
</html>
