<?php
session_start();

// Super Admin role check only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'super_admin') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Super Admin';

require_once __DIR__ . '/../../app/Config/database.php';

$db = new Database();
$conn = $db->connect();

// Test email sending
if (isset($_GET['test_email'])) {
    require_once __DIR__ . '/../../app/Helpers/EmailHelper.php';
    $emailHelper = new EmailHelper($conn);
    
    $testEmail = $_GET['test_email'];
    $companyName = $settings['company_name'] ?? 'NIELIT e-HRMS';
    
    $result = $emailHelper->sendEmail(
        $testEmail,
        'Test Email from ' . $companyName,
        '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
            <div style="background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="color: white; margin: 0;">✅ Test Email Successful!</h1>
            </div>
            <div style="background: #f8fafc; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="color: #1e293b; font-size: 16px;">This is a test notification from your <strong>' . htmlspecialchars($companyName) . '</strong> system.</p>
                <p style="color: #64748b;">If you received this email, your SMTP settings are configured correctly!</p>
                <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 20px 0;">
                <p style="color: #94a3b8; font-size: 12px;">Sent on ' . date('d M Y, h:i A') . '</p>
            </div>
        </div>'
    );
    
    $testResult = $result ? 'success' : 'failed';
}

// Ensure settings table exists
$conn->exec("CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT
)");

// Default settings
$defaults = [
    'company_name' => 'NIELIT e-HRMS',
    'company_logo' => 'e-HRMS logo.png',
    'company_email' => 'info@nielit.gov.in',
    'company_phone' => '+91 1234567890',
    'company_address' => '',
    'time_zone' => 'Asia/Kolkata',
    'date_format' => 'd/m/Y',
    'currency' => 'INR',
    'email_notifications' => '1',
    'payslip_alerts' => '1',
    'employee_updates' => '0',
    'leave_notifications' => '1',
    'two_factor' => '0',
    'session_timeout' => '1',
    'password_expiry' => '0',
    'login_attempts' => '5',
    // SMTP Settings
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'smtp_from_email' => '',
    // Session Settings
    'session_timeout_minutes' => '30'
];

// Save settings
$saved = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $keys = array_keys($defaults);
    $stmt = $conn->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (:k, :v)");
    foreach ($keys as $key) {
        $value = $_POST[$key] ?? $defaults[$key];
        $stmt->execute([':k' => $key, ':v' => $value]);
    }
    $saved = true;
}

