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
        'employee' => '/payslip_generator/public/employee/dashboard.php',
        'accountant' => '/payslip_generator/public/accountant/accountant_dashboard.php',
        'director' => '/payslip_generator/public/director/director_dashboard.php',
        'administrator' => '/payslip_generator/public/admin/admin_dashboard.php',
        default => '/payslip_generator/public/auth/login.php'
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
            'employee' => '/payslip_generator/public/employee/dashboard.php',
            'accountant' => '/payslip_generator/public/accountant/accountant_dashboard.php',
            'director' => '/payslip_generator/public/director/director_dashboard.php',
            'administrator' => '/payslip_generator/public/admin/admin_dashboard.php',
            default => '/payslip_generator/public/auth/login.php'
        };
        
        header("Location: $redirect");
        exit;
    }
}

$userName = $_SESSION['username'] ?? 'User';
$allRoles = $_SESSION['all_roles'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Choose Dashboard - e-HRMS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- tsParticles CDN -->
    <script src="https://cdn.jsdelivr.net/npm/tsparticles@2/tsparticles.bundle.min.js"></script>
    
    <style>
        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --accent: #667eea;
            --accent-2: #764ba2;
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
            font-family: 'Roboto', sans-serif;
            background: #f8fafc;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            overflow-x: hidden;
        }

        /* Particle canvas */
        #tsparticles {
            position: fixed;
            inset: 0;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 1000px;
            width: 100%;
        }

        .selector-wrapper {
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 24px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            padding: 40px;
            animation: slideUp 0.6s ease-out;
        }

        .selector-wrapper::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(102, 126, 234, 0.5), rgba(118, 75, 162, 0.5), transparent);
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

        .header {
            text-align: center;
            margin-bottom: 40px;
            padding-bottom: 30px;
            border-bottom: 1px solid var(--border);
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.02));
            border-radius: 16px;
            padding: 30px 20px;
        }

        .header h1 {
            font-family: "Roboto", sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: 0.5px;
        }

        .header p {
            font-size: 15px;
            color: var(--muted);
            margin-bottom: 5px;
            font-weight: 400;
        }

        .header p strong {
            color: var(--text);
            font-weight: 600;
        }

        .user-badge {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            padding: 10px 20px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
            margin-top: 15px;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
            letter-spacing: 0.5px;
        }

        .user-badge i {
            margin-right: 6px;
        }
        .user-badge i {
            margin-right: 6px;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));

        .role-card {
            background: rgba(255, 255, 255, 0.9);
            border: 1.5px solid var(--border);
            border-radius: 16px;
            padding: 32px 24px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            backdrop-filter: blur(10px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }

        .role-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .role-card:hover::before {
            transform: scaleX(1);
        }

        .role-card:hover {
            border-color: var(--accent);
            transform: translateY(-6px);
            box-shadow: 0 12px 24px rgba(102, 126, 234, 0.2);
            background: rgba(255, 255, 255, 0.95);
        }

        .role-card input[type="radio"] {
            display: none;
        }

        .role-card input[type="radio"]:checked + .checkmark {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-color: transparent;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .role-card input[type="radio"]:checked ~ .checkmark::after {
            display: block;
        }

        .checkmark {
            position: absolute;
            top: 16px;
            right: 16px;
            width: 26px;
            height: 26px;
            background: white;
            border: 2px solid var(--border);
            border-radius: 50%;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2;
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
            transform: scale(1.1);
        }

        .role-content {
            position: relative;
            z-index: 1;
        }

        .role-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
            margin-bottom: 18px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.3);
        }

        .role-card:hover .role-icon {
            transform: scale(1.08) rotate(3deg);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .role-title {
            font-family: "Roboto", sans-serif;
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 8px;
            color: var(--text);
            letter-spacing: 0.3px;
        }

        .role-description {
            font-size: 13px;
            color: var(--muted);
            line-height: 1.6;
            margin-bottom: 16px;
        }

        .role-features {
            font-size: 12px;
            color: var(--text);
            padding-left: 0;
        }
        .role-features {
            font-size: 12px;
            color: var(--text);
            list-style: none;
            display: flex;
            align-items: center;
            font-weight: 500;
        }

        .role-features li:before {
            content: '✓';
            color: var(--success);
            font-weight: bold;
            margin-right: 8px;
            font-size: 14px;
        }

        .submit-section {
            text-align: center;
            padding-top: 30px;
            border-top: 1px solid var(--border);
            margin-top: 30px;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .btn-continue {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            padding: 18px 64px;
            border-radius: 16px;
            font-weight: 700;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 14px;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            box-shadow: 0 8px 24px rgba(102, 126, 234, 0.4), 0 4px 12px rgba(118, 75, 162, 0.3);
            position: relative;
            overflow: hidden;
            min-width: 260px;
            font-family: 'Roboto', sans-serif;
            border: 2px solid transparent;
        }

        .btn-continue::before {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(135deg, #764ba2, #667eea);
            opacity: 0;
            transition: opacity 0.4s ease;
            z-index: 0;
        }

        .btn-continue::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
            z-index: 0;
        }

        .btn-continue::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
            z-index: 0;
        }

        .btn-continue:hover::before {
            opacity: 1;
        }

        .btn-continue:hover::after {
            width: 300px;
            height: 300px;
        }

        .btn-continue:hover {
            transform: translateY(-4px) scale(1.03);
            box-shadow: 0 16px 40px rgba(102, 126, 234, 0.5), 0 8px 20px rgba(118, 75, 162, 0.4);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .btn-continue:active {
            transform: translateY(-1px) scale(0.98);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-continue i,
        .btn-continue span {
            position: relative;
            z-index: 1;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }

        .btn-continue i {
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-size: 18px;
        }

        .btn-continue:hover i {
            transform: translateX(6px) scale(1.1);
            animation: arrowBounce 0.8s infinite;
        }

        @keyframes arrowBounce {
            0%, 100% { transform: translateX(6px) scale(1.1); }
            50% { transform: translateX(10px) scale(1.1); }
        }

        .btn-continue:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            background: linear-gradient(135deg, #9ca3af, #6b7280);
            animation: none;
        }

        .btn-continue:disabled:hover {
            transform: none;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.2);
            border-color: transparent;
        }

        .btn-continue:disabled:hover i {
            transform: none;
            animation: none;
        }

        .btn-continue:disabled::before,
        .btn-continue:disabled::after {
            opacity: 0;
        }

        .logout-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--muted);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 10px 24px;
            border-radius: 10px;
            transition: all 0.3s ease;
            background: transparent;
        }

        .logout-link:hover {
            color: var(--accent);
            background: rgba(102, 126, 234, 0.1);
            transform: translateY(-1px);
        }

        .logout-link i {
            transition: transform 0.3s ease;
            font-size: 14px;
        }

        .logout-link:hover i {
            transform: translateX(-2px);
        }

        @media (max-width: 768px) {
            .selector-wrapper {
                padding: 30px 20px;
            }

            .header h1 {
                font-size: 26px;
            }

            .header p {
                font-size: 14px;
            }

            .roles-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }

            .role-card {
                padding: 28px 20px;
            }

            .role-icon {
                width: 56px;
                height: 56px;
                font-size: 24px;
            }

            .role-title {
                font-size: 18px;
            }

            .btn-continue {
                padding: 16px 48px;
                font-size: 14px;
                min-width: 220px;
                letter-spacing: 1px;
            }

            .btn-continue i {
                font-size: 16px;
            }

            .logout-link {
                font-size: 13px;
                padding: 8px 20px;
            }
        }

        @media (max-width: 480px) {
            .selector-wrapper {
                padding: 25px 16px;
                border-radius: 20px;
            }

            .header {
                padding: 20px 16px;
            }

            .header h1 {
                font-size: 22px;
            }

            .user-badge {
                font-size: 12px;
                padding: 8px 16px;
            }

            .btn-continue {
                padding: 14px 40px;
                font-size: 13px;
                min-width: 200px;
                letter-spacing: 0.8px;
            }

            .btn-continue i {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <!-- Particle Background -->
    <div id="tsparticles"></div>

    <div class="container">
        <div class="selector-wrapper">
            <!-- Header -->
            <div class="header">
                <h1><i class="fas fa-user-circle"></i> Choose Your Dashboard</h1>
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
                        <span>Continue</span>
                        <i class="fas fa-arrow-right"></i>
                    </button>
                    <a href="logout.php" class="logout-link">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize tsParticles
        tsParticles.load("tsparticles", {
            background: {
                color: {
                    value: "linear-gradient(135deg, #667eea 0%, #764ba2 100%)",
                },
            },
            fpsLimit: 60,
            particles: {
                color: {
                    value: "#667eea",
                },
                links: {
                    color: "#764ba2",
                    distance: 150,
                    enable: true,
                    opacity: 0.4,
                    width: 1,
                },
                move: {
                    enable: true,
                    speed: 1.5,
                    direction: "none",
                    random: false,
                    straight: false,
                    outModes: {
                        default: "bounce",
                    },
                },
                number: {
                    value: 60,
                    density: {
                        enable: true,
                        area: 800,
                    },
                },
                opacity: {
                    value: { min: 0.3, max: 0.7 },
                },
                shape: {
                    type: "circle",
                },
                size: {
                    value: { min: 1, max: 3 },
                },
            },
            interactivity: {
                events: {
                    onHover: {
                        enable: true,
                        mode: "grab",
                    },
                    onClick: {
                        enable: true,
                        mode: "push",
                    },
                },
                modes: {
                    grab: {
                        distance: 140,
                        links: {
                            opacity: 0.8,
                        },
                    },
                    push: {
                        quantity: 4,
                    },
                },
            },
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
