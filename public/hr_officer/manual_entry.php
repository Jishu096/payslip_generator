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

// Get all active employees grouped by department
try {
    $stmt = $conn->query("
        SELECT 
            e.employee_id, 
            e.full_name, 
            d.department_name,
            e.designation
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.status = 'active'
        ORDER BY d.department_name ASC, e.full_name ASC
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by department
    $employeesByDept = [];
    foreach ($employees as $emp) {
        $dept = $emp['department_name'] ?? 'Unassigned';
        if (!isset($employeesByDept[$dept])) {
            $employeesByDept[$dept] = [];
        }
        $employeesByDept[$dept][] = $emp;
    }
} catch (Exception $e) {
    $employees = [];
    $employeesByDept = [];
    $error = "Error fetching employees: " . $e->getMessage();
}

// Get departments for filter
try {
    $deptStmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manual Entry - HR Officer</title>
    <?php include 'includes/hr_styles.php'; ?>
    <style>
        .entry-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        @media (max-width: 1200px) {
            .entry-container {
                grid-template-columns: 1fr;
            }
        }
        
        .entry-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .entry-card h2 {
            margin: 0 0 20px 0;
            color: #1e293b;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .entry-card h2 i {
            color: #667eea;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 14px;
            font-weight: 600;
            color: #475569;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .section-title i {
            color: #667eea;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-grid.single {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: #475569;
            font-size: 14px;
        }
        
        .form-group label .required {
            color: #ef4444;
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 80px;
        }
        
        .form-group .hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 4px;
        }
        
        .entry-type-selector {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .type-option {
            flex: 1;
            padding: 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
            background: white;
        }
        
        .type-option:hover {
            border-color: #667eea;
            background: #f8fafc;
        }
        
        .type-option.active {
            border-color: #667eea;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        }
        
        .type-option i {
            font-size: 24px;
            color: #667eea;
            margin-bottom: 8px;
            display: block;
        }
        
        .type-option .type-label {
            font-weight: 600;
            color: #1e293b;
            font-size: 14px;
        }
        
        .type-option .type-desc {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        
        .submit-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        }
        
        .submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .recent-entries-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .recent-header {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .recent-header h2 {
            margin: 0;
            color: #1e293b;
            font-size: 18px;
        }
        
        .refresh-btn {
            padding: 8px 16px;
            background: #f1f5f9;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: #475569;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .refresh-btn:hover {
            background: #e2e8f0;
        }
        
        .recent-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .recent-table thead {
            background: #f8fafc;
        }
        
        .recent-table th {
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        
        .recent-table td {
            padding: 12px 15px;
            border-top: 1px solid #f1f5f9;
            color: #334155;
            font-size: 13px;
        }
        
        .recent-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            display: inline-block;
            padding: 3px 10px;
            border-radius: 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.present {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-badge.absent {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-badge.leave {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-badge.holiday {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .employee-select {
            position: relative;
        }
        
        .search-input {
            width: 100%;
            padding: 11px 14px 11px 40px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .search-icon {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }
        
        .employee-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            margin-top: 5px;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            z-index: 100;
            display: none;
        }
        
        .employee-dropdown.show {
            display: block;
        }
        
        .dept-group {
            padding: 8px 12px;
            background: #f8fafc;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
        }
        
        .employee-option {
            padding: 10px 14px;
            cursor: pointer;
            transition: background 0.2s;
            border-bottom: 1px solid #f1f5f9;
        }
        
        .employee-option:hover {
            background: #f8fafc;
        }
        
        .employee-option .emp-name {
            font-weight: 500;
            color: #1e293b;
            font-size: 14px;
        }
        
        .employee-option .emp-designation {
            font-size: 12px;
            color: #64748b;
            margin-top: 2px;
        }
        
        .alert {
            padding: 15px 18px;
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
            border: 1px solid #a7f3d0;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }
        
        .alert i {
            font-size: 18px;
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_navbar.php'; ?>
    <?php include 'includes/hr_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-edit"></i> Manual Attendance Entry</h1>
                <p>Add attendance records manually for employees</p>
            </div>
        </div>

        <?php if (isset($error)): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div id="success-message" class="alert alert-success" style="display: none;">
            <i class="fas fa-check-circle"></i>
            <span id="success-text"></span>
        </div>

        <div class="entry-container">
            <!-- Entry Form -->
            <div class="entry-card">
                <h2><i class="fas fa-plus-circle"></i> Add Attendance Record</h2>
                
                <!-- Entry Type Selector -->
                <div class="entry-type-selector">
                    <div class="type-option active" onclick="setEntryType('single')" id="single-type">
                        <i class="fas fa-user"></i>
                        <div class="type-label">Single Entry</div>
                        <div class="type-desc">One employee, one date</div>
                    </div>
                    <div class="type-option" onclick="setEntryType('range')" id="range-type">
                        <i class="fas fa-calendar-week"></i>
                        <div class="type-label">Date Range</div>
                        <div class="type-desc">One employee, multiple dates</div>
                    </div>
                </div>

                <form id="attendance-form">
                    <!-- Employee Selection -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-user-circle"></i>
                            Employee Information
                        </div>
                        
                        <div class="form-group">
                            <label>Select Employee <span class="required">*</span></label>
                            <div class="employee-select">
                                <i class="fas fa-search search-icon"></i>
                                <input 
                                    type="text" 
                                    id="employee-search" 
                                    class="search-input"
                                    placeholder="Search employee by name..."
                                    autocomplete="off"
                                    required
                                >
                                <input type="hidden" id="employee-id" name="employee_id" required>
                                <div class="employee-dropdown" id="employee-dropdown">
                                    <?php foreach ($employeesByDept as $dept => $emps): ?>
                                        <div class="dept-group"><?php echo htmlspecialchars($dept); ?></div>
                                        <?php foreach ($emps as $emp): ?>
                                            <div class="employee-option" 
                                                 data-id="<?php echo $emp['employee_id']; ?>"
                                                 data-name="<?php echo htmlspecialchars($emp['full_name']); ?>"
                                                 onclick="selectEmployee(<?php echo $emp['employee_id']; ?>, '<?php echo addslashes($emp['full_name']); ?>')">
                                                <div class="emp-name"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                                <div class="emp-designation"><?php echo htmlspecialchars($emp['designation'] ?? 'N/A'); ?></div>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Date Selection -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-calendar-alt"></i>
                            Date Information
                        </div>
                        
                        <div class="form-grid" id="date-grid-single">
                            <div class="form-group">
                                <label>Date <span class="required">*</span></label>
                                <input type="date" name="date" id="single-date" max="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-grid" id="date-grid-range" style="display: none;">
                            <div class="form-group">
                                <label>Start Date <span class="required">*</span></label>
                                <input type="date" name="start_date" id="start-date" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="form-group">
                                <label>End Date <span class="required">*</span></label>
                                <input type="date" name="end_date" id="end-date" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Attendance Details -->
                    <div class="form-section">
                        <div class="section-title">
                            <i class="fas fa-clipboard-check"></i>
                            Attendance Details
                        </div>
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label>Status <span class="required">*</span></label>
                                <select name="status" id="status" onchange="toggleLeaveFields()" required>
                                    <option value="">Select status...</option>
                                    <option value="present">Present</option>
                                    <option value="absent">Absent</option>
                                    <option value="leave">Leave</option>
                                    <option value="holiday">Holiday</option>
                                </select>
                            </div>
                            
                            <div class="form-group" id="leave-type-group" style="display: none;">
                                <label>Leave Type</label>
                                <select name="leave_type" id="leave-type">
                                    <option value="">Select leave type...</option>
                                    <option value="CL">CL - Casual Leave</option>
                                    <option value="SL">SL - Sick Leave</option>
                                    <option value="PL">PL - Paid Leave</option>
                                    <option value="LWP">LWP - Leave Without Pay</option>
                                    <option value="ML">ML - Maternity Leave</option>
                                    <option value="OD">OD - On Duty</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-grid" id="time-fields">
                            <div class="form-group">
                                <label>Time In</label>
                                <input type="time" name="time_in" id="time-in">
                                <div class="hint">Leave empty if not applicable</div>
                            </div>
                            <div class="form-group">
                                <label>Time Out</label>
                                <input type="time" name="time_out" id="time-out">
                                <div class="hint">Leave empty if not applicable</div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea name="remarks" id="remarks" placeholder="Add any additional notes..."></textarea>
                        </div>
                    </div>

                    <button type="submit" class="submit-btn" id="submit-btn">
                        <i class="fas fa-save"></i>
                        <span id="submit-text">Add Attendance Record</span>
                    </button>
                </form>
            </div>

            <!-- Recent Entries -->
            <div class="entry-card">
                <h2><i class="fas fa-history"></i> Recent Entries (Today)</h2>
                
                <div class="recent-entries-card">
                    <div class="recent-header">
                        <h2>Today's Manual Entries</h2>
                        <button class="refresh-btn" onclick="loadRecentEntries()">
                            <i class="fas fa-sync-alt"></i> Refresh
                        </button>
                    </div>
                    
                    <div id="recent-entries-container">
                        <table class="recent-table">
                            <thead>
                                <tr>
                                    <th>Employee</th>
                                    <th>Status</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody id="recent-entries-body">
                                <tr>
                                    <td colspan="3" style="text-align: center; padding: 40px; color: #94a3b8;">
                                        <i class="fas fa-inbox" style="font-size: 36px; display: block; margin-bottom: 10px;"></i>
                                        Loading recent entries...
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/hr_scripts.php'; ?>
    <script>
        let entryType = 'single';
        const employeesData = <?php echo json_encode($employees); ?>;

        // Entry type toggle
        function setEntryType(type) {
            entryType = type;
            
            document.getElementById('single-type').classList.toggle('active', type === 'single');
            document.getElementById('range-type').classList.toggle('active', type === 'range');
            
            document.getElementById('date-grid-single').style.display = type === 'single' ? 'grid' : 'none';
            document.getElementById('date-grid-range').style.display = type === 'range' ? 'grid' : 'none';
            
            document.getElementById('single-date').required = type === 'single';
            document.getElementById('start-date').required = type === 'range';
            document.getElementById('end-date').required = type === 'range';
            
            document.getElementById('submit-text').textContent = 
                type === 'single' ? 'Add Attendance Record' : 'Add Attendance for Date Range';
        }

        // Employee search and selection
        const searchInput = document.getElementById('employee-search');
        const dropdown = document.getElementById('employee-dropdown');

        searchInput.addEventListener('focus', () => {
            dropdown.classList.add('show');
        });

        searchInput.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase();
            const options = dropdown.querySelectorAll('.employee-option');
            const groups = dropdown.querySelectorAll('.dept-group');
            
            options.forEach(option => {
                const name = option.dataset.name.toLowerCase();
                if (name.includes(query)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Hide empty department groups
            groups.forEach(group => {
                const nextOptions = [];
                let sibling = group.nextElementSibling;
                while (sibling && sibling.classList.contains('employee-option')) {
                    nextOptions.push(sibling);
                    sibling = sibling.nextElementSibling;
                }
                const hasVisible = nextOptions.some(opt => opt.style.display !== 'none');
                group.style.display = hasVisible ? 'block' : 'none';
            });
            
            dropdown.classList.add('show');
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.employee-select')) {
                dropdown.classList.remove('show');
            }
        });

        function selectEmployee(id, name) {
            document.getElementById('employee-id').value = id;
            document.getElementById('employee-search').value = name;
            dropdown.classList.remove('show');
        }

        // Toggle leave type field
        function toggleLeaveFields() {
            const status = document.getElementById('status').value;
            const leaveTypeGroup = document.getElementById('leave-type-group');
            const leaveTypeSelect = document.getElementById('leave-type');
            const timeFields = document.getElementById('time-fields');
            
            if (status === 'leave') {
                leaveTypeGroup.style.display = 'block';
                leaveTypeSelect.required = true;
                timeFields.style.display = 'none';
            } else {
                leaveTypeGroup.style.display = 'none';
                leaveTypeSelect.required = false;
                leaveTypeSelect.value = '';
                timeFields.style.display = status === 'present' ? 'grid' : 'none';
            }
        }

        // Form submission
        document.getElementById('attendance-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const submitBtn = document.getElementById('submit-btn');
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
            
            try {
                const response = await fetch('api/manual_entry.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showSuccess(result.message + (result.count > 1 ? ` (${result.count} records created)` : ''));
                    e.target.reset();
                    document.getElementById('employee-id').value = '';
                    loadRecentEntries();
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to add attendance record');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-save"></i> <span id="submit-text">' + 
                    (entryType === 'single' ? 'Add Attendance Record' : 'Add Attendance for Date Range') + '</span>';
            }
        });

        function showSuccess(message) {
            const successMsg = document.getElementById('success-message');
            document.getElementById('success-text').textContent = message;
            successMsg.style.display = 'flex';
            setTimeout(() => {
                successMsg.style.display = 'none';
            }, 5000);
        }

        // Load recent entries
        async function loadRecentEntries() {
            try {
                const response = await fetch('api/get_recent_entries.php');
                const result = await response.json();
                
                const tbody = document.getElementById('recent-entries-body');
                
                if (result.success && result.data.length > 0) {
                    tbody.innerHTML = result.data.map(entry => `
                        <tr>
                            <td><strong>${entry.full_name}</strong></td>
                            <td><span class="status-badge ${entry.status}">${entry.status.toUpperCase()}</span></td>
                            <td>${entry.time_in || '-'} ${entry.time_out ? '- ' + entry.time_out : ''}</td>
                        </tr>
                    `).join('');
                } else {
                    tbody.innerHTML = `
                        <tr>
                            <td colspan="3" style="text-align: center; padding: 40px; color: #94a3b8;">
                                <i class="fas fa-inbox" style="font-size: 36px; display: block; margin-bottom: 10px;"></i>
                                No manual entries today
                            </td>
                        </tr>
                    `;
                }
            } catch (error) {
                console.error('Error loading recent entries:', error);
            }
        }

        // Load recent entries on page load
        loadRecentEntries();
    </script>
</body>
</html>
