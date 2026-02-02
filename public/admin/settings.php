<?php
/**
 * Admin Settings Redirect
 * 
 * System Settings have been moved to the Super Admin Portal 
 * for proper separation of duties and security.
 * 
 * Only Super Admins can modify system-wide settings including:
 * - Company branding (logo, name)
 * - Email configuration
 * - Security policies
 * - Date/time formats
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// If user is super_admin, redirect to super admin settings
if ($_SESSION['role'] === 'super_admin') {
    header("Location: ../super_admin/settings.php");
    exit;
}

// For admin and other roles, show access denied message
$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Restricted - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .access-denied-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 70vh;
            text-align: center;
            padding: 40px;
        }

        .access-icon {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 30px;
        }

        .access-icon i {
            font-size: 50px;
            color: #ef4444;
        }

        .access-denied-container h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 15px 0;
        }

        .access-denied-container p {
            font-size: 16px;
            color: var(--muted);
            margin: 0 0 30px 0;
            max-width: 500px;
            line-height: 1.6;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            max-width: 500px;
            text-align: left;
        }

        .info-card h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .info-card h3 i {
            color: #667eea;
        }

        .info-card ul {
            margin: 0;
            padding-left: 20px;
            color: var(--muted);
        }

        .info-card li {
            margin-bottom: 10px;
            font-size: 14px;
        }

        .btn-back {
            margin-top: 30px;
            padding: 14px 28px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
        }

        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="access-denied-container">
            <div class="access-icon">
                <i class="fas fa-lock"></i>
            </div>
            
            <h1>System Settings Moved</h1>
            <p>System Settings have been relocated to the <strong>Super Admin Portal</strong> for enhanced security and proper separation of duties.</p>
            
            <div class="info-card">
                <h3><i class="fas fa-shield-alt"></i> Super Admin Exclusive Features</h3>
                <ul>
                    <li>Company branding (logo, name)</li>
                    <li>Email notification configuration</li>
                    <li>Security policies (2FA, session timeout)</li>
                    <li>System-wide preferences (timezone, date format)</li>
                </ul>
            </div>
            
            <a href="admin_dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
