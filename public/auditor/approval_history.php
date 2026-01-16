<?php
session_start();

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Auditor';
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get filter parameters
$typeFilter = $_GET['type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');

// Initialize arrays
$approvals = [];

// Fetch salary approvals
try {
    $sql = "SELECT 
                'Salary Change' as approval_type,
                sa.id,
                e.full_name as employee_name,
                sa.current_salary,
                sa.proposed_salary,
                sa.reason,
                sa.status,
                CONCAT(u1.username, ' (', u1.role, ')') as requested_by,
                CONCAT(u2.username, ' (', u2.role, ')') as reviewed_by,
                sa.requested_date,
                sa.reviewed_date,
                sa.director_comments
            FROM salary_approvals sa
            JOIN employees e ON sa.employee_id = e.employee_id
            JOIN users u1 ON sa.requested_by = u1.user_id
            LEFT JOIN users u2 ON sa.reviewed_by = u2.user_id
            WHERE sa.requested_date BETWEEN ? AND ?";
    
    $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    
    if ($statusFilter) {
        $sql .= " AND sa.status = ?";
        $params[] = $statusFilter;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $salaryApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$typeFilter || $typeFilter === 'salary') {
        $approvals = array_merge($approvals, $salaryApprovals);
    }
} catch (Exception $e) {
    $salaryApprovals = [];
}

// Fetch role change approvals
try {
    $sql = "SELECT 
                'Role Change' as approval_type,
                rca.id,
                e.full_name as employee_name,
                rca.current_role,
                rca.proposed_role,
                rca.reason,
                rca.status,
                CONCAT(u1.username, ' (', u1.role, ')') as requested_by,
                CONCAT(u2.username, ' (', u2.role, ')') as reviewed_by,
                rca.requested_date,
                rca.reviewed_date,
                rca.director_comments
            FROM role_change_approvals rca
            JOIN employees e ON rca.employee_id = e.employee_id
            JOIN users u1 ON rca.requested_by = u1.user_id
            LEFT JOIN users u2 ON rca.reviewed_by = u2.user_id
            WHERE rca.requested_date BETWEEN ? AND ?";
    
    $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    
    if ($statusFilter) {
        $sql .= " AND rca.status = ?";
        $params[] = $statusFilter;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $roleApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$typeFilter || $typeFilter === 'role') {
        $approvals = array_merge($approvals, $roleApprovals);
    }
} catch (Exception $e) {
    $roleApprovals = [];
}

// Fetch leave approvals
try {
    $sql = "SELECT 
                'Leave Request' as approval_type,
                lr.request_id as id,
                e.full_name as employee_name,
                lr.leave_type as current_role,
                CONCAT(lr.start_date, ' to ', lr.end_date) as proposed_role,
                lr.reason,
                lr.status,
                CONCAT(e2.full_name, ' (Employee)') as requested_by,
                CONCAT(u.username, ' (', u.role, ')') as reviewed_by,
                lr.requested_at as requested_date,
                lr.reviewed_at as reviewed_date,
                lr.admin_comments as director_comments
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            JOIN employees e2 ON lr.employee_id = e2.employee_id
            LEFT JOIN users u ON lr.reviewed_by = u.user_id
            WHERE lr.requested_at BETWEEN ? AND ?";
    
    $params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59'];
    
    if ($statusFilter) {
        $sql .= " AND lr.status = ?";
        $params[] = $statusFilter;
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $leaveApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!$typeFilter || $typeFilter === 'leave') {
        $approvals = array_merge($approvals, $leaveApprovals);
    }
} catch (Exception $e) {
    $leaveApprovals = [];
}

// Sort by date
usort($approvals, function($a, $b) {
    return strtotime($b['requested_date']) - strtotime($a['requested_date']);
});

