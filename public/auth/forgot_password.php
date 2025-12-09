<?php
header('Content-Type: application/json');

// Set timezone from settings
date_default_timezone_set('Asia/Kolkata');

error_reporting(E_ALL);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/EmailHelper.php';

try {
    $conn = getDBConnection();

    // Ensure reset table exists
    $conn->exec("CREATE TABLE IF NOT EXISTS password_resets (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash VARCHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (user_id),
        INDEX (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    $username = trim($_POST['username'] ?? '');
    if ($username === '') {
        echo json_encode(['success' => false, 'message' => 'Username is required']);
        exit;
    }

    // Find active user
    $stmt = $conn->prepare("SELECT user_id, username, email, role, employee_id FROM users WHERE username = :u AND is_active = 1 LIMIT 1");
    $stmt->execute([':u' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'User not found or inactive']);
        exit;
    }

    // Resolve recipient email (priority: users.email > employees.email > company email)
    $recipientEmail = $user['email'];
    $recipientName = $user['username'];
    
    if (!$recipientEmail && !empty($user['employee_id'])) {
        $empStmt = $conn->prepare("SELECT full_name, email FROM employees WHERE employee_id = :id LIMIT 1");
        $empStmt->execute([':id' => $user['employee_id']]);
        if ($emp = $empStmt->fetch(PDO::FETCH_ASSOC)) {
            $recipientEmail = $emp['email'];
            $recipientName = $emp['full_name'] ?: $recipientName;
        }
    }

    // Load settings
    $settings = [];
    $setStmt = $conn->query("SELECT setting_key, setting_value FROM settings");
    while ($row = $setStmt->fetch(PDO::FETCH_ASSOC)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }

    // Final fallback to company email
    if (!$recipientEmail) {
        $recipientEmail = $settings['company_email'] ?? null;
    }

    if (!$recipientEmail) {
        echo json_encode(['success' => false, 'message' => 'No email configured for this user. Contact administrator.']);
        exit;
    }

    // Create token
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);
    $expiresAt = (new DateTime('+5 minutes'))->format('Y-m-d H:i:s');

    // Invalidate older tokens for this user
    $conn->prepare("UPDATE password_resets SET used = 1 WHERE user_id = :uid")->execute([':uid' => $user['user_id']]);

    // Store reset token
    $ins = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at, used) VALUES (:uid, :th, :exp, 0)");
    $ins->execute([':uid' => $user['user_id'], ':th' => $tokenHash, ':exp' => $expiresAt]);

    // Build reset link
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $resetLink = $scheme . '://' . $host . '/payslip_generator/public/auth/reset_password.php?token=' . $token;

    // Compose email
    $companyName = $settings['company_name'] ?? 'Enterprise Payroll';
    $subject = 'Password Reset Instructions';
    $message = "<html><body style='font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #0ea5e9; margin-bottom: 20px;'>Password Reset Request</h2>
            <p style='color: #333; font-size: 14px; line-height: 1.6;'>Hi " . htmlspecialchars($recipientName) . ",</p>
            <p style='color: #333; font-size: 14px; line-height: 1.6;'>We received a request to reset your password. Click the button below to set a new password:</p>
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . htmlspecialchars($resetLink) . "' style='background: linear-gradient(135deg, #0ea5e9, #22d3ee); color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold; font-size: 14px;'>Reset Password</a>
            </div>
            <p style='color: #666; font-size: 12px; line-height: 1.6;'>Or copy this link if the button doesn't work:<br><a href='" . htmlspecialchars($resetLink) . "' style='color: #0ea5e9;'>" . htmlspecialchars($resetLink) . "</a></p>
            <hr style='margin: 20px 0; border: none; border-top: 1px solid #ddd;'>
            <p style='color: #666; font-size: 12px;'>This link will expire in 5 minutes. If you did not request a reset, please ignore this email.</p>
            <p style='color: #999; font-size: 11px; margin-top: 20px;'>Best regards,<br>" . htmlspecialchars($companyName) . "</p>
        </div>
    </body></html>";

    // Try to send email
    $emailHelper = new EmailHelper($conn);
    $emailSent = $emailHelper->sendEmail($recipientEmail, $subject, $message);

    // Also save to log file as backup
    $logDir = __DIR__ . '/../../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/password_resets.txt';
    $logContent = "\n" . str_repeat('=', 80) . "\n";
    $logContent .= "Password Reset Request\n";
    $logContent .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $logContent .= "Username: " . $user['username'] . "\n";
    $logContent .= "Email: " . $recipientEmail . "\n";
    $logContent .= "Email Sent: " . ($emailSent ? 'YES' : 'NO (Check Gmail SMTP Config)') . "\n";
    $logContent .= "Reset Link: " . $resetLink . "\n";
    $logContent .= "Expires: " . $expiresAt . "\n";
    $logContent .= str_repeat('=', 80) . "\n";
    
    file_put_contents($logFile, $logContent, FILE_APPEND);

    echo json_encode(['success' => true, 'message' => 'Password reset instructions have been sent to your email.']);
} catch (Throwable $e) {
    error_log("Forgot Password Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
