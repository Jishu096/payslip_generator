<?php
require_once __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailHelper {
    
    private $settings;
    private $conn;

    public function __construct($conn) {
        $this->conn = $conn;
        $this->loadSettings();
    }

    private function loadSettings() {
        $this->settings = [];
        try {
            $stmt = $this->conn->query("SELECT setting_key, setting_value FROM settings");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Use defaults if settings table doesn't exist
        }
    }

    public function sendEmail($to, $subject, $body, $fromName = null) {
        // Check if email notifications are enabled
        if (($this->settings['email_notifications'] ?? '0') !== '1') {
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            // Get SMTP settings from database (with fallback defaults)
            $smtpHost = $this->settings['smtp_host'] ?? 'smtp.gmail.com';
            $smtpPort = $this->settings['smtp_port'] ?? '587';
            $smtpUsername = $this->settings['smtp_username'] ?? '';
            $smtpPassword = $this->settings['smtp_password'] ?? '';
            $smtpEncryption = $this->settings['smtp_encryption'] ?? 'tls';
            $smtpFromEmail = $this->settings['smtp_from_email'] ?? $smtpUsername;
            $companyName = $this->settings['company_name'] ?? 'NIELIT e-HRMS';

            // Check if SMTP is configured
            if (empty($smtpUsername) || empty($smtpPassword)) {
                $this->logNotification('email', $to, $subject, false, 'SMTP not configured');
                return false;
            }

            // Server settings
            $mail->isSMTP();
            $mail->Host       = $smtpHost;
            $mail->SMTPAuth   = true;
            $mail->Username   = $smtpUsername;
            $mail->Password   = $smtpPassword;
            $mail->SMTPSecure = $smtpEncryption === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int)$smtpPort;
            $mail->Timeout    = 10;
            $mail->SMTPDebug  = 0;

            // Recipients
            $mail->setFrom($smtpFromEmail, $fromName ?? $companyName);
            $mail->addAddress($to);

            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;
            $mail->AltBody = strip_tags($body);

            $mail->send();
            $this->logNotification('email', $to, $subject, true);
            return true;
        } catch (Exception $e) {
            $error = $mail->ErrorInfo;
            $this->logNotification('email', $to, $subject, false, $error);
            return false;
        }
    }

    private function logNotification($type, $recipient, $subject, $success, $error = null) {
        try {
            $this->conn->exec("CREATE TABLE IF NOT EXISTS notification_logs (
                log_id INT AUTO_INCREMENT PRIMARY KEY,
                notification_type VARCHAR(50),
                recipient VARCHAR(255),
                subject VARCHAR(255),
                status VARCHAR(20),
                error_message TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )");

            $stmt = $this->conn->prepare("INSERT INTO notification_logs (notification_type, recipient, subject, status, error_message) 
                                           VALUES (:type, :recipient, :subject, :status, :error)");
            $stmt->execute([
                ':type' => $type,
                ':recipient' => $recipient,
                ':subject' => $subject,
                ':status' => $success ? 'sent' : 'failed',
                ':error' => $error
            ]);
        } catch (Exception $e) {
            // Silent fail on logging
        }
    }
}
