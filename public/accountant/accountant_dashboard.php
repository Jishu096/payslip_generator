<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'accountant') {
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
    <title>Accountant Dashboard - Enterprise Payroll</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b1221;
            --card: #0f172a;
            --muted: #9ca3af;
            --text: #e5e7eb;
            --accent: #0ea5e9;
            --accent-2: #22d3ee;
            --border: #1f2937;
            --success: #34d399;
            --warn: #fbbf24;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.06), transparent 25%),
                        radial-gradient(circle at 80% 0%, rgba(14,165,233,0.08), transparent 30%),
                        var(--bg);
            color: var(--text);
            min-height: 100vh;
        }

        a { color: inherit; text-decoration: none; }

        .layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            min-height: 100vh;
        }

        .sidebar {
            background: linear-gradient(180deg, #0f172a 0%, #0b1221 100%);
            border-right: 1px solid var(--border);
            padding: 24px;
            position: sticky;
            top: 0;
            height: 100vh;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 700;
            letter-spacing: 0.4px;
            margin-bottom: 28px;
        }

        .brand-icon {
            width: 38px;
            height: 38px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: grid;
            place-items: center;
            color: #0b1221;
            font-weight: 800;
        }

        .nav {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav a {
            padding: 12px 14px;
            border-radius: 10px;
            color: var(--muted);
            display: flex;
            align-items: center;
            gap: 12px;
            transition: all 0.2s ease;
        }

        .nav a:hover,
        .nav a.active {
            background: rgba(14,165,233,0.1);
            color: var(--text);
        }

        .main {
            padding: 32px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
        }

        .title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .title h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            letter-spacing: 0.2px;
        }

        .subtitle { color: var(--muted); margin-top: 4px; }

        .user { display: flex; align-items: center; gap: 12px; }

        .avatar {
            width: 44px;
            height: 44px;
            border-radius: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: grid;
            place-items: center;
            color: #0b1221;
            font-weight: 800;
        }

        .badge { color: var(--muted); font-size: 12px; }

        .grid { display: grid; gap: 16px; }

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 16px 18px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.25);
        }

        .stat-label { color: var(--muted); font-size: 13px; letter-spacing: 0.3px; }
        .stat-value { font-size: 28px; font-weight: 700; margin-top: 6px; }
        .pill { display: inline-flex; align-items: center; gap: 6px; padding: 4px 8px; border-radius: 999px; font-size: 12px; }
        .pill.accent { background: rgba(14,165,233,0.15); color: var(--accent); }
        .pill.success { background: rgba(52,211,153,0.15); color: var(--success); }

        .panel-title {
            font-size: 15px;
            letter-spacing: 0.3px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
        }

        .action {
            background: linear-gradient(135deg, rgba(14,165,233,0.12), rgba(34,211,238,0.12));
            border: 1px solid var(--border);
            color: var(--text);
            padding: 14px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s ease;
        }

        .action:hover { transform: translateY(-2px); border-color: rgba(34,211,238,0.4); }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 4px;
        }

        .table th, .table td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--border);
            text-align: left;
            color: var(--text);
        }

        .table th { color: var(--muted); font-size: 12px; letter-spacing: 0.4px; text-transform: uppercase; }

        .muted { color: var(--muted); font-size: 13px; }

        .logout-btn {
            margin-top: 18px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 10px;
            background: rgba(239,68,68,0.15);
            color: #f87171;
            border: 1px solid rgba(239,68,68,0.2);
            transition: all 0.2s ease;
        }

        .logout-btn:hover { transform: translateY(-1px); border-color: rgba(239,68,68,0.35); }
    </style>
