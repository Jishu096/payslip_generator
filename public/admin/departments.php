<?php
session_start();

// Support both single-role and multi-role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';
$conn = getDBConnection();

$success = isset($_GET['success']);
$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = $_GET['error'] ?? '';

// Fetch all departments with employee count
$sql = "SELECT d.*, COUNT(e.employee_id) as employee_count 
        FROM departments d 
        LEFT JOIN employees e ON d.department_id = e.department_id 
        GROUP BY d.department_id 
        ORDER BY d.department_name ASC";
$stmt = $conn->prepare($sql);
$stmt->execute();
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --gradient-green: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-orange: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        body {
            font-family: "Roboto", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .page-header {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .page-header-left h1 {
            font-family: "Roboto", sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .page-header-left p {
            color: var(--text-tertiary);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-warning {
            background: #fff4e5;
            border: 1px solid #ffd8a8;
            color: #b35c00;
        }



        .btn-add {
            background: var(--gradient-primary);
            color: white;
            padding: 14px 28px;
            border-radius: 10px;
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
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 25px;
        }

        .dept-card {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 30px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .dept-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .dept-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .dept-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .dept-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: white;
            background: var(--gradient-primary);
        }

        .dept-actions {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
            color: white;
        }

        .btn-edit {
            background: #3498db;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
        }

        .dept-card h3 {
            font-family: "Roboto", sans-serif;
            font-size: 20px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .dept-description {
            color: var(--text-tertiary);
            font-size: 14px;
            margin-bottom: 20px;
            line-height: 1.6;
            min-height: 42px;
        }

        .dept-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            padding-top: 20px;
            border-top: 2px solid var(--border-color);
        }

        .stat-item {
            text-align: center;
        }

        .stat-item label {
            font-size: 12px;
            color: var(--text-tertiary);
            display: block;
            margin-bottom: 6px;
            text-transform: uppercase;
            font-weight: 600;
            letter-spacing: 0.5px;
        }

        .stat-item strong {
            font-size: 24px;
            color: var(--text-primary);
            font-family: "Roboto", sans-serif;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: var(--bg-primary);
            border-radius: 16px;
            border: 2px dashed var(--border-color);
        }

        .empty-state i {
            font-size: 72px;
            color: var(--text-tertiary);
            opacity: 0.3;
            margin-bottom: 20px;
            display: block;
        }

        .empty-state h3 {
            font-family: "Roboto", sans-serif;
            font-size: 24px;
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--text-tertiary);
            margin-bottom: 25px;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-add {
                width: 100%;
                justify-content: center;
            }

            .dept-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <div class="page-header-left">
                <h1><i class="fas fa-building"></i> Departments</h1>
                <p>Manage organizational departments and structure</p>
            </div>
            <a href="add_department.php" class="btn-add">
                <i class="fas fa-plus"></i> Add Department
            </a>
        </div>

        <?php if ($created): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Department created successfully.
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
                                <a href="edit_department.php?id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action btn-edit" title="Edit Department">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="../index.php?page=delete-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-action btn-delete confirm-delete" data-name="<?php echo htmlspecialchars($dept['department_name']); ?>" title="Delete Department">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                        <p class="dept-description"><?php echo htmlspecialchars($dept['description'] ?? 'No description available'); ?></p>
                        <div class="dept-stats">
                            <div class="stat-item">
                                <label>Employees</label>
                                <strong><?php echo $dept['employee_count']; ?></strong>
                            </div>
                            <div class="stat-item">
                                <label>Dept ID</label>
                                <strong>#<?php echo $dept['department_id']; ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Departments Yet</h3>
                <p>Create your first department to organize your workforce</p>
                <a href="add_department.php" class="btn-add">
                    <i class="fas fa-plus"></i> Create First Department
                </a>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

</body>
</html>
