<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fa;
        overflow-x: hidden;
    }

    /* Top Navbar */
    .navbar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 70px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 20px;
        font-weight: 600;
    }

    .navbar-brand i {
        font-size: 28px;
    }

    .navbar-toggle {
        font-size: 24px;
        cursor: pointer;
        display: none;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .logout-btn {
        background: rgba(255,255,255,0.2);
        padding: 8px 20px;
        border-radius: 20px;
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logout-btn:hover {
        background: rgba(255,255,255,0.3);
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
        background: #2c3e50;
        position: fixed;
        left: 0;
        top: 70px;
        height: calc(100vh - 70px);
        overflow-y: auto;
        transition: all 0.3s ease;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }

    .sidebar.collapsed {
        left: -260px;
    }

    .sidebar-menu {
        list-style: none;
        padding: 20px 0;
    }

    .sidebar-menu li {
        margin-bottom: 5px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 25px;
        color: #ecf0f1;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background: rgba(52, 152, 219, 0.2);
        border-left: 4px solid #3498db;
        padding-left: 21px;
    }

    .sidebar-menu i {
        width: 20px;
        font-size: 18px;
    }

    /* Submenu Styles */
    .sidebar-menu li.has-submenu > a {
        position: relative;
    }

    .sidebar-menu .submenu-icon {
        margin-left: auto;
        font-size: 12px;
        transition: transform 0.3s ease;
    }

    .sidebar-menu li.has-submenu.open > a .submenu-icon {
        transform: rotate(180deg);
    }

    .sidebar-menu .submenu {
        list-style: none;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
        background: rgba(0, 0, 0, 0.2);
    }

    .sidebar-menu li.has-submenu.open .submenu {
        max-height: 500px;
    }

    .sidebar-menu .submenu li {
        margin-bottom: 0;
    }

    .sidebar-menu .submenu a {
        padding: 12px 25px 12px 60px;
        font-size: 14px;
    }

    .sidebar-menu .submenu a:hover,
    .sidebar-menu .submenu a.active {
        background: rgba(52, 152, 219, 0.3);
        border-left: 4px solid #3498db;
        padding-left: 56px;
    }

    /* Main Content */
    .main-content {
        margin-left: 260px;
        margin-top: 70px;
        padding: 30px;
        transition: all 0.3s ease;
        min-height: calc(100vh - 70px);
    }

    .main-content.expanded {
        margin-left: 0;
    }

    .page-header {
        margin-bottom: 30px;
    }

    .page-header h1 {
        font-size: 28px;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .page-header p {
        color: #7f8c8d;
        font-size: 14px;
    }

    .btn {
        display: inline-block;
        padding: 12px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .navbar-toggle {
            display: block;
        }

        .sidebar {
            left: -260px;
        }

        .sidebar.active {
            left: 0;
        }

        .main-content {
            margin-left: 0;
        }

        .navbar-brand span {
            display: none;
        }

        .user-info span {
            display: none;
        }
    }
    /* Glassmorphism & Modern UI */
    .glass-card {
        background: rgba(255, 255, 255, 0.9);
        backdrop-filter: blur(10px);
        -webkit-backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.18);
        box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.07);
        border-radius: 16px;
    }

    .glass-btn {
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 100%);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,0.18);
        box-shadow: 0 8px 32px 0 rgba(0,0,0,0.1);
    }

    /* Attendance Quick Action Cards */
    .attendance-actions-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        padding: 5px;
    }

    .action-card {
        position: relative;
        padding: 24px;
        border-radius: 20px;
        color: white;
        text-decoration: none;
        overflow: hidden;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        min-height: 160px;
    }

    .action-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 30px rgba(0,0,0,0.2);
    }

    .action-card .icon-bg {
        position: absolute;
        right: -20px;
        bottom: -20px;
        font-size: 100px;
        opacity: 0.2;
        transform: rotate(-15deg);
        transition: transform 0.3s ease;
    }

    .action-card:hover .icon-bg {
        transform: rotate(0deg) scale(1.1);
    }

    .action-card .card-content {
        position: relative;
        z-index: 1;
    }

    .action-card .card-title {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .action-card .card-desc {
        font-size: 13px;
        opacity: 0.9;
        line-height: 1.4;
    }

    /* Gradients */
    .bg-gradient-purple { background: linear-gradient(135deg, #8E2DE2 0%, #4A00E0 100%); }
    .bg-gradient-teal { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
    .bg-gradient-orange { background: linear-gradient(135deg, #f12711 0%, #f5af19 100%); }
    .bg-gradient-blue { background: linear-gradient(135deg, #2193b0 0%, #6dd5ed 100%); }
    .bg-gradient-pink { background: linear-gradient(135deg, #ec008c 0%, #fc6767 100%); }
    .bg-gradient-dark { background: linear-gradient(135deg, #232526 0%, #414345 100%); }
    
    /* Live Stats Widget */
    .live-stats-container {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
    }

    .live-stat-item {
        flex: 1;
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        gap: 15px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        border: 1px solid #eee;
    }
    
    .live-stat-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .stat-text h4 {
        font-size: 24px;
        font-weight: 700;
        color: #2d3748;
        line-height: 1;
    }

    .stat-text span {
        font-size: 12px;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
</style>
</style>

<style>
    /* Print Styles */
    @media print {
        .navbar,
        .sidebar,
        .no-print,
        .btn, 
        .page-header {
            display: none !important;
        }

        .main-content {
            margin-left: 0 !important;
            margin-top: 0 !important;
            padding: 0 !important;
            min-height: auto !important;
        }

        body {
            background: white !important;
            color: black !important;
        }

        .glass-card, .card {
            box-shadow: none !important;
            border: none !important;
        }
    }
</style>
