<?php
require_once __DIR__ . '/../../app/Helpers/SessionManager.php';
SessionManager::start();

// Support both single-role and multi-role scenarios
if (!SessionManager::has('role')) {
    header("Location: ../auth/login.php");
    exit;
}


// RBAC Check
require_once __DIR__ . "/../../app/Helpers/RBACHelper.php";
$userRoles = SessionManager::get('all_roles', [SessionManager::get('role')]);
if (!in_array('employee', $userRoles) && SessionManager::get('role') !== 'employee') {
    header("Location: ../auth/login.php");
    exit;
}


// DB & Models
require_once __DIR__ . "/../../app/Config/database.php";
require_once __DIR__ . "/../../app/Models/Employee.php";
require_once __DIR__ . "/../../app/Models/Attendance.php";
require_once __DIR__ . "/../../app/Models/LeaveRequest.php";
require_once __DIR__ . "/../../app/Models/Payslip.php";

$userId = SessionManager::get('user_id');
$employeeId = SessionManager::get('employee_id');
$employeeName = SessionManager::get('employee_name', "Employee");


// Initialize Models
$empModel = new Employee();
$attModel = new Attendance();
$leaveModel = new LeaveRequest();
$payslipModel = new Payslip();

// Fetch Data
$employeeData = $empModel->getEmployeeById($employeeId);
$employeeName = $employeeData['full_name'] ?? $employeeName; // Update with DB fresh name

// 1. Attendance Stats
$currentMonth = date('Y-m');
$attStats = $attModel->getAttendanceSummary($employeeId, $currentMonth);
$daysPresent = $attStats['present_days'] ?? 0;

// 2. Leave Stats (Calc total taken)
$leaveBalances = $leaveModel->getLeaveBalance($employeeId);
$totalLeavesTaken = 0;
if (is_array($leaveBalances)) {
    foreach ($leaveBalances as $lb) {
        $totalLeavesTaken += ($lb['days_taken'] ?? 0);
    }
}

// 3. Payslips Count
$allPayslips = $payslipModel->getPayslipsByEmployee($employeeId);
$totalPayslips = count($allPayslips);

// Page Title
$pageTitle = "Dashboard";

// Include Header (contains Sidebar & Head)
include 'includes/header.php';
?>

<!-- Action Buttons -->
<h2 style="font-size: 1.25rem; font-weight: 600; margin-bottom: 1.5rem; color: var(--text-primary);">Overview</h2>

<!-- Stats Grid -->
<div class="stats-grid">
    <!-- Basic Salary -->
    <div class="glass-card bg-indigo">
        <div class="stat-icon" style="background: rgba(99, 102, 241, 0.2); color: #4f46e5;">
            <i class="fas fa-wallet"></i>
        </div>
        <div class="stat-value">₹<?= number_format($employeeData['basic_salary'] ?? 0) ?></div>
        <div class="stat-label">Basic Salary</div>
    </div>

    <!-- Attendance -->
    <div class="glass-card bg-green">
        <div class="stat-icon" style="background: rgba(16, 185, 129, 0.2); color: #15803d;">
            <i class="fas fa-calendar-check"></i>
        </div>
        <div class="stat-value"><?= $daysPresent ?></div>
        <div class="stat-label">Days Present (<?= date('M') ?>)</div>
    </div>

    <!-- Leaves -->
    <div class="glass-card bg-pink">
        <div class="stat-icon" style="background: rgba(236, 72, 153, 0.2); color: #be185d;">
            <i class="fas fa-umbrella-beach"></i>
        </div>
        <div class="stat-value"><?= $totalLeavesTaken ?></div>
        <div class="stat-label">Leaves Taken (Year)</div>
    </div>

    <!-- Payslips -->
    <div class="glass-card bg-orange">
        <div class="stat-icon" style="background: rgba(249, 115, 22, 0.2); color: #c2410c;">
            <i class="fas fa-file-invoice-dollar"></i>
        </div>
        <div class="stat-value"><?= $totalPayslips ?></div>
        <div class="stat-label">Total Payslips</div>
    </div>
</div>

<div class="app-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 2rem; margin-top: 2rem;">
    
    <!-- Quick Actions -->
    <div class="glass-card">
        <h3 style="margin-bottom: 1.5rem;">Quick Actions</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem;">
            <a href="attendance_calendar.php" class="btn btn-outline" style="justify-content: center; flex-direction: column; padding: 1.5rem; gap: 10px;">
                <i class="fas fa-calendar-alt" style="font-size: 1.5rem; color: var(--primary);"></i>
                <span>View Attendance</span>
            </a>
            <a href="leave_management.php" class="btn btn-outline" style="justify-content: center; flex-direction: column; padding: 1.5rem; gap: 10px;">
                <i class="fas fa-plus-circle" style="font-size: 1.5rem; color: var(--secondary);"></i>
                <span>Apply Leave</span>
            </a>
            <a href="view_payslips.php" class="btn btn-outline" style="justify-content: center; flex-direction: column; padding: 1.5rem; gap: 10px;">
                <i class="fas fa-download" style="font-size: 1.5rem; color: var(--success);"></i>
                <span>Payslips</span>
            </a>
        </div>
    </div>

    <!-- Profile Summary -->
    <div class="glass-card">
        <h3 style="margin-bottom: 1.5rem;">My Profile</h3>
        <div style="display:flex; flex-direction:column; gap: 1rem;">
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                <span class="stat-label">Designation</span>
                <span style="font-weight:600;"><?= htmlspecialchars($employeeData['designation'] ?? 'N/A') ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                <span class="stat-label">Department</span>
                <span style="font-weight:600;"><?= htmlspecialchars($employeeData['department_name'] ?? 'N/A') ?></span>
            </div>
            <div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                <span class="stat-label">Join Date</span>
                <span style="font-weight:600;"><?= htmlspecialchars($employeeData['joining_date'] ?? 'N/A') ?></span>
            </div>
            <a href="employee_profile.php" class="btn btn-primary" style="margin-top: 1rem; justify-content: center;">
                View Full Profile
            </a>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
