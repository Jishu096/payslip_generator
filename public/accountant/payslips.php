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

// Get current selection
$selectedMonth = $_GET['month'] ?? date('F');
$selectedYear = $_GET['year'] ?? date('Y');

// Get all generated payslips
try {
    $stmt = $db->prepare("
        SELECT 
            ps.payslip_id,
            e.full_name,
            e.designation,
            d.department_name,
            p.month,
            p.year,
            p.net_salary,
            p.approval_status,
            ps.generated_at
        FROM payslips ps
        JOIN payroll p ON ps.payroll_id = p.payroll_id
        JOIN employees e ON ps.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE p.month = ? AND p.year = ?
        ORDER BY e.full_name
    ");
    $stmt->execute([$selectedMonth, $selectedYear]);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payslips = [];
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 2);

// Count by status
$totalPayslips = count($payslips);
$approvedPayslips = count(array_filter($payslips, fn($p) => $p['approval_status'] === 'approved'));
$pendingPayslips = count(array_filter($payslips, fn($p) => $p['approval_status'] === 'pending'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payslips - Accountant</title>
    <?php include 'includes/accountant_styles.php'; ?>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-file-invoice"></i> Payslips Management</h1>
                <p>View and download generated employee payslips</p>
            </div>
        </div>

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
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Total Payslips</div>
                    <div class="stat-value"><?php echo $totalPayslips; ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-icon" style="background: #d1fae5; color: #10b981;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Approved</div>
                    <div class="stat-value"><?php echo $approvedPayslips; ?></div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Pending</div>
                    <div class="stat-value"><?php echo $pendingPayslips; ?></div>
                </div>
            </div>
        </div>

        <!-- Payslips Table -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Payslips for <?php echo $selectedMonth . ' ' . $selectedYear; ?></h2>
            </div>
            <div class="table-container">
                <?php if (empty($payslips)): ?>
                    <div style="text-align: center; padding: 60px; color: var(--muted);">
                        <i class="fas fa-inbox" style="font-size: 56px; opacity: 0.5; display: block; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 20px; margin-bottom: 10px;">No Payslips Generated</h3>
                        <p>Generate payslips from the Payroll page for <?php echo $selectedMonth . ' ' . $selectedYear; ?></p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Generated Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payslips as $payslip): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($payslip['full_name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($payslip['designation'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($payslip['department_name'] ?? 'N/A'); ?></td>
                                    <td class="amount">₹<?php echo number_format($payslip['net_salary'], 2); ?></td>
                                    <td>
                                        <?php if ($payslip['approval_status'] === 'approved'): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Approved
                                            </span>
                                        <?php elseif ($payslip['approval_status'] === 'pending'): ?>
                                            <span class="badge badge-warning">
                                                <i class="fas fa-clock"></i> Pending
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times-circle"></i> Rejected
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($payslip['generated_at'])); ?></td>
                                    <td>
                                        <?php if ($payslip['approval_status'] === 'approved'): ?>
                                            <a href="generate_payslip_pdf.php?payslip_id=<?php echo $payslip['payslip_id']; ?>" 
                                               class="btn-small btn-primary" target="_blank">
                                                <i class="fas fa-file-pdf"></i> Download PDF
                                            </a>
                                        <?php else: ?>
                                            <span style="color: var(--muted); font-size: 13px;">Awaiting approval</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <h3>About Payslips</h3>
                <p>Payslips are generated from the Payroll page after calculating salaries</p>
                <p>PDF downloads are available only after Director approval</p>
                <p>Employees can view their payslips from their Employee Dashboard</p>
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

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-small {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .btn-small.btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-small.btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
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
