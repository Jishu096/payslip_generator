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

// Get all permanent employees with salary structure and Pay Level
try {
    $stmt = $db->query("
        SELECT 
            e.employee_id,
            e.employee_code,
            e.full_name,
            e.designation,
            e.basic_salary,
            e.pay_level_id,
            e.hra_type,
            e.employment_type,
            d.department_name,
            pl.level_name,
            pl.level_number,
            pl.transport_allowance
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.department_id
        LEFT JOIN pay_levels pl ON e.pay_level_id = pl.level_id
        WHERE e.employment_type = 'permanent'
        AND e.status = 'active'
        AND e.deleted_at IS NULL
        ORDER BY pl.level_number DESC, e.full_name
    ");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $employees = [];
}

// Get salary components
try {
    $stmt = $db->query("SELECT * FROM salary_components WHERE is_active = 1 ORDER BY display_order");
    $salaryComponents = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $salaryComponents = [];
}

// Get Pay Levels summary
try {
    $stmt = $db->query("SELECT level_name, level_number, min_basic, max_basic, transport_allowance, description FROM pay_levels WHERE is_active = 1 ORDER BY level_number");
    $payLevels = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $payLevels = [];
}

// HRA percentages by city category
$hraRates = [
    'city_a' => 24,  // Metro cities
    'city_b' => 16,  // Other major cities (default)
    'city_c' => 8    // Rest of cities
];

// Standard rates (7th CPC)
$daRate = 58;    // DA = 58% of Basic
$epfRate = 12;   // EPF = 12% of Basic
$npsRate = 10;   // NPS = 10% of Basic
$professionalTax = 200; // Fixed

// Calculate total payable for all employees
$totalPayable = 0;
$totalGross = 0;
$totalDeductions = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Structure (7th CPC) - e-HRMS</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        :root {
            --bg: #f1f5f9;
            --card: #ffffff;
            --accent: #667eea;
            --accent-2: #764ba2;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        .page-container {
            padding: 30px;
            max-width: 100%;
        }

        /* Hero Section */
        .hero-section {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 20px;
            padding: 35px 40px;
            color: white;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .hero-section h1 {
            font-size: 28px;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .hero-section p {
            opacity: 0.9;
            font-size: 15px;
            margin-bottom: 25px;
        }

        .hero-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
        }

        .hero-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }

        .hero-stat-value {
            font-size: 24px;
            font-weight: 800;
        }

        .hero-stat-label {
            font-size: 12px;
            opacity: 0.9;
            margin-top: 5px;
        }

        /* Components Card */
        .components-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        .components-card {
            background: var(--card);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .components-card h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .components-card h2 i {
            color: var(--accent);
        }

        .earning-item, .deduction-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 16px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s;
        }

        .earning-item {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            border-left: 4px solid var(--success);
        }

        .deduction-item {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            border-left: 4px solid var(--danger);
        }

        .earning-item:hover, .deduction-item:hover {
            transform: translateX(5px);
        }

        .component-name {
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }

        .component-rate {
            font-weight: 700;
            font-size: 14px;
            padding: 5px 12px;
            border-radius: 20px;
            background: rgba(255,255,255,0.7);
        }

        .earning-item .component-rate {
            color: #059669;
        }

        .deduction-item .component-rate {
            color: #dc2626;
        }

        /* Salary Table */
        .table-card {
            background: var(--card);
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .table-header h2 i {
            color: var(--accent);
        }

        .table-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 10px 20px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-outline {
            background: transparent;
            border: 2px solid var(--border);
            color: var(--text);
        }

        .btn-outline:hover {
            border-color: var(--accent);
            color: var(--accent);
        }

        /* Salary Table Design */
        .salary-table-container {
            overflow-x: auto;
            border-radius: 12px;
            border: 1px solid var(--border);
        }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            min-width: 1400px;
        }

        .salary-table thead {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .salary-table thead th {
            padding: 14px 10px;
            font-weight: 600;
            text-align: center;
            font-size: 12px;
            white-space: nowrap;
            border-right: 1px solid rgba(255,255,255,0.2);
        }

        .salary-table thead th:first-child {
            text-align: left;
            padding-left: 15px;
        }

        .salary-table thead th:last-child {
            border-right: none;
        }

        .salary-table thead tr:first-child th {
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }

        .salary-table tbody tr {
            transition: all 0.2s;
        }

        .salary-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        .salary-table tbody tr:hover {
            background: #e0e7ff;
        }

        .salary-table tbody td {
            padding: 12px 10px;
            text-align: center;
            border-bottom: 1px solid var(--border);
            border-right: 1px solid var(--border);
        }

        .salary-table tbody td:first-child {
            text-align: left;
            padding-left: 15px;
        }

        .salary-table tbody td:last-child {
            border-right: none;
        }

        .emp-info {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .emp-name {
            font-weight: 700;
            color: var(--text);
            font-size: 13px;
        }

        .emp-code {
            font-size: 11px;
            color: var(--muted);
        }

        .emp-designation {
            font-size: 11px;
            color: var(--accent);
            font-weight: 500;
        }

        .pay-level-badge {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
        }

        .amount-cell {
            font-family: 'Roboto Mono', monospace;
            font-weight: 600;
            font-size: 12px;
        }

        .earning-cell {
            color: #059669;
            background: rgba(16, 185, 129, 0.05);
        }

        .deduction-cell {
            color: #dc2626;
            background: rgba(239, 68, 68, 0.05);
        }

        .total-cell {
            font-weight: 700;
            font-size: 13px;
        }

        .total-gross {
            color: #0d9488;
            background: rgba(13, 148, 136, 0.1) !important;
        }

        .total-deductions {
            color: #dc2626;
            background: rgba(239, 68, 68, 0.1) !important;
        }

        .net-salary-cell {
            color: var(--accent);
            background: rgba(102, 126, 234, 0.1) !important;
            font-weight: 800;
            font-size: 14px;
        }

        /* Column Groups */
        .col-earnings {
            background: rgba(16, 185, 129, 0.08);
        }

        .col-deductions {
            background: rgba(239, 68, 68, 0.08);
        }

        /* Footer Row */
        .salary-table tfoot {
            background: linear-gradient(135deg, #1e293b, #334155);
            color: white;
        }

        .salary-table tfoot td {
            padding: 14px 10px;
            font-weight: 700;
            border: none;
            text-align: center;
        }

        .salary-table tfoot td:first-child {
            text-align: left;
            padding-left: 15px;
        }

        /* Pay Levels Reference */
        .pay-levels-card {
            background: var(--card);
            border-radius: 16px;
            padding: 25px;
            margin-top: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }

        .pay-levels-card h2 {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .pay-levels-card h2 i {
            color: var(--accent);
        }

        .pay-levels-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }

        .pay-level-card {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border: 2px solid var(--border);
            border-radius: 12px;
            padding: 15px;
            transition: all 0.3s;
        }

        .pay-level-card:hover {
            border-color: var(--accent);
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.15);
        }

        .pay-level-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .pay-level-name {
            font-weight: 700;
            color: var(--accent);
            font-size: 14px;
        }

        .pay-level-ta {
            font-size: 11px;
            padding: 3px 8px;
            background: var(--accent);
            color: white;
            border-radius: 10px;
        }

        .pay-level-range {
            font-size: 12px;
            color: var(--text);
            margin-bottom: 5px;
        }

        .pay-level-desc {
            font-size: 11px;
            color: var(--muted);
        }

        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border-radius: 12px;
            padding: 20px 25px;
            display: flex;
            gap: 15px;
            align-items: start;
            margin-top: 25px;
        }

        .info-box i {
            font-size: 24px;
            color: var(--accent);
        }

        .info-box h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 8px;
        }

        .info-box p {
            font-size: 13px;
            color: var(--text);
            margin: 4px 0;
        }

        .info-box code {
            background: rgba(102, 126, 234, 0.1);
            padding: 2px 8px;
            border-radius: 4px;
            font-family: 'Roboto Mono', monospace;
            font-size: 12px;
        }

        @media (max-width: 1200px) {
            .components-section {
                grid-template-columns: 1fr;
            }
            .hero-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .page-container {
                padding: 15px;
            }
            .hero-section {
                padding: 25px;
            }
            .hero-section h1 {
                font-size: 22px;
            }
            .hero-stats {
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            .pay-levels-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media print {
            .hero-section, .components-section, .pay-levels-card, .info-box, .table-actions {
                display: none !important;
            }
            .table-card {
                box-shadow: none;
                padding: 0;
            }
            .salary-table {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="page-container">
            <!-- Hero Section -->
            <div class="hero-section">
                <h1><i class="fas fa-money-bill-wave"></i> Salary Structure (7th CPC)</h1>
                <p>Permanent employees salary breakdown as per 7th Central Pay Commission guidelines</p>
                
                <div class="hero-stats">
                    <?php
                    $totalBasic = 0;
                    $totalNetSalary = 0;
                    foreach ($employees as $emp) {
                        $basic = floatval($emp['basic_salary'] ?? 0);
                        $hraType = $emp['hra_type'] ?? 'city_b';
                        $hraPercent = $hraRates[$hraType] ?? 16;
                        $ta = floatval($emp['transport_allowance'] ?? 0);
                        
                        $da = round($basic * ($daRate / 100), 2);
                        $hra = round($basic * ($hraPercent / 100), 2);
                        $daOnTa = round($ta * ($daRate / 100), 2);
                        $gross = $basic + $da + $hra + $ta + $daOnTa;
                        
                        $epf = round($basic * ($epfRate / 100), 2);
                        $nps = round($basic * ($npsRate / 100), 2);
                        $deductions = $epf + $nps + $professionalTax;
                        
                        $net = $gross - $deductions;
                        $totalBasic += $basic;
                        $totalNetSalary += $net;
                    }
                    ?>
                    <div class="hero-stat">
                        <div class="hero-stat-value"><?php echo count($employees); ?></div>
                        <div class="hero-stat-label">Permanent Employees</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">₹<?php echo number_format($totalBasic); ?></div>
                        <div class="hero-stat-label">Total Basic Pay</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value"><?php echo $daRate; ?>%</div>
                        <div class="hero-stat-label">Current DA Rate</div>
                    </div>
                    <div class="hero-stat">
                        <div class="hero-stat-value">₹<?php echo number_format($totalNetSalary); ?></div>
                        <div class="hero-stat-label">Total Net Payable</div>
                    </div>
                </div>
            </div>

            <!-- Salary Components -->
            <div class="components-section">
                <div class="components-card">
                    <h2><i class="fas fa-plus-circle"></i> Earnings (Allowances)</h2>
                    <div class="earning-item">
                        <span class="component-name">Basic Pay</span>
                        <span class="component-rate">As per Pay Level</span>
                    </div>
                    <div class="earning-item">
                        <span class="component-name">Dearness Allowance (DA)</span>
                        <span class="component-rate"><?php echo $daRate; ?>% of Basic</span>
                    </div>
                    <div class="earning-item">
                        <span class="component-name">House Rent Allowance (HRA)</span>
                        <span class="component-rate">24% / 16% / 8%</span>
                    </div>
                    <div class="earning-item">
                        <span class="component-name">Transport Allowance (TA)</span>
                        <span class="component-rate">As per Pay Level</span>
                    </div>
                    <div class="earning-item">
                        <span class="component-name">DA on Transport Allowance</span>
                        <span class="component-rate"><?php echo $daRate; ?>% of TA</span>
                    </div>
                    <div class="earning-item">
                        <span class="component-name">Canteen Subsidy</span>
                        <span class="component-rate">As Applicable</span>
                    </div>
                </div>

                <div class="components-card">
                    <h2><i class="fas fa-minus-circle"></i> Deductions</h2>
                    <div class="deduction-item">
                        <span class="component-name">Employee Provident Fund (EPF)</span>
                        <span class="component-rate"><?php echo $epfRate; ?>% of Basic</span>
                    </div>
                    <div class="deduction-item">
                        <span class="component-name">New Pension Scheme (NPS)</span>
                        <span class="component-rate"><?php echo $npsRate; ?>% of Basic</span>
                    </div>
                    <div class="deduction-item">
                        <span class="component-name">Central Provident Fund (CPF)</span>
                        <span class="component-rate">As Applicable</span>
                    </div>
                    <div class="deduction-item">
                        <span class="component-name">Professional Tax</span>
                        <span class="component-rate">₹<?php echo number_format($professionalTax); ?></span>
                    </div>
                    <div class="deduction-item">
                        <span class="component-name">Sudexo (Meal Card)</span>
                        <span class="component-rate">As Applicable</span>
                    </div>
                    <div class="deduction-item">
                        <span class="component-name">Income Tax (TDS)</span>
                        <span class="component-rate">As per Slab</span>
                    </div>
                </div>
            </div>

            <!-- Salary Table -->
            <div class="table-card">
                <div class="table-header">
                    <h2><i class="fas fa-table"></i> Employee Salary Structure</h2>
                    <div class="table-actions">
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class="fas fa-print"></i> Print
                        </button>
                        <button class="btn btn-primary" onclick="exportToExcel()">
                            <i class="fas fa-file-excel"></i> Export Excel
                        </button>
                    </div>
                </div>

                <div class="salary-table-container">
                    <table class="salary-table" id="salaryTable">
                        <thead>
                            <tr>
                                <th rowspan="2" style="min-width: 180px;">Employee Details</th>
                                <th rowspan="2">Pay Level</th>
                                <th rowspan="2">Basic Pay</th>
                                <th colspan="5" class="col-earnings">Earnings</th>
                                <th rowspan="2" class="total-gross">Total Salary</th>
                                <th colspan="6" class="col-deductions">Deductions</th>
                                <th rowspan="2" class="total-deductions">Total Ded.</th>
                                <th rowspan="2" class="net-salary-cell">Payable</th>
                            </tr>
                            <tr>
                                <th class="col-earnings">DA (<?php echo $daRate; ?>%)</th>
                                <th class="col-earnings">HRA</th>
                                <th class="col-earnings">TA</th>
                                <th class="col-earnings">DA on TA</th>
                                <th class="col-earnings">Canteen</th>
                                <th class="col-deductions">EPF</th>
                                <th class="col-deductions">NPS</th>
                                <th class="col-deductions">CPF</th>
                                <th class="col-deductions">P.Tax</th>
                                <th class="col-deductions">Sudexo</th>
                                <th class="col-deductions">I.Tax</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $grandTotalBasic = 0;
                            $grandTotalDA = 0;
                            $grandTotalHRA = 0;
                            $grandTotalTA = 0;
                            $grandTotalDAonTA = 0;
                            $grandTotalCanteen = 0;
                            $grandTotalGross = 0;
                            $grandTotalEPF = 0;
                            $grandTotalNPS = 0;
                            $grandTotalCPF = 0;
                            $grandTotalPTax = 0;
                            $grandTotalSudexo = 0;
                            $grandTotalITax = 0;
                            $grandTotalDeductions = 0;
                            $grandTotalNet = 0;

                            foreach ($employees as $index => $emp): 
                                $basic = floatval($emp['basic_salary'] ?? 0);
                                $hraType = $emp['hra_type'] ?? 'city_b';
                                $hraPercent = $hraRates[$hraType] ?? 16;
                                $ta = floatval($emp['transport_allowance'] ?? 0);
                                $canteen = 0; // Can be set per employee
                                
                                // Earnings
                                $da = round($basic * ($daRate / 100), 2);
                                $hra = round($basic * ($hraPercent / 100), 2);
                                $daOnTa = round($ta * ($daRate / 100), 2);
                                $gross = $basic + $da + $hra + $ta + $daOnTa + $canteen;
                                
                                // Deductions (basic calculations - can be overridden per employee)
                                $epf = round($basic * ($epfRate / 100), 2);
                                $nps = round($basic * ($npsRate / 100), 2);
                                $cpf = 0;
                                $sudexo = 0;
                                $iTax = 0;
                                $deductions = $epf + $nps + $cpf + $professionalTax + $sudexo + $iTax;
                                
                                $net = $gross - $deductions;

                                // Accumulate totals
                                $grandTotalBasic += $basic;
                                $grandTotalDA += $da;
                                $grandTotalHRA += $hra;
                                $grandTotalTA += $ta;
                                $grandTotalDAonTA += $daOnTa;
                                $grandTotalCanteen += $canteen;
                                $grandTotalGross += $gross;
                                $grandTotalEPF += $epf;
                                $grandTotalNPS += $nps;
                                $grandTotalCPF += $cpf;
                                $grandTotalPTax += $professionalTax;
                                $grandTotalSudexo += $sudexo;
                                $grandTotalITax += $iTax;
                                $grandTotalDeductions += $deductions;
                                $grandTotalNet += $net;
                            ?>
                            <tr>
                                <td>
                                    <div class="emp-info">
                                        <span class="emp-name"><?php echo htmlspecialchars($emp['full_name']); ?></span>
                                        <span class="emp-code"><?php echo htmlspecialchars($emp['employee_code'] ?? ''); ?></span>
                                        <span class="emp-designation"><?php echo htmlspecialchars($emp['designation'] ?? ''); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($emp['level_name']): ?>
                                        <span class="pay-level-badge"><?php echo htmlspecialchars($emp['level_name']); ?></span>
                                    <?php else: ?>
                                        <span style="color: var(--muted);">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="amount-cell">₹<?php echo number_format($basic); ?></td>
                                <td class="amount-cell earning-cell">₹<?php echo number_format($da); ?></td>
                                <td class="amount-cell earning-cell">₹<?php echo number_format($hra); ?></td>
                                <td class="amount-cell earning-cell">₹<?php echo number_format($ta); ?></td>
                                <td class="amount-cell earning-cell">₹<?php echo number_format($daOnTa); ?></td>
                                <td class="amount-cell earning-cell">₹<?php echo number_format($canteen); ?></td>
                                <td class="amount-cell total-cell total-gross">₹<?php echo number_format($gross); ?></td>
                                <td class="amount-cell deduction-cell">₹<?php echo number_format($epf); ?></td>
                                <td class="amount-cell deduction-cell">₹<?php echo number_format($nps); ?></td>
                                <td class="amount-cell deduction-cell">₹<?php echo number_format($cpf); ?></td>
                                <td class="amount-cell deduction-cell">₹<?php echo number_format($professionalTax); ?></td>
                                <td class="amount-cell deduction-cell">₹<?php echo number_format($sudexo); ?></td>
                                <td class="amount-cell deduction-cell">₹<?php echo number_format($iTax); ?></td>
                                <td class="amount-cell total-cell total-deductions">₹<?php echo number_format($deductions); ?></td>
                                <td class="amount-cell total-cell net-salary-cell">₹<?php echo number_format($net); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="2" style="text-align: left;"><strong>GRAND TOTAL</strong></td>
                                <td>₹<?php echo number_format($grandTotalBasic); ?></td>
                                <td>₹<?php echo number_format($grandTotalDA); ?></td>
                                <td>₹<?php echo number_format($grandTotalHRA); ?></td>
                                <td>₹<?php echo number_format($grandTotalTA); ?></td>
                                <td>₹<?php echo number_format($grandTotalDAonTA); ?></td>
                                <td>₹<?php echo number_format($grandTotalCanteen); ?></td>
                                <td style="color: #10b981;">₹<?php echo number_format($grandTotalGross); ?></td>
                                <td>₹<?php echo number_format($grandTotalEPF); ?></td>
                                <td>₹<?php echo number_format($grandTotalNPS); ?></td>
                                <td>₹<?php echo number_format($grandTotalCPF); ?></td>
                                <td>₹<?php echo number_format($grandTotalPTax); ?></td>
                                <td>₹<?php echo number_format($grandTotalSudexo); ?></td>
                                <td>₹<?php echo number_format($grandTotalITax); ?></td>
                                <td style="color: #ef4444;">₹<?php echo number_format($grandTotalDeductions); ?></td>
                                <td style="color: #60a5fa; font-size: 15px;">₹<?php echo number_format($grandTotalNet); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <!-- Pay Levels Reference -->
            <div class="pay-levels-card">
                <h2><i class="fas fa-layer-group"></i> Pay Level Reference (7th CPC)</h2>
                <div class="pay-levels-grid">
                    <?php foreach ($payLevels as $level): ?>
                    <div class="pay-level-card">
                        <div class="pay-level-header">
                            <span class="pay-level-name"><?php echo htmlspecialchars($level['level_name']); ?></span>
                            <span class="pay-level-ta">TA: ₹<?php echo number_format($level['transport_allowance']); ?></span>
                        </div>
                        <div class="pay-level-range">
                            ₹<?php echo number_format($level['min_basic']); ?> - ₹<?php echo number_format($level['max_basic']); ?>
                        </div>
                        <div class="pay-level-desc"><?php echo htmlspecialchars($level['description'] ?? ''); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Info Box -->
            <div class="info-box">
                <i class="fas fa-info-circle"></i>
                <div>
                    <h3>7th CPC Salary Calculation Formula</h3>
                    <p><strong>Total Salary</strong> = Basic + DA (<code><?php echo $daRate; ?>%</code>) + HRA (<code>24%/16%/8%</code>) + TA + DA on TA + Canteen</p>
                    <p><strong>Total Deductions</strong> = EPF (<code><?php echo $epfRate; ?>%</code>) + NPS (<code><?php echo $npsRate; ?>%</code>) + CPF + P.Tax (<code>₹<?php echo $professionalTax; ?></code>) + Sudexo + Income Tax</p>
                    <p><strong>Payable Salary</strong> = Total Salary - Total Deductions</p>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>

    <script>
        function exportToExcel() {
            const table = document.getElementById('salaryTable');
            const ws = XLSX?.utils?.table_to_sheet(table);
            
            if (typeof XLSX === 'undefined') {
                // Fallback: simple CSV export
                let csv = [];
                const rows = table.querySelectorAll('tr');
                rows.forEach(row => {
                    const cells = row.querySelectorAll('th, td');
                    const rowData = Array.from(cells).map(cell => '"' + cell.innerText.replace(/"/g, '""') + '"');
                    csv.push(rowData.join(','));
                });
                
                const blob = new Blob([csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = 'Salary_Structure_7thCPC.csv';
                link.click();
            } else {
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Salary Structure');
                XLSX.writeFile(wb, 'Salary_Structure_7thCPC.xlsx');
            }
        }
    </script>
</body>
</html>
