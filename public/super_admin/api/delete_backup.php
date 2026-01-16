<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../app/Config/database.php';
require_once __DIR__ . '/../../../app/Helpers/PermissionHelper.php';
require_once __DIR__ . '/../../../app/Helpers/AuditLogger.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('system.backup')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$filename = $_POST['filename'] ?? '';

if (empty($filename) || strpos($filename, '..') !== false) {
    echo json_encode(['success' => false, 'message' => 'Invalid filename']);
    exit;
}

try {
    $backupDir = __DIR__ . '/../../../../storage/backups';
    $filepath = $backupDir . '/' . basename($filename);
    
    if (!file_exists($filepath)) {
        echo json_encode(['success' => false, 'message' => 'Backup file not found']);
        exit;
    }
    
    // Delete file
    if (unlink($filepath)) {
        // Log audit
        $audit = new AuditLogger($conn);
        $audit->log($_SESSION['user_id'], 'system.backup', "Deleted database backup: $filename");
        
        echo json_encode(['success' => true, 'message' => 'Backup deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete backup file']);
    }
} catch (Exception $e) {
    error_log("Delete backup error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error deleting backup']);
}
?>
