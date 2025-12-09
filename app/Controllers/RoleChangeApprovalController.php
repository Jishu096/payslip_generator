<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../public/auth/login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../public/director/role_approvals.php");
    exit;
}

require_once __DIR__ . '/../Config/database.php';
require_once __DIR__ . '/../Helpers/NotificationHelper.php';

$db = getDBConnection();
$notificationHelper = new NotificationHelper($db);

$action = $_POST['action'] ?? '';
$request_id = $_POST['request_id'] ?? 0;
$comments = $_POST['comments'] ?? '';
$reviewer_id = $_SESSION['user_id'] ?? 0;
$reviewer_name = $_SESSION['username'] ?? 'Director';

if (!$request_id || !in_array($action, ['approve', 'reject'])) {
    header("Location: ../public/director/role_approvals.php?error=invalid_request");
    exit;
}

try {
    // Fetch the role change request
    $stmt = $db->prepare("
        SELECT rcr.*, e.email as employee_email
        FROM role_change_requests rcr
        JOIN employees e ON rcr.employee_id = e.employee_id
        WHERE rcr.request_id = ? AND rcr.status = 'pending'
    ");
    $stmt->execute([$request_id]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        header("Location: ../public/director/role_approvals.php?error=request_not_found");
        exit;
    }

    $db->beginTransaction();

    if ($action === 'approve') {
        // Update role change request status
        $stmt = $db->prepare("
            UPDATE role_change_requests 
            SET status = 'approved',
                reviewed_by = ?,
                reviewed_by_name = ?,
                review_date = NOW(),
                review_comments = ?
            WHERE request_id = ?
        ");
        $stmt->execute([$reviewer_id, $reviewer_name, $comments, $request_id]);

        // Update user role
        $stmt = $db->prepare("
            UPDATE users 
            SET role = ?
            WHERE employee_id = ?
        ");
        $stmt->execute([$request['new_role'], $request['employee_id']]);

        $db->commit();

        // Send notification to the admin who requested the change
        if ($notificationHelper->isNotificationEnabled('employee_updates')) {
            $subject = "Role Change Request Approved";
            $message = "
                <h2>Role Change Request Approved</h2>
                <p>The role change request for <strong>{$request['employee_name']}</strong> has been approved.</p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li>Previous Role: <strong>" . ucfirst($request['old_role']) . "</strong></li>
                    <li>New Role: <strong>" . ucfirst($request['new_role']) . "</strong></li>
                    <li>Reason: {$request['change_reason']}</li>
                    <li>Approved By: {$reviewer_name}</li>
                    <li>Approval Date: " . date('d M Y, h:i A') . "</li>
                </ul>
                " . ($comments ? "<p><strong>Director Comments:</strong> {$comments}</p>" : "") . "
                <p>The employee's role has been updated in the system.</p>
            ";
            
            // Get requester email from users table
            $stmt = $db->prepare("SELECT email FROM users WHERE user_id = ?");
            $stmt->execute([$request['requested_by']]);
            $requester = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($requester && $requester['email']) {
                $notificationHelper->sendEmailNotification(
                    $requester['email'],
                    $subject,
                    $message
                );
            }
        }

        header("Location: ../public/director/role_approvals.php?approved=1");
        exit;

    } else if ($action === 'reject') {
        // Update role change request status
        $stmt = $db->prepare("
            UPDATE role_change_requests 
            SET status = 'rejected',
                reviewed_by = ?,
                reviewed_by_name = ?,
                review_date = NOW(),
                review_comments = ?
            WHERE request_id = ?
        ");
        $stmt->execute([$reviewer_id, $reviewer_name, $comments, $request_id]);

        $db->commit();

        // Send notification to the admin who requested the change
        if ($notificationHelper->isNotificationEnabled('employee_updates')) {
            $subject = "Role Change Request Rejected";
            $message = "
                <h2>Role Change Request Rejected</h2>
                <p>The role change request for <strong>{$request['employee_name']}</strong> has been rejected.</p>
                <p><strong>Details:</strong></p>
                <ul>
                    <li>Current Role: <strong>" . ucfirst($request['old_role']) . "</strong></li>
                    <li>Requested Role: <strong>" . ucfirst($request['new_role']) . "</strong></li>
                    <li>Reason for Request: {$request['change_reason']}</li>
                    <li>Rejected By: {$reviewer_name}</li>
                    <li>Rejection Date: " . date('d M Y, h:i A') . "</li>
                </ul>
                <p><strong>Reason for Rejection:</strong> {$comments}</p>
                <p>Please review the rejection reason and make necessary adjustments if needed.</p>
            ";
            
            // Get requester email from users table
            $stmt = $db->prepare("SELECT email FROM users WHERE user_id = ?");
            $stmt->execute([$request['requested_by']]);
            $requester = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($requester && $requester['email']) {
                $notificationHelper->sendEmailNotification(
                    $requester['email'],
                    $subject,
                    $message
                );
            }
        }

        header("Location: ../public/director/role_approvals.php?rejected=1");
        exit;
    }

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Role change approval error: " . $e->getMessage());
    header("Location: ../public/director/role_approvals.php?error=database_error");
    exit;
}