// Calculate stats
$stats = [
    'total' => count($approvals),
    'pending' => count(array_filter($approvals, fn($a) => $a['status'] === 'Pending')),
    'approved' => count(array_filter($approvals, fn($a) => $a['status'] === 'Approved')),
    'rejected' => count(array_filter($approvals, fn($a) => $a['status'] === 'Rejected'))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval History - Auditor</title>
    <?php include 'includes/auditor_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
        }

        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.approved { border-left-color: #10b981; }
        .stat-card.rejected { border-left-color: #ef4444; }

        .stat-label {
            font-size: 13px;
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
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--muted);
            font-size: 13px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .filter-btn, .export-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .export-btn {
            background: #10b981;
            color: white;
        }

        .filter-btn:hover, .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .timeline-container {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .timeline {
            position: relative;
            padding-left: 50px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 20px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(to bottom, var(--accent), var(--border));
        }

        .timeline-item {
            position: relative;
            margin-bottom: 30px;
            padding: 20px;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 3px solid var(--accent);
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -33px;
            top: 25px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: white;
            border: 3px solid var(--accent);
            z-index: 1;
        }

        .timeline-item.approved::before {
            border-color: #10b981;
        }

        .timeline-item.rejected::before {
            border-color: #ef4444;
        }

        .timeline-item.pending::before {
            border-color: #f59e0b;
        }

        .timeline-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .timeline-type {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: var(--accent);
            color: white;
        }

        .timeline-type.role { background: #6366f1; }
        .timeline-type.leave { background: #f59e0b; }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .timeline-employee {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .timeline-details {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .timeline-detail-item {
            font-size: 13px;
        }

        .timeline-detail-label {
            color: var(--muted);
            font-weight: 600;
            margin-bottom: 3px;
        }

        .timeline-detail-value {
            color: var(--text);
        }

        .timeline-footer {
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid var(--border);
            font-size: 12px;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <?php include 'includes/auditor_navbar.php'; ?>
    <?php include 'includes/auditor_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-check-circle"></i> Approval History</h1>
                <p>View salary, role, and leave approval records</p>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Approvals</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">Pending</div>
                <div class="stat-value"><?php echo number_format($stats['pending']); ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label">Approved</div>
                <div class="stat-value"><?php echo number_format($stats['approved']); ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label">Rejected</div>
                <div class="stat-value"><?php echo number_format($stats['rejected']); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="filter-group">
                    <label>Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="salary" <?php echo $typeFilter === 'salary' ? 'selected' : ''; ?>>Salary Change</option>
                        <option value="role" <?php echo $typeFilter === 'role' ? 'selected' : ''; ?>>Role Change</option>
                        <option value="leave" <?php echo $typeFilter === 'leave' ? 'selected' : ''; ?>>Leave Request</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $statusFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Approved" <?php echo $statusFilter === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="Rejected" <?php echo $statusFilter === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Timeline -->
        <div class="timeline-container">
            <div class="timeline-header">
                <h3><?php echo count($approvals); ?> Approval Records</h3>
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>

            <?php if (empty($approvals)): ?>
                <div style="text-align: center; padding: 60px; color: var(--muted);">
                    <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                    <h3>No Approval Records</h3>
                    <p>Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <div class="timeline">
                    <?php foreach ($approvals as $approval): ?>
                        <div class="timeline-item <?php echo strtolower($approval['status']); ?>">
                            <div class="timeline-header-row">
                                <span class="timeline-type <?php echo strtolower(explode(' ', $approval['approval_type'])[0]); ?>">
                                    <?php echo $approval['approval_type']; ?>
                                </span>
                                <span class="status-badge <?php echo strtolower($approval['status']); ?>">
                                    <?php echo $approval['status']; ?>
                                </span>
                            </div>
                            
                            <div class="timeline-employee"><?php echo htmlspecialchars($approval['employee_name']); ?></div>
                            
                            <div class="timeline-details">
                                <?php if ($approval['approval_type'] === 'Salary Change'): ?>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Current Salary</div>
                                        <div class="timeline-detail-value">₹<?php echo number_format($approval['current_salary'], 2); ?></div>
                                    </div>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Proposed Salary</div>
                                        <div class="timeline-detail-value">₹<?php echo number_format($approval['proposed_salary'], 2); ?></div>
                                    </div>
                                <?php elseif ($approval['approval_type'] === 'Role Change'): ?>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Current Role</div>
                                        <div class="timeline-detail-value"><?php echo htmlspecialchars($approval['current_role']); ?></div>
                                    </div>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Proposed Role</div>
                                        <div class="timeline-detail-value"><?php echo htmlspecialchars($approval['proposed_role']); ?></div>
                                    </div>
                                <?php else: ?>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Leave Type</div>
                                        <div class="timeline-detail-value"><?php echo htmlspecialchars($approval['current_role']); ?></div>
                                    </div>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Duration</div>
                                        <div class="timeline-detail-value"><?php echo htmlspecialchars($approval['proposed_role']); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="timeline-detail-item">
                                    <div class="timeline-detail-label">Reason</div>
                                    <div class="timeline-detail-value"><?php echo htmlspecialchars($approval['reason']); ?></div>
                                </div>
                                
                                <?php if ($approval['director_comments']): ?>
                                    <div class="timeline-detail-item">
                                        <div class="timeline-detail-label">Comments</div>
                                        <div class="timeline-detail-value"><?php echo htmlspecialchars($approval['director_comments']); ?></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="timeline-footer">
                                <strong>Requested:</strong> <?php echo date('d M Y, h:i A', strtotime($approval['requested_date'])); ?> by <?php echo htmlspecialchars($approval['requested_by']); ?>
                                <?php if ($approval['reviewed_date']): ?>
                                    &nbsp;|&nbsp; <strong>Reviewed:</strong> <?php echo date('d M Y, h:i A', strtotime($approval['reviewed_date'])); ?> by <?php echo htmlspecialchars($approval['reviewed_by']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/auditor_scripts.php'; ?>
    <script>
        const approvalsData = <?php echo json_encode($approvals); ?>;

        function exportToCSV() {
            if (approvalsData.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['Type', 'Employee', 'Status', 'From', 'To', 'Reason', 'Requested By', 'Requested Date', 'Reviewed By', 'Reviewed Date', 'Comments'];
            const rows = approvalsData.map(a => [
                a.approval_type,
                a.employee_name,
                a.status,
                a.current_salary || a.current_role,
                a.proposed_salary || a.proposed_role,
                a.reason,
                a.requested_by,
                a.requested_date,
                a.reviewed_by || '',
                a.reviewed_date || '',
                a.director_comments || ''
            ]);

            let csv = headers.join(',') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `approval_history_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
