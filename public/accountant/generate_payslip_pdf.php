<?php
session_start();

// Allow access if user is logged in as accountant, or if payslip_id is provided (for direct links)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'accountant') {
    // Check if this is a direct link attempt without session
    if (empty($_GET['payslip_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
    // Allow direct payslip PDF access with ID
}

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Get payslip ID from request
$payslipId = $_GET['payslip_id'] ?? null;

if (!$payslipId) {
    die('Error: Payslip ID not provided');
}

try {
    // Fetch payslip and payroll data
    $stmt = $db->prepare("
        SELECT 
            ps.payslip_id,
            ps.generated_at,
            pr.payroll_id,
            pr.month,
            pr.year,
            pr.basic,
            pr.da_amount,
            pr.hra_amount,
            pr.ta_amount,
            pr.da_on_ta,
            pr.bonus,
            pr.gross_salary,
            pr.tax_deduction,
            pr.pf_deduction,
            pr.nps_deduction,
            pr.professional_tax,
            pr.other_deductions,
            pr.total_deductions,
            pr.net_salary,
            e.full_name,
            e.designation,
            e.email,
            e.phone,
            d.department_name
        FROM payslips ps
        JOIN payroll pr ON ps.payroll_id = pr.payroll_id
        JOIN employees e ON ps.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE ps.payslip_id = ?
    ");
    
    if (!$stmt->execute([$payslipId])) {
        die('Error: Query execution failed - ' . implode(', ', $stmt->errorInfo()));
    }
    
    $payslip = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$payslip) {
        die('Error: Payslip with ID ' . htmlspecialchars($payslipId) . ' not found');
    }
} catch (Exception $e) {
    die('Error: ' . htmlspecialchars($e->getMessage()));
}

// Pre-process variables to avoid null coalescing in heredoc
$deptName = $payslip['department_name'] ?? 'N/A';
$companyName = 'Payslip Generator Corp';
$companyAddress = '123 Business Street, City';
$companyPhone = '+1-800-PAYSLIP';


// Generate PDF using mPDF library approach (pure PHP HTML-to-PDF via browser print)
// For production, use: https://github.com/mpdf/mpdf or https://github.com/dompdf/dompdf

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslip - {$payslip['full_name']}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: white; color: #333; }
        @media print {
            body { margin: 0; padding: 0; }
            .no-print { display: none !important; }
            .page { page-break-after: always; }
        }
        .page { width: 210mm; height: 297mm; margin: 0 auto; padding: 20px; background: white; }
        .header { border-bottom: 3px solid #0ea5e9; margin-bottom: 20px; padding-bottom: 15px; }
        .company-info { display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px; }
        .company-name { font-size: 24px; font-weight: 700; color: #0b1221; }
        .company-detail { font-size: 11px; color: #666; line-height: 1.6; }
        .payslip-title { text-align: center; font-size: 20px; font-weight: 700; color: #0b1221; margin: 15px 0 5px 0; }
        .payslip-period { text-align: center; font-size: 13px; color: #666; margin-bottom: 15px; }
        
        .section { margin: 15px 0; }
        .section-title { background: linear-gradient(135deg, #0ea5e9, #22d3ee); color: white; padding: 10px 12px; font-weight: 700; border-radius: 4px; margin-bottom: 10px; font-size: 13px; }
        
        .two-column { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 15px 0; }
        .info-group { }
        .info-row { display: grid; grid-template-columns: 1fr auto; gap: 10px; padding: 6px 0; border-bottom: 1px solid #f0f0f0; font-size: 12px; }
        .info-label { font-weight: 600; color: #555; }
        .info-value { text-align: right; color: #333; }
        
        .earnings-deductions { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 15px 0; }
        .earnings-col, .deductions-col { }
        
        table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 15px; }
        th { background: #f0f0f0; padding: 8px 10px; text-align: left; border-bottom: 2px solid #0ea5e9; font-weight: 700; color: #333; }
        td { padding: 8px 10px; border-bottom: 1px solid #e0e0e0; }
        tr:last-child td { border-bottom: 2px solid #0ea5e9; }
        .amount { text-align: right; font-weight: 600; }
        .total-row { background: #f9f9f9; font-weight: 700; }
        .total-row .amount { color: #0ea5e9; }
        
        .summary-box { background: linear-gradient(135deg, #f0f9ff, #e0f7ff); padding: 15px; border-radius: 6px; border-left: 4px solid #0ea5e9; margin: 15px 0; }
        .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; font-size: 13px; border-bottom: 1px solid rgba(14, 165, 233, 0.2); }
        .summary-row:last-child { border-bottom: none; }
        .summary-label { font-weight: 600; color: #0b1221; }
        .summary-value { font-weight: 700; color: #0ea5e9; font-size: 14px; }
        
        .footer { margin-top: 20px; padding-top: 15px; border-top: 1px solid #e0e0e0; font-size: 10px; color: #999; text-align: center; }
        .footer-divider { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin: 20px 0; }
        .footer-box { text-align: center; padding: 10px 0; border-top: 1px solid #333; }
        .footer-label { font-size: 10px; color: #666; margin-top: 5px; }
        
        .no-print { background: #f9f9f9; padding: 10px; border-radius: 6px; margin-bottom: 15px; text-align: center; }
        .btn-print, .btn-download { background: #0ea5e9; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; font-weight: 700; }
        .btn-print:hover { background: #0284c7; }
        .btn-download { background: #22d3ee; }
        .btn-download:hover { background: #06b6d4; }
    </style>
</head>
<body>
    <div class="no-print" style="margin: 0 0 15px 0;">
        <button class="btn-print" onclick="window.print()">🖨️ Print Payslip</button>
        <button class="btn-download" onclick="downloadPDF()">📥 Download as PDF</button>
        <button style="background: #999;" onclick="window.history.back()">← Back</button>
    </div>

    <div class="page">
        <!-- Header -->
        <div class="header">
            <div class="company-info">
                <div>
                    <div class="company-name">💼 {$companyName}</div>
                    <div class="company-detail">{$companyAddress}</div>
                    <div class="company-detail">📞 {$companyPhone}</div>
                </div>
                <div>
                    <div style="font-size: 11px; text-align: right; color: #666;">
                        <strong>Payslip ID:</strong> {$payslip['payslip_id']}<br>
                        <strong>Date:</strong> {$payslip['generated_at']}
                    </div>
                </div>
            </div>
        </div>

        <!-- Title -->
        <div class="payslip-title">SALARY STATEMENT / PAYSLIP</div>
        <div class="payslip-period">For the Month of {$payslip['month']}, {$payslip['year']}</div>

        <!-- Employee & Company Info -->
        <div class="two-column">
            <div class="info-group">
                <div style="font-weight: 700; margin-bottom: 8px; border-bottom: 2px solid #0ea5e9; padding-bottom: 5px;">Employee Information</div>
                <div class="info-row">
                    <span class="info-label">Name</span>
                    <span class="info-value">{$payslip['full_name']}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Designation</span>
                    <span class="info-value">{$payslip['designation']}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Department</span>
                    <span class="info-value">{$deptName}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Email</span>
                    <span class="info-value">{$payslip['email']}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Phone</span>
                    <span class="info-value">{$payslip['phone']}</span>
                </div>
            </div>

            <div class="info-group">
                <div style="font-weight: 700; margin-bottom: 8px; border-bottom: 2px solid #0ea5e9; padding-bottom: 5px;">Payroll Details</div>
                <div class="info-row">
                    <span class="info-label">Month</span>
                    <span class="info-value">{$payslip['month']}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Year</span>
                    <span class="info-value">{$payslip['year']}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payslip ID</span>
                    <span class="info-value">{$payslip['payslip_id']}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Generated</span>
                    <span class="info-value">{$payslip['generated_at']}</span>
                </div>
            </div>
        </div>

        <!-- Earnings & Deductions -->
        <div class="earnings-deductions">
            <div class="earnings-col">
                <div class="section-title">💰 EARNINGS</div>
                <table>
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th class="amount">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Basic Salary</td>
                            <td class="amount">{$payslip['basic']}</td>
                        </tr>
                        <tr>
                            <td>HRA (20%)</td>
                            <td class="amount">{$payslip['hra_amount']}</td>
                        </tr>
                        <tr>
                            <td>DA (58%)</td>
                            <td class="amount">{$payslip['da_amount']}</td>
                        </tr>
                        <tr>
                            <td>Transport Allowance</td>
                            <td class="amount">{$payslip['ta_amount']}</td>
                        </tr>
                        <tr>
                            <td>DA on TA (58%)</td>
                            <td class="amount">{$payslip['da_on_ta']}</td>
                        </tr>
                        <tr>
                            <td>Bonus</td>
                            <td class="amount">{$payslip['bonus']}</td>
                        </tr>
                        <tr class="total-row">
                            <td>GROSS SALARY</td>
                            <td class="amount">{$payslip['gross_salary']}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="deductions-col">
                <div class="section-title">📊 DEDUCTIONS</div>
                <table>
                    <thead>
                        <tr>
                            <th>Component</th>
                            <th class="amount">Amount (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Income Tax (TDS)</td>
                            <td class="amount">{$payslip['tax_deduction']}</td>
                        </tr>
                        <tr>
                            <td>EPF (12%)</td>
                            <td class="amount">{$payslip['pf_deduction']}</td>
                        </tr>
                        <tr>
                            <td>NPS (10%)</td>
                            <td class="amount">{$payslip['nps_deduction']}</td>
                        </tr>
                        <tr>
                            <td>Professional Tax</td>
                            <td class="amount">{$payslip['professional_tax']}</td>
                        </tr>
                        <tr>
                            <td>Other Deductions</td>
                            <td class="amount">{$payslip['other_deductions']}</td>
                        </tr>
                        <tr class="total-row">
                            <td>TOTAL DEDUCTIONS</td>
                            <td class="amount">{$payslip['total_deductions']}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Summary Box -->
        <div class="summary-box">
            <div class="summary-row">
                <span class="summary-label">Gross Salary</span>
                <span class="summary-value">₹ {$payslip['gross_salary']}</span>
            </div>
            <div class="summary-row">
                <span class="summary-label">Total Deductions</span>
                <span class="summary-value">- ₹ {$payslip['total_deductions']}</span>
            </div>
            <div class="summary-row" style="border-bottom: 2px solid #0ea5e9; padding: 10px 0; font-size: 16px;">
                <span class="summary-label" style="font-size: 15px;">NET SALARY (Take-Home)</span>
                <span class="summary-value" style="font-size: 16px;">₹ {$payslip['net_salary']}</span>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div style="margin: 10px 0; font-size: 11px; color: #333;">
                <strong>Note:</strong> This is a computer-generated document and does not require a signature.
            </div>
            <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #ddd;">
                <p style="font-size: 10px;">For any queries regarding this payslip, please contact the HR/Accounts department.</p>
                <p style="font-size: 9px; margin-top: 10px; color: #999;">Generated on {$payslip['generated_at']} | Payslip ID: {$payslip['payslip_id']}</p>
            </div>
        </div>
    </div>

    <script>
        function downloadPDF() {
            const element = document.querySelector('.page');
            const filename = '{$payslip['full_name']}_Payslip_{$payslip['month']}_{$payslip['year']}.pdf';
            
            // Using html2pdf.js CDN-free alternative or print-to-PDF
            alert('Click OK to open print dialog, then save as PDF using your browser.');
            window.print();
        }
    </script>
</body>
</html>
HTML;

echo $html;
?>
