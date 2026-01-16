<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Director';

// Get pending payroll approvals (payrolls awaiting director approval)
try {
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM payroll 
        WHERE approval_status = 'pending' OR approval_status IS NULL
    ");
    $pendingApprovals = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $pendingApprovals = 0;
}

// Get total payout for current month (approved payrolls)
try {
    $stmt = $db->query("
        SELECT SUM(net_salary) 
        FROM payroll 
        WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(created_at) = YEAR(CURRENT_DATE())
        AND approval_status = 'approved'
    ");
    $totalPayout = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $totalPayout = 0;
}

// Get approved count this month
try {
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM payroll 
        WHERE approval_status = 'approved'
        AND MONTH(approved_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(approved_at) = YEAR(CURRENT_DATE())
    ");
    $approvedCount = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $approvedCount = 0;
}

// Get rejected count this month
try {
    $stmt = $db->query("
        SELECT COUNT(*) 
        FROM payroll 
        WHERE approval_status = 'rejected'
        AND MONTH(approved_at) = MONTH(CURRENT_DATE()) 
        AND YEAR(approved_at) = YEAR(CURRENT_DATE())
    ");
    $rejectedCount = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $rejectedCount = 0;
}

// Get recent pending payrolls
try {
    $stmt = $db->query("
        SELECT 
            p.payroll_id,
            e.full_name,
            d.department_name,
            p.month,
            p.year,
            p.basic,
            p.gross_salary,
            p.total_deductions,
            p.net_salary,
            p.created_at
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE p.approval_status = 'pending' OR p.approval_status IS NULL
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $pendingPayrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingPayrolls = [];
}

$currentMonth = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Director Dashboard</title>
    <?php include 'includes/director_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            transition: transform 0.3s, box-shadow 0.3s;
            border-left: 5px solid var(--accent);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 24px rgba(0,0,0,0.12);
        }

        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.payout { border-left-color: #10b981; }
        .stat-card.approved { border-left-color: #3b82f6; }
        .stat-card.rejected { border-left-color: #ef4444; }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
        }

        .stat-icon.pending { background: #fef3c7; color: #f59e0b; }
        .stat-icon.payout { background: #d1fae5; color: #10b981; }
        .stat-icon.approved { background: #dbeafe; color: #3b82f6; }
        .stat-icon.rejected { background: #fee2e2; color: #ef4444; }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .stat-desc {
            font-size: 13px;
            color: var(--muted);
        }

        .data-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--border);
        }

        .card-header h2 {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .card-header h2 i {
            color: var(--accent);
        }

        .view-all-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            padding: 10px 20px;
            border-radius: 10px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .view-all-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
        }

        .payroll-table {
            width: 100%;
            border-collapse: collapse;
        }

        .payroll-table thead {
            background: #f8fafc;
        }

        .payroll-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payroll-table td {
            padding: 18px 15px;
            border-top: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .payroll-table tbody tr:hover {
            background: #f8fafc;
        }

        .amount {
            font-weight: 700;
        }

        .amount.positive {
            color: #10b981;
        }

        .amount.negative {
            color: #ef4444;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
        }

        .btn-approve, .btn-reject, .btn-view {
            padding: 6px 14px;
            border: none;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: #e0e7ff;
            color: #667eea;
        }

        .btn-view:hover {
            background: #c7d2fe;
        }

        .btn-approve {
            background: #d1fae5;
            color: #065f46;
        }

        .btn-approve:hover {
            background: #a7f3d0;
        }

        .btn-reject {
            background: #fee2e2;
            color: #991b1b;
        }

        .btn-reject:hover {
            background: #fecaca;
        }

        .period-badge {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-user-tie"></i> Director Dashboard</h1>
                <p>Payroll approval and financial oversight</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-icon pending">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-label">Pending Approvals</div>
                <div class="stat-value"><?php echo number_format($pendingApprovals); ?></div>
                <div class="stat-desc">Awaiting your review</div>
            </div>

            <div class="stat-card payout">
                <div class="stat-icon payout">
                    <i class="fas fa-wallet"></i>
                </div>
                <div class="stat-label">Total Payout</div>
                <div class="stat-value">₹<?php echo number_format($totalPayout, 2); ?></div>
                <div class="stat-desc">For <?php echo $currentMonth; ?></div>
            </div>

            <div class="stat-card approved">
                <div class="stat-icon approved">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?php echo number_format($approvedCount); ?></div>
                <div class="stat-desc">This month</div>
            </div>

            <div class="stat-card rejected">
                <div class="stat-icon rejected">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?php echo number_format($rejectedCount); ?></div>
                <div class="stat-desc">This month</div>
            </div>
        </div>

        <!-- Pending Payrolls -->
        <div class="data-card">
            <div class="card-header">
                <h2><i class="fas fa-file-invoice-dollar"></i> Pending Payroll Approvals</h2>
                <a href="approvals.php" class="view-all-btn">
                    View All <i class="fas fa-arrow-right"></i>
                </a>
            </div>

            <?php if (empty($pendingPayrolls)): ?>
                <div style="text-align: center; padding: 60px; color: var(--muted);">
                    <i class="fas fa-check-double" style="font-size: 56px; display: block; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3 style="font-size: 24px; margin-bottom: 10px;">All Caught Up!</h3>
                    <p style="font-size: 16px;">No pending payroll approvals at the moment</p>
                </div>
            <?php else: ?>
                <table class="payroll-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Basic</th>
                            <th>Gross</th>
                            <th>Deductions</th>
                            <th>Net Salary</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingPayrolls as $payroll): ?>
                            <tr>
                                <td>
                                    <span class="period-badge"><?php echo $payroll['month'] . ' ' . $payroll['year']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($payroll['full_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($payroll['department_name'] ?? 'N/A'); ?></td>
                                <td class="amount">₹<?php echo number_format($payroll['basic'], 2); ?></td>
                                <td class="amount positive">₹<?php echo number_format($payroll['gross_salary'], 2); ?></td>
                                <td class="amount negative">₹<?php echo number_format($payroll['total_deductions'], 2); ?></td>
                                <td class="amount" style="font-size: 16px; font-weight: 700; color: #667eea;">₹<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <a href="approvals.php?payroll_id=<?php echo $payroll['payroll_id']; ?>" class="btn-view">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/director_scripts.php'; ?>
</body>
</html>
