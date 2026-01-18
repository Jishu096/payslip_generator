<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has accountant role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!$hasAccountantRole && $_SESSION['role'] !== 'accountant') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Models/Employee.php";
require_once __DIR__ . '/../../app/Config/database.php';

$employeeModel = new Employee();
$db = getDBConnection();

$username = $_SESSION['username'] ?? 'Accountant';
$totalEmployees = count($employeeModel->getAllEmployees());

// Payroll and payslip stats
$stmt = $db->query("SELECT SUM(basic_salary) AS total_payroll FROM employees");
$totalPayroll = (float)($stmt->fetchColumn() ?? 0);

$payslipCount = (int)$db->query("SELECT COUNT(*) FROM payslips")->fetchColumn();
$payslipMonthCount = (int)$db->query("
    SELECT COUNT(*) 
    FROM payslips 
    WHERE MONTH(generated_at) = MONTH(CURRENT_DATE()) 
      AND YEAR(generated_at) = YEAR(CURRENT_DATE())
")->fetchColumn();

$recentPayslipsStmt = $db->prepare("
    SELECT 
        ps.payslip_id,
        ps.generated_at,
        e.full_name,
        e.designation,
        d.department_name,
        pr.net_salary,
        pr.gross_salary
    FROM payslips ps
    JOIN payroll pr ON ps.payroll_id = pr.payroll_id
    JOIN employees e ON ps.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY ps.generated_at DESC
    LIMIT 6
");
$recentPayslipsStmt->execute();
$recentPayslips = $recentPayslipsStmt->fetchAll(PDO::FETCH_ASSOC);

$monthLabel = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Payroll System</title>
    <!-- Include Admin Styles for Theme Consistency -->
    <?php include '../admin/includes/admin_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Specific adjustments for Accountant Dashboard if needed */
        .live-stat-icon.text-white { color: white; }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>
    <?php include 'includes/accountant_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Welcome back, <strong><?= htmlspecialchars($username) ?></strong>. Payroll Overview for <?= $monthLabel ?></p>
        </div>

        <!-- Live Stats Widgets -->
        <div class="live-stats-container">
            <!-- Total Employees -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-blue text-white">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-text">
                    <h4><?= $totalEmployees ?></h4>
                    <span>Active Employees</span>
                </div>
            </div>

            <!-- Monthly Payroll -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-rupee-sign"></i>
                </div>
                <div class="stat-text">
                    <h4>₹<?= number_format($totalPayroll, 0) ?></h4>
                    <span>Base Payroll</span>
                </div>
            </div>

            <!-- Payslips Generated -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-purple text-white">
                    <i class="fas fa-file-invoice"></i>
                </div>
                <div class="stat-text">
                    <h4><?= $payslipCount ?></h4>
                    <span>Total Payslips (<?= $payslipMonthCount ?> New)</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h3 style="font-size: 18px; color: #2d3748; margin-bottom: 20px; font-weight: 700;">Quick Actions</h3>
        <div class="attendance-actions-grid">
            <a href="generate_payslip.php" class="action-card bg-gradient-purple">
                <div class="card-content">
                    <div class="card-title">Generate Payslip</div>
                    <div class="card-desc">Process new payslips for the current month.</div>
                </div>
                <i class="fas fa-file-invoice-dollar icon-bg"></i>
            </a>

            <a href="payroll_management.php" class="action-card bg-gradient-teal">
                <div class="card-content">
                    <div class="card-title">Manage Payroll</div>
                    <div class="card-desc">Adjust salary structures and bonuses.</div>
                </div>
                <i class="fas fa-money-bill-wave icon-bg"></i>
            </a>

            <a href="financial_reports.php" class="action-card bg-gradient-orange">
                <div class="card-content">
                    <div class="card-title">Financial Reports</div>
                    <div class="card-desc">View detailed analytics and summaries.</div>
                </div>
                <i class="fas fa-chart-pie icon-bg"></i>
            </a>

            <a href="attendance_statement.php" class="action-card bg-gradient-pink">
                <div class="card-content">
                    <div class="card-title">Attendance Statement</div>
                    <div class="card-desc">View and export attendance registers.</div>
                </div>
                <i class="fas fa-file-contract icon-bg"></i>
            </a>

            <a href="../admin/employees.php" class="action-card bg-gradient-blue">
                <div class="card-content">
                    <div class="card-title">Employee Directory</div>
                    <div class="card-desc">View and manage employee profiles.</div>
                </div>
                <i class="fas fa-users-cog icon-bg"></i>
            </a>
        </div>

        <!-- Recent Payslips Table -->
        <div class="glass-card" style="margin-top: 30px; padding: 25px;">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom: 20px;">
                <h3 style="font-size: 18px; color: #2d3748; font-weight: 700;"><i class="fas fa-history"></i> Recent Payslips</h3>
                <a href="financial_reports.php" class="btn" style="padding: 8px 15px; font-size: 12px;">View All</a>
            </div>
            
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="padding: 12px; text-align: left; color: #718096; font-size: 13px;">Employee</th>
                        <th style="padding: 12px; text-align: left; color: #718096; font-size: 13px;">Designation</th>
                        <th style="padding: 12px; text-align: left; color: #718096; font-size: 13px;">Department</th>
                        <th style="padding: 12px; text-align: left; color: #718096; font-size: 13px;">Net Salary</th>
                        <th style="padding: 12px; text-align: left; color: #718096; font-size: 13px;">Generated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($recentPayslips) === 0): ?>
                        <tr><td colspan="5" style="text-align: center; padding: 20px; color: #a0aec0;">No payslips generated yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recentPayslips as $row): ?>
                            <tr style="border-bottom: 1px solid #f7fafc;">
                                <td style="padding: 15px; font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($row['full_name']); ?></td>
                                <td style="padding: 15px; color: #4a5568;"><?php echo htmlspecialchars($row['designation']); ?></td>
                                <td style="padding: 15px; color: #4a5568;"><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                <td style="padding: 15px;"><span style="background: #e6fffa; color: #0f766e; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">₹<?php echo number_format($row['net_salary'], 2); ?></span></td>
                                <td style="padding: 15px; color: #718096; font-size: 13px;"><?php echo date('d M, Y', strtotime($row['generated_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        // Simple Sidebar Toggle Script if needed
        // Since we are using standard structure, admin_styles might expect a toggle function if we add a toggle button.
        // But we didn't add the toggle button in the navbar for simplicity (it was hidden in CSS for desktop).
    </script>
</body>
</html>
