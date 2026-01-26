<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasDirectorRole = in_array('director', $userRoles);
if (!isset($_SESSION['role']) || (!$hasDirectorRole && $_SESSION['role'] !== 'director')) {
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
    <?php include 'includes/director_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-user-check"></i> Role Change Approvals</h1>
            <p>Review and approve employee role changes.</p>
        </div>

        <?php if ($approved): ?>
            <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #065f46;">
                <i class="fas fa-check-circle"></i> Role change request approved successfully!
            </div>
        <?php endif; ?>

        <?php if ($rejected): ?>
            <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #b91c1c;">
                <i class="fas fa-exclamation-circle"></i> Role change request rejected.
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #ffedd5; border-left: 4px solid #f97316; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #9a3412;">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="live-stats-container">
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-orange text-white">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $pending_count; ?></h4>
                    <span>Pending</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $approved_count; ?></h4>
                    <span>Approved</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-pink text-white">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $rejected_count; ?></h4>
                    <span>Rejected</span>
                </div>
            </div>
        </div>

        <h3 style="font-size: 18px; color: #2d3748; margin-bottom: 20px; font-weight: 700; margin-top: 30px;">Request List</h3>

        <?php if (count($requests) === 0): ?>
            <div style="text-align: center; padding: 50px; background: white; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e0; margin-bottom: 15px;"></i>
                <p style="color: #718096;">There are no role change requests at this time.</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $req): ?>
                <div class="request-card <?php echo $req['status']; ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 4px;">
                                <i class="fas fa-user-circle" style="color: #667eea;"></i>
                                <?php echo htmlspecialchars($req['full_name']); ?>
                            </h3>
                            <div style="font-size: 13px; color: #718096;">
                                Employee ID: <?php echo $req['employee_id']; ?>
                            </div>
                        </div>
                        <?php 
                            $statusClass = 'badge-warning';
                            if($req['status'] === 'approved') $statusClass = 'badge-success';
                            if($req['status'] === 'rejected') $statusClass = 'badge-danger';
                        ?>
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo ucfirst($req['status']); ?>
                        </span>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Department</label>
                            <div class="value"><?php echo htmlspecialchars($req['department_name'] ?? 'N/A'); ?></div>
                        </div>

                        <div class="detail-item">
                            <label>Email</label>
                            <div class="value"><?php echo htmlspecialchars($req['email']); ?></div>
                        </div>

                        <div class="detail-item">
                            <label>Request Date</label>
                            <div class="value"><?php echo date('d M Y, h:i A', strtotime($req['request_date'])); ?></div>
                        </div>

                        <div class="detail-item">
                            <label>Requested By</label>
                            <div class="value"><?php echo htmlspecialchars($req['requested_by_name'] ?? 'System Admin'); ?></div>
                        </div>
                    </div>

                    <div style="background: rgba(255, 243, 205, 0.5); border: 1px solid rgba(255, 243, 205, 1); padding: 12px; border-radius: 8px; margin-bottom: 15px; font-weight: 600; color: #856404; display: flex; align-items: center; gap: 10px; margin-top: 15px;">
                        <?php echo htmlspecialchars($req['old_role']); ?>
                        <i class="fas fa-arrow-right" style="color: #856404;"></i>
                        <?php echo htmlspecialchars($req['new_role']); ?>
                    </div>

                    <div style="background: #f7fafc; padding: 12px; border-radius: 8px; margin-bottom: 15px; border-left: 3px solid #667eea;">
                        <strong>Change Reason:</strong><br>
                        <?php echo htmlspecialchars($req['change_reason']); ?>
                    </div>

                    <?php if ($req['status'] !== 'pending'): ?>
                        <div style="background: #f7fafc; padding: 12px; border-radius: 8px; margin-top: 10px; font-size: 13px; color: #718096;">
                            <strong>Review Comments:</strong><br>
                            <?php echo htmlspecialchars($req['review_comments'] ?? 'No additional comments'); ?><br>
                            <div style="margin-top: 5px; font-style: italic;">
                                Reviewed by: <?php echo htmlspecialchars($req['reviewed_by_name'] ?? 'System'); ?> on <?php echo date('d M Y, h:i A', strtotime($req['review_date'])); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if ($req['status'] === 'pending'): ?>
                        <div style="display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #f0f0f0; padding-top: 15px;">
                            <button class="btn btn-success" style="border: none;" onclick="approveRequest(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['full_name']); ?>', '<?php echo htmlspecialchars($req['new_role']); ?>')">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger" style="border: none;" onclick="rejectRequest(<?php echo $req['request_id']; ?>, '<?php echo htmlspecialchars($req['full_name']); ?>')">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle" style="color: #10b981;"></i> Approve Role Change</h3>
            </div>
            <p id="approveMessage" style="color: #718096; margin-bottom: 20px;"></p>
            
            <div class="form-group">
                <label for="approveComments">Comments (Optional)</label>
                <textarea id="approveComments" placeholder="Add any comments about this approval..."></textarea>
            </div>

            <div class="modal-actions">
                <button onclick="closeApproveModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="submitApprove()" class="btn btn-success">
                    <i class="fas fa-check"></i> Confirm Approval
                </button>
            </div>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Role Change</h3>
            </div>
            <p id="rejectMessage" style="color: #718096; margin-bottom: 20px;"></p>
            
            <div class="form-group">
                <label for="rejectComments">Reason for Rejection <span style="color: #ef4444;">*</span></label>
                <textarea id="rejectComments" required placeholder="Please explain why you are rejecting this request..."></textarea>
            </div>

            <div class="modal-actions">
                <button onclick="closeRejectModal()" class="btn btn-secondary">Cancel</button>
                <button onclick="submitReject()" class="btn btn-danger">
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
            document.getElementById('approveModal').classList.add('show');
        }

        function closeApproveModal() {
            document.getElementById('approveModal').classList.remove('show');
            document.getElementById('approveComments').value = '';
            currentRequestId = null;
        }

        function submitApprove() {
            const comments = document.getElementById('approveComments').value;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '/payslip_generator/public/index.php?page=approve-role-change';
            
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
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeRejectModal() {
            document.getElementById('rejectModal').classList.remove('show');
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
            form.action = '/payslip_generator/public/index.php?page=reject-role-change';
            
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
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }
    </script>
</body>
</html>
