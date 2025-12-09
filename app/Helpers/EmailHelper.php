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
        $stmt = $this->conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    public function sendEmail($to, $subject, $body, $fromName = null) {
        $mail = new PHPMailer(true);

        try {
            // Server settings
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'jishusahu096@gmail.com';
            $mail->Password   = 'rdxu nrvx xnit xozm';  // Gmail App Password
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;
            $mail->Timeout    = 10;
            $mail->SMTPDebug  = 0;

            // Recipients
            $companyName = $this->settings['company_name'] ?? 'Enterprise Payroll';
            $mail->setFrom('jishusahu096@gmail.com', $fromName ?? $companyName);
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
