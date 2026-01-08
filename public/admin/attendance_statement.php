<?php
session_start();

// Admin-only access
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Config/database.php";
require_once "../../app/Models/Employee.php";

$db = getDBConnection();

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
                mas.remarks
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
                mas.remarks
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
    <title>Attendance & Absentee Statement - NIELIT Bhubaneswar</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* No Print Elements */
        .no-print {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filter-section {
            display: flex;
            gap: 20px;
            align-items: end;
            flex-wrap: wrap;
        }

        .form-group {
            flex: 1;
            min-width: 150px;
        }

        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2d3748;
        }

        .form-group select,
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-success {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, #4299e1 0%, #3182ce 100%);
            color: white;
        }

        .report-tabs {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .tab-btn {
            padding: 10px 20px;
            background: #e2e8f0;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }

        .tab-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Print Section */
        .print-section {
            background: white;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        /* Government Register Header */
        .register-header {
            text-align: center;
            border-bottom: 3px double #000;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }

        .register-header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #000;
            margin-bottom: 5px;
        }

        .register-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .register-header .period {
            font-size: 16px;
            font-weight: 500;
            color: #4a5568;
            margin-top: 10px;
        }

        /* Government-style Table */
        .govt-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            font-size: 12px;
        }

        .govt-table th,
        .govt-table td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
            vertical-align: middle;
        }

        .govt-table thead th {
            background: #f0f0f0;
            font-weight: 600;
            color: #000;
        }

        .govt-table tbody td {
            color: #2d3748;
        }

        .govt-table .text-left {
            text-align: left;
        }

        .govt-table .serial-no {
            width: 50px;
        }

        .govt-table .name-designation {
            width: 200px;
            text-align: left;
        }

        .govt-table .period-col {
            width: 120px;
        }

        .govt-table .nature-col {
            width: 150px;
            text-align: left;
        }

        .govt-table .days-col {
            width: 50px;
        }

        .govt-table .remarks-col {
            width: 150px;
            text-align: left;
        }

        .govt-table .group-header {
            background: #e2e8f0;
            font-weight: 600;
            text-align: left;
            padding-left: 15px;
        }

        .designation-small {
            font-size: 11px;
            color: #718096;
            display: block;
            margin-top: 3px;
        }

        .empty-cell {
            color: #a0aec0;
        }

        /* Footer */
        .register-footer {
            margin-top: 40px;
            display: flex;
            justify-content: space-between;
            padding-top: 20px;
            border-top: 2px solid #000;
        }

        .signature-block {
            text-align: center;
        }

        .signature-line {
            width: 200px;
            border-bottom: 1px solid #000;
            margin: 60px auto 10px;
        }

        /* Print Styles */
        @media print {
            body {
                background: white;
                padding: 0;
            }

            .no-print {
                display: none !important;
            }

            .print-section {
                box-shadow: none;
                padding: 20px;
            }

            .govt-table {
                page-break-inside: auto;
            }

            .govt-table tr {
                page-break-inside: avoid;
                page-break-after: auto;
            }

            .govt-table thead {
                display: table-header-group;
            }

            @page {
                margin: 15mm;
                size: A4 landscape;
            }
        }

        /* Responsive */
        @media (max-width: 768px) {
            .filter-section {
                flex-direction: column;
            }

            .form-group {
                width: 100%;
            }

            .print-section {
                padding: 20px;
                overflow-x: auto;
            }

            .govt-table {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Filter Section (No Print) -->
        <div class="no-print">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="color: #2d3748;">
                    <i class="fas fa-file-alt"></i> Attendance & Absentee Statement
                </h2>
                <a href="admin_dashboard.php" class="btn btn-info">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <form method="GET" action="" class="filter-section">
                <div class="form-group">
                    <label><i class="far fa-calendar"></i> Month</label>
                    <select name="month" required>
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                            <option value="<?= $m ?>" <?= $selectedMonth == $m ? 'selected' : '' ?>>
                                <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><i class="far fa-calendar-alt"></i> Year</label>
                    <input type="number" name="year" min="2020" max="2030" 
                           value="<?= $selectedYear ?>" required>
                </div>

                <div class="form-group">
                    <label><i class="fas fa-users"></i> Report Type</label>
                    <select name="report_type" required>
                        <option value="regular" <?= $reportType === 'regular' ? 'selected' : '' ?>>
                            Regular Employees Attendance
                        </option>
                        <option value="contract" <?= $reportType === 'contract' ? 'selected' : '' ?>>
                            Contract Employees Absentee
                        </option>
                    </select>
                </div>

                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Generate Report
                </button>

                <button type="button" onclick="window.print()" class="btn btn-success">
                    <i class="fas fa-print"></i> Print
                </button>
            </form>
        </div>

        <!-- Print Section -->
        <div class="print-section">
            <!-- Register Header -->
            <div class="register-header">
                <h1>NATIONAL INSTITUTE OF ELECTRONICS & INFORMATION TECHNOLOGY</h1>
                <h2>NIELIT BHUBANESWAR</h2>
                <h2 style="margin-top: 15px;">
                    <?php if ($reportType === 'regular'): ?>
                        ATTENDANCE STATEMENT OF REGULAR EMPLOYEES
                    <?php else: ?>
                        ABSENTEE STATEMENT OF CONTRACT EMPLOYEES
                    <?php endif; ?>
                </h2>
                <div class="period">
                    For the month of <strong><?= $monthName ?> <?= $selectedYear ?></strong>
                </div>
            </div>

            <?php if ($reportType === 'regular'): ?>
                <!-- REGULAR EMPLOYEES ATTENDANCE STATEMENT -->
                <table class="govt-table">
                    <thead>
                        <tr>
                            <th rowspan="2" class="serial-no">S.No.</th>
                            <th rowspan="2" class="name-designation">Name & Designation</th>
                            <th rowspan="2" class="period-col">Period of Absence/<br>OD/Tour</th>
                            <th rowspan="2" class="nature-col">Nature of Leave/<br>OD/Tour</th>
                            <th colspan="5">Number of Days</th>
                            <th rowspan="2" class="days-col">Working<br>Days</th>
                            <th rowspan="2" class="days-col">Net Working<br>Days</th>
                            <th rowspan="2" class="remarks-col">Remarks</th>
                        </tr>
                        <tr>
                            <th class="days-col">OD/<br>Tour</th>
                            <th class="days-col">EL/CCL/<br>PL</th>
                            <th class="days-col">CL/RH</th>
                            <th class="days-col">Sat/Sun/<br>GH</th>
                            <th class="days-col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($regularEmployees)): ?>
                            <tr>
                                <td colspan="12" style="text-align: center; padding: 20px; color: #a0aec0;">
                                    No data available for <?= $monthName ?> <?= $selectedYear ?>
                                </td>
                            </tr>
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
                                
                                // Calculate totals
                                $odTour = $emp['od_days'] + $emp['tour_days'];
                                $elCclPl = $emp['el_days'] + $emp['ccl_days'] + $emp['pl_days'];
                                $clRh = $emp['cl_days'] + $emp['rh_days'];
                                $satSunGh = $emp['sat_days'] + $emp['sun_days'] + $emp['gh_days'];
                                $totalDays = $odTour + $elCclPl + $clRh + $satSunGh;
                            ?>
                                <tr>
                                    <td><?= $serialNo++ ?></td>
                                    <td class="text-left">
                                        <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                                        <span class="designation-small"><?= htmlspecialchars($emp['designation']) ?></span>
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
                                    <td class="text-left"><?= htmlspecialchars($emp['remarks'] ?: '---') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

            <?php else: ?>
                <!-- CONTRACT EMPLOYEES ABSENTEE STATEMENT -->
                <table class="govt-table">
                    <thead>
                        <tr>
                            <th class="serial-no">S.No.</th>
                            <th class="name-designation">Name & Designation</th>
                            <th class="period-col">Period of Leave<br>(From – To)</th>
                            <th class="nature-col">Nature of Leave/OD</th>
                            <th class="period-col">Period of Absence<br>(From – To)</th>
                            <th class="days-col">Absent<br>Days</th>
                            <th class="remarks-col">Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($contractEmployees)): ?>
                            <tr>
                                <td colspan="7" style="text-align: center; padding: 20px; color: #a0aec0;">
                                    No data available for <?= $monthName ?> <?= $selectedYear ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $serialNo = 1;
                            foreach ($groupedContractEmployees as $groupName => $employees): 
                            ?>
                                <!-- Group Header -->
                                <tr>
                                    <td colspan="7" class="group-header">
                                        <i class="fas fa-users"></i> <?= htmlspecialchars($groupName) ?>
                                    </td>
                                </tr>
                                
                                <?php foreach ($employees as $emp): 
                                    $leaveDetails = getLeaveDetails($db, $emp['employee_id'], $selectedMonth, $selectedYear);
                                    
                                    // Get leave and absence periods
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
                                    
                                    // Check if contract ended
                                    $remarks = $emp['remarks'] ?: '';
                                    if ($emp['contract_end_date'] && strtotime($emp['contract_end_date']) < strtotime("$selectedYear-$selectedMonth-$daysInMonth")) {
                                        $remarks = 'Contract ended on ' . date('d/m/Y', strtotime($emp['contract_end_date']));
                                    }
                                ?>
                                    <tr>
                                        <td><?= $serialNo++ ?></td>
                                        <td class="text-left">
                                            <strong><?= htmlspecialchars($emp['full_name']) ?></strong>
                                            <span class="designation-small"><?= htmlspecialchars($emp['designation']) ?></span>
                                        </td>
                                        <td><?= $leavePerStr ?></td>
                                        <td class="text-left"><?= $leaveNatStr ?></td>
                                        <td><?= $absenceStr ?></td>
                                        <td><strong><?= $emp['absent_days'] > 0 ? $emp['absent_days'] : '---' ?></strong></td>
                                        <td class="text-left"><?= htmlspecialchars($remarks ?: '---') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <!-- Footer with Signatures -->
            <div class="register-footer">
                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div><strong>Prepared By</strong></div>
                    <div style="font-size: 12px; color: #718096; margin-top: 5px;">HR Department</div>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div><strong>Checked By</strong></div>
                    <div style="font-size: 12px; color: #718096; margin-top: 5px;">HOD/Admin</div>
                </div>

                <div class="signature-block">
                    <div class="signature-line"></div>
                    <div><strong>Approved By</strong></div>
                    <div style="font-size: 12px; color: #718096; margin-top: 5px;">Director</div>
                </div>
            </div>

            <div style="margin-top: 30px; text-align: center; font-size: 11px; color: #718096;">
                Generated on: <?= date('d/m/Y h:i A') ?> | NIELIT Bhubaneswar Payroll System
            </div>
        </div>
    </div>
</body>
</html>
