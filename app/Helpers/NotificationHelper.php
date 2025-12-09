<?php

class NotificationHelper
{
    private $conn;
    private $settings;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadSettings();
    }

    private function loadSettings()
    {
        $this->settings = [];
        $stmt = $this->conn->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    public function sendEmailNotification($to, $subject, $message)
    {
        // Only send if email notifications are enabled
        if (($this->settings['email_notifications'] ?? '0') !== '1') {
            return false;
        }

        $companyEmail = $this->settings['company_email'] ?? 'noreply@company.com';
        $companyName = $this->settings['company_name'] ?? 'Company';

        $headers = "From: {$companyName} <{$companyEmail}>\r\n";
        $headers .= "Reply-To: {$companyEmail}\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        return mail($to, $subject, $message, $headers);
    }

    public function notifyPayslipGeneration($employeeEmail, $employeeName, $month)
    {
        // Only send if payslip alerts are enabled
        if (($this->settings['payslip_alerts'] ?? '0') !== '1') {
            return false;
        }

        $subject = "Payslip Generated for {$month}";
        $message = "
            <html>
            <body>
                <h2>Payslip Generated</h2>
                <p>Dear {$employeeName},</p>
                <p>Your payslip for <strong>{$month}</strong> has been generated and is now available.</p>
                <p>Please log in to view and download your payslip.</p>
                <br>
                <p>Best regards,<br>{$this->settings['company_name']}</p>
            </body>
            </html>
        ";

        return $this->sendEmailNotification($employeeEmail, $subject, $message);
    }

    public function notifyEmployeeUpdate($adminEmail, $employeeName, $updateType)
    {
        // Only send if employee update notifications are enabled
        if (($this->settings['employee_updates'] ?? '0') !== '1') {
            return false;
        }

        $subject = "Employee Profile Updated: {$employeeName}";
        $message = "
            <html>
            <body>
                <h2>Employee Update Notification</h2>
                <p>An employee profile has been updated:</p>
                <ul>
                    <li><strong>Employee:</strong> {$employeeName}</li>
                    <li><strong>Update Type:</strong> {$updateType}</li>
                    <li><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</li>
                </ul>
                <p>Please review the changes in the admin panel.</p>
                <br>
                <p>Best regards,<br>{$this->settings['company_name']}</p>
            </body>
            </html>
        ";

        return $this->sendEmailNotification($adminEmail, $subject, $message);
    }

    public function logNotification($type, $recipient, $subject, $status)
    {
        // Create notifications log table if not exists
        $this->conn->exec("CREATE TABLE IF NOT EXISTS notification_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            notification_type VARCHAR(50),
            recipient VARCHAR(255),
            subject VARCHAR(255),
            status VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $stmt = $this->conn->prepare("INSERT INTO notification_logs (notification_type, recipient, subject, status) 
                                       VALUES (:type, :recipient, :subject, :status)");
        $stmt->execute([
            ':type' => $type,
            ':recipient' => $recipient,
            ':subject' => $subject,
            ':status' => $status ? 'sent' : 'failed'
        ]);
    }

    public function isNotificationEnabled($type)
    {
        $typeMap = [
            'email' => 'email_notifications',
            'payslip' => 'payslip_alerts',
            'employee_update' => 'employee_updates'
        ];

        $key = $typeMap[$type] ?? null;
        return $key && ($this->settings[$key] ?? '0') === '1';
    }
}
