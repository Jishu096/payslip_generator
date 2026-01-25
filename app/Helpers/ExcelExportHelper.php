<?php

namespace App\Helpers;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class ExcelExportHelper {

    /**
     * Generate Regular Employees Attendance Statement Excel
     */
    public static function generateRegularEmployeesExcel($regularEmployees, $db, $selectedMonth, $selectedYear, $monthName, $daysInMonth) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $spreadsheet->getProperties()->setTitle("Attendance Statement {$monthName} {$selectedYear}");
        
        // Merge cells for header
        $sheet->mergeCells('A1:L1');
        $sheet->mergeCells('A2:L2');
        
        // Header styling - matching the photo format
        $sheet->getCell('A1')->setValue('National Institute of Electronics & Information Technology (NIELIT) Bhubaneswar');
        $sheet->getStyle('A1')->getFont()->applyFromArray(['bold' => true, 'size' => 14, 'underline' => true]);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Format the period string
        $startDate = "01.{$selectedMonth}.{$selectedYear}";
        $endDate = date('d.m.Y', strtotime("$selectedYear-$selectedMonth-01 +1 month -1 day"));
        
        $sheet->getCell('A2')->setValue("ATTENDANCE STATEMENT OF REGULAR EMPLOYEES FOR THE PERIOD FROM {$startDate} TO {$endDate}");
        $sheet->getStyle('A2')->getFont()->applyFromArray(['bold' => false, 'size' => 11]);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(10);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(10);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(10);
        $sheet->getColumnDimension('L')->setWidth(20);
        
        // Header rows construction - matching photo layout
        // Row 4 (Top level headers)
        $sheet->setCellValue('A4', 'S.No.');
        $sheet->mergeCells('A4:A5');
        
        $sheet->setCellValue('B4', 'Name & Designation');
        $sheet->mergeCells('B4:B5');
        
        $sheet->setCellValue('C4', 'Period of Absence/ OD & TOUR');
        $sheet->mergeCells('C4:D4');
        
        $sheet->setCellValue('E4', 'Nature Of Leave/OD/TOUR');
        $sheet->mergeCells('E4:E5');
        
        $sheet->setCellValue('F4', 'OD/TOUR');
        $sheet->mergeCells('F4:F5');
        
        $sheet->setCellValue('G4', 'EL/HPL/CCL/PL');
        $sheet->mergeCells('G4:G5');
        
        $sheet->setCellValue('H4', 'CL/RH');
        $sheet->mergeCells('H4:H5');
        
        $sheet->setCellValue('I4', 'EL/HPL/OD on Sat/Sun/GH');
        $sheet->mergeCells('I4:I5');
        
        $sheet->setCellValue('J4', 'Working days');
        $sheet->mergeCells('J4:J5');
        
        $sheet->setCellValue('K4', 'Net working Days');
        $sheet->mergeCells('K4:K5');
        
        $sheet->setCellValue('L4', 'Remarks');
        $sheet->mergeCells('L4:L5');

        // Row 5 (Sub headers for Period of Absence)
        $sheet->setCellValue('C5', 'From');
        $sheet->setCellValue('D5', 'To');
        
        // Styling for headers
        $sheet->getStyle('A4:L5')->getFont()->applyFromArray(['bold' => true, 'size' => 10]);
        $sheet->getStyle('A4:L5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A4:L5')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A4:L5')->getAlignment()->setWrapText(true);
        
        // Apply borders to header
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A4:L5")->applyFromArray($borderStyle);
        
        // Data rows start after header
        $row = 6;
        $serialNo = 1;
        
