<?php
// Start output buffering to prevent any stray output from corrupting PDF
ob_start();

// Enable error reporting for debugging (logs only, no display)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) && !$hasAccountantRole) {
    if (empty($_GET['payslip_id'])) {
        header("Location: ../auth/login.php");
        exit;
    }
}

require_once __DIR__ . '/../../app/Config/database.php';

// Check if vendor autoload exists
$autoloadPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    ob_end_clean();
    die('Error: Composer dependencies not installed. Please run "composer install" in the project directory.');
}
require_once $autoloadPath;

// Check if TCPDF class exists
if (!class_exists('TCPDF')) {
    ob_end_clean();
    die('Error: TCPDF library not found. Please run "composer install" to install dependencies.');
}

// Function to convert number to words (Indian format)
function numberToWords($num) {
    $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
             'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
    
    if ($num == 0) return 'Zero';
    if ($num < 0) return 'Minus ' . numberToWords(abs($num));
    
    $num = (int)$num;
    $words = '';
    
    // Crores (10 million)
    if ($num >= 10000000) {
        $words .= numberToWords(floor($num / 10000000)) . ' Crore ';
        $num %= 10000000;
    }
    
    // Lakhs (100 thousand)
    if ($num >= 100000) {
        $words .= numberToWords(floor($num / 100000)) . ' Lakh ';
        $num %= 100000;
    }
    
    // Thousands
    if ($num >= 1000) {
        $words .= numberToWords(floor($num / 1000)) . ' Thousand ';
        $num %= 1000;
    }
    
    // Hundreds
    if ($num >= 100) {
        $words .= $ones[floor($num / 100)] . ' Hundred ';
        $num %= 100;
    }
    
    if ($num >= 20) {
        $words .= $tens[floor($num / 10)] . ' ';
        $num %= 10;
    }
    
    if ($num > 0) {
        $words .= $ones[$num] . ' ';
    }
    
    return trim($words);
}

$db = getDBConnection();

// Get payslip ID from request
$payslipId = $_GET['payslip_id'] ?? null;

if (!$payslipId) {
    die('Error: Payslip ID not provided');
}

try {
    // Fetch payslip, payroll, and employee data with all required fields
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
            pr.canteen_subsidy,
            pr.gross_salary,
            pr.tax_deduction,
            pr.pf_deduction,
            pr.nps_deduction,
            pr.cpf_deduction,
            pr.professional_tax,
            pr.sudexo_deduction,
            pr.income_tax,
            pr.other_deductions,
            pr.total_deductions,
            pr.net_salary,
            pr.pay_level,
            e.employee_code,
            e.full_name,
            e.designation,
            e.email,
            e.phone,
            e.pan_no,
            e.pran_nps_no,
            e.bank_account_no,
            e.ifsc_code,
            e.bank_name,
            e.bank_branch,
            d.department_name,
            pl.level_name
        FROM payslips ps
        JOIN payroll pr ON ps.payroll_id = pr.payroll_id
        JOIN employees e ON ps.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN pay_levels pl ON e.pay_level_id = pl.level_id
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

// Load settings for accountant name
$accountantName = 'Assistant (Accounts)';
$accountantNameHindi = 'सहायक (लेखा)';
try {
    $settingsStmt = $db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('accountant_name', 'accountant_name_hindi')");
    while ($setting = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
        if ($setting['setting_key'] === 'accountant_name') $accountantName = $setting['setting_value'];
        if ($setting['setting_key'] === 'accountant_name_hindi') $accountantNameHindi = $setting['setting_value'];
    }
} catch (Exception $e) {
    // Use defaults
}

// Format date
$generatedDate = date('d/m/Y', strtotime($payslip['generated_at']));

// Convert net salary to words
$netSalaryWords = 'Rupees ' . numberToWords($payslip['net_salary']) . ' only';

// Get short month for display
$monthShort = substr($payslip['month'], 0, 3);
$yearShort = substr($payslip['year'], 2, 2);

// Create new PDF document using TCPDF
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

// Set document information
$pdf->SetCreator('NIELIT e-HRMS');
$pdf->SetAuthor('NIELIT Bhubaneswar');
$pdf->SetTitle('Payslip - ' . $payslip['full_name']);
$pdf->SetSubject('Pay Slip for ' . $payslip['month'] . ' ' . $payslip['year']);

// Remove default header/footer
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);

// Set margins
$pdf->SetMargins(10, 10, 10);
$pdf->SetAutoPageBreak(TRUE, 10);

// Add a page
$pdf->AddPage();

// Set font for Hindi support
$pdf->SetFont('freeserif', '', 10);

