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
require_once __DIR__ . '/../../app/Helpers/NotificationHelper.php';
$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';
$notificationHelper = new NotificationHelper($db);

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
        e.pay_level_id,
        e.hra_type,
        e.email,
        e.phone,
        pl.level_name,
        pl.transport_allowance
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    LEFT JOIN pay_levels pl ON e.pay_level_id = pl.level_id
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
    $bonus = (float)($_POST['bonus'] ?? 0);
    $canteenSubsidy = (float)($_POST['canteen_subsidy'] ?? 0);
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
    $grossSalary = $basicSalary + $hra + $da + $taAmount + $daTa + $bonus + $canteenSubsidy;
    $totalDeductions = $taxDeduction + $pfDeduction + $npsDeduction + $cpfDeduction + $professionalTax + $sudexoDeduction + $incomeTax + $otherDeductions;
    $netSalary = $grossSalary - $totalDeductions;
    
    // Insert payroll record first
    try {
        $db->beginTransaction();
        
        // Insert into payroll table with 7th CPC components
        $payrollStmt = $db->prepare("
            INSERT INTO payroll 
            (employee_id, month, year, basic, da_amount, hra_amount, ta_amount, da_on_ta, bonus,
             canteen_subsidy, gross_salary, tax_deduction, pf_deduction, nps_deduction, cpf_deduction,
             professional_tax, sudexo_deduction, income_tax, pay_level, other_deductions,
             total_deductions, net_salary, created_at)
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
            $bonus,
            $canteenSubsidy,
            $grossSalary,
            $taxDeduction,
            $pfDeduction,
            $npsDeduction,
            $cpfDeduction,
            $professionalTax,
            $sudexoDeduction,
            $incomeTax,
            $payLevel,
            $otherDeductions,
            $totalDeductions,
            $netSalary
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
        
        // Send payslip notification email to employee
        $empStmt = $db->prepare("SELECT full_name, email FROM employees WHERE employee_id = ?");
        $empStmt->execute([$employeeId]);
        $empData = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($empData && !empty($empData['email'])) {
            $notificationHelper->notifyPayslipGeneration(
                $empData['email'],
                $empData['full_name'],
                $month . ' ' . $year
            );
        }
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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        .payslip-form {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            padding: 30px;
            margin-bottom: 30px;
        }

        .payslip-form h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .payslip-form h2 i {
            color: var(--accent);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
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
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .form-group label i {
            color: var(--accent);
            font-size: 13px;
        }

        .form-group input,
        .form-group select {
            padding: 12px 15px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s ease;
            background: white;
            color: var(--text);
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .section-header {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin: 30px 0 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 15px;
        }

        .calculation-summary {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            padding: 25px;
            border-radius: 12px;
            border: 1px solid rgba(102, 126, 234, 0.2);
            margin-top: 25px;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .calc-row .label {
            color: var(--muted);
            font-weight: 500;
        }

        .calc-row .value {
            color: var(--text);
            font-weight: 600;
        }

        .calc-row.total {
            font-weight: 700;
            font-size: 18px;
            border-bottom: none;
            border-top: 2px solid var(--accent);
            margin-top: 10px;
            padding-top: 15px;
        }

        .calc-row.total .label {
            color: var(--text);
        }

        .calc-row.total .value {
            color: var(--success);
            font-size: 22px;
        }

        .btn-generate {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 15px 35px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }

        .recent-payslips {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
            border: 1px solid rgba(102, 126, 234, 0.1);
            padding: 30px;
        }

        .recent-payslips h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-payslips h2 i {
            color: var(--accent);
        }

        .payslip-card {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid var(--accent);
            transition: all 0.3s ease;
            border: 1px solid rgba(102, 126, 234, 0.1);
        }

        .payslip-card:hover {
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
            transform: translateY(-2px);
        }

        .payslip-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .payslip-header h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
        }

        .payslip-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            font-size: 13px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            color: var(--muted);
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 4px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .info-value {
            color: var(--text);
            font-weight: 600;
            font-size: 14px;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-generated {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .status-sent {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .success-banner {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .success-banner i {
            font-size: 20px;
        }

        .error-banner {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .error-banner i {
            font-size: 20px;
        }

        .required {
            color: var(--danger);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .payslip-info {
                grid-template-columns: 1fr;
            }
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
            <div class="success-banner">
                <i class="fas fa-check-circle"></i> Payslip generated successfully!
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-banner">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Payslip Generation Form -->
        <div class="payslip-form">
            <h2><i class="fas fa-plus-circle"></i> New Payslip</h2>
            
            <form method="POST" id="payslipForm">
                <input type="hidden" name="generate_payslip" value="1">
                
                <div class="form-grid">
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
                                        data-pay-level="<?php echo htmlspecialchars($emp['level_name'] ?? ''); ?>"
                                        data-hra-type="<?php echo $emp['hra_type'] ?? 'city_b'; ?>"
                                        data-transport-allowance="<?php echo $emp['transport_allowance'] ?? 0; ?>">
                                    <?php echo htmlspecialchars($emp['employee_code'] ?? 'EMP' . str_pad($emp['employee_id'], 3, '0', STR_PAD_LEFT)); ?> - <?php echo htmlspecialchars($emp['full_name']); ?> (<?php echo htmlspecialchars($emp['designation']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> 7th CPC components auto-calc based on Pay Level; you can edit any value.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="employee_info"><i class="fas fa-id-card"></i> Employee Details</label>
                        <div id="employee_info" style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 13px; color: #555;">
                            <div id="emp_designation">Designation: -</div>
                            <div id="emp_department">Department: -</div>
                            <div id="emp_email">Email: -</div>
                            <div id="emp_pay_level" style="color: #667eea; font-weight: 600;">Pay Level: -</div>
                        </div>
                    </div>
                </div>

                <input type="hidden" name="pay_level" id="pay_level" value="">

                <div class="form-grid" style="grid-template-columns: repeat(2, 1fr);">
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

                <div class="section-header">
                    <i class="fas fa-dollar-sign"></i> Earnings
                    <small style="font-size: 12px; font-weight: 400; opacity: 0.9; margin-left: 15px;">
                        Auto-calculated (govt. rates) — you can edit any value
                    </small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="basic_salary">
                            <i class="fas fa-money-bill-wave"></i> Basic Salary <span class="required">*</span>
                        </label>
                        <input type="number" id="basic_salary" name="basic_salary" step="0.01" required>
                        <small style="color: #666;">From employee record</small>
                    </div>

                    <div class="form-group">
                        <label for="hra">
                            <i class="fas fa-home"></i> HRA (House Rent Allowance)
                            <span style="color: #28a745; font-size: 11px;">20% of Basic</span>
                        </label>
                        <input type="number" id="hra" name="hra" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="da">
                            <i class="fas fa-coins"></i> DA (Dearness Allowance)
                            <span style="color: #28a745; font-size: 11px;">58% of Basic</span>
                        </label>
                        <input type="number" id="da" name="da" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="ta_amount">
                            <i class="fas fa-bus"></i> Transport Allowance (TA)
                            <span style="color: #28a745; font-size: 11px;">Per Pay Level</span>
                        </label>
                        <input type="number" id="ta_amount" name="ta_amount" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-set from Pay Level, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="da_ta">
                            <i class="fas fa-percentage"></i> DA on TA
                            <span style="color: #28a745; font-size: 11px;">58% of TA</span>
                        </label>
                        <input type="number" id="da_ta" name="da_ta" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="canteen_subsidy">
                            <i class="fas fa-utensils"></i> Canteen Subsidy
                        </label>
                        <input type="number" id="canteen_subsidy" name="canteen_subsidy" step="0.01" value="0" min="0">
                        <small style="color: #666;">Enter if applicable</small>
                    </div>

                    <div class="form-group">
                        <label for="bonus"><i class="fas fa-gift"></i> Bonus</label>
                        <input type="number" id="bonus" name="bonus" step="0.01" value="0" min="0">
                        <small style="color: #666;">Optional, enter manually</small>
                    </div>
                </div>

                <div class="section-header">
                    <i class="fas fa-minus-circle"></i> Deductions
                    <small style="font-size: 12px; font-weight: 400; opacity: 0.9; margin-left: 15px;">
                        7th CPC standard deductions — you can edit any value
                    </small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="pf_deduction">
                            <i class="fas fa-piggy-bank"></i> EPF (Employee Provident Fund)
                            <span style="color: #dc3545; font-size: 11px;">12% of Basic</span>
                        </label>
                        <input type="number" id="pf_deduction" name="pf_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="nps_deduction">
                            <i class="fas fa-university"></i> NPS (New Pension Scheme)
                            <span style="color: #dc3545; font-size: 11px;">10% of Basic</span>
                        </label>
                        <input type="number" id="nps_deduction" name="nps_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="cpf_deduction">
                            <i class="fas fa-landmark"></i> CPF (Central Provident Fund)
                        </label>
                        <input type="number" id="cpf_deduction" name="cpf_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Enter if applicable</small>
                    </div>

                    <div class="form-group">
                        <label for="professional_tax">
                            <i class="fas fa-id-badge"></i> Professional Tax
                            <span style="color: #dc3545; font-size: 11px;">Flat ₹200</span>
                        </label>
                        <input type="number" id="professional_tax" name="professional_tax" step="0.01" value="<?php echo $standardRates['professional_tax']; ?>" min="0">
                        <small style="color: #666;">Edit if different state rate</small>
                    </div>

                    <div class="form-group">
                        <label for="sudexo_deduction">
                            <i class="fas fa-credit-card"></i> Sudexo (Meal Card)
                        </label>
                        <input type="number" id="sudexo_deduction" name="sudexo_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Enter if applicable</small>
                    </div>

                    <div class="form-group">
                        <label for="income_tax">
                            <i class="fas fa-receipt"></i> Income Tax (TDS)
                        </label>
                        <input type="number" id="income_tax" name="income_tax" step="0.01" value="0" min="0">
                        <small style="color: #666;">Enter based on tax slab</small>
                    </div>

                    <div class="form-group">
                        <label for="tax_deduction">
                            <i class="fas fa-file-invoice-dollar"></i> Other Tax Deduction
                        </label>
                        <input type="number" id="tax_deduction" name="tax_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Enter if applicable</small>
                    </div>

                    <div class="form-group">
                        <label for="other_deductions"><i class="fas fa-hand-holding-usd"></i> Other Deductions</label>
                        <input type="number" id="other_deductions" name="other_deductions" step="0.01" value="0" min="0">
                        <small style="color: #666;">Insurance, loans, etc.</small>
                    </div>
                </div>

                <div class="calculation-summary">
                    <h3 style="margin-bottom: 15px;"><i class="fas fa-calculator"></i> Salary Summary (7th CPC)</h3>
                    
                    <div style="font-weight: 600; color: #10b981; margin-bottom: 8px; font-size: 12px; text-transform: uppercase;">Earnings</div>
                    <div class="calc-row">
                        <span>Basic Pay:</span>
                        <span id="display_basic">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>DA (58% of Basic):</span>
                        <span id="display_da">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>HRA:</span>
                        <span id="display_hra">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>Transport Allowance:</span>
                        <span id="display_ta">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>DA on TPA (58%):</span>
                        <span id="display_da_ta">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>Canteen Subsidy:</span>
                        <span id="display_canteen">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>Bonus:</span>
                        <span id="display_bonus">₹0.00</span>
                    </div>
                    <div class="calc-row" style="background: #e8f5e9; padding: 8px; border-radius: 4px; margin: 5px 0;">
                        <span><strong>Gross Salary:</strong></span>
                        <span id="display_gross"><strong>₹0.00</strong></span>
                    </div>
                    
                    <div style="font-weight: 600; color: #dc3545; margin: 12px 0 8px 0; font-size: 12px; text-transform: uppercase;">Deductions</div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">EPF (12% of Basic):</span>
                        <span id="display_pf" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">NPS (10% of Basic):</span>
                        <span id="display_nps" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">CPF:</span>
                        <span id="display_cpf" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Professional Tax:</span>
                        <span id="display_pt" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Sudexo:</span>
                        <span id="display_sudexo" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Income Tax:</span>
                        <span id="display_incometax" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Other (TDS etc.):</span>
                        <span id="display_tax" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Other Deductions:</span>
                        <span id="display_other" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row total">
                        <span>NET SALARY:</span>
                        <span id="display_net">₹0.00</span>
                    </div>
                </div>

                <div style="margin-top: 30px;">
                    <button type="submit" class="btn-generate">
                        <i class="fas fa-file-invoice"></i> Generate Payslip
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Payslips -->
        <div class="recent-payslips">
            <h2><i class="fas fa-history"></i> Recent Payslips</h2>
            
            <?php if (count($recentPayslips) > 0): ?>
                <?php foreach($recentPayslips as $payslip): ?>
                    <div class="payslip-card">
                        <div class="payslip-header">
                            <div>
                                <h3 style="margin: 0 0 5px 0;"><?php echo htmlspecialchars($payslip['full_name']); ?></h3>
                                <small style="color: #666;"><?php echo htmlspecialchars($payslip['designation']); ?> - <?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></small>
                            </div>
                            <div>
                                <span class="status-badge status-generated">
                                    Generated
                                </span>
                                <div style="margin-top: 8px;">
                                    <a href="generate_payslip_pdf.php?payslip_id=<?php echo $payslip['payslip_id']; ?>" target="_blank" style="color: #667eea; text-decoration: none; font-weight: 600; font-size: 12px;">
                                        <i class="fas fa-download"></i> Download PDF
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payslip-info">
                            <div class="info-item">
                                <span class="info-label">Period</span>
                                <span class="info-value"><?php echo $payslip['month']; ?> <?php echo $payslip['year']; ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Gross Salary</span>
                                <span class="info-value">₹<?php echo number_format($payslip['gross_salary'], 2); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Deductions</span>
                                <span class="info-value" style="color: #dc3545;">-₹<?php echo number_format($payslip['total_deductions'], 2); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Net Salary</span>
                                <span class="info-value" style="color: #28a745;">₹<?php echo number_format($payslip['net_salary'], 2); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Generated</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($payslip['generated_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
                    <p>No payslips generated yet</p>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // 7th CPC Standard percentage rates
        const rates = {
            hra_a: 24,       // HRA City A = 24% of Basic (Metro)
            hra_b: 16,       // HRA City B = 16% of Basic
            hra_c: 8,        // HRA City C = 8% of Basic  
            da: 58,          // DA = 58% of Basic
            daOnTa: 58,      // DA on TA = 58% of TA
            epf: 12,         // EPF = 12% of Basic
            nps: 10          // NPS = 10% of Basic
        };

        // Auto-fill salary components when employee is selected
        const employeeSelect = document.getElementById('employee_id');
        const basicSalaryInput = document.getElementById('basic_salary');
        const hraInput = document.getElementById('hra');
        const daInput = document.getElementById('da');
        const taInput = document.getElementById('ta_amount');
        const daTaInput = document.getElementById('da_ta');
        const pfInput = document.getElementById('pf_deduction');
        const npsInput = document.getElementById('nps_deduction');
        const cpfInput = document.getElementById('cpf_deduction');
        const professionalTaxInput = document.getElementById('professional_tax');
        const sudexoInput = document.getElementById('sudexo_deduction');
        const incomeTaxInput = document.getElementById('income_tax');
        const canteenInput = document.getElementById('canteen_subsidy');
        const payLevelInput = document.getElementById('pay_level');
        
        employeeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (!selectedOption.value) {
                // Clear all fields if no employee selected
                basicSalaryInput.value = '';
                hraInput.value = 0;
                daInput.value = 0;
                taInput.value = 0;
                daTaInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                cpfInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = <?php echo $standardRates['professional_tax']; ?>;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                canteenInput.value = 0;
                document.getElementById('other_deductions').value = 0;
                document.getElementById('bonus').value = 0;
                document.getElementById('emp_designation').textContent = 'Designation: -';
                document.getElementById('emp_department').textContent = 'Department: -';
                document.getElementById('emp_email').textContent = 'Email: -';
                document.getElementById('emp_pay_level').textContent = 'Pay Level: -';
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
                pfInput.value = 0;
                npsInput.value = 0;
                cpfInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = 0;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                canteenInput.value = 0;
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
                employmentNote.innerHTML = '<i class="fas fa-info-circle"></i> <strong>Intern:</strong> Fixed stipend of ₹10,000. No automatic allowances or deductions.';
                earningsHeader.parentNode.insertBefore(employmentNote, earningsHeader.nextSibling);
            } else if (employmentType === 'contract') {
                // For contract employees: Only basic salary, no allowances or deductions
                hraInput.value = 0;
                daInput.value = 0;
                taInput.value = 0;
                daTaInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                cpfInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = 0;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                canteenInput.value = 0;
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

                // Auto-calculate 7th CPC components based on rules
                hraInput.value = (salary * hraRate / 100).toFixed(2);
                daInput.value = (salary * rates.da / 100).toFixed(2);
                taInput.value = transportAllowance.toFixed(2);
                daTaInput.value = (transportAllowance * rates.daOnTa / 100).toFixed(2);
                pfInput.value = (salary * rates.epf / 100).toFixed(2);
                npsInput.value = (salary * rates.nps / 100).toFixed(2);
                cpfInput.value = 0;
                sudexoInput.value = 0;
                incomeTaxInput.value = 0;
                canteenInput.value = 0;
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
