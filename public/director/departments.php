<?php
session_start();

// Check if user has director role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Director';

require_once __DIR__ . '/../../app/Models/Department.php';
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$restored = isset($_GET['restored']);
$error = $_GET['error'] ?? '';
$showDeleted = isset($_GET['show_deleted']);

// Fetch departments using the model
$departmentModel = new Department($conn);
$departments = $showDeleted ? $departmentModel->getDeletedDepartments() : $departmentModel->getAllDepartments();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Departments - Director Portal</title>
    <?php include 'includes/director_styles.php'; ?>
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        .page-header-left h1 {
            font-size: 28px;
            color: var(--text);
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header-left p {
            color: var(--muted);
            font-size: 14px;
        }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border-radius: 10px;
            border: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid var(--success);
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border-left: 4px solid #3b82f6;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid var(--danger);
        }

        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .dept-card {
            background: var(--card);
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .dept-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.12);
        }

        .dept-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .dept-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .dept-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .btn-restore {
            background: var(--success);
            color: white;
        }

        .btn-restore:hover {
            background: #059669;
            transform: scale(1.1);
        }

        .btn-delete {
            background: var(--danger);
            color: white;
        }

        .btn-delete:hover {
            background: #dc2626;
            transform: scale(1.1);
        }

        .dept-card h3 {
            font-size: 18px;
            color: var(--text);
            margin-bottom: 10px;
        }

        .dept-stats {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
        }

        .stat-item {
            text-align: center;
        }

        .stat-item label {
            display: block;
            font-size: 12px;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .stat-item strong {
            font-size: 18px;
            color: var(--accent);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.3;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text);
        }

        .badge-deleted {
            display: inline-block;
            padding: 3px 10px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 5px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 8px;
        }

        @media (max-width: 768px) {
            .dept-grid {
                grid-template-columns: 1fr;
            }

            .btn-group {
                flex-direction: column;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content" id="mainContent">

        <div class="content-area">
            <div class="page-header">
                <div class="page-header-left">
                    <h1><i class="fas fa-building"></i> Departments</h1>
                    <p>View and manage organizational departments</p>
                </div>
                <div class="btn-group">
                    <?php if ($showDeleted): ?>
                        <a href="departments.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> Active Departments
                        </a>
                    <?php else: ?>
                        <a href="departments.php?show_deleted=1" class="btn btn-secondary">
                            <i class="fas fa-trash-restore"></i> Deleted Departments
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($created): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Department created successfully.
                </div>
            <?php elseif ($restored): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Department restored successfully.
                </div>
            <?php elseif ($updated): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Department updated successfully.
                </div>
            <?php elseif ($deleted): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Department deleted successfully.
                </div>
            <?php elseif ($error === 'has_employees'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Cannot delete department with employees. Reassign employees first.
                </div>
            <?php elseif ($error === 'unauthorized'): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-lock"></i> Unauthorized action.
                </div>
            <?php endif; ?>

            <?php if ($showDeleted): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> <strong>Deleted Departments:</strong> You can restore departments that were previously deleted.
                </div>
            <?php endif; ?>

            <?php if (!empty($departments)): ?>
                <div class="dept-grid">
                    <?php foreach ($departments as $dept): ?>
                        <div class="dept-card">
                            <div class="dept-header">
                                <div class="dept-icon">
                                    <i class="fas fa-building"></i>
                                </div>
                                <div class="dept-actions">
                                    <?php if ($showDeleted): ?>
                                        <a href="../index.php?page=restore-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action btn-restore" title="Restore Department">
                                            <i class="fas fa-undo"></i>
                                        </a>
                                    <?php else: ?>
                                        <a href="../index.php?page=delete-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action btn-delete confirm-delete" data-name="<?php echo htmlspecialchars($dept['department_name']); ?>" title="Delete Department">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <h3>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                                <?php if ($showDeleted): ?>
                                    <span class="badge-deleted">
                                        <i class="fas fa-trash"></i> Deleted
                                    </span>
                                <?php endif; ?>
                            </h3>
                            <div class="dept-stats">
                                <div class="stat-item">
                                    <label><?php echo $showDeleted ? 'Deleted By' : 'Employees'; ?></label>
                                    <strong><?php echo $showDeleted ? htmlspecialchars($dept['deleted_by_username'] ?? 'Unknown') : $dept['employee_count']; ?></strong>
                                </div>
                                <div class="stat-item">
                                    <label><?php echo $showDeleted ? 'Deleted On' : 'Dept ID'; ?></label>
                                    <strong><?php echo $showDeleted ? date('M d, Y', strtotime($dept['deleted_at'])) : '#' . $dept['department_id']; ?></strong>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3><?php echo $showDeleted ? 'No Deleted Departments' : 'No Departments Available'; ?></h3>
                    <p><?php echo $showDeleted ? 'There are no deleted departments to restore' : 'No departments found in the system'; ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/director_scripts.php'; ?>

    <script>
        // Delete confirmation
        document.querySelectorAll('.confirm-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const deptName = this.getAttribute('data-name');
                if (confirm('Are you sure you want to delete the department "' + deptName + '"?\n\nThis can be restored later by Directors.')) {
                    window.location.href = this.href;
                }
            });
        });
    </script>

</body>
</html>
