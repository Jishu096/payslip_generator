<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Models/Employee.php';

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

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
    <title>Departments - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .dept-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .dept-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            padding: 25px;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .dept-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .dept-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
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
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .dept-actions {
            display: flex;
            gap: 8px;
        }

        .btn-sm {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            color: white;
            text-decoration: none;
            font-size: 14px;
        }

        .btn-edit {
            background: #3498db;
        }

        .btn-delete {
            background: #e74c3c;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .dept-card h3 {
            font-size: 18px;
            color: #2c3e50;
            margin: 15px 0 8px;
        }

        .dept-description {
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 15px;
            min-height: 30px;
        }

        .dept-stats {
            display: flex;
            gap: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
        }

        .stat-item {
            flex: 1;
        }

        .stat-item label {
            font-size: 11px;
            color: #95a5a6;
            display: block;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .stat-item strong {
            font-size: 18px;
            color: #2c3e50;
        }

        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }

        .empty-state i {
            font-size: 64px;
            color: #bdc3c7;
            margin-bottom: 20px;
            display: block;
        }

        @media (max-width: 768px) {
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

        <?php if ($created): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Department created successfully.
            </div>
        <?php elseif ($updated): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Department updated successfully.
            </div>
        <?php elseif ($deleted): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Department deleted successfully.
            </div>
        <?php elseif ($error === 'name_exists'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Department name already exists.
            </div>
        <?php elseif ($error === 'has_employees'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Cannot delete department with employees. Reassign employees first.
            </div>
        <?php endif; ?>

        <a href="add_department.php" class="add-btn">
            <i class="fas fa-plus"></i> Add Department
        </a>

        <?php if (!empty($departments)): ?>
            <div class="dept-grid">
                <?php foreach ($departments as $dept): ?>
                    <div class="dept-card">
                        <div class="dept-header">
                            <div class="dept-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div class="dept-actions">
                                <a href="edit_department.php?id=<?php echo urlencode($dept['department_id']); ?>" class="btn-sm btn-edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <a href="../index.php?page=delete-department&id=<?php echo urlencode($dept['department_id']); ?>" class="btn-sm btn-delete confirm-delete" data-name="<?php echo htmlspecialchars($dept['department_name']); ?>" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                        <p class="dept-description"><?php echo htmlspecialchars($dept['description'] ?? 'No description'); ?></p>
                        <div class="dept-stats">
                            <div class="stat-item">
                                <label>Employees</label>
                                <strong><?php echo $dept['employee_count']; ?></strong>
                            </div>
                            <div class="stat-item">
                                <label>Dept ID</label>
                                <strong><?php echo $dept['department_id']; ?></strong>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Departments</h3>
                <p>Create your first department to get started.</p>
                <a href="add_department.php" class="add-btn">
                    <i class="fas fa-plus"></i> Create Department
                </a>
            </div>
        <?php endif; ?>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        document.querySelectorAll('.confirm-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var name = this.getAttribute('data-name') || 'this department';
                if (!confirm('Are you sure you want to delete ' + name + '?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>
</html>