        foreach ($regularEmployees as $emp) {
            $leaveDetails = self::getLeaveDetails($db, $emp['employee_id'], $selectedMonth, $selectedYear);
            
            $fromDates = [];
            $toDates = [];
            $natures = [];
            foreach ($leaveDetails as $leave) {
                $fromDates[] = $leave['start_date'];
                $toDates[] = $leave['end_date'];
                $natures[] = $leave['leave_type'] . ($leave['nature_of_leave'] ? ': ' . $leave['nature_of_leave'] : '');
            }
            $fromStr = !empty($fromDates) ? implode("\n", $fromDates) : '';
            $toStr = !empty($toDates) ? implode("\n", $toDates) : '';
            $natureStr = !empty($natures) ? implode('; ', $natures) : '';
            
            $odTour = $emp['od_days'] + $emp['tour_days'];
            $elCclPl = $emp['el_days'] + $emp['ccl_days'] + $emp['pl_days'];
            $clRh = $emp['cl_days'] + $emp['rh_days'];
            $satSunGh = $emp['sat_days'] + $emp['sun_days'] + $emp['gh_days'];
            
            // Auto-Remarks Logic
            $generatedRemarks = [];
            if ($emp['remarks']) $generatedRemarks[] = $emp['remarks'];
            
            $monthEndTs = strtotime("$selectedYear-$selectedMonth-01 +1 month -1 day");
            $monthStartTs = strtotime("$selectedYear-$selectedMonth-01");
            $todayTs = time();
            
            if (!empty($emp['resignation_date'])) {
                $resTs = strtotime($emp['resignation_date']);
                if ($resTs >= $monthStartTs && $resTs <= $monthEndTs && $resTs <= $todayTs) {
                    $generatedRemarks[] = "Resigned on " . date('d/m/Y', $resTs);
                }
            }
            if (!empty($emp['retirement_date'])) {
                $retTs = strtotime($emp['retirement_date']);
                if ($retTs >= $monthStartTs && $retTs <= $monthEndTs && $retTs <= $todayTs) {
                    $generatedRemarks[] = "Retired on " . date('d/m/Y', $retTs);
                }
            }
            
            $finalRemarks = implode('; ', $generatedRemarks);
            
            // Add row data - empty cells instead of '---'
            $sheet->getCell('A' . $row)->setValue($serialNo++);
            $sheet->getCell('B' . $row)->setValue($emp['full_name'] . ' (' . $emp['designation'] . ')');
            $sheet->getCell('C' . $row)->setValue($fromStr);
            $sheet->getCell('D' . $row)->setValue($toStr);
            $sheet->getCell('E' . $row)->setValue($natureStr);
            $sheet->getCell('F' . $row)->setValue($odTour > 0 ? $odTour : '');
            $sheet->getCell('G' . $row)->setValue($elCclPl > 0 ? $elCclPl : '');
            $sheet->getCell('H' . $row)->setValue($clRh > 0 ? $clRh : '');
            $sheet->getCell('I' . $row)->setValue($satSunGh > 0 ? $satSunGh : '');
            $sheet->getCell('J' . $row)->setValue($emp['working_days'] ?: $daysInMonth);
            $sheet->getCell('K' . $row)->setValue($emp['net_working_days'] ?: $daysInMonth);
            $sheet->getCell('L' . $row)->setValue($finalRemarks);
            
            // Apply borders and alignment
            $sheet->getStyle("A$row:L$row")->applyFromArray($borderStyle);
            $sheet->getStyle("A$row:L$row")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A$row:L$row")->getAlignment()->setWrapText(true);
            
            $row++;
        }
        
        // Add footer note
        $row++; // Skip a row
        $sheet->setCellValue("A$row", "The report is according to attendance register.");
        $row++;
        $sheet->setCellValue("A$row", "1. Absentee Report From {$startDate} to {$endDate}");
        
        // Add signature section
        $row += 5;
        $sheet->setCellValue("A$row", "Sh Suvranshu Mahapatra");
        $sheet->setCellValue("C$row", "Smt Sukanya Palli");
        $sheet->setCellValue("F$row", "Sh Satikanta Dash");
        $sheet->setCellValue("J$row", "Sh Anil Kumar Shaw");
        $sheet->getStyle("A$row:J$row")->getFont()->setBold(true);
        
        $row++;
        $sheet->setCellValue("A$row", "Assistant Accounts");
        $sheet->setCellValue("C$row", "Assistant Accounts");
        $sheet->setCellValue("F$row", "Assistant Director (Admin)");
        $sheet->setCellValue("J$row", "Director-In-Charge");
        $sheet->getStyle("A$row:J$row")->getFont()->setBold(true);
        
        // Set row height for header
        $sheet->getRowDimension(4)->setRowHeight(40);
        
