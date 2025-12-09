<?php
// === attendance.php ===
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
header("Location: login.php"); exit;
}
require_once "../../backend/models/Attendance.php";
a$ = new Attendance();
$rows = $a$->getAttendanceByEmployee($_SESSION['employee_id']);
?>
<!DOCTYPE html>
<html><head><title>Attendance</title></head><body>
<h2>Attendance Records</h2>
<table border="1" cellpadding="5">
<tr><th>Date</th><th>Status</th></tr>
<?php foreach ($rows as $r): ?>
<tr><td><?= $r['date'] ?></td><td><?= $r['status'] ?></td></tr>
<?php endforeach; ?>
</table>
</body></html>