<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: /payslip_generator/public/admin/employees.php?error=missing_id");
    exit;
}

$employeeModel = new Employee();
$emp = $employeeModel->getEmployeeById($id);
if (!$emp) {
    header("Location: /payslip_generator/public/admin/employees.php?error=not_found");
    exit;
}

// Get current user role
$db = getDBConnection();
$stmt = $db->prepare("SELECT role FROM users WHERE employee_id = ? LIMIT 1");
$stmt->execute([$id]);
$userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
$currentUserRole = $userRecord['role'] ?? 'employee';

$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Enterprise Payroll Solutions</title>
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
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-group label { display:block; margin-bottom:8px; color:#2c3e50; font-weight:500; font-size:14px; }
        .form-group label i { margin-right:5px; color:#667eea; }
        .form-group input, .form-group select {
            width: 100%; padding: 12px 15px; border:2px solid #e0e0e0; border-radius:8px; font-size:14px; transition:all 0.3s ease;
        }
        .form-group input:focus, .form-group select:focus { outline:none; border-color:#667eea; box-shadow:0 0 0 4px rgba(102,126,234,0.1); }
        .form-actions { display:flex; gap:15px; margin-top:30px; }
        .btn-cancel { background:#95a5a6; }
        .btn-cancel:hover { background:#7f8c8d; }
        @media (max-width: 768px) { .form-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-user-edit"></i> Edit Employee</h1>
            <p>Update employee details</p>
        </div>

        <div class="form-card">
            <form method="POST" action="../index.php?page=update-employee">
                <input type="hidden" name="employee_id" value="<?php echo htmlspecialchars($emp['employee_id']); ?>">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="full_name"><i class="fas fa-user"></i> Full Name</label>
                        <input type="text" id="full_name" name="full_name" required value="<?php echo htmlspecialchars($emp['full_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="email"><i class="fas fa-envelope"></i> Email Address</label>
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($emp['email']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="phone"><i class="fas fa-phone"></i> Phone Number</label>
                        <input type="text" id="phone" name="phone" required value="<?php echo htmlspecialchars($emp['phone']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="designation"><i class="fas fa-briefcase"></i> Designation</label>
                        <input type="text" id="designation" name="designation" required value="<?php echo htmlspecialchars($emp['designation']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="department_id"><i class="fas fa-building"></i> Department</label>
                        <select id="department_id" name="department_id" required>
                            <option value="">Select Department</option>
                            <option value="1" <?php echo $emp['department_id']==1?'selected':''; ?>>Administration</option>
                            <option value="2" <?php echo $emp['department_id']==2?'selected':''; ?>>Accounts</option>
                            <option value="3" <?php echo $emp['department_id']==3?'selected':''; ?>>HR</option>
                            <option value="4" <?php echo $emp['department_id']==4?'selected':''; ?>>IT</option>
                            <option value="5" <?php echo $emp['department_id']==5?'selected':''; ?>>Management</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="employment_type"><i class="fas fa-id-card"></i> Employment Type</label>
                        <select id="employment_type" name="employment_type" required>
                            <option value="">Select Type</option>
                            <option value="permanent" <?php echo $emp['employment_type']==='permanent'?'selected':''; ?>>Permanent</option>
                            <option value="contract" <?php echo $emp['employment_type']==='contract'?'selected':''; ?>>Contract</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="basic_salary"><i class="fas fa-dollar-sign"></i> Basic Salary</label>
                        <input type="number" id="basic_salary" name="basic_salary" step="0.01" required value="<?php echo htmlspecialchars($emp['basic_salary']); ?>" data-original="<?php echo htmlspecialchars($emp['basic_salary']); ?>">
                        <p class="form-hint" style="color: #e74c3c; font-weight: 500; display: none;" id="salary_warning">
                            <i class="fas fa-exclamation-triangle"></i> Salary changes require Director approval
                        </p>
                    </div>

                    <div class="form-group">
                        <label for="user_role"><i class="fas fa-user-tag"></i> User Role/Position</label>
                        <select id="user_role" name="user_role">
                            <option value="employee" <?php echo $currentUserRole==='employee'?'selected':''; ?>>Employee</option>
                            <option value="accountant" <?php echo $currentUserRole==='accountant'?'selected':''; ?>>Accountant</option>
                            <option value="director" <?php echo $currentUserRole==='director'?'selected':''; ?>>Director</option>
                            <option value="administrator" <?php echo $currentUserRole==='administrator'?'selected':''; ?>>Administrator</option>
                        </select>
                        <p class="form-hint" style="color: #e74c3c; font-weight: 500; display: none;" id="role_warning">
                            <i class="fas fa-exclamation-triangle"></i> Role changes require Director approval
                        </p>
                    </div>

                    <div class="form-group full-width" id="role_change_fields" style="display: none; background: #e8f4f8; padding: 15px; border-radius: 8px; border: 2px solid #b3d9e8; margin-bottom: 20px;">
                        <h4 style="color: #0c5377; margin-bottom: 15px;"><i class="fas fa-user-check"></i> Role Change Request Details</h4>
                        
                        <label for="role_change_reason">Reason for Role Change <span class="required">*</span></label>
                        <textarea id="role_change_reason" name="role_change_reason" rows="3" placeholder="E.g., Promoted to Accountant due to performance excellence and 5+ years experience" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                    </div>

                    <div class="form-group full-width" id="salary_change_fields" style="display: none; background: #fff4e5; padding: 15px; border-radius: 8px; border: 2px solid #ffd8a8; margin-bottom: 20px;">
                        <h4 style="color: #b35c00; margin-bottom: 15px;"><i class="fas fa-file-invoice-dollar"></i> Salary Change Request Details</h4>
                        
                        <label for="change_type">Change Type <span class="required">*</span></label>
                        <select id="change_type" name="change_type" style="margin-bottom: 15px;">
                            <option value="Annual Increment">Annual Increment</option>
                            <option value="DA Increase">DA (Dearness Allowance) Increase</option>
                            <option value="Promotion">Promotion</option>
                            <option value="Performance Bonus">Performance Bonus</option>
                            <option value="Government Mandate">Government Mandate (e.g., DA 58% in 2025)</option>
                            <option value="Other">Other</option>
                        </select>

                        <label for="change_reason">Reason for Change <span class="required">*</span></label>
                        <textarea id="change_reason" name="change_reason" rows="3" placeholder="E.g., DA increment 2025 - 58% as per government rules" style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px;"></textarea>
                    </div>

                    <div class="form-group full-width">
                        <label for="address"><i class="fas fa-map-marker-alt"></i> Address</label>
                        <input type="text" id="address" name="address" value="<?php echo htmlspecialchars($emp['address']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="city"><i class="fas fa-city"></i> City</label>
                        <input type="text" id="city" name="city" value="<?php echo htmlspecialchars($emp['city']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="state"><i class="fas fa-flag"></i> State</label>
                        <input type="text" id="state" name="state" value="<?php echo htmlspecialchars($emp['state']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="pincode"><i class="fas fa-mail-bulk"></i> Pincode</label>
                        <input type="text" id="pincode" name="pincode" value="<?php echo htmlspecialchars($emp['pincode']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_name"><i class="fas fa-user-shield"></i> Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($emp['emergency_contact_name']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_phone"><i class="fas fa-phone-alt"></i> Emergency Contact Phone</label>
                        <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($emp['emergency_contact_phone']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="emergency_contact_relation"><i class="fas fa-handshake"></i> Emergency Contact Relation</label>
                        <input type="text" id="emergency_contact_relation" name="emergency_contact_relation" value="<?php echo htmlspecialchars($emp['emergency_contact_relation']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="aadhaar_no"><i class="fas fa-id-card"></i> Aadhaar Number</label>
                        <input type="text" id="aadhaar_no" name="aadhaar_no" value="<?php echo htmlspecialchars($emp['aadhaar_no']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="pan_no"><i class="fas fa-id-badge"></i> PAN Number</label>
                        <input type="text" id="pan_no" name="pan_no" value="<?php echo htmlspecialchars($emp['pan_no']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="bank_account_no"><i class="fas fa-university"></i> Bank Account Number</label>
                        <input type="text" id="bank_account_no" name="bank_account_no" value="<?php echo htmlspecialchars($emp['bank_account_no']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="ifsc_code"><i class="fas fa-barcode"></i> IFSC Code</label>
                        <input type="text" id="ifsc_code" name="ifsc_code" value="<?php echo htmlspecialchars($emp['ifsc_code']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="experience_years"><i class="fas fa-briefcase"></i> Experience (years)</label>
                        <input type="number" step="0.1" id="experience_years" name="experience_years" value="<?php echo htmlspecialchars($emp['experience_years']); ?>">
                    </div>

                    <div class="form-group">
                        <label for="last_appraisal_date"><i class="fas fa-calendar-check"></i> Last Appraisal Date</label>
                        <input type="date" id="last_appraisal_date" name="last_appraisal_date" value="<?php echo htmlspecialchars($emp['last_appraisal_date']); ?>">
                    </div>

                    <div class="form-group full-width">
                        <label for="remarks"><i class="fas fa-comment-dots"></i> Remarks</label>
                        <input type="text" id="remarks" name="remarks" value="<?php echo htmlspecialchars($emp['remarks']); ?>">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Update Employee
                    </button>
                    <a href="employees.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Show/hide salary change fields when salary is modified
        const salaryInput = document.getElementById('basic_salary');
        const originalSalary = salaryInput.dataset.original;
        const salaryWarning = document.getElementById('salary_warning');
        const salaryChangeFields = document.getElementById('salary_change_fields');
        const changeTypeField = document.getElementById('change_type');
        const changeReasonField = document.getElementById('change_reason');

        // Role change fields
        const roleInput = document.getElementById('user_role');
        const originalRole = roleInput.value; // Set at page load
        const roleWarning = document.getElementById('role_warning');
        const roleChangeFields = document.getElementById('role_change_fields');
        const roleChangeReasonField = document.getElementById('role_change_reason');

        salaryInput.addEventListener('input', function() {
            if (parseFloat(this.value) !== parseFloat(originalSalary)) {
                salaryWarning.style.display = 'block';
                salaryChangeFields.style.display = 'block';
                changeTypeField.required = true;
                changeReasonField.required = true;
            } else {
                salaryWarning.style.display = 'none';
                salaryChangeFields.style.display = 'none';
                changeTypeField.required = false;
                changeReasonField.required = false;
            }
        });

        roleInput.addEventListener('change', function() {
            if (this.value !== originalRole) {
                roleWarning.style.display = 'block';
                roleChangeFields.style.display = 'block';
                roleChangeReasonField.required = true;
            } else {
                roleWarning.style.display = 'none';
                roleChangeFields.style.display = 'none';
                roleChangeReasonField.required = false;
            }
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            if (parseFloat(salaryInput.value) !== parseFloat(originalSalary)) {
                if (!changeReasonField.value.trim()) {
                    e.preventDefault();
                    alert('Please provide a reason for salary change');
                    changeReasonField.focus();
                    return false;
                }
            }

            if (roleInput.value !== originalRole) {
                if (!roleChangeReasonField.value.trim()) {
                    e.preventDefault();
                    alert('Please provide a reason for role change');
                    roleChangeReasonField.focus();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
