<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Director';
$userId = $_SESSION['user_id'] ?? null;

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Fetch pending role change requests
$stmt = $db->prepare("
    SELECT 
        rcr.*,
        e.full_name,
        e.email,
        e.department_id,
        d.department_name
    FROM role_change_requests rcr
    JOIN employees e ON rcr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY 
        CASE rcr.status 
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        rcr.request_date DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count pending requests
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;
foreach ($requests as $req) {
    if ($req['status'] === 'pending') {
        $pending_count++;
    } elseif ($req['status'] === 'approved') {
        $approved_count++;
    } elseif ($req['status'] === 'rejected') {
        $rejected_count++;
    }
}

// Check for action status
$approved = isset($_GET['approved']);
$rejected = isset($_GET['rejected']);
$error = $_GET['error'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Change Approvals - Director Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            color: #667eea;
            font-size: 28px;
            font-weight: 600;
        }

        .header .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-card .icon {
            font-size: 40px;
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .stat-card.pending .icon {
            background: #fff3cd;
            color: #ff9800;
        }

        .stat-card.approved .icon {
            background: #d4edda;
            color: #28a745;
        }

        .stat-card.rejected .icon {
            background: #f8d7da;
            color: #dc3545;
        }

        .stat-card .content h3 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-card .content p {
            color: #666;
            font-size: 14px;
            margin: 0;
        }

        .requests-section {
            background: white;
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .requests-section h2 {
            color: #333;
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #667eea;
        }

        .request-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #667eea;
            transition: all 0.3s ease;
        }

        .request-card:hover {
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }

        .request-card.pending {
            border-left-color: #0c5377;
            background: #e8f4f8;
        }

        .request-card.approved {
            border-left-color: #28a745;
            background: #d4edda;
        }

        .request-card.rejected {
            border-left-color: #dc3545;
            background: #f8d7da;
        }

        .request-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .request-header h3 {
            font-size: 18px;
            color: #333;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .status-badge {
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.pending {
            background: #0c5377;
            color: white;
        }

        .status-badge.approved {
            background: #28a745;
            color: white;
        }

        .status-badge.rejected {
            background: #dc3545;
            color: white;
        }

        .request-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .detail-item label {
            font-size: 12px;
            color: #666;
            font-weight: 600;
            text-transform: uppercase;
        }

        .detail-item span {
            font-size: 15px;
            color: #333;
            font-weight: 500;
        }

        .role-change-highlight {
            background: #fff3cd;
            padding: 10px;
            border-radius: 5px;
            font-weight: 600;
            color: #b35c00;
        }

        .reason-box {
            background: #f0f0f0;
            padding: 12px;
            border-left: 3px solid #667eea;
            margin-top: 10px;
            border-radius: 5px;
            font-style: italic;
            font-size: 14px;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn-approve, .btn-reject {
            padding: 8px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(40, 167, 69, 0.3);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(220, 53, 69, 0.3);
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: #666;
        }

        .empty-state i {
            font-size: 50px;
            color: #ccc;
            margin-bottom: 15px;
        }

        .success-banner {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }

        .error-banner {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }

        .back-link {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .back-link:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="director_dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="header">
            <div>
                <h1><i class="fas fa-user-check"></i> Role Change Approvals</h1>
                <p style="color: #999; margin-top: 5px;">Review and approve employee role changes</p>
            </div>
            <div class="user-info">
                <div style="text-align: right;">
                    <p style="margin: 0; color: #333; font-weight: 600;">Welcome, <?php echo htmlspecialchars($username); ?></p>
                    <small style="color: #999;">Director</small>
                </div>
                <img src="https://via.placeholder.com/50" alt="User" style="width: 50px; height: 50px; border-radius: 50%;">
            </div>
        </div>

        <?php if ($approved): ?>
            <div class="success-banner">
                <i class="fas fa-check-circle"></i> Role change request approved successfully!
            </div>
        <?php endif; ?>

        <?php if ($rejected): ?>
            <div class="success-banner">
                <i class="fas fa-check-circle"></i> Role change request rejected. Employee has been notified.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="error-banner">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="stats-cards">
            <div class="stat-card pending">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="content">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending Approvals</p>
                </div>
            </div>

            <div class="stat-card approved">
                <div class="icon">
                    <i class="fas fa-check"></i>
                </div>
                <div class="content">
                    <h3><?php echo $approved_count; ?></h3>
                    <p>Approved</p>
                </div>
            </div>

            <div class="stat-card rejected">
                <div class="icon">
                    <i class="fas fa-times"></i>
                </div>
                <div class="content">
                    <h3><?php echo $rejected_count; ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <div class="requests-section">
            <h2><i class="fas fa-list"></i> All Role Change Requests</h2>

            <?php if (count($requests) === 0): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Requests</h3>
                    <p>There are no role change requests at this time.</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $req): ?>
                    <div class="request-card <?php echo $req['status']; ?>">
                        <div class="request-header">
                            <div>
                                <h3>
                                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($req['full_name']); ?>
                                </h3>
                                <small style="color: #666;">Employee ID: <?php echo $req['employee_id']; ?></small>
                            </div>
                            <span class="status-badge <?php echo $req['status']; ?>">
                                <?php echo ucfirst($req['status']); ?>
                            </span>
                        </div>

                        <div class="request-details">
                            <div class="detail-item">
                                <label><i class="fas fa-briefcase"></i> Department</label>
                                <span><?php echo htmlspecialchars($req['department_name'] ?? 'N/A'); ?></span>
                            </div>

                            <div class="detail-item">
                                <label><i class="fas fa-envelope"></i> Email</label>
                                <span><?php echo htmlspecialchars($req['email']); ?></span>
                            </div>

                            <div class="detail-item">
                                <label><i class="fas fa-calendar"></i> Request Date</label>
                                <span><?php echo date('d M Y, h:i A', strtotime($req['request_date'])); ?></span>
                            </div>

                            <div class="detail-item">
                                <label><i class="fas fa-user-tie"></i> Requested By</label>
                                <span><?php echo htmlspecialchars($req['requested_by_name'] ?? 'System Admin'); ?></span>
                            </div>
                        </div>

                        <div class="role-change-highlight">
                            <i class="fas fa-exchange-alt"></i>
                            <?php echo htmlspecialchars($req['old_role']); ?> 
                            <i class="fas fa-arrow-right"></i> 
                            <?php echo htmlspecialchars($req['new_role']); ?>
                        </div>

                        <div class="reason-box">
                            <strong><i class="fas fa-comment"></i> Change Reason:</strong><br>
                            <?php echo htmlspecialchars($req['change_reason']); ?>
                        </div>

                        <?php if ($req['status'] !== 'pending'): ?>
                            <div class="reason-box" style="background: #f0f0f0; border-left-color: #666; margin-top: 10px;">
                                <strong><i class="fas fa-comment"></i> Review Comments:</strong><br>
                                <?php echo htmlspecialchars($req['review_comments'] ?? 'No additional comments'); ?><br>
                                <small style="color: #666; margin-top: 5px;">Reviewed by: <?php echo htmlspecialchars($req['reviewed_by_name'] ?? 'System'); ?> on <?php echo date('d M Y, h:i A', strtotime($req['review_date'])); ?></small>
                            </div>
                        <?php endif; ?>

                        <?php if ($req['status'] === 'pending'): ?>
                            <div class="action-buttons">
                                <button class="btn-approve" onclick="approveRequest(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['full_name']); ?>', '<?php echo htmlspecialchars($req['new_role']); ?>')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn-reject" onclick="rejectRequest(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['full_name']); ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 5px 30px rgba(0,0,0,0.3);">
            <h3 style="margin-bottom: 15px; color: #333;">
                <i class="fas fa-check-circle" style="color: #28a745;"></i> Approve Role Change
            </h3>
            <p id="approveMessage" style="color: #666; margin-bottom: 15px;"></p>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Comments (Optional)</label>
                <textarea id="approveComments" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; min-height: 80px; font-family: inherit;" placeholder="Add any comments about this approval..."></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeApproveModal()" style="padding: 10px 20px; background: #e0e0e0; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button onclick="submitApprove()" style="padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-check"></i> Confirm Approval
                </button>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
        <div style="background: white; padding: 30px; border-radius: 15px; width: 90%; max-width: 500px; box-shadow: 0 5px 30px rgba(0,0,0,0.3);">
            <h3 style="margin-bottom: 15px; color: #333;">
                <i class="fas fa-times-circle" style="color: #dc3545;"></i> Reject Role Change
            </h3>
            <p id="rejectMessage" style="color: #666; margin-bottom: 15px;"></p>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #333;">Reason for Rejection <span style="color: #dc3545;">*</span></label>
                <textarea id="rejectComments" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; min-height: 80px; font-family: inherit;" placeholder="Please explain why you are rejecting this request..." required></textarea>
            </div>

            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                <button onclick="closeRejectModal()" style="padding: 10px 20px; background: #e0e0e0; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">Cancel</button>
                <button onclick="submitReject()" style="padding: 10px 20px; background: #dc3545; color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-times"></i> Confirm Rejection
                </button>
            </div>
        </div>
    </div>

    <script>
        let currentRequestId = null;

        function approveRequest(requestId, employeeName, newRole) {
            currentRequestId = requestId;
            document.getElementById('approveMessage').innerHTML = 
                `Are you sure you want to approve the role change for <strong>${employeeName}</strong> to <strong>${newRole}</strong>?`;
            document.getElementById('approveModal').style.display = 'flex';
        }

        function closeApproveModal() {
            document.getElementById('approveModal').style.display = 'none';
            document.getElementById('approveComments').value = '';
            currentRequestId = null;
        }

        function submitApprove() {
            const comments = document.getElementById('approveComments').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../../app/Controllers/RoleChangeApprovalController.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'approve';
            form.appendChild(actionInput);

            const requestIdInput = document.createElement('input');
            requestIdInput.type = 'hidden';
            requestIdInput.name = 'request_id';
            requestIdInput.value = currentRequestId;
            form.appendChild(requestIdInput);

            const commentsInput = document.createElement('input');
            commentsInput.type = 'hidden';
            commentsInput.name = 'review_comments';
            commentsInput.value = comments;
            form.appendChild(commentsInput);

            document.body.appendChild(form);
            form.submit();
        }

        function rejectRequest(requestId, employeeName) {
            currentRequestId = requestId;
            document.getElementById('rejectMessage').innerHTML = 
                `Are you sure you want to reject the role change for <strong>${employeeName}</strong>?`;
            document.getElementById('rejectModal').style.display = 'flex';
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
            document.getElementById('rejectComments').value = '';
            currentRequestId = null;
        }

        function submitReject() {
            const comments = document.getElementById('rejectComments').value;
            if (!comments.trim()) {
                alert('Please provide a reason for rejection');
                return;
            }

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '../../app/Controllers/RoleChangeApprovalController.php';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'reject';
            form.appendChild(actionInput);

            const requestIdInput = document.createElement('input');
            requestIdInput.type = 'hidden';
            requestIdInput.name = 'request_id';
            requestIdInput.value = currentRequestId;
            form.appendChild(requestIdInput);

            const commentsInput = document.createElement('input');
            commentsInput.type = 'hidden';
            commentsInput.name = 'review_comments';
            commentsInput.value = comments;
            form.appendChild(commentsInput);

            document.body.appendChild(form);
            form.submit();
        }
    </script>
</body>
</html>