        return $spreadsheet;
    }

    /**
     * Generate Contract Employees Absentee Statement Excel
     */
    public static function generateContractEmployeesExcel($groupedContractEmployees, $db, $selectedMonth, $selectedYear, $monthName) {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set title
        $spreadsheet->getProperties()->setTitle("Absentee Statement {$monthName} {$selectedYear}");
        
        // Merge cells for header
        $sheet->mergeCells('A1:I1');
        $sheet->mergeCells('A2:I2');
        $sheet->mergeCells('A3:I3');
        $sheet->mergeCells('A4:I4');
        
        // Header styling
        $sheet->getCell('A1')->setValue('NATIONAL INSTITUTE OF ELECTRONICS & INFORMATION TECHNOLOGY');
        $sheet->getStyle('A1')->getFont()->applyFromArray(['bold' => true, 'size' => 12]);
        
        $sheet->getCell('A2')->setValue('NIELIT BHUBANESWAR');
        $sheet->getStyle('A2')->getFont()->applyFromArray(['bold' => true, 'size' => 11]);
        
        $sheet->getCell('A3')->setValue('ABSENTEE STATEMENT OF CONTRACT EMPLOYEES');
        $sheet->getStyle('A3')->getFont()->applyFromArray(['bold' => true, 'size' => 11]);
        
        $sheet->getCell('A4')->setValue("For the month of {$monthName} {$selectedYear}");
        $sheet->getStyle('A4')->getFont()->applyFromArray(['size' => 10]);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(12);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(12);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(8);
        $sheet->getColumnDimension('I')->setWidth(20);
        
        // Header row
        $row = 6;
        $sheet->setCellValue('A6', 'S.No.');
        $sheet->mergeCells('A6:A7');
        
        $sheet->setCellValue('B6', 'Name & Designation');
        $sheet->mergeCells('B6:B7');
        
        $sheet->setCellValue('C6', 'Period of Leave');
        $sheet->mergeCells('C6:D6');
        
        $sheet->setCellValue('E6', 'Nature of Leave/OD');
        $sheet->mergeCells('E6:E7');
        
        $sheet->setCellValue('F6', 'Period of Absence');
        $sheet->mergeCells('F6:G6');
        
        $sheet->setCellValue('H6', 'Absent Days');
        $sheet->mergeCells('H6:H7');
        
        $sheet->setCellValue('I6', 'Remarks');
        $sheet->mergeCells('I6:I7');
        
        // Sub-headers
        $sheet->setCellValue('C7', 'From');
        $sheet->setCellValue('D7', 'To');
        $sheet->setCellValue('F7', 'From');
        $sheet->setCellValue('G7', 'To');
        
        // Styling
        $sheet->getStyle('A6:I7')->getFont()->applyFromArray(['bold' => true, 'size' => 10]);
        $sheet->getStyle('A6:I7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A6:I7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A6:I7')->getAlignment()->setWrapText(true);
        
        // Apply borders to header
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A6:I7")->applyFromArray($borderStyle);
        
        // Data rows
        $row = 7;
        $serialNo = 1;
        
        foreach ($groupedContractEmployees as $groupName => $employees) {
            // Group header
            $sheet->mergeCells("A$row:G$row");
            $cell = $sheet->getCell("A$row");
            $cell->setValue($groupName);
            $groupStyle = $sheet->getStyle("A$row");
            $groupStyle->getFont()->applyFromArray(['bold' => true, 'size' => 10]);
            $groupStyle->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
            $sheet->getStyle("A$row:G$row")->applyFromArray([
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'EEEEEE']],
            ])->applyFromArray($borderStyle);
            $row++;
            
            foreach ($employees as $emp) {
                $leaveDetails = self::getLeaveDetails($db, $emp['employee_id'], $selectedMonth, $selectedYear);
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
                
                $leavePerStr = !empty($leavePeriods) ? implode(', ', $leavePeriods) : '';
                $leaveNatStr = !empty($leaveNatures) ? implode('; ', $leaveNatures) : '';
                $absenceStr = !empty($absencePeriods) ? implode(', ', $absencePeriods) : '';
                
                // Auto-Remarks for Contract
                $finalRemarks = $emp['remarks'] ?: '';
                $monthEndTs = strtotime("$selectedYear-$selectedMonth-01 +1 month -1 day");
                $monthStartTs = strtotime("$selectedYear-$selectedMonth-01");
                $todayTs = time();
                
                $extras = [];
                if ($emp['contract_end_date']) {
                    $contractTs = strtotime($emp['contract_end_date']);
                    if ($contractTs >= $monthStartTs && $contractTs <= $monthEndTs && $contractTs <= $todayTs) {
                        $extras[] = 'Contract ended on ' . date('d/m/Y', $contractTs);
                    }
                }
                if ($emp['internship_duration']) {
                    $extras[] = $emp['internship_duration'] . " Months Internship";
                }
                
                if (!empty($extras)) {
                    $finalRemarks = ($finalRemarks ? $finalRemarks . '; ' : '') . implode('; ', $extras);
                }
                
                // Add row data
                $sheet->getCell('A' . $row)->setValue($serialNo++);
                $sheet->getCell('B' . $row)->setValue($emp['full_name'] . ' (' . $emp['designation'] . ')');
                $sheet->getCell('C' . $row)->setValue($leaveFromStr);
                $sheet->getCell('D' . $row)->setValue($leaveToStr);
                $sheet->getCell('E' . $row)->setValue($leaveNatStr);
                $sheet->getCell('F' . $row)->setValue($absFromStr);
                $sheet->getCell('G' . $row)->setValue($absToStr);
                $sheet->getCell('H' . $row)->setValue($emp['absent_days'] > 0 ? $emp['absent_days'] : '');
                $sheet->getCell('I' . $row)->setValue($finalRemarks);
                
                // Apply borders and alignment
                $sheet->getStyle("A$row:I$row")->applyFromArray($borderStyle);
                $sheet->getStyle("A$row:I$row")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("A$row:I$row")->getAlignment()->setWrapText(true);
                
                $row++;
            }
        }
        
        // Set row height for header
        $sheet->getRowDimension(6)->setRowHeight(40);
        
        return $spreadsheet;
    }

    /**
     * Fetch leave details for a specific employee
     */
    private static function getLeaveDetails($db, $employeeId, $month, $year) {
        $sql = "SELECT 
                    leave_type,
                    DATE_FORMAT(start_date, '%d.%m.%Y') as start_date,
                    DATE_FORMAT(end_date, '%d.%m.%Y') as end_date,
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
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}