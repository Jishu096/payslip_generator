<?php
session_start();

// Admin-only access
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Config/database.php";
require_once "../../app/Models/Employee.php";
require_once "../../app/Helpers/AttendanceStatementHelper.php";

$db = getDBConnection();

// Handle Generate Summary Action
$generateMessage = '';
$generateError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_summary'])) {
    $genMonth = $_POST['gen_month'] ?? date('n');
    $genYear = $_POST['gen_year'] ?? date('Y');
    
    try {
        $helper = new AttendanceStatementHelper($db);
        $result = $helper->processMonthlyAttendance($genMonth, $genYear);
        
        if ($result['success']) {
            $generateMessage = "Successfully processed {$result['processed']} employees for " . date('F', mktime(0, 0, 0, $genMonth, 1)) . " $genYear";
            if (!empty($result['errors'])) {
                $generateMessage .= ". Errors: " . implode('; ', $result['errors']);
            }
        }
    } catch (Exception $e) {
        $generateError = "Failed to generate summary: " . $e->getMessage();
    }
}

// Get filter parameters
$selectedMonth = $_GET['month'] ?? date('n');
$selectedYear = $_GET['year'] ?? date('Y');
$reportType = $_GET['report_type'] ?? 'regular'; // 'regular' or 'contract'

// Calculate days in month
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
$monthName = date('F', mktime(0, 0, 0, $selectedMonth, 1));

