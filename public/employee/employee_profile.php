<?php
session_start();

// Auth Check
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

if (!isset($_SESSION['employee_id'])) {
    die("Employee ID missing from session.");
}

$employeeId = $_SESSION['employee_id'];

// DB Connection
require_once __DIR__ . "/../../app/Config/database.php";
$conn = getDBConnection();

// Fetch employee + department
$sql = "SELECT e.*, d.department_name 
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.employee_id = :eid
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->execute([":eid" => $employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    die("Employee not found.");
}

function val($arr, $key, $default = 'Not Provided') {
    return isset($arr[$key]) && $arr[$key] !== '' ? htmlspecialchars($arr[$key]) : $default;
}

$avatarLetter = strtoupper(substr($emp['full_name'], 0, 1));
$pageTitle = "My Profile";
include 'includes/header.php';
?>

<!-- Profile Header Card -->
<div class="glass-card" style="margin-bottom: 2rem; background: linear-gradient(135deg, rgba(255,255,255,0.9), rgba(255,255,255,0.7));">
    <div style="display:flex; align-items:center; gap: 2rem; flex-wrap:wrap;">
        <div style="width:100px; height:100px; background:linear-gradient(135deg, var(--primary), var(--secondary)); border-radius:50%; display:flex; align-items:center; justify-content:center; color:white; font-size:2.5rem; font-weight:700; box-shadow: var(--shadow-md);">
            <?= $avatarLetter ?>
        </div>
        <div>
            <h2 style="font-size:2rem; font-weight:700; color:var(--text-primary); margin-bottom:0.5rem;"><?= htmlspecialchars($emp['full_name']) ?></h2>
            <div style="display:flex; gap:1.5rem; color:var(--text-secondary); font-size:0.95rem; flex-wrap:wrap;">
                <span><i class="fas fa-id-badge" style="color:var(--primary);"></i> <?= htmlspecialchars($emp['employee_id']) ?></span>
                <span><i class="fas fa-briefcase" style="color:var(--secondary);"></i> <?= val($emp, 'designation') ?> • <?= val($emp, 'department_name') ?></span>
                <span><i class="fas fa-envelope" style="color:var(--success);"></i> <?= val($emp, 'email') ?></span>
            </div>
        </div>
    </div>
</div>

<div class="app-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem;">

    <!-- Personal Information -->
    <div class="glass-card">
        <h3 style="margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid rgba(0,0,0,0.05); color:var(--text-primary);">
            <i class="fas fa-user" style="color:var(--primary); margin-right:0.5rem;"></i> Personal Information
        </h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Full Name</label>
                <div style="font-weight:600;"><?= htmlspecialchars($emp['full_name']) ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Date of Birth</label>
                <div style="font-weight:600;"><?= val($emp, 'dob') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Gender</label>
                <div style="font-weight:600;"><?= val($emp, 'gender') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Phone</label>
                <div style="font-weight:600;"><?= val($emp, 'phone') ?></div>
            </div>
            <div style="grid-column: span 2;">
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Address</label>
                <div style="font-weight:600;"><?= val($emp, 'address') ?>, <?= val($emp, 'city') ?>, <?= val($emp, 'state') ?> - <?= val($emp, 'pincode') ?></div>
            </div>
        </div>
    </div>

    <!-- Job Details -->
    <div class="glass-card">
        <h3 style="margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid rgba(0,0,0,0.05); color:var(--text-primary);">
            <i class="fas fa-briefcase" style="color:var(--secondary); margin-right:0.5rem;"></i> Employment Details
        </h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Designation</label>
                <div style="font-weight:600;"><?= val($emp, 'designation') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Department</label>
                <div style="font-weight:600;"><?= val($emp, 'department_name') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Joining Date</label>
                <div style="font-weight:600;"><?= val($emp, 'join_date') ?></div>
            </div>
             <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Type</label>
                <div style="font-weight:600;"><?= val($emp, 'employment_type') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Experience</label>
                <div style="font-weight:600;"><?= val($emp, 'experience_years') ?> Years</div>
            </div>
        </div>
    </div>

    <!-- Financials -->
    <div class="glass-card">
        <h3 style="margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid rgba(0,0,0,0.05); color:var(--text-primary);">
            <i class="fas fa-wallet" style="color:var(--success); margin-right:0.5rem;"></i> Financial & Bank
        </h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Basic Salary</label>
                <div style="font-weight:600; font-size:1.1rem; color:var(--success);">₹<?= number_format((float)$emp['basic_salary']) ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Est. Monthly (w/ DA)</label>
                <div style="font-weight:600; font-size:1.1rem;">
                    ₹<?= number_format((float)$emp['basic_salary'] * 1.58) ?>
                </div>
            </div>
             <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Bank Account</label>
                <div style="font-weight:600;"><?= val($emp, 'bank_account_no') ?></div>
            </div>
             <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">IFSC Code</label>
                <div style="font-weight:600;"><?= val($emp, 'ifsc_code') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">PAN No</label>
                <div style="font-weight:600;"><?= val($emp, 'pan_no') ?></div>
            </div>
        </div>
    </div>

    <!-- Emergency Contact -->
    <div class="glass-card">
        <h3 style="margin-bottom:1.5rem; padding-bottom:1rem; border-bottom:1px solid rgba(0,0,0,0.05); color:var(--text-primary);">
            <i class="fas fa-phone-alt" style="color:var(--danger); margin-right:0.5rem;"></i> Emergency Contact
        </h3>
        <div style="display:grid; grid-template-columns: 1fr; gap: 1.5rem;">
             <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Name</label>
                <div style="font-weight:600;"><?= val($emp, 'emergency_contact_name') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Relationship</label>
                <div style="font-weight:600;"><?= val($emp, 'emergency_contact_relation') ?></div>
            </div>
            <div>
                <label style="display:block; font-size:0.8rem; color:var(--text-secondary); text-transform:uppercase; letter-spacing:0.5px; margin-bottom:0.25rem;">Phone Number</label>
                <div style="font-weight:600;"><?= val($emp, 'emergency_contact_phone') ?></div>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
