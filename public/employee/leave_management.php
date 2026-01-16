<?php
session_start();

$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/LeaveRequest.php";

$db = getDBConnection(); // Need for direct queries if any, or rely on models
$leaveModel = new LeaveRequest();
$employeeId = $_SESSION['employee_id'];
$employeeModel = new Employee();
$employeeData = $employeeModel->getEmployeeById($employeeId);
$employeeName = $employeeData['first_name'] . ' ' . $employeeData['last_name'];

// Handle Form
$successMessage = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Delete
    if (isset($_POST['delete_leave_id'])) {
        if ($leaveModel->deleteLeaveRequest($_POST['delete_leave_id'], $employeeId)) {
            $successMessage = "Leave request deleted successfully.";
        } else {
            $error = "Failed to delete request. It may no longer be pending.";
        }
    }
    // Handle Submit
    elseif (isset($_POST['submit_leave'])) {
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
                $successMessage = "Request submitted successfully!";
            } else {
                $error = "Failed to submit leave request. Please try again.";
            }
        }
    }
}

$leaveRequests = $leaveModel->getLeaveRequestsByEmployee($employeeId);
$leaveBalArray = $leaveModel->getLeaveBalance($employeeId);

// Transform balance array to key-value if needed, or iterate directly
// Just use the array as is.

$pageTitle = "Leave Management";
include 'includes/header.php';
?>

