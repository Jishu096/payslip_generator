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

require_once __DIR__ . '/../../app/Config/database.php';
$conn = getDBConnection();

// Get all departments with employee count
$query = "SELECT d.*, COUNT(e.employee_id) as employee_count 
          FROM departments d 
          LEFT JOIN employees e ON d.department_id = e.department_id 
          GROUP BY d.department_id 
          ORDER BY d.department_name";
$stmt = $conn->query($query);
$departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Director Dashboard</title>
    <?php include 'includes/director_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-building"></i> Departments</h1>
            <p>Organization departments and structure.</p>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-sitemap"></i> All Departments (<?php echo count($departments); ?>)</h3>
            </div>
            <div class="card-body">
                <div class="dept-grid">
                    <?php if (empty($departments)): ?>
                        <p style="text-align: center; padding: 40px; color: #a0aec0; grid-column: 1 / -1; width: 100%;">
                            No departments found
                        </p>
                    <?php else: ?>
                        <?php foreach ($departments as $dept): ?>
                            <div class="dept-card">
                                <div class="dept-header">
                                    <div class="dept-icon">
                                        <i class="fas fa-building"></i>
                                    </div>
                                    <div class="dept-info">
                                        <h3><?php echo htmlspecialchars($dept['department_name']); ?></h3>
                                        <p>Department ID: #<?php echo $dept['department_id']; ?></p>
                                    </div>
                                </div>
                                <div class="dept-stats">
                                    <div class="dept-stat">
                                        <div class="dept-stat-value"><?php echo $dept['employee_count']; ?></div>
                                        <div class="dept-stat-label">Employees</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