// Fetch Regular Employees Attendance Statement
function getRegularEmployeesStatement($db, $month, $year) {
    $sql = "SELECT 
                e.employee_id,
                e.full_name,
                e.designation,
                e.location,
                COALESCE(mas.od_days, 0) as od_days,
                COALESCE(mas.tour_days, 0) as tour_days,
                COALESCE(mas.el_days, 0) as el_days,
                COALESCE(mas.ccl_days, 0) as ccl_days,
                COALESCE(mas.pl_days, 0) as pl_days,
                COALESCE(mas.cl_days, 0) as cl_days,
                COALESCE(mas.rh_days, 0) as rh_days,
                COALESCE(mas.sat_days, 0) as sat_days,
                COALESCE(mas.sun_days, 0) as sun_days,
                COALESCE(mas.gh_days, 0) as gh_days,
                COALESCE(mas.working_days, 0) as working_days,
                COALESCE(mas.net_working_days, 0) as net_working_days,
                (SELECT GROUP_CONCAT(DISTINCT remarks SEPARATOR '; ')
                 FROM attendance_leave_details ald
                 WHERE ald.employee_id = e.employee_id
                 AND YEAR(ald.start_date) = $year
                 AND MONTH(ald.start_date) = $month
                 AND ald.remarks IS NOT NULL
                 AND ald.remarks != '') as remarks
            FROM employees e
            LEFT JOIN monthly_attendance_summary mas 
                ON e.employee_id = mas.employee_id 
                AND mas.month = :month 
                AND mas.year = :year
            WHERE e.employee_type = 'regular' 
            AND e.status = 'active'
            ORDER BY e.full_name";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([':month' => $month, ':year' => $year]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch Contract Employees Absentee Statement
function getContractEmployeesStatement($db, $month, $year) {
    $sql = "SELECT 
                e.employee_id,
                e.full_name,
                e.designation,
                e.employee_group,
                e.location,
                e.contract_end_date,
                COALESCE(mas.total_days, 0) as total_days,
                COALESCE(mas.absent_days, 0) as absent_days,
                COALESCE(mas.payable_days, 0) as payable_days,
                (SELECT GROUP_CONCAT(DISTINCT remarks SEPARATOR '; ')
                 FROM attendance_leave_details ald
                 WHERE ald.employee_id = e.employee_id
                 AND YEAR(ald.start_date) = $year
                 AND MONTH(ald.start_date) = $month
                 AND ald.remarks IS NOT NULL
                 AND ald.remarks != '') as remarks
            FROM employees e
            LEFT JOIN monthly_attendance_summary mas 
                ON e.employee_id = mas.employee_id 
                AND mas.month = :month 
                AND mas.year = :year
            WHERE e.employee_type IN ('contract', 'project', 'daily_wage')
            AND (e.status = 'active' OR 
                 (e.status = 'inactive' AND e.contract_end_date >= :start_date))
            ORDER BY e.employee_group, e.location, e.full_name";
    
    $startDate = "$year-$month-01";
    $stmt = $db->prepare($sql);
    $stmt->execute([':month' => $month, ':year' => $year, ':start_date' => $startDate]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch leave details for a specific employee
function getLeaveDetails($db, $employeeId, $month, $year) {
    $sql = "SELECT 
                leave_type,
                DATE_FORMAT(start_date, '%d/%m') as start_date,
                DATE_FORMAT(end_date, '%d/%m') as end_date,
                total_days,
                nature_of_leave,
                CONCAT(
                    DATE_FORMAT(start_date, '%d/%m'),
                    IF(start_date != end_date, 
                        CONCAT(' to ', DATE_FORMAT(end_date, '%d/%m')), 
                        '')
                ) as period
            FROM attendance_leave_details
            WHERE employee_id = :employee_id
            AND YEAR(start_date) = :year
            AND MONTH(start_date) = :month
            ORDER BY start_date";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':employee_id' => $employeeId,
        ':month' => $month,
        ':year' => $year
    ]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch data based on report type
$regularEmployees = ($reportType === 'regular') ? getRegularEmployeesStatement($db, $selectedMonth, $selectedYear) : [];
$contractEmployees = ($reportType === 'contract') ? getContractEmployeesStatement($db, $selectedMonth, $selectedYear) : [];

// Group contract employees
$groupedContractEmployees = [];
if (!empty($contractEmployees)) {
    foreach ($contractEmployees as $emp) {
        $group = $emp['employee_group'] ?? 'Others';
        $location = $emp['location'] ?? 'NIELIT Bhubaneswar';
        $key = "$location - $group";
        if (!isset($groupedContractEmployees[$key])) {
            $groupedContractEmployees[$key] = [];
        }
        $groupedContractEmployees[$key][] = $emp;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Statement - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-container {
            max-width: 100%; /* Use full width for big tables */
        }
        
        .filter-section {
            display: flex;
            align-items: flex-end;
            gap: 15px;
            margin-bottom: 30px;
        }

        .filter-group { flex: 1; }
        .filter-group label { display: block; margin-bottom: 5px; font-size: 13px; color: #4a5568; font-weight: 500; }
        .filter-control { width: 100%; padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0; font-size: 14px; }
        
        /* Govt Table Styles */
        .govt-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            font-family: 'Times New Roman', serif;
        }
        
        .govt-table th, .govt-table td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            vertical-align: middle;
        }
        
        .govt-table thead th {
            background: #f0f0f0;
            font-weight: bold;
        }

        .text-left { text-align: left !important; }
        
        .print-preview-container {
            background: white;
            padding: 40px;
            margin-top: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            overflow-x: auto;
        }

        .register-header { text-align: center; margin-bottom: 20px; border-bottom: 3px double #000; padding-bottom: 10px; }
        .register-header h1 { font-size: 18px; margin-bottom: 5px; color: black; }
        .register-header h2 { font-size: 14px; margin-bottom: 5px; color: black; font-weight: normal; }
        
        .signature-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #000;
        }
        .sig-block { text-align: center; width: 200px; }
        .sig-line { border-bottom: 1px solid #000; margin-bottom: 5px; height: 30px; }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1>Attendance Statement</h1>
                <p>Generate formal monthly attendance and absentee statements.</p>
            </div>

            <?php if ($generateMessage): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($generateMessage) ?>
                </div>
            <?php endif; ?>
            <?php if ($generateError): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($generateError) ?>
                </div>
            <?php endif; ?>

            <!-- Filter Card -->
            <div class="glass-card no-print" style="padding: 25px; margin-bottom: 20px;">
                <form method="GET" class="filter-section">
                    <div class="filter-group">
                        <label>Month</label>
                        <select name="month" class="filter-control">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $selectedMonth == $m ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Year</label>
                        <input type="number" name="year" value="<?= $selectedYear ?>" class="filter-control">
                    </div>
                    <div class="filter-group">
                        <label>Report Type</label>
                        <select name="report_type" class="filter-control">
                            <option value="regular" <?= $reportType === 'regular' ? 'selected' : '' ?>>Regular Employees</option>
                            <option value="contract" <?= $reportType === 'contract' ? 'selected' : '' ?>>Contract Employees</option>
                        </select>
                    </div>
                    <div style="padding-bottom: 2px;">
                        <button type="submit" class="btn">
                            <i class="fas fa-filter"></i> Generate
                        </button>
                    </div>
                    <div style="padding-bottom: 2px;">
                        <button type="button" onclick="window.print()" class="btn" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);">
                            <i class="fas fa-print"></i> Print Statement
                        </button>
                    </div>
                     <div style="padding-bottom: 2px;">
                        <a href="add_attendance_record.php" class="btn" style="background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);">
                            <i class="fas fa-plus"></i> Add Manual Record
                        </a>
                    </div>
                </form>
                
                <!-- Generate Summary Form -->
                <form method="POST" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                    <input type="hidden" name="gen_month" value="<?= $selectedMonth ?>">
                    <input type="hidden" name="gen_year" value="<?= $selectedYear ?>">
                    <button type="submit" name="generate_summary" class="btn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 100%;">
                        <i class="fas fa-sync-alt"></i> Generate Summary for <?= date('F', mktime(0, 0, 0, $selectedMonth, 1)) ?> <?= $selectedYear ?>
                    </button>
                    <p style="font-size: 12px; color: #718096; margin-top: 8px; text-align: center;">
                        <i class="fas fa-info-circle"></i> Click this to calculate and populate attendance data from daily records
                    </p>
                </form>
            </div>

            <!-- Print Preview Area -->
            <div class="print-preview-container">
                <div class="register-header">
                    <h1>NATIONAL INSTITUTE OF ELECTRONICS & INFORMATION TECHNOLOGY</h1>
                    <h2>NIELIT BHUBANESWAR</h2>
                    <h2 style="font-weight: bold; margin-top: 10px;">
                        <?php if ($reportType === 'regular'): ?>
                            ATTENDANCE STATEMENT OF REGULAR EMPLOYEES
                        <?php else: ?>
                            ABSENTEE STATEMENT OF CONTRACT EMPLOYEES
                        <?php endif; ?>
                    </h2>
                    <div style="margin-top: 5px; font-size: 12px;">
                        For the month of <strong><?= $monthName ?> <?= $selectedYear ?></strong>
                    </div>
                </div>

                <?php if ($reportType === 'regular'): ?>
                    <table class="govt-table">
                        <thead>
                            <tr>
                                <th rowspan="2">S.No.</th>
                                <th rowspan="2">Name & Designation</th>
                                <th rowspan="2">Period of Absence/<br>OD/Tour</th>
                                <th rowspan="2">Nature of Leave/<br>OD/Tour</th>
                                <th colspan="5">Number of Days</th>
                                <th rowspan="2">Working<br>Days</th>
                                <th rowspan="2">Net Working<br>Days</th>
                                <th rowspan="2">Remarks</th>
                            </tr>
                            <tr>
                                <th>OD/<br>Tour</th>
                                <th>EL/CCL/<br>PL</th>
                                <th>CL/RH</th>
                                <th>Sat/Sun/<br>GH</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($regularEmployees)): ?>
                                <tr><td colspan="12" style="padding: 20px;">No data available</td></tr>
                            <?php else: ?>
                                <?php 
                                $serialNo = 1;
                                foreach ($regularEmployees as $emp): 
                                    $leaveDetails = getLeaveDetails($db, $emp['employee_id'], $selectedMonth, $selectedYear);
                                    
                                    // Combine periods and natures
                                    $periods = [];
                                    $natures = [];
                                    foreach ($leaveDetails as $leave) {
                                        $periods[] = $leave['period'];
                                        $natures[] = $leave['leave_type'] . ($leave['nature_of_leave'] ? ': ' . $leave['nature_of_leave'] : '');
                                    }
                                    $periodStr = !empty($periods) ? implode(', ', $periods) : '---';
                                    $natureStr = !empty($natures) ? implode('; ', $natures) : '---';
                                    
                                    $odTour = $emp['od_days'] + $emp['tour_days'];
                                    $elCclPl = $emp['el_days'] + $emp['ccl_days'] + $emp['pl_days'];
                                    $clRh = $emp['cl_days'] + $emp['rh_days'];
                                    $satSunGh = $emp['sat_days'] + $emp['sun_days'] + $emp['gh_days'];
                                    $totalDays = $odTour + $elCclPl + $clRh + $satSunGh;
                                ?>
                                    <tr>
                                        <td><?= $serialNo++ ?></td>
                                        <td class="text-left">
                                            <strong><?= htmlspecialchars($emp['full_name']) ?></strong><br>
                                            <span style="font-size: 9px;"><?= htmlspecialchars($emp['designation']) ?></span>
                                        </td>
                                        <td><?= $periodStr ?></td>
                                        <td class="text-left"><?= $natureStr ?></td>
                                        <td><?= $odTour > 0 ? $odTour : '---' ?></td>
                                        <td><?= $elCclPl > 0 ? $elCclPl : '---' ?></td>
                                        <td><?= $clRh > 0 ? $clRh : '---' ?></td>
                                        <td><?= $satSunGh > 0 ? $satSunGh : '---' ?></td>
                                        <td><?= $totalDays > 0 ? $totalDays : '---' ?></td>
                                        <td><?= $emp['working_days'] ?: $daysInMonth ?></td>
                                        <td><strong><?= $emp['net_working_days'] ?: $daysInMonth ?></strong></td>
                                        <td class="text-left"><?= htmlspecialchars($emp['remarks'] ?: '') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                <?php else: ?>
                    <table class="govt-table">
                        <thead>
                            <tr>
                                <th>S.No.</th>
                                <th>Name & Designation</th>
                                <th>Period of Leave<br>(From – To)</th>
                                <th>Nature of Leave/OD</th>
                                <th>Period of Absence<br>(From – To)</th>
                                <th>Absent<br>Days</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($groupedContractEmployees)): ?>
                                <tr><td colspan="7" style="padding: 20px;">No data available</td></tr>
                            <?php else: ?>
                                <?php 
                                $serialNo = 1;
                                foreach ($groupedContractEmployees as $groupName => $employees): 
                                ?>
                                    <tr>
                                        <td colspan="7" class="text-left" style="background: #eee; font-weight: bold;">
                                            <?= htmlspecialchars($groupName) ?>
                                        </td>
                                    </tr>
                                    <?php foreach ($employees as $emp): 
                                        $leaveDetails = getLeaveDetails($db, $emp['employee_id'], $selectedMonth, $selectedYear);
                                        $leavePeriods = [];
                                        $leaveNatures = [];
                                        $absencePeriods = [];
                                        foreach ($leaveDetails as $leave) {
                                            if ($leave['leave_type'] === 'Absent') {
                                                $absencePeriods[] = $leave['period'];
                                            } else {
                                                $leavePeriods[] = $leave['period'];
                                                $leaveNatures[] = $leave['leave_type'] . ($leave['nature_of_leave'] ? ': ' . $leave['nature_of_leave'] : '');
                                            }
                                        }
                                        $leavePerStr = !empty($leavePeriods) ? implode(', ', $leavePeriods) : '---';
                                        $leaveNatStr = !empty($leaveNatures) ? implode('; ', $leaveNatures) : '---';
                                        $absenceStr = !empty($absencePeriods) ? implode(', ', $absencePeriods) : '---';
                                        
                                        $remarks = $emp['remarks'] ?: '';
                                        if ($emp['contract_end_date'] && strtotime($emp['contract_end_date']) < strtotime("$selectedYear-$selectedMonth-$daysInMonth")) {
                                            $remarks = 'Contract ended on ' . date('d/m/Y', strtotime($emp['contract_end_date']));
                                        }
                                    ?>
                                        <tr>
                                            <td><?= $serialNo++ ?></td>
                                            <td class="text-left">
                                                <strong><?= htmlspecialchars($emp['full_name']) ?></strong><br>
                                                <span style="font-size: 9px;"><?= htmlspecialchars($emp['designation']) ?></span>
                                            </td>
                                            <td><?= $leavePerStr ?></td>
                                            <td class="text-left"><?= $leaveNatStr ?></td>
                                            <td><?= $absenceStr ?></td>
                                            <td><strong><?= $emp['absent_days'] > 0 ? $emp['absent_days'] : '---' ?></strong></td>
                                            <td class="text-left"><?= htmlspecialchars($remarks) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <div class="signature-footer">
                    <div class="sig-block">
                        <div class="sig-line"></div>
                        <strong>Prepared By</strong><br>HR Department
                    </div>
                    <div class="sig-block">
                        <div class="sig-line"></div>
                        <strong>Checked By</strong><br>HOD/Admin
                    </div>
                    <div class="sig-block">
                        <div class="sig-line"></div>
                        <strong>Approved By</strong><br>Director
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
