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
        $sheet->mergeCells('A1:M1');
        $sheet->mergeCells('A2:M2');
        $sheet->mergeCells('A3:M3');
        $sheet->mergeCells('A4:M4');
        
        // Header styling
        $sheet->getCell('A1')->setValue('NATIONAL INSTITUTE OF ELECTRONICS & INFORMATION TECHNOLOGY');
        $sheet->getStyle('A1')->getFont()->applyFromArray(['bold' => true, 'size' => 12]);
        
        $sheet->getCell('A2')->setValue('NIELIT BHUBANESWAR');
        $sheet->getStyle('A2')->getFont()->applyFromArray(['bold' => true, 'size' => 11]);
        
        $sheet->getCell('A3')->setValue('ATTENDANCE STATEMENT OF REGULAR EMPLOYEES');
        $sheet->getStyle('A3')->getFont()->applyFromArray(['bold' => true, 'size' => 11]);
        
        $sheet->getCell('A4')->setValue("For the month of {$monthName} {$selectedYear}");
        $sheet->getStyle('A4')->getFont()->applyFromArray(['size' => 10]);
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        $sheet->getColumnDimension('B')->setWidth(20);
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(8);
        $sheet->getColumnDimension('F')->setWidth(10);
        $sheet->getColumnDimension('G')->setWidth(8);
        $sheet->getColumnDimension('H')->setWidth(10);
        $sheet->getColumnDimension('I')->setWidth(8);
        $sheet->getColumnDimension('J')->setWidth(10);
        $sheet->getColumnDimension('K')->setWidth(10);
        $sheet->getColumnDimension('L')->setWidth(10);
        $sheet->getColumnDimension('M')->setWidth(20);
        
        // Header rows construction
        // Row 6 (Top level headers)
        $sheet->setCellValue('A6', 'S.No.');
        $sheet->mergeCells('A6:A7');
        
        $sheet->setCellValue('B6', 'Name & Designation');
        $sheet->mergeCells('B6:B7');
        
        $sheet->setCellValue('C6', 'Period of Absence/ OD & TOUR');
        $sheet->mergeCells('C6:D6');
        
        $sheet->setCellValue('E6', 'Nature of Leave/ OD/Tour');
        $sheet->mergeCells('E6:E7');
        
        $sheet->setCellValue('F6', 'Number of Days');
        $sheet->mergeCells('F6:J6');
        
        $sheet->setCellValue('K6', 'Working Days');
        $sheet->mergeCells('K6:K7');
        
        $sheet->setCellValue('L6', 'Net Working Days');
        $sheet->mergeCells('L6:L7');
        
        $sheet->setCellValue('M6', 'Remarks');
        $sheet->mergeCells('M6:M7');

        // Row 7 (Sub headers)
        $sheet->setCellValue('C7', 'From');
        $sheet->setCellValue('D7', 'To');
        $sheet->setCellValue('F7', 'OD/Tour');
        $sheet->setCellValue('G7', 'EL/CCL/PL');
        $sheet->setCellValue('H7', 'CL/RH');
        $sheet->setCellValue('I7', 'Sat/Sun/GH');
        $sheet->setCellValue('J7', 'Total');
        
        // Styling for headers
        $sheet->getStyle('A6:M7')->getFont()->applyFromArray(['bold' => true, 'size' => 10]);
        $sheet->getStyle('A6:M7')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A6:M7')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle('A6:M7')->getAlignment()->setWrapText(true);
        
        // Apply borders to header
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A6:M7")->applyFromArray($borderStyle);
        
        // Data rows start after header
        $row = 8;
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
            $fromStr = !empty($fromDates) ? implode("\n", $fromDates) : '---';
            $toStr = !empty($toDates) ? implode("\n", $toDates) : '---';
            $natureStr = !empty($natures) ? implode('; ', $natures) : '---';
            
            $odTour = $emp['od_days'] + $emp['tour_days'];
            $elCclPl = $emp['el_days'] + $emp['ccl_days'] + $emp['pl_days'];
            $clRh = $emp['cl_days'] + $emp['rh_days'];
            $satSunGh = $emp['sat_days'] + $emp['sun_days'] + $emp['gh_days'];
            $totalDays = $odTour + $elCclPl + $clRh + $satSunGh;
            
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
            
            // Add row data
            $sheet->getCell('A' . $row)->setValue($serialNo++);
            $sheet->getCell('B' . $row)->setValue($emp['full_name'] . ' (' . $emp['designation'] . ')');
            $sheet->getCell('C' . $row)->setValue($fromStr);
            $sheet->getCell('D' . $row)->setValue($toStr);
            $sheet->getCell('E' . $row)->setValue($natureStr);
            $sheet->getCell('F' . $row)->setValue($odTour > 0 ? $odTour : '---');
            $sheet->getCell('G' . $row)->setValue($elCclPl > 0 ? $elCclPl : '---');
            $sheet->getCell('H' . $row)->setValue($clRh > 0 ? $clRh : '---');
            $sheet->getCell('I' . $row)->setValue($satSunGh > 0 ? $satSunGh : '---');
            $sheet->getCell('J' . $row)->setValue($totalDays > 0 ? $totalDays : '---');
            $sheet->getCell('K' . $row)->setValue($emp['working_days'] ?: $daysInMonth);
            $sheet->getCell('L' . $row)->setValue($emp['net_working_days'] ?: $daysInMonth);
            $sheet->getCell('M' . $row)->setValue($finalRemarks);
            
            // Apply borders and alignment
            $sheet->getStyle("A$row:M$row")->applyFromArray($borderStyle);
            $sheet->getStyle("A$row:M$row")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
            $sheet->getStyle("A$row:M$row")->getAlignment()->setWrapText(true);
            
            $row++;
        }
        
        // Set row height for header
        $sheet->getRowDimension(6)->setRowHeight(40);
        
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
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');
        $sheet->mergeCells('A4:G4');
        
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
        $sheet->getColumnDimension('C')->setWidth(15);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(8);
        $sheet->getColumnDimension('G')->setWidth(20);
        
        // Header row
        $row = 6;
        $headers = ['S.No.', 'Name & Designation', 'Period of Leave (From – To)', 'Nature of Leave/OD', 
                    'Period of Absence (From – To)', 'Absent Days', 'Remarks'];
        $cols = ['A', 'B', 'C', 'D', 'E', 'F', 'G'];
        
        foreach ($headers as $idx => $header) {
            $cellRef = $cols[$idx] . $row;
            $sheet->getCell($cellRef)->setValue($header);
            $style = $sheet->getStyle($cellRef);
            $style->getFont()->applyFromArray(['bold' => true, 'size' => 10]);
            $style->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            $style->getAlignment()->setWrapText(true);
        }
        
        // Apply borders to header
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                ],
            ],
        ];
        $sheet->getStyle("A6:G6")->applyFromArray($borderStyle);
        
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
                
                $leavePerStr = !empty($leavePeriods) ? implode(', ', $leavePeriods) : '---';
                $leaveNatStr = !empty($leaveNatures) ? implode('; ', $leaveNatures) : '---';
                $absenceStr = !empty($absencePeriods) ? implode(', ', $absencePeriods) : '---';
                
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
                $sheet->getCell('C' . $row)->setValue($leavePerStr);
                $sheet->getCell('D' . $row)->setValue($leaveNatStr);
                $sheet->getCell('E' . $row)->setValue($absenceStr);
                $sheet->getCell('F' . $row)->setValue($emp['absent_days'] > 0 ? $emp['absent_days'] : '---');
                $sheet->getCell('G' . $row)->setValue($finalRemarks);
                
                // Apply borders and alignment
                $sheet->getStyle("A$row:G$row")->applyFromArray($borderStyle);
                $sheet->getStyle("A$row:G$row")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("A$row:G$row")->getAlignment()->setWrapText(true);
                
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
