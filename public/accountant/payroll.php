<?php
session_start();

// Check if user has accountant role (supports multi-role)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';
$accountantId = $_SESSION['user_id'] ?? null;

// Handle payroll processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'generate_payslips') {
        $month = $_POST['month'] ?? '';
        $year = $_POST['year'] ?? '';
        
        if ($month && $year) {
            try {
                // Get all payroll records for the month
                $stmt = $db->prepare("
                    SELECT payroll_id, employee_id 
                    FROM payroll 
                    WHERE month = ? AND year = ?
                ");
                $stmt->execute([$month, $year]);
                $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $successCount = 0;
                foreach ($payrolls as $payroll) {
                    // Check if payslip already exists
                    $stmt = $db->prepare("SELECT payslip_id FROM payslips WHERE payroll_id = ?");
                    $stmt->execute([$payroll['payroll_id']]);
                    
                    if (!$stmt->fetch()) {
                        // Create payslip record
                        $stmt = $db->prepare("
                            INSERT INTO payslips (payroll_id, employee_id, generated_at) 
                            VALUES (?, ?, NOW())
                        ");
                        $stmt->execute([$payroll['payroll_id'], $payroll['employee_id']]);
                        $successCount++;
                    }
                }
                
                $_SESSION['success_message'] = "Payslips generated successfully! {$successCount} payslips created.";
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error generating payslips: ' . $e->getMessage();
            }
        }
        
        header("Location: payroll.php?month={$month}&year={$year}");
        exit();
    }
    
    if ($action === 'send_to_director') {
        $month = $_POST['month'] ?? '';
        $year = $_POST['year'] ?? '';
        
        if ($month && $year) {
            try {
                // First, get the count of payrolls to send
                $countStmt = $db->prepare("
                    SELECT COUNT(*) 
                    FROM payroll 
                    WHERE month = ? AND year = ? 
                    AND approval_status NOT IN ('approved', 'rejected')
                ");
                $countStmt->execute([$month, $year]);
                $count = $countStmt->fetchColumn();
                
                if ($count > 0) {
                    // Update all payrolls (draft, pending, or null) to pending
                    $stmt = $db->prepare("
                        UPDATE payroll 
                        SET approval_status = 'pending'
                        WHERE month = ? AND year = ? 
                        AND approval_status NOT IN ('approved', 'rejected')
                    ");
                    $stmt->execute([$month, $year]);
                    
                    $_SESSION['success_message'] = "{$count} payroll record(s) sent to Director for approval.";
                } else {
                    $_SESSION['error_message'] = "No payroll records found for {$month} {$year}, or all records are already approved/rejected.";
                }
                
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error sending to Director: ' . $e->getMessage();
            }
        }
        
        header("Location: payroll.php?month={$month}&year={$year}");
        exit();
    }
    
    if ($action === 'calculate_payroll') {
        $month = $_POST['month'] ?? '';
        $year = $_POST['year'] ?? '';
        
        if ($month && $year) {
            try {
                $db->beginTransaction();
                
                // Get all employees with salary structure
                $stmt = $db->query("
                    SELECT 
                        e.employee_id,
                        e.full_name,
                        e.basic_salary,
                        d.department_name
                    FROM employees e
                    LEFT JOIN departments d ON e.department_id = d.department_id
                    WHERE e.basic_salary IS NOT NULL AND e.basic_salary > 0
                ");
                $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $successCount = 0;
                $skipCount = 0;
                
                foreach ($employees as $emp) {
                    // Check if payroll already exists
                    $stmt = $db->prepare("SELECT payroll_id FROM payroll WHERE employee_id = ? AND month = ? AND year = ?");
                    $stmt->execute([$emp['employee_id'], $month, $year]);
                    if ($stmt->fetch()) {
                        $skipCount++;
                        continue; // Skip if already exists
                    }
                    
                    $basic = $emp['basic_salary'];
                    
                    // Calculate allowances (standard rates)
                    $daPercent = 58; // 58% DA
                    $hraPercent = 20; // 20% HRA
                    
                    $da = round($basic * $daPercent / 100, 2);
                    $hra = round($basic * $hraPercent / 100, 2);
                    $ta = round($basic * 10 / 100, 2); // 10% TA
                    $daOnTa = round($ta * $daPercent / 100, 2);
                    $bonus = 0; // Set manually if needed
                    
                    $gross = $basic + $da + $hra + $ta + $daOnTa + $bonus;
                    
                    // Calculate deductions (standard rates)
                    $pfPercent = 12; // 12% PF
                    $npsPercent = 10; // 10% NPS
                    
                    $taxDeduction = round($gross * 8 / 100, 2); // 8% tax estimate
                    $pfDeduction = round($basic * $pfPercent / 100, 2);
                    $npsDeduction = round($basic * $npsPercent / 100, 2);
                    $professionalTax = 200; // Flat amount
                    $otherDeductions = 0;
                    
                    $totalDeductions = $taxDeduction + $pfDeduction + $npsDeduction + $professionalTax + $otherDeductions;
                    $netSalary = $gross - $totalDeductions;
                    
                    // Insert payroll record
                    $stmt = $db->prepare("
                        INSERT INTO payroll (
                            employee_id, month, year, 
                            basic, da_amount, hra_amount, ta_amount, da_on_ta, bonus,
                            gross_salary, 
                            tax_deduction, pf_deduction, nps_deduction, professional_tax, other_deductions,
                            total_deductions, net_salary,
                            approval_status, created_at
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
                    ");
                    
                    $stmt->execute([
                        $emp['employee_id'], $month, $year,
                        $basic, $da, $hra, $ta, $daOnTa, $bonus,
                        $gross,
                        $taxDeduction, $pfDeduction, $npsDeduction, $professionalTax, $otherDeductions,
                        $totalDeductions, $netSalary
                    ]);
                    
                    $successCount++;
                }
                
                $db->commit();
                
                $_SESSION['success_message'] = "Payroll calculated successfully! {$successCount} records created" . 
                    ($skipCount > 0 ? ", {$skipCount} skipped (already exists)" : "");
                
            } catch (Exception $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                $_SESSION['error_message'] = 'Error calculating payroll: ' . $e->getMessage();
            }
        }
    }
    
    header("Location: payroll.php");
    exit();
}

// Get current selection or defaults
$selectedMonth = $_GET['month'] ?? date('F');
$selectedYear = $_GET['year'] ?? date('Y');

// Get payroll summary for selected month
try {
    $stmt = $db->prepare("
        SELECT 
            p.payroll_id,
            p.employee_id,
            e.full_name,
            d.department_name,
            p.basic,
            p.gross_salary,
            p.total_deductions,
            p.net_salary,
            p.approval_status,
            p.created_at
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE p.month = ? AND p.year = ?
        ORDER BY e.full_name
    ");
    $stmt->execute([$selectedMonth, $selectedYear]);
    $payrollRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate totals
    $totalGross = array_sum(array_column($payrollRecords, 'gross_salary'));
    $totalDeductions = array_sum(array_column($payrollRecords, 'total_deductions'));
    $totalNet = array_sum(array_column($payrollRecords, 'net_salary'));
    $employeeCount = count($payrollRecords);
    
} catch (Exception $e) {
    $payrollRecords = [];
    $totalGross = 0;
    $totalDeductions = 0;
    $totalNet = 0;
    $employeeCount = 0;
}

// Check if attendance is verified for selected month
try {
    $stmt = $db->prepare("
        SELECT COUNT(DISTINCT employee_id) as verified_count
        FROM attendance
        WHERE MONTH(date) = MONTH(STR_TO_DATE(?, '%M'))
        AND YEAR(date) = ?
        AND status = 'verified'
    ");
    $monthNum = date('n', strtotime($selectedMonth));
    $stmt->execute([$selectedMonth, $selectedYear]);
    $verifiedCount = $stmt->fetchColumn() ?? 0;
    $attendanceVerified = $verifiedCount > 0;
} catch (Exception $e) {
    $attendanceVerified = false;
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Processing - Accountant</title>
    <?php include 'includes/accountant_styles.php'; ?>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-calculator"></i> Payroll Processing</h1>
                <p>Calculate and manage employee salaries</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Month Selector -->
        <div class="selector-card">
            <h2><i class="fas fa-calendar-alt"></i> Select Period</h2>
            <form method="GET" class="selector-form">
                <div class="form-group">
                    <label>Month</label>
                    <select name="month" onchange="this.form.submit()">
                        <?php foreach ($months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $selectedMonth === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Year</label>
                    <select name="year" onchange="this.form.submit()">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $selectedYear == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($attendanceVerified): ?>
                <div class="verification-badge success">
                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Generate payslips for all employees?');">
                        <input type="hidden" name="action" value="generate_payslips">
                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-file-pdf"></i> Generate Payslips
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Send payroll to Director for approval?');">
                        <input type="hidden" name="action" value="send_to_director">
                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                        <button type="submit" class="btn-success">
                            <i class="fas fa-paper-plane"></i> Send to Director
                        </button>
                    </form
            <?php endif; ?>
        </div>

        <!-- Payroll Summary -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: #667eea;">
                <div class="stat-icon" style="background: #e0e7ff; color: #667eea;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Employees</div>
                    <div class="stat-value"><?php echo $employeeCount; ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-icon" style="background: #d1fae5; color: #10b981;">
                    <i class="fas fa-plus-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Total Gross</div>
                    <div class="stat-value">₹<?php echo number_format($totalGross, 2); ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #ef4444;">
                <div class="stat-icon" style="background: #fee2e2; color: #ef4444;">
                    <i class="fas fa-minus-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Total Deductions</div>
                    <div class="stat-value">₹<?php echo number_format($totalDeductions, 2); ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Net Payout</div>
                    <div class="stat-value">₹<?php echo number_format($totalNet, 2); ?></div>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <?php if (empty($payrollRecords)): ?>
            <div class="action-card">
                <form method="POST" onsubmit="return confirm('Calculate payroll for <?php echo $selectedMonth . ' ' . $selectedYear; ?>?');">
                    <input type="hidden" name="action" value="calculate_payroll">
                    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                    <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-calculator"></i> Calculate Payroll for <?php echo $selectedMonth . ' ' . $selectedYear; ?>
                    </button>
                </form>
                <p style="margin-top: 10px; color: var(--muted); font-size: 14px;">
                    This will calculate salaries for all employees with verified attendance
                </p>
            </div>
        <?php else: ?>
            <div class="action-card">
                <div class="action-buttons">
                    <form method="POST" style="display: inline-block;" onsubmit="return confirm('Generate payslips for all employees in <?php echo $selectedMonth . ' ' . $selectedYear; ?>?')">
                        <input type="hidden" name="action" value="generate_payslips">
                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-file-pdf"></i> Generate Payslips
                        </button>
                    </form>
                    
                    <form method="POST" style="display: inline-block; margin-left: 15px;" onsubmit="return confirm('Send <?php echo $selectedMonth . ' ' . $selectedYear; ?> payroll to Director for approval?')">
                        <input type="hidden" name="action" value="send_to_director">
                        <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                        <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                        <button type="submit" class="btn-success">
                            <i class="fas fa-paper-plane"></i> Send to Director
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payroll Records -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Payroll Records - <?php echo $selectedMonth . ' ' . $selectedYear; ?></h2>
            </div>
            <div class="table-container">
                <?php if (empty($payrollRecords)): ?>
                    <div style="text-align: center; padding: 60px; color: var(--muted);">
                        <i class="fas fa-calculator" style="font-size: 56px; opacity: 0.5; display: block; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 20px; margin-bottom: 10px;">No Payroll Calculated</h3>
                        <p>Click "Calculate Payroll" to process salaries for this month</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Basic</th>
                                <th>Gross</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payrollRecords as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                    <td class="amount">₹<?php echo number_format($record['basic'], 2); ?></td>
                                    <td class="amount positive">₹<?php echo number_format($record['gross_salary'], 2); ?></td>
                                    <td class="amount negative">₹<?php echo number_format($record['total_deductions'], 2); ?></td>
                                    <td class="amount" style="font-weight: 700; font-size: 16px; color: #667eea;">₹<?php echo number_format($record['net_salary'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status = $record['approval_status'] ?? 'pending';
                                        $statusClass = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn-icon" onclick="viewPayrollDetails(<?php echo $record['payroll_id']; ?>)" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>
    <style>
        .selector-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .selector-card h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .selector-form {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            align-items: end;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--muted);
            font-size: 13px;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .verification-badge {
            padding: 12px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .verification-badge.success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .verification-badge.warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-primary, .btn-success {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
        }

        .btn-icon {
            background: #e0e7ff;
            color: var(--accent);
            border: none;
            padding: 8px 12px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-icon:hover {
            background: var(--accent);
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.success {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.warning {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.danger {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
    <script>
        function viewPayrollDetails(payrollId) {
            alert('View details for payroll ID: ' + payrollId);
            // TODO: Implement modal or redirect to details page
        }
    </script>
</body>
</html>
