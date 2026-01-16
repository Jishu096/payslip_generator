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
        font-size: 22px;
        font-weight: 600;
    }

    .navbar-toggle {
        cursor: pointer;
        font-size: 24px;
        transition: transform 0.3s;
    }

    .navbar-toggle:hover {
        transform: rotate(90deg);
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 8px 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 25px;
    }

    .user-avatar {
        width: 35px;
        height: 35px;
        background: white;
        color: #667eea;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        background: rgba(255,255,255,0.2);
        color: white;
        text-decoration: none;
        border-radius: 25px;
        transition: all 0.3s;
    }

    .logout-btn:hover {
        background: rgba(255,255,255,0.3);
        transform: translateY(-2px);
    }

    /* Sidebar */
    .sidebar {
        position: fixed;
        left: 0;
        top: 70px;
        width: 260px;
        height: calc(100vh - 70px);
        background: white;
        box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        overflow-y: auto;
        transition: transform 0.3s;
        z-index: 999;
    }

    .sidebar.collapsed {
        transform: translateX(-260px);
    }

    .sidebar-menu {
        list-style: none;
        padding: 20px 0;
    }

    .sidebar-menu li {
        margin: 5px 0;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 15px 25px;
        color: #4a5568;
        text-decoration: none;
        transition: all 0.3s;
        border-left: 4px solid transparent;
    }

    .sidebar-menu a:hover {
        background: #f7fafc;
        color: #667eea;
        border-left-color: #667eea;
    }

    .sidebar-menu a.active {
        background: linear-gradient(90deg, rgba(102,126,234,0.1) 0%, rgba(255,255,255,0) 100%);
        color: #667eea;
        border-left-color: #667eea;
        font-weight: 600;
    }

    .sidebar-menu i {
        width: 20px;
        text-align: center;
    }

    /* Main Content */
    .main-content {
        margin-left: 260px;
        margin-top: 70px;
        padding: 30px;
        transition: margin-left 0.3s;
        min-height: calc(100vh - 70px);
    }

    .main-content.expanded {
        margin-left: 0;
    }

    /* Cards */
    .card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        margin-bottom: 25px;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .card-title {
        font-size: 20px;
        color: #2d3748;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Buttons */
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }

    .btn-success {
        background: #48bb78;
        color: white;
    }

    .btn-danger {
        background: #f56565;
        color: white;
    }

    .btn-warning {
        background: #ed8936;
        color: white;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 15px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        transition: transform 0.3s, box-shadow 0.3s;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    }

    .stat-icon {
        width: 60px;
        height: 60px;
        border-radius: 15px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin-bottom: 15px;
    }

    .stat-value {
        font-size: 32px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 5px;
    }

    .stat-label {
        color: #718096;
        font-size: 14px;
    }

    /* Responsive */
    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-260px);
        }
        
        .sidebar.active {
            transform: translateX(0);
        }
        
        .main-content {
            margin-left: 0;
        }
    }
</style>
