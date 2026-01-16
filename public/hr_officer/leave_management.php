<?php
session_start();

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr_officer') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'HR Officer';
$userId = $_SESSION['user_id'] ?? 0;
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get filter parameters
$statusFilter = $_GET['status'] ?? 'pending';
$leaveTypeFilter = $_GET['leave_type'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT 
            lr.leave_id,
            lr.employee_id,
            lr.employee_name,
            lr.leave_type,
            lr.start_date,
            lr.end_date,
            lr.reason,
            lr.request_date,
            lr.status,
            lr.reviewed_by_name,
            lr.review_date,
            lr.review_comments,
            DATEDIFF(lr.end_date, lr.start_date) + 1 as days_requested,
            e.full_name,
            d.department_name
        FROM leave_requests lr
        JOIN employees e ON lr.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE 1=1";

$params = [];

if ($statusFilter !== 'all') {
    $sql .= " AND lr.status = ?";
    $params[] = $statusFilter;
}

if ($leaveTypeFilter) {
    $sql .= " AND lr.leave_type = ?";
    $params[] = $leaveTypeFilter;
}

if ($searchQuery) {
    $sql .= " AND (lr.employee_name LIKE ? OR e.full_name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY lr.request_date DESC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leaveRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $leaveRequests = [];
    $error = "Error fetching leave requests: " . $e->getMessage();
}

// Get stats
try {
    $statsStmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM leave_requests
        WHERE request_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - HR Officer</title>
    <?php include 'includes/hr_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.approved { border-left-color: #10b981; }
        .stat-card.rejected { border-left-color: #ef4444; }
        
        .stat-card h3 {
            font-size: 14px;
            color: #64748b;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
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
            margin-bottom: 5px;
            font-weight: 500;
            color: #475569;
            font-size: 14px;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .leave-table-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .table-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 18px;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .bulk-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .bulk-approve {
            background: #10b981;
            color: white;
        }
        
        .bulk-reject {
            background: #ef4444;
            color: white;
        }
        
        .bulk-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .bulk-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        .leave-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .leave-table thead {
            background: #f8fafc;
        }
        
        .leave-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .leave-table th:first-child {
            padding-left: 20px;
        }
        
        .leave-table td {
            padding: 15px;
            border-top: 1px solid #f1f5f9;
            color: #334155;
            font-size: 14px;
        }
        
        .leave-table td:first-child {
            padding-left: 20px;
        }
        
        .leave-table tbody tr {
            transition: background-color 0.2s;
        }
        
        .leave-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
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
        
        .leave-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .approve-btn {
            background: #10b981;
            color: white;
        }
        
        .reject-btn {
            background: #ef4444;
            color: white;
        }
        
        .view-btn {
            background: #667eea;
            color: white;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        
        .no-records {
            padding: 60px 20px;
            text-align: center;
            color: #94a3b8;
        }
        
        .no-records i {
            font-size: 48px;
            margin-bottom: 15px;
            display: block;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 20px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: #1e293b;
        }
        
        .detail-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            gap: 15px;
            margin-bottom: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: #475569;
            font-size: 14px;
        }
        
        .detail-value {
            color: #1e293b;
            font-size: 14px;
        }
        
        .review-form {
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #e2e8f0;
        }
        
        .review-form textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-family: inherit;
            font-size: 14px;
            resize: vertical;
            min-height: 80px;
        }
        
        .review-form textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .review-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .review-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_navbar.php'; ?>
    <?php include 'includes/hr_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-calendar-check"></i> Leave Management</h1>
                <p>Review and manage employee leave requests</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Requests (30 Days)</h3>
                <p class="value"><?php echo $stats['total']; ?></p>
            </div>
            <div class="stat-card pending">
                <h3>Pending</h3>
                <p class="value"><?php echo $stats['pending']; ?></p>
            </div>
            <div class="stat-card approved">
                <h3>Approved</h3>
                <p class="value"><?php echo $stats['approved']; ?></p>
            </div>
            <div class="stat-card rejected">
                <h3>Rejected</h3>
                <p class="value"><?php echo $stats['rejected']; ?></p>
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
                        <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Leave Type</label>
                    <select name="leave_type">
                        <option value="">All Types</option>
                        <option value="casual" <?php echo $leaveTypeFilter === 'casual' ? 'selected' : ''; ?>>Casual</option>
                        <option value="sick" <?php echo $leaveTypeFilter === 'sick' ? 'selected' : ''; ?>>Sick</option>
                        <option value="paid" <?php echo $leaveTypeFilter === 'paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="unpaid" <?php echo $leaveTypeFilter === 'unpaid' ? 'selected' : ''; ?>>Unpaid</option>
                        <option value="maternity" <?php echo $leaveTypeFilter === 'maternity' ? 'selected' : ''; ?>>Maternity</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search Employee</label>
                    <input type="text" name="search" placeholder="Employee name..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Leave Requests Table -->
        <div class="leave-table-card">
            <div class="table-header">
                <h2>Leave Requests</h2>
                <div class="bulk-actions">
                    <span id="selected-count" style="color: #64748b; font-size: 14px;">0 selected</span>
                    <button class="bulk-btn bulk-approve" onclick="bulkApprove()" id="bulk-approve-btn" disabled>
                        <i class="fas fa-check"></i> Approve Selected
                    </button>
                    <button class="bulk-btn bulk-reject" onclick="bulkReject()" id="bulk-reject-btn" disabled>
                        <i class="fas fa-times"></i> Reject Selected
                    </button>
                </div>
            </div>
            
            <?php if (empty($leaveRequests)): ?>
                <div class="no-records">
                    <i class="fas fa-inbox"></i>
                    <h3>No Leave Requests Found</h3>
                    <p>No leave requests match your current filters.</p>
                </div>
            <?php else: ?>
                <table class="leave-table">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="select-all" onchange="toggleSelectAll()"></th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Leave Type</th>
                            <th>From - To</th>
                            <th>Days</th>
                            <th>Requested</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveRequests as $request): ?>
                            <tr>
                                <td>
                                    <?php if ($request['status'] === 'pending'): ?>
                                        <input type="checkbox" class="leave-checkbox" value="<?php echo $request['leave_id']; ?>" onchange="updateBulkButtons()">
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong><?php echo htmlspecialchars($request['full_name'] ?? $request['employee_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($request['department_name'] ?? 'N/A'); ?></td>
                                <td><span class="leave-badge"><?php echo strtoupper($request['leave_type']); ?></span></td>
                                <td><?php echo date('d M Y', strtotime($request['start_date'])) . ' - ' . date('d M Y', strtotime($request['end_date'])); ?></td>
                                <td><?php echo $request['days_requested']; ?> days</td>
                                <td><?php echo date('d M Y', strtotime($request['request_date'])); ?></td>
                                <td><span class="status-badge <?php echo $request['status']; ?>"><?php echo strtoupper($request['status']); ?></span></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="action-btn view-btn" onclick="viewLeaveDetails(<?php echo htmlspecialchars(json_encode($request)); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <?php if ($request['status'] === 'pending'): ?>
                                            <button class="action-btn approve-btn" onclick="approveLeave(<?php echo $request['leave_id']; ?>)">
                                                <i class="fas fa-check"></i> Approve
                                            </button>
                                            <button class="action-btn reject-btn" onclick="rejectLeave(<?php echo $request['leave_id']; ?>)">
                                                <i class="fas fa-times"></i> Reject
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- View Details Modal -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Leave Request Details</h3>
                <button class="close-modal" onclick="closeDetailsModal()">&times;</button>
            </div>
            <div id="details-content"></div>
        </div>
    </div>

    <?php include 'includes/hr_scripts.php'; ?>
    <script>
        let selectedLeaves = [];

        function toggleSelectAll() {
            const selectAll = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.leave-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkButtons();
        }

        function updateBulkButtons() {
            const checkboxes = document.querySelectorAll('.leave-checkbox:checked');
            selectedLeaves = Array.from(checkboxes).map(cb => cb.value);
            
            document.getElementById('selected-count').textContent = selectedLeaves.length + ' selected';
            document.getElementById('bulk-approve-btn').disabled = selectedLeaves.length === 0;
            document.getElementById('bulk-reject-btn').disabled = selectedLeaves.length === 0;
        }

        function viewLeaveDetails(request) {
            const content = `
                <div class="detail-row">
                    <div class="detail-label">Employee:</div>
                    <div class="detail-value"><strong>${request.full_name || request.employee_name}</strong></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Department:</div>
                    <div class="detail-value">${request.department_name || 'N/A'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Leave Type:</div>
                    <div class="detail-value"><span class="leave-badge">${request.leave_type.toUpperCase()}</span></div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Start Date:</div>
                    <div class="detail-value">${new Date(request.start_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'})}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">End Date:</div>
                    <div class="detail-value">${new Date(request.end_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'})}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Duration:</div>
                    <div class="detail-value">${request.days_requested} days</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Reason:</div>
                    <div class="detail-value">${request.reason || 'No reason provided'}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Requested On:</div>
                    <div class="detail-value">${new Date(request.request_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'})}</div>
                </div>
                <div class="detail-row">
                    <div class="detail-label">Status:</div>
                    <div class="detail-value"><span class="status-badge ${request.status}">${request.status.toUpperCase()}</span></div>
                </div>
                ${request.reviewed_by_name ? `
                    <div class="detail-row">
                        <div class="detail-label">Reviewed By:</div>
                        <div class="detail-value">${request.reviewed_by_name}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Review Date:</div>
                        <div class="detail-value">${new Date(request.review_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit'})}</div>
                    </div>
                    ${request.review_comments ? `
                        <div class="detail-row">
                            <div class="detail-label">Comments:</div>
                            <div class="detail-value">${request.review_comments}</div>
                        </div>
                    ` : ''}
                ` : ''}
                ${request.status === 'pending' ? `
                    <div class="review-form">
                        <h4 style="margin-bottom: 10px; color: #1e293b;">Review This Request</h4>
                        <textarea id="review-comments" placeholder="Add comments (optional)..."></textarea>
                        <div class="review-actions">
                            <button class="action-btn approve-btn" onclick="approveLeave(${request.leave_id})" style="flex: 1;">
                                <i class="fas fa-check"></i> Approve
                            </button>
                            <button class="action-btn reject-btn" onclick="rejectLeave(${request.leave_id})" style="flex: 1;">
                                <i class="fas fa-times"></i> Reject
                            </button>
                        </div>
                    </div>
                ` : ''}
            `;
            
            document.getElementById('details-content').innerHTML = content;
            document.getElementById('detailsModal').classList.add('active');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        async function approveLeave(leaveId) {
            const comments = document.getElementById('review-comments')?.value || '';
            
            if (!confirm('Are you sure you want to approve this leave request?')) {
                return;
            }

            try {
                const response = await fetch('api/review_leave.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        leave_id: leaveId,
                        action: 'approve',
                        comments: comments
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('Leave request approved successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to approve leave'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to approve leave request');
            }
        }

        async function rejectLeave(leaveId) {
            const comments = document.getElementById('review-comments')?.value || '';
            
            if (!comments.trim()) {
                alert('Please provide a reason for rejection');
                return;
            }

            if (!confirm('Are you sure you want to reject this leave request?')) {
                return;
            }

            try {
                const response = await fetch('api/review_leave.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        leave_id: leaveId,
                        action: 'reject',
                        comments: comments
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert('Leave request rejected');
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to reject leave'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to reject leave request');
            }
        }

        async function bulkApprove() {
            if (selectedLeaves.length === 0) return;
            
            if (!confirm(`Approve ${selectedLeaves.length} leave request(s)?`)) {
                return;
            }

            try {
                const response = await fetch('api/bulk_review_leave.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        leave_ids: selectedLeaves,
                        action: 'approve'
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert(`Successfully approved ${result.count} leave request(s)`);
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to approve leaves'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to approve leave requests');
            }
        }

        async function bulkReject() {
            if (selectedLeaves.length === 0) return;
            
            const comments = prompt(`Reject ${selectedLeaves.length} leave request(s)?\n\nProvide reason for rejection:`);
            
            if (!comments) {
                alert('Rejection reason is required');
                return;
            }

            try {
                const response = await fetch('api/bulk_review_leave.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        leave_ids: selectedLeaves,
                        action: 'reject',
                        comments: comments
                    })
                });

                const result = await response.json();
                
                if (result.success) {
                    alert(`Successfully rejected ${result.count} leave request(s)`);
                    location.reload();
                } else {
                    alert('Error: ' + (result.message || 'Failed to reject leaves'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to reject leave requests');
            }
        }

        // Close modal on outside click
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeDetailsModal();
            }
        });
    </script>
</body>
</html>
