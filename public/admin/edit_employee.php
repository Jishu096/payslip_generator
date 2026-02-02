<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
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

// Fetch Pay Levels for permanent employees (7th CPC)
$payLevels = [];
try {
    $stmt = $db->query("SELECT level_id, level_name, level_number, min_basic, max_basic, transport_allowance, description FROM pay_levels WHERE is_active = 1 ORDER BY level_number");
    $payLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent fail
}

$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Employee - Enterprise Payroll Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --input-bg: #ffffff;
            --input-border: #e0e0e0;
            --input-focus: #667eea;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        body {
            font-family: "Roboto", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .form-card {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 35px;
            max-width: 950px;
            border: 1px solid var(--border-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label i {
            margin-right: 8px;
            color: #667eea;
            width: 16px;
            text-align: center;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            font-size: 14px;
            font-family: "Roboto", sans-serif;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-tertiary);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 13px 28px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-cancel {
            background: linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%);
            box-shadow: 0 4px 15px rgba(149, 165, 166, 0.3);
        }

        .btn-cancel:hover {
            box-shadow: 0 6px 20px rgba(149, 165, 166, 0.4);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            .form-card {
                padding: 25px 20px;
            }
            .form-actions {
                flex-direction: column;
            }
            .btn {
                width: 100%;
                justify-content: center;
            }
        }

        .form-hint {
            display: block;
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }

        .pay-level-group, .hra-group {
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

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
                            <option value="intern" <?php echo $emp['employment_type']==='intern'?'selected':''; ?>>Intern</option>
                        </select>
                    </div>

                    <!-- Pay Level - Only for Permanent Employees (7th CPC) -->
                    <div class="form-group pay-level-group" id="payLevelGroup" style="<?php echo $emp['employment_type']==='permanent'?'':'display: none;'; ?>">
                        <label for="pay_level_id"><i class="fas fa-layer-group"></i> Pay Level (7th CPC)</label>
                        <select id="pay_level_id" name="pay_level_id">
                            <option value="">Select Pay Level</option>
                            <?php foreach ($payLevels as $level): ?>
                            <option value="<?= $level['level_id'] ?>" 
                                    data-min="<?= $level['min_basic'] ?>" 
                                    data-max="<?= $level['max_basic'] ?>"
                                    data-ta="<?= $level['transport_allowance'] ?>"
                                    <?= ($emp['pay_level_id'] == $level['level_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($level['level_name']) ?> 
                                (₹<?= number_format($level['min_basic']) ?> - ₹<?= number_format($level['max_basic']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint" id="payLevelHint">Transport Allowance based on Pay Level</small>
                    </div>

                    <!-- HRA Type - Only for Permanent Employees -->
                    <div class="form-group hra-group" id="hraGroup" style="<?php echo $emp['employment_type']==='permanent'?'':'display: none;'; ?>">
                        <label for="hra_type"><i class="fas fa-home"></i> HRA City Category</label>
                        <select id="hra_type" name="hra_type">
                            <option value="city_b" <?php echo ($emp['hra_type'] ?? 'city_b')==='city_b'?'selected':''; ?>>Category B - 16% (Default)</option>
                            <option value="city_a" <?php echo ($emp['hra_type'] ?? 'city_b')==='city_a'?'selected':''; ?>>Category A - 24% (Metro Cities)</option>
                            <option value="city_c" <?php echo ($emp['hra_type'] ?? 'city_b')==='city_c'?'selected':''; ?>>Category C - 8% (Other Cities)</option>
                        </select>
                        <small class="form-hint">HRA percentage is calculated on Basic Pay</small>
                    </div>

                    <div class="form-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status" required>
                            <option value="active" <?php echo ($emp['status'] ?? 'active')==='active'?'selected':''; ?>>Active</option>
                            <option value="inactive" <?php echo ($emp['status'] ?? 'active')==='inactive'?'selected':''; ?>>Inactive</option>
                            <option value="on_leave" <?php echo ($emp['status'] ?? 'active')==='on_leave'?'selected':''; ?>>On Leave</option>
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
                        <input type="text" id="pan_no" name="pan_no" value="<?php echo htmlspecialchars($emp['pan_no'] ?? ''); ?>" placeholder="ABCDE1234F">
                    </div>

                    <div class="form-group">
                        <label for="bank_name"><i class="fas fa-landmark"></i> Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" value="<?php echo htmlspecialchars($emp['bank_name'] ?? ''); ?>" placeholder="e.g. State Bank of India">
                    </div>

                    <div class="form-group">
                        <label for="bank_branch"><i class="fas fa-map-marker-alt"></i> Bank Branch</label>
                        <input type="text" id="bank_branch" name="bank_branch" value="<?php echo htmlspecialchars($emp['bank_branch'] ?? ''); ?>" placeholder="e.g. Bhubaneswar Main Branch">
                    </div>

                    <div class="form-group">
                        <label for="bank_account_no"><i class="fas fa-university"></i> Bank Account Number</label>
                        <input type="text" id="bank_account_no" name="bank_account_no" value="<?php echo htmlspecialchars($emp['bank_account_no'] ?? ''); ?>" placeholder="Account Number">
                    </div>

                    <div class="form-group">
                        <label for="ifsc_code"><i class="fas fa-barcode"></i> IFSC Code</label>
                        <input type="text" id="ifsc_code" name="ifsc_code" value="<?php echo htmlspecialchars($emp['ifsc_code'] ?? ''); ?>" placeholder="e.g. SBIN0001234">
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
        // Handle salary based on employment type
        const employmentTypeSelect = document.getElementById('employment_type');
        const salaryLabel = document.getElementById('salary_label');
        const payLevelGroup = document.getElementById('payLevelGroup');
        const hraGroup = document.getElementById('hraGroup');
        const payLevelSelect = document.getElementById('pay_level_id');
        const payLevelHint = document.getElementById('payLevelHint');
        
        function handleEmploymentTypeSalary() {
            const salaryInput = document.getElementById('basic_salary');
            if (employmentTypeSelect.value === 'intern') {
                // Interns: Fixed stipend of 10,000
                salaryInput.value = '10000.00';
                salaryInput.readOnly = true;
                salaryInput.placeholder = '10000.00 (Fixed)';
                if (salaryLabel) salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Stipend (Fixed for Interns)';
                payLevelGroup.style.display = 'none';
                hraGroup.style.display = 'none';
                payLevelSelect.required = false;
            } else if (employmentTypeSelect.value === 'contract') {
                // Contract: Manual entry (contractual basis)
                salaryInput.readOnly = false;
                salaryInput.placeholder = 'Enter contractual amount';
                if (salaryLabel) salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Contractual Pay (Manual Entry)';
                payLevelGroup.style.display = 'none';
                hraGroup.style.display = 'none';
                payLevelSelect.required = false;
            } else if (employmentTypeSelect.value === 'permanent') {
                // Permanent: Show Pay Level fields (7th CPC)
                salaryInput.readOnly = false;
                salaryInput.placeholder = 'Enter basic salary within Pay Level range';
                if (salaryLabel) salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Basic Salary (7th CPC)';
                payLevelGroup.style.display = 'block';
                hraGroup.style.display = 'block';
                payLevelSelect.required = true;
            } else {
                // Default
                salaryInput.readOnly = false;
                if (salaryLabel) salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Basic Salary';
                payLevelGroup.style.display = 'none';
                hraGroup.style.display = 'none';
                payLevelSelect.required = false;
            }
        }
        
        // Check on page load
        handleEmploymentTypeSalary();
        
        employmentTypeSelect.addEventListener('change', handleEmploymentTypeSalary);

        // Update salary hint when Pay Level changes
        payLevelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const minBasic = parseFloat(selectedOption.dataset.min);
                const maxBasic = parseFloat(selectedOption.dataset.max);
                const ta = parseFloat(selectedOption.dataset.ta);
                
                payLevelHint.innerHTML = `<strong>Range:</strong> ₹${minBasic.toLocaleString()} - ₹${maxBasic.toLocaleString()} | <strong>TA:</strong> ₹${ta.toLocaleString()}/month`;
                payLevelHint.style.color = '#10b981';
            } else {
                payLevelHint.innerHTML = 'Transport Allowance based on Pay Level';
                payLevelHint.style.color = '#64748b';
            }
        });

        // Trigger change event on load to show current Pay Level info
        if (payLevelSelect.value) {
            payLevelSelect.dispatchEvent(new Event('change'));
        }
        
        // Show/hide salary change fields when salary is modified
                salaryInput.placeholder = 'Enter contractual amount';
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Contractual Pay (Manual Entry)';
            } else if (employmentTypeSelect.value === 'permanent') {
                // Permanent: Standard basic salary
                salaryInput.readOnly = false;
                salaryInput.placeholder = 'Enter basic salary';
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Basic Salary';
            } else {
                // Default
                salaryInput.readOnly = false;
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Basic Salary';
            }
        }
        
        // Check on page load
        handleEmploymentTypeSalary();
        
        employmentTypeSelect.addEventListener('change', handleEmploymentTypeSalary);
        
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
            // Validate Pay Level salary range for permanent employees
            const empType = employmentTypeSelect.value;
            if (empType === 'permanent' && payLevelSelect.value) {
                const selectedOption = payLevelSelect.options[payLevelSelect.selectedIndex];
                const minBasic = parseFloat(selectedOption.dataset.min);
                const maxBasic = parseFloat(selectedOption.dataset.max);
                const salary = parseFloat(salaryInput.value);
                
                if (salary < minBasic || salary > maxBasic) {
                    e.preventDefault();
                    alert(`Basic salary must be between ₹${minBasic.toLocaleString()} and ₹${maxBasic.toLocaleString()} for the selected Pay Level.`);
                    salaryInput.focus();
                    return false;
                }
            }

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
