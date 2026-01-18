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
    <?php include '../admin/includes/admin_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .payslip-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            margin-bottom: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 15px;
        }

        .form-group label {
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 8px;
            font-size: 14px;
        }

        .form-group input,
        .form-group select {
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            color: #2d3748;
            transition: all 0.2s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .section-header {
            background: #f7fafc;
            color: #2d3748;
            padding: 12px 15px;
            border-left: 4px solid #667eea;
            border-radius: 4px;
            margin: 25px 0 20px 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .calculation-summary {
            background: #f8fafc;
            padding: 25px;
            border-radius: 12px;
            border: 1px solid #edf2f7;
            margin-top: 20px;
        }

        .calc-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
        }

        .calc-row.total {
            font-weight: 700;
            font-size: 18px;
            color: #0f766e;
            border-bottom: none;
            border-top: 2px solid #e2e8f0;
            margin-top: 15px;
            padding-top: 15px;
        }

        .btn-generate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .success-banner {
            background: #f0fff4;
            color: #2f855a;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #9ae6b4;
        }

        .error-banner {
            background: #fff5f5;
            color: #c53030;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border: 1px solid #feb2b2;
        }
        
        .recent-list-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            margin-bottom: 10px;
            transition: transform 0.2s;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recent-list-item:hover { transform: translateX(5px); border-color: #cbd5e0; }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>
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
            <h2 style="margin-bottom: 20px; font-size: 20px; color: #2d3748;"><i class="fas fa-plus-circle"></i> New Payslip Entry</h2>
            
            <form method="POST" id="payslipForm">
                <input type="hidden" name="generate_payslip" value="1">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="employee_id"><i class="fas fa-user"></i> Select Employee <span style="color:red">*</span></label>
                        <select id="employee_id" name="employee_id" required>
                            <option value="">-- Choose Employee --</option>
                            <?php foreach($employees as $emp): ?>
                                <option value="<?php echo $emp['employee_id']; ?>" 
                                        data-salary="<?php echo $emp['basic_salary']; ?>"
                                        data-designation="<?php echo htmlspecialchars($emp['designation']); ?>"
                                        data-department="<?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?>"
                                        data-email="<?php echo htmlspecialchars($emp['email']); ?>"
                                        data-employment-type="<?php echo $emp['employment_type']; ?>">
                                    <?php echo htmlspecialchars($emp['full_name']); ?> - <?php echo htmlspecialchars($emp['designation']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Employee Details</label>
                        <div id="employee_info" style="background: #f8fafc; padding: 10px; border-radius: 6px; font-size: 13px; color: #4a5568; border: 1px solid #edf2f7; min-height: 46px;">
                            <span id="emp_designation">Select an employee...</span>
                        </div>
                    </div>
                </div>

                <div class="form-grid" style="grid-template-columns: 1fr 1fr;">
                    <div class="form-group">
                        <label for="month">Month</label>
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
                        <label for="year">Year</label>
                        <input type="number" id="year" name="year" value="<?php echo date('Y'); ?>" min="2020" max="2030" required>
                    </div>
                </div>

                <div class="section-header">
                    <i class="fas fa-coins"></i> Earnings
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Basic Salary <span style="color:red">*</span></label>
                        <input type="number" id="basic_salary" name="basic_salary" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label>HRA (20%)</label>
                        <input type="number" id="hra" name="hra" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>DA (58%)</label>
                        <input type="number" id="da" name="da" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Transport Allowance (TA)</label>
                        <select id="ta_amount" name="ta_amount">
                            <option value="3600">₹3,600</option>
                            <option value="1800">₹1,800</option>
                            <option value="900">₹900</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>DA on TA</label>
                        <input type="number" id="da_ta" name="da_ta" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Bonus</label>
                        <input type="number" id="bonus" name="bonus" step="0.01" value="0">
                    </div>
                </div>

                <div class="section-header" style="border-left-color: #ef4444;">
                    <i class="fas fa-minus-circle"></i> Deductions
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Tax (TDS)</label>
                        <input type="number" id="tax_deduction" name="tax_deduction" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>EPF (12%)</label>
                        <input type="number" id="pf_deduction" name="pf_deduction" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>NPS (10%)</label>
                        <input type="number" id="nps_deduction" name="nps_deduction" step="0.01" value="0">
                    </div>
                    <div class="form-group">
                        <label>Professional Tax</label>
                        <input type="number" id="professional_tax" name="professional_tax" step="0.01" value="<?php echo $standardRates['professional_tax']; ?>">
                    </div>
                    <div class="form-group">
                        <label>Other Deductions</label>
                        <input type="number" id="other_deductions" name="other_deductions" step="0.01" value="0">
                    </div>
                </div>

                <div class="calculation-summary">
                    <h3 style="margin-bottom: 15px; font-size: 16px;">Summary</h3>
                    <div class="calc-row"><span>Basic:</span><span id="display_basic">₹0.00</span></div>
                    <div class="calc-row"><span>HRA:</span><span id="display_hra">₹0.00</span></div>
                    <div class="calc-row"><span>DA:</span><span id="display_da">₹0.00</span></div>
                    <div class="calc-row"><span>TA + DA on TA:</span><span id="display_ta_total">₹0.00</span></div>
                    <div class="calc-row" style="font-weight: 600;"><span>Gross Salary:</span><span id="display_gross">₹0.00</span></div>
                    <div class="calc-row" style="color: #e53e3e;"><span>Total Deductions:</span><span id="display_deductions">-₹0.00</span></div>
                    <div class="calc-row total"><span>NET SALARY:</span><span id="display_net">₹0.00</span></div>
                </div>

                <div style="margin-top: 25px;">
                    <button type="submit" class="btn-generate"><i class="fas fa-check"></i> Generate Payslip</button>
                </div>
            </form>
        </div>

        <!-- Recent Payslips -->
        <h3 style="font-size: 18px; color: #2d3748; margin-bottom: 15px;">Recent Payslips</h3>
        <?php foreach($recentPayslips as $payslip): ?>
            <div class="recent-list-item">
                <div>
                    <div style="font-weight: 600; font-size: 15px; color: #2d3748;"><?= htmlspecialchars($payslip['full_name']) ?></div>
                    <div style="font-size: 13px; color: #718096;"><?= $payslip['month'] . ' ' . $payslip['year'] ?> • <?= htmlspecialchars($payslip['designation']) ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-weight: 700; color: #2d3748;">₹<?= number_format($payslip['net_salary']) ?></div>
                    <a href="generate_payslip_pdf.php?payslip_id=<?= $payslip['payslip_id'] ?>" target="_blank" style="font-size: 12px; color: #667eea; font-weight: 600;">Download PDF</a>
                </div>
            </div>
        <?php endforeach; ?>

    </main>

    <script>
        // Standard percentage rates
        const rates = {
            hra: 20,         // HRA = 20% of Basic
            da: 58,          // DA = 58% of Basic
            daOnTa: 58,      // DA on TA = 58% of TA
            tax: 10,         // Tax = 10% of Gross
            epf: 12,         // EPF = 12% of Basic
            nps: 10          // NPS = 10% of Basic
        };

        const employeeSelect = document.getElementById('employee_id');
        const basicInput = document.getElementById('basic_salary');
        const hraInput = document.getElementById('hra');
        const daInput = document.getElementById('da');
        const taSelect = document.getElementById('ta_amount');
        const daTaInput = document.getElementById('da_ta');
        const bonusInput = document.getElementById('bonus');
        
        const taxInput = document.getElementById('tax_deduction');
        const pfInput = document.getElementById('pf_deduction');
        const npsInput = document.getElementById('nps_deduction');
        const ptInput = document.getElementById('professional_tax');
        const otherInput = document.getElementById('other_deductions');

        function updateCalculations() {
            const basic = parseFloat(basicInput.value) || 0;
            const hra = parseFloat(hraInput.value) || 0;
            const da = parseFloat(daInput.value) || 0;
            const ta = parseFloat(taSelect.value) || 0;
            const daTa = parseFloat(daTaInput.value) || 0;
            const bonus = parseFloat(bonusInput.value) || 0;

            const gross = basic + hra + da + ta + daTa + bonus;

            const tax = parseFloat(taxInput.value) || 0;
            const pf = parseFloat(pfInput.value) || 0;
            const nps = parseFloat(npsInput.value) || 0;
            const pt = parseFloat(ptInput.value) || 0;
            const other = parseFloat(otherInput.value) || 0;

            const deductions = tax + pf + nps + pt + other;
            const net = gross - deductions;

            // Display
            document.getElementById('display_basic').textContent = '₹' + basic.toFixed(2);
            document.getElementById('display_hra').textContent = '₹' + hra.toFixed(2);
            document.getElementById('display_da').textContent = '₹' + da.toFixed(2);
            document.getElementById('display_ta_total').textContent = '₹' + (ta + daTa).toFixed(2);
            document.getElementById('display_gross').textContent = '₹' + gross.toFixed(2);
            document.getElementById('display_deductions').textContent = '-₹' + deductions.toFixed(2);
            document.getElementById('display_net').textContent = '₹' + net.toFixed(2);
        }

        // Auto-fill and Calc Logic
        employeeSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (!opt.value) return;

            const salary = parseFloat(opt.dataset.salary) || 0;
            const type = opt.dataset.employmentType || 'permanent';

            // Display Info
            document.getElementById('emp_designation').innerHTML = `
                <strong>${opt.dataset.designation}</strong> • ${opt.dataset.department} <br> 
                <span style="font-size:11px; color: #718096">${opt.dataset.email} • ${type.toUpperCase()}</span>
            `;

            basicInput.value = salary.toFixed(2);

            if (type === 'permanent') {
                hraInput.value = (salary * rates.hra / 100).toFixed(2);
                daInput.value = (salary * rates.da / 100).toFixed(2);
                
                // Assuming standard TA logic (simplified)
                const taVal = parseFloat(taSelect.value);
                daTaInput.value = (taVal * rates.daOnTa / 100).toFixed(2);

                pfInput.value = (salary * rates.epf / 100).toFixed(2);
                npsInput.value = (salary * rates.nps / 100).toFixed(2);
                
                // Gross for tax calc (approx)
                const tempGross = salary + parseFloat(hraInput.value) + parseFloat(daInput.value) + taVal + parseFloat(daTaInput.value);
                taxInput.value = (tempGross * rates.tax / 100).toFixed(2);
            } else {
                // Interns/Contracts get flat salary usually
                hraInput.value = 0;
                daInput.value = 0;
                daTaInput.value = 0;
                pfInput.value = 0;
                npsInput.value = 0;
                taxInput.value = 0;
            }
            
            updateCalculations();
        });

        [basicInput, hraInput, daInput, taSelect, daTaInput, bonusInput, taxInput, pfInput, npsInput, ptInput, otherInput].forEach(el => {
            el.addEventListener('input', updateCalculations);
            el.addEventListener('change', updateCalculations);
        });
    </script>
</body>
</html>
