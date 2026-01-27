<?php
require_once __DIR__ . '/../../app/Helpers/SessionManager.php';
SessionManager::start();

// Support both single-role and multi-role scenarios
if (!SessionManager::has('role')) {
    header("Location: ../auth/login.php");
    exit;
}


// Check if user has director role (either primary or in all_roles)
// Check if user has director role (either primary or in all_roles)
$userRoles = SessionManager::get('all_roles', [SessionManager::get('role')]);
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && SessionManager::get('role') !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}


require_once __DIR__ . "/../../app/Models/Employee.php";
require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$employeeModel = new Employee();

$username = SessionManager::get('username', 'Director');

$totalEmployees = count($employeeModel->getAllEmployees());

// Get pending salary change requests count
$stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM salary_change_requests WHERE status = 'pending'");
$stmt->execute();
$pendingRequests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

// Get pending role change requests count
$stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM role_change_requests WHERE status = 'pending'");
$stmt->execute();
$pendingRoleRequests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];

// Get approved requests count
$stmt = $db->prepare("SELECT COUNT(*) as approved_count FROM salary_change_requests WHERE status = 'approved'");
$stmt->execute();
$approvedRequests = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];

// Get rejected requests count
$stmt = $db->prepare("SELECT COUNT(*) as rejected_count FROM salary_change_requests WHERE status = 'rejected'");
$stmt->execute();
$rejectedRequests = $stmt->fetch(PDO::FETCH_ASSOC)['rejected_count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Director Dashboard - Payroll System</title>
    <!-- Include Admin Styles for Theme Consistency -->
    <?php include '../admin/includes/admin_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1>Dashboard</h1>
            <p>Welcome back, <strong><?= htmlspecialchars($username) ?></strong>. Manage approvals and company operations.</p>
        </div>

        <!-- Live Stats Container -->
        <div class="live-stats-container">
            <!-- Active Employees -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-blue text-white">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-text">
                    <h4><?= $totalEmployees ?></h4>
                    <span>Active Employees</span>
                </div>
            </div>

            <!-- Salary Requests -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-orange text-white">
                    <i class="fas fa-hand-holding-usd"></i>
                </div>
                <div class="stat-text">
                    <h4><?= $pendingRequests ?></h4>
                    <span>Pending Salary Requests</span>
                </div>
            </div>

            <!-- Role Changes -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-text">
                    <h4><?= $pendingRoleRequests ?></h4>
                    <span>Pending Role Changes</span>
                </div>
            </div>

            <!-- Approved Requests -->
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-purple text-white">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-text">
                    <h4><?= $approvedRequests ?></h4>
                    <span>Total Approved</span>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <h3 style="font-size: 18px; color: #2d3748; margin-bottom: 20px; font-weight: 700;">Quick Actions</h3>
        <div class="attendance-actions-grid">
            <a href="salary_approvals.php" class="action-card bg-gradient-purple">
                <div class="card-content">
                    <div class="card-title">Salary Approvals</div>
                    <div class="card-desc">Review and approve pending salary change requests.</div>
                    <?php if ($pendingRequests > 0): ?>
                        <div style="margin-top: 10px;">
                            <span style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?= $pendingRequests ?> Pending</span>
                        </div>
                    <?php endif; ?>
                </div>
                <i class="fas fa-hand-holding-usd icon-bg"></i>
            </a>

            <a href="role_approvals.php" class="action-card bg-gradient-teal">
                <div class="card-content">
                    <div class="card-title">Role Approvals</div>
                    <div class="card-desc">Manage employee role promotion and changes.</div>
                    <?php if ($pendingRoleRequests > 0): ?>
                        <div style="margin-top: 10px;">
                            <span style="background: rgba(255,255,255,0.2); padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;"><?= $pendingRoleRequests ?> Pending</span>
                        </div>
                    <?php endif; ?>
                </div>
                <i class="fas fa-user-check icon-bg"></i>
            </a>

            <a href="employees.php" class="action-card bg-gradient-blue">
                <div class="card-content">
                    <div class="card-title">Employee Directory</div>
                    <div class="card-desc">Browse and search through all employee records.</div>
                </div>
                <i class="fas fa-users icon-bg"></i>
            </a>

            <a href="reports.php" class="action-card bg-gradient-orange">
                <div class="card-content">
                    <div class="card-title">Reports & Analytics</div>
                    <div class="card-desc">Access detailed analytics and system reports.</div>
                </div>
                <i class="fas fa-chart-line icon-bg"></i>
            </a>
        </div>
    </div>

    <script>
        // Toggle Sidebar Script (matches Admin/Accountant behavior)
        document.querySelector('.navbar-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>
