<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
    :root {
        --bg: #f8fafc;
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

    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Roboto', sans-serif;
        background: var(--bg);
        color: var(--text);
    }

    .sidebar {
        width: 260px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        height: 100vh;
        padding: 0;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        text-align: center;
    }

    .sidebar-logo {
        width: 80px;
        height: 80px;
        border-radius: 12px;
        object-fit: contain;
        margin-bottom: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        background: white;
        padding: 8px;
    }

    .sidebar-icon {
        font-size: 40px;
        margin-bottom: 12px;
        opacity: 0.9;
    }

    .sidebar-header h3 {
        font-size: 18px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .sidebar-header p {
        font-size: 12px;
        opacity: 0.8;
    }

    .sidebar-menu {
        padding: 20px 15px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        text-decoration: none;
        padding: 12px 15px;
        margin-bottom: 8px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    }

    .sidebar-menu a i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .sidebar-menu a:hover {
        background: rgba(255,255,255,0.15);
        transform: translateX(5px);
    }

    .sidebar-menu a.active {
        background: rgba(255,255,255,0.25);
        font-weight: 600;
    }

    .sidebar-footer {
        padding: 20px 15px;
        border-top: 1px solid rgba(255,255,255,0.1);
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .sidebar-footer .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .sidebar-footer .user-info i {
        font-size: 18px;
    }

    .sidebar-footer .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 12px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }

    .sidebar-footer .logout-btn:hover {
        background: rgba(239, 68, 68, 1);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .main-content {
        margin-left: 260px;
        padding: 30px;
        min-height: 100vh;
    }

    .content-header {
        background: white;
        padding: 25px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .content-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .content-header h1 i {
        color: var(--accent);
    }

    .content-header p {
        color: var(--muted);
        font-size: 14px;
        margin-top: 5px;
    }

    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1100;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        border: none;
        width: 45px;
        height: 45px;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    .mobile-menu-toggle i {
        font-size: 20px;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        opacity: 1;
    }

    /* Tablet Responsive */
    @media (max-width: 1199px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Mobile & Tablet - Sidebar Toggle */
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-overlay {
            display: block;
        }

        .sidebar {
            position: fixed;
            left: -260px;
            transition: left 0.3s ease;
            z-index: 1001;
        }

        .sidebar.active {
            left: 0;
        }

        .main-content {
            margin-left: 0;
            padding: 80px 20px 20px 20px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .profile-banner {
            padding: 30px 20px;
        }

        .content-header {
            flex-direction: column;
            gap: 15px;
        }

        .content-header h1 {
            font-size: 22px;
        }

        table {
            font-size: 13px;
        }

        .stat-card {
            padding: 20px;
        }
    }

    /* Small Mobile */
    @media (max-width: 480px) {
        .main-content {
            padding: 70px 15px 15px 15px;
        }

        .profile-banner {
            padding: 25px 15px;
        }

        .content-header h1 {
            font-size: 20px;
        }

        table {
            font-size: 12px;
        }
    }

    /* Page Header */
    .page-header {
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.15);
    }

    .page-header h1 {
        font-size: 28px;
        font-weight: 700;
        margin: 0;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .page-header h1 i {
        font-size: 26px;
    }

    .breadcrumb {
        font-size: 14px;
        margin-bottom: 12px;
        opacity: 0.95;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .breadcrumb a {
        color: white;
        text-decoration: none;
        transition: all 0.2s;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .breadcrumb a:hover {
        opacity: 0.8;
        transform: translateX(2px);
    }

    .breadcrumb i.fa-chevron-right {
        font-size: 10px;
        opacity: 0.7;
    }

    .breadcrumb span {
        opacity: 0.9;
    }

    /* Card Styles */
    .card {
        background: white;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        margin-bottom: 25px;
        overflow: hidden;
        position: relative;
    }

    .card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .card-header {
        padding: 20px 30px;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        background: rgba(102, 126, 234, 0.02);
    }

    .card-header h2 {
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
        margin: 0;
    }

    .card-header i {
        color: var(--accent);
        font-size: 20px;
    }

    .card-body {
        padding: 30px;
    }

    /* Table Styles */
    .table-wrapper {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
    }

    th {
        padding: 16px 20px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        color: var(--text);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        border-bottom: 2px solid var(--border);
    }

    td {
        padding: 18px 20px;
        border-bottom: 1px solid var(--border);
        font-size: 14px;
        color: var(--text);
    }

    tbody tr {
        transition: all 0.2s ease;
    }

    tbody tr:hover {
        background: rgba(102, 126, 234, 0.03);
    }

    /* Form Styles */
    .form-group {
        margin-bottom: 20px;
    }

    .form-label,
    label {
        display: block;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 8px;
        font-size: 14px;
    }

    .form-control,
    input[type="text"],
    input[type="email"],
    input[type="number"],
    input[type="date"],
    input[type="password"],
    select,
    textarea {
        width: 100%;
        padding: 12px 15px;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.2s ease;
        background: white;
        color: var(--text);
        font-family: 'Roboto', sans-serif;
    }

    .form-control:focus,
    input:focus,
    select:focus,
    textarea:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    textarea {
        resize: vertical;
        min-height: 100px;
    }

    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
    }

    /* Button Styles */
    .btn,
    .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        padding: 12px 24px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .btn:hover,
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }

    .btn-success {
        background: linear-gradient(135deg, var(--success), #059669);
    }

    .btn-danger {
        background: linear-gradient(135deg, var(--danger), #dc2626);
    }

    .btn-warning {
        background: linear-gradient(135deg, var(--warning), #d97706);
    }

    /* Alert Styles */
    .alert {
        padding: 15px 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 14px;
        border-left: 4px solid;
    }

    .alert i {
        font-size: 18px;
    }

    .alert-success {
        background: #dcfce7;
        color: #166534;
        border-color: #10b981;
    }

    .alert-error,
    .alert-danger {
        background: #fee2e2;
        color: #991b1b;
        border-color: #ef4444;
    }

    .alert-warning {
        background: #fef3c7;
        color: #92400e;
        border-color: #f59e0b;
    }

    .alert-info {
        background: #dbeafe;
        color: #1e40af;
        border-color: #3b82f6;
    }

    /* Section Divider */
    .section-divider {
        font-size: 14px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 15px 0 10px;
        margin: 20px 0 15px;
        border-bottom: 2px solid var(--border);
    }

    /* Grid Layout */
    .grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }

    /* Stat Cards */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.15);
    }

    .stat-label {
        font-size: 13px;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 8px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-subtext {
        font-size: 13px;
        color: var(--muted);
    }

    .stat-icon {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 50px;
        height: 50px;
        border-radius: 12px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        color: var(--accent);
    }

    /* Profile Page Styles */
    .profile-header {
        background: white;
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        display: flex;
        align-items: center;
        gap: 25px;
        position: relative;
        overflow: hidden;
    }

    .profile-header::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 40px;
        font-weight: 700;
        flex-shrink: 0;
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .profile-details {
        flex: 1;
    }

    .profile-details h2 {
        font-size: 26px;
        font-weight: 700;
        color: var(--text);
        margin-bottom: 10px;
    }

    .profile-details p {
        color: var(--muted);
        font-size: 14px;
        margin-bottom: 6px;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .profile-details p i {
        color: var(--accent);
        width: 18px;
    }

    .section-card {
        background: white;
        border-radius: 15px;
        padding: 0;
        margin-bottom: 25px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        overflow: hidden;
        position: relative;
    }

    .section-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .section-header {
        padding: 20px 30px;
        background: rgba(102, 126, 234, 0.02);
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 18px;
        font-weight: 700;
        color: var(--text);
    }

    .section-header i {
        color: var(--accent);
        font-size: 20px;
    }

    .section-body {
        padding: 30px;
    }

    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 25px;
    }

    .info-item {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }

    .info-item label {
        font-size: 12px;
        font-weight: 600;
        color: var(--muted);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0;
    }

    .info-item .value {
        font-size: 15px;
        font-weight: 500;
        color: var(--text);
        padding: 10px 12px;
        background: rgba(102, 126, 234, 0.03);
        border-radius: 8px;
        border: 1px solid rgba(102, 126, 234, 0.1);
    }

    /* Calendar Styles */
    .calendar-wrapper {
        background: white;
        border-radius: 15px;
        padding: 30px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        position: relative;
        overflow: hidden;
    }

    .calendar-wrapper::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .calendar-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        flex-wrap: wrap;
        gap: 15px;
    }

    .month-navigation h2 {
        font-size: 24px;
        color: var(--text);
        font-weight: 700;
        margin: 0;
    }

    .month-nav-buttons {
        display: flex;
        gap: 10px;
    }

    .month-nav-buttons a,
    .calendar-wrapper .btn {
        padding: 10px 18px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .month-nav-buttons a:hover,
    .calendar-wrapper .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }

    .calendar-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 10px;
        margin-top: 20px;
    }

    .calendar-day-header {
        text-align: center;
        padding: 12px;
        font-weight: 600;
        color: white;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        border-radius: 8px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .calendar-day {
        min-height: 80px;
        border: 1px solid var(--border);
        border-radius: 8px;
        padding: 10px;
        background: white;
        transition: all 0.3s;
    }

    .calendar-day:hover {
        border-color: var(--accent);
        box-shadow: 0 2px 10px rgba(102, 126, 234, 0.15);
        transform: translateY(-2px);
    }

    .calendar-day.empty {
        background: #f8f9fa;
        opacity: 0.5;
    }

    .day-number {
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--text);
        font-size: 14px;
    }

    .day-status {
        font-size: 11px;
        padding: 4px 8px;
        border-radius: 10px;
        display: inline-block;
        font-weight: 600;
    }

    .status-present {
        background: #dcfce7;
        color: #166534;
    }

    .status-absent {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-leave {
        background: #fef3c7;
        color: #92400e;
    }

    .legend {
        display: flex;
        gap: 20px;
        margin-top: 25px;
        padding-top: 20px;
        border-top: 1px solid var(--border);
        flex-wrap: wrap;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 14px;
        color: var(--text);
        font-weight: 500;
    }

    .legend-box {
        width: 20px;
        height: 20px;
        border-radius: 4px;
    }

    /* Attendance Summary Cards */
    .summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .summary-card {
        background: white;
        border: 1px solid rgba(102, 126, 234, 0.1);
        border-radius: 15px;
        padding: 24px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        transition: all 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .summary-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .summary-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 30px rgba(102, 126, 234, 0.15);
    }

    .summary-label {
        font-size: 14px;
        color: #718096;
        font-weight: 600;
        margin-bottom: 12px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .summary-value {
        font-family: 'Roboto', sans-serif;
        font-size: 36px;
        font-weight: 700;
        margin-bottom: 8px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .summary-value.success {
        background: linear-gradient(135deg, #10b981, #059669);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .summary-value.danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .summary-value.warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .summary-subtext {
        font-size: 13px;
        color: #a0aec0;
    }

    /* Date text styling */
    .date-text {
        color: #718096;
        font-size: 14px;
    }

    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #a0aec0;
    }

    .empty-state i {
        font-size: 64px;
        margin-bottom: 20px;
        opacity: 0.5;
    }

    .empty-state h3 {
        font-size: 22px;
        font-weight: 600;
        color: #718096;
        margin-bottom: 10px;
    }

    .empty-state p {
        font-size: 14px;
    }

    /* Advanced Calendar Styles */
    .calendar-container {
        background: white;
        border-radius: 15px;
        padding: 35px;
        margin-top: 25px;
        box-shadow: 0 4px 20px rgba(102, 126, 234, 0.08);
        border: 1px solid rgba(102, 126, 234, 0.1);
        position: relative;
        overflow: hidden;
    }

    .calendar-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .calendar {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
        gap: 8px;
    }

    .calendar-day.header {
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        font-weight: 700;
        text-align: center;
        padding: 15px 10px;
        border-radius: 10px;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 1px;
        box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
    }

    .calendar-day {
        background: #f8fafc;
        border: 2px solid transparent;
        border-radius: 10px;
        padding: 12px;
        min-height: 110px;
        transition: all 0.3s ease;
        position: relative;
        cursor: pointer;
    }

    .calendar-day:not(.header):not(.empty):hover {
        background: white;
        border-color: var(--accent);
        box-shadow: 0 4px 15px rgba(102, 126, 234, 0.15);
        transform: translateY(-3px);
    }

    .calendar-day.empty {
        background: transparent;
        cursor: default;
    }

    .calendar-day.today {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.08), rgba(118, 75, 162, 0.08));
        border: 2px solid var(--accent);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .calendar-day.today .day-number {
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
        font-size: 18px;
    }

    .calendar-day.has-holiday {
        background: linear-gradient(135deg, rgba(243, 156, 18, 0.08), rgba(230, 126, 34, 0.08));
        border-color: #f39c12;
    }

    .calendar-day .day-number {
        font-size: 16px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .holiday-badge {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        color: white;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 10px;
        font-weight: 600;
        margin: 4px 0;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .holiday-badge i {
        font-size: 10px;
    }

    .leave-badge {
        background: linear-gradient(135deg, #4299e1, #3182ce);
        color: white;
        padding: 4px 8px;
        border-radius: 6px;
        font-size: 9px;
        font-weight: 600;
        margin: 4px 0;
        display: flex;
        align-items: center;
        gap: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .leave-badge i {
        font-size: 9px;
    }

    .attendance-indicators {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        margin-top: 8px;
    }

    .status-indicator {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        display: inline-block;
        transition: all 0.3s ease;
    }

    .status-indicator:hover {
        transform: scale(1.3);
    }

    .status-indicator.present {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
    }

    .status-indicator.absent {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        box-shadow: 0 0 0 2px rgba(239, 68, 68, 0.2);
    }

    .status-indicator.leave {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        box-shadow: 0 0 0 2px rgba(245, 158, 11, 0.2);
    }

    .legend {
        display: flex;
        gap: 25px;
        margin-top: 30px;
        padding: 25px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
        border-radius: 12px;
        border: 1px solid rgba(102, 126, 234, 0.1);
        flex-wrap: wrap;
        justify-content: center;
    }

    .legend-item {
        display: flex;
        align-items: center;
        gap: 10px;
        font-size: 13px;
        font-weight: 600;
        color: #4a5568;
        padding: 8px 12px;
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
    }

    .legend-dot {
        width: 14px;
        height: 14px;
        border-radius: 50%;
        box-shadow: 0 0 0 2px rgba(0, 0, 0, 0.05);
    }

    .legend-dot.present {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .legend-dot.absent {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    .legend-dot.leave {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .legend-dot.holiday {
        background: linear-gradient(135deg, #f39c12, #e67e22);
    }

    /* Stats Row for Calendar */
    .stats-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin: 25px 0;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
        display: flex;
        align-items: center;
        justify-content: space-between;
        transition: all 0.3s ease;
        border: 2px solid transparent;
        position: relative;
        overflow: hidden;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
    }

    .stat-card.present::before {
        background: linear-gradient(135deg, #10b981, #059669);
    }

    .stat-card.absent::before {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }

    .stat-card.leave::before {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }

    .stat-card.holiday::before {
        background: linear-gradient(135deg, #f39c12, #e67e22);
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 25px rgba(0, 0, 0, 0.12);
        border-color: var(--accent);
    }

    .stat-info h3 {
        font-size: 13px;
        font-weight: 600;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 8px 0;
    }

    .stat-info p {
        font-size: 32px;
        font-weight: 700;
        margin: 0;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.present .stat-info p {
        background: linear-gradient(135deg, #10b981, #059669);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.absent .stat-info p {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.leave .stat-info p {
        background: linear-gradient(135deg, #f59e0b, #d97706);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-card.holiday .stat-info p {
        background: linear-gradient(135deg, #f39c12, #e67e22);
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        background-clip: text;
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
    }

    .stat-card.present .stat-icon {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.1), rgba(5, 150, 105, 0.1));
        color: #10b981;
    }

    .stat-card.absent .stat-icon {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.1), rgba(220, 38, 38, 0.1));
        color: #ef4444;
    }

    .stat-card.leave .stat-icon {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.1), rgba(217, 119, 6, 0.1));
        color: #f59e0b;
    }

    .stat-card.holiday .stat-icon {
        background: linear-gradient(135deg, rgba(243, 156, 18, 0.1), rgba(230, 126, 34, 0.1));
        color: #f39c12;
    }

    /* Filter Section Styling */
    .filter-form {
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.03), rgba(118, 75, 162, 0.03));
        padding: 20px;
        border-radius: 12px;
        margin: 20px 0;
        border: 1px solid rgba(102, 126, 234, 0.1);
    }

    .filter-row {
        display: flex;
        gap: 15px;
        align-items: flex-end;
        flex-wrap: wrap;
    }

    .filter-group {
        flex: 1;
        min-width: 200px;
    }

    .filter-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        font-size: 13px;
        color: #4a5568;
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-group label i {
        color: var(--accent);
    }

    .filter-group input,
    .filter-group select {
        width: 100%;
        padding: 10px 14px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s ease;
        background: white;
    }

    .filter-group input:focus,
    .filter-group select:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        outline: none;
    }

    .filter-btn {
        padding: 10px 24px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
        min-width: 120px;
        justify-content: center;
    }

    .filter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
    }

    /* Holiday List Section */
    .holiday-section {
        margin-top: 30px;
        padding: 25px;
        background: linear-gradient(135deg, rgba(243, 156, 18, 0.05), rgba(230, 126, 34, 0.05));
        border-left: 4px solid #f39c12;
        border-radius: 12px;
    }

    .holiday-section h3 {
        margin: 0 0 20px 0;
        color: #e67e22;
        font-size: 20px;
        font-weight: 700;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .holiday-section h3 i {
        font-size: 24px;
    }

    .holidays-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 15px;
    }

    .holiday-item {
        padding: 15px;
        background: white;
        border-radius: 10px;
        border: 2px solid #f39c12;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(243, 156, 18, 0.1);
    }

    .holiday-item:hover {
        transform: translateY(-3px);
        box-shadow: 0 4px 15px rgba(243, 156, 18, 0.2);
    }

    .holiday-date {
        font-weight: 700;
        color: #e67e22;
        font-size: 14px;
        margin-bottom: 6px;
    }

    .holiday-name {
        color: #2d3748;
        font-weight: 600;
        font-size: 14px;
    }

    .optional-tag {
        font-size: 10px;
        background: #ffeaa7;
        padding: 3px 8px;
        border-radius: 4px;
        margin-left: 8px;
        font-weight: 600;
        color: #d97706;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }

        .profile-avatar {
            width: 80px;
            height: 80px;
            font-size: 32px;
        }

        .profile-details p {
            justify-content: center;
        }

        .info-grid {
            grid-template-columns: 1fr;
        }

        .calendar-header {
            flex-direction: column;
            align-items: stretch;
        }

        .month-nav-buttons {
            flex-direction: column;
        }

        .calendar {
            gap: 4px;
        }

        .calendar-day {
            min-height: 80px;
            padding: 6px;
        }

        .calendar-day.header {
            padding: 10px 5px;
            font-size: 11px;
        }

        .calendar-day .day-number {
            font-size: 14px;
        }

        .holiday-badge,
        .leave-badge {
            font-size: 8px;
            padding: 3px 6px;
        }

        .stats-row {
            grid-template-columns: 1fr;
        }

        .filter-row {
            flex-direction: column;
        }

        .filter-group {
            width: 100%;
        }

        .holidays-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