<!-- Alerts -->
<?php if ($successMessage): ?>
<div style="background: #dcfce7; color: #166534; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #bbf7d0; display:flex; align-items:center; gap:0.5rem;">
    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($successMessage) ?>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div style="background: #fee2e2; color: #991b1b; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; border: 1px solid #fecaca; display:flex; align-items:center; gap:0.5rem;">
    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div class="app-grid" style="display: grid; grid-template-columns: 1fr 2fr; gap: 2rem;">
    
    <!-- Left Col: Apply Form & Balance -->
    <div style="display:flex; flex-direction:column; gap: 2rem;">
        
        <!-- Balance Card -->
        <div class="glass-card bg-indigo">
             <h3 style="margin-bottom: 1rem; font-size: 1.1rem;"><i class="fas fa-chart-pie"></i> Leave Balance</h3>
             <?php if (empty($leaveBalArray)): ?>
                <p style="opacity:0.8; font-size:0.9rem;">No leave data available yet.</p>
             <?php else: ?>
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <?php foreach ($leaveBalArray as $bal): ?>
                    <div style="background: rgba(255,255,255,0.2); padding: 0.75rem; border-radius: 0.5rem; text-align: center;">
                        <span style="font-size: 1.5rem; font-weight: 700; display:block;"><?= $bal['days_taken'] ?></span>
                        <span style="font-size: 0.75rem; text-transform: uppercase;"><?= $bal['leave_type'] ?></span>
                        <span style="font-size: 0.7rem; opacity: 0.8; display:block;">(Taken)</span>
                    </div>
                    <?php endforeach; ?>
                </div>
             <?php endif; ?>
        </div>

        <!-- Apply Form -->
        <div class="glass-card">
            <h3 style="margin-bottom: 1.5rem;"><i class="fas fa-plus-circle"></i> Apply for Leave</h3>
            <form method="POST" action="">
                <div style="margin-bottom: 1rem;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color:var(--text-secondary);">Leave Type</label>
                    <select name="leave_type" required style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; background:white;">
                        <option value="">Select Type</option>
                        <option value="casual">Casual Leave</option>
                        <option value="sick">Sick Leave</option>
                        <option value="paid">Paid Leave</option>
                        <option value="maternity">Maternity Leave</option>
                        <option value="unpaid">Unpaid Leave</option>
                    </select>
                </div>

                <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color:var(--text-secondary);">Start Date</label>
                        <input type="date" id="start_date" name="start_date" required min="<?= date('Y-m-d') ?>" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                    </div>
                    <div>
                        <label style="display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color:var(--text-secondary);">End Date</label>
                        <input type="date" id="end_date" name="end_date" required min="<?= date('Y-m-d') ?>" style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem;">
                    </div>
                </div>

                <div style="margin-bottom: 1.5rem;">
                    <label style="display:block; margin-bottom:0.5rem; font-weight:500; font-size:0.9rem; color:var(--text-secondary);">Reason</label>
                    <textarea name="reason" required placeholder="Describe user reason..." style="width:100%; padding:0.75rem; border:1px solid #cbd5e1; border-radius:0.5rem; min-height:80px;"></textarea>
                </div>

                <button type="submit" name="submit_leave" class="btn btn-primary" style="width:100%; justify-content:center;">
                    <i class="fas fa-paper-plane"></i> Submit Request
                </button>
            </form>
        </div>
    
    </div>

    <!-- Right Col: History -->
    <div class="glass-card">
        <h3 style="margin-bottom: 1.5rem; display:flex; align-items:center; justify-content:space-between;">
            <span><i class="fas fa-history"></i> Leave History</span>
            <span style="font-size:0.8rem; font-weight:400; color:var(--text-secondary);"><?= count($leaveRequests) ?> Records</span>
        </h3>

        <?php if (empty($leaveRequests)): ?>
            <div style="text-align:center; padding: 4rem 1rem; color:var(--text-secondary);">
                <i class="far fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem; opacity:0.3;"></i>
                <p>No leave history found.</p>
            </div>
        <?php else: ?>
            <div style="overflow-x:auto;">
                <table style="width:100%; border-collapse:collapse; font-size:0.9rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="text-align:left; padding:1rem; color:var(--text-secondary); font-weight:600;">Type</th>
                            <th style="text-align:left; padding:1rem; color:var(--text-secondary); font-weight:600;">Dates</th>
                            <th style="text-align:left; padding:1rem; color:var(--text-secondary); font-weight:600;">Ex-Days</th>
                            <th style="text-align:left; padding:1rem; color:var(--text-secondary); font-weight:600;">Status</th>
                            <th style="text-align:right; padding:1rem; color:var(--text-secondary); font-weight:600;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaveRequests as $req): 
                             $days = (strtotime($req['end_date']) - strtotime($req['start_date'])) / 86400 + 1;
                        ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding:1rem;">
                                <div style="font-weight:600; text-transform:capitalize;"><?= $req['leave_type'] ?></div>
                                <div style="font-size:0.75rem; color:var(--text-secondary);"><?= date('M d, Y', strtotime($req['request_date'])) ?></div>
                            </td>
                            <td style="padding:1rem;">
                                <div><?= date('M d', strtotime($req['start_date'])) ?> - <?= date('M d, Y', strtotime($req['end_date'])) ?></div>
                            </td>
                            <td style="padding:1rem;">
                                <span style="background:#f1f5f9; padding:2px 8px; border-radius:4px; font-size:0.8rem; font-weight:600;"><?= $days ?>d</span>
                            </td>
                            <td style="padding:1rem;">
                                <?php 
                                    $statusColor = '#f59e0b'; // pending
                                    if ($req['status'] === 'approved') $statusColor = '#10b981';
                                    if ($req['status'] === 'rejected') $statusColor = '#ef4444';
                                ?>
                                <span style="color:<?= $statusColor ?>; font-weight:600; text-transform:uppercase; font-size:0.75rem; border:1px solid <?= $statusColor ?>; padding:2px 8px; border-radius:12px;">
                                    <?= $req['status'] ?>
                                </span>
                            </td>
                            <td style="padding:1rem; text-align:right;">
                                <?php if ($req['status'] === 'pending'): ?>
                                    <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to cancel this leave request?');">
                                        <input type="hidden" name="delete_leave_id" value="<?= $req['leave_id'] ?>">
                                        <button type="submit" style="background:none; border:none; color:#ef4444; cursor:pointer; padding:5px;" title="Delete Request">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
    document.getElementById('start_date').addEventListener('change', function() {
        document.getElementById('end_date').min = this.value;
    });
</script>

<?php include 'includes/footer.php'; ?>
