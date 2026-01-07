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

$leaveModel = new LeaveRequest();

// Handle approval/rejection
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve'])) {
        $requestId = $_POST['request_id'];
        $comments = $_POST['comments'] ?? null;
        
        if ($leaveModel->approveLeaveRequest($requestId, $userId, $username, $comments)) {
            $success = "Leave request approved successfully!";
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .breadcrumb i {
            margin: 0 8px;
            font-size: 10px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 2px solid #e2e8f0;
            transition: all 0.3s;
            cursor: pointer;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }

        .stat-card.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: rgba(102, 126, 234, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 15px;
            color: #667eea;
        }

        .stat-card.active .stat-icon {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }

        .stat-number {
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .request-item {
            background: #f7fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            transition: all 0.3s;
        }

        .request-item:hover {
            border-color: #667eea;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
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
            color: #2d3748;
            margin-bottom: 2px;
        }

        .employee-details p {
            font-size: 13px;
            color: #718096;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-badge.pending {
            background: #feebc8;
            color: #7c2d12;
        }

        .status-badge.approved {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.rejected {
            background: #fed7d7;
            color: #742a2a;
        }

        .leave-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
        }

        .detail-item i {
            color: #667eea;
        }

        .reason-box {
            background: white;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #667eea;
            margin-bottom: 15px;
        }

        .reason-box h4 {
            font-size: 13px;
            color: #718096;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .reason-box p {
            font-size: 14px;
            color: #2d3748;
        }

        .action-form {
            display: flex;
            gap: 10px;
            align-items: flex-end;
        }

        .form-group {
            flex: 1;
        }

        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e0;
            border-radius: 6px;
            font-size: 14px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve {
            background: #48bb78;
            color: white;
        }

        .btn-approve:hover {
            background: #38a169;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #f56565;
            color: white;
        }

        .btn-reject:hover {
            background: #e53e3e;
            transform: translateY(-2px);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .action-form {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="breadcrumb">
                <a href="<?= $hasAdminRole || $_SESSION['role'] === 'administrator' ? 'admin_dashboard.php' : '../director/director_dashboard.php' ?>">
                    <i class="fas fa-home"></i> Dashboard
                </a>
                <i class="fas fa-chevron-right"></i>
                <span>Leave Approvals</span>
            </div>
            <h1><i class="fas fa-clipboard-check"></i> Leave Approvals</h1>
        </div>

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
            <a href="?status=pending" style="text-decoration: none;">
                <div class="stat-card <?= $filterStatus === 'pending' ? 'active' : '' ?>">
                    <div class="stat-icon">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number"><?= $pendingCount ?></div>
                    <div class="stat-label">Pending Requests</div>
                </div>
            </a>

            <a href="?status=approved" style="text-decoration: none;">
                <div class="stat-card <?= $filterStatus === 'approved' ? 'active' : '' ?>">
                    <div class="stat-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="stat-number"><?= count($approvedRequests) ?></div>
                    <div class="stat-label">Approved</div>
                </div>
            </a>

            <a href="?status=rejected" style="text-decoration: none;">
                <div class="stat-card <?= $filterStatus === 'rejected' ? 'active' : '' ?>">
                    <div class="stat-icon">
                        <i class="fas fa-times"></i>
                    </div>
                    <div class="stat-number"><?= count($rejectedRequests) ?></div>
                    <div class="stat-label">Rejected</div>
                </div>
            </a>
        </div>

        <!-- Leave Requests -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-list"></i>
                <h2><?= ucfirst($filterStatus) ?> Leave Requests</h2>
            </div>
            <div class="card-body">
                <?php if (empty($leaveRequests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox"></i>
                        <h3>No <?= $filterStatus ?> requests</h3>
                        <p>There are no <?= $filterStatus ?> leave requests at this time.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($leaveRequests as $request): 
                        $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / 86400 + 1;
                        $initials = '';
                        if ($request['employee_name']) {
                            $nameParts = explode(' ', $request['employee_name']);
                            $initials = strtoupper(substr($nameParts[0], 0, 1) . (isset($nameParts[1]) ? substr($nameParts[1], 0, 1) : ''));
                        }
                    ?>
                    <div class="request-item">
                        <div class="request-header">
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

                        <div class="leave-details">
                            <div class="detail-item">
                                <i class="fas fa-tag"></i>
                                <strong><?= ucfirst($request['leave_type']) ?> Leave</strong>
                            </div>
                            <div class="detail-item">
                                <i class="far fa-calendar"></i>
                                <?= date('M d, Y', strtotime($request['start_date'])) ?> - <?= date('M d, Y', strtotime($request['end_date'])) ?>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-calendar-day"></i>
                                <strong><?= $days ?> days</strong>
                            </div>
                            <div class="detail-item">
                                <i class="fas fa-clock"></i>
                                Requested: <?= isset($request['request_date']) ? date('M d, Y', strtotime($request['request_date'])) : 'N/A' ?>
                            </div>
                        </div>

                        <div class="reason-box">
                            <h4>Reason</h4>
                            <p><?= htmlspecialchars($request['reason']) ?></p>
                        </div>

                        <?php if ($request['status'] === 'pending'): ?>
                        <form method="POST" action="" class="action-form">
                            <input type="hidden" name="request_id" value="<?= $request['leave_id'] ?>">
                            
                            <div class="form-group">
                                <label>Comments (Optional for approval, required for rejection)</label>
                                <input type="text" name="comments" placeholder="Enter your comments...">
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
                                <div class="reason-box" style="border-left-color: <?= $request['status'] === 'approved' ? '#48bb78' : '#f56565' ?>;">
                                    <h4>Review Comments</h4>
                                    <p><?= htmlspecialchars($request['review_comments']) ?></p>
                                    <p style="font-size: 12px; color: #718096; margin-top: 8px;">
                                        By <?= htmlspecialchars($request['reviewed_by_name'] ?? 'N/A') ?> on 
                                        <?= isset($request['review_date']) ? date('M d, Y', strtotime($request['review_date'])) : 'N/A' ?>
                                    </p>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <a href="<?= $hasAdminRole || $_SESSION['role'] === 'administrator' ? 'admin_dashboard.php' : '../director/director_dashboard.php' ?>" 
       class="back-btn" 
       title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>
</body>
</html>