// Load settings
$settings = $defaults;
$res = $conn->query("SELECT setting_key, setting_value FROM settings");
while ($row = $res->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Apply timezone setting
date_default_timezone_set($settings['time_zone']);

// Count enabled notifications
$notifCount = 0;
if ($settings['email_notifications'] === '1') $notifCount++;
if ($settings['payslip_alerts'] === '1') $notifCount++;
if ($settings['employee_updates'] === '1') $notifCount++;
if ($settings['leave_notifications'] === '1') $notifCount++;

// Count enabled security features (excluding Coming Soon features)
$securityCount = 0;
if ($settings['two_factor'] === '1') $securityCount++; // Coming Soon - won't count
if ($settings['session_timeout'] === '1') $securityCount++;
// password_expiry is Coming Soon - not counted
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Super Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/superadmin_styles.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header h1 i {
            margin-right: 12px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-stats {
            display: flex;
            gap: 20px;
        }

        .header-stat {
            background: rgba(255,255,255,0.15);
            padding: 15px 25px;
            border-radius: 12px;
            text-align: center;
        }

        .header-stat-value {
            font-size: 28px;
            font-weight: 700;
            display: block;
        }

        .header-stat-label {
            font-size: 12px;
            opacity: 0.9;
        }

        /* Alert Messages */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border: 1px solid #10b981;
            color: #059669;
        }

        .alert-error {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border: 1px solid #ef4444;
            color: #dc2626;
        }

        /* Settings Layout */
        .settings-layout {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
        }

        /* Settings Navigation */
        .settings-nav {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 20px;
            position: sticky;
            top: 20px;
            height: fit-content;
        }

        .settings-nav h3 {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 15px;
            margin-bottom: 15px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 15px;
            border-radius: 10px;
            color: var(--text);
            text-decoration: none;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 5px;
        }

        .nav-item:hover {
            background: #f8fafc;
        }

        .nav-item.active {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            color: #667eea;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        .nav-item .badge {
            margin-left: auto;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 3px 8px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
        }

        /* Settings Content */
        .settings-content {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .settings-card-header {
            padding: 25px 30px;
            border-bottom: 2px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .settings-card-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .settings-card-icon.purple { background: linear-gradient(135deg, #667eea, #764ba2); }
        .settings-card-icon.green { background: linear-gradient(135deg, #10b981, #059669); }
        .settings-card-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .settings-card-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .settings-card-icon.red { background: linear-gradient(135deg, #ef4444, #dc2626); }

        .settings-card-title h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 5px 0;
        }

        .settings-card-title p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }

        .settings-card-body {
            padding: 25px 30px;
        }

        /* Logo Selector */
        .logo-selector {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .logo-preview {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            background: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e2e8f0;
            overflow: hidden;
        }

        .logo-preview img {
            max-width: 90px;
            max-height: 90px;
            object-fit: contain;
        }

        .logo-preview i {
            font-size: 40px;
            color: #94a3b8;
        }

        .logo-selector select {
            width: 100%;
            padding: 12px 14px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: #f8fafc;
            color: var(--text);
            cursor: pointer;
        }

        .logo-selector select:focus {
            outline: none;
            border-color: #667eea;
            background: white;
        }

        .logo-selector code {
            background: #f1f5f9;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 11px;
            color: #667eea;
        }

        /* Form Styles */
        .form-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-row.single {
            grid-template-columns: 1fr;
        }

        .form-group {
            margin-bottom: 0;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 10px;
            color: var(--text);
            font-weight: 600;
            font-size: 14px;
        }

        .form-group label i {
            color: #667eea;
            font-size: 14px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
            background: #f8fafc;
            color: var(--text);
            transition: all 0.3s ease;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-hint {
            font-size: 12px;
            color: var(--muted);
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Toggle Setting Item */
        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .setting-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .setting-item:first-child {
            padding-top: 0;
        }

        .setting-info {
            flex: 1;
        }

        .setting-info h4 {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 5px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .setting-info h4 .status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }

        .setting-info h4 .status-dot.active { background: #10b981; }
        .setting-info h4 .status-dot.inactive { background: #94a3b8; }

        .setting-info p {
            font-size: 13px;
            color: var(--muted);
            margin: 0;
        }

        /* Toggle Switch */
        .toggle-switch {
            position: relative;
            width: 56px;
            height: 28px;
            flex-shrink: 0;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #e2e8f0;
            transition: .3s;
            border-radius: 28px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #667eea, #764ba2);
        }

        input:checked + .slider:before {
            transform: translateX(28px);
        }

        /* Buttons */
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: var(--text);
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 25px;
            border-top: 2px solid #f1f5f9;
        }

        /* Status Overview */
        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }

        .status-item {
            background: #f8fafc;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .status-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .status-icon.enabled {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #10b981;
        }

        .status-icon.disabled {
            background: linear-gradient(135deg, rgba(148, 163, 184, 0.15), rgba(100, 116, 139, 0.15));
            color: #64748b;
        }

        .status-text {
            flex: 1;
        }

        .status-text h5 {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
            margin: 0 0 2px 0;
        }

        .status-text span {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-text span.enabled { color: #10b981; }
        .status-text span.disabled { color: #64748b; }

        /* Test Email Section */
        .test-email-section {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
            padding: 20px;
            border-radius: 12px;
            border: 1px dashed #667eea;
            margin-top: 20px;
        }

        .test-email-section h4 {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .test-email-section h4 i {
            color: #667eea;
        }

        .test-email-row {
            display: flex;
            gap: 12px;
        }

        .test-email-row input {
            flex: 1;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            background: white;
        }

        .test-email-row input:focus {
            outline: none;
            border-color: #667eea;
        }

        .test-email-row button {
            padding: 12px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .test-email-row button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border: 1px solid #f59e0b;
            padding: 15px 20px;
            border-radius: 10px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-top: 20px;
        }

        .info-box i {
            color: #f59e0b;
            font-size: 18px;
            margin-top: 2px;
        }

        .info-box p {
            font-size: 13px;
            color: #b45309;
            margin: 0;
            line-height: 1.5;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .settings-layout {
                grid-template-columns: 1fr;
            }

            .settings-nav {
                position: static;
                display: flex;
                flex-wrap: wrap;
                gap: 10px;
                padding: 15px;
            }

            .settings-nav h3 {
                width: 100%;
                margin-bottom: 10px;
            }

            .nav-item {
                margin-bottom: 0;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 30px;
            }

            .header-stats {
                width: 100%;
                justify-content: center;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .status-grid {
                grid-template-columns: 1fr;
            }

            .test-email-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/superadmin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <p>Configure system preferences and application settings</p>
            </div>
            <div class="header-stats">
                <div class="header-stat">
                    <span class="header-stat-value"><?= $notifCount ?>/4</span>
                    <span class="header-stat-label">Notifications Active</span>
                </div>
                <div class="header-stat">
                    <span class="header-stat-value"><?= $securityCount ?>/3</span>
                    <span class="header-stat-label">Security Features</span>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if ($saved): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Settings saved successfully!</span>
            </div>
        <?php endif; ?>

        <?php if (isset($testResult)): ?>
            <div class="alert <?= $testResult === 'success' ? 'alert-success' : 'alert-error' ?>">
                <i class="fas fa-<?= $testResult === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <span>Test email <?= $testResult === 'success' ? 'sent successfully! Check your inbox.' : 'failed. Check PHP mail configuration.' ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
            <div class="settings-layout">
                <!-- Settings Navigation -->
                <div class="settings-nav">
                    <h3>Settings Menu</h3>
                    <a href="#general" class="nav-item active" onclick="scrollToSection('general')">
                        <i class="fas fa-building"></i>
                        <span>General</span>
                    </a>
                    <a href="#notifications" class="nav-item" onclick="scrollToSection('notifications')">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                        <span class="badge"><?= $notifCount ?></span>
                    </a>
                    <a href="#smtp" class="nav-item" onclick="scrollToSection('smtp')">
                        <i class="fas fa-envelope"></i>
                        <span>Email (SMTP)</span>
                    </a>
                    <a href="#security" class="nav-item" onclick="scrollToSection('security')">
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                        <span class="badge"><?= $securityCount ?></span>
                    </a>
                    <a href="#status" class="nav-item" onclick="scrollToSection('status')">
                        <i class="fas fa-chart-bar"></i>
                        <span>Status Overview</span>
                    </a>
                </div>

                <!-- Settings Content -->
                <div class="settings-content">
                    <!-- General Settings -->
                    <div class="settings-card" id="general">
                        <div class="settings-card-header">
                            <div class="settings-card-icon purple">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="settings-card-title">
                                <h3>General Settings</h3>
                                <p>Basic organization and system preferences</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <!-- Logo & Company Name Row -->
                            <div class="form-row" style="align-items: flex-start;">
                                <div class="form-group">
                                    <label><i class="fas fa-image"></i> Company Logo</label>
                                    <div class="logo-selector">
                                        <div class="logo-preview">
                                            <?php 
                                            $logoPath = $settings['company_logo'] ?? 'e-HRMS logo.png';
                                            $logoFile = __DIR__ . '/../assets/images/' . $logoPath;
                                            if ($logoPath && file_exists($logoFile)): ?>
                                                <img src="../assets/images/<?= htmlspecialchars($logoPath) ?>" alt="Current Logo" id="logoPreview">
                                            <?php else: ?>
                                                <i class="fas fa-building" id="logoPlaceholder"></i>
                                            <?php endif; ?>
                                        </div>
                                        <select name="company_logo" id="logoSelect" onchange="updateLogoPreview()">
                                            <option value="">No Logo</option>
                                            <?php
                                            $imagesDir = __DIR__ . '/../assets/images/';
                                            if (is_dir($imagesDir)) {
                                                $images = scandir($imagesDir);
                                                foreach ($images as $img) {
                                                    if (preg_match('/\.(png|jpg|jpeg|gif|svg)$/i', $img)) {
                                                        $sel = ($settings['company_logo'] ?? '') === $img ? 'selected' : '';
                                                        echo "<option value=\"" . htmlspecialchars($img) . "\" {$sel}>" . htmlspecialchars($img) . "</option>";
                                                    }
                                                }
                                            }
                                            ?>
                                        </select>
                                        <div class="form-hint">
                                            <i class="fas fa-info-circle"></i>
                                            Upload images to <code>public/assets/images/</code>
                                        </div>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-building"></i> Company Name</label>
                                    <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name']) ?>" placeholder="Enter company name" required>
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Displayed in sidebar, emails & payslips
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Company Email</label>
                                    <input type="email" name="company_email" value="<?= htmlspecialchars($settings['company_email']) ?>" placeholder="Enter company email" required>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-phone"></i> Company Phone</label>
                                    <input type="tel" name="company_phone" value="<?= htmlspecialchars($settings['company_phone']) ?>" placeholder="Enter phone number">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-globe"></i> Time Zone</label>
                                    <select name="time_zone" required>
                                        <?php
                                            $zones = [
                                                'UTC' => 'UTC (Coordinated Universal Time)',
                                                'Asia/Kolkata' => 'Asia/Kolkata (IST +5:30)',
                                                'America/New_York' => 'America/New_York (EST -5:00)',
                                                'America/Los_Angeles' => 'America/Los_Angeles (PST -8:00)',
                                                'Europe/London' => 'Europe/London (GMT +0:00)',
                                                'Europe/Paris' => 'Europe/Paris (CET +1:00)',
                                                'Asia/Tokyo' => 'Asia/Tokyo (JST +9:00)',
                                                'Australia/Sydney' => 'Australia/Sydney (AEST +10:00)'
                                            ];
                                            foreach ($zones as $val => $label) {
                                                $sel = $settings['time_zone'] === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-calendar-alt"></i> Date Format</label>
                                    <select name="date_format">
                                        <?php
                                            $formats = [
                                                'd/m/Y' => 'DD/MM/YYYY (31/12/2026)',
                                                'm/d/Y' => 'MM/DD/YYYY (12/31/2026)',
                                                'Y-m-d' => 'YYYY-MM-DD (2026-12-31)',
                                                'd-M-Y' => 'DD-Mon-YYYY (31-Dec-2026)'
                                            ];
                                            foreach ($formats as $val => $label) {
                                                $sel = ($settings['date_format'] ?? 'd/m/Y') === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row single">
                                <div class="form-group">
                                    <label><i class="fas fa-rupee-sign"></i> Currency</label>
                                    <select name="currency">
                                        <?php
                                            $currencies = [
                                                'INR' => '₹ Indian Rupee (INR)',
                                                'USD' => '$ US Dollar (USD)',
                                                'EUR' => '€ Euro (EUR)',
                                                'GBP' => '£ British Pound (GBP)'
                                            ];
                                            foreach ($currencies as $val => $label) {
                                                $sel = ($settings['currency'] ?? 'INR') === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-hint">
                                <i class="fas fa-clock"></i>
                                Current Server Time: <strong><?= date('d M Y, h:i:s A') ?></strong> (<?= date_default_timezone_get() ?>)
                            </div>
                        </div>
                    </div>

                    <!-- Notification Settings -->
                    <div class="settings-card" id="notifications">
                        <div class="settings-card-header">
                            <div class="settings-card-icon green">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div class="settings-card-title">
                                <h3>Notification Settings</h3>
                                <p>Configure email and system notifications</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot <?= $settings['email_notifications'] === '1' ? 'active' : 'inactive' ?>"></span>
                                        Email Notifications
                                    </h4>
                                    <p>Send email notifications for important system events</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="email_notifications" value="1" <?= $settings['email_notifications'] === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot <?= $settings['payslip_alerts'] === '1' ? 'active' : 'inactive' ?>"></span>
                                        Payslip Generation Alerts
                                    </h4>
                                    <p>Notify employees when their payslips are generated</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="payslip_alerts" value="1" <?= $settings['payslip_alerts'] === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot <?= $settings['employee_updates'] === '1' ? 'active' : 'inactive' ?>"></span>
                                        Employee Profile Updates
                                    </h4>
                                    <p>Alert admins when employee profiles are modified</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="employee_updates" value="1" <?= $settings['employee_updates'] === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot <?= ($settings['leave_notifications'] ?? '1') === '1' ? 'active' : 'inactive' ?>"></span>
                                        Leave Request Notifications
                                    </h4>
                                    <p>Notify when leave requests are submitted or processed</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="leave_notifications" value="1" <?= ($settings['leave_notifications'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <!-- Test Email Section -->
                            <div class="test-email-section">
                                <h4><i class="fas fa-paper-plane"></i> Test Email Notifications</h4>
                                <div class="test-email-row">
                                    <input type="email" id="testEmail" placeholder="Enter email address to test...">
                                    <button type="button" onclick="sendTestEmail()">
                                        <i class="fas fa-paper-plane"></i> Send Test
                                    </button>
                                </div>
                            </div>

                            <div class="info-box">
                                <i class="fas fa-info-circle"></i>
                                <p><strong>Note:</strong> Configure SMTP settings below to enable email delivery. Without SMTP, emails cannot be sent.</p>
                            </div>
                        </div>
                    </div>

                    <!-- SMTP Settings -->
                    <div class="settings-card" id="smtp">
                        <div class="settings-card-header">
                            <div class="settings-card-icon orange">
                                <i class="fas fa-server"></i>
                            </div>
                            <div class="settings-card-title">
                                <h3>Email Server (SMTP) Settings</h3>
                                <p>Configure email delivery for notifications</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="info-box" style="margin-top: 0; margin-bottom: 20px; background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1)); border-color: #3b82f6;">
                                <i class="fas fa-lightbulb" style="color: #3b82f6;"></i>
                                <p style="color: #1d4ed8;"><strong>Gmail Setup:</strong> Use <code>smtp.gmail.com</code>, port <code>587</code>, TLS encryption. Create an <a href="https://myaccount.google.com/apppasswords" target="_blank" style="color: #3b82f6;">App Password</a> (not your regular password).</p>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-server"></i> SMTP Host</label>
                                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com') ?>" placeholder="smtp.gmail.com">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Gmail: smtp.gmail.com | Outlook: smtp.office365.com
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-plug"></i> SMTP Port</label>
                                    <select name="smtp_port">
                                        <?php
                                            $ports = ['587' => '587 (TLS - Recommended)', '465' => '465 (SSL)', '25' => '25 (Unsecured)'];
                                            foreach ($ports as $val => $label) {
                                                $sel = ($settings['smtp_port'] ?? '587') === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> SMTP Username</label>
                                    <input type="email" name="smtp_username" value="<?= htmlspecialchars($settings['smtp_username'] ?? '') ?>" placeholder="your-email@gmail.com">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Your email address for authentication
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-key"></i> SMTP Password / App Password</label>
                                    <input type="password" name="smtp_password" value="<?= htmlspecialchars($settings['smtp_password'] ?? '') ?>" placeholder="Enter app password">
                                    <div class="form-hint">
                                        <i class="fas fa-exclamation-triangle" style="color: #f59e0b;"></i>
                                        For Gmail, use App Password (16 characters, no spaces)
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Encryption</label>
                                    <select name="smtp_encryption">
                                        <?php
                                            $encryptions = ['tls' => 'TLS (Recommended)', 'ssl' => 'SSL'];
                                            foreach ($encryptions as $val => $label) {
                                                $sel = ($settings['smtp_encryption'] ?? 'tls') === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> From Email (optional)</label>
                                    <input type="email" name="smtp_from_email" value="<?= htmlspecialchars($settings['smtp_from_email'] ?? '') ?>" placeholder="Same as username if empty">
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Leave empty to use SMTP username
                                    </div>
                                </div>
                            </div>

                            <!-- SMTP Status -->
                            <div style="margin-top: 20px; padding: 15px; background: #f8fafc; border-radius: 10px; display: flex; align-items: center; gap: 15px;">
                                <?php 
                                $smtpConfigured = !empty($settings['smtp_username']) && !empty($settings['smtp_password']);
                                ?>
                                <div style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: <?= $smtpConfigured ? 'linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15))' : 'linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15))' ?>;">
                                    <i class="fas fa-<?= $smtpConfigured ? 'check' : 'times' ?>" style="color: <?= $smtpConfigured ? '#10b981' : '#ef4444' ?>;"></i>
                                </div>
                                <div>
                                    <strong style="color: var(--text);">SMTP Status:</strong>
                                    <span style="color: <?= $smtpConfigured ? '#10b981' : '#ef4444' ?>; font-weight: 600;">
                                        <?= $smtpConfigured ? 'Configured' : 'Not Configured' ?>
                                    </span>
                                    <?php if (!$smtpConfigured): ?>
                                        <p style="margin: 5px 0 0; font-size: 12px; color: var(--muted);">Enter SMTP credentials above and save to enable email sending.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Security Settings -->
                    <div class="settings-card" id="security">
                        <div class="settings-card-header">
                            <div class="settings-card-icon red">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="settings-card-title">
                                <h3>Security Settings</h3>
                                <p>Configure authentication and security options</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot inactive"></span>
                                        Two-Factor Authentication
                                        <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px;">Coming Soon</span>
                                    </h4>
                                    <p>Require 2FA for all administrator accounts (not yet implemented)</p>
                                </div>
                                <label class="toggle-switch" style="opacity: 0.5; pointer-events: none;">
                                    <input type="checkbox" name="two_factor" value="1" disabled>
                                    <span class="slider"></span>
                                </label>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot <?= $settings['session_timeout'] === '1' ? 'active' : 'inactive' ?>"></span>
                                        Session Timeout
                                    </h4>
                                    <p>Automatically logout users after period of inactivity</p>
                                </div>
                                <div style="display: flex; align-items: center; gap: 15px;">
                                    <select name="session_timeout_minutes" style="padding: 8px 12px; border: 2px solid #e2e8f0; border-radius: 8px; font-size: 13px; background: #f8fafc;">
                                        <?php
                                            $timeouts = ['15' => '15 min', '30' => '30 min', '60' => '1 hour', '120' => '2 hours'];
                                            foreach ($timeouts as $val => $label) {
                                                $sel = ($settings['session_timeout_minutes'] ?? '30') === $val ? 'selected' : '';
                                                echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                            }
                                        ?>
                                    </select>
                                    <label class="toggle-switch">
                                        <input type="checkbox" name="session_timeout" value="1" <?= $settings['session_timeout'] === '1' ? 'checked' : '' ?>>
                                        <span class="slider"></span>
                                    </label>
                                </div>
                            </div>

                            <div class="setting-item">
                                <div class="setting-info">
                                    <h4>
                                        <span class="status-dot inactive"></span>
                                        Password Expiry
                                        <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px; margin-left: 8px;">Coming Soon</span>
                                    </h4>
                                    <p>Force users to change password periodically (30/60/90 days)</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="password_expiry" value="1" disabled>
                                    <span class="slider" style="opacity: 0.5; cursor: not-allowed;"></span>
                                </label>
                            </div>

                            <div class="form-row" style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #f1f5f9;">
                                <div class="form-group">
                                    <label><i class="fas fa-lock"></i> Max Login Attempts</label>
                                    <select name="login_attempts">
                                        <?php
                                            $attempts = [3, 5, 10];
                                            foreach ($attempts as $num) {
                                                $sel = ($settings['login_attempts'] ?? '5') == $num ? 'selected' : '';
                                                echo "<option value=\"{$num}\" {$sel}>{$num} attempts before lockout</option>";
                                            }
                                        ?>
                                    </select>
                                    <div class="form-hint">
                                        <i class="fas fa-info-circle"></i>
                                        Account will be locked after exceeding this limit
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status Overview -->
                    <div class="settings-card" id="status">
                        <div class="settings-card-header">
                            <div class="settings-card-icon blue">
                                <i class="fas fa-chart-bar"></i>
                            </div>
                            <div class="settings-card-title">
                                <h3>Status Overview</h3>
                                <p>Current configuration status at a glance</p>
                            </div>
                        </div>
                        <div class="settings-card-body">
                            <div class="status-grid">
                                <div class="status-item">
                                    <div class="status-icon <?= $settings['email_notifications'] === '1' ? 'enabled' : 'disabled' ?>">
                                        <i class="fas fa-envelope"></i>
                                    </div>
                                    <div class="status-text">
                                        <h5>Email Notifications</h5>
                                        <span class="<?= $settings['email_notifications'] === '1' ? 'enabled' : 'disabled' ?>">
                                            <?= $settings['email_notifications'] === '1' ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="status-item">
                                    <div class="status-icon <?= $settings['payslip_alerts'] === '1' ? 'enabled' : 'disabled' ?>">
                                        <i class="fas fa-file-invoice"></i>
                                    </div>
                                    <div class="status-text">
                                        <h5>Payslip Alerts</h5>
                                        <span class="<?= $settings['payslip_alerts'] === '1' ? 'enabled' : 'disabled' ?>">
                                            <?= $settings['payslip_alerts'] === '1' ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="status-item">
                                    <div class="status-icon <?= $settings['two_factor'] === '1' ? 'enabled' : 'disabled' ?>">
                                        <i class="fas fa-mobile-alt"></i>
                                    </div>
                                    <div class="status-text">
                                        <h5>Two-Factor Auth</h5>
                                        <span class="<?= $settings['two_factor'] === '1' ? 'enabled' : 'disabled' ?>">
                                            <?= $settings['two_factor'] === '1' ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="status-item">
                                    <div class="status-icon <?= $settings['session_timeout'] === '1' ? 'enabled' : 'disabled' ?>">
                                        <i class="fas fa-clock"></i>
                                    </div>
                                    <div class="status-text">
                                        <h5>Session Timeout</h5>
                                        <span class="<?= $settings['session_timeout'] === '1' ? 'enabled' : 'disabled' ?>">
                                            <?= $settings['session_timeout'] === '1' ? 'Enabled' : 'Disabled' ?>
                                        </span>
                                    </div>
                                </div>

                                <div class="status-item">
                                    <div class="status-icon disabled">
                                        <i class="fas fa-key"></i>
                                    </div>
                                    <div class="status-text">
                                        <h5>Password Expiry</h5>
                                        <span style="background: linear-gradient(135deg, #f59e0b, #d97706); color: white; font-size: 10px; padding: 2px 8px; border-radius: 10px;">Coming Soon</span>
                                    </div>
                                </div>

                                <div class="status-item">
                                    <div class="status-icon enabled">
                                        <i class="fas fa-globe"></i>
                                    </div>
                                    <div class="status-text">
                                        <h5>Time Zone</h5>
                                        <span class="enabled"><?= $settings['time_zone'] ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save All Settings
                        </button>
                        <button type="reset" class="btn btn-secondary">
                            <i class="fas fa-undo"></i> Reset Changes
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <?php include 'includes/superadmin_scripts.php'; ?>

    <script>
        function sendTestEmail() {
            const email = document.getElementById('testEmail').value;
            if (email && email.includes('@')) {
                window.location.href = 'settings.php?test_email=' + encodeURIComponent(email);
            } else {
                alert('Please enter a valid email address');
            }
        }

        function updateLogoPreview() {
            const select = document.getElementById('logoSelect');
            const preview = document.querySelector('.logo-preview');
            const selectedFile = select.value;
            
            if (selectedFile) {
                preview.innerHTML = `<img src="../assets/images/${selectedFile}" alt="Logo Preview" id="logoPreview">`;
            } else {
                preview.innerHTML = '<i class="fas fa-building" id="logoPlaceholder"></i>';
            }
        }

        function scrollToSection(id) {
            const element = document.getElementById(id);
            if (element) {
                element.scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                // Update active nav
                document.querySelectorAll('.nav-item').forEach(item => item.classList.remove('active'));
                document.querySelector(`[href="#${id}"]`).classList.add('active');
            }
        }

        // Highlight active section on scroll
        window.addEventListener('scroll', () => {
            const sections = ['general', 'notifications', 'smtp', 'security', 'status'];
            let current = '';

            sections.forEach(id => {
                const section = document.getElementById(id);
                if (section) {
                    const sectionTop = section.offsetTop - 150;
                    if (window.scrollY >= sectionTop) {
                        current = id;
                    }
                }
            });

            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
                if (item.getAttribute('href') === '#' + current) {
                    item.classList.add('active');
                }
            });
        });
    </script>

</body>
</html>
