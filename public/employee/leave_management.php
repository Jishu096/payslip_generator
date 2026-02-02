<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Employee';
$employeeId = $_SESSION['employee_id'];

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/LeaveRequest.php";
require_once "../../app/Helpers/NotificationHelper.php";
require_once "../../app/Config/database.php";

$employeeModel = new Employee();
$leaveModel = new LeaveRequest();
$db = getDBConnection();
$notificationHelper = new NotificationHelper($db);

// Get employee details
$employeeData = $employeeModel->getEmployeeById($employeeId);
$employeeName = $employeeData['first_name'] . ' ' . $employeeData['last_name'];

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        $error = "All fields are required.";
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $error = "End date must be after start date.";
    } else {
        if ($leaveModel->submitLeaveRequest($employeeId, $employeeName, $leaveType, $startDate, $endDate, $reason)) {
            $success = true;
            
            // Notify admin about new leave request
            $adminStmt = $db->prepare("SELECT email FROM users WHERE role = 'administrator' AND is_active = 1 LIMIT 1");
            $adminStmt->execute();
            $adminData = $adminStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($adminData && !empty($adminData['email'])) {
                $notificationHelper->notifyLeaveSubmitted(
                    $adminData['email'],
                    $employeeName,
                    $leaveType,
                    $startDate,
                    $endDate
                );
            }
        } else {
            $error = "Failed to submit leave request. Please try again.";
        }
    }
}

// Get leave requests history
$leaveRequests = $leaveModel->getLeaveRequestsByEmployee($employeeId);

// Get leave balance
$leaveBalance = $leaveModel->getLeaveBalance($employeeId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Employee Portal</title>
    <?php include 'includes/employee_styles.php'; ?>
</head>
<body>
    <?php include 'includes/employee_navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Leave Management</span>
            </div>
            <h1><i class="fas fa-umbrella-beach"></i> Leave Management</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Leave request submitted successfully! Awaiting approval.</span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Leave Application Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i>
                    <h2>Apply for Leave</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="leave_type">
                                <i class="fas fa-tag"></i> Leave Type
                            </label>
                            <select id="leave_type" name="leave_type" required>
                                <option value="">Select Leave Type</option>
                                <option value="casual">Casual Leave</option>
                                <option value="sick">Sick Leave</option>
                                <option value="paid">Paid Leave</option>
                                <option value="unpaid">Unpaid Leave</option>
                                <option value="maternity">Maternity Leave</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_date">
                                <i class="far fa-calendar"></i> Start Date
                            </label>
                            <input type="date" 
                                   id="start_date" 
                                   name="start_date" 
                                   min="<?= date('Y-m-d') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="end_date">
                                <i class="far fa-calendar-check"></i> End Date
                            </label>
                            <input type="date" 
                                   id="end_date" 
                                   name="end_date" 
                                   min="<?= date('Y-m-d') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="reason">
                                <i class="fas fa-comment"></i> Reason
                            </label>
                            <textarea id="reason" 
                                      name="reason" 
                                      placeholder="Please provide a reason for your leave request..."
                                      required></textarea>
                        </div>

                        <button type="submit" name="submit_leave" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- Leave Balance -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i>
                    <h2>Leave Balance (<?= date('Y') ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($leaveBalance)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No leave taken yet this year</p>
                        </div>
                    <?php else: ?>
                        <div class="leave-balance">
                            <?php foreach ($leaveBalance as $balance): ?>
                                <div class="balance-card">
                                    <div class="balance-number"><?= $balance['days_taken'] ?></div>
                                    <div class="balance-label"><?= ucfirst($balance['leave_type']) ?> Days</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Leave History -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i>
                <h2>Leave History</h2>
            </div>
            <div class="card-body">
                <?php if (empty($leaveRequests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No leave requests found</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Requested On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaveRequests as $request): 
                                $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / 86400 + 1;
                            ?>
                            <tr>
                                <td><?= ucfirst($request['leave_type']) ?></td>
                                <td><?= date('M d, Y', strtotime($request['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><?= $days ?> days</td>
                                <td>
                                    <span class="status-badge <?= strtolower($request['status']) ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td><?= isset($request['request_date']) ? date('M d, Y', strtotime($request['request_date'])) : 'N/A' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
    <script>
        // Auto-update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });
    </script>
</body>
</html>
