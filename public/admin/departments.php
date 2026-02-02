<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);
$hasDirectorRole = in_array('director', $userRoles);
$hasSuperAdminRole = in_array('super_admin', $userRoles);
$canRestoreDepartments = $hasDirectorRole || $hasSuperAdminRole;

if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Models/Department.php';
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

$success = isset($_GET['success']);
$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$restored = isset($_GET['restored']);
$permanentlyDeleted = isset($_GET['permanently_deleted']);
$error = $_GET['error'] ?? '';
$showDeleted = isset($_GET['show_deleted']);

// Fetch all departments using the model (excludes soft-deleted)
$departmentModel = new Department($conn);
$departments = $showDeleted ? $departmentModel->getDeletedDepartments() : $departmentModel->getAllDepartments();

// Calculate stats
$totalDepartments = count($departments);
$totalEmployees = 0;
$maxEmployees = 0;
$avgEmployees = 0;

if (!$showDeleted && !empty($departments)) {
    foreach ($departments as $dept) {
        $totalEmployees += $dept['employee_count'] ?? 0;
        if (($dept['employee_count'] ?? 0) > $maxEmployees) {
            $maxEmployees = $dept['employee_count'];
        }
    }
    $avgEmployees = $totalDepartments > 0 ? round($totalEmployees / $totalDepartments, 1) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header h1 i {
            margin-right: 12px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .btn-add {
            background: white;
            color: #667eea;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.2);
        }

        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-box::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-box.purple::before { background: linear-gradient(90deg, #667eea, #764ba2); }
        .stat-box.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-box.green::before { background: linear-gradient(90deg, #10b981, #059669); }
        .stat-box.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        .stat-box-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-box-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-box-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .stat-box-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-box.purple .stat-box-icon { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-box.blue .stat-box-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-box.green .stat-box-icon { background: linear-gradient(135deg, #10b981, #059669); }
        .stat-box.orange .stat-box-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }

        /* Alert Styles */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
            border: 1px solid #10b981;
            color: #059669;
        }

        .alert-warning {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border: 1px solid #f59e0b;
            color: #d97706;
        }

        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
            border: 1px solid #ef4444;
            color: #dc2626;
        }

        .alert-info {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.1), rgba(37, 99, 235, 0.1));
            border: 1px solid #3b82f6;
            color: #2563eb;
        }

        /* Department Grid */
        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .dept-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            padding: 0;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dept-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.15);
        }

        .dept-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px;
            color: white;
            position: relative;
        }

        .dept-card-header.deleted {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }

        .dept-card-header-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .dept-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: #667eea;
            background: white;
        }

        .dept-card-header.deleted .dept-icon {
            color: #ef4444;
        }

        .dept-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .btn-action:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .btn-action.btn-restore {
            background: rgba(255,255,255,0.9);
            color: #10b981;
        }

        .btn-action.btn-permanent-delete {
            background: rgba(255,255,255,0.9);
            color: #ef4444;
        }

        .dept-card-header h3 {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
            color: white;
        }

        .dept-card-header .deleted-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            background: rgba(255,255,255,0.2);
            padding: 4px 10px;
            border-radius: 20px;
            margin-top: 8px;
        }

        .dept-card-body {
            padding: 25px;
        }

        .dept-stats-row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        .dept-stat-item {
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .dept-stat-item label {
            font-size: 12px;
            color: var(--muted);
            display: block;
            margin-bottom: 8px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .dept-stat-item strong {
            font-size: 24px;
            color: var(--text);
            font-weight: 700;
        }

        .dept-stat-item.highlight strong {
            color: #667eea;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .empty-state-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }

        .empty-state-icon i {
            font-size: 40px;
            color: #667eea;
        }

        .empty-state h3 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 10px;
            font-weight: 700;
        }

        .empty-state p {
            color: var(--muted);
            margin-bottom: 25px;
            font-size: 16px;
        }

        .empty-state .btn-add {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 30px;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .btn-add, .btn-secondary {
                width: 100%;
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .dept-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-building"></i> <?php echo $showDeleted ? 'Deleted Departments' : 'Departments'; ?></h1>
                <p><?php echo $showDeleted ? 'Restore or permanently remove deleted departments' : 'Manage organizational departments and structure'; ?></p>
            </div>
            <div class="header-actions">
                <?php if ($canRestoreDepartments): ?>
                    <?php if ($showDeleted): ?>
                        <a href="departments.php" class="btn-secondary">
                            <i class="fas fa-list"></i> Active Departments
                        </a>
                    <?php else: ?>
                        <a href="departments.php?show_deleted=1" class="btn-secondary">
                            <i class="fas fa-trash-restore"></i> View Deleted
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
                <?php if (!$showDeleted): ?>
                    <a href="add_department.php" class="btn-add">
                        <i class="fas fa-plus"></i> Add Department
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Alerts -->
        <?php if ($created): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Department created successfully.
            </div>
        <?php elseif ($restored): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Department restored successfully.
            </div>
        <?php elseif ($permanentlyDeleted): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Department permanently deleted from database.
            </div>
        <?php elseif ($updated): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Department updated successfully.
            </div>
        <?php elseif ($deleted): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Department deleted successfully.
            </div>
        <?php elseif ($error === 'name_exists'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Department name already exists.
            </div>
        <?php elseif ($error === 'has_employees'): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> Cannot delete department with employees. Reassign employees first.
            </div>
        <?php elseif ($error === 'unauthorized'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-lock"></i> Unauthorized: Only Directors and Super Admins can restore departments.
            </div>
        <?php elseif ($error === 'unauthorized_permanent'): ?>
            <div class="alert alert-danger">
                <i class="fas fa-lock"></i> Unauthorized: Only Super Admins can permanently delete departments.
            </div>
        <?php endif; ?>

        <?php if ($showDeleted && !$canRestoreDepartments): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> <strong>View Only Mode:</strong> You can view deleted departments, but only Directors and Super Admins can restore them.
            </div>
        <?php endif; ?>

        <!-- Department Cards -->
        <?php if (!empty($departments)): ?>
            <h2 class="section-title">
                <i class="fas fa-<?php echo $showDeleted ? 'trash-restore' : 'th-large'; ?>"></i> 
                <?php echo $showDeleted ? 'Deleted Departments' : 'All Departments'; ?>
                <span style="font-size: 14px; color: var(--muted); font-weight: 400; margin-left: 10px;">(<?php echo count($departments); ?>)</span>
            </h2>
            <div class="dept-grid">
                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card">
                        <div class="dept-card-header <?php echo $showDeleted ? 'deleted' : ''; ?>">
                            <div class="dept-card-header-top">
                                <div class="dept-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="dept-actions">
                                    <?php if ($showDeleted && $canRestoreDepartments): ?>
                                        <a href="../index.php?page=restore-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action btn-restore" title="Restore Department">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                        <?php if ($hasSuperAdminRole): ?>
                                            <a href="../index.php?page=permanently-delete-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action btn-permanent-delete confirm-permanent-delete" data-name="<?php echo htmlspecialchars($dept['department_name']); ?>" title="Permanently Delete">
                                                <i class="fas fa-trash-alt"></i>
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($showDeleted): ?>
                                        <span style="color: rgba(255,255,255,0.7); font-size: 12px; padding: 8px;">
                                            <i class="fas fa-lock"></i> Director Only
                                        </span>
                                    <?php else: ?>
                                        <a href="edit_department.php?id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action" title="Edit Department">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="../index.php?page=delete-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action confirm-delete" data-name="<?php echo htmlspecialchars($dept['department_name']); ?>" title="Delete Department">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                            <?php if ($showDeleted): ?>
                                <div class="deleted-badge">
                                    <i class="fas fa-trash"></i> Deleted
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="dept-card-body">
                            <div class="dept-stats-row">
                                <div class="dept-stat-item <?php echo !$showDeleted ? 'highlight' : ''; ?>">
                                    <label><?php echo $showDeleted ? 'Deleted By' : 'Employees'; ?></label>
                                    <strong><?php echo $showDeleted ? htmlspecialchars($dept['deleted_by_username'] ?? 'Unknown') : $dept['employee_count']; ?></strong>
                                </div>
                                <div class="dept-stat-item">
                                    <label><?php echo $showDeleted ? 'Deleted On' : 'Dept ID'; ?></label>
                                    <strong><?php echo $showDeleted ? date('M d', strtotime($dept['deleted_at'])) : '#' . $dept['department_id']; ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="empty-state-icon">
                    <i class="fas fa-<?php echo $showDeleted ? 'trash-restore' : 'building'; ?>"></i>
                </div>
                <h3><?php echo $showDeleted ? 'No Deleted Departments' : 'No Departments Yet'; ?></h3>
                <p><?php echo $showDeleted ? 'There are no deleted departments to restore' : 'Create your first department to organize your workforce'; ?></p>
                <?php if (!$showDeleted): ?>
                    <a href="add_department.php" class="btn-add">
                        <i class="fas fa-plus"></i> Create First Department
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Soft delete confirmation
        document.querySelectorAll('.confirm-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const deptName = this.getAttribute('data-name');
                if (confirm('Are you sure you want to delete the department "' + deptName + '"?\n\nThis action can be undone by Directors.')) {
                    window.location.href = this.href;
                }
            });
        });

        // Permanent delete confirmation (more serious warning)
        document.querySelectorAll('.confirm-permanent-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const deptName = this.getAttribute('data-name');
                if (confirm('⚠️ PERMANENT DELETE WARNING ⚠️\n\nAre you ABSOLUTELY SURE you want to PERMANENTLY delete "' + deptName + '"?\n\n❌ This will COMPLETELY REMOVE the department from the database.\n❌ This action CANNOT BE UNDONE.\n❌ All records will be lost forever.\n\nType "DELETE" to confirm or Cancel to abort.')) {
                    const confirmText = prompt('Type "DELETE" (in capital letters) to confirm permanent deletion:');
                    if (confirmText === 'DELETE') {
                        window.location.href = this.href;
                    } else {
                        alert('Permanent deletion cancelled. Department was NOT deleted.');
                    }
                }
            });
        });
    </script>

</body>
</html>
