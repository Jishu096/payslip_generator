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

// Get selected date (default to today)
$selectedDate = $_GET['date'] ?? date('Y-m-d');

// Fetch attendance sheet for selected date
try {
    $stmt = $conn->prepare("
        SELECT 
            a.attendance_id,
            a.employee_id,
            e.full_name,
            d.department_name as department,
            a.date,
            a.status,
            a.time_in,
            a.time_out,
            a.leave_type,
            a.remarks,
            a.verification_status
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE a.date = ?
        ORDER BY e.full_name ASC
    ");
    $stmt->execute([$selectedDate]);
    $attendanceRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $attendanceRecords = [];
    $error = "Error fetching attendance: " . $e->getMessage();
}

// Get all employees for adding missing entries
try {
    $stmt = $conn->query("
        SELECT e.employee_id, e.full_name, d.department_name as department 
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE e.status = 'active'
        ORDER BY e.full_name ASC
    ");
    $allEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $allEmployees = [];
}

// Get distinct dates with pending verification
try {
    $stmt = $conn->query("
        SELECT DISTINCT date, verification_status
        FROM attendance
        WHERE verification_status = 'Pending' OR verification_status IS NULL
        ORDER BY date DESC
        LIMIT 30
    ");
    $pendingDates = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pendingDates = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Attendance - HR Officer</title>
    <?php include 'includes/hr_styles.php'; ?>
    <style>
        .date-selector {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .date-selector h3 {
            margin: 0 0 15px 0;
            color: #1e293b;
        }
        
        .date-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 10px;
        }
        
        .date-card {
            padding: 12px;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s ease;
            background: white;
        }
        
        .date-card:hover {
            border-color: #667eea;
            transform: translateY(-2px);
        }
        
        .date-card.active {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-color: #667eea;
        }
        
        .date-card .date-label {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .date-card .status-badge {
            font-size: 11px;
            padding: 2px 8px;
            border-radius: 10px;
            background: #fee2e2;
            color: #991b1b;
        }
        
        .date-card.active .status-badge {
            background: rgba(255,255,255,0.2);
            color: white;
        }
        
        .attendance-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        
        .table-header {
            padding: 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .table-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .verify-btn {
            background: white;
            color: #667eea;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .verify-btn:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .verify-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .editable-table {
            width: 100%;
        }
        
        .editable-table thead {
            background: #f8fafc;
        }
        
        .editable-table th {
            padding: 15px;
            text-align: left;
            font-weight: 600;
            color: #475569;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .editable-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .editable-table tr:hover {
            background: #f8fafc;
        }
        
        .editable-cell {
            padding: 5px;
            border: 1px solid #cbd5e1;
            border-radius: 4px;
            min-width: 80px;
        }
        
        .editable-cell:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        select.editable-cell {
            background: white;
            cursor: pointer;
        }
        
        .add-entry-btn {
            margin: 20px;
            padding: 12px 24px;
            background: #10b981;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .add-entry-btn:hover {
            background: #059669;
            transform: translateY(-2px);
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1e293b;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            color: #475569;
            font-weight: 500;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-size: 14px;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        .modal-actions button {
            flex: 1;
            padding: 12px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-save {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-cancel {
            background: #e2e8f0;
            color: #475569;
        }
    </style>
</head>
<body>
    <?php include 'includes/hr_navbar.php'; ?>
    
    <div class="container">
        <?php include 'includes/hr_sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <h1><i class="fas fa-check-circle"></i> Verify Attendance</h1>
                <p>Review and verify uploaded attendance sheets</p>
            </div>
            
            <?php if (isset($error)): ?>
                <div style="background: #fee2e2; color: #991b1b; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Date Selector -->
            <div class="date-selector">
                <h3><i class="fas fa-calendar"></i> Select Date</h3>
                <div class="date-grid">
                    <?php foreach ($pendingDates as $dateRow): ?>
                        <a href="?date=<?php echo $dateRow['date']; ?>" 
                           class="date-card <?php echo $dateRow['date'] === $selectedDate ? 'active' : ''; ?>">
                            <div class="date-label">
                                <?php echo date('M d', strtotime($dateRow['date'])); ?>
                            </div>
                            <div class="status-badge">
                                <?php echo $dateRow['verification_status'] ?? 'Pending'; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Attendance Table -->
            <div class="attendance-table">
                <div class="table-header">
                    <h2>Attendance Sheet - <?php echo date('F d, Y', strtotime($selectedDate)); ?></h2>
                    <button class="verify-btn" onclick="verifyAttendance()" <?php echo empty($attendanceRecords) ? 'disabled' : ''; ?>>
                        <i class="fas fa-check-double"></i> VERIFY & SEND TO ACCOUNTANT
                    </button>
                </div>
                
                <button class="add-entry-btn" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> Add Missing Entry
                </button>
                
                <?php if (!empty($attendanceRecords)): ?>
                    <table class="editable-table">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Department</th>
                                <th>Status</th>
                                <th>Time In</th>
                                <th>Time Out</th>
                                <th>Leave Type</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="attendanceTableBody">
                            <?php foreach ($attendanceRecords as $record): ?>
                            <tr data-id="<?php echo $record['attendance_id']; ?>">
                                <td><strong><?php echo htmlspecialchars($record['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($record['department']); ?></td>
                                <td>
                                    <select class="editable-cell" data-field="status" onchange="updateField(this, <?php echo $record['attendance_id']; ?>)">
                                        <option value="Present" <?php echo $record['status'] === 'Present' ? 'selected' : ''; ?>>Present</option>
                                        <option value="Absent" <?php echo $record['status'] === 'Absent' ? 'selected' : ''; ?>>Absent</option>
                                        <option value="Leave" <?php echo $record['status'] === 'Leave' ? 'selected' : ''; ?>>Leave</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="time" class="editable-cell" 
                                           value="<?php echo $record['time_in']; ?>" 
                                           data-field="time_in"
                                           onchange="updateField(this, <?php echo $record['attendance_id']; ?>)">
                                </td>
                                <td>
                                    <input type="time" class="editable-cell" 
                                           value="<?php echo $record['time_out']; ?>" 
                                           data-field="time_out"
                                           onchange="updateField(this, <?php echo $record['attendance_id']; ?>)">
                                </td>
                                <td>
                                    <select class="editable-cell" data-field="leave_type" onchange="updateField(this, <?php echo $record['attendance_id']; ?>)">
                                        <option value="">None</option>
                                        <option value="CL" <?php echo $record['leave_type'] === 'CL' ? 'selected' : ''; ?>>CL</option>
                                        <option value="OD" <?php echo $record['leave_type'] === 'OD' ? 'selected' : ''; ?>>OD</option>
                                        <option value="SL" <?php echo $record['leave_type'] === 'SL' ? 'selected' : ''; ?>>SL</option>
                                        <option value="PL" <?php echo $record['leave_type'] === 'PL' ? 'selected' : ''; ?>>PL</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="editable-cell" 
                                           value="<?php echo htmlspecialchars($record['remarks'] ?? ''); ?>" 
                                           data-field="remarks"
                                           onchange="updateField(this, <?php echo $record['attendance_id']; ?>)"
                                           style="min-width: 150px;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p style="text-align: center; padding: 40px; color: #64748b;">
                        <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 10px; display: block;"></i>
                        No attendance records for this date
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Entry Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <i class="fas fa-plus-circle"></i> Add Missing Attendance Entry
            </div>
            <form id="addEntryForm">
                <div class="form-group">
                    <label>Employee *</label>
                    <select id="employeeSelect" required>
                        <option value="">Select Employee</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?php echo $emp['employee_id']; ?>">
                                <?php echo htmlspecialchars($emp['full_name']) . ' (' . htmlspecialchars($emp['department']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status *</label>
                    <select id="statusSelect" required>
                        <option value="Present">Present</option>
                        <option value="Absent">Absent</option>
                        <option value="Leave">Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Time In</label>
                    <input type="time" id="timeInInput" value="09:00">
                </div>
                <div class="form-group">
                    <label>Time Out</label>
                    <input type="time" id="timeOutInput" value="17:00">
                </div>
                <div class="form-group">
                    <label>Leave Type</label>
                    <select id="leaveTypeSelect">
                        <option value="">None</option>
                        <option value="CL">CL - Casual Leave</option>
                        <option value="OD">OD - On Duty</option>
                        <option value="SL">SL - Sick Leave</option>
                        <option value="PL">PL - Privilege Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <input type="text" id="remarksInput" placeholder="Optional remarks">
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-cancel" onclick="closeAddModal()">Cancel</button>
                    <button type="submit" class="btn-save">Add Entry</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/hr_scripts.php'; ?>
    <script>
        const selectedDate = '<?php echo $selectedDate; ?>';
        
        function updateField(element, attendanceId) {
            const field = element.getAttribute('data-field');
            const value = element.value;
            
            fetch('api/update_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    attendance_id: attendanceId,
                    field: field,
                    value: value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    element.style.borderColor = '#10b981';
                    setTimeout(() => { element.style.borderColor = '#cbd5e1'; }, 1000);
                } else {
                    alert('Error updating: ' + data.message);
                    element.style.borderColor = '#ef4444';
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                element.style.borderColor = '#ef4444';
            });
        }
        
        function verifyAttendance() {
            if (!confirm('Verify this attendance sheet and send to Accountant?')) return;
            
            fetch('api/verify_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ date: selectedDate })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Attendance sheet verified and sent to Accountant!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        }
        
        function openAddModal() {
            document.getElementById('addModal').classList.add('active');
        }
        
        function closeAddModal() {
            document.getElementById('addModal').classList.remove('active');
            document.getElementById('addEntryForm').reset();
        }
        
        document.getElementById('addEntryForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const data = {
                employee_id: document.getElementById('employeeSelect').value,
                date: selectedDate,
                status: document.getElementById('statusSelect').value,
                time_in: document.getElementById('timeInInput').value,
                time_out: document.getElementById('timeOutInput').value,
                leave_type: document.getElementById('leaveTypeSelect').value,
                remarks: document.getElementById('remarksInput').value
            };
            
            fetch('api/add_attendance.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Entry added successfully!');
                    window.location.reload();
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error.message));
        });
        
        // Close modal on outside click
        document.getElementById('addModal').addEventListener('click', function(e) {
            if (e.target === this) closeAddModal();
        });
    </script>
</body>
</html>
