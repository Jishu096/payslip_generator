<?php
// Start output buffering and clean any previous output
ob_start();
ob_clean();

// Enable error logging to file
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../storage/logs/export_errors.log');
error_reporting(E_ALL);
ini_set('display_errors', 0);

session_start();

// Set JSON header
header('Content-Type: application/json');

// Check if user has administrator role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['user_id']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    ob_clean();
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    require_once __DIR__ . '/../../../app/Config/database.php';
} catch (Exception $e) {
    ob_clean();
    error_log("Failed to load database config: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database configuration error']);
    exit;
}

// Load PHPSpreadsheet for Excel export
require_once __DIR__ . '/../../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['month']) || !isset($input['year']) || !isset($input['format'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Month, year, and format are required']);
    exit;
}

$month = $input['month'];
$year = $input['year'];
$format = $input['format']; // 'csv' or 'excel'

try {
    $db = getDBConnection();
    
    // Get finalized attendance data for the month
    $query = "SELECT 
        a.date,
        e.employee_code,
        e.full_name as employee_name,
        d.department_name,
        a.status,
        a.workflow_status,
        a.time_in,
        a.time_out,
        CASE 
            WHEN a.time_in IS NOT NULL AND a.time_out IS NOT NULL 
            THEN TIMESTAMPDIFF(HOUR, a.time_in, a.time_out)
            ELSE 0 
        END as hours_worked
    FROM attendance a
    JOIN employees e ON a.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    WHERE DATE_FORMAT(a.date, '%M') = ? 
    AND YEAR(a.date) = ?
    AND a.workflow_status = 'admin_finalized'
    ORDER BY a.date ASC, e.full_name ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$month, $year]);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($records)) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'No finalized attendance records found for this month'
        ]);
        exit;
    }
    
    // Create exports directory if not exists
    $exportDir = __DIR__ . '/../../storage/exports';
    if (!file_exists($exportDir)) {
        mkdir($exportDir, 0777, true);
    }
    
    // Ensure directory is writable
    if (!is_writable($exportDir)) {
        chmod($exportDir, 0777);
    }
    
    $filename = strtolower($month) . '_' . $year . '_attendance_export_' . time();
    
    if ($format === 'csv') {
        // Generate CSV
        $filepath = $exportDir . '/' . $filename . '.csv';
        $file = fopen($filepath, 'w');
        
        // Add BOM for Excel UTF-8 support
        fprintf($file, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header
        fputcsv($file, [
            'Date',
            'Employee Code',
            'Employee Name',
            'Department',
            'Status',
            'Workflow Status',
            'Check In',
            'Check Out',
            'Hours Worked'
        ]);
        
        // Data rows
        foreach ($records as $record) {
            fputcsv($file, [
                date('Y-m-d', strtotime($record['date'])),
                $record['employee_code'],
                $record['employee_name'],
                $record['department_name'] ?? 'N/A',
                ucfirst($record['status']),
                $record['workflow_status'],
                $record['time_in'] ?? '',
                $record['time_out'] ?? '',
                $record['hours_worked']
            ]);
        }
        
        fclose($file);
        
        // Log export to database
        $logStmt = $db->prepare("INSERT INTO attendance_export_log (month, year, exported_by, file_path, record_count, export_format) VALUES (?, ?, ?, ?, ?, ?)");
        $logStmt->execute([$month, $year, $_SESSION['user_id'], 'exports/' . $filename . '.csv', count($records), 'csv']);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'CSV exported successfully',
            'filename' => $filename . '.csv',
            'download_url' => '/payslip_generator/public/storage/exports/' . $filename . '.csv',
            'record_count' => count($records)
        ]);
        exit;
        
    } else {
        // Excel format - NIELIT Official Format
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $sheet->setTitle(substr($month . ' ' . $year, 0, 31));
        
        // Get date range
        $firstDate = date('d.m.Y', strtotime($records[0]['date']));
        $lastDate = date('d.m.Y', strtotime($records[count($records)-1]['date']));
        
        // HEADER - Organization Name
        $sheet->setCellValue('A1', 'National Institute of Electronics & Information Technology (NIELIT) Bhubaneswar');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        
        // TITLE - Period
        $sheet->setCellValue('A2', "ATTENDANCE STATEMENT OF PERMANENT EMPLOYEES FOR THE PERIOD FROM {$firstDate} TO {$lastDate}");
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 11],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER]
        ]);
        
        // Column Headers (Row 3-4 merged)
        $headers = [
            'A3' => 'S.No.',
            'B3' => 'Name & Designation',
            'C3' => 'Period of Absence OR TO UL',
            'E3' => 'Nature Of Leave/OD/TOUR',
            'F3' => 'OD/TOUR',
            'G3' => 'EL/HPL/OD on Sat/Sun/GH',
            'I3' => 'Working days',
            'J3' => 'Net working Days',
            'K3' => 'Remarks'
        ];
        
        foreach ($headers as $cell => $value) {
            $sheet->setCellValue($cell, $value);
        }
        
        // Sub-headers for Period (From/To)
        $sheet->setCellValue('C4', 'From');
        $sheet->setCellValue('D4', 'To');
        
        // Sub-headers for EL/HPL
        $sheet->setCellValue('G4', 'EL/HPL');
        $sheet->setCellValue('H4', 'CL/RH');
        
        // Merge headers
        $sheet->mergeCells('A3:A4');
        $sheet->mergeCells('B3:B4');
        $sheet->mergeCells('E3:E4');
        $sheet->mergeCells('F3:F4');
        $sheet->mergeCells('I3:I4');
        $sheet->mergeCells('J3:J4');
        $sheet->mergeCells('K3:K4');
        $sheet->mergeCells('C3:D3');
        $sheet->mergeCells('G3:H3');
        
        // Style headers
        $headerStyle = [
            'font' => ['bold' => true, 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E0E0E0']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]]
        ];
        $sheet->getStyle('A3:K4')->applyFromArray($headerStyle);
        
        // Group records by employee
        $employeeData = [];
        foreach ($records as $record) {
            $empCode = $record['employee_code'];
            if (!isset($employeeData[$empCode])) {
                $employeeData[$empCode] = [
                    'name' => $record['employee_name'],
                    'department' => $record['department_name'],
                    'leaves' => []
                ];
            }
            $employeeData[$empCode]['leaves'][] = $record;
        }
        
        // Data rows
        $row = 5;
        $sno = 1;
        
        foreach ($employeeData as $empCode => $emp) {
            $startRow = $row;
            
            // Employee name and designation (first row)
            $sheet->setCellValue('A' . $row, $sno);
            $sheet->setCellValue('B' . $row, $emp['name'] . "\n" . $emp['department']);
            
            // Calculate total working days for this employee
            $totalDays = count($emp['leaves']);
            $totalNetDays = 0;
            
            // Add each leave/attendance entry
            foreach ($emp['leaves'] as $idx => $leave) {
                if ($idx > 0) $row++; // New row for each leave entry except first
                
                $leaveDate = date('d.m.Y', strtotime($leave['date']));
                $sheet->setCellValue('C' . $row, $leaveDate);
                $sheet->setCellValue('D' . $row, $leaveDate);
                
                // Nature of leave
                $nature = $leave['status'];
                if ($nature === 'Present') {
                    $nature = '';
                    $totalNetDays++;
                } elseif ($nature === 'Absent') {
                    $nature = 'Absent';
                } elseif ($nature === 'Leave') {
                    $nature = 'Leave';
                } elseif ($nature === 'Holiday') {
                    $nature = 'Holiday';
                    $totalNetDays++;
                }
                $sheet->setCellValue('E' . $row, $nature);
                
                // OD/TOUR column
                $sheet->setCellValue('F' . $row, '');
                
                // EL/HPL columns
                $sheet->setCellValue('G' . $row, '');
                $sheet->setCellValue('H' . $row, '');
            }
            
            // Merge S.No and Name cells for all leave rows
            if ($row > $startRow) {
                $sheet->mergeCells('A' . $startRow . ':A' . $row);
                $sheet->mergeCells('B' . $startRow . ':B' . $row);
            }
            
            // Working days and Net working days (in last row of employee)
            $sheet->setCellValue('I' . $row, $totalDays);
            $sheet->setCellValue('J' . $row, $totalNetDays);
            $sheet->setCellValue('K' . $row, '');
            
            // Merge working days columns for all rows
            if ($row > $startRow) {
                $sheet->mergeCells('I' . $startRow . ':I' . $row);
                $sheet->mergeCells('J' . $startRow . ':J' . $row);
                $sheet->mergeCells('K' . $startRow . ':K' . $row);
            }
            
            // Apply borders to this employee's rows
            $sheet->getStyle('A' . $startRow . ':K' . $row)->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true]
            ]);
            
            $row++;
            $sno++;
        }
        
        // Add footer signatures (3 rows below data)
        $row += 3;
        $sheet->setCellValue('A' . $row, 'Sh.Suvranshu Mahapatra');
        $sheet->setCellValue('D' . $row, 'Smt Sukanya Patil');
        $sheet->setCellValue('G' . $row, 'Sh.Satikanta Dash');
        $sheet->setCellValue('J' . $row, 'Sh Anil Kumar Shaw');
        
        $row++;
        $sheet->setCellValue('A' . $row, 'Assistant Accounts');
        $sheet->setCellValue('D' . $row, 'Assistant Accounts');
        $sheet->setCellValue('G' . $row, 'Assistant Director (Admin)');
        $sheet->setCellValue('J' . $row, 'Director-In-Charge');
        
        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(12);
        $sheet->getColumnDimension('K')->setWidth(15);
        
        // Row heights
        $sheet->getRowDimension(1)->setRowHeight(25);
        $sheet->getRowDimension(2)->setRowHeight(20);
        $sheet->getRowDimension(3)->setRowHeight(30);
        
        // Save file
        $filepath = $exportDir . '/' . $filename . '.xlsx';
        $writer = new Xlsx($spreadsheet);
        $writer->save($filepath);
        
        // Log export to database
        $logStmt = $db->prepare("INSERT INTO attendance_export_log (month, year, exported_by, file_path, record_count, export_format) VALUES (?, ?, ?, ?, ?, ?)");
        $logStmt->execute([$month, $year, $_SESSION['user_id'], 'exports/' . $filename . '.xlsx', count($records), 'excel']);
        
        ob_clean();
        echo json_encode([
            'success' => true,
            'message' => 'Excel exported successfully in NIELIT format',
            'filename' => $filename . '.xlsx',
            'download_url' => '/payslip_generator/public/storage/exports/' . $filename . '.xlsx',
            'record_count' => count($records)
        ]);
        exit;
    }
    
} catch (Exception $e) {
    ob_clean();
    echo json_encode([
        'success' => false,
        'message' => 'Error exporting attendance: ' . $e->getMessage()
    ]);
    exit;
}
