<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

require_once __DIR__ . '/../../app/Models/Employee.php';
$employeeModel = new Employee();
$employees = $employeeModel->getAllEmployees();

$success = isset($_GET['success']);
$deleted = isset($_GET['deleted']);
$updated = isset($_GET['updated']);
$salary_pending = isset($_GET['salary_pending']);
$role_pending = isset($_GET['role_pending']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employees - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .employee-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            font-size: 20px;
            color: #2c3e50;
            flex: 1;
            min-width: 200px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            min-width: 200px;
            flex: 1;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .table-wrapper {
            overflow-x: auto;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }

        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;
            border-top: 1px solid #f0f0f0;
            color: #555;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }

        .action-btns {
            display: flex;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
        }

        .btn-edit {
            background: #3498db;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }
        
        .btn-sm i {
            pointer-events: none;
        }

        @media (max-width: 1200px) {
            th, td {
                padding: 12px 8px;
                font-size: 13px;
            }
            .btn-sm {
                padding: 5px 8px;
                min-width: 32px;
                height: 32px;
            }
        }

        @media (max-width: 768px) {
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            .table-header h2 {
                width: 100%;
                margin: 0;
            }
            .search-box {
                width: 100%;
                flex-direction: column;
            }
            .search-box input {
                width: 100%;
                min-width: unset;
            }
            .add-btn {
                width: 100%;
                justify-content: center;
            }
        }

        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-users"></i> Employees Management</h1>
            <p>View and manage all employee records</p>
        </div>

        <?php if ($success): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Employee added successfully.
            </div>
        <?php elseif ($updated && $salary_pending && $role_pending): ?>
            <div style="background:#fff8e1;border:1px solid #ffb74d;color:#e65100;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-clock"></i> Employee updated successfully. <strong>Salary change and role change requests sent to Director for approval.</strong>
            </div>
        <?php elseif ($updated && $salary_pending): ?>
            <div style="background:#fff8e1;border:1px solid #ffb74d;color:#e65100;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-clock"></i> Employee updated successfully. <strong>Salary change request sent to Director for approval.</strong>
            </div>
        <?php elseif ($updated && $role_pending): ?>
            <div style="background:#e8f5ff;border:1px solid #b3d9e8;color:#0c5377;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-clock"></i> Employee updated successfully. <strong>Role change request sent to Director for approval.</strong>
            </div>
        <?php elseif ($updated): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Employee updated successfully.
            </div>
        <?php elseif ($deleted): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Employee deleted successfully.
            </div>
        <?php elseif ($error === 'user_create_failed'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Employee saved, but user account creation failed.
            </div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Delete failed. Please try again.
            </div>
        <?php elseif ($error === 'update_failed'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Update failed. Please review the inputs.
            </div>
        <?php elseif ($error === 'missing_id' || $error === 'not_found'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Employee not found.
            </div>
        <?php endif; ?>

        <div class="employee-table">
            <div class="table-header">
                <h2>All Employees</h2>
                <div class="search-box">
                    <input type="text" placeholder="Search employees..." id="searchInput">
                    <a href="add_employee.php" class="add-btn">
                        <i class="fas fa-plus"></i> Add Employee
                    </a>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Department</th>
                        <th>Designation</th>
                        <th>Address</th>
                        <th>City / State</th>
                        <th>Emergency Contact</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($emp['employee_id']); ?></td>
                                <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($emp['email']); ?></td>
                                <td><?php echo htmlspecialchars($emp['phone']); ?></td>
                                <td><?php echo htmlspecialchars($emp['department_name'] ?? '—'); ?></td>
                                <td><?php echo htmlspecialchars($emp['designation']); ?></td>
                                <td><?php echo htmlspecialchars($emp['address'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars(($emp['city'] ?? '') . (isset($emp['state']) && $emp['state'] !== '' ? ' / ' . $emp['state'] : '')); ?></td>
                                <td><?php echo htmlspecialchars($emp['emergency_contact_phone'] ?? ''); ?></td>
                                <td><span class="badge badge-active">Active</span></td>
                                <td>
                                    <div class="action-btns">
                                        <a class="btn-sm btn-edit" href="edit_employee.php?id=<?php echo urlencode($emp['employee_id']); ?>">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a class="btn-sm btn-delete confirm-delete" href="../index.php?page=delete-employee&id=<?php echo urlencode($emp['employee_id']); ?>" data-name="<?php echo htmlspecialchars($emp['full_name']); ?>">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" style="text-align:center; padding:20px; color:#7f8c8d;">No employees found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const table = document.querySelector('table tbody');
        const rows = table.querySelectorAll('tr');

        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Delete confirmation
        document.querySelectorAll('.confirm-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var name = this.getAttribute('data-name') || 'this employee';
                if (!confirm('Are you sure you want to delete ' + name + '?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>
</html>
