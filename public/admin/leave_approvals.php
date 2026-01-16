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
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .stats-grid-leaves {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card-leave {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
            text-decoration: none;
        }

        .stat-card-leave:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.08);
        }

        .stat-card-leave.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .stat-card-leave.active .stat-label { color: rgba(255,255,255,0.8); }
        .stat-card-leave.active .stat-number { color: white; }
        .stat-card-leave.active .icon-bg { color: rgba(255,255,255,0.2); }

        .icon-bg {
            font-size: 40px;
            color: rgba(0,0,0,0.05);
            position: absolute;
            right: 15px;
            bottom: -5px;
            pointer-events: none;
        }

        .request-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            transition: all 0.3s;
            position: relative;
        }

        .request-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.06);
            border-color: #667eea;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #f7fafc;
        }

        .emp-profile {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .emp-avatar {
            width: 54px;
            height: 54px;
            border-radius: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: 700;
        }

        .request-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            color: #4a5568;
        }

        .meta-item i {
            color: #667eea;
            width: 20px;
            text-align: center;
        }

        .reason-content {
            background: #fff;
            padding: 15px;
            border-radius: 10px;
            border-left: 4px solid #ed8936;
            background: #fffaf0;
            margin-bottom: 20px;
        }

        .reason-content h4 {
            font-size: 12px;
            color: #c05621;
            text-transform: uppercase;
            margin-bottom: 8px;
            font-weight: 700;
        }

        .reason-content p {
            font-size: 14px;
            color: #2d3748;
            line-height: 1.5;
        }

        .action-bar {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 12px;
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .action-input {
            flex: 1;
            padding: 10px 15px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        .action-input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        @media (max-width: 992px) {
            .stats-grid-leaves { grid-template-columns: 1fr; }
            .action-bar { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1>Leave Approvals</h1>
                <p>Manage and review employee leave requests.</p>
            </div>

            <?php if ($success): ?>
                <div class="alert alert-success" style="background: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-error" style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <!-- Stats -->
            <div class="stats-grid-leaves">
                <a href="?status=pending" class="stat-card-leave <?= $filterStatus === 'pending' ? 'active' : '' ?>">
                    <div>
                        <div class="stat-number" style="font-size: 32px; font-weight: 700; color: #d69e2e;"><?= $pendingCount ?></div>
                        <div class="stat-label" style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 600;">Pending Requests</div>
                    </div>
                    <i class="fas fa-clock icon-bg"></i>
                </a>
                <a href="?status=approved" class="stat-card-leave <?= $filterStatus === 'approved' ? 'active' : '' ?>">
                    <div>
                         <div class="stat-number" style="font-size: 32px; font-weight: 700; color: #48bb78;"><?= count($approvedRequests) ?></div>
                         <div class="stat-label" style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 600;">Approved</div>
                    </div>
                    <i class="fas fa-check-circle icon-bg"></i>
                </a>
                <a href="?status=rejected" class="stat-card-leave <?= $filterStatus === 'rejected' ? 'active' : '' ?>">
                    <div>
                         <div class="stat-number" style="font-size: 32px; font-weight: 700; color: #f56565;"><?= count($rejectedRequests) ?></div>
                         <div class="stat-label" style="font-size: 13px; color: #718096; text-transform: uppercase; font-weight: 600;">Rejected</div>
                    </div>
                    <i class="fas fa-times-circle icon-bg"></i>
                </a>
            </div>

            <!-- List -->
            <div class="glass-card">
                <div class="card-header" style="border-bottom: 1px solid #f0f0f0; padding: 20px;">
                    <h3 style="margin: 0; color: #2d3748; font-size: 18px;"><i class="fas fa-list" style="margin-right: 10px; color: #667eea;"></i> <?= ucfirst($filterStatus) ?> Requests</h3>
                </div>
                <div class="card-body" style="padding: 24px;">
                    <?php if (empty($leaveRequests)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #a0aec0;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
                            <p style="font-size: 16px;">No <?= $filterStatus ?> leave requests found.</p>
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
                        <div class="request-card">
                            <div class="request-header">
                                <div class="emp-profile">
                                    <div class="emp-avatar"><?= $initials ?></div>
                                    <div>
                                        <h3 style="margin: 0; font-size: 16px; color: #2d3748;"><?= htmlspecialchars($request['employee_name']) ?></h3>
                                        <div style="font-size: 12px; color: #718096; margin-top: 4px;"><?= htmlspecialchars($request['designation']) ?> • <?= htmlspecialchars($request['department_name'] ?? 'N/A') ?></div>
                                    </div>
                                </div>
                                <span class="status-badge <?= strtolower($request['status']) ?>"><?= ucfirst($request['status']) ?></span>
                            </div>

                            <div class="request-meta">
                                <div class="meta-item">
                                    <i class="fas fa-tag"></i>
                                    <span><strong><?= ucfirst($request['leave_type']) ?></strong> Leave</span>
                                </div>
                                <div class="meta-item">
                                    <i class="far fa-calendar-alt"></i>
                                    <span><?= date('M d, Y', strtotime($request['start_date'])) ?> - <?= date('M d, Y', strtotime($request['end_date'])) ?></span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?= $days ?> Days</span>
                                </div>
                                <div class="meta-item">
                                    <i class="fas fa-history"></i>
                                    <span>Applied: <?= date('M d, Y', strtotime($request['request_date'])) ?></span>
                                </div>
                            </div>

                            <div class="reason-content">
                                <h4>Reason for Leave</h4>
                                <p><?= htmlspecialchars($request['reason']) ?></p>
                            </div>

                            <?php if ($request['status'] === 'pending'): ?>
                                <form method="POST" class="action-bar">
                                    <input type="hidden" name="request_id" value="<?= $request['leave_id'] ?>">
                                    <input type="text" name="comments" class="action-input" placeholder="Add comments (optional for approval)...">
                                    <div style="display: flex; gap: 10px;">
                                        <button type="submit" name="approve" class="glass-btn btn" style="background: #48bb78; color: white; border: none;">
                                            <i class="fas fa-check"></i> Approve
                                        </button>
                                        <button type="submit" name="reject" class="glass-btn btn" style="background: #f56565; color: white; border: none;">
                                            <i class="fas fa-times"></i> Reject
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <?php if ($request['review_comments']): ?>
                                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; border-left: 4px solid #718096;">
                                        <h4 style="font-size: 12px; color: #718096; text-transform: uppercase; margin-bottom: 5px;">Admin Comments</h4>
                                        <p style="font-size: 13px; color: #2d3748;"><?= htmlspecialchars($request['review_comments']) ?></p>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
