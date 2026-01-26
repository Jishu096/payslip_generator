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

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';

$employeeModel = new Employee();
$db = getDBConnection();

// Get all employees with department info
$query = "SELECT e.*, d.department_name 
          FROM employees e 
          LEFT JOIN departments d ON e.department_id = d.department_id 
          ORDER BY e.full_name ASC";
$stmt = $db->query($query);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get departments for filter
$deptStmt = $db->query("SELECT * FROM departments ORDER BY department_name");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Employees - Director Dashboard</title>
    <?php include 'includes/director_styles.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'includes/director_navbar.php'; ?>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Employee Directory</h1>
            <p>View all employees in the organization.</p>
        </div>

        <div class="live-stats-container">
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-blue text-white">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo count($employees); ?></h4>
                    <span>Total Employees</span>
                </div>
            </div>
            
            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-teal text-white">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo count(array_filter($employees, fn($e) => $e['status'] === 'active')); ?></h4>
                    <span>Active Employees</span>
                </div>
            </div>

            <div class="live-stat-item">
                <div class="live-stat-icon bg-gradient-purple text-white">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-text">
                    <h4><?php echo count($departments); ?></h4>
                    <span>Departments</span>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><i class="fas fa-list"></i> Employee List</h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Department</th>
                                <th>Designation</th>
                                <th>Status</th>
                                <th>Salary</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($employees)): ?>
                                <tr>
                                    <td colspan="7" style="text-align: center; padding: 40px; color: #a0aec0;">
                                        <i class="fas fa-users" style="font-size: 48px; opacity: 0.2; margin-bottom: 20px;"></i>
                                        <p>No employees found</p>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($employees as $emp): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($emp['employee_id']); ?></strong></td>
                                        <td>
                                            <div style="font-weight: 500; color: #2d3748;"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                        <td>
                                            <span class="badge badge-primary"><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                                        <td>
                                            <?php if ($emp['status'] === 'active'): ?>
                                                <span class="badge badge-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge badge-warning">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-family: monospace; font-weight: 600;">₹<?php echo number_format($emp['basic_salary'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
