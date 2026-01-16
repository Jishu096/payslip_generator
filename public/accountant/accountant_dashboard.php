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

// Get Verified Months (attendance verified and ready for payroll)
try {
    $stmt = $db->query("
        SELECT COUNT(DISTINCT CONCAT(MONTH(date), '-', YEAR(date))) as verified_months
        FROM attendance
        WHERE status = 'verified'
        AND date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    ");
    $verifiedMonths = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $verifiedMonths = 0;
}

// Get Salary Ready (payrolls calculated but pending director approval)
try {
    $stmt = $db->query("
        SELECT COUNT(*) as salary_ready
        FROM payroll
        WHERE approval_status = 'pending' OR approval_status IS NULL
    ");
    $salaryReady = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $salaryReady = 0;
}

// Get Arrears (rejected or pending corrections)
try {
    $stmt = $db->query("
        SELECT COUNT(*) as arrears
        FROM payroll
        WHERE approval_status = 'rejected'
    ");
    $arrears = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $arrears = 0;
}

// Get recent payroll activities
try {
    $stmt = $db->query("
        SELECT 
            p.payroll_id,
            e.full_name,
            p.month,
            p.year,
            p.net_salary,
            p.approval_status,
            p.created_at
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $recentPayrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentPayrolls = [];
}

$currentMonth = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard</title>
    <?php include 'includes/accountant_styles.php'; ?>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Accountant Dashboard</h1>
                <p>Manage payroll and salary processing</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 14px; color: var(--muted); margin-bottom: 5px;">Current Period</div>
                <div style="font-size: 20px; font-weight: 700; color: var(--accent);"><?php echo $currentMonth; ?></div>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="stats-grid">
            <div class="stat-card" style="border-left-color: #10b981;">
                <div class="stat-icon" style="background: #d1fae5; color: #10b981;">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Verified Months</div>
                    <div class="stat-value"><?php echo $verifiedMonths; ?></div>
                    <div class="stat-sublabel">Attendance verified, ready for payroll</div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #667eea;">
                <div class="stat-icon" style="background: #e0e7ff; color: #667eea;">
                    <i class="fas fa-calculator"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Salary Ready</div>
                    <div class="stat-value"><?php echo $salaryReady; ?></div>
                    <div class="stat-sublabel">Calculated, pending director approval</div>
                </div>
            </div>

            <div class="stat-card" style="border-left-color: #f59e0b;">
                <div class="stat-icon" style="background: #fef3c7; color: #f59e0b;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Arrears</div>
                    <div class="stat-value"><?php echo $arrears; ?></div>
                    <div class="stat-sublabel">Rejected, needs correction</div>
                </div>
            </div>
        </div>

        <!-- Workflow Guide -->
        <div class="workflow-card">
            <h2><i class="fas fa-sitemap"></i> Payroll Workflow</h2>
            <div class="workflow-steps">
                <div class="workflow-step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Select Month</h3>
                        <p>Choose the payroll period</p>
                    </div>
                </div>
                <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="workflow-step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Verify Attendance</h3>
                        <p>System uses verified attendance</p>
                    </div>
                </div>
                <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="workflow-step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Calculate Salary</h3>
                        <p>Auto-calculate with structure</p>
                    </div>
                </div>
                <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="workflow-step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Generate Payslips</h3>
                        <p>Create PDF payslips</p>
                    </div>
                </div>
                <div class="workflow-arrow"><i class="fas fa-arrow-right"></i></div>
                <div class="workflow-step">
                    <div class="step-number">5</div>
                    <div class="step-content">
                        <h3>Send to Director</h3>
                        <p>Submit for approval</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <a href="payroll.php" class="action-btn primary">
                <i class="fas fa-calculator"></i>
                <span>Process Payroll</span>
            </a>
            <a href="generate_attendance_statement.php" class="action-btn success">
                <i class="fas fa-file-excel"></i>
                <span>Attendance Statement</span>
            </a>
            <a href="manage_statement_officials.php" class="action-btn" style="border-left-color: #8b5cf6;">
                <i class="fas fa-user-tie"></i>
                <span>Statement Officials</span>
            </a>
            <a href="manage_salary_config.php" class="action-btn" style="border-left-color: #f59e0b;">
                <i class="fas fa-cog"></i>
                <span>Salary Configuration</span>
            </a>
            <a href="salary_structure.php" class="action-btn">
                <i class="fas fa-money-bill-wave"></i>
                <span>Salary Structure</span>
            </a>
            <a href="bank_file.php" class="action-btn">
                <i class="fas fa-university"></i>
                <span>Generate Bank File</span>
            </a>
            <a href="payslips.php" class="action-btn">
                <i class="fas fa-file-invoice"></i>
                <span>View Payslips</span>
            </a>
        </div>

        <!-- Recent Payrolls -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Recent Payroll Activity</h2>
            </div>
            <div class="table-container">
                <?php if (empty($recentPayrolls)): ?>
                    <div style="text-align: center; padding: 60px; color: var(--muted);">
                        <i class="fas fa-inbox" style="font-size: 56px; opacity: 0.5; display: block; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 20px; margin-bottom: 10px;">No Payroll Records</h3>
                        <p>Start by processing payroll for the current month</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>Employee</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentPayrolls as $payroll): ?>
                                <tr>
                                    <td>
                                        <span class="period-badge"><?php echo $payroll['month'] . ' ' . $payroll['year']; ?></span>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($payroll['full_name']); ?></strong></td>
                                    <td class="amount">₹<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                    <td>
                                        <?php
                                        $status = $payroll['approval_status'] ?? 'pending';
                                        $statusClass = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'warning');
                                        ?>
                                        <span class="status-badge <?php echo $statusClass; ?>">
                                            <?php echo ucfirst($status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d M Y', strtotime($payroll['created_at'])); ?></td>
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
        .workflow-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .workflow-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .workflow-steps {
            display: flex;
            align-items: center;
            gap: 15px;
            overflow-x: auto;
            padding: 10px;
        }

        .workflow-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            min-width: 150px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 12px;
            border: 2px solid var(--border);
            transition: all 0.3s;
        }

        .workflow-step:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.2);
        }

        .step-number {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            margin-bottom: 15px;
        }

        .step-content h3 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
            text-align: center;
        }

        .step-content p {
            font-size: 12px;
            color: var(--muted);
            text-align: center;
        }

        .workflow-arrow {
            color: var(--accent);
            font-size: 24px;
            opacity: 0.5;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .action-btn {
            background: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            text-decoration: none;
            color: var(--text);
            border: 2px solid var(--border);
            transition: all 0.3s;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .action-btn i {
            font-size: 32px;
            color: var(--accent);
        }

        .action-btn:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.2);
        }

        .action-btn.primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
        }

        .action-btn.primary i {
            color: white;
        }

        .action-btn span {
            font-weight: 600;
            font-size: 14px;
        }

        .period-badge {
            background: var(--accent);
            color: white;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
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

        @media (max-width: 1200px) {
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            .workflow-steps {
                flex-direction: column;
            }
            .workflow-arrow {
                transform: rotate(90deg);
            }
        }
    </style>
</body>
</html>