// Build HTML content for NIELIT format PDF
$html = '
<style>
    .header-hindi { font-size: 14px; font-weight: bold; text-align: center; color: #000080; }
    .header-english { font-size: 12px; font-weight: bold; text-align: center; color: #000080; }
    .address-hindi { font-size: 9px; text-align: center; color: #333; }
    .address-english { font-size: 9px; text-align: center; color: #333; }
    .payslip-title { font-size: 11px; font-weight: bold; text-align: center; margin: 5px 0; background: #e6e6ff; padding: 5px; }
    .info-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
    .info-table td { padding: 3px 5px; font-size: 9px; border: 1px solid #999; }
    .info-label { font-weight: bold; background: #f0f0f0; width: 20%; }
    .info-value { width: 30%; }
    .salary-table { width: 100%; border-collapse: collapse; margin: 5px 0; }
    .salary-table th { background: #d9d9d9; padding: 5px; font-size: 9px; font-weight: bold; border: 1px solid #999; text-align: center; }
    .salary-table td { padding: 4px 6px; font-size: 9px; border: 1px solid #999; }
    .amount-cell { text-align: right; }
    .total-row { background: #e6e6ff; font-weight: bold; }
    .net-pay-row { background: #ffffcc; font-weight: bold; }
    .words-row { font-size: 9px; font-style: italic; padding: 5px; background: #f9f9f9; border: 1px solid #999; }
    .signature-section { margin-top: 20px; }
    .signature-box { text-align: center; font-size: 9px; }
    .disclaimer { font-size: 8px; color: #666; text-align: center; margin-top: 10px; font-style: italic; }
    .date-section { text-align: right; font-size: 9px; margin: 5px 0; }
</style>

<!-- Header Section -->
<table width="100%" cellpadding="2">
    <tr>
        <td width="15%" align="center">
            <img src="' . __DIR__ . '/../assets/images/NIELIT-Preview.png" width="50" height="50">
        </td>
        <td width="70%" align="center">
            <div class="header-hindi">राष्ट्रीय इलेक्ट्रॉनिकी एवं सूचना प्रौद्योगिकी संस्थान</div>
            <div class="header-english">National Institute of Electronics and Information Technology</div>
            <div class="address-hindi">तीसरी मंजिल, उत्तरदिशा, ओ.सी.ए.सी.टॉवर, आचार्यविहार, भुवनेश्वर 751013</div>
            <div class="address-english">3rd Floor, North Side, OCAC Tower, Acharya Vihar, Bhubaneswar 751013</div>
        </td>
        <td width="15%"></td>
    </tr>
</table>

<div class="payslip-title">Pay Slip for the Month: ' . htmlspecialchars($monthShort) . ' ' . htmlspecialchars($payslip['year']) . '</div>

<div class="date-section">' . $generatedDate . '</div>

<!-- Employee Information Section -->
<table class="info-table">
    <tr>
        <td class="info-label">Emp No :</td>
        <td class="info-value">' . htmlspecialchars($payslip['employee_code'] ?? 'N/A') . '</td>
        <td class="info-label">PAN No :</td>
        <td class="info-value">' . htmlspecialchars($payslip['pan_no'] ?? 'N/A') . '</td>
    </tr>
    <tr>
        <td class="info-label">Name :</td>
        <td class="info-value">' . htmlspecialchars($payslip['full_name']) . '</td>
        <td class="info-label">PRAN/NPS No:</td>
        <td class="info-value">' . htmlspecialchars($payslip['pran_nps_no'] ?? 'N/A') . '</td>
    </tr>
    <tr>
        <td class="info-label">Designation :</td>
        <td class="info-value">' . htmlspecialchars($payslip['designation']) . '</td>
        <td class="info-label"></td>
        <td class="info-value">' . htmlspecialchars($payslip['department_name'] ?? 'NIELIT BHUBANESWAR') . '</td>
    </tr>
    <tr>
        <td class="info-label">Bank A/c No.</td>
        <td class="info-value">' . htmlspecialchars($payslip['bank_account_no'] ?? 'N/A') . '</td>
        <td class="info-label">Branch and<br>IFSC Code :</td>
        <td class="info-value">' . htmlspecialchars(($payslip['bank_branch'] ?? '') . '<br>' . ($payslip['ifsc_code'] ?? 'N/A')) . '</td>
    </tr>
    <tr>
        <td class="info-label">Pay Band :</td>
        <td class="info-value">' . htmlspecialchars($payslip['pay_level'] ?? $payslip['level_name'] ?? 'N/A') . '</td>
        <td class="info-label">Basic: Rs.</td>
        <td class="info-value">' . number_format($payslip['basic'], 2) . '</td>
    </tr>
</table>

<!-- Earnings and Deductions Section -->
<table class="salary-table">
    <tr>
        <th width="50%" colspan="2">EARNINGS (in Rs.)</th>
        <th width="50%" colspan="2">DEDUCTIONS (in Rs.)</th>
    </tr>
    <tr>
        <td width="30%">Basic</td>
        <td width="20%" class="amount-cell">' . number_format($payslip['basic'], 2) . '</td>
        <td width="30%">NPS @ 10%</td>
        <td width="20%" class="amount-cell">' . number_format($payslip['nps_deduction'], 2) . '</td>
    </tr>
    <tr>
        <td>DA @ 58%</td>
        <td class="amount-cell">' . number_format($payslip['da_amount'], 2) . '</td>
        <td>Income Tax</td>
        <td class="amount-cell">' . number_format($payslip['income_tax'] ?? $payslip['tax_deduction'], 2) . '</td>
    </tr>
    <tr>
        <td>HRA @ 20%</td>
        <td class="amount-cell">' . number_format($payslip['hra_amount'], 2) . '</td>
        <td>Professional Tax</td>
        <td class="amount-cell">' . number_format($payslip['professional_tax'], 2) . '</td>
    </tr>
    <tr>
        <td>Transport Allowance</td>
        <td class="amount-cell">' . number_format($payslip['ta_amount'], 2) . '</td>
        <td>Voluntary EPF</td>
        <td class="amount-cell">' . number_format($payslip['pf_deduction'], 2) . '</td>
    </tr>
    <tr>
        <td>DA on TA @ 58%</td>
        <td class="amount-cell">' . number_format($payslip['da_on_ta'], 2) . '</td>
        <td>Group Insurance</td>
        <td class="amount-cell">' . number_format($payslip['other_deductions'], 2) . '</td>
    </tr>
    <tr>
        <td>Canteen Subsidy</td>
        <td class="amount-cell">' . number_format($payslip['canteen_subsidy'] ?? 0, 2) . '</td>
        <td>Sudexo</td>
        <td class="amount-cell">' . number_format($payslip['sudexo_deduction'] ?? 0, 2) . '</td>
    </tr>
    <tr>
        <td>Bonus</td>
        <td class="amount-cell">' . number_format($payslip['bonus'], 2) . '</td>
        <td>CPF</td>
        <td class="amount-cell">' . number_format($payslip['cpf_deduction'] ?? 0, 2) . '</td>
    </tr>
    <tr>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
        <td>&nbsp;</td>
    </tr>
    <tr class="total-row">
        <td><strong>Gross Pay</strong></td>
        <td class="amount-cell"><strong>' . number_format($payslip['gross_salary'], 2) . '</strong></td>
        <td><strong>Total Deductions</strong></td>
        <td class="amount-cell"><strong>' . number_format($payslip['total_deductions'], 2) . '</strong></td>
    </tr>
    <tr class="net-pay-row">
        <td colspan="2"><strong>Net Pay</strong></td>
        <td colspan="2" class="amount-cell"><strong>Rs. ' . number_format($payslip['net_salary'], 2) . '</strong></td>
    </tr>
</table>

<!-- Net Pay in Words -->
<div class="words-row">
    <strong>' . $netSalaryWords . '</strong>
</div>

<!-- Signature Section -->
<table class="signature-section" width="100%">
    <tr>
        <td width="60%">&nbsp;</td>
        <td width="40%" align="center" class="signature-box">
            <br><br><br>
            <div style="border-top: 1px solid #000; padding-top: 5px;">
                <strong>' . htmlspecialchars($accountantNameHindi) . '</strong><br>
                <strong>' . htmlspecialchars($accountantName) . '</strong><br>
                सहायक (लेखा)<br>
                Assistant (Accounts)
            </div>
        </td>
    </tr>
</table>

<!-- Disclaimer -->
<div class="disclaimer">
    E&amp;OE. If any discrepancies found, kindly contact the concerned in Accounts Section immediately.
</div>
';

// Write HTML content to PDF
$pdf->writeHTML($html, true, false, true, false, '');

// Generate filename
$filename = str_replace(' ', '_', $payslip['full_name']) . '_Payslip_' . $payslip['month'] . '_' . $payslip['year'] . '.pdf';

// Clean any output buffer before sending PDF
ob_end_clean();

// Output PDF
try {
    $pdf->Output($filename, 'I');
} catch (Exception $e) {
    die('Error generating PDF: ' . htmlspecialchars($e->getMessage()));
}
