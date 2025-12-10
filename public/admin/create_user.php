<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create User - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --input-bg: #ffffff;
            --input-border: #e0e0e0;
            --input-focus: #667eea;
        }

        [data-theme="dark"] {
            --bg-primary: #1a1f36;
            --bg-secondary: #232946;
            --bg-tertiary: #2d3250;
            --text-primary: #fffffe;
            --text-secondary: #b8c1ec;
            --text-tertiary: #a0a8d4;
            --border-color: #3d4263;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
            --input-bg: #2d3250;
            --input-border: #3d4263;
            --input-focus: #667eea;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 15px;
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .page-header p {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .alert-warning {
            background: #fff4e5;
            border: 1px solid #ffd8a8;
            color: #b35c00;
        }

        [data-theme="dark"] .alert-warning {
            background: rgba(255, 152, 0, 0.15);
            border-color: rgba(255, 152, 0, 0.3);
            color: #ffb74d;
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

        .form-card {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 35px;
            max-width: 900px;
            border: 1px solid var(--border-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label i {
            color: #667eea;
        }

        .required {
            color: #e74c3c;
            margin-left: 2px;
        }

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--input-border);
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Manrope', sans-serif;
            background: var(--input-bg);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: var(--input-focus);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .form-group input::placeholder {
            color: var(--text-tertiary);
        }

        .form-hint {
            font-size: 13px;
            color: var(--text-tertiary);
            margin-top: 6px;
        }

        .password-strength {
            margin-top: 10px;
            display: none;
        }

        .strength-bar {
            height: 6px;
            border-radius: 3px;
            background: var(--bg-tertiary);
            overflow: hidden;
            margin-bottom: 6px;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s ease;
            border-radius: 3px;
        }

        .strength-text {
            font-size: 13px;
            font-weight: 600;
        }

        .password-match {
            margin-top: 6px;
            font-size: 13px;
            font-weight: 500;
            display: none;
        }

        .password-match.error {
            color: #e74c3c;
        }

        .password-match.success {
            color: #27ae60;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 35px;
            padding-top: 25px;
            border-top: 2px solid var(--border-color);
        }

        .btn {
            padding: 14px 28px;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            font-family: 'Manrope', sans-serif;
            text-decoration: none;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 2px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            transform: translateY(-2px);
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-card {
                padding: 25px;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                justify-content: center;
                width: 100%;
            }
        }
    </style>
</head>
<body>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-user-plus"></i> Create New User</h1>
            <p>Create login credentials for employees, accountants, directors, and administrators</p>
        </div>

        <?php if ($error === 'username_exists'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Username already exists. Please choose a different one.
            </div>
        <?php elseif ($error === 'missing_fields'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Please fill in all required fields.
            </div>
        <?php elseif ($error === 'password_mismatch'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Passwords do not match.
            </div>
        <?php elseif ($error === 'username_too_short'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Username must be at least 3 characters.
            </div>
        <?php elseif ($error === 'password_too_short'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Password must be at least 8 characters.
            </div>
        <?php elseif ($error === 'password_weak'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                Password is too weak. Use uppercase, lowercase, numbers, and special characters.
            </div>
        <?php endif; ?>

        <div class="form-card">
            <form method="POST" action="../index.php?page=create-user" id="userForm">
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label for="username">
                            <i class="fas fa-user"></i> Username 
                            <span class="required">*</span>
                        </label>
                        <input type="text" id="username" name="username" required 
                               placeholder="Enter unique username" minlength="3">
                        <small class="form-hint">Username must be unique and at least 3 characters</small>
                    </div>

                    <div class="form-group">
                        <label for="password">
                            <i class="fas fa-lock"></i> Password 
                            <span class="required">*</span>
                        </label>
                        <input type="password" id="password" name="password" required 
                               placeholder="Enter strong password" minlength="8">
                        <small class="form-hint">Minimum 8 characters with uppercase, number, and special character</small>
                        <div class="password-strength" id="passwordStrength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthFill"></div>
                            </div>
                            <div class="strength-text" id="strengthText">Weak</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">
                            <i class="fas fa-lock"></i> Confirm Password 
                            <span class="required">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required 
                               placeholder="Re-enter password">
                        <div class="password-match" id="passwordMatch"></div>
                    </div>

                    <div class="form-group">
                        <label for="role">
                            <i class="fas fa-user-tag"></i> Role 
                            <span class="required">*</span>
                        </label>
                        <select id="role" name="role" required>
                            <option value="">Select Role</option>
                            <option value="employee">Employee</option>
                            <option value="accountant">Accountant</option>
                            <option value="director">Director</option>
                            <option value="administrator">Administrator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="employee_id">
                            <i class="fas fa-id-badge"></i> Employee ID
                        </label>
                        <input type="number" id="employee_id" name="employee_id" 
                               placeholder="Link to employee record (optional)">
                    </div>

                    <div class="form-group full-width">
                        <label for="email">
                            <i class="fas fa-envelope"></i> Email
                        </label>
                        <input type="email" id="email" name="email" placeholder="user@company.com">
                    </div>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-save"></i> Create User
                    </button>
                    <a href="manage_users.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                </div>
            </form>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('adminTheme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            themeIcon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Form validation
        const form = document.getElementById('userForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const passwordMatch = document.getElementById('passwordMatch');
        const passwordStrength = document.getElementById('passwordStrength');
        const strengthFill = document.getElementById('strengthFill');
        const strengthText = document.getElementById('strengthText');
        const submitBtn = document.getElementById('submitBtn');

        function checkPasswordStrength(pwd) {
            let strength = 0;
            const checks = {
                hasMinLength: pwd.length >= 8,
                hasUppercase: /[A-Z]/.test(pwd),
                hasLowercase: /[a-z]/.test(pwd),
                hasNumber: /\d/.test(pwd),
                hasSpecial: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(pwd)
            };

            Object.values(checks).forEach(check => { if (check) strength += 20; });
            return { strength, checks };
        }

        password.addEventListener('input', function() {
            const { strength } = checkPasswordStrength(this.value);
            
            if (this.value.length > 0) {
                passwordStrength.style.display = 'block';
                strengthFill.style.width = strength + '%';

                if (strength < 40) {
                    strengthFill.style.background = '#e74c3c';
                    strengthText.textContent = 'Weak';
                    strengthText.style.color = '#e74c3c';
                } else if (strength < 60) {
                    strengthFill.style.background = '#f39c12';
                    strengthText.textContent = 'Fair';
                    strengthText.style.color = '#f39c12';
                } else if (strength < 80) {
                    strengthFill.style.background = '#f1c40f';
                    strengthText.textContent = 'Good';
                    strengthText.style.color = '#f1c40f';
                } else {
                    strengthFill.style.background = '#27ae60';
                    strengthText.textContent = 'Strong';
                    strengthText.style.color = '#27ae60';
                }
            } else {
                passwordStrength.style.display = 'none';
            }
            checkPasswordMatch();
        });

        function checkPasswordMatch() {
            if (confirmPassword.value.length > 0) {
                if (password.value !== confirmPassword.value) {
                    passwordMatch.innerHTML = '<i class="fas fa-times"></i> Passwords do not match';
                    passwordMatch.className = 'password-match error';
                    passwordMatch.style.display = 'block';
                    submitBtn.disabled = true;
                } else {
                    passwordMatch.innerHTML = '<i class="fas fa-check"></i> Passwords match';
                    passwordMatch.className = 'password-match success';
                    passwordMatch.style.display = 'block';
                    submitBtn.disabled = false;
                }
            } else {
                passwordMatch.style.display = 'none';
                submitBtn.disabled = false;
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
                alert('Passwords do not match!');
            }
        });
    </script>

</body>
</html>
