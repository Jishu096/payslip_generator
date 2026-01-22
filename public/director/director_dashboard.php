<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Director';
$userId = $_SESSION['user_id'] ?? null;
$baseURL = "/payslip_generator/public/";

// Get Director's employee details
$directorInfo = null;
try {
    $stmt = $db->prepare("
        SELECT 
            e.employee_code,
            e.full_name,
            e.designation,
            e.email,
            e.phone,
            e.experience_years,
            e.profile_photo,
            d.department_name,
            e.join_date
        FROM users u
        JOIN employees e ON u.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE u.user_id = ?
    ");
    $stmt->execute([$userId]);
    $directorInfo = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $directorInfo = null;
}

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
        .director-profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            padding: 30px;
            color: white;
            margin-bottom: 30px;
            box-shadow: 0 8px 30px rgba(102, 126, 234, 0.3);
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
            margin-bottom: 25px;
        }

        .profile-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            border: 4px solid rgba(255, 255, 255, 0.3);
            backdrop-filter: blur(10px);
            overflow: hidden;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .profile-photo:hover {
            transform: scale(1.05);
            border-color: rgba(255, 255, 255, 0.6);
            box-shadow: 0 0 20px rgba(255, 255, 255, 0.3);
        }

        .profile-photo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
        }

        .profile-main {
            flex: 1;
        }

        .profile-main h2 {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .profile-designation {
            font-size: 16px;
            opacity: 0.95;
            margin-bottom: 10px;
        }

        .profile-meta {
            display: flex;
            gap: 25px;
            flex-wrap: wrap;
        }

        .profile-meta-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            opacity: 0.9;
        }

        .profile-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            background: rgba(255, 255, 255, 0.15);
            padding: 20px;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-label {
            font-size: 12px;
            opacity: 0.8;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-value {
            font-size: 15px;
            font-weight: 600;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
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

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.payout { border-left-color: #10b981; }
        .stat-card.approved { border-left-color: #3b82f6; }
        .stat-card.rejected { border-left-color: #ef4444; }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.pending .stat-value {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.payout .stat-value {
            background: linear-gradient(135deg, #10b981, #059669);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.approved .stat-value {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.rejected .stat-value {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-desc {
            font-size: 13px;
            color: var(--muted);
            margin-top: 8px;
        }

        .data-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f1f5f9;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            margin: -25px -25px 25px -25px;
            padding: 25px;
            border-radius: 12px 12px 0 0;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 0;
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
    </style>
</head>
<body>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-user-tie"></i> Director Dashboard</h1>
                <p>Payroll approval and financial oversight</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 13px; color: var(--muted); margin-bottom: 5px;">Current Period</div>
                <div style="font-size: 16px; font-weight: 700; color: var(--accent);">
                    <?php echo date('F Y'); ?>
                </div>
            </div>
        </div>

        <?php if ($directorInfo): ?>
        <!-- Director Profile Card -->
        <div class="director-profile-card">
            <div class="profile-header">
                <a href="update_profile_photo.php" class="profile-photo" title="Click to update photo">
                    <?php if ($directorInfo['profile_photo']): ?>
                        <img src="<?php echo $baseURL; ?>assets/uploads/profile_photos/<?php echo htmlspecialchars($directorInfo['profile_photo']); ?>" alt="Director Photo">
                    <?php else: ?>
                        <i class="fas fa-user-tie"></i>
                    <?php endif; ?>
                </a>
                <div class="profile-main">
                    <h2><?php echo htmlspecialchars($directorInfo['full_name']); ?></h2>
                    <div class="profile-designation">
                        <?php echo htmlspecialchars($directorInfo['designation']); ?>
                    </div>
                    <div class="profile-meta">
                        <div class="profile-meta-item">
                            <i class="fas fa-id-badge"></i>
                            <span><?php echo htmlspecialchars($directorInfo['employee_code']); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-building"></i>
                            <span><?php echo htmlspecialchars($directorInfo['department_name']); ?></span>
                        </div>
                        <div class="profile-meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo $directorInfo['experience_years']; ?>+ Years Experience</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="profile-details">
                <div class="detail-item">
                    <div class="detail-label">Email Address</div>
                    <div class="detail-value">
                        <i class="fas fa-envelope"></i> <?php echo htmlspecialchars($directorInfo['email']); ?>
                        <?php if (strpos($directorInfo['email'], 'dir-bbsr') !== false): ?>
                            <br><small style="opacity: 0.8;">Alternative: anilshaw@nielit.gov.in</small>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($directorInfo['phone']): ?>
                <div class="detail-item">
                    <div class="detail-label">Contact Number</div>
                    <div class="detail-value">
                        <i class="fas fa-phone"></i> <?php echo htmlspecialchars($directorInfo['phone']); ?>
                    </div>
                </div>
                <?php endif; ?>
                <div class="detail-item">
                    <div class="detail-label">Qualification</div>
                    <div class="detail-value">
                        <i class="fas fa-graduation-cap"></i> MTech, BTech (ECE)
                    </div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">Since</div>
                    <div class="detail-value">
                        <i class="fas fa-calendar-check"></i> <?php echo date('M Y', strtotime($directorInfo['join_date'])); ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card pending">
                <div class="stat-label">Pending Approvals</div>
                <div class="stat-value"><?php echo number_format($pendingApprovals); ?></div>
                <div class="stat-desc">Awaiting your review</div>
            </div>

            <div class="stat-card payout">
                <div class="stat-label">Total Payout</div>
                <div class="stat-value">₹<?php echo number_format($totalPayout, 2); ?></div>
                <div class="stat-desc">For <?php echo $currentMonth; ?></div>
            </div>

            <div class="stat-card approved">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?php echo number_format($approvedCount); ?></div>
                <div class="stat-desc">This month</div>
            </div>

            <div class="stat-card rejected">
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
