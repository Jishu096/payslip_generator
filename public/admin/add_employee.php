<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
            max-width: 900px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #2c3e50;
            font-weight: 500;
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 5px;
            color: #667eea;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-cancel {
            background: #95a5a6;
        }

        .btn-cancel:hover {
            background: #7f8c8d;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Add New Employee</h1>
            <p>Create a new employee record with complete details</p>
        </div>

        <div class="form-card">
            <form method="POST" action="../index.php?page=add-employee">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" id="full_name" name="full_name" required placeholder="Enter full name">
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="email" name="email" required placeholder="employee@company.com">
                    </div>

                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" id="phone" name="phone" required placeholder="+1 234 567 890">
                    </div>

                    <div class="form-group">
                        <label for="designation"><i class="fas fa-briefcase"></i> Designation</label>
                        <input type="text" id="designation" name="designation" required placeholder="e.g., Senior Developer">
                    </div>

                    <div class="form-group">
                        <label for="department_id"><i class="fas fa-building"></i> Department</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <option value="1">Administration</option>
                            <option value="2">Accounts</option>
                            <option value="3">HR</option>
                            <option value="4">IT</option>
                            <option value="5">Management</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="employment_type"><i class="fas fa-id-card"></i> Employment Type</label>
                        <select id="employment_type" name="employment_type" required>
                            <option value="">Select Type</option>
                            <option value="permanent">Permanent</option>
                            <option value="contract">Contract</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="basic_salary"><i class="fas fa-dollar-sign"></i> Basic Salary</label>
                        <input type="number" id="basic_salary" name="basic_salary" step="0.01" required placeholder="0.00">
                    </div>

                    <div class="form-group full-width">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <input type="text" id="address" name="address" placeholder="Enter complete address">
                    </div>

                    <div class="form-group">
                        <label for="city"><i class="fas fa-city"></i> City</label>
                        <input type="text" id="city" name="city" placeholder="City">
                    </div>

                    <div class="form-group">
                        <label for="state"><i class="fas fa-flag"></i> State</label>
                        <input type="text" id="state" name="state" placeholder="State">
                    </div>

                    <div class="form-group">
                        <label for="pincode"><i class="fas fa-mail-bulk"></i> Pincode</label>
                        <input type="text" id="pincode" name="pincode" placeholder="Pincode">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_name"><i class="fas fa-user-shield"></i> Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" placeholder="Contact name">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_phone"><i class="fas fa-phone-alt"></i> Emergency Contact Phone</label>
                        <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" placeholder="Contact phone">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_relation"><i class="fas fa-handshake"></i> Emergency Contact Relation</label>
                        <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" placeholder="Relation">
                    </div>

                    <div class="form-group">
                        <label for="aadhaar_no"><i class="fas fa-id-card"></i> Aadhaar Number</label>
                        <input type="text" id="aadhaar_no" name="aadhaar_no" placeholder="Aadhaar number">
                    </div>

                    <div class="form-group">
                        <label for="pan_no"><i class="fas fa-id-badge"></i> PAN Number</label>
                        <input type="text" id="pan_no" name="pan_no" placeholder="PAN number">
                    </div>

                    <div class="form-group">
                        <label for="bank_account_no"><i class="fas fa-university"></i> Bank Account Number</label>
                        <input type="text" id="bank_account_no" name="bank_account_no" placeholder="Account number">
                    </div>

                    <div class="form-group">
                        <label for="ifsc_code"><i class="fas fa-barcode"></i> IFSC Code</label>
                        <input type="text" id="ifsc_code" name="ifsc_code" placeholder="IFSC code">
                    </div>

                    <div class="form-group">
                        <label for="experience_years"><i class="fas fa-briefcase"></i> Experience (years)</label>
                        <input type="number" step="0.1" id="experience_years" name="experience_years" placeholder="0.0">
                    </div>

                    <div class="form-group">
                        <label for="last_appraisal_date"><i class="fas fa-calendar-check"></i> Last Appraisal Date</label>
                        <input type="date" id="last_appraisal_date" name="last_appraisal_date">
                    </div>

                    <div class="form-group full-width">
                        <label for="remarks"><i class="fas fa-comment-dots"></i> Remarks</label>
                        <input type="text" id="remarks" name="remarks" placeholder="Any notes or remarks">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Save Employee
                    </button>
                    <a href="employees.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

</body>
</html>
