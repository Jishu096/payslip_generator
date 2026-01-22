<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['employee_id'])) {
    die("Employee ID missing from session.");
}

$employeeId = $_SESSION['employee_id'];

// DB Connection
require_once __DIR__ . "/../../app/Config/database.php";

$db  = new Database();
$conn = $db->connect();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Employee Portal</title>
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
                <span>My Profile</span>
            </div>
            <h1><i class="fas fa-user-circle"></i> My Profile</h1>
        </div>

        <!-- Profile Header -->
        <div class="profile-header">
            <div class="profile-avatar"><?= $avatarLetter ?></div>
            <div class="profile-details">
                <h2><?= htmlspecialchars($emp['full_name']) ?></h2>
                <p><i class="fas fa-id-badge"></i> Employee ID: <?= htmlspecialchars($emp['employee_id']) ?></p>
                <p><i class="fas fa-briefcase"></i> <?= val($emp, 'designation') ?> • <?= val($emp, 'department_name') ?></p>
                <p><i class="fas fa-envelope"></i> <?= val($emp, 'email') ?></p>
            </div>
        </div>

        <!-- Personal Information -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-user"></i>
                <span>Personal Information</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <div class="value"><?= htmlspecialchars($emp['full_name']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Date of Birth</label>
                        <div class="value"><?= val($emp, 'dob') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Gender</label>
                        <div class="value"><?= val($emp, 'gender') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Email</label>
                        <div class="value"><?= val($emp, 'email') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <div class="value"><?= val($emp, 'phone') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Address</label>
                        <div class="value"><?= val($emp, 'address') ?></div>
                    </div>
                    <div class="info-item">
                        <label>City</label>
                        <div class="value"><?= val($emp, 'city') ?></div>
                    </div>
                    <div class="info-item">
                        <label>State</label>
                        <div class="value"><?= val($emp, 'state') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Pincode</label>
                        <div class="value"><?= val($emp, 'pincode') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Job & Employment Details -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-briefcase"></i>
                <span>Job & Employment Details</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Employee ID</label>
                        <div class="value"><?= htmlspecialchars($emp['employee_id']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>Designation</label>
                        <div class="value"><?= val($emp, 'designation') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Department</label>
                        <div class="value"><?= val($emp, 'department_name') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Employment Type</label>
                        <div class="value"><?= val($emp, 'employment_type') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Date of Joining</label>
                        <div class="value"><?= val($emp, 'join_date') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Experience (Years)</label>
                        <div class="value"><?= val($emp, 'experience_years') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Compensation & Benefits -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-coins"></i>
                <span>Compensation & Benefits</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Basic Salary</label>
                        <div class="value">₹<?= number_format((float)$emp['basic_salary']) ?></div>
                    </div>
                    <div class="info-item">
                        <label>DA (58%)</label>
                        <div class="value">
                            ₹<?php
                                $da = (float)$emp['basic_salary'] * 0.58;
                                echo number_format($da);
                            ?>
                        </div>
                    </div>
                    <div class="info-item">
                        <label>Estimated Total (Basic + DA)</label>
                        <div class="value">
                            ₹<?php
                                $total = (float)$emp['basic_salary'] + $da;
                                echo number_format($total);
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Emergency Contact -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-user-shield"></i>
                <span>Emergency Contact</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Contact Name</label>
                        <div class="value"><?= val($emp, 'emergency_contact_name') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Relationship</label>
                        <div class="value"><?= val($emp, 'emergency_contact_relation') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Phone</label>
                        <div class="value"><?= val($emp, 'emergency_contact_phone') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Documents & IDs -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-folder-open"></i>
                <span>Documents & IDs</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Aadhaar Number</label>
                        <div class="value"><?= val($emp, 'aadhaar_no') ?></div>
                    </div>
                    <div class="info-item">
                        <label>PAN Number</label>
                        <div class="value"><?= val($emp, 'pan_no') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Bank Account No.</label>
                        <div class="value"><?= val($emp, 'bank_account_no') ?></div>
                    </div>
                    <div class="info-item">
                        <label>IFSC Code</label>
                        <div class="value"><?= val($emp, 'ifsc_code') ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Performance & Remarks -->
        <div class="section-card">
            <div class="section-header">
                <i class="fas fa-chart-line"></i>
                <span>Performance & History</span>
            </div>
            <div class="section-body">
                <div class="info-grid">
                    <div class="info-item">
                        <label>Last Appraisal Date</label>
                        <div class="value"><?= val($emp, 'last_appraisal_date') ?></div>
                    </div>
                    <div class="info-item">
                        <label>Remarks</label>
                        <div class="value"><?= val($emp, 'remarks', 'No remarks yet.') ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>
