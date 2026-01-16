<?php
session_start();

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr_officer') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'HR Officer';
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get filter parameters
$departmentFilter = $_GET['department'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$typeFilter = $_GET['type'] ?? '';
$searchQuery = $_GET['search'] ?? '';

// Build query
$sql = "SELECT 
            e.employee_id,
            e.full_name,
            e.designation,
            e.email,
            e.phone,
            e.join_date,
            e.status,
            e.employee_type,
            e.basic_salary,
            d.department_name,
            d.department_id
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.deleted_at IS NULL";

$params = [];

if ($departmentFilter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $departmentFilter;
}

if ($statusFilter) {
    $sql .= " AND e.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter) {
    $sql .= " AND e.employee_type = ?";
    $params[] = $typeFilter;
}

if ($searchQuery) {
    $sql .= " AND (e.full_name LIKE ? OR e.email LIKE ? OR e.designation LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$sql .= " ORDER BY e.full_name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
    $error = "Error fetching employees: " . $e->getMessage();
}

// Get departments for filter
try {
    $deptStmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

// Get stats
try {
    $statsStmt = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
            SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
            SUM(CASE WHEN status = 'on_leave' THEN 1 ELSE 0 END) as on_leave
        FROM employees
        WHERE deleted_at IS NULL
    ");
    $stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'on_leave' => 0];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Records - HR Officer</title>
    <?php include 'includes/hr_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.active { border-left-color: #10b981; }
        .stat-card.inactive { border-left-color: #94a3b8; }
        .stat-card.on-leave { border-left-color: #f59e0b; }
        
        .stat-card h3 {
            font-size: 14px;
            color: #64748b;
            margin: 0 0 8px 0;
            text-transform: uppercase;
            font-weight: 600;
        }
        
        .stat-card .value {
            font-size: 32px;
            font-weight: 700;
            color: #1e293b;
            margin: 0;
        }
        
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #475569;
            font-size: 14px;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
        }
        
        .filter-group select:focus,
        .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .filter-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        
        .clear-btn {
            padding: 10px 20px;
            background: #f1f5f9;
            color: #475569;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .clear-btn:hover {
            background: #e2e8f0;
        }
        
        .employees-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .card-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 18px;
        }
        
        .export-btn {
            padding: 8px 16px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        .export-btn:hover {
            background: #059669;
        }
        
        .employees-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .employee-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .employee-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .employee-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .employee-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 700;
        }
        
        .employee-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .employee-status.active {
            background: #d1fae5;
            color: #065f46;
        }
        
        .employee-status.inactive {
            background: #f1f5f9;
            color: #475569;
        }
        
        .employee-status.on_leave {
            background: #fef3c7;
            color: #92400e;
        }
        
        .employee-info {
            margin-bottom: 15px;
        }
        
        .employee-name {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px 0;
        }
        
        .employee-designation {
            font-size: 13px;
            color: #64748b;
            margin: 0 0 8px 0;
        }
        
        .employee-department {
            display: inline-block;
            padding: 3px 10px;
            background: #e0e7ff;
            color: #3730a3;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .employee-details {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        
        .detail-row {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 13px;
            color: #64748b;
        }
        
        .detail-row i {
            width: 16px;
            color: #667eea;
        }
        
        .employee-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .view-btn {
            background: #667eea;
            color: white;
        }
        
        .view-btn:hover {
            background: #5568d3;
        }
        
        .contact-btn {
            background: #f1f5f9;
            color: #475569;
        }
        
        .contact-btn:hover {
            background: #e2e8f0;
        }
        
        .no-records {
            padding: 80px 20px;
            text-align: center;
            color: #94a3b8;
        }
        
        .no-records i {
            font-size: 64px;
            margin-bottom: 20px;
            display: block;
            opacity: 0.5;
        }
        
        .no-records h3 {
            font-size: 20px;
            margin: 0 0 10px 0;
            color: #64748b;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            border-radius: 16px;
            padding: 0;
            max-width: 700px;
            width: 90%;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            animation: slideUp 0.3s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
        }
        
        .modal-header {
            padding: 25px 30px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }
        
        .modal-header h3 {
            margin: 0;
            color: #1e293b;
            font-size: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #94a3b8;
            transition: color 0.3s;
            line-height: 1;
        }
        
        .close-modal:hover {
            color: #1e293b;
        }
        
        .modal-body {
            padding: 30px;
        }
        
        .profile-section {
            margin-bottom: 30px;
        }
        
        .profile-header {
            display: flex;
            gap: 20px;
            align-items: start;
            margin-bottom: 25px;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
            font-weight: 700;
            flex-shrink: 0;
        }
        
        .profile-info {
            flex: 1;
        }
        
        .profile-name {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 8px 0;
        }
        
        .profile-meta {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }
        
        .info-item {
            padding: 15px;
            background: #f8fafc;
            border-radius: 8px;
            border-left: 3px solid #667eea;
        }
        
        .info-label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }
        
        .info-value {
            font-size: 15px;
            color: #1e293b;
            font-weight: 500;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 15px 0;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_navbar.php'; ?>
    <?php include 'includes/hr_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-users"></i> Employee Records</h1>
                <p>View and manage employee information</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <p class="value"><?php echo $stats['total']; ?></p>
            </div>
            <div class="stat-card active">
                <h3>Active</h3>
                <p class="value"><?php echo $stats['active']; ?></p>
            </div>
            <div class="stat-card inactive">
                <h3>Inactive</h3>
                <p class="value"><?php echo $stats['inactive']; ?></p>
            </div>
            <div class="stat-card on-leave">
                <h3>On Leave</h3>
                <p class="value"><?php echo $stats['on_leave']; ?></p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" 
                                <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                        <option value="on_leave" <?php echo $statusFilter === 'on_leave' ? 'selected' : ''; ?>>On Leave</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Employee Type</label>
                    <select name="type">
                        <option value="">All Types</option>
                        <option value="permanent" <?php echo $typeFilter === 'permanent' ? 'selected' : ''; ?>>Permanent</option>
                        <option value="contract" <?php echo $typeFilter === 'contract' ? 'selected' : ''; ?>>Contract</option>
                        <option value="intern" <?php echo $typeFilter === 'intern' ? 'selected' : ''; ?>>Intern</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Search</label>
                    <input type="text" name="search" placeholder="Name, email, designation..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <a href="employee_records.php" class="clear-btn" style="display: inline-block; text-align: center; text-decoration: none;">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>

        <!-- Employees List -->
        <div class="employees-card">
            <div class="card-header">
                <h2><?php echo count($employees); ?> Employee<?php echo count($employees) !== 1 ? 's' : ''; ?> Found</h2>
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
            
            <?php if (empty($employees)): ?>
                <div class="no-records">
                    <i class="fas fa-user-slash"></i>
                    <h3>No Employees Found</h3>
                    <p>Try adjusting your filters or search query.</p>
                </div>
            <?php else: ?>
                <div class="employees-grid">
                    <?php foreach ($employees as $emp): ?>
                        <div class="employee-card" onclick="viewEmployeeDetails(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                            <div class="employee-header">
                                <div class="employee-avatar">
                                    <?php echo strtoupper(substr($emp['full_name'], 0, 1)); ?>
                                </div>
                                <span class="employee-status <?php echo $emp['status']; ?>">
                                    <?php echo str_replace('_', ' ', strtoupper($emp['status'])); ?>
                                </span>
                            </div>
                            
                            <div class="employee-info">
                                <h3 class="employee-name"><?php echo htmlspecialchars($emp['full_name']); ?></h3>
                                <p class="employee-designation"><?php echo htmlspecialchars($emp['designation'] ?? 'N/A'); ?></p>
                                <span class="employee-department">
                                    <?php echo htmlspecialchars($emp['department_name'] ?? 'Unassigned'); ?>
                                </span>
                            </div>
                            
                            <div class="employee-details">
                                <div class="detail-row">
                                    <i class="fas fa-envelope"></i>
                                    <?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?>
                                </div>
                                <div class="detail-row">
                                    <i class="fas fa-phone"></i>
                                    <?php echo htmlspecialchars($emp['phone'] ?? 'N/A'); ?>
                                </div>
                                <div class="detail-row">
                                    <i class="fas fa-calendar-plus"></i>
                                    Joined <?php echo date('M Y', strtotime($emp['join_date'])); ?>
                                </div>
                            </div>
                            
                            <div class="employee-actions" onclick="event.stopPropagation()">
                                <button class="action-btn view-btn" onclick="viewEmployeeDetails(<?php echo htmlspecialchars(json_encode($emp)); ?>)">
                                    <i class="fas fa-eye"></i> View Details
                                </button>
                                <a href="mailto:<?php echo htmlspecialchars($emp['email']); ?>" class="action-btn contact-btn" style="text-decoration: none;">
                                    <i class="fas fa-envelope"></i> Email
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Employee Details Modal -->
    <div id="employeeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-circle"></i> Employee Details</h3>
                <button class="close-modal" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body" id="employee-details"></div>
        </div>
    </div>

    <?php include 'includes/hr_scripts.php'; ?>
    <script>
        const employeesData = <?php echo json_encode($employees); ?>;

        function viewEmployeeDetails(employee) {
            const detailsHTML = `
                <div class="profile-section">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            ${employee.full_name.charAt(0).toUpperCase()}
                        </div>
                        <div class="profile-info">
                            <h2 class="profile-name">${employee.full_name}</h2>
                            <div class="profile-meta">
                                <span class="employee-status ${employee.status}">
                                    ${employee.status.replace('_', ' ').toUpperCase()}
                                </span>
                                <span class="employee-department">
                                    ${employee.department_name || 'Unassigned'}
                                </span>
                                <span class="employee-department" style="background: #fef3c7; color: #92400e;">
                                    ${employee.employee_type.toUpperCase()}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="section-title">Personal Information</h3>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label">Employee ID</div>
                            <div class="info-value">#${employee.employee_id}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Designation</div>
                            <div class="info-value">${employee.designation || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Email</div>
                            <div class="info-value">${employee.email || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Phone</div>
                            <div class="info-value">${employee.phone || 'N/A'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Join Date</div>
                            <div class="info-value">${new Date(employee.join_date).toLocaleDateString('en-IN', {day: '2-digit', month: 'short', year: 'numeric'})}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">Basic Salary</div>
                            <div class="info-value">₹${parseFloat(employee.basic_salary || 0).toLocaleString('en-IN')}</div>
                        </div>
                    </div>
                </div>

                <div class="profile-section">
                    <h3 class="section-title">Quick Actions</h3>
                    <div class="employee-actions" style="margin-top: 15px;">
                        <a href="mailto:${employee.email}" class="action-btn view-btn" style="text-decoration: none;">
                            <i class="fas fa-envelope"></i> Send Email
                        </a>
                        <a href="tel:${employee.phone}" class="action-btn contact-btn" style="text-decoration: none;">
                            <i class="fas fa-phone"></i> Call
                        </a>
                    </div>
                </div>
            `;
            
            document.getElementById('employee-details').innerHTML = detailsHTML;
            document.getElementById('employeeModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('employeeModal').classList.remove('active');
        }

        // Close modal on outside click
        document.getElementById('employeeModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Export to CSV
        function exportToCSV() {
            if (employeesData.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['Employee ID', 'Name', 'Designation', 'Department', 'Email', 'Phone', 'Status', 'Type', 'Join Date', 'Basic Salary'];
            const rows = employeesData.map(emp => [
                emp.employee_id,
                emp.full_name,
                emp.designation || '',
                emp.department_name || '',
                emp.email || '',
                emp.phone || '',
                emp.status,
                emp.employee_type,
                emp.join_date,
                emp.basic_salary || 0
            ]);

            let csv = headers.join(',') + '\\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(',') + '\\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `employees_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
