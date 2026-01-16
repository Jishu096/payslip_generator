<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Director';
$directorId = $_SESSION['user_id'] ?? null;

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $payrollId = $_POST['payroll_id'] ?? null;
    $action = $_POST['action']; // 'approve' or 'reject'
    $comments = $_POST['comments'] ?? '';
    
    if ($payrollId && in_array($action, ['approve', 'reject'])) {
        try {
            $db->beginTransaction();
            
            // Update payroll approval status
            $status = $action === 'approve' ? 'approved' : 'rejected';
            $stmt = $db->prepare("
                UPDATE payroll 
                SET approval_status = ?,
                    approved_by = ?,
                    approved_at = NOW(),
                    approval_comments = ?
                WHERE payroll_id = ?
            ");
            $stmt->execute([$status, $directorId, $comments, $payrollId]);
            
            // If approved, lock the month for this employee
            if ($action === 'approve') {
                // Get payroll details
                $stmt = $db->prepare("SELECT employee_id, month, year FROM payroll WHERE payroll_id = ?");
                $stmt->execute([$payrollId]);
                $payroll = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($payroll) {
                    // Check if month_lock table exists, if not create it
                    $createTableSQL = "
                        CREATE TABLE IF NOT EXISTS month_lock (
                            lock_id INT PRIMARY KEY AUTO_INCREMENT,
                            employee_id INT NOT NULL,
                            month VARCHAR(20) NOT NULL,
                            year INT NOT NULL,
                            locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            locked_by INT,
                            UNIQUE KEY unique_employee_month (employee_id, month, year)
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                    ";
                    $db->exec($createTableSQL);
                    
                    // Lock the month
                    $stmt = $db->prepare("
                        INSERT INTO month_lock (employee_id, month, year, locked_by) 
                        VALUES (?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE locked_at = NOW(), locked_by = ?
                    ");
                    $stmt->execute([
                        $payroll['employee_id'], 
                        $payroll['month'], 
                        $payroll['year'], 
                        $directorId,
                        $directorId
                    ]);
                }
            }
            
            $db->commit();
            
            $_SESSION['success_message'] = $action === 'approve' 
                ? 'Payroll approved successfully! Month has been locked.' 
                : 'Payroll rejected successfully.';
            
            header("Location: approvals.php");
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $_SESSION['error_message'] = 'Error processing approval: ' . $e->getMessage();
            header("Location: approvals.php");
            exit();
        }
    }
}

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'pending';
$monthFilter = $_GET['month'] ?? '';
$yearFilter = $_GET['year'] ?? '';

// Build query
$sql = "SELECT 
            p.payroll_id,
            p.employee_id,
            e.full_name,
            d.department_name,
            p.month,
            p.year,
            p.basic,
            p.da_amount,
            p.hra_amount,
            p.ta_amount,
            p.da_on_ta,
            p.bonus,
            p.gross_salary,
            p.tax_deduction,
            p.pf_deduction,
            p.nps_deduction,
            p.professional_tax,
            p.other_deductions,
            p.total_deductions,
            p.net_salary,
            p.created_at,
            p.approval_status,
            p.approved_at,
            p.approval_comments,
            u.username as approved_by_name
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN users u ON p.approved_by = u.user_id
        WHERE 1=1";

$params = [];

if ($statusFilter === 'pending') {
    $sql .= " AND (p.approval_status = 'pending' OR p.approval_status IS NULL)";
} elseif ($statusFilter === 'approved') {
    $sql .= " AND p.approval_status = 'approved'";
} elseif ($statusFilter === 'rejected') {
    $sql .= " AND p.approval_status = 'rejected'";
}

if ($monthFilter) {
    $sql .= " AND p.month = ?";
    $params[] = $monthFilter;
}

if ($yearFilter) {
    $sql .= " AND p.year = ?";
    $params[] = $yearFilter;
}

$sql .= " ORDER BY p.created_at DESC";

try {
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $payrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payrolls = [];
    $error = $e->getMessage();
}

// Get stats
$pendingCount = count(array_filter($payrolls, fn($p) => ($p['approval_status'] ?? 'pending') === 'pending'));
$approvedCount = count(array_filter($payrolls, fn($p) => $p['approval_status'] === 'approved'));
$rejectedCount = count(array_filter($payrolls, fn($p) => $p['approval_status'] === 'rejected'));

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Approvals - Director</title>
    <?php include 'includes/director_styles.php'; ?>
    <style>
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

        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
        }

        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.approved { border-left-color: #10b981; }
        .stat-card.rejected { border-left-color: #ef4444; }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 8px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
        }

        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--muted);
            font-size: 13px;
        }

        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .filter-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .payroll-grid {
            display: grid;
            gap: 20px;
        }

        .payroll-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .payroll-card:hover {
            box-shadow: 0 4px 16px rgba(0,0,0,0.12);
        }

        .payroll-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .employee-info h3 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .employee-meta {
            font-size: 13px;
            color: var(--muted);
            display: flex;
            gap: 15px;
        }

        .period-badge {
            background: var(--accent);
            color: white;
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 13px;
            font-weight: 600;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .salary-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .salary-section {
            padding: 15px;
            background: #f8fafc;
            border-radius: 10px;
        }

        .salary-section h4 {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 12px;
        }

        .salary-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .salary-item-label {
            color: var(--muted);
        }

        .salary-item-value {
            font-weight: 600;
            color: var(--text);
        }

        .salary-total {
            padding-top: 12px;
            border-top: 2px solid var(--border);
            margin-top: 8px;
        }

        .salary-total .salary-item-value {
            font-size: 18px;
            font-weight: 700;
            color: var(--accent);
        }

        .action-buttons {
            display: flex;
            gap: 12px;
            margin-top: 20px;
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-approve:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.3);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(239, 68, 68, 0.3);
        }

        .btn-toggle {
            background: #e0e7ff;
            color: #667eea;
        }

        .btn-toggle:hover {
            background: #c7d2fe;
        }

        .details-section {
            display: none;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid var(--border);
        }

        .details-section.active {
            display: block;
        }

        .comments-input {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            margin-top: 10px;
        }

        .comments-input:focus {
            outline: none;
            border-color: var(--accent);
        }

        @media (max-width: 1200px) {
            .salary-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-row {
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
                <h1><i class="fas fa-check-circle"></i> Payroll Approvals</h1>
                <p>Review and approve employee payrolls</p>
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

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card pending">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo $pendingCount; ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?php echo $approvedCount; ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?php echo $rejectedCount; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="pending" <?php echo $statusFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $statusFilter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="" <?php echo $statusFilter === '' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month">
                        <option value="">All Months</option>
                        <?php foreach ($months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $monthFilter === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year">
                        <option value="">All Years</option>
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $yearFilter == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Payrolls -->
        <div class="payroll-grid">
            <?php if (empty($payrolls)): ?>
                <div style="text-align: center; padding: 60px; background: white; border-radius: 12px;">
                    <i class="fas fa-inbox" style="font-size: 56px; color: var(--muted); opacity: 0.5; display: block; margin-bottom: 20px;"></i>
                    <h3 style="color: var(--muted); font-size: 20px;">No Payrolls Found</h3>
                    <p style="color: var(--muted);">Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <?php foreach ($payrolls as $index => $payroll): ?>
                    <div class="payroll-card">
                        <div class="payroll-header">
                            <div class="employee-info">
                                <h3><?php echo htmlspecialchars($payroll['full_name']); ?></h3>
                                <div class="employee-meta">
                                    <span><i class="fas fa-building"></i> <?php echo htmlspecialchars($payroll['department_name'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <span class="period-badge"><?php echo $payroll['month'] . ' ' . $payroll['year']; ?></span><br>
                                <span class="status-badge <?php echo strtolower($payroll['approval_status'] ?? 'pending'); ?>" style="margin-top: 8px; display: inline-block;">
                                    <?php echo ucfirst($payroll['approval_status'] ?? 'Pending'); ?>
                                </span>
                            </div>
                        </div>

                        <div class="salary-grid">
                            <div class="salary-section">
                                <h4><i class="fas fa-plus-circle"></i> Earnings</h4>
                                <div class="salary-item">
                                    <span class="salary-item-label">Basic</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['basic'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">DA</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['da_amount'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">HRA</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['hra_amount'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">TA</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['ta_amount'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">Bonus</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['bonus'], 2); ?></span>
                                </div>
                                <div class="salary-item salary-total">
                                    <span class="salary-item-label"><strong>Gross Salary</strong></span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['gross_salary'], 2); ?></span>
                                </div>
                            </div>

                            <div class="salary-section">
                                <h4><i class="fas fa-minus-circle"></i> Deductions</h4>
                                <div class="salary-item">
                                    <span class="salary-item-label">Tax</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['tax_deduction'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">PF</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['pf_deduction'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">NPS</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['nps_deduction'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">Prof. Tax</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['professional_tax'], 2); ?></span>
                                </div>
                                <div class="salary-item">
                                    <span class="salary-item-label">Other</span>
                                    <span class="salary-item-value">₹<?php echo number_format($payroll['other_deductions'], 2); ?></span>
                                </div>
                                <div class="salary-item salary-total">
                                    <span class="salary-item-label"><strong>Total Deductions</strong></span>
                                    <span class="salary-item-value" style="color: #ef4444;">₹<?php echo number_format($payroll['total_deductions'], 2); ?></span>
                                </div>
                            </div>

                            <div class="salary-section" style="background: linear-gradient(135deg, #e0e7ff, #c7d2fe);">
                                <h4><i class="fas fa-wallet"></i> Net Salary</h4>
                                <div style="text-align: center; padding: 20px 0;">
                                    <div style="font-size: 32px; font-weight: 700; color: #667eea;">
                                        ₹<?php echo number_format($payroll['net_salary'], 2); ?>
                                    </div>
                                    <div style="font-size: 12px; color: var(--muted); margin-top: 8px;">
                                        Final Payout
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (($payroll['approval_status'] ?? 'pending') === 'pending'): ?>
                            <form method="POST" action="approvals.php" id="approval-form-<?php echo $payroll['payroll_id']; ?>">
                                <input type="hidden" name="payroll_id" value="<?php echo $payroll['payroll_id']; ?>">
                                <input type="hidden" name="action" id="action-<?php echo $payroll['payroll_id']; ?>" value="">
                                
                                <label style="display: block; margin-top: 15px; font-weight: 600; color: var(--muted); font-size: 13px;">
                                    Comments (Optional)
                                </label>
                                <textarea name="comments" class="comments-input" rows="2" placeholder="Add any comments about this approval..."></textarea>
                                
                                <div class="action-buttons">
                                    <button type="button" class="btn btn-approve" onclick="submitApproval(<?php echo $payroll['payroll_id']; ?>, 'approve')">
                                        <i class="fas fa-check"></i> Approve & Lock Month
                                    </button>
                                    <button type="button" class="btn btn-reject" onclick="submitApproval(<?php echo $payroll['payroll_id']; ?>, 'reject')">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 8px;">
                                <div style="font-weight: 600; color: var(--muted); font-size: 13px; margin-bottom: 5px;">
                                    <?php echo ucfirst($payroll['approval_status']); ?> by <?php echo htmlspecialchars($payroll['approved_by_name'] ?? 'Director'); ?>
                                    on <?php echo date('d M Y, h:i A', strtotime($payroll['approved_at'])); ?>
                                </div>
                                <?php if ($payroll['approval_comments']): ?>
                                    <div style="color: var(--text); font-size: 14px;">
                                        <strong>Comments:</strong> <?php echo htmlspecialchars($payroll['approval_comments']); ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($payroll['approval_status'] === 'approved'): ?>
                                    <div style="margin-top: 10px; padding: 10px; background: #d1fae5; border-left: 4px solid #10b981; border-radius: 6px; color: #065f46; font-weight: 600; font-size: 13px;">
                                        <i class="fas fa-lock"></i> Month Locked - No further changes allowed
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/director_scripts.php'; ?>
    <script>
        function submitApproval(payrollId, action) {
            if (action === 'approve') {
                if (!confirm('Are you sure you want to approve this payroll? The month will be LOCKED and cannot be modified.')) {
                    return;
                }
            } else {
                if (!confirm('Are you sure you want to reject this payroll?')) {
                    return;
                }
            }
            
            document.getElementById('action-' + payrollId).value = action;
            document.getElementById('approval-form-' + payrollId).submit();
        }
    </script>
</body>
</html>
