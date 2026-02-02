<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Fetch Pay Levels for permanent employees (7th CPC)
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

$payLevels = [];
$hraCities = [];
try {
    // Get pay levels
    $stmt = $conn->query("SELECT level_id, level_name, level_number, min_basic, max_basic, transport_allowance, description FROM pay_levels WHERE is_active = 1 ORDER BY level_number");
    $payLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get HRA cities grouped by category
    $stmt = $conn->query("SELECT city_id, city_name, state, hra_category, hra_percentage FROM hra_cities WHERE is_active = 1 ORDER BY hra_category, city_name");
    $hraCities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Silent fail - will show empty dropdowns
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Employee - Enterprise Payroll Solutions</title>
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
                            <option value="intern">Intern</option>
                        </select>
                    </div>

                    <!-- Pay Level - Only for Permanent Employees (7th CPC) -->
                    <div class="form-group pay-level-group" id="payLevelGroup" style="display: none;">
                        <label for="pay_level_id"><i class="fas fa-layer-group"></i> Pay Level (7th CPC)</label>
                        <select id="pay_level_id" name="pay_level_id">
                            <option value="">Select Pay Level</option>
                            <?php foreach ($payLevels as $level): ?>
                            <option value="<?= $level['level_id'] ?>" 
                                    data-min="<?= $level['min_basic'] ?>" 
                                    data-max="<?= $level['max_basic'] ?>"
                                    data-ta="<?= $level['transport_allowance'] ?>">
                                <?= htmlspecialchars($level['level_name']) ?> 
                                (₹<?= number_format($level['min_basic']) ?> - ₹<?= number_format($level['max_basic']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint" id="payLevelHint">Transport Allowance will be set based on selected level</small>
                    </div>

                    <!-- HRA Type - Only for Permanent Employees -->
                    <div class="form-group hra-group" id="hraGroup" style="display: none;">
                        <label for="hra_type"><i class="fas fa-home"></i> HRA City Category</label>
                        <select id="hra_type" name="hra_type">
                            <option value="city_b">Category B - 16% (Default)</option>
                            <option value="city_a">Category A - 24% (Metro Cities)</option>
                            <option value="city_c">Category C - 8% (Other Cities)</option>
                        </select>
                        <small class="form-hint">HRA percentage is calculated on Basic Pay</small>
                    </div>

                    <div class="form-group">
                        <label for="status"><i class="fas fa-toggle-on"></i> Status</label>
                        <select id="status" name="status" required>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="on_leave">On Leave</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="basic_salary" id="salary_label"><i class="fas fa-dollar-sign"></i> Basic Salary</label>
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
                        <input type="text" id="pan_no" name="pan_no" placeholder="e.g. ABCDE1234F">
                    </div>

                    <div class="form-group">
                        <label for="bank_name"><i class="fas fa-landmark"></i> Bank Name</label>
                        <input type="text" id="bank_name" name="bank_name" placeholder="e.g. State Bank of India">
                    </div>

                    <div class="form-group">
                        <label for="bank_branch"><i class="fas fa-map-marker-alt"></i> Bank Branch</label>
                        <input type="text" id="bank_branch" name="bank_branch" placeholder="e.g. Bhubaneswar Main Branch">
                    </div>

                    <div class="form-group">
                        <label for="bank_account_no"><i class="fas fa-university"></i> Bank Account Number</label>
                        <input type="text" id="bank_account_no" name="bank_account_no" placeholder="Account number">
                    </div>

                    <div class="form-group">
                        <label for="ifsc_code"><i class="fas fa-barcode"></i> IFSC Code</label>
                        <input type="text" id="ifsc_code" name="ifsc_code" placeholder="e.g. SBIN0001234">
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

    <script>
        // Handle salary and Pay Level visibility based on employment type
        const employmentTypeSelect = document.getElementById('employment_type');
        const salaryInput = document.getElementById('basic_salary');
        const salaryLabel = document.getElementById('salary_label');
        const payLevelGroup = document.getElementById('payLevelGroup');
        const hraGroup = document.getElementById('hraGroup');
        const payLevelSelect = document.getElementById('pay_level_id');
        const payLevelHint = document.getElementById('payLevelHint');
        
        employmentTypeSelect.addEventListener('change', function() {
            if (this.value === 'intern') {
                // Interns: Fixed stipend of 10,000
                salaryInput.value = '10000.00';
                salaryInput.readOnly = true;
                salaryInput.placeholder = '10000.00 (Fixed)';
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Stipend (Fixed for Interns)';
                payLevelGroup.style.display = 'none';
                hraGroup.style.display = 'none';
                payLevelSelect.required = false;
            } else if (this.value === 'contract') {
                // Contract: Manual entry (contractual basis)
                salaryInput.readOnly = false;
                salaryInput.value = '';
                salaryInput.placeholder = 'Enter contractual amount';
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Contractual Pay (Manual Entry)';
                payLevelGroup.style.display = 'none';
                hraGroup.style.display = 'none';
                payLevelSelect.required = false;
            } else if (this.value === 'permanent') {
                // Permanent: Show Pay Level fields (7th CPC)
                salaryInput.readOnly = false;
                salaryInput.value = '';
                salaryInput.placeholder = 'Enter basic salary within Pay Level range';
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Basic Salary (7th CPC)';
                payLevelGroup.style.display = 'block';
                hraGroup.style.display = 'block';
                payLevelSelect.required = true;
            } else {
                // Default
                salaryInput.readOnly = false;
                salaryInput.value = '';
                salaryInput.placeholder = '0.00';
                salaryLabel.innerHTML = '<i class="fas fa-dollar-sign"></i> Basic Salary';
                payLevelGroup.style.display = 'none';
                hraGroup.style.display = 'none';
                payLevelSelect.required = false;
            }
        });

        // Update salary hint when Pay Level changes
        payLevelSelect.addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const minBasic = parseFloat(selectedOption.dataset.min);
                const maxBasic = parseFloat(selectedOption.dataset.max);
                const ta = parseFloat(selectedOption.dataset.ta);
                
                salaryInput.min = minBasic;
                salaryInput.max = maxBasic;
                salaryInput.placeholder = `₹${minBasic.toLocaleString()} - ₹${maxBasic.toLocaleString()}`;
                payLevelHint.innerHTML = `<strong>Range:</strong> ₹${minBasic.toLocaleString()} - ₹${maxBasic.toLocaleString()} | <strong>TA:</strong> ₹${ta.toLocaleString()}/month`;
                payLevelHint.style.color = '#10b981';
            } else {
                payLevelHint.innerHTML = 'Transport Allowance will be set based on selected level';
                payLevelHint.style.color = '#64748b';
            }
        });

        // Validate basic salary is within Pay Level range on form submit
        document.querySelector('form').addEventListener('submit', function(e) {
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
                }
            }
        });
    </script>

</body>
</html>
