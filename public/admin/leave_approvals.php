<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

// Only administrator can approve leaves
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$userId = $_SESSION['user_id'];

require_once "../../app/Models/LeaveRequest.php";
require_once "../../app/Helpers/NotificationHelper.php";
require_once "../../app/Config/database.php";

$leaveModel = new LeaveRequest();
$db = getDBConnection();
$notificationHelper = new NotificationHelper($db);

// Handle approval/rejection
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $requestId = $_POST['request_id'];
        $comments = $_POST['comments'] ?? null;
        
        if ($leaveModel->approveLeaveRequest($requestId, $userId, $username, $comments)) {
            $success = "Leave request approved successfully!";
            
            // Get leave request details and notify employee
            $leaveDetails = $leaveModel->getLeaveRequestById($requestId);
            if ($leaveDetails) {
                $empStmt = $db->prepare("SELECT email FROM employees WHERE employee_id = ?");
                $empStmt->execute([$leaveDetails['employee_id']]);
                $empData = $empStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($empData && !empty($empData['email'])) {
                    $notificationHelper->notifyLeaveApproved(
                        $empData['email'],
                        $leaveDetails['employee_name'],
                        $leaveDetails['leave_type'],
                        $leaveDetails['start_date'],
                        $leaveDetails['end_date'],
                        $comments
                    );
                }
            }
        } else {
            $error = "Failed to approve leave request.";
        }
    } elseif (isset($_POST['reject'])) {
        $requestId = $_POST['request_id'];
        $comments = $_POST['comments'] ?? '';
        
        if (empty($comments)) {
            $error = "Please provide a reason for rejection.";
        } else {
            if ($leaveModel->rejectLeaveRequest($requestId, $userId, $username, $comments)) {
                $success = "Leave request rejected successfully!";
                
                // Get leave request details and notify employee
                $leaveDetails = $leaveModel->getLeaveRequestById($requestId);
                if ($leaveDetails) {
                    $empStmt = $db->prepare("SELECT email FROM employees WHERE employee_id = ?");
                    $empStmt->execute([$leaveDetails['employee_id']]);
                    $empData = $empStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($empData && !empty($empData['email'])) {
                        $notificationHelper->notifyLeaveRejected(
                            $empData['email'],
                            $leaveDetails['employee_name'],
                            $leaveDetails['leave_type'],
                            $leaveDetails['start_date'],
                            $leaveDetails['end_date'],
                            $comments
                        );
                    }
                }
            } else {
                $error = "Failed to reject leave request.";
            }
        }
    }
}

// Get filter
$filterStatus = $_GET['status'] ?? 'pending';

// Get leave requests
$leaveRequests = $leaveModel->getAllLeaveRequests($filterStatus);

