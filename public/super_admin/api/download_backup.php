<?php
session_start();

require_once __DIR__ . '/../../../app/Config/database.php';
require_once __DIR__ . '/../../../app/Helpers/PermissionHelper.php';

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized');
}

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('system.backup')) {
    die('Permission denied');
}

$filename = $_GET['file'] ?? '';

if (empty($filename) || strpos($filename, '..') !== false) {
    die('Invalid filename');
}

$backupDir = __DIR__ . '/../../../../storage/backups';
$filepath = $backupDir . '/' . basename($filename);

if (!file_exists($filepath)) {
    die('File not found');
}

// Send file for download
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: public');

readfile($filepath);
exit;
?>
