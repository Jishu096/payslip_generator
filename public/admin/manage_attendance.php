<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/Attendance.php";

$employeeModel = new Employee();
$attendanceModel = new Attendance();

// Get all employees
$employees = $employeeModel->getAllEmployees();

// Get today's attendance if exists
$selectedDate = $_GET['date'] ?? date('Y-m-d');
$attendanceRecords = [];

foreach ($employees as $emp) {
    $records = $attendanceModel->getAttendanceByDateRange($emp['employee_id'], $selectedDate, $selectedDate);
    if (!empty($records)) {
        $attendanceRecords[$emp['employee_id']] = strtolower($records[0]['status']); // Normalize to lowercase
    } else {
        $attendanceRecords[$emp['employee_id']] = 'unmarked'; // Default state
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mark Attendance - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Toolbar Styling */
        .toolbar-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            border: 1px solid rgba(0,0,0,0.05);
            flex-wrap: wrap;
        }

        .date-picker-group {
            display: flex;
            align-items: center;
            gap: 15px;
            background: #f8f9fa;
            padding: 8px 15px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
        }

        .date-picker-group input {
            border: none;
            background: transparent;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            color: #2d3748;
            cursor: pointer;
        }

        .date-picker-group input:focus {
            outline: none;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 20px 12px 45px;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            background: #f8f9fa;
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            background: white;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        /* Grid Layout */
        .employee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .employee-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            position: relative;
        }

        .employee-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: #667eea;
        }

        .card-header-flex {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
        }

        .emp-avatar {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            font-weight: 700;
            flex-shrink: 0;
        }

        .emp-info h3 {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .emp-info p {
            font-size: 13px;
            color: #718096;
            margin: 0;
        }

        .status-buttons {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 8px;
            background: #f7fafc;
            padding: 5px;
            border-radius: 10px;
        }

        .status-btn {
            border: none;
            background: transparent;
            padding: 8px 0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            color: #718096;
            transition: all 0.2s ease;
            text-align: center;
        }

        .status-btn:hover {
            background: rgba(0,0,0,0.05);
        }

        .status-btn.active.present { background: #c6f6d5; color: #22543d; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .status-btn.active.absent { background: #fed7d7; color: #742a2a; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .status-btn.active.leave { background: #bee3f8; color: #2c5282; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .status-btn.active.holiday { background: #e2e8f0; color: #2d3748; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .status-btn.active.clear { background: #fee2e2; color: #991b1b; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }

        .spinner {
            display: none;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(0,0,0,0.1);
            border-left-color: #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: absolute;
            top: 15px;
            right: 15px;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .saving-indicator {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: #2d3748;
            color: white;
            padding: 12px 24px;
            border-radius: 30px;
            font-weight: 500;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            transition: transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .saving-indicator.show {
            transform: translateX(-50%) translateY(0);
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <h1>Manage Attendance</h1>
                <p>Mark daily attendance for all employees instantly.</p>
            </div>

            <!-- Toolbar -->
            <div class="toolbar-card">
                <div class="date-picker-group">
                    <i class="far fa-calendar-alt" style="color: #667eea;"></i>
                    <input type="date" id="datePicker" value="<?= htmlspecialchars($selectedDate) ?>" max="<?= date('Y-m-d') ?>">
                </div>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="searchInput" placeholder="Search by name or department...">
                </div>

                <div style="display: flex; gap: 10px;">
                     <button class="btn" onclick="markAll('present')" style="background: #48bb78;">
                        <i class="fas fa-check-double"></i> All Present
                    </button>
                    <button class="btn" onclick="markAll('clear')" style="background: #e53e3e;">
                         <i class="fas fa-undo"></i> Reset All
                    </button>
                </div>
            </div>

            <!-- Employee Grid -->
            <div class="employee-grid" id="employeeGrid">
                <?php foreach ($employees as $emp): 
                    $currentStatus = $attendanceRecords[$emp['employee_id']];
                ?>
                <div class="employee-card" data-name="<?= strtolower($emp['full_name']) ?>" data-dept="<?= strtolower($emp['department_name'] ?? '') ?>">
                    <div class="spinner" id="spinner-<?= $emp['employee_id'] ?>"></div>
                    <div class="card-header-flex">
                        <div class="emp-avatar">
                            <?php 
                                $nameParts = explode(' ', $emp['full_name']);
                                echo strtoupper(substr($nameParts[0], 0, 1));
                                if (count($nameParts) > 1) echo strtoupper(substr($end = end($nameParts), 0, 1));
                            ?>
                        </div>
                        <div class="emp-info">
                            <h3><?= htmlspecialchars($emp['full_name']) ?></h3>
                            <p><?= htmlspecialchars($emp['department_name'] ?? 'No Dept') ?></p>
                            <p style="font-size: 11px; opacity: 0.7;"><?= htmlspecialchars($emp['designation']) ?></p>
                        </div>
                    </div>

                    <div class="status-buttons">
                        <button class="status-btn <?= $currentStatus === 'present' ? 'active present' : '' ?>" 
                                onclick="updateStatus(<?= $emp['employee_id'] ?>, 'present', this)">Present</button>
                        
                        <button class="status-btn <?= $currentStatus === 'absent' ? 'active absent' : '' ?>" 
                                onclick="updateStatus(<?= $emp['employee_id'] ?>, 'absent', this)">Absent</button>
                        
                        <button class="status-btn <?= $currentStatus === 'leave' ? 'active leave' : '' ?>" 
                                onclick="updateStatus(<?= $emp['employee_id'] ?>, 'leave', this)">Leave</button>
                        
                        <button class="status-btn <?= $currentStatus === 'holiday' ? 'active holiday' : '' ?>" 
                                onclick="updateStatus(<?= $emp['employee_id'] ?>, 'holiday', this)">Holiday</button>

                        <button class="status-btn" onclick="updateStatus(<?= $emp['employee_id'] ?>, 'clear', this)">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <div class="saving-indicator" id="savingIndicator">
        <i class="fas fa-check-circle" style="color: #48bb78;"></i> Changes Saved
    </div>

    <script>
        const datePicker = document.getElementById('datePicker');
        const searchInput = document.getElementById('searchInput');
        const savingIndicator = document.getElementById('savingIndicator');
        let saveTimeout;

        // Date Change
        datePicker.addEventListener('change', (e) => {
            window.location.href = '?date=' + e.target.value;
        });

        // Search Filter
        searchInput.addEventListener('input', (e) => {
            const term = e.target.value.toLowerCase();
            const cards = document.querySelectorAll('.employee-card');
            
            cards.forEach(card => {
                const name = card.dataset.name;
                const dept = card.dataset.dept;
                if (name.includes(term) || dept.includes(term)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });

        // Update Status API
        async function updateStatus(employeeId, status, btnElement) {
            const card = btnElement.closest('.employee-card');
            const btns = card.querySelectorAll('.status-btn');
            const spinner = document.getElementById('spinner-' + employeeId);
            const currentDate = datePicker.value;

            // Optimistic UI Update
            btns.forEach(b => {
                b.className = 'status-btn'; // remove all active classes
            });
            btnElement.classList.add('active', status);
            spinner.style.display = 'block';

            try {
                const response = await fetch('api/save_attendance.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        employee_id: employeeId,
                        date: currentDate,
                        status: status
                    })
                });

                const result = await response.json();
                
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Failed to save');
                }

                showSavedIndicator();

            } catch (error) {
                console.error('Error:', error);
                alert('Failed to save attendance. Please try again.');
                // Revert UI (detailed implementation omitted for brevity)
            } finally {
                spinner.style.display = 'none';
            }
        }

        function markAll(status) {
            const actionText = status === 'clear' ? 'RESET (Delete)' : `mark as ${status}`;
            if(!confirm(`Are you sure you want to ${actionText} ALL displayed employees?`)) return;

            const cards = document.querySelectorAll('.employee-card');
            cards.forEach(card => {
                if(card.style.display !== 'none') {
                    // Find the button for the target status
                    const buttons = card.querySelectorAll('.status-btn');
                    buttons.forEach(btn => {
                        // Handle 'clear' button which has only icon
                        if (status === 'clear') {
                            if (btn.querySelector('.fa-times')) {
                                btn.click();
                            }
                        } else if(btn.textContent.toLowerCase() === status) {
                             btn.click();
                        }
                    });
                }
            });
        }

        function showSavedIndicator() {
            savingIndicator.classList.add('show');
            clearTimeout(saveTimeout);
            saveTimeout = setTimeout(() => {
                savingIndicator.classList.remove('show');
            }, 2000);
        }
    </script>
</body>
</html>
