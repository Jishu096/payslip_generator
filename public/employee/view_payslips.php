<?php
session_start();

$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Models/Payslip.php";

$employeeId = $_SESSION['employee_id'];
$payslipModel = new Payslip();
$payslips = $payslipModel->getPayslipsByEmployee($employeeId);

$pageTitle = "My Payslips";
include 'includes/header.php';
?>

<!-- Header Section -->
<div class="glass-card bg-indigo" style="margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; padding: 2rem;">
    <div>
        <h2 style="font-size: 1.5rem; font-weight: 700; color: #4338ca; margin-bottom: 0.5rem;">Payslip Documents</h2>
        <p style="color: #6366f1; opacity: 0.9;">View and download your monthly salary slips.</p>
    </div>
    <div style="font-size: 3rem; opacity: 0.2; color: #4338ca;">
        <i class="fas fa-file-invoice-dollar"></i>
    </div>
</div>

<!-- Payslips List -->
<div class="glass-card">
    <?php if (empty($payslips)): ?>
        <div style="text-align:center; padding: 4rem 1rem; color:var(--text-secondary);">
            <i class="fas fa-search-dollar" style="font-size: 3rem; margin-bottom: 1rem; opacity:0.3;"></i>
            <p>No payslips have been generated for you yet.</p>
        </div>
    <?php else: ?>
        <div style="overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:0.95rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0;">
                        <th style="text-align:left; padding:1.25rem; color:var(--text-secondary); font-weight:600;">Period</th>
                        <th style="text-align:left; padding:1.25rem; color:var(--text-secondary); font-weight:600;">Generated Date</th>
                        <th style="text-align:left; padding:1.25rem; color:var(--text-secondary); font-weight:600;">Net Salary</th>
                        <th style="text-align:right; padding:1.25rem; color:var(--text-secondary); font-weight:600;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $monthNames = [
                        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                    ];

                    foreach ($payslips as $slip): 
                        $monthName = $monthNames[(int)$slip['month']] ?? $slip['month'];
                    ?>
                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.2s;" onmouseover="this.style.background='rgba(241, 245, 249, 0.5)'" onmouseout="this.style.background='transparent'">
                        <td style="padding:1.25rem;">
                            <div style="display:flex; align-items:center; gap:0.75rem;">
                                <div style="width:40px; height:40px; background:#e0e7ff; border-radius:8px; display:flex; align-items:center; justify-content:center; color:#4338ca; font-weight:600;">
                                    <?= substr($monthName, 0, 3) ?>
                                </div>
                                <span style="font-weight:600; color:var(--text-primary);"><?= $monthName ?> <?= $slip['year'] ?></span>
                            </div>
                        </td>
                        <td style="padding:1.25rem; color:var(--text-secondary);">
                            <i class="far fa-clock" style="margin-right:5px;"></i>
                            <?= date('M d, Y', strtotime($slip['generated_at'])) ?>
                        </td>
                        <td style="padding:1.25rem; font-weight:600; font-family:monospace;">
                            ₹<?= number_format($slip['net_salary'] ?? 0, 2) ?>
                        </td>
                        <td style="padding:1.25rem; text-align:right;">
                            <?php if (!empty($slip['file_path'])): ?>
                                <a href="../../storage/payslips/<?= htmlspecialchars($slip['file_path']) ?>" target="_blank" class="btn btn-primary" style="padding:0.5rem 1rem; font-size:0.875rem;">
                                    <i class="fas fa-download"></i> Download
                                </a>
                            <?php else: ?>
                                <a href="../accountant/generate_payslip_pdf.php?payslip_id=<?= $slip['payslip_id'] ?>" target="_blank" class="btn btn-outline" style="padding:0.5rem 1rem; font-size:0.875rem;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
