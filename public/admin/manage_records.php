<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

// Admin-only access
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Config/database.php";
$db = getDBConnection();

$message = '';
$messageType = '';

// Handle delete action
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    try {
        $stmt = $db->prepare("DELETE FROM attendance_leave_details WHERE detail_id = :id");
        $stmt->execute([':id' => $_GET['delete']]);
        $message = "Record deleted successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $message = "Error deleting record: " . $e->getMessage();
        $messageType = "error";
    }
}

// Fetch all records
$filterMonth = $_GET['month'] ?? date('n');
$filterYear = $_GET['year'] ?? date('Y');

$sql = "SELECT ald.*, e.full_name, e.designation 
        FROM attendance_leave_details ald
        JOIN employees e ON ald.employee_id = e.employee_id
        WHERE YEAR(ald.start_date) = :year
        AND MONTH(ald.start_date) = :month
        ORDER BY ald.start_date DESC, e.full_name";

$stmt = $db->prepare($sql);
$stmt->execute([':year' => $filterYear, ':month' => $filterMonth]);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Attendance Records - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .action-btns {
            display: flex;
            gap: 8px;
        }
        .btn-icon {
            padding: 8px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 13px;
        }
        .btn-edit {
            background: #667eea;
            color: white;
        }
        .btn-edit:hover {
            background: #5568d3;
        }
        .btn-delete {
            background: #ef4444;
            color: white;
        }
        .btn-delete:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1>Manage Attendance Records</h1>
                <p>View, edit, and delete individual attendance/leave records.</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 25px; padding: 15px; border-radius: 10px; display: flex; align-items: center; gap: 10px; 
                    background: <?= $messageType === 'success' ? '#def7ec' : '#fde8e8' ?>; 
                    color: <?= $messageType === 'success' ? '#03543f' : '#9b1c1c' ?>;">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="glass-card" style="padding: 20px; margin-bottom: 20px;">
                <form method="GET" style="display: flex; gap: 15px; align-items: flex-end;">
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #4a5568; font-weight: 500;">Month</label>
                        <select name="month" class="form-control" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                <option value="<?= $m ?>" <?= $filterMonth == $m ? 'selected' : '' ?>>
                                    <?= date('F', mktime(0, 0, 0, $m, 1)) ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label style="display: block; margin-bottom: 5px; font-size: 13px; color: #4a5568; font-weight: 500;">Year</label>
                        <input type="number" name="year" value="<?= $filterYear ?>" class="form-control" style="padding: 10px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    </div>
                    <button type="submit" class="btn" style="padding: 10px 20px;">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <a href="add_attendance_record.php" class="btn" style="background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); padding: 10px 20px;">
                        <i class="fas fa-plus"></i> Add New
                    </a>
                </form>
            </div>

            <!-- Records Table -->
            <div class="glass-card">
                <div class="card-header" style="border-bottom: 1px solid #f0f0f0; padding: 20px;">
                    <h3 style="margin: 0; color: #2d3748; font-size: 18px;">
                        <i class="fas fa-list" style="margin-right: 10px; color: #667eea;"></i> 
                        Records for <?= date('F', mktime(0, 0, 0, $filterMonth, 1)) ?> <?= $filterYear ?>
                    </h3>
                </div>
                <div class="card-body" style="padding: 0;">
                    <?php if (empty($records)): ?>
                        <div style="text-align: center; padding: 60px 20px; color: #a0aec0;">
                            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px;"></i>
                            <p style="font-size: 16px;">No records found for this period.</p>
                        </div>
                    <?php else: ?>
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f7fafc; border-bottom: 2px solid #e2e8f0;">
                                    <th style="padding: 15px; text-align: left; font-size: 12px; color: #718096; text-transform: uppercase;">Employee</th>
                                    <th style="padding: 15px; text-align: left; font-size: 12px; color: #718096; text-transform: uppercase;">Type</th>
                                    <th style="padding: 15px; text-align: left; font-size: 12px; color: #718096; text-transform: uppercase;">Period</th>
                                    <th style="padding: 15px; text-align: center; font-size: 12px; color: #718096; text-transform: uppercase;">Days</th>
                                    <th style="padding: 15px; text-align: left; font-size: 12px; color: #718096; text-transform: uppercase;">Nature</th>
                                    <th style="padding: 15px; text-align: center; font-size: 12px; color: #718096; text-transform: uppercase;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 15px;">
                                            <strong><?= htmlspecialchars($record['full_name']) ?></strong><br>
                                            <span style="font-size: 12px; color: #718096;"><?= htmlspecialchars($record['designation']) ?></span>
                                        </td>
                                        <td style="padding: 15px;">
                                            <span style="background: #e0e7ff; color: #4338ca; padding: 4px 10px; border-radius: 12px; font-size: 12px; font-weight: 600;">
                                                <?= htmlspecialchars($record['leave_type']) ?>
                                            </span>
                                        </td>
                                        <td style="padding: 15px; font-size: 14px;">
                                            <?= date('M d', strtotime($record['start_date'])) ?> - <?= date('M d, Y', strtotime($record['end_date'])) ?>
                                        </td>
                                        <td style="padding: 15px; text-align: center; font-weight: 600;">
                                            <?= $record['total_days'] ?>
                                        </td>
                                        <td style="padding: 15px; font-size: 13px; color: #4a5568;">
                                            <?= htmlspecialchars($record['nature_of_leave'] ?? '---') ?>
                                            <?php if (!empty($record['remarks'])): ?>
                                                <br><span style="font-size: 11px; color: #9ca3af; font-style: italic;">Remarks: <?= htmlspecialchars($record['remarks']) ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 15px; text-align: center;">
                                            <div class="action-btns" style="justify-content: center;">
                                                <a href="add_attendance_record.php?id=<?= $record['detail_id'] ?>" class="btn-icon btn-edit" title="Edit">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="?delete=<?= $record['detail_id'] ?>&month=<?= $filterMonth ?>&year=<?= $filterYear ?>" 
                                                   class="btn-icon btn-delete" 
                                                   onclick="return confirm('Are you sure you want to delete this record?')" 
                                                   title="Delete">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
