<?php
session_start();

// Allow all authenticated users to view holidays
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';

// Holiday data
$closedHolidays = [
    ['name' => 'Republic Day', 'date' => '26 January', 'day' => 'Monday'],
    ['name' => 'Holi', 'date' => '04 March', 'day' => 'Wednesday'],
    ['name' => 'Ram Navami', 'date' => '27 March', 'day' => 'Friday'],
    ['name' => 'Mahavir Jayanti', 'date' => '31 March', 'day' => 'Tuesday'],
    ['name' => 'Good Friday', 'date' => '03 April', 'day' => 'Friday'],
    ['name' => 'Mahabishuba Sankranti / Dr. B.R. Ambedkar Jayanti', 'date' => '14 April', 'day' => 'Tuesday'],
    ['name' => 'Budha Purnima', 'date' => '01 May', 'day' => 'Friday'],
    ['name' => 'Id-ul-Zuha (Bakrid)', 'date' => '27 May', 'day' => 'Wednesday'],
    ['name' => 'Muharram', 'date' => '26 June', 'day' => 'Friday'],
    ['name' => 'Rath Yatra', 'date' => '16 July', 'day' => 'Thursday'],
    ['name' => 'Milad-un-Nabi / Id-e-Milad', 'date' => '26 August', 'day' => 'Wednesday'],
    ['name' => 'Janmashtami (Vaishnava)', 'date' => '04 September', 'day' => 'Friday'],
    ['name' => "Mahatma Gandhi's Birthday", 'date' => '02 October', 'day' => 'Friday'],
    ['name' => 'Dussehra (Mahanavami)', 'date' => '19 October', 'day' => 'Monday'],
    ['name' => 'Dussehra', 'date' => '20 October', 'day' => 'Tuesday'],
    ['name' => "Guru Nanak's Birthday", 'date' => '24 November', 'day' => 'Tuesday'],
    ['name' => 'Christmas Day', 'date' => '25 December', 'day' => 'Friday'],
];

