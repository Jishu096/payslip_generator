<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'accountant') {
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
        e.full_name,
        e.designation,
        e.department_id,
        d.department_name,
        e.basic_salary,
        e.employment_type,
        e.email,
        e.phone
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
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
    $taxDeduction = (float)($_POST['tax_deduction'] ?? 0);
    $pfDeduction = (float)($_POST['pf_deduction'] ?? 0);
    $npsDeduction = (float)($_POST['nps_deduction'] ?? 0);
    $professionalTax = (float)($_POST['professional_tax'] ?? 0);
    $otherDeductions = (float)($_POST['other_deductions'] ?? 0);
    
    // Calculate totals using new components
    $grossSalary = $basicSalary + $hra + $da + $taAmount + $daTa + $bonus;
    $totalDeductions = $taxDeduction + $pfDeduction + $npsDeduction + $professionalTax + $otherDeductions;
    $netSalary = $grossSalary - $totalDeductions;
    
    // Insert payroll record first
    try {
        $db->beginTransaction();
        
        // Insert into payroll table with new components
        $payrollStmt = $db->prepare("
            INSERT INTO payroll 
            (employee_id, month, year, basic, da_amount, hra_amount, ta_amount, da_on_ta, bonus,
             gross_salary, tax_deduction, pf_deduction, nps_deduction, professional_tax, other_deductions,
             total_deductions, net_salary, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
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
            $grossSalary,
            $taxDeduction,
            $pfDeduction,
            $npsDeduction,
            $professionalTax,
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
    <style>
        .payslip-form {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
            margin-bottom: 30px;
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
            color: #333;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.2);
        }

        .section-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: 30px 0 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calculation-summary {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
            margin-top: 20px;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #ddd;
        }

        .calc-row.total {
            font-weight: 700;
            font-size: 18px;
            color: #28a745;
            border-bottom: none;
            border-top: 2px solid #333;
            margin-top: 10px;
        }

        .btn-generate {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border: none;
            padding: 15px 30px;
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
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }

        .recent-payslips {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px;
        }

        .payslip-card {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .payslip-card:hover {
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .payslip-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 10px;
        }

        .payslip-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
            font-size: 13px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            color: #666;
            font-size: 11px;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .info-value {
            color: #333;
            font-weight: 600;
        }

        .status-badge {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-generated {
            background: #d4edda;
            color: #155724;
        }

        .status-sent {
            background: #cce5ff;
            color: #004085;
        }

        .success-banner {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .required {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-file-invoice-dollar"></i> Generate Payslip</h1>
            <p>Create and manage employee payslips</p>
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
                                        data-email="<?php echo htmlspecialchars($emp['email']); ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?> - <?php echo htmlspecialchars($emp['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; display: block; margin-top: 5px;">
                            <i class="fas fa-info-circle"></i> All components auto-calc using current government rates; you can edit any value.
                        </small>
                    </div>

                    <div class="form-group">
                        <label for="employee_info"><i class="fas fa-id-card"></i> Employee Details</label>
                        <div id="employee_info" style="background: #f8f9fa; padding: 12px; border-radius: 6px; font-size: 13px; color: #555;">
                            <div id="emp_designation">Designation: -</div>
                            <div id="emp_department">Department: -</div>
                            <div id="emp_email">Email: -</div>
                        </div>
                    </div>
                </div>

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
                            <span style="color: #28a745; font-size: 11px;">Govt slabs</span>
                        </label>
                        <select id="ta_amount" name="ta_amount">
                            <option value="3600">₹3,600</option>
                            <option value="1800">₹1,800</option>
                            <option value="900">₹900</option>
                        </select>
                        <small style="color: #666;">Change if employee TA slab differs</small>
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
                        <label for="bonus"><i class="fas fa-gift"></i> Bonus</label>
                        <input type="number" id="bonus" name="bonus" step="0.01" value="0" min="0">
                        <small style="color: #666;">Optional, enter manually</small>
                    </div>
                </div>

                <div class="section-header">
                    <i class="fas fa-minus-circle"></i> Deductions
                    <small style="font-size: 12px; font-weight: 400; opacity: 0.9; margin-left: 15px;">
                        Auto-calculated (govt. rates) — you can edit any value
                    </small>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="tax_deduction">
                            <i class="fas fa-receipt"></i> Tax Deduction (TDS)
                            <span style="color: #dc3545; font-size: 11px;">10% of Gross</span>
                        </label>
                        <input type="number" id="tax_deduction" name="tax_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="pf_deduction">
                            <i class="fas fa-piggy-bank"></i> EPF
                            <span style="color: #dc3545; font-size: 11px;">12% of Basic</span>
                        </label>
                        <input type="number" id="pf_deduction" name="pf_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="nps_deduction">
                            <i class="fas fa-university"></i> NPS
                            <span style="color: #dc3545; font-size: 11px;">10% of Basic</span>
                        </label>
                        <input type="number" id="nps_deduction" name="nps_deduction" step="0.01" value="0" min="0">
                        <small style="color: #666;">Auto-calculated, editable</small>
                    </div>

                    <div class="form-group">
                        <label for="professional_tax">
                            <i class="fas fa-id-badge"></i> Professional Tax
                            <span style="color: #dc3545; font-size: 11px;">Flat (state)</span>
                        </label>
                        <input type="number" id="professional_tax" name="professional_tax" step="0.01" value="<?php echo $standardRates['professional_tax']; ?>" min="0">
                        <small style="color: #666;">Edit if different state rate</small>
                    </div>

                    <div class="form-group">
                        <label for="other_deductions"><i class="fas fa-hand-holding-usd"></i> Other Deductions</label>
                        <input type="number" id="other_deductions" name="other_deductions" step="0.01" value="0" min="0">
                        <small style="color: #666;">Insurance, loans, etc.</small>
                    </div>
                </div>

                <div class="calculation-summary">
                    <h3 style="margin-bottom: 15px;"><i class="fas fa-calculator"></i> Salary Summary</h3>
                    
                    <div class="calc-row">
                        <span>Basic Salary:</span>
                        <span id="display_basic">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>HRA (20%):</span>
                        <span id="display_hra">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>DA (58% of Basic):</span>
                        <span id="display_da">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>TA (Slab):</span>
                        <span id="display_ta">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>DA on TA (58%):</span>
                        <span id="display_da_ta">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span>Bonus:</span>
                        <span id="display_bonus">₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span><strong>Gross Salary:</strong></span>
                        <span id="display_gross"><strong>₹0.00</strong></span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Tax Deduction (10%):</span>
                        <span id="display_tax" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">EPF (12% of Basic):</span>
                        <span id="display_pf" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">NPS (10% of Basic):</span>
                        <span id="display_nps" style="color: #dc3545;">-₹0.00</span>
                    </div>
                    <div class="calc-row">
                        <span style="color: #dc3545;">Professional Tax:</span>
                        <span id="display_pt" style="color: #dc3545;">-₹0.00</span>
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
        // Standard percentage rates (kept in JS to mirror PHP config)
        const rates = {
            hra: 20,         // HRA = 20% of Basic
            da: 58,          // DA = 58% of Basic
            daOnTa: 58,      // DA on TA = 58% of TA
            tax: 10,         // Tax = 10% of Gross
            epf: 12,         // EPF = 12% of Basic
            nps: 10          // NPS = 10% of Basic
        };

        // Auto-fill salary components when employee is selected
        const employeeSelect = document.getElementById('employee_id');
        const basicSalaryInput = document.getElementById('basic_salary');
        const hraInput = document.getElementById('hra');
        const daInput = document.getElementById('da');
        const taSelect = document.getElementById('ta_amount');
        const daTaInput = document.getElementById('da_ta');
        const pfInput = document.getElementById('pf_deduction');
        const npsInput = document.getElementById('nps_deduction');
        const professionalTaxInput = document.getElementById('professional_tax');
        
        employeeSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            
            if (!selectedOption.value) {
                // Clear all fields if no employee selected
                basicSalaryInput.value = '';
                hraInput.value = 0;
                daInput.value = 0;
                taSelect.value = '3600';
                daTaInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                document.getElementById('tax_deduction').value = 0;
                professionalTaxInput.value = <?php echo $standardRates['professional_tax']; ?>;
                document.getElementById('other_deductions').value = 0;
                document.getElementById('bonus').value = 0;
                document.getElementById('emp_designation').textContent = 'Designation: -';
                document.getElementById('emp_department').textContent = 'Department: -';
                document.getElementById('emp_email').textContent = 'Email: -';
                updateCalculations();
                return;
            }
            
            const salary = parseFloat(selectedOption.dataset.salary) || 0;
            const designation = selectedOption.dataset.designation || '-';
            const department = selectedOption.dataset.department || '-';
            const email = selectedOption.dataset.email || '-';
            
            // Update employee info display
            document.getElementById('emp_designation').innerHTML = '<i class="fas fa-briefcase"></i> Designation: <strong>' + designation + '</strong>';
            document.getElementById('emp_department').innerHTML = '<i class="fas fa-building"></i> Department: <strong>' + department + '</strong>';
            document.getElementById('emp_email').innerHTML = '<i class="fas fa-envelope"></i> Email: <strong>' + email + '</strong>';
            
            // Set basic salary
            basicSalaryInput.value = salary.toFixed(2);

            // Transport Allowance default (highest slab); user can change
            const taVal = parseFloat(taSelect.value) || 0;

            // Auto-calculate components based on rules
            hraInput.value = (salary * rates.hra / 100).toFixed(2);
            daInput.value = (salary * rates.da / 100).toFixed(2);
            daTaInput.value = (taVal * rates.daOnTa / 100).toFixed(2);
            pfInput.value = (salary * rates.epf / 100).toFixed(2);
            npsInput.value = (salary * rates.nps / 100).toFixed(2);

            // Calculate gross to get tax
            const gross = salary +
                         parseFloat(hraInput.value) +
                         parseFloat(daInput.value) +
                         taVal +
                         parseFloat(daTaInput.value);

            document.getElementById('tax_deduction').value = (gross * rates.tax / 100).toFixed(2);
            professionalTaxInput.value = <?php echo $standardRates['professional_tax']; ?>;

            updateCalculations();
        });

        // Update calculations in real-time
        const numericInputs = [
            'basic_salary', 'hra', 'da', 'da_ta', 'bonus', 'tax_deduction',
            'pf_deduction', 'nps_deduction', 'professional_tax', 'other_deductions'
        ];
        
        numericInputs.forEach(inputId => {
            const input = document.getElementById(inputId);
            if (input) {
                input.addEventListener('input', updateCalculations);
            }
        });

        // TA select also triggers recalculation (DA on TA depends on this)
        taSelect.addEventListener('change', () => {
            const taVal = parseFloat(taSelect.value) || 0;
            const daTaVal = taVal * rates.daOnTa / 100;
            daTaInput.value = daTaVal.toFixed(2);
            const basic = parseFloat(basicSalaryInput.value) || 0;
            const hra = parseFloat(hraInput.value) || 0;
            const da = parseFloat(daInput.value) || 0;
            const bonus = parseFloat(document.getElementById('bonus').value) || 0;
            const gross = basic + hra + da + taVal + daTaVal + bonus;
            document.getElementById('tax_deduction').value = (gross * rates.tax / 100).toFixed(2);
            updateCalculations();
        });

        function updateCalculations() {
            const basic = parseFloat(document.getElementById('basic_salary').value) || 0;
            const hra = parseFloat(document.getElementById('hra').value) || 0;
            const da = parseFloat(document.getElementById('da').value) || 0;
            const ta = parseFloat(document.getElementById('ta_amount').value) || 0;
            const daOnTa = parseFloat(document.getElementById('da_ta').value) || 0;
            const bonus = parseFloat(document.getElementById('bonus').value) || 0;
            const tax = parseFloat(document.getElementById('tax_deduction').value) || 0;
            const epf = parseFloat(document.getElementById('pf_deduction').value) || 0;
            const nps = parseFloat(document.getElementById('nps_deduction').value) || 0;
            const pt = parseFloat(document.getElementById('professional_tax').value) || 0;
            const other = parseFloat(document.getElementById('other_deductions').value) || 0;

            const gross = basic + hra + da + ta + daOnTa + bonus;
            const totalDeductions = tax + epf + nps + pt + other;
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
            document.getElementById('display_bonus').textContent = formatCurrency(bonus);
            document.getElementById('display_gross').textContent = formatCurrency(gross);
            document.getElementById('display_tax').textContent = '-' + formatCurrency(tax);
            document.getElementById('display_pf').textContent = '-' + formatCurrency(epf);
            document.getElementById('display_nps').textContent = '-' + formatCurrency(nps);
            document.getElementById('display_pt').textContent = '-' + formatCurrency(pt);
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
