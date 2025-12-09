<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PaySlip Generator</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b1221;
            --card: #0f172a;
            --accent: #0ea5e9;
            --accent-2: #22d3ee;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --border: #1f2937;
            --success: #34d399;
        }

        body.light-theme {
            --bg: #f8fafc;
            --card: #ffffff;
            --accent: #0284c7;
            --accent-2: #0ea5e9;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.08), transparent 25%),
                        radial-gradient(circle at 80% 0%, rgba(14,165,233,0.06), transparent 30%),
                        var(--bg);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            overflow: hidden;
            transition: background 0.4s ease;
        }

        body.light-theme {
            background: radial-gradient(circle at 20% 20%, rgba(2,132,199,0.05), transparent 25%),
                        radial-gradient(circle at 80% 0%, rgba(2,132,199,0.04), transparent 30%),
                        var(--bg);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            width: 50px;
            height: 50px;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
            z-index: 100;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }

        .theme-toggle:hover {
            background: var(--accent);
            color: var(--bg);
            transform: scale(1.1);
        }

        .theme-toggle i {
            color: var(--accent);
            transition: color 0.3s ease;
        }

        .theme-toggle:hover i {
            color: var(--bg);
        }

        .login-wrapper {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 20px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5), 0 0 100px rgba(14,165,233,0.1);
            overflow: hidden;
            max-width: 480px;
            width: 100%;
            animation: slideUp 0.6s ease-out;
            position: relative;
            transition: box-shadow 0.3s ease;
        }

        body.light-theme .login-wrapper {
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
        }

        .login-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(34,211,238,0.5), transparent);
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(40px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .login-header {
            background: linear-gradient(135deg, rgba(14,165,233,0.1), rgba(34,211,238,0.05));
            padding: 50px 30px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            position: relative;
        }

        .header-top {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 30px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }

        .nielit-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e40af, #0284c7);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            letter-spacing: 1px;
        }

        .nielit-logo-img {
            width: 80px;
            height: 80px;
            object-fit: contain;
            filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.15));
        }

        .nielit-logo-fallback {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #1e3a8a, #0369a1);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 11px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.5px;
            text-align: center;
            padding: 4px;
            flex-direction: column;
            gap: 2px;
        }

        .nielit-logo-fallback-top {
            font-size: 10px;
            opacity: 0.9;
        }

        .nielit-logo-fallback-bottom {
            font-size: 9px;
            opacity: 0.85;
        }

        .meity-logo-fallback {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #7c2d12, #ea580c);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 9px;
            font-weight: 700;
            color: white;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            letter-spacing: 0.3px;
            text-align: center;
            padding: 4px;
            flex-direction: column;
            gap: 2px;
        }

        .meity-logo-fallback-top {
            font-size: 8px;
            opacity: 0.9;
        }

        .meity-logo-fallback-bottom {
            font-size: 7px;
            opacity: 0.85;
        }

        .company-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            animation: float 3s ease-in-out infinite;
        }

        .login-header::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(14,165,233,0.3), transparent);
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .logo-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }

        .login-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            font-size: 13px;
            color: var(--muted);
            letter-spacing: 0.1px;
        }

        .login-container {
            padding: 40px 30px;
        }

        .form-group {
            margin-bottom: 22px;
            position: relative;
        }

        .form-group label {
            display: block;
            margin-bottom: 10px;
            color: var(--text);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper i:first-child {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--accent);
            font-size: 16px;
            pointer-events: none;
            z-index: 2;
            opacity: 0.7;
        }

        .form-group input {
            width: 100%;
            padding: 14px 50px 14px 48px;
            border: 1px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: rgba(255, 255, 255, 0.02);
            color: var(--text);
            position: relative;
            z-index: 1;
            font-family: 'Manrope', sans-serif;
        }

        .form-group input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(14,165,233,0.05);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1), inset 0 0 0 1px rgba(34,211,238,0.2);
        }

        .form-group input::placeholder {
            color: var(--muted);
            opacity: 0.6;
        }

        .show-password {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: var(--muted);
            font-size: 16px;
            transition: all 0.3s ease;
            pointer-events: auto;
            z-index: 3;
        }

        .show-password:hover {
            color: var(--accent);
        }

        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: var(--bg);
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            margin-top: 8px;
            letter-spacing: 0.3px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.2);
            transition: left 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(14,165,233,0.3);
        }

        .btn-login:hover::before {
            left: 100%;
        }

        .btn-login:active {
            transform: translateY(-1px);
        }

        .forgot-password-link {
            text-align: right;
            margin-top: 12px;
        }

        .forgot-password-link a {
            color: var(--accent);
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .forgot-password-link a:hover {
            color: var(--accent-2);
            text-decoration: underline;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 40px 30px;
            max-width: 420px;
            width: 90%;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease-out;
            position: relative;
        }

        .modal-close {
            position: absolute;
            top: 15px;
            right: 15px;
            background: none;
            border: none;
            color: var(--muted);
            font-size: 24px;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .modal-close:hover {
            color: var(--accent);
        }

        .modal-header {
            margin-bottom: 24px;
        }

        .modal-header h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            margin-bottom: 8px;
            color: var(--text);
        }

        .modal-header p {
            color: var(--muted);
            font-size: 13px;
        }

        .form-group-modal {
            margin-bottom: 16px;
        }

        .form-group-modal label {
            display: block;
            margin-bottom: 8px;
            color: var(--text);
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group-modal input {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: rgba(255, 255, 255, 0.02);
            color: var(--text);
            font-size: 13px;
            font-family: 'Manrope', sans-serif;
            transition: all 0.3s ease;
        }

        .form-group-modal input:focus {
            outline: none;
            border-color: var(--accent);
            background: rgba(14,165,233,0.05);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.1);
        }

        .btn-reset {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: var(--bg);
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            font-size: 14px;
        }

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(14,165,233,0.3);
        }

        .btn-cancel {
            width: 100%;
            padding: 12px;
            background: transparent;
            color: var(--muted);
            border: 1px solid var(--border);
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 8px;
            font-size: 14px;
        }

        .btn-cancel:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        .success-message {
            display: none;
            padding: 16px;
            background: rgba(52, 211, 153, 0.1);
            border: 1px solid rgba(52, 211, 153, 0.3);
            border-radius: 10px;
            color: var(--success);
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
        }

        .error-message {
            display: none;
            padding: 12px;
            background: rgba(248, 113, 113, 0.1);
            border: 1px solid rgba(248, 113, 113, 0.3);
            border-radius: 10px;
            color: #f87171;
            font-size: 13px;
            margin-bottom: 16px;
            text-align: center;
        }

        .credentials-info {
            margin-top: 28px;
            padding: 16px;
            background: rgba(34,211,238,0.05);
            border: 1px solid rgba(34,211,238,0.15);
            border-radius: 12px;
            font-size: 12px;
            color: var(--muted);
            line-height: 1.6;
        }

        .credentials-info i {
            color: var(--accent-2);
            margin-right: 8px;
        }

        .credentials-info strong {
            color: var(--accent);
            font-weight: 700;
        }

        .role-info {
            margin-top: 12px;
            font-size: 11px;
            opacity: 0.8;
        }

        .role-badge {
            display: inline-block;
            padding: 2px 8px;
            background: rgba(14,165,233,0.15);
            color: var(--accent);
            border-radius: 4px;
            margin: 0 2px;
            font-weight: 600;
        }

        @media (max-width: 480px) {
            .login-wrapper {
                border-radius: 16px;
            }
            
            .login-header {
                padding: 35px 24px;
            }

            .login-container {
                padding: 30px 24px;
            }

            .login-header h2 {
                font-size: 22px;
            }

            .login-header p {
                font-size: 12px;
            }
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle" title="Toggle theme">
        <i class="fas fa-moon"></i>
    </button>

    <div class="login-wrapper">
        <div class="login-header">
            <h2>PaySlip Generator</h2>
            <p>Enterprise Payroll Management System</p>
        </div>
        
        <div class="login-container">
            <form id="loginForm">
                <div id="loginAlert" style="display: none; padding: 12px; background: rgba(248, 113, 113, 0.1); border: 1px solid rgba(248, 113, 113, 0.3); border-radius: 10px; color: #f87171; font-size: 13px; margin-bottom: 16px; text-align: center;"></div>
                
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <i class="fas fa-user"></i>
                        <input type="text" name="username" id="username" required placeholder="Enter your username" autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <i class="fas fa-lock"></i>
                        <input type="password" name="password" id="password" required placeholder="Enter your password" autocomplete="current-password">
                        <i class="fas fa-eye show-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>
                <div class="forgot-password-link">
                    <a href="#" id="forgotPasswordBtn"><i class="fas fa-key"></i> Forgot Password?</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal">
        <div class="modal-content">
            <button class="modal-close" id="modalCloseBtn">&times;</button>
            <div class="modal-header">
                <h3><i class="fas fa-lock-open"></i> Reset Password</h3>
                <p>Enter your username to receive password reset instructions</p>
            </div>
            <form id="forgotPasswordForm">
                <div id="successMessage" class="success-message">
                    <i class="fas fa-check-circle"></i> Password reset instructions have been sent to your email!
                </div>
                <div id="errorMessage" class="error-message">
                    <i class="fas fa-exclamation-circle"></i> <span id="errorText"></span>
                </div>
                <div class="form-group-modal">
                    <label for="resetUsername">Username</label>
                    <input type="text" id="resetUsername" name="username" required placeholder="Enter your username" autocomplete="username">
                </div>
                <button type="submit" class="btn-reset">
                    <i class="fas fa-envelope"></i> Send Reset Link
                </button>
                <button type="button" class="btn-cancel" id="modalCancelBtn">
                    <i class="fas fa-arrow-left"></i> Back to Login
                </button>
            </form>
        </div>
    </div>

    <script>
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        const body = document.body;

        // Load saved theme preference
        const savedTheme = localStorage.getItem('loginTheme') || 'dark';
        if (savedTheme === 'light') {
            body.classList.add('light-theme');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i>';
        }

        // Theme toggle handler
        themeToggle.addEventListener('click', function() {
            body.classList.toggle('light-theme');
            const isLight = body.classList.contains('light-theme');
            
            // Save preference
            localStorage.setItem('loginTheme', isLight ? 'light' : 'dark');
            
            // Update icon
            themeToggle.innerHTML = isLight ? 
                '<i class="fas fa-sun"></i>' : 
                '<i class="fas fa-moon"></i>';
        });

        // Forgot Password Modal
        const forgotPasswordBtn = document.getElementById('forgotPasswordBtn');
        const forgotPasswordModal = document.getElementById('forgotPasswordModal');
        const modalCloseBtn = document.getElementById('modalCloseBtn');
        const modalCancelBtn = document.getElementById('modalCancelBtn');
        const forgotPasswordForm = document.getElementById('forgotPasswordForm');
        const successMessage = document.getElementById('successMessage');
        const errorMessage = document.getElementById('errorMessage');
        const errorText = document.getElementById('errorText');

        // Auto-open modal if ?forgot=1 is present
        const params = new URLSearchParams(window.location.search);
        if (params.get('forgot') === '1') {
            forgotPasswordModal.classList.add('show');
            document.getElementById('resetUsername').focus();
            window.history.replaceState({}, document.title, window.location.pathname);
        }

        // Open modal
        forgotPasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            forgotPasswordModal.classList.add('show');
            document.getElementById('resetUsername').focus();
        });

        // Close modal
        function closeModal() {
            forgotPasswordModal.classList.remove('show');
            successMessage.style.display = 'none';
            errorMessage.style.display = 'none';
            forgotPasswordForm.reset();
        }

        modalCloseBtn.addEventListener('click', closeModal);
        modalCancelBtn.addEventListener('click', closeModal);

        // Close modal when clicking outside
        forgotPasswordModal.addEventListener('click', function(e) {
            if (e.target === forgotPasswordModal) {
                closeModal();
            }
        });

        // Handle forgot password form submission (calls backend endpoint)
        forgotPasswordForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const username = document.getElementById('resetUsername').value.trim();

            if (!username) {
                errorMessage.style.display = 'block';
                errorText.textContent = 'Please enter your username';
                successMessage.style.display = 'none';
                return;
            }

            // Disable while processing
            const resetBtn = forgotPasswordForm.querySelector('.btn-reset');
            resetBtn.disabled = true;
            resetBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

            try {
                const response = await fetch('forgot_password.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ username })
                });

                const result = await response.json();

                if (result.success) {
                    successMessage.style.display = 'block';
                    errorMessage.style.display = 'none';
                    successMessage.textContent = result.message || 'Password reset instructions have been sent.';

                    setTimeout(() => {
                        closeModal();
                    }, 1800);
                } else {
                    errorMessage.style.display = 'block';
                    errorText.textContent = result.message || 'Unable to process request';
                    successMessage.style.display = 'none';
                }
            } catch (err) {
                errorMessage.style.display = 'block';
                errorText.textContent = 'Network error. Please try again.';
                successMessage.style.display = 'none';
            } finally {
                resetBtn.disabled = false;
                resetBtn.innerHTML = '<i class="fas fa-envelope"></i> Send Reset Link';
            }
        });

        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        const submitBtn = document.getElementById('submitBtn');
        const loginForm = document.getElementById('loginForm');

        // Toggle password visibility
        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submission with AJAX
        loginForm.addEventListener('submit', function(e) {
            e.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Signing in...';
            const loginAlert = document.getElementById('loginAlert');
            loginAlert.style.display = 'none';

            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;

            if (!username || !password) {
                loginAlert.textContent = 'Please enter both username and password';
                loginAlert.style.display = 'block';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
                return;
            }

            fetch('login_api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ username, password })
            })
            .then(res => res.json())
            .then(result => {
                if (result.success) {
                    window.location.href = result.redirect;
                } else if (result.locked) {
                    loginAlert.textContent = result.message + ' Click "Forgot Password?" to reset.';
                    loginAlert.style.display = 'block';
                } else {
                    loginAlert.textContent = result.message;
                    loginAlert.style.display = 'block';
                }
            })
            .catch(err => {
                loginAlert.textContent = 'Network error. Please try again.';
                loginAlert.style.display = 'block';
                console.error(err);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sign-in-alt"></i> Sign In';
            });
        });

        // Input focus effects
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('focus', function() {
                this.style.transition = 'all 0.3s ease';
            });
        });
    </script>
</body>
</html>
