<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['employee_id'])) {
    die("Employee ID missing from session.");
}

$employeeId = $_SESSION['employee_id'];

// DB Connection
require_once __DIR__ . "/../../backend/config/database.php";

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
    <title>My Profile - Employee</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            font-family: "Segoe UI", sans-serif;
            background: #f5f6fa;
        }

        /* Sidebar (reuse style from dashboard for consistency) */
        .sidebar {
            width: 250px;
            height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            position: fixed;
            padding: 20px;
            color: #fff;
        }
        .sidebar h3 {
            text-align: center;
            margin-bottom: 35px;
            font-weight: 700;
        }
        .sidebar a {
            display: block;
            padding: 12px 15px;
            color: #fff;
            text-decoration: none;
            margin-bottom: 12px;
            border-radius: 6px;
        }
        .sidebar a.active-link {
            background: rgba(255,255,255,0.35);
            font-weight: 600;
        }
        .sidebar a:hover {
            background: rgba(255,255,255,0.25);
        }

        /* Main layout */
        .main {
            margin-left: 270px;
            padding: 30px;
        }

        .section-box {
            background: #fff;
            border-radius: 10px;
            padding: 20px 22px;
            margin-bottom: 20px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.06);
        }

        .section-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 15px;
            border-bottom: 1px solid #eee;
            padding-bottom: 6px;
            color: #333;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
        }

        .info-box label {
            font-size: 12px;
            color: #777;
            margin-bottom: 3px;
            display: block;
        }

        .info-box div {
            font-size: 14px;
            font-weight: 600;
            color: #333;
        }

        .avatar-large {
            width: 110px;
            height: 110px;
            border-radius: 50%;
            background: #6c5ce7;
            display: flex;
            justify-content: center;
            align-items: center;
            color: #fff;
            font-size: 42px;
            font-weight: 700;
            margin: 0 auto 12px;
        }

        .profile-header-name {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .profile-header-sub {
            font-size: 13px;
            color: #666;
        }
    </style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <h3><i class="fas fa-file-invoice-dollar"></i> Payslip</h3>

    <a href="employee_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
    <a href="employee_profile.php" class="active-link"><i class="fas fa-user"></i> My Profile</a>
    <a href="view_payslips.php"><i class="fas fa-file-invoice"></i> Payslips</a>
    <a href="attendance.php"><i class="fas fa-calendar-check"></i> Attendance</a>
</div>

<!-- Main Content -->
<div class="main">

    <!-- Profile header card -->
    <div class="section-box text-center">
        <div class="avatar-large"><?= $avatarLetter ?></div>
        <div class="profile-header-name"><?= htmlspecialchars($emp['full_name']) ?></div>
        <div class="profile-header-sub">
            <?= val($emp, 'designation', 'No designation') ?>
            <?php if (!empty($emp['department_name'])): ?>
                • <?= htmlspecialchars($emp['department_name']) ?>
            <?php endif; ?>
        </div>
        <div class="profile-header-sub mt-1">
            Employee ID: <strong><?= htmlspecialchars($emp['employee_id']) ?></strong>
        </div>
    </div>

    <!-- Personal & Contact Information -->
    <div class="section-box">
        <div class="section-header"><i class="fas fa-id-card"></i> Personal & Contact Information</div>

        <div class="profile-grid">
            <div class="info-box">
                <label>Full Name</label>
                <div><?= htmlspecialchars($emp['full_name']) ?></div>
            </div>

            <div class="info-box">
                <label>Email</label>
                <div><?= val($emp, 'email') ?></div>
            </div>

            <div class="info-box">
                <label>Phone</label>
                <div><?= val($emp, 'phone') ?></div>
            </div>

            <div class="info-box">
                <label>Address</label>
                <div><?= val($emp, 'address') ?></div>
            </div>

            <div class="info-box">
                <label>City</label>
                <div><?= val($emp, 'city') ?></div>
            </div>

            <div class="info-box">
                <label>State</label>
                <div><?= val($emp, 'state') ?></div>
            </div>

            <div class="info-box">
                <label>Pincode</label>
                <div><?= val($emp, 'pincode') ?></div>
            </div>
        </div>
    </div>

    <!-- Job & Employment Details -->
    <div class="section-box">
        <div class="section-header"><i class="fas fa-briefcase"></i> Job & Employment Details</div>

        <div class="profile-grid">
            <div class="info-box">
                <label>Employee ID</label>
                <div><?= htmlspecialchars($emp['employee_id']) ?></div>
            </div>

            <div class="info-box">
                <label>Designation</label>
                <div><?= val($emp, 'designation') ?></div>
            </div>

            <div class="info-box">
                <label>Department</label>
                <div><?= val($emp, 'department_name') ?></div>
            </div>

            <div class="info-box">
                <label>Employment Type</label>
                <div><?= val($emp, 'employment_type') ?></div>
            </div>

            <div class="info-box">
                <label>Date of Joining</label>
                <div><?= val($emp, 'join_date') ?></div>
            </div>

            <div class="info-box">
                <label>Experience (Years)</label>
                <div><?= val($emp, 'experience_years') ?></div>
            </div>
        </div>
    </div>

    <!-- Compensation & Benefits -->
    <div class="section-box">
        <div class="section-header"><i class="fas fa-coins"></i> Compensation & Benefits</div>

        <div class="profile-grid">
            <div class="info-box">
                <label>Basic Salary</label>
                <div>₹<?= number_format((float)$emp['basic_salary']) ?></div>
            </div>

            <div class="info-box">
                <label>DA (58%)</label>
                <div>
                    ₹<?php
                        $da = (float)$emp['basic_salary'] * 0.58;
                        echo number_format($da);
                    ?>
                </div>
            </div>

            <div class="info-box">
                <label>Estimated Total (Basic + DA)</label>
                <div>
                    ₹<?php
                        $total = (float)$emp['basic_salary'] + $da;
                        echo number_format($total);
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Emergency Contact -->
    <div class="section-box">
        <div class="section-header"><i class="fas fa-user-shield"></i> Emergency Contact</div>

        <div class="profile-grid">
            <div class="info-box">
                <label>Contact Name</label>
                <div><?= val($emp, 'emergency_contact_name') ?></div>
            </div>

            <div class="info-box">
                <label>Relationship</label>
                <div><?= val($emp, 'emergency_contact_relation') ?></div>
            </div>

            <div class="info-box">
                <label>Phone</label>
                <div><?= val($emp, 'emergency_contact_phone') ?></div>
            </div>
        </div>
    </div>

    <!-- Documents & IDs -->
    <div class="section-box">
        <div class="section-header"><i class="fas fa-folder-open"></i> Documents & IDs</div>

        <div class="profile-grid">
            <div class="info-box">
                <label>Aadhaar Number</label>
                <div><?= val($emp, 'aadhaar_no') ?></div>
            </div>

            <div class="info-box">
                <label>PAN Number</label>
                <div><?= val($emp, 'pan_no') ?></div>
            </div>

            <div class="info-box">
                <label>Bank Account No.</label>
                <div><?= val($emp, 'bank_account_no') ?></div>
            </div>

            <div class="info-box">
                <label>IFSC Code</label>
                <div><?= val($emp, 'ifsc_code') ?></div>
            </div>
        </div>
    </div>

    <!-- Performance & Remarks -->
    <div class="section-box">
        <div class="section-header"><i class="fas fa-chart-line"></i> Performance & History</div>

        <div class="profile-grid">
            <div class="info-box">
                <label>Last Appraisal Date</label>
                <div><?= val($emp, 'last_appraisal_date') ?></div>
            </div>

            <div class="info-box">
                <label>Remarks</label>
                <div><?= val($emp, 'remarks', 'No remarks yet.') ?></div>
            </div>
        </div>
    </div>

</div>

</body>
</html>
