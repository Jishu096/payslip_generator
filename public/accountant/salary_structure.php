<?php
session_start();

$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';

// Get all employees with salary structure
try {
    $stmt = $db->query("
        SELECT 
            e.employee_id,
            e.full_name,
            e.designation,
            e.basic_salary,
            d.department_name
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        ORDER BY e.full_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// Standard salary components (percentage of basic)
$salaryComponents = [
    'basic' => ['name' => 'Basic Salary', 'type' => 'base', 'rate' => 100],
    'da' => ['name' => 'DA (Dearness Allowance)', 'type' => 'allowance', 'rate' => 58],
    'hra' => ['name' => 'HRA (House Rent Allowance)', 'type' => 'allowance', 'rate' => 20],
    'ta' => ['name' => 'TA (Transport Allowance)', 'type' => 'allowance', 'rate' => 10],
    'pf' => ['name' => 'PF (Provident Fund)', 'type' => 'deduction', 'rate' => 12],
    'nps' => ['name' => 'NPS (New Pension Scheme)', 'type' => 'deduction', 'rate' => 10],
    'professional_tax' => ['name' => 'Professional Tax', 'type' => 'deduction', 'rate' => 200, 'fixed' => true]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Structure - Accountant</title>
    <?php include 'includes/accountant_styles.php'; ?>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-money-bill-wave"></i> Salary Structure</h1>
                <p>Manage salary components and employee compensation</p>
            </div>
        </div>

        <!-- Salary Components -->
        <div class="components-card">
            <h2><i class="fas fa-cogs"></i> Standard Salary Components</h2>
            <div class="components-grid">
                <?php foreach ($salaryComponents as $key => $component): ?>
                    <div class="component-card <?php echo $component['type']; ?>">
                        <div class="component-header">
                            <h3><?php echo $component['name']; ?></h3>
                            <span class="component-type"><?php echo ucfirst($component['type']); ?></span>
                        </div>
                        <div class="component-rate">
                            <?php if (isset($component['fixed']) && $component['fixed']): ?>
                                ₹<?php echo number_format($component['rate'], 2); ?>
                            <?php else: ?>
                                <?php echo $component['rate']; ?>% of Basic
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Employee Salary List -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-users"></i> Employee Salary Structure</h2>
            </div>
            <div class="table-container">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Designation</th>
                            <th>Department</th>
                            <th>Basic Salary</th>
                            <th>DA (58%)</th>
                            <th>HRA (20%)</th>
                            <th>Gross Salary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                            $basic = $emp['basic_salary'] ?? 0;
                            $da = round($basic * 0.58, 2);
                            $hra = round($basic * 0.20, 2);
                            $ta = round($basic * 0.10, 2);
                            $gross = $basic + $da + $hra + $ta;
                            ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($emp['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($emp['designation'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                                <td class="amount">₹<?php echo number_format($basic, 2); ?></td>
                                <td class="amount">₹<?php echo number_format($da, 2); ?></td>
                                <td class="amount">₹<?php echo number_format($hra, 2); ?></td>
                                <td class="amount" style="font-weight: 700; color: #667eea;">₹<?php echo number_format($gross, 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <h3>Salary Calculation Formula</h3>
                <p><strong>Gross Salary</strong> = Basic + DA (58%) + HRA (20%) + TA (10%) + Bonus</p>
                <p><strong>Net Salary</strong> = Gross Salary - (Tax + PF (12%) + NPS (10%) + Professional Tax + Other Deductions)</p>
            </div>
        </div>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>
    <style>
        .components-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .components-card h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .components-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .component-card {
            padding: 20px;
            border-radius: 10px;
            border: 2px solid;
            transition: all 0.3s;
        }

        .component-card.base {
            background: #f8fafc;
            border-color: #667eea;
        }

        .component-card.allowance {
            background: #d1fae5;
            border-color: #10b981;
        }

        .component-card.deduction {
            background: #fee2e2;
            border-color: #ef4444;
        }

        .component-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .component-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }

        .component-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .component-type {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            background: rgba(0,0,0,0.1);
        }

        .component-rate {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
        }

        .info-box {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: start;
        }

        .info-box i {
            font-size: 32px;
            color: var(--accent);
        }

        .info-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }

        .info-box p {
            margin: 8px 0;
            color: var(--text);
            font-size: 14px;
        }

        @media (max-width: 1200px) {
            .components-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .components-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>
