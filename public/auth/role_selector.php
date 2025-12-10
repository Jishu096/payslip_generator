<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['all_roles'])) {
    header("Location: login.php");
    exit;
}

// If only one role, redirect directly to that dashboard
if (count($_SESSION['all_roles']) === 1) {
    $role = $_SESSION['all_roles'][0];
    $redirect = match($role) {
        'employee' => 'employee/dashboard.php',
        'accountant' => 'accountant/accountant_dashboard.php',
        'director' => 'director/director_dashboard.php',
        'administrator' => 'admin/admin_dashboard.php',
        default => 'login.php'
    };
    header("Location: $redirect");
    exit;
}

// Handle role selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_role'])) {
    $selectedRole = $_POST['selected_role'];
    
    // Verify user has this role
    if (in_array($selectedRole, $_SESSION['all_roles'])) {
        $_SESSION['current_role'] = $selectedRole;
        
        $redirect = match($selectedRole) {
            'employee' => 'employee/dashboard.php',
            'accountant' => 'accountant/accountant_dashboard.php',
            'director' => 'director/director_dashboard.php',
            'administrator' => 'admin/admin_dashboard.php',
            default => 'login.php'
        };
        
        header("Location: $redirect");
        exit;
    }
}

$userName = $_SESSION['username'] ?? 'User';
$allRoles = $_SESSION['all_roles'] ?? [];
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Dashboard - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --accent: #667eea;
            --accent-2: #764ba2;
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
            --accent: #667eea;
            --accent-2: #764ba2;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            transition: background 0.3s ease, color 0.3s ease;
        }

        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 15px;
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            font-size: 20px;
            z-index: 100;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
        }

        .container {
            max-width: 900px;
            width: 100%;
        }

        .header {
            text-align: center;
            margin-bottom: 50px;
        }

        .header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 36px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .header p {
            font-size: 16px;
            color: var(--text-tertiary);
            margin-bottom: 5px;
        }

        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 600;
            margin-top: 10px;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
            margin-bottom: 40px;
        }

        .role-card {
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 16px;
            padding: 40px 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: all 0.3s ease;
        }

        .role-card:hover {
            border-color: var(--accent);
            transform: translateY(-8px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.15);
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card input[type="radio"]:checked + .role-content {
            color: white;
        }

        .role-card input[type="radio"]:checked ~ .checkmark {
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-color: linear-gradient(135deg, #667eea, #764ba2);
        }

        .role-card input[type="radio"]:checked ~ .checkmark::after {
            display: block;
        }

        .role-card input[type="radio"]:checked {
            display: block;
        }

        .checkmark {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 24px;
            height: 24px;
            background: var(--bg-secondary);
            border: 2px solid var(--border-color);
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .checkmark::after {
            content: '✓';
            color: white;
            font-weight: bold;
            font-size: 14px;
            display: none;
        }

        .role-card:hover .checkmark {
            border-color: var(--accent);
        }

        .role-content {
            position: relative;
            z-index: 2;
        }

        .role-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            color: white;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .role-card:hover .role-icon {
            transform: scale(1.1);
        }

        .role-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 10px;
            color: var(--text-primary);
        }

        .role-description {
            font-size: 14px;
            color: var(--text-tertiary);
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .role-features {
            font-size: 13px;
            color: var(--text-secondary);
        }

        .role-features li {
            margin-bottom: 6px;
            list-style: none;
            display: flex;
            align-items: center;
        }

        .role-features li:before {
            content: '✓';
            color: #10b981;
            font-weight: bold;
            margin-right: 8px;
        }

        .submit-section {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
        }

        .btn-continue {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 14px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-block;
            margin-top: 20px;
        }

        .btn-continue:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-continue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }

        .logout-link {
            display: block;
            margin-top: 20px;
            color: var(--text-tertiary);
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .logout-link:hover {
            color: var(--accent);
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 28px;
            }

            .roles-grid {
                grid-template-columns: 1fr;
            }

            .role-card {
                padding: 30px 20px;
            }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>Choose Your Dashboard</h1>
            <p>Welcome back, <strong><?= htmlspecialchars($userName) ?></strong>!</p>
            <p>You have access to multiple dashboards. Select one to continue.</p>
            <div class="user-badge">
                <i class="fas fa-user-check"></i> Multi-Role User
            </div>
        </div>

        <!-- Role Selection Form -->
        <form method="POST">
            <div class="roles-grid">
                <?php
                $roleIcons = [
                    'employee' => ['icon' => 'fas fa-user', 'title' => 'Employee', 'color' => '#667eea', 'description' => 'View your payslips, attendance, and personal information', 'features' => ['View payslips', 'Check attendance', 'Edit profile']],
                    'accountant' => ['icon' => 'fas fa-calculator', 'title' => 'Accountant', 'color' => '#667eea', 'description' => 'Manage payroll, generate payslips, and view financial reports', 'features' => ['Manage payroll', 'Generate payslips', 'View reports']],
                    'director' => ['icon' => 'fas fa-chart-line', 'title' => 'Director', 'color' => '#667eea', 'description' => 'Approve salary changes and review role requests', 'features' => ['Approve salaries', 'Review requests', 'View reports']],
                    'administrator' => ['icon' => 'fas fa-cogs', 'title' => 'Administrator', 'color' => '#667eea', 'description' => 'Full system access and administration capabilities', 'features' => ['Manage users', 'Manage employees', 'System settings']],
                ];

                foreach ($allRoles as $role) {
                    $roleInfo = $roleIcons[$role] ?? [];
                    if (empty($roleInfo)) continue;
                    ?>
                    <label class="role-card">
                        <input type="radio" name="selected_role" value="<?= $role ?>" required>
                        <div class="checkmark"></div>
                        <div class="role-content">
                            <div class="role-icon">
                                <i class="<?= $roleInfo['icon'] ?>"></i>
                            </div>
                            <div class="role-title"><?= $roleInfo['title'] ?></div>
                            <div class="role-description"><?= $roleInfo['description'] ?></div>
                            <ul class="role-features">
                                <?php foreach ($roleInfo['features'] as $feature): ?>
                                    <li><?= $feature ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </label>
                    <?php
                }
                ?>
            </div>

            <div class="submit-section">
                <button type="submit" class="btn-continue" id="continueBtn" disabled>
                    <i class="fas fa-arrow-right"></i> Continue
                </button>
                <a href="logout.php" class="logout-link">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </form>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });

        // Enable Continue button when role is selected
        const radioButtons = document.querySelectorAll('input[name="selected_role"]');
        const continueBtn = document.getElementById('continueBtn');

        radioButtons.forEach(radio => {
            radio.addEventListener('change', () => {
                continueBtn.disabled = false;
            });
        });

        // Allow Enter key to submit
        radioButtons.forEach(radio => {
            radio.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    continueBtn.click();
                }
            });
        });
    </script>

</body>
</html>
