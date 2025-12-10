<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 30px;
            max-width: 800px;
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
            <h1><i class="fas fa-user-plus"></i> Create New User</h1>
            <p>Create login credentials for employees, accountants, directors, and administrators</p>
        </div>

        <?php if ($error === 'username_exists'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Username already exists. Please choose a different one.
            </div>
        <?php elseif ($error === 'missing_fields'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Please fill in all required fields.
            </div>
        <?php elseif ($error === 'password_mismatch'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Passwords do not match.
            </div>
        <?php elseif ($error === 'username_too_short'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Username must be at least 3 characters.
            </div>
        <?php elseif ($error === 'password_too_short'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Password must be at least 8 characters.
            </div>
        <?php elseif ($error === 'password_weak'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Password is too weak. Use uppercase, lowercase, numbers, and special characters.
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="../index.php?page=create-user" id="userForm">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="username"><i class="fas fa-user"></i> Username <span style="color:red;">*</span></label>
                        <input type="text" id="username" name="username" required placeholder="Enter username (min 3 characters)" minlength="3">
                        <small style="color:#7f8c8d;">Username must be unique and at least 3 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="password"><i class="fas fa-lock"></i> Password <span style="color:red;">*</span></label>
                        <input type="password" id="password" name="password" required placeholder="Enter password (min 8 characters)" minlength="8">
                        <small style="color:#7f8c8d;">Minimum 8 characters, include uppercase, number, and special character</small>
                        <div id="passwordStrength" style="margin-top:8px; display:none;">
                            <div style="height:4px;border-radius:2px;background:#e0e0e0;overflow:hidden;">
                                <div id="strengthBar" style="height:100%;width:0%;transition:all 0.3s ease;background:#e74c3c;"></div>
                            </div>
                            <small id="strengthText" style="color:#e74c3c;display:block;margin-top:4px;">Weak</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password"><i class="fas fa-lock"></i> Confirm Password <span style="color:red;">*</span></label>
                        <input type="password" id="confirm_password" name="confirm_password" required placeholder="Re-enter password">
                        <small id="passwordError" style="color:red;display:none;">Passwords do not match</small>
                        <small id="passwordMatch" style="color:green;display:none;"><i class="fas fa-check"></i> Passwords match</small>
                    </div>

                    <div class="form-group">
                        <label for="role"><i class="fas fa-user-tag"></i> Role <span style="color:red;">*</span></label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="employee">Employee</option>
                            <option value="accountant">Accountant</option>
                            <option value="director">Director</option>
                            <option value="administrator">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="employee_id"><i class="fas fa-id-badge"></i> Employee ID (Optional)</label>
                        <input type="number" id="employee_id" name="employee_id" placeholder="Link to employee record">
                    </div>

                    <div class="form-group full-width">
                        <label for="email"><i class="fas fa-envelope"></i> Email (Optional)</label>
                        <input type="email" id="email" name="email" placeholder="user@company.com">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <a href="manage_users.php" class="btn btn-cancel">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        const form = document.getElementById('userForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordError = document.getElementById('passwordError');
        const passwordMatch = document.getElementById('passwordMatch');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        // Password strength checker
        function checkPasswordStrength(pwd) {
            let strength = 0;
            const checks = {
                hasMinLength: pwd.length >= 8,
                hasUppercase: /[A-Z]/.test(pwd),
                hasLowercase: /[a-z]/.test(pwd),
                hasNumber: /\d/.test(pwd),
                hasSpecial: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pwd)
            };

            Object.values(checks).forEach(check => {
                if (check) strength += 20;
            });

            return { strength, checks };
        }

        // Update password strength indicator
        password.addEventListener('input', function() {
            const { strength } = checkPasswordStrength(this.value);
            
            if (this.value.length > 0) {
                passwordStrength.style.display = 'block';
                strengthBar.style.width = strength + '%';

                if (strength < 40) {
                    strengthBar.style.background = '#e74c3c';
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = '#e74c3c';
                } else if (strength < 60) {
                    strengthBar.style.background = '#f39c12';
                    strengthText.textContent = 'Fair';
                    strengthText.style.color = '#f39c12';
                } else if (strength < 80) {
                    strengthBar.style.background = '#f1c40f';
                    strengthText.textContent = 'Good';
                    strengthText.style.color = '#f1c40f';
                } else {
                    strengthBar.style.background = '#27ae60';
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = '#27ae60';
                }
            } else {
                passwordStrength.style.display = 'none';
            }

            // Check if passwords match
            checkPasswordMatch();
        });

        // Check password match
        function checkPasswordMatch() {
            if (confirmPassword.value.length > 0) {
                if (password.value !== confirmPassword.value) {
                    passwordError.style.display = 'block';
                    passwordMatch.style.display = 'none';
                    form.querySelector('button[type="submit"]').disabled = true;
                } else {
                    passwordError.style.display = 'none';
                    passwordMatch.style.display = 'block';
                    form.querySelector('button[type="submit"]').disabled = false;
                }
            } else {
                passwordError.style.display = 'none';
                passwordMatch.style.display = 'none';
            }
        }

        confirmPassword.addEventListener('input', checkPasswordMatch);

        form.addEventListener('submit', function(e) {
            const { strength } = checkPasswordStrength(password.value);
            
            if (strength < 40) {
                e.preventDefault();
                alert('Password is too weak. Please use a stronger password with uppercase, numbers, and special characters.');
                return;
            }

            if (password.value !== confirmPassword.value) {
                e.preventDefault();
                passwordError.style.display = 'block';
            }
        });
    </script>

</body>
</html>
