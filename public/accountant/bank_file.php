<?php
session_start();

$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';

// Handle bank file generation
if (isset($_GET['generate'])) {
    $month = $_GET['month'] ?? '';
    $year = $_GET['year'] ?? '';
    
    if ($month && $year) {
        try {
            // Get approved payrolls with employee bank details
            $stmt = $db->prepare("
                SELECT 
                    e.full_name,
                    e.bank_account_no,
                    e.ifsc_code,
                    p.net_salary,
                    p.month,
                    p.year
                FROM payroll p
                JOIN employees e ON p.employee_id = e.employee_id
                WHERE p.month = ? AND p.year = ? AND p.approval_status = 'approved'
                ORDER BY e.full_name
            ");
            $stmt->execute([$month, $year]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($records)) {
                // Generate CSV
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="Bank_Salary_Transfer_' . $month . '_' . $year . '.csv"');
                
                $output = fopen('php://output', 'w');
                
                // CSV Headers
                fputcsv($output, ['Employee Name', 'Account Number', 'IFSC Code', 'Amount', 'Period']);
                
                // Data rows
                foreach ($records as $record) {
                    fputcsv($output, [
                        $record['full_name'],
                        $record['bank_account_no'] ?? 'N/A',
                        $record['ifsc_code'] ?? 'N/A',
                        number_format($record['net_salary'], 2, '.', ''),
                        $record['month'] . ' ' . $record['year']
                    ]);
                }
                
                fclose($output);
                exit;
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error generating bank file: ' . $e->getMessage();
        }
    }
}

// Get current selection
$selectedMonth = $_GET['month'] ?? date('F');
$selectedYear = $_GET['year'] ?? date('Y');

// Get approved payrolls for selected month
try {
    $stmt = $db->prepare("
        SELECT 
            e.full_name,
            e.bank_account_no,
            e.ifsc_code,
            p.net_salary,
            p.approval_status
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        WHERE p.month = ? AND p.year = ? AND p.approval_status = 'approved'
        ORDER BY e.full_name
    ");
    $stmt->execute([$selectedMonth, $selectedYear]);
    $bankRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $totalAmount = array_sum(array_column($bankRecords, 'net_salary'));
    $employeeCount = count($bankRecords);
} catch (Exception $e) {
    $bankRecords = [];
    $totalAmount = 0;
    $employeeCount = 0;
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank File Generation - Accountant</title>
    <?php include 'includes/accountant_styles.php'; ?>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-university"></i> Bank File Generation</h1>
                <p>Generate salary transfer files for approved payrolls</p>
            </div>
        </div>

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
        </div>

        <!-- Summary Stats -->
        <div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
            <div class="stat-card" style="border-left-color: #667eea;">
                <div class="stat-icon" style="background: #e0e7ff; color: #667eea;">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Approved Employees</div>
                    <div class="stat-value"><?php echo $employeeCount; ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-icon" style="background: #d1fae5; color: #10b981;">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Total Amount</div>
                    <div class="stat-value">₹<?php echo number_format($totalAmount, 2); ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                    <i class="fas fa-file-csv"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">File Format</div>
                    <div class="stat-value" style="font-size: 24px;">CSV</div>
                </div>
            </div>
        </div>

        <!-- Generate Button -->
        <?php if (!empty($bankRecords)): ?>
            <div class="action-card">
                <a href="?generate=1&month=<?php echo $selectedMonth; ?>&year=<?php echo $selectedYear; ?>" class="btn-primary large">
                    <i class="fas fa-download"></i> Download Bank Transfer File
                </a>
                <p style="margin-top: 15px; color: var(--muted);">
                    CSV file will contain employee names, account numbers, IFSC codes, and net salary amounts
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i>
                No approved payrolls found for <?php echo $selectedMonth . ' ' . $selectedYear; ?>. Please ensure payrolls are approved by Director.
            </div>
        <?php endif; ?>

        <!-- Preview Table -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Bank Transfer Preview - <?php echo $selectedMonth . ' ' . $selectedYear; ?></h2>
            </div>
            <div class="table-container">
                <?php if (empty($bankRecords)): ?>
                    <div style="text-align: center; padding: 60px; color: var(--muted);">
                        <i class="fas fa-inbox" style="font-size: 56px; opacity: 0.5; display: block; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 20px; margin-bottom: 10px;">No Approved Payrolls</h3>
                        <p>Wait for Director to approve payrolls before generating bank file</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Account Number</th>
                                <th>IFSC Code</th>
                                <th>Net Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($bankRecords as $record): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($record['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($record['bank_account_no'] ?? 'Not Available'); ?></td>
                                    <td><?php echo htmlspecialchars($record['ifsc_code'] ?? 'Not Available'); ?></td>
                                    <td class="amount" style="font-weight: 700; font-size: 16px; color: #667eea;">
                                        ₹<?php echo number_format($record['net_salary'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" style="text-align: right; font-weight: 700;"><strong>Total Amount:</strong></td>
                                <td class="amount" style="font-weight: 700; font-size: 18px; color: #667eea;">
                                    ₹<?php echo number_format($totalAmount, 2); ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <h3>Bank File Format</h3>
                <p>The CSV file contains: Employee Name, Account Number, IFSC Code, Amount, Period</p>
                <p>Upload this file to your bank's salary transfer portal for bulk payment processing</p>
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
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
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

        .action-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            text-align: center;
        }

        .btn-primary.large {
            padding: 18px 40px;
            font-size: 18px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-primary.large:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
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

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .info-box {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: start;
        }

        .info-box i {
            font-size: 32px;
            color: var(--accent);
        }

        .info-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }

        .info-box p {
            margin: 8px 0;
            color: var(--text);
            font-size: 14px;
        }
    </style>
</body>
</html>
