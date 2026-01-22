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
    die("Employee ID missing.");
}

$employeeId = $_SESSION['employee_id'];

// Database
require_once __DIR__ . "/../../app/Config/database.php";

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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Employee Portal</title>
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
                <a href="employee_profile.php">My Profile</a>
                <i class="fas fa-chevron-right"></i>
                <span>Edit Profile</span>
            </div>
            <h1><i class="fas fa-user-edit"></i> Edit Profile</h1>
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-edit"></i>
                <h2>Edit Profile (Approval Required)</h2>
            </div>

            <div class="card-body">
                <form method="POST" action="../../backend/public/index.php?page=request-profile-update">

                    <!-- Phone -->
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" value="<?= val('phone') ?>" required>
                    </div>

                    <!-- Address Fields -->
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Address</label>
                            <input type="text" name="address" class="form-control" value="<?= val('address') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= val('city') ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?= val('state') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Pincode</label>
                            <input type="text" name="pincode" class="form-control" value="<?= val('pincode') ?>">
                        </div>
                    </div>

                    <!-- Emergency Contacts -->
                    <div class="section-divider">Emergency Contact</div>

                    <div class="form-group">
                        <label class="form-label">Contact Name</label>
                        <input type="text" name="emergency_contact_name" class="form-control" value="<?= val('emergency_contact_name') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Relation</label>
                        <input type="text" name="emergency_contact_relation" class="form-control" value="<?= val('emergency_contact_relation') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Contact Number</label>
                        <input type="text" name="emergency_contact_phone" class="form-control" value="<?= val('emergency_contact_phone') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-top: 20px;">
                        <i class="fas fa-paper-plane"></i> Submit Update Request
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>
