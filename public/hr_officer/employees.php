<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/PermissionHelper.php';

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('employee.view')) {
    header("Location: ../auth/login.php?error=unauthorized");
    exit;
}

$username = $_SESSION['username'] ?? 'HR Officer';

// Fetch all employees
$stmt = $conn->query("
    SELECT e.*, d.department_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.department_id
    ORDER BY e.employee_id DESC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch departments for dropdown
$deptStmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name");
$departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - HR Officer</title>
    <?php include 'includes/hr_styles.php'; ?>
    <style>
        .search-filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
        }
        .search-box {
            flex: 1;
            position: relative;
        }
        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
        }
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-sm {
            padding: 6px 12px;
            font-size: 13px;
        }
        .employees-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .employees-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .employees-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        .employees-table td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .employees-table tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active {
            background: #d1fae5;
            color: #065f46;
        }
        .badge-inactive {
            background: #fee2e2;
            color: #991b1b;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background: white;
            margin: 3% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .modal-header h3 {
            color: #1e293b;
            font-size: 22px;
        }
        .close {
            font-size: 28px;
            font-weight: bold;
            color: #64748b;
            cursor: pointer;
        }
        .close:hover {
            color: #ef4444;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_navbar.php'; ?>
    
    <div class="container">
        <?php include 'includes/hr_sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-users"></i> Employee Management</h1>
                <p>Manage employee records, add new employees, and update information</p>
            </div>

            <div id="alertBox" class="alert"></div>

            <div class="search-filter-bar">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search employees by name, email, or department...">
                </div>
                <select id="statusFilter" style="padding: 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px;">
                    <option value="">All Status</option>
                    <option value="Active">Active</option>
                    <option value="Inactive">Inactive</option>
                </select>
                <?php if ($perm->hasPermission('employee.create')): ?>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Employee
                </button>
                <?php endif; ?>
            </div>

            <div class="employees-table">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Department</th>
                            <th>Position</th>
                            <th>Joining Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="employeeTableBody">
                        <?php foreach ($employees as $emp): ?>
                        <tr data-name="<?php echo htmlspecialchars($emp['full_name']); ?>" 
                            data-email="<?php echo htmlspecialchars($emp['email']); ?>" 
                            data-dept="<?php echo htmlspecialchars($emp['department_name'] ?? ''); ?>"
                            data-status="<?php echo htmlspecialchars($emp['status']); ?>">
                            <td><strong><?php echo $emp['employee_id']; ?></strong></td>
                            <td><?php echo htmlspecialchars($emp['full_name']); ?></td>
                            <td><?php echo htmlspecialchars($emp['email']); ?></td>
                            <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($emp['position'] ?? 'N/A'); ?></td>
                            <td><?php echo date('M d, Y', strtotime($emp['joining_date'])); ?></td>
                            <td>
                                <span class="badge badge-<?php echo $emp['status'] === 'Active' ? 'active' : 'inactive'; ?>">
                                    <?php echo htmlspecialchars($emp['status']); ?>
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-success btn-sm" onclick="viewEmployee(<?php echo $emp['employee_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($perm->hasPermission('employee.update')): ?>
                                <button class="btn btn-primary btn-sm" onclick="editEmployee(<?php echo $emp['employee_id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Employee Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add New Employee</h3>
                <span class="close" onclick="closeEmployeeModal()">&times;</span>
            </div>
            <form id="employeeForm">
                <input type="hidden" name="employee_id" id="employeeId">
                <div class="form-row">
                    <div class="form-group">
                        <label>Full Name *</label>
                        <input type="text" name="full_name" id="fullName" required>
                    </div>
                    <div class="form-group">
                        <label>Email *</label>
                        <input type="email" name="email" id="email" required>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" name="phone_number" id="phoneNumber">
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" name="date_of_birth" id="dateOfBirth">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Department *</label>
                        <select name="department_id" id="departmentId" required>
                            <option value="">-- Select Department --</option>
                            <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>">
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" id="position">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Joining Date *</label>
                        <input type="date" name="joining_date" id="joiningDate" required>
                    </div>
                    <div class="form-group">
                        <label>Basic Salary</label>
                        <input type="number" name="basic_salary" id="basicSalary" step="0.01">
                    </div>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" id="address" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label>Gender</label>
                        <select name="gender" id="gender">
                            <option value="">-- Select --</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" id="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> <span id="submitBtnText">Add Employee</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Employee Modal -->
    <div id="viewEmployeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user"></i> Employee Details</h3>
                <span class="close" onclick="closeViewModal()">&times;</span>
            </div>
            <div id="employeeDetails"></div>
        </div>
    </div>

    <?php include 'includes/hr_scripts.php'; ?>
    <script>
        // Search and filter
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const tableRows = document.querySelectorAll('#employeeTableBody tr');

        function filterTable() {
            const searchTerm = searchInput.value.toLowerCase();
            const statusValue = statusFilter.value;

            tableRows.forEach(row => {
                const name = row.dataset.name.toLowerCase();
                const email = row.dataset.email.toLowerCase();
                const dept = row.dataset.dept.toLowerCase();
                const status = row.dataset.status;

                const matchesSearch = name.includes(searchTerm) || email.includes(searchTerm) || dept.includes(searchTerm);
                const matchesStatus = !statusValue || status === statusValue;

                row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
            });
        }

        searchInput.addEventListener('input', filterTable);
        statusFilter.addEventListener('change', filterTable);

        // Modal functions
        function openAddModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add New Employee';
            document.getElementById('submitBtnText').textContent = 'Add Employee';
            document.getElementById('employeeForm').reset();
            document.getElementById('employeeId').value = '';
            document.getElementById('employeeModal').style.display = 'block';
        }

        function closeEmployeeModal() {
            document.getElementById('employeeModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewEmployeeModal').style.display = 'none';
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Form submission
        document.getElementById('employeeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const employeeId = document.getElementById('employeeId').value;
            const endpoint = employeeId ? 'api/update_employee.php' : 'api/create_employee.php';

            try {
                const response = await fetch(endpoint, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    showAlert(result.message || 'Operation successful!', 'success');
                    closeEmployeeModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Operation failed', 'error');
                }
            } catch (error) {
                showAlert('An error occurred', 'error');
            }
        });

        async function viewEmployee(employeeId) {
            try {
                const response = await fetch(`api/get_employee.php?id=${employeeId}`);
                const result = await response.json();

                if (result.success) {
                    const emp = result.employee;
                    document.getElementById('employeeDetails').innerHTML = `
                        <div style="display: grid; gap: 15px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <strong style="color: #64748b;">Employee ID:</strong>
                                    <p style="margin-top: 5px;">${emp.employee_id}</p>
                                </div>
                                <div>
                                    <strong style="color: #64748b;">Full Name:</strong>
                                    <p style="margin-top: 5px;">${emp.full_name}</p>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <strong style="color: #64748b;">Email:</strong>
                                    <p style="margin-top: 5px;">${emp.email}</p>
                                </div>
                                <div>
                                    <strong style="color: #64748b;">Phone:</strong>
                                    <p style="margin-top: 5px;">${emp.phone_number || 'N/A'}</p>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <strong style="color: #64748b;">Department:</strong>
                                    <p style="margin-top: 5px;">${emp.department_name || 'N/A'}</p>
                                </div>
                                <div>
                                    <strong style="color: #64748b;">Position:</strong>
                                    <p style="margin-top: 5px;">${emp.position || 'N/A'}</p>
                                </div>
                            </div>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                                <div>
                                    <strong style="color: #64748b;">Joining Date:</strong>
                                    <p style="margin-top: 5px;">${new Date(emp.joining_date).toLocaleDateString()}</p>
                                </div>
                                <div>
                                    <strong style="color: #64748b;">Status:</strong>
                                    <p style="margin-top: 5px;"><span class="badge badge-${emp.status === 'Active' ? 'active' : 'inactive'}">${emp.status}</span></p>
                                </div>
                            </div>
                            ${emp.basic_salary ? `
                            <div>
                                <strong style="color: #64748b;">Basic Salary:</strong>
                                <p style="margin-top: 5px;">₹${parseFloat(emp.basic_salary).toLocaleString()}</p>
                            </div>
                            ` : ''}
                            ${emp.address ? `
                            <div>
                                <strong style="color: #64748b;">Address:</strong>
                                <p style="margin-top: 5px;">${emp.address}</p>
                            </div>
                            ` : ''}
                        </div>
                    `;
                    document.getElementById('viewEmployeeModal').style.display = 'block';
                } else {
                    showAlert('Failed to load employee details', 'error');
                }
            } catch (error) {
                showAlert('An error occurred', 'error');
            }
        }

        async function editEmployee(employeeId) {
            try {
                const response = await fetch(`api/get_employee.php?id=${employeeId}`);
                const result = await response.json();

                if (result.success) {
                    const emp = result.employee;
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Employee';
                    document.getElementById('submitBtnText').textContent = 'Update Employee';
                    document.getElementById('employeeId').value = emp.employee_id;
                    document.getElementById('fullName').value = emp.full_name;
                    document.getElementById('email').value = emp.email;
                    document.getElementById('phoneNumber').value = emp.phone_number || '';
                    document.getElementById('dateOfBirth').value = emp.date_of_birth || '';
                    document.getElementById('departmentId').value = emp.department_id;
                    document.getElementById('position').value = emp.position || '';
                    document.getElementById('joiningDate').value = emp.joining_date;
                    document.getElementById('basicSalary').value = emp.basic_salary || '';
                    document.getElementById('address').value = emp.address || '';
                    document.getElementById('gender').value = emp.gender || '';
                    document.getElementById('status').value = emp.status;
                    document.getElementById('employeeModal').style.display = 'block';
                } else {
                    showAlert('Failed to load employee data', 'error');
                }
            } catch (error) {
                showAlert('An error occurred', 'error');
            }
        }

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = 'alert alert-' + type;
            alertBox.style.display = 'block';
            setTimeout(() => alertBox.style.display = 'none', 5000);
        }
    </script>
</body>
</html>
