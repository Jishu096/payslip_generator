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
    <?php include 'includes/director_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-hand-holding-usd"></i> Salary Change Approvals</h1>
            <p>Review and process salary modification requests.</p>
        </div>

        <?php if ($approved): ?>
            <div style="background: #d1fae5; border-left: 4px solid #10b981; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #065f46;">
                <div style="font-weight: 600;"><i class="fas fa-check-circle"></i> Salary change approved successfully!</div>
            </div>
        <?php elseif ($rejected): ?>
            <div style="background: #fee2e2; border-left: 4px solid #ef4444; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #b91c1c;">
                <div style="font-weight: 600;"><i class="fas fa-exclamation-circle"></i> Salary change request rejected.</div>
            </div>
        <?php elseif($error === 'database_error'): ?>
            <div style="background: #ffedd5; border-left: 4px solid #f97316; padding: 15px; margin-bottom: 20px; border-radius: 4px; color: #9a3412;">
                <div style="font-weight: 600;"><i class="fas fa-exclamation-triangle"></i> Database error occurred.</div>
            </div>
        <?php endif; ?>

        <!-- Stats Overview -->
        <div class="live-stats-container">
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-orange text-white">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $pending_count; ?></h4>
                    <span>Pending Requests</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?php 
                        $approved_count = 0;
                        foreach ($requests as $req) { if ($req['status'] === 'approved') $approved_count++; }
                        echo $approved_count;
                    ?></h4>
                    <span>Approved</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-pink text-white">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?php 
                        $rejected_count = 0;
                        foreach ($requests as $req) { if ($req['status'] === 'rejected') $rejected_count++; }
                        echo $rejected_count;
                    ?></h4>
                    <span>Rejected</span>
                </div>
            </div>
        </div>

        <h3 style="font-size: 18px; color: #2d3748; margin-bottom: 20px; font-weight: 700; margin-top: 30px;">Request List</h3>
        
        <?php if (empty($requests)): ?>
            <div style="text-align: center; padding: 50px; background: white; border-radius: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.05);">
                <i class="fas fa-inbox" style="font-size: 48px; color: #cbd5e0; margin-bottom: 15px;"></i>
                <p style="color: #718096;">No requests found</p>
            </div>
        <?php else: ?>
            <?php foreach ($requests as $request): ?>
                <div class="request-card <?php echo strtolower($request['status']); ?>">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 15px;">
                        <div>
                            <h3 style="font-size: 18px; font-weight: 700; color: #2d3748; margin-bottom: 4px;">
                                <?php echo htmlspecialchars($request['employee_name']); ?>
                            </h3>
                            <div style="font-size: 13px; color: #718096;">
                                <?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?> • ID: <?php echo $request['employee_id']; ?>
                            </div>
                        </div>
                        <?php 
                            $statusClass = 'badge-warning';
                            if($request['status'] === 'approved') $statusClass = 'badge-success';
                            if($request['status'] === 'rejected') $statusClass = 'badge-danger';
                        ?>
                        <span class="badge <?php echo $statusClass; ?>">
                            <?php echo strtoupper($request['status']); ?>
                        </span>
                    </div>

                    <div style="background: #f7fafc; padding: 12px; border-radius: 8px; margin-bottom: 15px; border: 1px solid #e2e8f0;">
                        <div style="font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 700; color: #4a5568; margin-bottom: 4px;">
                            <?php echo htmlspecialchars($request['change_type']); ?>
                        </div>
                        <div style="font-size: 14px; color: #2d3748;">
                            <strong style="color: #718096;">Reason:</strong> <?php echo htmlspecialchars($request['change_reason']); ?>
                        </div>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <label>Current Salary</label>
                            <div class="value">₹<?php echo number_format($request['current_salary'], 2); ?></div>
                        </div>
                        <div class="detail-item">
                            <label>New Salary</label>
                            <div class="value" style="color: #2c3e50; font-weight: 700;">₹<?php echo number_format($request['new_salary'], 2); ?></div>
                        </div>
                        <div class="detail-item">
                            <label>Increase</label>
                            <div class="value" style="color: #059669;">
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
                        <div style="display: flex; gap: 10px; margin-top: 20px; border-top: 1px solid #f0f0f0; padding-top: 15px;">
                            <button onclick="openApproveModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['employee_name'], ENT_QUOTES); ?>')" 
                                    class="btn btn-success" style="border: none;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button onclick="openRejectModal(<?php echo $request['request_id']; ?>, '<?php echo htmlspecialchars($request['employee_name'], ENT_QUOTES); ?>')" 
                                    class="btn btn-danger" style="border: none;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    <?php else: ?>
                        <div style="margin-top: 15px; padding: 12px; background: #f7fafc; border-radius: 8px; font-size: 13px; color: #718096;">
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

    <!-- Approve Modal -->
    <div id="approveModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle" style="color: #10b981;"></i> Approve Salary Change</h3>
                <p id="approveEmployeeName" style="color: #718096; margin-top: 5px; font-size: 14px;"></p>
            </div>
            <form id="approveForm" method="POST" action="/payslip_generator/public/index.php?page=approve-salary-change">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="request_id" id="approve_request_id">
                <div class="form-group">
                    <label for="approve_comments">Comments (Optional)</label>
                    <textarea name="comments" id="approve_comments" placeholder="Add any comments about this approval..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approveModal')">Cancel</button>
                    <button type="submit" class="btn btn-success">Approve Request</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-times-circle" style="color: #ef4444;"></i> Reject Salary Change</h3>
                <p id="rejectEmployeeName" style="color: #718096; margin-top: 5px; font-size: 14px;"></p>
            </div>
            <form id="rejectForm" method="POST" action="/payslip_generator/public/index.php?page=reject-salary-change">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="request_id" id="reject_request_id">
                <div class="form-group">
                    <label for="reject_comments">Rejection Reason <span style="color: #ef4444;">*</span></label>
                    <textarea name="comments" id="reject_comments" required placeholder="Please provide a reason for rejection..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Reject Request</button>
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
