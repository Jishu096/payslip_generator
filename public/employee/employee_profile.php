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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Employee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #ffffff;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .breadcrumb i {
            margin: 0 8px;
            font-size: 10px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .profile-header {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 48px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .profile-details h2 {
            font-size: 28px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 8px;
        }

        .profile-details p {
            font-size: 16px;
            color: #718096;
            margin-bottom: 4px;
        }

        .section-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .section-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }

        .section-header i {
            color: #667eea;
            font-size: 20px;
        }

        .section-body {
            padding: 25px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }

        .info-item {
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .info-item label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item .value {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-avatar {
                width: 100px;
                height: 100px;
                font-size: 40px;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .back-btn {
                width: 50px;
                height: 50px;
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
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

    <!-- Back Button -->
    <a href="dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>
</body>
</html>
