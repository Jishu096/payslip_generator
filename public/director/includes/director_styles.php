<?php include '../admin/includes/admin_styles.php'; ?>
<style>
    /* Director Specific Styles & Modals */
    
    /* Modal Styles */
    .modal {
        display: none; 
        position: fixed; 
        z-index: 2000; 
        left: 0;
        top: 0;
        width: 100%; 
        height: 100%; 
        overflow: auto; 
        background-color: rgba(0,0,0,0.5); 
        backdrop-filter: blur(5px);
        align-items: center;
        justify-content: center;
    }

    .modal.show {
        display: flex;
    }

    .modal-content {
        background-color: #fefefe;
        /* margin: 15% auto; */
        padding: 30px;
        border: 1px solid #888;
        width: 100%;
        max-width: 500px;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        animation: modalSlideIn 0.3s ease;
    }

    @keyframes modalSlideIn {
        from { transform: translateY(-50px); opacity: 0; }
        to { transform: translateY(0); opacity: 1; }
    }

    .modal-header h3 {
        margin-top: 0;
        color: #2c3e50;
        font-size: 20px;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        margin-bottom: 8px;
        font-weight: 500;
        color: #4a5568;
    }

    .form-group textarea, .form-group input {
        width: 100%;
        padding: 12px;
        border: 2px solid #e2e8f0;
        border-radius: 8px;
        font-family: inherit;
        transition: border-color 0.3s;
    }

    .form-group textarea:focus, .form-group input:focus {
        border-color: #667eea;
        outline: none;
    }

    .modal-actions {
        display: flex;
        justify-content: flex-end;
        gap: 10px;
        margin-top: 25px;
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #4a5568;
    }

    .btn-secondary:hover {
        background: #cbd5e0;
        color: #2d3748;
    }

    .btn-success { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
    .btn-danger { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }

    /* Badges */
    .badge {
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        display: inline-block;
    }
    .badge-success { background: #d1fae5; color: #065f46; }
    .badge-warning { background: #fef3c7; color: #92400e; }
    .badge-danger { background: #fee2e2; color: #b91c1c; }
    .badge-primary { background: #e0e7ff; color: #3730a3; }

    /* Request Cards */
    .request-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 15px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        border-left: 4px solid transparent;
        transition: transform 0.2s;
    }
    .request-card:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
    .request-card.pending { border-left-color: #f59e0b; }
    .request-card.approved { border-left-color: #10b981; }
    .request-card.rejected { border-left-color: #ef4444; }

    .detail-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid #f0f0f0;
    }

    .detail-item label {
        font-size: 11px;
        text-transform: uppercase;
        color: #718096;
        font-weight: 600;
        display: block;
        margin-bottom: 4px;
    }

    .detail-item .value {
        font-weight: 500;
        color: #2d3748;
    }

    /* General Card & Table */
    .card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.05);
        margin-bottom: 25px;
        overflow: hidden;
    }

    .card-header {
        padding: 20px;
        border-bottom: 1px solid #f0f0f0;
        background: #fafafa;
    }

    .card-header h3 {
        margin: 0;
        font-size: 18px;
        color: #2d3748;
    }

    .card-body {
        padding: 20px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    th {
        text-align: left;
        padding: 12px;
        background: #f7fafc;
        color: #718096;
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    td {
        padding: 15px 12px;
        border-bottom: 1px solid #edf2f7;
        color: #4a5568;
        font-size: 14px;
    }

    tr:last-child td { border-bottom: none; }

    /* Departments */
    .dept-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        margin-top: 20px;
    }

    .dept-card {
        background: #f8fafc;
        padding: 25px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
        transition: all 0.3s ease;
    }

    .dept-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.05);
        border-color: #667eea;
        background: white;
    }

    .dept-header {
        display: flex;
        align-items: center;
        gap: 15px;
        margin-bottom: 15px;
    }

    .dept-icon {
        width: 50px;
        height: 50px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        box-shadow: 0 4px 10px rgba(102, 126, 234, 0.3);
    }

    .dept-info h3 {
        font-size: 18px;
        font-weight: 700;
        color: #2d3748;
        margin-bottom: 4px;
    }

    .dept-info p {
        font-size: 13px;
        color: #718096;
    }

    .dept-stats {
        display: flex;
        gap: 20px;
        padding-top: 15px;
        border-top: 1px solid #e2e8f0;
    }

    .dept-stat-value {
        font-size: 24px;
        font-weight: 700;
        color: #667eea;
    }

    .dept-stat-label {
        font-size: 12px;
        color: #718096;
        margin-top: 4px;
    }

    /* Badges */
    .type-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 8px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    .salary-type { background: #e0e7ff; color: #4338ca; }
    .role-type { background: #f3e8ff; color: #7e22ce; }
</style>
