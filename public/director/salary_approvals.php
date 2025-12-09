<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Director';

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Fetch pending salary change requests
$stmt = $db->prepare("
    SELECT 
        scr.*,
        e.full_name,
        e.email,
        e.department_id,
        d.department_name
    FROM salary_change_requests scr
    JOIN employees e ON scr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY 
        CASE scr.status 
            WHEN 'pending' THEN 1
            WHEN 'approved' THEN 2
            WHEN 'rejected' THEN 3
        END,
        scr.request_date DESC
");
$stmt->execute();
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count pending requests
$pending_count = 0;
foreach ($requests as $req) {
    if ($req['status'] === 'pending') {
        $pending_count++;
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
    <title>Salary Approvals - Director Portal</title>
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
            border-left-color: #ff9800;
            background: #fff8e1;
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
            background: #ff9800;
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

        .detail-item .value {
            font-size: 16px;
            color: #333;
            font-weight: 500;
        }

        .detail-item .value.highlight {
            color: #667eea;
            font-weight: 700;
            font-size: 18px;
        }

        .salary-comparison {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .salary-comparison .arrow {
            color: #ff9800;
            font-size: 20px;
        }

        .change-info {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }

        .change-info .type {
            display: inline-block;
            padding: 4px 12px;
            background: #667eea;
            color: white;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 10px;
        }

        .change-info .reason {
            color: #555;
            font-size: 14px;
            line-height: 1.6;
        }

        .request-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-approve, .btn-reject {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-approve {
            background: #28a745;
            color: white;
        }

        .btn-approve:hover {
            background: #218838;
            transform: translateY(-2px);
        }

        .btn-reject {
            background: #dc3545;
            color: white;
        }

        .btn-reject:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .review-info {
            background: white;
            padding: 12px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }

        .review-info strong {
            color: #333;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }

        .modal-header {
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #333;
            font-size: 22px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }

        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 100px;
        }

        .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }

        .btn-cancel {
            padding: 10px 25px;
            background: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .btn-cancel:hover {
            background: #5a6268;
        }

        .btn-submit {
            padding: 10px 25px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            color: white;
        }

        .btn-submit.approve {
            background: #28a745;
        }

        .btn-submit.approve:hover {
            background: #218838;
        }

        .btn-submit.reject {
            background: #dc3545;
        }

        .btn-submit.reject:hover {
            background: #c82333;
        }

        .no-requests {
            text-align: center;
            padding: 60px 20px;
            color: #999;
        }

        .no-requests i {
            font-size: 80px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .no-requests h3 {
            font-size: 24px;
            margin-bottom: 10px;
        }

        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1><i class="fas fa-hand-holding-usd"></i> Salary Change Approvals</h1>
            </div>
            <div class="user-info">
                <a href="director_dashboard.php" class="back-link">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <span style="color: #666;">Welcome, <strong><?php echo htmlspecialchars($username); ?></strong></span>
            </div>
        </div>

        <?php if ($approved): ?>
            <div style="background:#d4edda;border:1px solid #b6e0c5;color:#155724;padding:15px 20px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-check-circle" style="font-size:20px;"></i>
                <div>
                    <strong>Salary change approved successfully!</strong>
                    <p style="margin-bottom:0;font-size:13px;">The employee's salary has been updated and the admin has been notified.</p>
                </div>
            </div>
        <?php elseif ($rejected): ?>
            <div style="background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px 20px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-circle" style="font-size:20px;"></i>
                <div>
                    <strong>Salary change request rejected.</strong>
                    <p style="margin-bottom:0;font-size:13px;">The admin has been notified of the rejection and your comments.</p>
                </div>
            </div>
        <?php elseif ($error === 'database_error'): ?>
            <div style="background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px 20px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;">
                <i class="fas fa-exclamation-triangle" style="font-size:20px;"></i>
                <div>
                    <strong>Database error occurred.</strong>
                    <p style="margin-bottom:0;font-size:13px;">Please try again or contact the system administrator.</p>
                </div>
            </div>
        <?php endif; ?>

        <div class="stats-cards">
            <div class="stat-card pending">
                <div class="icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="content">
                    <h3><?php echo $pending_count; ?></h3>
                    <p>Pending Requests</p>
                </div>
            </div>
            <div class="stat-card approved">
                <div class="icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="content">
                    <h3><?php 
                        $approved_count = 0;
                        foreach ($requests as $req) {
                            if ($req['status'] === 'approved') $approved_count++;
                        }
                        echo $approved_count;
                    ?></h3>
                    <p>Approved</p>
                </div>
            </div>
            <div class="stat-card rejected">
                <div class="icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="content">
                    <h3><?php 
                        $rejected_count = 0;
                        foreach ($requests as $req) {
                            if ($req['status'] === 'rejected') $rejected_count++;
                        }
                        echo $rejected_count;
                    ?></h3>
                    <p>Rejected</p>
                </div>
            </div>
        </div>

        <div class="requests-section">
            <h2><i class="fas fa-list"></i> Salary Change Requests</h2>
            
            <?php if (empty($requests)): ?>
                <div class="no-requests">
                    <i class="fas fa-inbox"></i>
                    <h3>No Salary Change Requests</h3>
                    <p>All salary change requests will appear here for your review.</p>
                </div>
            <?php else: ?>
                <?php foreach ($requests as $request): ?>
                    <div class="request-card <?php echo strtolower($request['status']); ?>">
                        <div class="request-header">
                            <div>
                                <h3><?php echo htmlspecialchars($request['employee_name']); ?></h3>
                                <p style="color: #666; font-size: 14px;">
                                    <?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?> • 
                                    Employee ID: <?php echo $request['employee_id']; ?>
                                </p>
                            </div>
                            <span class="status-badge <?php echo strtolower($request['status']); ?>">
                                <?php echo strtoupper($request['status']); ?>
                            </span>
                        </div>

                        <div class="change-info">
                            <div class="type"><?php echo htmlspecialchars($request['change_type']); ?></div>
                            <div class="reason">
                                <strong>Reason:</strong> <?php echo htmlspecialchars($request['change_reason']); ?>
                            </div>
                        </div>

                        <div class="request-details">
                            <div class="detail-item">
                                <label>Current Salary</label>
                                <div class="value">₹<?php echo number_format($request['current_salary'], 2); ?></div>
                            </div>
                            <div class="detail-item">
                                <label>New Salary</label>
                                <div class="value highlight">₹<?php echo number_format($request['new_salary'], 2); ?></div>
                            </div>
                            <div class="detail-item">
                                <label>Increase</label>
                                <div class="value" style="color: #28a745;">
                                    +₹<?php echo number_format($request['new_salary'] - $request['current_salary'], 2); ?>
                                    (<?php 
                                        $increase_percent = (($request['new_salary'] - $request['current_salary']) / $request['current_salary']) * 100;
                                        echo number_format($increase_percent, 2); 
                                    ?>%)
                                </div>
                            </div>
                            <div class="detail-item">
                                <label>Requested By</label>
                                <div class="value"><?php echo htmlspecialchars($request['requested_by_name']); ?></div>
                            </div>
                            <div class="detail-item">
                                <label>Request Date</label>
                                <div class="value"><?php echo date('d M Y, h:i A', strtotime($request['request_date'])); ?></div>
                            </div>
                        </div>

                        <?php if ($request['status'] === 'pending'): ?>
                            <div class="request-actions">
                                <button class="btn-approve" onclick="openApproveModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['employee_name'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button class="btn-reject" onclick="openRejectModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['employee_name'], ENT_QUOTES); ?>')">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                            </div>
                        <?php else: ?>
                            <div class="review-info">
                                <strong><?php echo ucfirst($request['status']); ?> by:</strong> <?php echo htmlspecialchars($request['reviewed_by_name']); ?> 
                                on <?php echo date('d M Y, h:i A', strtotime($request['review_date'])); ?>
                                <?php if ($request['review_comments']): ?>
                                    <br><strong>Comments:</strong> <?php echo htmlspecialchars($request['review_comments']); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> Approve Salary Change</h3>
                <p id="approveEmployeeName" style="color: #666; margin-top: 5px;"></p>
            </div>
            <form id="approveForm" method="POST" action="../../app/Controllers/SalaryApprovalController.php">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approve_request_id">
                <div class="form-group">
                    <label for="approve_comments">Comments (Optional)</label>
                    <textarea name="comments" id="approve_comments" placeholder="Add any comments about this approval..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" class="btn-submit approve">Approve Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle" style="color: #dc3545;"></i> Reject Salary Change</h3>
                <p id="rejectEmployeeName" style="color: #666; margin-top: 5px;"></p>
            </div>
            <form id="rejectForm" method="POST" action="../../app/Controllers/SalaryApprovalController.php">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="reject_request_id">
                <div class="form-group">
                    <label for="reject_comments">Rejection Reason <span style="color: red;">*</span></label>
                    <textarea name="comments" id="reject_comments" required placeholder="Please provide a reason for rejection..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn-submit reject">Reject Request</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openApproveModal(requestId, employeeName) {
            document.getElementById('approve_request_id').value = requestId;
            document.getElementById('approveEmployeeName').textContent = 'Employee: ' + employeeName;
            document.getElementById('approveModal').classList.add('show');
        }

        function openRejectModal(requestId, employeeName) {
            document.getElementById('reject_request_id').value = requestId;
            document.getElementById('rejectEmployeeName').textContent = 'Employee: ' + employeeName;
            document.getElementById('rejectModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        // Form validation for reject
        document.getElementById('rejectForm').addEventListener('submit', function(e) {
            const comments = document.getElementById('reject_comments').value.trim();
            if (!comments) {
                e.preventDefault();
                alert('Please provide a reason for rejection');
            }
        });
    </script>
</body>
</html>
