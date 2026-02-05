<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has accountant role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!$hasAccountantRole && $_SESSION['role'] !== 'accountant') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';

// Standard salary component percentages (editable in one place)
$standardRates = [
    'hra_percent' => 20,      // HRA = 20% of Basic
    'da_percent' => 58,       // DA = 58% of Basic
    'da_ta_percent' => 58,    // DA on TA = 58% of TA
    'tax_percent' => 10,      // Tax = 10% of Gross (edit if slab-based)
    'epf_percent' => 12,      // EPF = 12% of Basic (edit if different)
    'nps_percent' => 10,      // NPS = 10% of Basic (edit if different)
    'professional_tax' => 200 // Flat professional tax (edit if state-specific)
];

// Fetch all employees for payslip generation
$stmt = $db->prepare("
    SELECT 
        e.employee_id,
        e.employee_code,
        e.full_name,
        e.designation,
        e.department_id,
        d.department_name,
        e.basic_salary,
        e.employment_type,
        e.email,
        e.phone,
        e.pay_level_id,
        e.hra_type,
        p.level_name AS pay_level,
        p.transport_allowance
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN pay_levels p ON e.pay_level_id = p.level_id
    WHERE e.status = 'active'
    ORDER BY e.full_name ASC
");
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle payslip generation form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_payslip'])) {
    $employeeId = $_POST['employee_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    $basicSalary = (float)($_POST['basic_salary'] ?? 0);
    $hra = (float)($_POST['hra'] ?? 0);
    $da = (float)($_POST['da'] ?? 0);
    $taAmount = (float)($_POST['ta_amount'] ?? 0);
    $daTa = (float)($_POST['da_ta'] ?? 0);
    $canteenSubsidy = (float)($_POST['canteen_subsidy'] ?? 0);
    $bonus = (float)($_POST['bonus'] ?? 0);
    $taxDeduction = (float)($_POST['tax_deduction'] ?? 0);
    $pfDeduction = (float)($_POST['pf_deduction'] ?? 0);
    $npsDeduction = (float)($_POST['nps_deduction'] ?? 0);
    $cpfDeduction = (float)($_POST['cpf_deduction'] ?? 0);
    $professionalTax = (float)($_POST['professional_tax'] ?? 0);
    $sudexoDeduction = (float)($_POST['sudexo_deduction'] ?? 0);
    $incomeTax = (float)($_POST['income_tax'] ?? 0);
    $otherDeductions = (float)($_POST['other_deductions'] ?? 0);
    $payLevel = $_POST['pay_level'] ?? '';
    
    // Calculate totals using 7th CPC components
    $grossSalary = $basicSalary + $hra + $da + $taAmount + $daTa + $canteenSubsidy + $bonus;
    $totalDeductions = $taxDeduction + $pfDeduction + $npsDeduction + $cpfDeduction + $professionalTax + $sudexoDeduction + $incomeTax + $otherDeductions;
    $netSalary = $grossSalary - $totalDeductions;
    
    // Insert payroll record first
    try {
        $db->beginTransaction();
        
        // Insert into payroll table with 7th CPC components
        $payrollStmt = $db->prepare("
            INSERT INTO payroll 
            (employee_id, month, year, basic, da_amount, hra_amount, ta_amount, da_on_ta, canteen_subsidy, bonus,
             gross_salary, tax_deduction, pf_deduction, nps_deduction, cpf_deduction, professional_tax, 
             sudexo_deduction, income_tax, other_deductions, total_deductions, net_salary, pay_level, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $payrollStmt->execute([
            $employeeId,
            $month,
            $year,
            $basicSalary,
            $da,
            $hra,
            $taAmount,
            $daTa,
            $canteenSubsidy,
            $bonus,
            $grossSalary,
            $taxDeduction,
            $pfDeduction,
            $npsDeduction,
            $cpfDeduction,
            $professionalTax,
            $sudexoDeduction,
            $incomeTax,
            $otherDeductions,
            $totalDeductions,
            $netSalary,
            $payLevel
        ]);
        
        $payrollId = $db->lastInsertId();
        
        // Insert into payslips table
        $payslipStmt = $db->prepare("
            INSERT INTO payslips 
            (payroll_id, employee_id, generated_at)
            VALUES (?, ?, NOW())
        ");
        
        $payslipStmt->execute([$payrollId, $employeeId]);
        
        $db->commit();
        
        $success = true;
        $payslipId = $db->lastInsertId();
    } catch (PDOException $e) {
        $db->rollBack();
        $error = "Failed to generate payslip: " . $e->getMessage();
    }
}

// Get recent payslips with payroll data
$recentStmt = $db->prepare("
    SELECT 
        ps.payslip_id,
        ps.generated_at,
        ps.file_path,
        pr.month,
        pr.year,
        pr.basic,
        pr.da_amount,
        pr.hra_amount,
        pr.gross_salary,
        pr.total_deductions,
        pr.net_salary,
        e.full_name,
        e.designation,
        d.department_name
    FROM payslips ps
    JOIN payroll pr ON ps.payroll_id = pr.payroll_id
    JOIN employees e ON ps.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY ps.generated_at DESC
    LIMIT 10
");
$recentStmt->execute();
$recentPayslips = $recentStmt->fetchAll(PDO::FETCH_ASSOC);

$success = isset($_GET['success']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Payslip - Accountant Portal</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        /* Page Card */
        .page-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            overflow: hidden;
            margin-bottom: 25px;
        }

        .page-card-header {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-card-header h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }

        .page-card-header i {
            font-size: 20px;
        }

        .page-card-body {
            padding: 25px;
        }

        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group label i {
            color: var(--accent);
            font-size: 14px;
            width: 18px;
        }

        .form-group input,
        .form-group select {
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: #fafbfc;
            color: var(--text);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            background: white;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: #a0aec0;
        }

        /* Section Divider */
        .section-divider {
            display: flex;
            align-items: center;
            gap: 15px;
            margin: 25px 0 20px 0;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .section-divider::before,
        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border);
        }

        .section-divider i {
            color: var(--accent);
        }

        /* Rate Selector Buttons */
        .rate-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
        }

        .rate-btn {
            flex: 1;
            padding: 10px 14px;
            border: 2px solid var(--border);
            border-radius: 10px;
            background: #fafbfc;
            color: var(--muted);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .rate-btn:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: white;
        }

        .rate-btn.active {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-color: transparent;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.25);
        }

        /* Employee Info Card */
        .employee-info-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            border-radius: 12px;
            padding: 15px 18px;
            border: 1px solid rgba(102, 126, 234, 0.15);
        }

        .employee-info-card div {
            padding: 4px 0;
            font-size: 13px;
            color: var(--muted);
        }

        .employee-info-card strong {
            color: var(--text);
        }

        /* Summary Panel */
        .summary-panel {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 16px;
            padding: 25px;
            border: 1px solid var(--border);
            margin-top: 25px;
        }

        .summary-panel h3 {
            font-size: 15px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .summary-panel h3 i {
            color: var(--accent);
        }

        .summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 25px;
        }

        .summary-column h4 {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid var(--border);
        }

        .summary-column.earnings h4 { color: var(--success); border-color: var(--success); }
        .summary-column.deductions h4 { color: var(--danger); border-color: var(--danger); }

        .summary-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 13px;
            color: var(--muted);
        }

        .summary-row span:last-child {
            font-weight: 600;
            color: var(--text);
        }

        .summary-total {
            background: white;
            border-radius: 10px;
            padding: 12px 15px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            border: 1px solid var(--border);
        }

        .summary-total.earnings { border-left: 4px solid var(--success); }
        .summary-total.deductions { border-left: 4px solid var(--danger); }
        .summary-total.deductions span:last-child { color: var(--danger); }

        .net-pay-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 16px;
            padding: 30px;
            color: white;
            text-align: center;
        }

        .net-pay-box .label {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.9;
            margin-bottom: 8px;
        }

        .net-pay-box .amount {
            font-size: 32px;
            font-weight: 700;
        }

        /* Submit Button */
        .btn-submit {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 14px 32px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.25);
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.35);
        }

        /* Recent Payslips Table */
        .payslip-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .payslip-table th {
            background: #f8fafc;
            padding: 14px 16px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border);
        }

        .payslip-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .payslip-table tr:hover td {
            background: rgba(102, 126, 234, 0.03);
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .status-badge.generated {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .btn-download {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-download:hover {
            text-decoration: underline;
        }

        /* Alerts */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #059669;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #dc2626;
        }

        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .required { color: var(--danger); }

        @media (max-width: 992px) {
            .summary-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-file-invoice-dollar"></i> Generate Payslip</h1>
                <p>Create and manage employee payslips</p>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Payslip generated successfully!
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Payslip Generation Form -->
        <div class="page-card">
            <div class="page-card-header">
                <i class="fas fa-plus-circle"></i>
                <h2>New Payslip</h2>
            </div>
            <div class="page-card-body">
                <form method="POST" id="payslipForm">
                    <input type="hidden" name="generate_payslip" value="1">
                    <input type="hidden" name="pay_level" id="pay_level" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="employee_id"><i class="fas fa-user"></i> Employee <span class="required">*</span></label>
                            <select id="employee_id" name="employee_id" required>
                                <option value="">Select Employee</option>
                                <?php foreach($employees as $emp): ?>
                                    <option value="<?php echo $emp['employee_id']; ?>" 
                                            data-salary="<?php echo $emp['basic_salary']; ?>"
                                            data-designation="<?php echo htmlspecialchars($emp['designation']); ?>"
                                            data-department="<?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?>"
                                            data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                            data-employment-type="<?php echo $emp['employment_type']; ?>"
                                            data-pay-level="<?php echo htmlspecialchars($emp['pay_level'] ?? ''); ?>"
                                            data-hra-type="<?php echo $emp['hra_type'] ?? 'city_b'; ?>"
                                            data-transport-allowance="<?php echo $emp['transport_allowance'] ?? 0; ?>">
                                        <?php echo htmlspecialchars($emp['employee_code'] ?? 'EMP' . str_pad($emp['employee_id'], 3, '0', STR_PAD_LEFT)); ?> - <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label><i class="fas fa-id-card"></i> Employee Details</label>
                            <div id="employee_info" class="employee-info-card">
                                <div id="emp_designation"><strong>Designation:</strong> -</div>
                                <div id="emp_department"><strong>Department:</strong> -</div>
                                <div id="emp_pay_level"><strong>Pay Level:</strong> -</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="month"><i class="fas fa-calendar"></i> Month <span class="required">*</span></label>
                            <select id="month" name="month" required>
                                <?php
                                $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                          'July', 'August', 'September', 'October', 'November', 'December'];
                                foreach($months as $i => $m) {
                                    $selected = ($i + 1 == date('n')) ? 'selected' : '';
                                    echo "<option value='$m' $selected>$m</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="year"><i class="fas fa-calendar-alt"></i> Year <span class="required">*</span></label>
                            <input type="number" id="year" name="year" value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                        </div>
                    </div>

                    <div class="section-divider">
                        <i class="fas fa-plus-circle"></i> Earnings
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="basic_salary"><i class="fas fa-money-bill-wave"></i> Basic Pay <span class="required">*</span></label>
                            <input type="number" id="basic_salary" name="basic_salary" step="0.01" required placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="da"><i class="fas fa-coins"></i> DA (58%)</label>
                            <input type="number" id="da" name="da" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="hra"><i class="fas fa-home"></i> HRA</label>
                            <div class="rate-selector" id="hra_buttons">
                                <button type="button" class="rate-btn active" data-value="20">20%</button>
                                <button type="button" class="rate-btn" data-value="10">10%</button>
                                <button type="button" class="rate-btn" data-value="0">0%</button>
                            </div>
                            <input type="hidden" id="hra_rate" value="20">
                            <input type="number" id="hra" name="hra" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="ta_amount"><i class="fas fa-bus"></i> Transport Allowance</label>
                            <div class="rate-selector" id="ta_buttons">
                                <button type="button" class="rate-btn active" data-value="3600">₹3.6K</button>
                                <button type="button" class="rate-btn" data-value="1800">₹1.8K</button>
                                <button type="button" class="rate-btn" data-value="900">₹900</button>
                                <button type="button" class="rate-btn" data-value="0">None</button>
                            </div>
                            <input type="hidden" id="ta_rate" value="3600">
                            <input type="number" id="ta_amount" name="ta_amount" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="da_ta"><i class="fas fa-percentage"></i> DA on TA (58%)</label>
                            <input type="number" id="da_ta" name="da_ta" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="canteen_subsidy"><i class="fas fa-utensils"></i> Canteen</label>
                            <input type="number" id="canteen_subsidy" name="canteen_subsidy" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="bonus"><i class="fas fa-gift"></i> Bonus</label>
                            <input type="number" id="bonus" name="bonus" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <div class="section-divider">
                        <i class="fas fa-minus-circle"></i> Deductions
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="pf_deduction"><i class="fas fa-piggy-bank"></i> EPF AMT</label>
                            <input type="number" id="pf_deduction" name="pf_deduction" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="nps_deduction"><i class="fas fa-university"></i> NPS AMT</label>
                            <input type="number" id="nps_deduction" name="nps_deduction" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="cpf_deduction"><i class="fas fa-coins"></i> CPF</label>
                            <input type="number" id="cpf_deduction" name="cpf_deduction" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="professional_tax"><i class="fas fa-id-badge"></i> Professional Tax</label>
                            <input type="number" id="professional_tax" name="professional_tax" step="0.01" value="<?php echo $standardRates['professional_tax']; ?>" min="0" placeholder="200">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="sudexo_deduction"><i class="fas fa-credit-card"></i> Sudexo</label>
                            <input type="number" id="sudexo_deduction" name="sudexo_deduction" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="income_tax"><i class="fas fa-file-invoice-dollar"></i> Income Tax</label>
                            <input type="number" id="income_tax" name="income_tax" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="tax_deduction"><i class="fas fa-receipt"></i> TDS</label>
                            <input type="number" id="tax_deduction" name="tax_deduction" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>

                        <div class="form-group">
                            <label for="other_deductions"><i class="fas fa-hand-holding-usd"></i> Other</label>
                            <input type="number" id="other_deductions" name="other_deductions" step="0.01" value="0" min="0" placeholder="0.00">
                        </div>
                    </div>

                    <!-- Salary Summary -->
                    <div class="summary-panel">
                        <h3><i class="fas fa-calculator"></i> Salary Summary</h3>
                        
                        <div class="summary-grid">
                            <!-- Earnings Column -->
                            <div class="summary-column earnings">
                                <h4>Earnings</h4>
                                <div class="summary-row"><span>Basic Pay</span><span id="display_basic">₹0</span></div>
                                <div class="summary-row"><span>DA (58%)</span><span id="display_da">₹0</span></div>
                                <div class="summary-row"><span>HRA</span><span id="display_hra">₹0</span></div>
                                <div class="summary-row"><span>TA</span><span id="display_ta">₹0</span></div>
                                <div class="summary-row"><span>DA on TA</span><span id="display_da_ta">₹0</span></div>
                                <div class="summary-row"><span>Canteen</span><span id="display_canteen">₹0</span></div>
                                <div class="summary-row"><span>Bonus</span><span id="display_bonus">₹0</span></div>
                                <div class="summary-total earnings">
                                    <span>TOTAL SALARY</span><span id="display_gross" style="color: var(--success);">₹0</span>
                                </div>
                            </div>
                            
                            <!-- Deductions Column -->
                            <div class="summary-column deductions">
                                <h4>Deductions</h4>
                                <div class="summary-row"><span>EPF AMT</span><span id="display_pf">-₹0</span></div>
                                <div class="summary-row"><span>NPS AMT</span><span id="display_nps">-₹0</span></div>
                                <div class="summary-row"><span>CPF</span><span id="display_cpf">-₹0</span></div>
                                <div class="summary-row"><span>Prof. Tax</span><span id="display_pt">-₹0</span></div>
                                <div class="summary-row"><span>Sudexo</span><span id="display_sudexo">-₹0</span></div>
                                <div class="summary-row"><span>Income Tax</span><span id="display_incometax">-₹0</span></div>
                                <div class="summary-row"><span>TDS</span><span id="display_tax">-₹0</span></div>
                                <div class="summary-row"><span>Other</span><span id="display_other">-₹0</span></div>
                                <div class="summary-total deductions">
                                    <span>TOTAL DED</span><span id="display_total_ded">-₹0</span>
                                </div>
                            </div>
                            
                            <!-- Net Pay Box -->
                            <div class="net-pay-box">
                                <span class="label">PAYABLE SALARY</span>
                                <span class="amount" id="display_net">₹0</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-top: 25px;">
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-file-invoice"></i> Generate Payslip
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Recent Payslips -->
        <div class="page-card">
            <div class="page-card-header">
                <i class="fas fa-history"></i>
                <h2>Recent Payslips</h2>
            </div>
            <div class="page-card-body">
                <?php if (count($recentPayslips) > 0): ?>
                    <table class="payslip-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Gross Salary</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentPayslips as $payslip): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($payslip['full_name']); ?></strong><br>
                                        <small style="color: var(--muted);"><?php echo htmlspecialchars($payslip['designation']); ?></small>
                                    </td>
                                    <td><?php echo $payslip['month']; ?> <?php echo $payslip['year']; ?></td>
                                    <td>₹<?php echo number_format($payslip['gross_salary'], 0); ?></td>
                                    <td style="color: var(--danger);">-₹<?php echo number_format($payslip['total_deductions'], 0); ?></td>
                                    <td style="color: var(--success); font-weight: 600;">₹<?php echo number_format($payslip['net_salary'], 0); ?></td>
                                    <td><span class="status-badge generated">Generated</span></td>
                                    <td>
                                        <a href="generate_payslip_pdf.php?payslip_id=<?php echo $payslip['payslip_id']; ?>" target="_blank" class="btn-download">
                                            <i class="fas fa-download"></i> PDF
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <p>No payslips generated yet</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // 7th CPC percentage rates
        const rates = {
            hra_high: 20,    // HRA = 20% of Basic
            hra_low: 10,     // HRA = 10% of Basic
            da: 58,          // DA = 58% of Basic
            daOnTa: 58,      // DA on TA = 58% of TA
            epf: 12,         // EPF = 12% of Basic
            nps: 10          // NPS = 10% of Basic
        };

        // Form elements
        const employeeSelect = document.getElementById('employee_id');
        const basicSalaryInput = document.getElementById('basic_salary');
        const hraInput = document.getElementById('hra');
        const daInput = document.getElementById('da');
        const taInput = document.getElementById('ta_amount');
        const daTaInput = document.getElementById('da_ta');
        const canteenInput = document.getElementById('canteen_subsidy');
        const pfInput = document.getElementById('pf_deduction');
        const npsInput = document.getElementById('nps_deduction');
        const cpfInput = document.getElementById('cpf_deduction');
        const professionalTaxInput = document.getElementById('professional_tax');
        const sudexoInput = document.getElementById('sudexo_deduction');
        const incomeTaxInput = document.getElementById('income_tax');
        const payLevelInput = document.getElementById('pay_level');
        const hraRateInput = document.getElementById('hra_rate');
        const taRateInput = document.getElementById('ta_rate');

        // HRA button click handler
        document.querySelectorAll('#hra_buttons .rate-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('#hra_buttons .rate-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Calculate HRA
                const rate = parseInt(this.dataset.value);
                hraRateInput.value = rate;
                const basic = parseFloat(basicSalaryInput.value) || 0;
                hraInput.value = (basic * rate / 100).toFixed(2);
                updateCalculations();
            });
        });

        // TA button click handler
        document.querySelectorAll('#ta_buttons .rate-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('#ta_buttons .rate-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Calculate TA and DA on TA
                const ta = parseFloat(this.dataset.value);
                taRateInput.value = ta;
                taInput.value = ta.toFixed(2);
                daTaInput.value = (ta * rates.daOnTa / 100).toFixed(2);
                updateCalculations();
            });
        });

        // Helper function to set active button
        function setActiveButton(container, value) {
            document.querySelectorAll(`#${container} .rate-btn`).forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.value == value) {
                    btn.classList.add('active');
                }
            });
        }
        
        employeeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (!selectedOption.value) {
                // Clear all fields if no employee selected
                basicSalaryInput.value = '';
                hraInput.value = 0;
                daInput.value = 0;
                taInput.value = 0;
                daTaInput.value = 0;
                canteenInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                cpfInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = <?php echo $standardRates['professional_tax']; ?>;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                document.getElementById('other_deductions').value = 0;
                document.getElementById('bonus').value = 0;
                document.getElementById('emp_designation').textContent = 'Designation: -';
                document.getElementById('emp_department').textContent = 'Department: -';
                document.getElementById('emp_email').textContent = 'Email: -';
                document.getElementById('emp_pay_level').innerHTML = '<span style="color: #667eea;">Pay Level: -</span>';
                payLevelInput.value = '';
                
                // Show all fields
                showAllFields();
                updateCalculations();
                return;
            }
            
            const salary = parseFloat(selectedOption.dataset.salary) || 0;
            const designation = selectedOption.dataset.designation || '-';
            const department = selectedOption.dataset.department || '-';
            const email = selectedOption.dataset.email || '-';
            const employmentType = selectedOption.dataset.employmentType || 'permanent';
            const payLevel = selectedOption.dataset.payLevel || '-';
            const hraType = selectedOption.dataset.hraType || 'city_b';
            const transportAllowance = parseFloat(selectedOption.dataset.transportAllowance) || 0;
            
            // Update employee info display
            document.getElementById('emp_designation').innerHTML = '<i class="fas fa-briefcase"></i> Designation: <strong>' + designation + '</strong>';
            document.getElementById('emp_department').innerHTML = '<i class="fas fa-building"></i> Department: <strong>' + department + '</strong>';
            document.getElementById('emp_email').innerHTML = '<i class="fas fa-envelope"></i> Email: <strong>' + email + '</strong>';
            document.getElementById('emp_pay_level').innerHTML = '<i class="fas fa-layer-group"></i> Pay Level: <strong style="color: #667eea;">' + (payLevel || 'Not Assigned') + '</strong>';
            
            // Set pay level hidden field
            payLevelInput.value = payLevel;
            
            // Set basic salary
            basicSalaryInput.value = salary.toFixed(2);

            // Determine HRA rate based on city type
            let hraRate = rates.hra_b; // Default 16%
            if (hraType === 'city_a') hraRate = rates.hra_a; // 24%
            else if (hraType === 'city_c') hraRate = rates.hra_c; // 8%

            // Check if employee is an intern or contract
            if (employmentType === 'intern') {
                // For interns: Only stipend (10,000), no allowances, no deductions
                hraInput.value = 0;
                daInput.value = 0;
                taInput.value = 0;
                daTaInput.value = 0;
                canteenInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                cpfInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = 0;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                document.getElementById('other_deductions').value = 0;
                document.getElementById('bonus').value = 0;
                
                basicSalaryInput.readOnly = true;
                basicSalaryInput.style.background = '#f8f9fa';
                
                // Remove any existing employment note first
                const existingNote = document.getElementById('employment-note');
                if (existingNote) existingNote.remove();
                
                // Show info message for intern
                const earningsHeader = document.querySelector('.section-header');
                const employmentNote = document.createElement('div');
                employmentNote.id = 'employment-note';
                employmentNote.style.cssText = 'background: #fff8e1; border: 2px solid #ffd54f; padding: 12px; border-radius: 8px; margin: 10px 0; font-size: 13px; color: #f57c00;';
                employmentNote.innerHTML = '<i class="fas fa-info-circle"></i> <strong>Intern:</strong> Fixed stipend. No automatic allowances or deductions.';
                earningsHeader.parentNode.insertBefore(employmentNote, earningsHeader.nextSibling);
            } else if (employmentType === 'contract') {
                // For contract employees: Only basic salary, no allowances or deductions
                hraInput.value = 0;
                daInput.value = 0;
                taInput.value = 0;
                daTaInput.value = 0;
                canteenInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                cpfInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = 0;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                document.getElementById('other_deductions').value = 0;
                document.getElementById('bonus').value = 0;
                
                basicSalaryInput.readOnly = false;
                basicSalaryInput.style.background = '#ffffff';
                
                // Remove any existing employment note first
                const existingNote = document.getElementById('employment-note');
                if (existingNote) existingNote.remove();
                
                // Show info message for contract
                const earningsHeader = document.querySelector('.section-header');
                const employmentNote = document.createElement('div');
                employmentNote.id = 'employment-note';
                employmentNote.style.cssText = 'background: #e3f2fd; border: 2px solid #64b5f6; padding: 12px; border-radius: 8px; margin: 10px 0; font-size: 13px; color: #1976d2;';
                employmentNote.innerHTML = '<i class="fas fa-info-circle"></i> <strong>Contract Employee:</strong> Only basic salary. Manual allowances/deductions if needed.';
                earningsHeader.parentNode.insertBefore(employmentNote, earningsHeader.nextSibling);
            } else {
                // For permanent employees: Full 7th CPC breakdown
                basicSalaryInput.readOnly = false;
                basicSalaryInput.style.background = '#ffffff';
                
                // Remove employment note if exists
                const employmentNote = document.getElementById('employment-note');
                if (employmentNote) employmentNote.remove();

                // Determine HRA rate (20% for city_a/city_b, 10% for city_c)
                let hraRate = (hraType === 'city_c') ? rates.hra_low : rates.hra_high;
                hraRateInput.value = hraRate;
                setActiveButton('hra_buttons', hraRate);

                // Set TA button based on transport allowance
                let taValue = 0;
                if (transportAllowance >= 3600) {
                    taValue = 3600;
                } else if (transportAllowance >= 1800) {
                    taValue = 1800;
                } else if (transportAllowance > 0) {
                    taValue = 900;
                }
                taRateInput.value = taValue;
                setActiveButton('ta_buttons', taValue);

                // Auto-calculate 7th CPC components
                hraInput.value = (salary * hraRate / 100).toFixed(2);
                daInput.value = (salary * rates.da / 100).toFixed(2);
                taInput.value = taValue.toFixed(2);
                daTaInput.value = (taValue * rates.daOnTa / 100).toFixed(2);
                canteenInput.value = 0;
                pfInput.value = (salary * rates.epf / 100).toFixed(2);
                npsInput.value = (salary * rates.nps / 100).toFixed(2);
                cpfInput.value = 0;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = <?php echo $standardRates['professional_tax']; ?>;
            }

            showAllFields();
            updateCalculations();
        });

        function showAllFields() {
            // Show all fields (in case they were hidden before)
            const allInputs = document.querySelectorAll('.form-group');
            allInputs.forEach(group => {
                group.style.display = '';
            });
        }

        // Update calculations in real-time
        const numericInputs = [
            'basic_salary', 'hra', 'da', 'ta_amount', 'da_ta', 'canteen_subsidy', 'bonus', 
            'tax_deduction', 'pf_deduction', 'nps_deduction', 'cpf_deduction', 
            'professional_tax', 'sudexo_deduction', 'income_tax', 'other_deductions'
        ];
        
        numericInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', updateCalculations);
            }
        });

        // TA input triggers DA on TA recalculation
        taInput.addEventListener('input', () => {
            const taVal = parseFloat(taInput.value) || 0;
            const daTaVal = taVal * rates.daOnTa / 100;
            daTaInput.value = daTaVal.toFixed(2);
            updateCalculations();
        });

        function updateCalculations() {
            const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
            const hra = parseFloat(document.getElementById('hra').value) || 0;
            const da = parseFloat(document.getElementById('da').value) || 0;
            const ta = parseFloat(document.getElementById('ta_amount').value) || 0;
            const daOnTa = parseFloat(document.getElementById('da_ta').value) || 0;
            const canteen = parseFloat(document.getElementById('canteen_subsidy').value) || 0;
            const bonus = parseFloat(document.getElementById('bonus').value) || 0;
            const tax = parseFloat(document.getElementById('tax_deduction').value) || 0;
            const epf = parseFloat(document.getElementById('pf_deduction').value) || 0;
            const nps = parseFloat(document.getElementById('nps_deduction').value) || 0;
            const cpf = parseFloat(document.getElementById('cpf_deduction').value) || 0;
            const pt = parseFloat(document.getElementById('professional_tax').value) || 0;
            const sudexo = parseFloat(document.getElementById('sudexo_deduction').value) || 0;
            const incomeTax = parseFloat(document.getElementById('income_tax').value) || 0;
            const other = parseFloat(document.getElementById('other_deductions').value) || 0;

            const gross = basic + hra + da + ta + daOnTa + canteen + bonus;
            const totalDeductions = tax + epf + nps + cpf + pt + sudexo + incomeTax + other;
            const net = gross - totalDeductions;

            // Format currency
            const formatCurrency = (amount) => '₹' + amount.toLocaleString('en-IN', {
                minimumFractionDigits: 2, 
                maximumFractionDigits: 2
            });

            document.getElementById('display_basic').textContent = formatCurrency(basic);
            document.getElementById('display_hra').textContent = formatCurrency(hra);
            document.getElementById('display_da').textContent = formatCurrency(da);
            document.getElementById('display_ta').textContent = formatCurrency(ta);
            document.getElementById('display_da_ta').textContent = formatCurrency(daOnTa);
            document.getElementById('display_canteen').textContent = formatCurrency(canteen);
            document.getElementById('display_bonus').textContent = formatCurrency(bonus);
            document.getElementById('display_gross').textContent = formatCurrency(gross);
            document.getElementById('display_tax').textContent = '-' + formatCurrency(tax);
            document.getElementById('display_pf').textContent = '-' + formatCurrency(epf);
            document.getElementById('display_nps').textContent = '-' + formatCurrency(nps);
            document.getElementById('display_cpf').textContent = '-' + formatCurrency(cpf);
            document.getElementById('display_pt').textContent = '-' + formatCurrency(pt);
            document.getElementById('display_sudexo').textContent = '-' + formatCurrency(sudexo);
            document.getElementById('display_incometax').textContent = '-' + formatCurrency(incomeTax);
            document.getElementById('display_other').textContent = '-' + formatCurrency(other);
            document.getElementById('display_total_ded').textContent = '-' + formatCurrency(totalDeductions);
            document.getElementById('display_net').textContent = formatCurrency(net);
        }

        // Form validation
        document.getElementById('payslipForm').addEventListener('submit', function(e) {
            const employeeId = document.getElementById('employee_id').value;
            const basicSalary = parseFloat(document.getElementById('basic_salary').value);

            if (!employeeId) {
                e.preventDefault();
                alert('Please select an employee');
                return false;
            }

            if (!basicSalary || basicSalary <= 0) {
                e.preventDefault();
                alert('Basic salary must be greater than 0');
                return false;
            }
        });
    </script>
</body>
</html>
