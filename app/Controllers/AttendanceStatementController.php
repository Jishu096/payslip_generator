<?php
session_start();

// Security: Only Accountant role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../../public/auth/login.php");
    exit;
}

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../../public/accountant/generate_attendance_statement.php");
    exit;
}

$db = getDBConnection();

// Get form data
$monthYear = $_POST['month_year'] ?? '';
$employeeType = $_POST['employee_type'] ?? '';

if (empty($monthYear) || empty($employeeType)) {
    $_SESSION['error_message'] = 'Please select both Month/Year and Employee Category';
    header("Location: ../../public/accountant/generate_attendance_statement.php");
    exit;
}

list($month, $year) = explode('-', $monthYear);

// Validate month and year
if (!is_numeric($month) || !is_numeric($year) || $month < 1 || $month > 12) {
    $_SESSION['error_message'] = 'Invalid month or year selected';
    header("Location: ../../public/accountant/generate_attendance_statement.php");
    exit;
}

try {
    // Get employees of selected type
    $stmt = $db->prepare("
        SELECT 
            e.employee_id,
            e.full_name,
            e.designation
        FROM employees e
        WHERE e.employee_type = ?
        AND e.status = 'active'
        ORDER BY e.employee_id
    ");
    $stmt->execute([$employeeType]);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($employees)) {
        $_SESSION['error_message'] = "No active {$employeeType} employees found";
        header("Location: ../../public/accountant/generate_attendance_statement.php");
        exit;
    }

    // Get gazetted holidays for the month
    $stmt = $db->prepare("
        SELECT holiday_date
        FROM holidays
        WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?
    ");
    $stmt->execute([$month, $year]);
    $holidays = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'holiday_date');

    // Calculate working days
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $totalSaturdays = 0;
    $totalSundays = 0;
    
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dayOfWeek = date('N', strtotime("$year-$month-$day"));
        if ($dayOfWeek == 6) $totalSaturdays++;
        if ($dayOfWeek == 7) $totalSundays++;
    }

    $totalHolidays = count($holidays);
    $workingDays = $daysInMonth - ($totalSaturdays + $totalSundays + $totalHolidays);

    // Create Excel
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set page orientation to landscape
    $sheet->getPageSetup()->setOrientation(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::ORIENTATION_LANDSCAPE);
    $sheet->getPageSetup()->setPaperSize(\PhpOffice\PhpSpreadsheet\Worksheet\PageSetup::PAPERSIZE_A4);

    // Institute Header
    $sheet->mergeCells('A1:K1');
    $sheet->setCellValue('A1', 'National Institute of Electronics & Information Technology (NIELIT) Bhubaneswar');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
    $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Period Header
    $fromDate = date('d.m.Y', strtotime("$year-$month-01"));
    $toDate = date('d.m.Y', strtotime("$year-$month-$daysInMonth"));
    $employeeTypeText = strtoupper($employeeType);
    $sheet->mergeCells('A2:K2');
    $sheet->setCellValue('A2', "ATTENDANCE STATEMENT OF {$employeeTypeText} EMPLOYEES FOR THE PERIOD FROM $fromDate TO $toDate");
    $sheet->getStyle('A2')->getFont()->setBold(false)->setSize(11);
    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

    // Column Headers (Row 3 - Main headers, Row 4 - Sub headers)
    $sheet->mergeCells('A3:A4');
    $sheet->setCellValue('A3', 'S.No.');
    
    $sheet->mergeCells('B3:B4');
    $sheet->setCellValue('B3', 'Name & Designation');
    
    $sheet->mergeCells('C3:D3');
    $sheet->setCellValue('C3', 'Period of Absence/ OD & TOUR');
    $sheet->setCellValue('C4', 'From');
    $sheet->setCellValue('D4', 'To');
    
    $sheet->mergeCells('E3:E4');
    $sheet->setCellValue('E3', 'Nature Of Leave/OD/TOUR');
    
    $sheet->mergeCells('F3:F4');
    $sheet->setCellValue('F3', 'OD/TOUR');
    
    $sheet->mergeCells('G3:G4');
    $sheet->setCellValue('G3', 'EL/HPL/CCL/PL');
    
    $sheet->mergeCells('H3:H4');
    $sheet->setCellValue('H3', 'CL/RH');
    
    $sheet->mergeCells('I3:I4');
    $sheet->setCellValue('I3', 'EL/HPL/OD on Sat/Sun/GH');
    
    $sheet->mergeCells('J3:J4');
    $sheet->setCellValue('J3', 'Working days');
    
    $sheet->mergeCells('K3:K4');
    $sheet->setCellValue('K3', 'Net working Days');
    
    $sheet->mergeCells('L3:L4');
    $sheet->setCellValue('L3', 'Remarks');
    
    // Style headers
    $sheet->getStyle('A3:L4')->applyFromArray([
        'font' => ['bold' => true, 'size' => 10],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'FFFFFF']
        ],
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER,
            'wrapText' => true
        ],
        'borders' => [
            'allBorders' => [
                'borderStyle' => Border::BORDER_THIN,
                'color' => ['rgb' => '000000']
            ]
        ]
    ]);

    // Set column widths
    $sheet->getColumnDimension('A')->setWidth(6);
    $sheet->getColumnDimension('B')->setWidth(30);
    $sheet->getColumnDimension('C')->setWidth(12);
    $sheet->getColumnDimension('D')->setWidth(12);
    $sheet->getColumnDimension('E')->setWidth(20);
    $sheet->getColumnDimension('F')->setWidth(10);
    $sheet->getColumnDimension('G')->setWidth(10);
    $sheet->getColumnDimension('H')->setWidth(10);
    $sheet->getColumnDimension('I')->setWidth(12);
    $sheet->getColumnDimension('J')->setWidth(10);
    $sheet->getColumnDimension('K')->setWidth(12);
    $sheet->getColumnDimension('L')->setWidth(20);

    $sheet->getRowDimension(3)->setRowHeight(30);
    $sheet->getRowDimension(4)->setRowHeight(20);

    // Data rows
    $row = 5;
    $sno = 1;

    foreach ($employees as $emp) {
        // Get attendance data (only HR verified)
        $stmt = $db->prepare("
            SELECT 
                date,
                status,
                leave_type
            FROM attendance
            WHERE employee_id = ?
            AND MONTH(date) = ?
            AND YEAR(date) = ?
            AND verification_status = 'Verified'
            ORDER BY date
        ");
        $stmt->execute([$emp['employee_id'], $month, $year]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Group consecutive absence periods
        $absencePeriods = [];
        $leaveTypes = [];
        $currentPeriod = null;
        $currentLeaveType = null;

        foreach ($attendance as $att) {
            if ($att['status'] == 'leave' || $att['leave_type']) {
                $attDate = $att['date'];
                
                if ($currentPeriod === null) {
                    $currentPeriod = ['start' => $attDate, 'end' => $attDate];
                    $currentLeaveType = $att['leave_type'] ?? 'Leave';
                } else {
                    // Check if consecutive
                    $lastDate = strtotime($currentPeriod['end']);
                    $thisDate = strtotime($attDate);
                    $daysDiff = ($thisDate - $lastDate) / (60 * 60 * 24);
                    
                    if ($daysDiff <= 1 && $currentLeaveType == ($att['leave_type'] ?? 'Leave')) {
                        $currentPeriod['end'] = $attDate;
                    } else {
                        $absencePeriods[] = $currentPeriod;
                        $leaveTypes[] = $currentLeaveType;
                        $currentPeriod = ['start' => $attDate, 'end' => $attDate];
                        $currentLeaveType = $att['leave_type'] ?? 'Leave';
                    }
                }
            }
        }
        
        if ($currentPeriod !== null) {
            $absencePeriods[] = $currentPeriod;
            $leaveTypes[] = $currentLeaveType;
        }

        // Calculate leave statistics
        $odTour = 0;
        $el_hpl_ccl_pl = 0;
        $cl_rh = 0;
        $weekendHolidayLeaves = 0;

        foreach ($attendance as $att) {
            $attDate = $att['date'];
            $dayOfWeek = date('N', strtotime($attDate));
            $isWeekend = ($dayOfWeek == 6 || $dayOfWeek == 7);
            $isHoliday = in_array($attDate, $holidays);

            if ($att['leave_type']) {
                // Categorize leaves
                if (in_array($att['leave_type'], ['OD', 'TOUR'])) {
                    $odTour++;
                    if ($isWeekend || $isHoliday) {
                        $weekendHolidayLeaves++;
                    }
                } elseif (in_array($att['leave_type'], ['EL', 'HPL', 'CCL', 'PL'])) {
                    $el_hpl_ccl_pl++;
                    if ($isWeekend || $isHoliday) {
                        $weekendHolidayLeaves++;
                    }
                } elseif (in_array($att['leave_type'], ['CL', 'RH'])) {
                    $cl_rh++;
                }
            }
        }

        $netWorkingDays = $workingDays - ($el_hpl_ccl_pl + $cl_rh);

        // Write data - if multiple absence periods, create multiple rows
        $startRow = $row;
        $numPeriods = max(1, count($absencePeriods));
        
        for ($i = 0; $i < $numPeriods; $i++) {
            if ($i == 0) {
                // First row with employee details
                $sheet->setCellValue("A$row", $sno);
                $nameDesignation = $emp['full_name'] . "\n" . $emp['designation'];
                $sheet->setCellValue("B$row", $nameDesignation);
                $sheet->getStyle("B$row")->getAlignment()->setWrapText(true);
            } else {
                // Subsequent rows - empty S.No and Name columns
                $sheet->setCellValue("A$row", '');
                $sheet->setCellValue("B$row", '');
            }
            
            if (!empty($absencePeriods[$i])) {
                $fromDate = date('d.m.Y', strtotime($absencePeriods[$i]['start']));
                $toDate = date('d.m.Y', strtotime($absencePeriods[$i]['end']));
                if ($absencePeriods[$i]['start'] == $absencePeriods[$i]['end']) {
                    // Single day
                    $toDate = $fromDate;
                }
                $sheet->setCellValue("C$row", $fromDate);
                $sheet->setCellValue("D$row", $toDate);
                $sheet->setCellValue("E$row", $leaveTypes[$i] ?? '');
            } else {
                $sheet->setCellValue("C$row", '');
                $sheet->setCellValue("D$row", '');
                $sheet->setCellValue("E$row", '');
            }
            
            if ($i == 0) {
                // Only show totals in first row
                $sheet->setCellValue("F$row", $odTour > 0 ? $odTour : '');
                $sheet->setCellValue("G$row", $el_hpl_ccl_pl > 0 ? $el_hpl_ccl_pl : '');
                $sheet->setCellValue("H$row", $cl_rh > 0 ? $cl_rh : '');
                $sheet->setCellValue("I$row", $weekendHolidayLeaves > 0 ? $weekendHolidayLeaves : '');
                $sheet->setCellValue("J$row", $workingDays);
                $sheet->setCellValue("K$row", $netWorkingDays);
                $sheet->setCellValue("L$row", '');
            } else {
                $sheet->setCellValue("F$row", '');
                $sheet->setCellValue("G$row", '');
                $sheet->setCellValue("H$row", '');
                $sheet->setCellValue("I$row", '');
                $sheet->setCellValue("J$row", '');
                $sheet->setCellValue("K$row", '');
                $sheet->setCellValue("L$row", '');
            }

            // Style data row
            $sheet->getStyle("A$row:L$row")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical' => Alignment::VERTICAL_TOP
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => '000000']
                    ]
                ]
            ]);
            
            $sheet->getStyle("B$row")->applyFromArray([
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_LEFT,
                    'vertical' => Alignment::VERTICAL_TOP,
                    'wrapText' => true
                ]
            ]);
            $sheet->getRowDimension($row)->setRowHeight(35);
            
            $row++;
        }
        
        // Merge cells if multiple periods
        if ($numPeriods > 1) {
            $endRow = $row - 1;
            $sheet->mergeCells("A$startRow:A$endRow");
            $sheet->mergeCells("B$startRow:B$endRow");
            $sheet->mergeCells("F$startRow:F$endRow");
            $sheet->mergeCells("G$startRow:G$endRow");
            $sheet->mergeCells("H$startRow:H$endRow");
            $sheet->mergeCells("I$startRow:I$endRow");
            $sheet->mergeCells("J$startRow:J$endRow");
            $sheet->mergeCells("K$startRow:K$endRow");
            $sheet->mergeCells("L$startRow:L$endRow");
        }

        $sno++;
    }

    // Get officials from database
    $stmt = $db->query("
        SELECT official_name, position_title, display_order
        FROM attendance_officials
        WHERE is_active = 1
        ORDER BY display_order
    ");
    $officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Add signature blocks with proper spacing
    $row += 4;
    
    // Merge 3 columns for each signature
    $signatureRow = $row;
    if (isset($officials[0])) {
        $sheet->mergeCells("A$signatureRow:B$signatureRow");
        $sheet->setCellValue("A$signatureRow", $officials[0]['official_name']);
    }
    if (isset($officials[1])) {
        $sheet->mergeCells("D$signatureRow:E$signatureRow");
        $sheet->setCellValue("D$signatureRow", $officials[1]['official_name']);
    }
    if (isset($officials[2])) {
        $sheet->mergeCells("G$signatureRow:H$signatureRow");
        $sheet->setCellValue("G$signatureRow", $officials[2]['official_name']);
    }
    if (isset($officials[3])) {
        $sheet->mergeCells("J$signatureRow:K$signatureRow");
        $sheet->setCellValue("J$signatureRow", $officials[3]['official_name']);
    }
    
    // Position titles on next row
    $row++;
    if (isset($officials[0])) {
        $sheet->mergeCells("A$row:B$row");
        $sheet->setCellValue("A$row", $officials[0]['position_title']);
    }
    if (isset($officials[1])) {
        $sheet->mergeCells("D$row:E$row");
        $sheet->setCellValue("D$row", $officials[1]['position_title']);
    }
    if (isset($officials[2])) {
        $sheet->mergeCells("G$row:H$row");
        $sheet->setCellValue("G$row", $officials[2]['position_title']);
    }
    if (isset($officials[3])) {
        $sheet->mergeCells("J$row:K$row");
        $sheet->setCellValue("J$row", $officials[3]['position_title']);
    }
    
    // Style signature blocks
    $sheet->getStyle("A$signatureRow:K$row")->applyFromArray([
        'alignment' => [
            'horizontal' => Alignment::HORIZONTAL_CENTER,
            'vertical' => Alignment::VERTICAL_CENTER
        ],
        'font' => ['size' => 10]
    ]);

    // Generate filename
    $monthNames = ['', 'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'];
    $fileName = "ATTENDANCE_STATEMENT_{$employeeType}_{$monthNames[$month]}_{$year}.xlsx";

    // Log to audit
    $stmt = $db->prepare("
        INSERT INTO audit_logs (user_id, action, month, year, employee_type, file_name, details)
        VALUES (?, 'attendance_statement_generated', ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        $month,
        $year,
        $employeeType,
        $fileName,
        "Generated for " . count($employees) . " employees"
    ]);

    // Output file directly to browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="' . $fileName . '"');
    header('Cache-Control: max-age=0');
    header('Pragma: public');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    $_SESSION['error_message'] = 'Error generating Excel: ' . $e->getMessage();
    header("Location: ../../public/accountant/generate_attendance_statement.php");
    exit;
}
