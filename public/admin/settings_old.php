<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

require_once __DIR__ . '/../../app/Config/database.php';

$db = new Database();
$conn = $db->connect();

// Test email sending
if (isset($_GET['test_email'])) {
    require_once __DIR__ . '/../../app/Helpers/NotificationHelper.php';
    $notif = new NotificationHelper($conn);
    
    $testEmail = $_GET['test_email'];
    $result = $notif->sendEmailNotification(
        $testEmail,
        'Test Email from Payroll System',
        '<h2>Test Email</h2><p>This is a test notification from your payroll system.</p><p>If you received this, email notifications are working!</p>'
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
    'company_name' => 'Enterprise Payroll Solutions',
    'company_email' => 'info@enterprisepayroll.com',
    'time_zone' => 'Asia/Kolkata',
    'email_notifications' => '1',
    'payslip_alerts' => '1',
    'employee_updates' => '0',
    'two_factor' => '0',
    'session_timeout' => '1',
    'password_expiry' => '0'
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .settings-grid {
            display: grid;
            gap: 25px;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 25px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .settings-header i {
            font-size: 24px;
        }

        .settings-header h3 {
            font-size: 18px;
            font-weight: 600;
        }

        .settings-body {
            padding: 25px;
        }

        .setting-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            border-bottom: 1px solid #e0e0e0;
        }

        .setting-item:last-child {
            border-bottom: none;
        }

        .setting-info h4 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .setting-info p {
            font-size: 13px;
            color: #7f8c8d;
        }

        .toggle-switch {
            position: relative;
            width: 60px;
            height: 30px;
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
            background-color: #ccc;
            transition: .4s;
            border-radius: 30px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        input:checked + .slider:before {
            transform: translateX(30px);
        }

        .btn-save {
            margin-top: 20px;
        }

        .form-group {
            margin-bottom: 20px;
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
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-cog"></i> System Settings</h1>
            <p>Configure system preferences and application settings</p>
        </div>

        <?php if ($saved): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Settings saved successfully.
            </div>
        <?php endif; ?>

        <?php if (isset($testResult)): ?>
            <div style="background:<?php echo $testResult === 'success' ? '#e8f7ee' : '#fee'; ?>;border:1px solid <?php echo $testResult === 'success' ? '#b6e0c5' : '#fcc'; ?>;color:<?php echo $testResult === 'success' ? '#1b6b3d' : '#c00'; ?>;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-<?php echo $testResult === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i> 
                Test email <?php echo $testResult === 'success' ? 'sent successfully! Check your inbox.' : 'failed. Check PHP mail configuration.'; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="settings.php">
        <div class="settings-grid">
            <!-- General Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-sliders-h"></i>
                    <h3>General Settings</h3>
                </div>
                <div class="settings-body">
                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="<?php echo htmlspecialchars($settings['company_name']); ?>" placeholder="Enter company name" required>
                    </div>
                    <div class="form-group">
                        <label>Company Email</label>
                        <input type="email" name="company_email" value="<?php echo htmlspecialchars($settings['company_email']); ?>" placeholder="Enter company email" required>
                    </div>
                    <div class="form-group">
                        <label>Time Zone</label>
                        <select name="time_zone" required>
                            <?php
                                $zones = [
                                    'UTC' => 'UTC',
                                    'Asia/Kolkata' => 'Asia/Kolkata (IST)',
                                    'America/New_York' => 'America/New_York (EST)',
                                    'Europe/London' => 'Europe/London (GMT)'
                                ];
                                foreach ($zones as $val => $label) {
                                    $sel = $settings['time_zone'] === $val ? 'selected' : '';
                                    echo "<option value=\"{$val}\" {$sel}>{$label}</option>";
                                }
                            ?>
                        </select>
                        <p style="font-size: 12px; color: #7f8c8d; margin-top: 8px;">
                            <i class="fas fa-clock"></i> Current Server Time: <strong><?php echo date('Y-m-d h:i:s A'); ?></strong> (<?php echo date_default_timezone_get(); ?>)
                        </p>
                    </div>
                    <button type="submit" class="btn btn-save">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </div>

            <!-- Notification Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-bell"></i>
                    <h3>Notification Settings</h3>
                </div>
                <div class="settings-body">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Email Notifications</h4>
                            <p>Send email notifications for important events</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="email_notifications" value="1" <?php echo $settings['email_notifications'] === '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Payslip Generation Alerts</h4>
                            <p>Notify when payslips are generated</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="payslip_alerts" value="1" <?php echo $settings['payslip_alerts'] === '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Employee Updates</h4>
                            <p>Alert on employee profile changes</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="employee_updates" value="1" <?php echo $settings['employee_updates'] === '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-shield-alt"></i>
                    <h3>Security Settings</h3>
                </div>
                <div class="settings-body">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Two-Factor Authentication</h4>
                            <p>Enable 2FA for enhanced security</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="two_factor" value="1" <?php echo $settings['two_factor'] === '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Session Timeout</h4>
                            <p>Auto-logout after 30 minutes of inactivity</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="session_timeout" value="1" <?php echo $settings['session_timeout'] === '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Password Expiry</h4>
                            <p>Force password change every 90 days</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" name="password_expiry" value="1" <?php echo $settings['password_expiry'] === '1' ? 'checked' : ''; ?>>
                            <span class="slider"></span>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Notification Status Card -->
            <div class="settings-card">
                <div class="settings-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Notification Status</h3>
                </div>
                <div class="settings-body">
                    <div style="background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                        <h4 style="font-size: 14px; color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-chart-bar"></i> Active Notifications</h4>
                        <ul style="list-style: none; padding: 0; margin: 0;">
                            <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                <i class="fas fa-<?php echo $settings['email_notifications'] === '1' ? 'check-circle' : 'times-circle'; ?>" style="color: <?php echo $settings['email_notifications'] === '1' ? '#27ae60' : '#e74c3c'; ?>;"></i>
                                Email Notifications: <strong><?php echo $settings['email_notifications'] === '1' ? 'Enabled' : 'Disabled'; ?></strong>
                            </li>
                            <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                                <i class="fas fa-<?php echo $settings['payslip_alerts'] === '1' ? 'check-circle' : 'times-circle'; ?>" style="color: <?php echo $settings['payslip_alerts'] === '1' ? '#27ae60' : '#e74c3c'; ?>;"></i>
                                Payslip Alerts: <strong><?php echo $settings['payslip_alerts'] === '1' ? 'Enabled' : 'Disabled'; ?></strong>
                            </li>
                            <li style="padding: 8px 0;">
                                <i class="fas fa-<?php echo $settings['employee_updates'] === '1' ? 'check-circle' : 'times-circle'; ?>" style="color: <?php echo $settings['employee_updates'] === '1' ? '#27ae60' : '#e74c3c'; ?>;"></i>
                                Employee Update Alerts: <strong><?php echo $settings['employee_updates'] === '1' ? 'Enabled' : 'Disabled'; ?></strong>
                            </li>
                        </ul>
                    </div>
                    <div style="background: #fff4e5; border: 1px solid #ffd8a8; padding: 12px; border-radius: 8px; color: #b35c00; font-size: 13px;">
                        <i class="fas fa-info-circle"></i> <strong>Note:</strong> Email notifications require proper mail server configuration (SMTP). Currently using PHP mail() function.
                    </div>
                    
                    <!-- Test Email Form -->
                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <h4 style="font-size: 14px; color: #2c3e50; margin-bottom: 10px;"><i class="fas fa-envelope"></i> Test Email Notifications</h4>
                        <div style="display: flex; gap: 10px; align-items: flex-end;">
                            <div style="flex: 1;">
                                <label style="display: block; font-size: 12px; color: #7f8c8d; margin-bottom: 5px;">Enter Email Address</label>
                                <input type="email" id="testEmail" placeholder="your@email.com" style="width: 100%; padding: 8px 12px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 14px;">
                            </div>
                            <button type="button" onclick="sendTestEmail()" style="padding: 8px 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">
                                <i class="fas fa-paper-plane"></i> Send Test
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        </form>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        function sendTestEmail() {
            const email = document.getElementById('testEmail').value;
            if (!email) {
                alert('Please enter an email address');
                return;
            }
            if (!email.includes('@')) {
                alert('Please enter a valid email address');
                return;
            }
            window.location.href = 'settings.php?test_email=' + encodeURIComponent(email);
        }
    </script>

</body>
</html>
