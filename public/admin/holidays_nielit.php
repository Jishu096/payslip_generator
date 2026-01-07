<?php
session_start();

// Allow all authenticated users to view holidays
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Holiday List 2026 - NIELIT Bhubaneswar</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .page-header {
            background: white;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            text-align: center;
        }

        .page-header h1 {
            color: #2d3748;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .page-header .subtitle {
            color: #718096;
            font-size: 18px;
            font-weight: 400;
        }

        .breadcrumb {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb a:hover {
            color: #764ba2;
        }

        .breadcrumb i.fa-chevron-right {
            color: #cbd5e0;
            font-size: 10px;
        }

        .breadcrumb span {
            color: #4a5568;
        }

        .holiday-section {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 30px;
            overflow: hidden;
        }

        .section-header {
            padding: 25px 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 24px;
            font-weight: 600;
            color: white;
        }

        .section-header.closed {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
        }

        .section-header.restricted {
            background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
        }

        .section-header .icon {
            font-size: 28px;
        }

        .section-note {
            padding: 15px 30px;
            background: #fef5e7;
            border-left: 4px solid #f39c12;
            color: #856404;
            font-style: italic;
            font-size: 14px;
        }

        .holiday-table {
            width: 100%;
            border-collapse: collapse;
        }

        .holiday-table thead {
            background: #f7fafc;
        }

        .holiday-table thead th {
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .holiday-table tbody tr {
            transition: background 0.2s;
        }

        .holiday-table tbody tr:hover {
            background: #f7fafc;
        }

        .holiday-table tbody tr:not(:last-child) {
            border-bottom: 1px solid #e2e8f0;
        }

        .holiday-table tbody td {
            padding: 16px 20px;
            color: #4a5568;
            font-size: 14px;
        }

        .holiday-table tbody td:first-child {
            font-weight: 500;
            color: #2d3748;
            width: 60px;
            text-align: center;
        }

        .holiday-table tbody td:nth-child(2) {
            font-weight: 500;
            color: #2d3748;
        }

        .holiday-table tbody td:nth-child(3) {
            color: #667eea;
            font-weight: 500;
        }

        .holiday-table tbody td:nth-child(4) {
            font-weight: 400;
            color: #718096;
        }

        .day-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            background: #edf2f7;
            color: #4a5568;
        }

        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 15px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: white;
        }

        .stat-icon.closed {
            background: linear-gradient(135deg, #3182ce 0%, #2c5282 100%);
        }

        .stat-icon.restricted {
            background: linear-gradient(135deg, #f6ad55 0%, #ed8936 100%);
        }

        .stat-content h3 {
            font-size: 14px;
            color: #718096;
            font-weight: 400;
            margin-bottom: 5px;
        }

        .stat-content .number {
            font-size: 32px;
            font-weight: 700;
            color: #2d3748;
        }

        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: white;
            color: #667eea;
            text-decoration: none;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s;
            font-weight: 500;
            margin-bottom: 20px;
        }

        .back-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        @media print {
            body {
                background: white;
                padding: 0;
            }

            .breadcrumb,
            .back-button,
            .stats-container {
                display: none;
            }

            .holiday-section {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 24px;
            }

            .holiday-table {
                font-size: 12px;
            }

            .holiday-table thead th,
            .holiday-table tbody td {
                padding: 12px 10px;
            }

            .section-header {
                font-size: 18px;
                padding: 20px 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <i class="fas fa-chevron-right"></i>
            <span>Holiday List 2026</span>
        </div>

        <!-- Page Header -->
        <div class="page-header">
            <h1>
                <i class="fas fa-calendar-star"></i>
                Holiday List 2026
            </h1>
            <div class="subtitle">NIELIT Bhubaneswar</div>
        </div>

        <!-- Statistics -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-icon closed">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <div class="stat-content">
                    <h3>Closed Holidays</h3>
                    <div class="number">17</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon restricted">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="stat-content">
                    <h3>Restricted Holidays</h3>
                    <div class="number">18</div>
                </div>
            </div>
        </div>

        <!-- Closed Holidays Section -->
        <div class="holiday-section">
            <div class="section-header closed">
                <span class="icon">🟦</span>
                <span>Closed Holidays – NIELIT Bhubaneswar (2026)</span>
            </div>
            <table class="holiday-table">
                <thead>
                    <tr>
                        <th>S. No.</th>
                        <th>Holiday</th>
                        <th>Date</th>
                        <th>Day</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>Republic Day</td>
                        <td>26 January</td>
                        <td><span class="day-badge">Monday</span></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Holi</td>
                        <td>04 March</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Ram Navami</td>
                        <td>27 March</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Mahavir Jayanti</td>
                        <td>31 March</td>
                        <td><span class="day-badge">Tuesday</span></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Good Friday</td>
                        <td>03 April</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>Mahabishuba Sankranti / Dr. B.R. Ambedkar Jayanti</td>
                        <td>14 April</td>
                        <td><span class="day-badge">Tuesday</span></td>
                    </tr>
                    <tr>
                        <td>7</td>
                        <td>Budha Purnima</td>
                        <td>01 May</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>8</td>
                        <td>Id-ul-Zuha (Bakrid)</td>
                        <td>27 May</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>9</td>
                        <td>Muharram</td>
                        <td>26 June</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>10</td>
                        <td>Rath Yatra</td>
                        <td>16 July</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                    <tr>
                        <td>11</td>
                        <td>Milad-un-Nabi / Id-e-Milad (Birthday of Prophet Mohammad)</td>
                        <td>26 August</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>12</td>
                        <td>Janmashtami (Vaishnava)</td>
                        <td>04 September</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>13</td>
                        <td>Mahatma Gandhi's Birthday</td>
                        <td>02 October</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>14</td>
                        <td>Dussehra (Mahanavami)</td>
                        <td>19 October</td>
                        <td><span class="day-badge">Monday</span></td>
                    </tr>
                    <tr>
                        <td>15</td>
                        <td>Dussehra</td>
                        <td>20 October</td>
                        <td><span class="day-badge">Tuesday</span></td>
                    </tr>
                    <tr>
                        <td>16</td>
                        <td>Guru Nanak's Birthday</td>
                        <td>24 November</td>
                        <td><span class="day-badge">Tuesday</span></td>
                    </tr>
                    <tr>
                        <td>17</td>
                        <td>Christmas Day</td>
                        <td>25 December</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Restricted Holidays Section -->
        <div class="holiday-section">
            <div class="section-header restricted">
                <span class="icon">🟨</span>
                <span>Restricted Holidays – NIELIT Bhubaneswar (2026)</span>
            </div>
            <div class="section-note">
                <i class="fas fa-info-circle"></i> Any two holidays may be availed by employees from the list below.
            </div>
            <table class="holiday-table">
                <thead>
                    <tr>
                        <th>S. No.</th>
                        <th>Holiday</th>
                        <th>Date</th>
                        <th>Day</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1</td>
                        <td>New Year's Day</td>
                        <td>01 January</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                    <tr>
                        <td>2</td>
                        <td>Makar Sankranti / Magha Bihu / Pongal</td>
                        <td>14 January</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>3</td>
                        <td>Basanta Panchami / Sri Panchami</td>
                        <td>23 January</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>4</td>
                        <td>Birthday of Swami Dayananda Saraswati</td>
                        <td>12 February</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                    <tr>
                        <td>5</td>
                        <td>Shivaji Jayanti</td>
                        <td>19 February</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                    <tr>
                        <td>6</td>
                        <td>Holika Dahan / Dol Yatra</td>
                        <td>03 March</td>
                        <td><span class="day-badge">Tuesday</span></td>
                    </tr>
                    <tr>
                        <td>7</td>
                        <td>Chaitra Sukladi / Gudi Padava / Ugadi / Cheti Chand</td>
                        <td>19 March</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                    <tr>
                        <td>8</td>
                        <td>Jamat-Ul-Vida</td>
                        <td>20 March</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>9</td>
                        <td>Vaisakhadi (Bengal) / Bahag Bihu (Assam)</td>
                        <td>15 April</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>10</td>
                        <td>Raksha Bandhan</td>
                        <td>28 August</td>
                        <td><span class="day-badge">Friday</span></td>
                    </tr>
                    <tr>
                        <td>11</td>
                        <td>Ganesh Chaturthi / Vinayaka Chaturthi</td>
                        <td>14 September</td>
                        <td><span class="day-badge">Monday</span></td>
                    </tr>
                    <tr>
                        <td>12</td>
                        <td>Maharishi Valmiki's Birthday</td>
                        <td>26 October</td>
                        <td><span class="day-badge">Monday</span></td>
                    </tr>
                    <tr>
                        <td>13</td>
                        <td>Karaka Chaturthi (Karwa Chouth)</td>
                        <td>29 October</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                    <tr>
                        <td>14</td>
                        <td>Govardhan Puja</td>
                        <td>09 November</td>
                        <td><span class="day-badge">Monday</span></td>
                    </tr>
                    <tr>
                        <td>15</td>
                        <td>Bhai Duj</td>
                        <td>11 November</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>16</td>
                        <td>Guru Teg Bahadur's Martyrdom Day</td>
                        <td>24 November</td>
                        <td><span class="day-badge">Tuesday</span></td>
                    </tr>
                    <tr>
                        <td>17</td>
                        <td>Hazarat Ali's Birthday</td>
                        <td>23 December</td>
                        <td><span class="day-badge">Wednesday</span></td>
                    </tr>
                    <tr>
                        <td>18</td>
                        <td>Christmas Eve</td>
                        <td>24 December</td>
                        <td><span class="day-badge">Thursday</span></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <a href="admin_dashboard.php" class="back-button">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</body>
</html>