// Get counts
$pendingCount = $leaveModel->getPendingCount();
$approvedRequests = $leaveModel->getAllLeaveRequests('approved');
$rejectedRequests = $leaveModel->getAllLeaveRequests('rejected');
$totalRequests = $pendingCount + count($approvedRequests) + count($rejectedRequests);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approvals - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header h1 i {
            margin-right: 12px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-badge {
            background: rgba(255,255,255,0.2);
            padding: 15px 30px;
            border-radius: 12px;
            text-align: center;
        }

        .header-badge .count {
            font-size: 36px;
            font-weight: 700;
        }

        .header-badge .label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none;
            display: block;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-card.red::before { background: linear-gradient(90deg, #ef4444, #dc2626); }
        .stat-card.purple::before { background: linear-gradient(90deg, #667eea, #764ba2); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .stat-card.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card.active::before {
            display: none;
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-card.active .stat-value {
            color: white;
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .stat-card.active .stat-label {
            color: rgba(255,255,255,0.9);
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.green .stat-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-card.red .stat-icon { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }

        .stat-card.active .stat-icon {
            background: rgba(255,255,255,0.2);
        }

        /* Alert Styles */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border: 1px solid #10b981;
            color: #059669;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border: 1px solid #ef4444;
            color: #dc2626;
        }

        /* Section Title */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        .section-title .count-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* Request Cards */
        .requests-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .request-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .request-card:hover {
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .request-card-header {
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #f1f5f9;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .employee-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .employee-details h3 {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 4px 0;
        }

        .employee-details p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }

        .status-badge {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
            color: #d97706;
        }

        .status-badge.approved {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #059669;
        }

        .status-badge.rejected {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: #dc2626;
        }

        .request-card-body {
            padding: 25px;
        }

        .leave-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .leave-info-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .leave-info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #667eea;
            font-size: 16px;
        }

        .leave-info-content label {
            display: block;
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2px;
        }

        .leave-info-content span {
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
        }

        .reason-box {
            background: #f8fafc;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
            margin-bottom: 20px;
        }

        .reason-box h4 {
            font-size: 12px;
            color: var(--muted);
            margin: 0 0 8px 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reason-box p {
            font-size: 14px;
            color: var(--text);
            margin: 0;
            line-height: 1.6;
        }

        .reason-box.approved {
            border-left-color: #10b981;
        }

        .reason-box.rejected {
            border-left-color: #ef4444;
        }

        .reason-box .reviewer-info {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: var(--muted);
        }

        /* Action Form */
        .action-form {
            display: flex;
            gap: 15px;
            align-items: flex-end;
            padding-top: 20px;
            border-top: 1px solid #f1f5f9;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--muted);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
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
            box-shadow: 0 6px 20px rgba(16, 185, 129, 0.4);
        }

        .btn-reject {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-reject:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .empty-state-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .empty-state-icon i {
            font-size: 40px;
            color: #667eea;
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 16px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 30px;
            }

            .header-badge {
                width: 100%;
            }

            .action-form {
                flex-direction: column;
            }

            .leave-info-grid {
                grid-template-columns: 1fr 1fr;
            }

            .request-card-header {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-clipboard-check"></i> Leave Approvals</h1>
                <p>Review and manage employee leave requests</p>
            </div>
            <?php if ($pendingCount > 0): ?>
            <div class="header-badge">
                <div class="count"><?= $pendingCount ?></div>
                <div class="label">Pending Review</div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Alerts -->
        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span><?= htmlspecialchars($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <a href="?status=pending" class="stat-card orange <?= $filterStatus === 'pending' ? 'active' : '' ?>">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $pendingCount ?></div>
                        <div class="stat-label">Pending Requests</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
            </a>

            <a href="?status=approved" class="stat-card green <?= $filterStatus === 'approved' ? 'active' : '' ?>">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= count($approvedRequests) ?></div>
                        <div class="stat-label">Approved</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
            </a>

            <a href="?status=rejected" class="stat-card red <?= $filterStatus === 'rejected' ? 'active' : '' ?>">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= count($rejectedRequests) ?></div>
                        <div class="stat-label">Rejected</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-times-circle"></i>
                    </div>
                </div>
            </a>

            <a href="?status=all" class="stat-card purple <?= $filterStatus === 'all' ? 'active' : '' ?>">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?= $totalRequests ?></div>
                        <div class="stat-label">Total Requests</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-list"></i>
                    </div>
                </div>
            </a>
        </div>

        <!-- Section Title -->
        <h2 class="section-title">
            <i class="fas fa-<?= $filterStatus === 'pending' ? 'clock' : ($filterStatus === 'approved' ? 'check-circle' : ($filterStatus === 'rejected' ? 'times-circle' : 'list')) ?>"></i>
            <?= ucfirst($filterStatus) ?> Leave Requests
            <span class="count-badge"><?= count($leaveRequests) ?></span>
        </h2>

        <!-- Leave Requests -->
        <?php if (empty($leaveRequests)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-inbox"></i>
                </div>
                <h3>No <?= $filterStatus ?> requests</h3>
                <p>There are no <?= $filterStatus ?> leave requests at this time.</p>
            </div>
        <?php else: ?>
            <div class="requests-container">
                <?php foreach ($leaveRequests as $request): 
                    $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / 86400 + 1;
                    $initials = '';
                    if ($request['employee_name']) {
                        $nameParts = explode(' ', $request['employee_name']);
                        $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                    }
                ?>
                <div class="request-card">
                    <div class="request-card-header">
                        <div class="employee-info">
                            <div class="employee-avatar"><?= $initials ?></div>
                            <div class="employee-details">
                                <h3><?= htmlspecialchars($request['employee_name']) ?></h3>
                                <p><?= htmlspecialchars($request['designation']) ?> • <?= htmlspecialchars($request['department_name'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <span class="status-badge <?= strtolower($request['status']) ?>">
                            <?= ucfirst($request['status']) ?>
                        </span>
                    </div>

                    <div class="request-card-body">
                        <div class="leave-info-grid">
                            <div class="leave-info-item">
                                <div class="leave-info-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <div class="leave-info-content">
                                    <label>Leave Type</label>
                                    <span><?= ucfirst($request['leave_type']) ?> Leave</span>
                                </div>
                            </div>
                            <div class="leave-info-item">
                                <div class="leave-info-icon">
                                    <i class="fas fa-calendar-alt"></i>
                                </div>
                                <div class="leave-info-content">
                                    <label>Duration</label>
                                    <span><?= date('M d', strtotime($request['start_date'])) ?> - <?= date('M d, Y', strtotime($request['end_date'])) ?></span>
                                </div>
                            </div>
                            <div class="leave-info-item">
                                <div class="leave-info-icon">
                                    <i class="fas fa-hourglass-half"></i>
                                </div>
                                <div class="leave-info-content">
                                    <label>Days</label>
                                    <span><?= $days ?> day<?= $days > 1 ? 's' : '' ?></span>
                                </div>
                            </div>
                            <div class="leave-info-item">
                                <div class="leave-info-icon">
                                    <i class="fas fa-paper-plane"></i>
                                </div>
                                <div class="leave-info-content">
                                    <label>Requested</label>
                                    <span><?= isset($request['request_date']) ? date('M d, Y', strtotime($request['request_date'])) : 'N/A' ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="reason-box">
                            <h4><i class="fas fa-comment-alt"></i> Reason for Leave</h4>
                            <p><?= htmlspecialchars($request['reason']) ?></p>
                        </div>

                        <?php if ($request['status'] === 'pending'): ?>
                        <form method="POST" action="" class="action-form">
                            <input type="hidden" name="request_id" value="<?= $request['leave_id'] ?>">
                            
                            <div class="form-group">
                                <label>Comments (Required for rejection)</label>
                                <input type="text" name="comments" placeholder="Enter your comments here...">
                            </div>

                            <button type="submit" name="approve" class="btn btn-approve">
                                <i class="fas fa-check"></i> Approve
                            </button>

                            <button type="submit" name="reject" class="btn btn-reject">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </form>
                        <?php else: ?>
                            <?php if ($request['review_comments']): ?>
                                <div class="reason-box <?= $request['status'] ?>">
                                    <h4><i class="fas fa-<?= $request['status'] === 'approved' ? 'check' : 'times' ?>-circle"></i> Review Comments</h4>
                                    <p><?= htmlspecialchars($request['review_comments']) ?></p>
                                    <div class="reviewer-info">
                                        <i class="fas fa-user"></i> Reviewed by <?= htmlspecialchars($request['reviewed_by_name'] ?? 'N/A') ?> 
                                        on <?= isset($request['review_date']) ? date('M d, Y', strtotime($request['review_date'])) : 'N/A' ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

</body>
</html>
