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

try {
    $dbName = 'payslip_generator';
    $backupDir = __DIR__ . '/../../../../storage/backups';
    
    // Create backup directory if not exists
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    // Generate filename
    $filename = 'backup_' . date('Y-m-d_His') . '.sql';
    $filepath = $backupDir . '/' . $filename;
    
    // Get MySQL connection details from database config
    $host = 'localhost';
    $user = 'root';
    $password = '';
    $socket = '/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock';
    
    // Build mysqldump command
    $command = sprintf(
        '/Applications/XAMPP/xamppfiles/bin/mysqldump --user=%s --socket=%s %s > %s 2>&1',
        escapeshellarg($user),
        escapeshellarg($socket),
        escapeshellarg($dbName),
        escapeshellarg($filepath)
    );
    
    // Execute backup
    exec($command, $output, $returnCode);
    
    if ($returnCode === 0 && file_exists($filepath) && filesize($filepath) > 0) {
        // Log audit
        $audit = new AuditLogger($conn);
        $audit->log($_SESSION['user_id'], 'system.backup', "Created database backup: $filename");
        
        echo json_encode([
            'success' => true,
            'message' => 'Backup created successfully',
            'filename' => $filename,
            'size' => filesize($filepath)
        ]);
    } else {
        // Delete failed backup file
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        error_log("Backup failed: " . implode("\n", $output));
        echo json_encode([
            'success' => false,
            'message' => 'Failed to create backup. Check mysqldump path.'
        ]);
    }
} catch (Exception $e) {
    error_log("Backup error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Backup error: ' . $e->getMessage()]);
}
?>