</head>
<body>
    <div class="layout">
        <aside class="sidebar">
            <div class="brand">
                <div class="brand-icon"><i class="fas fa-calculator"></i></div>
                <div>
                    <div style="font-family: 'Space Grotesk', sans-serif;">Accountant</div>
                    <div class="badge">Payroll Control Center</div>
                </div>
            </div>
            <nav class="nav">
                <a href="accountant_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a>
                <a href="payroll_management.php"><i class="fas fa-money-bill-wave"></i> Payroll Management</a>
                <a href="generate_payslip.php"><i class="fas fa-file-invoice-dollar"></i> Generate Payslip</a>
                <a href="financial_reports.php"><i class="fas fa-chart-pie"></i> Financial Reports</a>
                <a href="../admin/employees.php"><i class="fas fa-users"></i> Employees</a>
            </nav>
            <a href="../index.php?page=logout" class="logout-btn" style="margin-top: 30px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </aside>

        <main class="main">
            <div class="header">
                <div>
                    <div class="title">
                        <i class="fas fa-bolt" style="color: var(--accent);"></i>
                        <div>
                            <h1>Accountant Dashboard</h1>
                            <div class="subtitle">Operational snapshot for <?php echo htmlspecialchars($monthLabel); ?></div>
                        </div>
                    </div>
                </div>
                <div class="user">
                    <div>
                        <div style="font-weight: 700;"><?php echo htmlspecialchars($username); ?></div>
                        <div class="badge">Accountant</div>
                    </div>
                    <div class="avatar"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                </div>
            </div>

            <div class="grid" style="gap: 18px;">
                <div class="stats">
                    <div class="card">
                        <div class="stat-label"><i class="fas fa-users"></i> Active Employees</div>
                        <div class="stat-value"><?php echo $totalEmployees; ?></div>
                        <div class="pill accent"><i class="fas fa-level-up-alt"></i> Headcount stable</div>
                    </div>
                    <div class="card">
                        <div class="stat-label"><i class="fas fa-indian-rupee-sign"></i> Monthly Payroll (Base)</div>
                        <div class="stat-value">₹<?php echo number_format($totalPayroll, 0); ?></div>
                        <div class="muted">Based on employee basic salary</div>
                    </div>
                    <div class="card">
                        <div class="stat-label"><i class="fas fa-file-invoice-dollar"></i> Payslips (All)</div>
                        <div class="stat-value"><?php echo $payslipCount; ?></div>
                        <div class="pill success"><i class="fas fa-check"></i> <?php echo $payslipMonthCount; ?> this month</div>
                    </div>
                    <div class="card">
                        <div class="stat-label"><i class="fas fa-calendar-alt"></i> Current Period</div>
                        <div class="stat-value"><?php echo date('M Y'); ?></div>
                        <div class="muted"><?php echo date('l, d M'); ?></div>
                    </div>
                </div>

                <div class="card">
                    <div class="panel-title"><i class="fas fa-bolt"></i> Quick Actions</div>
                    <div class="quick-actions">
                        <a class="action" href="generate_payslip.php">
                            <div>
                                <div style="font-weight: 700;">Generate Payslip</div>
                                <div class="muted">Auto-calc with new rates</div>
                            </div>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a class="action" href="payroll_management.php">
                            <div>
                                <div style="font-weight: 700;">Manage Payroll</div>
                                <div class="muted">Adjust payouts & bonuses</div>
                            </div>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a class="action" href="financial_reports.php">
                            <div>
                                <div style="font-weight: 700;">View Reports</div>
                                <div class="muted">Cash flow & summaries</div>
                            </div>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                        <a class="action" href="../admin/employees.php">
                            <div>
                                <div style="font-weight: 700;">Employee Directory</div>
                                <div class="muted">Update profiles & CTC</div>
                            </div>
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="card">
                    <div class="panel-title"><i class="fas fa-clock"></i> Recent Payslips</div>
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Designation</th>
                                <th>Department</th>
                                <th>Net</th>
                                <th>Generated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($recentPayslips) === 0): ?>
                                <tr><td colspan="5" class="muted">No payslips generated yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recentPayslips as $row): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                                        <td class="muted"><?php echo htmlspecialchars($row['designation']); ?></td>
                                        <td class="muted"><?php echo htmlspecialchars($row['department_name'] ?? 'N/A'); ?></td>
                                        <td><span class="pill accent">₹<?php echo number_format($row['net_salary'], 2); ?></span></td>
                                        <td class="muted"><?php echo date('d M, H:i', strtotime($row['generated_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</body>
</html>