$restrictedHolidays = [
    ['name' => "New Year's Day", 'date' => '01 January', 'day' => 'Thursday'],
    ['name' => 'Makar Sankranti / Magha Bihu / Pongal', 'date' => '14 January', 'day' => 'Wednesday'],
    ['name' => 'Basanta Panchami / Sri Panchami', 'date' => '23 January', 'day' => 'Friday'],
    ['name' => 'Birthday of Swami Dayananda Saraswati', 'date' => '12 February', 'day' => 'Thursday'],
    ['name' => 'Shivaji Jayanti', 'date' => '19 February', 'day' => 'Thursday'],
    ['name' => 'Holika Dahan / Dol Yatra', 'date' => '03 March', 'day' => 'Tuesday'],
    ['name' => 'Chaitra Sukladi / Gudi Padava / Ugadi / Cheti Chand', 'date' => '19 March', 'day' => 'Thursday'],
    ['name' => 'Jamat-Ul-Vida', 'date' => '20 March', 'day' => 'Friday'],
    ['name' => 'Vaisakhadi (Bengal) / Bahag Bihu (Assam)', 'date' => '15 April', 'day' => 'Wednesday'],
    ['name' => 'Raksha Bandhan', 'date' => '28 August', 'day' => 'Friday'],
    ['name' => 'Ganesh Chaturthi / Vinayaka Chaturthi', 'date' => '14 September', 'day' => 'Monday'],
    ['name' => "Maharishi Valmiki's Birthday", 'date' => '26 October', 'day' => 'Monday'],
    ['name' => 'Karaka Chaturthi (Karwa Chouth)', 'date' => '29 October', 'day' => 'Thursday'],
    ['name' => 'Govardhan Puja', 'date' => '09 November', 'day' => 'Monday'],
    ['name' => 'Bhai Duj', 'date' => '11 November', 'day' => 'Wednesday'],
    ['name' => "Guru Teg Bahadur's Martyrdom Day", 'date' => '24 November', 'day' => 'Tuesday'],
    ['name' => "Hazarat Ali's Birthday", 'date' => '23 December', 'day' => 'Wednesday'],
    ['name' => 'Christmas Eve', 'date' => '24 December', 'day' => 'Thursday'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday Calendar 2026 - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header h1 i {
            margin-right: 12px;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .header-year {
            text-align: right;
            background: rgba(255,255,255,0.15);
            padding: 15px 30px;
            border-radius: 12px;
        }

        .header-year .year {
            font-size: 36px;
            font-weight: 700;
        }

        .header-year .label {
            font-size: 14px;
            opacity: 0.9;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.blue::before { background: linear-gradient(90deg, #3b82f6, #2563eb); }
        .stat-card.orange::before { background: linear-gradient(90deg, #f59e0b, #d97706); }
        .stat-card.purple::before { background: linear-gradient(90deg, #667eea, #764ba2); }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--muted);
            font-weight: 500;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }

        .stat-card.blue .stat-icon { background: linear-gradient(135deg, #3b82f6, #2563eb); }
        .stat-card.orange .stat-icon { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .stat-card.purple .stat-icon { background: linear-gradient(135deg, #667eea, #764ba2); }

        /* Holiday Sections */
        .holiday-section {
            background: white;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .section-header {
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            color: white;
        }

        .section-header.closed {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .section-header.restricted {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .section-header-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .section-header-text h2 {
            font-size: 20px;
            font-weight: 700;
            margin: 0 0 5px 0;
            color: white;
        }

        .section-header-text p {
            font-size: 14px;
            margin: 0;
            opacity: 0.9;
        }

        .section-note {
            padding: 15px 30px;
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
            border-left: 4px solid #f59e0b;
            color: #92400e;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        /* Holiday Table */
        .holiday-table-wrapper {
            overflow-x: auto;
        }

        .holiday-table {
            width: 100%;
            border-collapse: collapse;
        }

        .holiday-table thead {
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }

        .holiday-table thead th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .holiday-table thead th:first-child {
            width: 80px;
            text-align: center;
        }

        .holiday-table tbody tr {
            transition: all 0.2s ease;
        }

        .holiday-table tbody tr:hover {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }

        .holiday-table tbody tr:not(:last-child) {
            border-bottom: 1px solid #f1f5f9;
        }

        .holiday-table tbody td {
            padding: 18px 20px;
            color: var(--text);
            font-size: 14px;
        }

        .holiday-table tbody td:first-child {
            text-align: center;
        }

        .holiday-number {
            width: 32px;
            height: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 13px;
        }

        .holiday-name {
            font-weight: 500;
            color: var(--text);
        }

        .holiday-date {
            color: #667eea;
            font-weight: 600;
        }

        .day-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
            color: #475569;
        }

        .day-badge.weekend {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.15), rgba(118, 75, 162, 0.15));
            color: #667eea;
        }

        /* Print Button */
        .btn-print {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 14px 28px;
            border-radius: 12px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-print:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
                padding: 30px;
            }

            .header-year {
                text-align: center;
            }

            .section-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .holiday-table thead th,
            .holiday-table tbody td {
                padding: 12px 10px;
            }
        }

        /* Print Styles */
        @media print {
            body {
                background: white !important;
            }

            .sidebar, .mobile-menu-toggle, .sidebar-overlay, .btn-print {
                display: none !important;
            }

            .main-content {
                margin-left: 0 !important;
                padding: 20px !important;
            }

            .page-header {
                background: #667eea !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .holiday-section {
                page-break-inside: avoid;
                box-shadow: none;
                border: 1px solid #e2e8f0;
            }

            .section-header.closed {
                background: #3b82f6 !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .section-header.restricted {
                background: #f59e0b !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>

    <main class="main-content" id="mainContent">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-calendar-alt"></i> Holiday Calendar</h1>
                <p>NIELIT Bhubaneswar - Official Holiday List</p>
            </div>
            <div style="display: flex; align-items: center; gap: 20px;">
                <button class="btn-print" onclick="window.print()">
                    <i class="fas fa-print"></i> Print Calendar
                </button>
                <div class="header-year">
                    <div class="year">2026</div>
                    <div class="label">Calendar Year</div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card blue">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo count($closedHolidays); ?></div>
                        <div class="stat-label">Closed Holidays</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-times"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card orange">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo count($restrictedHolidays); ?></div>
                        <div class="stat-label">Restricted Holidays</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                </div>
            </div>
            <div class="stat-card purple">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo count($closedHolidays) + count($restrictedHolidays); ?></div>
                        <div class="stat-label">Total Holidays</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-calendar-star"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Closed Holidays Section -->
        <div class="holiday-section">
            <div class="section-header closed">
                <div class="section-header-icon">
                    <i class="fas fa-building-lock"></i>
                </div>
                <div class="section-header-text">
                    <h2>Closed Holidays</h2>
                    <p>Office remains closed on these days</p>
                </div>
            </div>
            <div class="holiday-table-wrapper">
                <table class="holiday-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Holiday</th>
                            <th>Date</th>
                            <th>Day</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($closedHolidays as $index => $holiday): ?>
                            <tr>
                                <td><span class="holiday-number"><?php echo $index + 1; ?></span></td>
                                <td class="holiday-name"><?php echo htmlspecialchars($holiday['name']); ?></td>
                                <td class="holiday-date"><?php echo htmlspecialchars($holiday['date']); ?></td>
                                <td>
                                    <span class="day-badge <?php echo in_array($holiday['day'], ['Saturday', 'Sunday']) ? 'weekend' : ''; ?>">
                                        <?php echo htmlspecialchars($holiday['day']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Restricted Holidays Section -->
        <div class="holiday-section">
            <div class="section-header restricted">
                <div class="section-header-icon">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <div class="section-header-text">
                    <h2>Restricted Holidays</h2>
                    <p>Optional holidays - choose any two</p>
                </div>
            </div>
            <div class="section-note">
                <i class="fas fa-info-circle"></i>
                <span><strong>Note:</strong> Employees may avail any two holidays from the restricted holiday list during the year.</span>
            </div>
            <div class="holiday-table-wrapper">
                <table class="holiday-table">
                    <thead>
                        <tr>
                            <th>S.No.</th>
                            <th>Holiday</th>
                            <th>Date</th>
                            <th>Day</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($restrictedHolidays as $index => $holiday): ?>
                            <tr>
                                <td><span class="holiday-number" style="background: linear-gradient(135deg, #f59e0b, #d97706);"><?php echo $index + 1; ?></span></td>
                                <td class="holiday-name"><?php echo htmlspecialchars($holiday['name']); ?></td>
                                <td class="holiday-date" style="color: #d97706;"><?php echo htmlspecialchars($holiday['date']); ?></td>
                                <td>
                                    <span class="day-badge <?php echo in_array($holiday['day'], ['Saturday', 'Sunday']) ? 'weekend' : ''; ?>">
                                        <?php echo htmlspecialchars($holiday['day']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

</body>
</html>
