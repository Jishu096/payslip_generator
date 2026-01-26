<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has director role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";
$db = getDBConnection();

$username = $_SESSION['username'] ?? 'Director';

// Fetch all pending approvals (salary + role changes)
$stmt = $db->prepare("
    SELECT 
        'salary' as type,
        scr.request_id as id,
        scr.employee_id,
        scr.status,
        scr.request_date,
        scr.employee_name as full_name,
        NULL as email,
        NULL as department_name,
        scr.current_salary as old_salary,
        scr.new_salary,
        NULL as old_role,
        NULL as new_role
    FROM salary_change_requests scr
    
    UNION ALL
    
    SELECT 
        'role' as type,
        rcr.request_id as id,
        rcr.employee_id,
        rcr.status,
        rcr.request_date,
        rcr.employee_name as full_name,
        NULL as email,
        NULL as department_name,
        NULL as old_salary,
        NULL as new_salary,
        rcr.old_role,
        rcr.new_role
    FROM role_change_requests rcr
    
    ORDER BY status ASC, request_date DESC
");
$stmt->execute();
$allApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($allApprovals as $approval) {
    if ($approval['status'] === 'pending') $pendingCount++;
    elseif ($approval['status'] === 'approved') $approvedCount++;
    elseif ($approval['status'] === 'rejected') $rejectedCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Approvals - Director</title>
    <?php include 'includes/director_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-check-square"></i> All Approvals</h1>
            <p>View all salary and role change requests.</p>
        </div>

        <div class="live-stats-container">
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-orange text-white">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $pendingCount; ?></h4>
                    <span>Pending</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $approvedCount; ?></h4>
                    <span>Approved</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-pink text-white">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo $rejectedCount; ?></h4>
                    <span>Rejected</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Request History</h3>
            </div>
            <div class="card-body">
                <?php if (count($allApprovals) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Details</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($allApprovals as $approval): ?>
                                    <tr>
                                        <td>
                                            <span class="type-badge <?php echo $approval['type']; ?>-type">
                                                <i class="fas fa-<?php echo $approval['type'] === 'salary' ? 'money-bill' : 'user'; ?>"></i>
                                                <?php echo ucfirst($approval['type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($approval['full_name']); ?></strong><br>
                                            <small style="color: #718096;">Employee ID: <?php echo $approval['employee_id']; ?></small>
                                        </td>
                                        <td>—</td>
                                        <td>
                                            <?php if ($approval['type'] === 'salary'): ?>
                                                <small>
                                                    <?php if ($approval['old_salary']): ?>
                                                        Old: <strong><?php echo number_format($approval['old_salary'], 2); ?></strong><br>
                                                    <?php endif; ?>
                                                    New: <strong><?php echo number_format($approval['new_salary'], 2); ?></strong>
                                                </small>
                                            <?php else: ?>
                                                <small>
                                                    <?php if ($approval['old_role']): ?>
                                                        Current: <strong><?php echo htmlspecialchars($approval['old_role']); ?></strong><br>
                                                    <?php endif; ?>
                                                    Requested: <strong><?php echo htmlspecialchars($approval['new_role']); ?></strong>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $statusClass = 'badge-warning';
                                                if($approval['status'] === 'approved') $statusClass = 'badge-success';
                                                if($approval['status'] === 'rejected') $statusClass = 'badge-danger';
                                            ?>
                                            <span class="badge <?php echo $statusClass; ?>">
                                                <?php echo ucfirst($approval['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?php echo date('M d, Y', strtotime($approval['request_date'])); ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px; color: #a0aec0;">
                        <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.5; margin-bottom: 16px;"></i>
                        <p>No approvals found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>
