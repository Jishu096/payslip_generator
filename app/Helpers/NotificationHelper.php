<?php

require_once __DIR__ . '/EmailHelper.php';

class NotificationHelper
{
    private $conn;
    private $settings;
    private $emailHelper;

    public function __construct($conn)
    {
        $this->conn = $conn;
        $this->loadSettings();
        $this->emailHelper = new EmailHelper($conn);
    }

    private function loadSettings()
    {
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

    public function sendEmailNotification($to, $subject, $message)
    {
        // Use EmailHelper which handles SMTP
        return $this->emailHelper->sendEmail($to, $subject, $message);
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

    public function notifyLeaveSubmitted($adminEmail, $employeeName, $leaveType, $startDate, $endDate)
    {
        // Only send if leave notifications are enabled
        if (($this->settings['leave_notifications'] ?? '0') !== '1') {
            return false;
        }

        $subject = "New Leave Request from {$employeeName}";
        $message = "
            <html>
            <body>
                <h2>New Leave Request Submitted</h2>
                <p>A new leave request has been submitted:</p>
                <ul>
                    <li><strong>Employee:</strong> {$employeeName}</li>
                    <li><strong>Leave Type:</strong> {$leaveType}</li>
                    <li><strong>From:</strong> {$startDate}</li>
                    <li><strong>To:</strong> {$endDate}</li>
                </ul>
                <p>Please review and process this request in the admin panel.</p>
                <br>
                <p>Best regards,<br>" . ($this->settings['company_name'] ?? 'e-HRMS') . "</p>
            </body>
            </html>
        ";

        return $this->sendEmailNotification($adminEmail, $subject, $message);
    }

    public function notifyLeaveApproved($employeeEmail, $employeeName, $leaveType, $startDate, $endDate, $comments = '')
    {
        // Only send if leave notifications are enabled
        if (($this->settings['leave_notifications'] ?? '0') !== '1') {
            return false;
        }

        $subject = "Leave Request Approved";
        $commentsHtml = $comments ? "<li><strong>Comments:</strong> {$comments}</li>" : "";
        $message = "
            <html>
            <body>
                <h2 style='color: #10b981;'>Leave Request Approved ✓</h2>
                <p>Dear {$employeeName},</p>
                <p>Your leave request has been <strong>approved</strong>:</p>
                <ul>
                    <li><strong>Leave Type:</strong> {$leaveType}</li>
                    <li><strong>From:</strong> {$startDate}</li>
                    <li><strong>To:</strong> {$endDate}</li>
                    {$commentsHtml}
                </ul>
                <p>Enjoy your time off!</p>
                <br>
                <p>Best regards,<br>" . ($this->settings['company_name'] ?? 'e-HRMS') . "</p>
            </body>
            </html>
        ";

        return $this->sendEmailNotification($employeeEmail, $subject, $message);
    }

    public function notifyLeaveRejected($employeeEmail, $employeeName, $leaveType, $startDate, $endDate, $reason)
    {
        // Only send if leave notifications are enabled
        if (($this->settings['leave_notifications'] ?? '0') !== '1') {
            return false;
        }

        $subject = "Leave Request Rejected";
        $message = "
            <html>
            <body>
                <h2 style='color: #ef4444;'>Leave Request Rejected</h2>
                <p>Dear {$employeeName},</p>
                <p>Unfortunately, your leave request has been <strong>rejected</strong>:</p>
                <ul>
                    <li><strong>Leave Type:</strong> {$leaveType}</li>
                    <li><strong>From:</strong> {$startDate}</li>
                    <li><strong>To:</strong> {$endDate}</li>
                    <li><strong>Reason:</strong> {$reason}</li>
                </ul>
                <p>Please contact HR if you have any questions.</p>
                <br>
                <p>Best regards,<br>" . ($this->settings['company_name'] ?? 'e-HRMS') . "</p>
            </body>
            </html>
        ";

        return $this->sendEmailNotification($employeeEmail, $subject, $message);
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
            'employee_update' => 'employee_updates',
            'leave' => 'leave_notifications'
        ];

        $key = $typeMap[$type] ?? null;
        return $key && ($this->settings[$key] ?? '0') === '1';
    }
}
