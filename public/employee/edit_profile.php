<?php
session_start();
$currentPage = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

if (!isset($_SESSION['employee_id'])) {
    die("Employee ID missing.");
}

$employeeId = $_SESSION['employee_id'];

// Database
require_once __DIR__ . "/../../backend/config/database.php";

$db = new Database();
$conn = $db->connect();

// Fetch employee data
$sql = "SELECT e.*, d.department_name 
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.employee_id = :eid LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->execute([":eid" => $employeeId]);
$emp = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$emp) {
    die("Employee not found.");
}

function val($key){
    global $emp;
    return htmlspecialchars($emp[$key] ?? '');
}
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Edit Profile - Employee</title>

    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body { background:#f5f6fa; font-family:"Segoe UI",sans-serif; }
        .sidebar {
            width:250px; height:100vh; position:fixed;
            background:linear-gradient(135deg,#667eea,#764ba2);
            padding:20px; color:white;
        }
        .sidebar h3 { text-align:center; margin-bottom:30px; font-weight:700; }
        .sidebar a {
            display:block; padding:12px 15px; color:white; text-decoration:none;
            border-radius:6px; margin-bottom:10px;
        }
        .sidebar a:hover, .active-link { background:rgba(255,255,255,0.3); }
        .main { margin-left:270px; padding:30px; }
        .section-box {
            background:white; padding:25px; border-radius:10px;
            box-shadow:0 2px 6px rgba(0,0,0,0.08); margin-bottom:25px;
        }
        .section-header {
            font-size:18px; font-weight:600;
            border-bottom:1px solid #eee; padding-bottom:8px; margin-bottom:20px;
        }
    </style>
</head>

<body>

<!-- Sidebar -->
<div class="sidebar">
    <h3><i class="fas fa-file-invoice-dollar"></i> Payslip</h3>

    <a href="employee_dashboard.php"><i class="fa fa-home"></i> Dashboard</a>
    <a href="employee_profile.php"><i class="fa fa-user"></i> My Profile</a>
    <a href="edit_profile.php" class="active-link"><i class="fa fa-edit"></i> Edit Profile</a>
    <a href="view_payslips.php"><i class="fa fa-file"></i> Payslips</a>
    <a href="attendance.php"><i class="fa fa-calendar-check"></i> Attendance</a>
</div>

<div class="main">

    <div class="section-box">
        <div class="section-header"><i class="fa fa-edit"></i> Edit Profile (Approval Required)</div>

        <form method="POST" action="../../backend/public/index.php?page=request-profile-update">

            <!-- Phone -->
            <div class="mb-3">
                <label class="form-label">Phone Number</label>
                <input type="text" name="phone" class="form-control" value="<?= val('phone') ?>" required>
            </div>

            <!-- Address Fields -->
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Address</label>
                    <input type="text" name="address" class="form-control" value="<?= val('address') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">City</label>
                    <input type="text" name="city" class="form-control" value="<?= val('city') ?>">
                </div>
            </div>

            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">State</label>
                    <input type="text" name="state" class="form-control" value="<?= val('state') ?>">
                </div>

                <div class="col-md-6 mb-3">
                    <label class="form-label">Pincode</label>
                    <input type="text" name="pincode" class="form-control" value="<?= val('pincode') ?>">
                </div>
            </div>

            <!-- Emergency Contacts -->
            <div class="section-header mt-4">Emergency Contact</div>

            <div class="mb-3">
                <label class="form-label">Contact Name</label>
                <input type="text" name="emergency_contact_name" class="form-control" value="<?= val('emergency_contact_name') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Relation</label>
                <input type="text" name="emergency_contact_relation" class="form-control" value="<?= val('emergency_contact_relation') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" name="emergency_contact_phone" class="form-control" value="<?= val('emergency_contact_phone') ?>">
            </div>

            <button class="btn btn-primary w-100 mt-3">
                Submit Update Request
            </button>
        </form>
    </div>

</div>

</body>
</html>
