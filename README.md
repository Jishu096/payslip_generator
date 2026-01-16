# e-HRMS - Payslip Generator System

A comprehensive PHP-based **Human Resource Management System** with multi-role RBAC, automated payslip generation, attendance tracking, and payroll management.

![PHP](https://img.shields.io/badge/PHP-8.x-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

## 🚀 Features

### Multi-Role Access Control
- **Administrator**: Full system access, employee & department management
- **Accountant**: Payroll processing, payslip generation, financial reports
- **Director**: Salary & role change approvals, reports
- **Employee**: View payslips, attendance, profile management
- **HR Officer**: Attendance verification, employee records
- **Auditor**: Audit trails, payroll reports, approval history
- **Super Admin**: User management, role assignments, system security

### Payroll Management
- ✅ Multi-category salary calculations (Permanent/Contractual/Intern)
- ✅ Configurable DA rates per month/year
- ✅ Automated allowances (HRA, DA, TA, Bonus)
- ✅ Deductions (Tax, PF, NPS, Professional Tax)
- ✅ PDF payslip generation with digital signature
- ✅ Bank file generation for salary transfers

### Attendance System
- ✅ Attendance tracking with Present/Absent/Leave status
- ✅ Excel attendance statement generation
- ✅ Biometric integration support
- ✅ Attendance officials management
- ✅ Manual attendance entry & corrections

### Security Features
- ✅ Multi-role RBAC with granular permissions
- ✅ CSRF protection on all forms
- ✅ Comprehensive audit logging
- ✅ Secure password hashing (bcrypt)
- ✅ Session management with role validation

## 📁 Project Structure

```
payslip_generator/
├── app/
│   ├── Controllers/         # Business logic (9 controllers)
│   │   ├── AccountantController.php
│   │   ├── AttendanceStatementController.php
│   │   ├── AuditLogController.php
│   │   ├── DepartmentController.php
│   │   ├── DirectorController.php
│   │   ├── EmployeeController.php
│   │   ├── LoginController.php
│   │   ├── ProfileController.php
│   │   ├── RoleChangeApprovalController.php
│   │   ├── SalaryApprovalController.php
│   │   └── UserController.php
│   │
│   ├── Models/              # Data access layer
│   │   ├── Attendance.php
│   │   ├── Department.php
│   │   ├── Employee.php
│   │   ├── LeaveRequest.php
│   │   ├── Payroll.php       # Multi-category calculations
│   │   ├── Payslip.php
│   │   ├── SalaryConfig.php  # DA rate configuration
│   │   └── User.php          # Multi-role support
│   │
│   ├── Helpers/             # Utility classes
│   │   ├── AttendanceStatementHelper.php
│   │   ├── AuditLogger.php
│   │   ├── CSRFHelper.php
│   │   ├── EmailHelper.php
│   │   ├── LoginAttemptHelper.php
│   │   ├── NotificationHelper.php
│   │   ├── PermissionHelper.php
│   │   └── RBACHelper.php
│   │
│   ├── Config/
│   │   └── database.php     # PDO connection (Unix socket for XAMPP macOS)
│   │
│   └── Routes/
│       └── web.php          # Application routing
│
├── public/                  # Web-accessible files
│   ├── index.php            # Entry point
│   ├── admin/               # Administrator portal (20+ pages)
│   ├── accountant/          # Accountant portal (11+ pages)
│   ├── director/            # Director portal (5+ pages)
│   ├── employee/            # Employee portal (8+ pages)
│   ├── hr_officer/          # HR Officer portal
│   ├── auditor/             # Auditor portal
│   ├── super_admin/         # Super Admin portal
│   ├── auth/                # Authentication pages
│   ├── api/                 # AJAX endpoints
│   └── assets/              # CSS, JS, images
│
├── database/                # SQL schemas & migrations
│   ├── schema.sql           # Main database schema
│   ├── sample_data.sql      # Test data
│   ├── contractual_intern_salary_schema.sql
│   ├── attendance_statement_schema.sql
│   ├── attendance_officials_schema.sql
│   ├── audit_logs_schema.sql
│   ├── rbac_audit_schema.sql
│   └── add_indexes.sql      # Performance optimizations
│
├── docs/                    # Documentation
│   ├── guides/              # User guides
│   │   ├── MULTI_ROLE_RBAC_GUIDE.md
│   │   ├── PAYSLIP_GENERATION_GUIDE.md
│   │   └── ACCOUNTANT_ROLE_DOCUMENTATION.md
│   │
│   ├── implementation/      # Technical docs
│   │   ├── CONTRACTUAL_INTERN_SALARY_IMPLEMENTATION.md
│   │   ├── ACCOUNTANT_SIDEBAR_UNIFICATION.md
│   │   ├── DASHBOARD_STANDARDIZATION.md
│   │   ├── RBAC_IMPLEMENTATION_SUMMARY.md
│   │   └── ROLE_CHANGE_APPROVAL_IMPLEMENTATION.md
│   │
│   └── quick-reference/     # Quick start guides
│
├── storage/                 # Generated files
│   ├── logs/                # Application logs
│   └── payslips/            # PDF payslips
│
├── vendor/                  # Composer dependencies
├── .github/                 # GitHub configuration
│   └── copilot-instructions.md
│
├── composer.json            # PHP dependencies
├── .gitignore               # Git ignore rules
├── PROJECT_REPORT.md        # Complete project report
└── README.md                # This file
```

## 🛠️ Technology Stack

- **Backend**: PHP 8.x with MVC-like architecture
- **Database**: MySQL 5.7+ with PDO
- **Server**: XAMPP (macOS) - Apache + MySQL
- **Frontend**: Custom CSS with purple gradient theme (#667eea → #764ba2)
- **Icons**: Font Awesome 6.4.0
- **Excel**: PhpSpreadsheet for attendance statements
- **PDF**: TCPDF/FPDF for payslip generation
- **Email**: PHPMailer for notifications

## 📦 Installation

### Prerequisites
- XAMPP 8.x (PHP 8.x + MySQL 5.7+)
- Composer
- macOS/Linux/Windows

### Setup Steps

1. **Clone the repository**
   ```bash
   cd /Applications/XAMPP/xamppfiles/htdocs/
   git clone https://github.com/Jishu096/payslip_generator.git
   cd payslip_generator
   ```

2. **Install dependencies**
   ```bash
   composer install
   ```

3. **Configure database**
   ```bash
   # Edit app/Config/database.php with your MySQL credentials
   # Default: root with no password on localhost
   ```

4. **Import database**
   ```bash
   mysql -u root -p payslip_generator < database/schema.sql
   mysql -u root -p payslip_generator < database/sample_data.sql
   mysql -u root -p payslip_generator < database/contractual_intern_salary_schema.sql
   mysql -u root -p payslip_generator < database/attendance_statement_schema.sql
   mysql -u root -p payslip_generator < database/audit_logs_schema.sql
   ```

5. **Set permissions**
   ```bash
   chmod -R 755 storage/
   chmod -R 777 storage/logs/
   chmod -R 777 storage/payslips/
   ```

6. **Start XAMPP**
   ```bash
   # Start Apache and MySQL from XAMPP Control Panel
   ```

7. **Access the application**
   ```
   http://localhost/payslip_generator/public/
   ```

### Default Login Credentials

| Role | Username | Password |
|------|----------|----------|
| Administrator | admin | admin123 |
| Accountant | accountant | accountant123 |
| Director | director | director123 |
| Employee | employee | employee123 |

## 🎨 Design System

### Color Palette
```css
--bg: #f8fafc;              /* Light background */
--card: #ffffff;            /* Card background */
--accent: #667eea;          /* Primary purple */
--accent-2: #764ba2;        /* Darker purple */
--text: #1e293b;            /* Text color */
--muted: #64748b;           /* Muted text */
--border: #e2e8f0;          /* Border color */
--success: #10b981;         /* Success green */
--error: #ef4444;           /* Error red */
--warning: #f59e0b;         /* Warning orange */
```

### Component Patterns
- **Gradient Buttons**: `linear-gradient(135deg, #667eea, #764ba2)`
- **Cards**: White background, 20px border-radius, subtle shadow
- **Glassmorphism**: `backdrop-filter: blur(15px)` on overlays
- **Animations**: Smooth transitions with `ease-out` timing

## 📊 Database Schema

### Core Tables
- **users**: User accounts with multi-role support
- **roles**: Role definitions
- **user_roles**: Many-to-many user-role relationship
- **permissions**: Granular permission definitions
- **role_permissions**: Permission assignments to roles

### Employee Management
- **employees**: Employee records with salary details
- **departments**: Organizational units
- **attendance**: Daily attendance tracking
- **leave_requests**: Leave applications

### Payroll System
- **payroll**: Salary calculations and components
- **payslips**: Generated payslip metadata
- **salary_config**: Monthly DA rate configuration

### Audit & Security
- **audit_logs**: Comprehensive activity logging
- **login_attempts**: Failed login tracking

## 🔐 RBAC System

### Permission Structure
```
administrator.*
  ├── employees.create
  ├── employees.update
  ├── employees.delete
  ├── departments.manage
  └── users.manage

accountant.*
  ├── payroll.process
  ├── payslips.generate
  ├── salary_config.manage
  └── reports.financial

director.*
  ├── salary_changes.approve
  ├── role_changes.approve
  └── reports.view

employee.*
  ├── payslips.view_own
  ├── attendance.view_own
  └── profile.update_own
```

### Multi-Role Support
Users can have multiple roles simultaneously:
```php
$_SESSION['all_roles'] = ['employee', 'accountant'];
RBACHelper::userHasAnyRole($userRoles, ['accountant', 'director']);
```

## 📖 Key Documentation

- **[Multi-Role RBAC Guide](docs/guides/MULTI_ROLE_RBAC_GUIDE.md)** - Complete RBAC implementation
- **[Payslip Generation Guide](docs/guides/PAYSLIP_GENERATION_GUIDE.md)** - Payslip workflow
- **[Contractual & Intern Salary](docs/implementation/CONTRACTUAL_INTERN_SALARY_IMPLEMENTATION.md)** - Salary system
- **[Accountant Documentation](docs/guides/ACCOUNTANT_ROLE_DOCUMENTATION.md)** - Accountant features
- **[Biometric Integration](docs/BIOMETRIC_INTEGRATION.md)** - Attendance hardware integration
- **[Project Report](PROJECT_REPORT.md)** - Complete 803-line project overview

## 🧪 Testing

```bash
# Test RBAC functionality
./test-rbac.sh

# Create test employee
mysql -u root -p payslip_generator < create_test_employee.sql
```

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## 📝 Development Notes

### Routing Pattern
```php
// URL: index.php?page=check-login
// Route: app/Routes/web.php
case 'check-login':
    require_once __DIR__ . '/../Controllers/LoginController.php';
    $controller = new LoginController();
    $controller->checkLogin();
    break;
```

### Database Connection
```php
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();  // Returns PDO instance
```

### Standard Salary Rates
```php
$standardRates = [
    'hra_percent' => 20,      // HRA = 20% of Basic
    'da_percent' => 58,       // DA = 58% of Basic
    'epf_percent' => 12,      // EPF = 12% of Basic
    'nps_percent' => 10,      // NPS = 10% of Basic
    'professional_tax' => 200 // Flat amount
];
```

## 🐛 Known Issues

- Biometric device integration requires hardware configuration
- PDF generation may timeout for bulk operations (100+ payslips)
- Excel export limited to 10,000 records per sheet

## 📅 Changelog

### v2.0.0 (January 2026)
- ✅ Implemented Contractual & Intern Salary System
- ✅ Unified Accountant sidebar design
- ✅ Added attendance statement Excel generation
- ✅ Enhanced RBAC with audit logging
- ✅ Fixed session management bugs

### v1.5.0 (December 2025)
- ✅ Multi-role RBAC implementation
- ✅ Director approval workflows
- ✅ Dashboard standardization across roles

### v1.0.0 (December 2025)
- ✅ Initial release with core features

## 📄 License

This project is licensed under the MIT License - see the LICENSE file for details.

## 👨‍💻 Author

**Jishu Sahoo**
- GitHub: [@Jishu096](https://github.com/Jishu096)
- Email: jishusahoo@example.com

## 🙏 Acknowledgments

- Font Awesome for icons
- PhpSpreadsheet for Excel generation
- TCPDF for PDF generation
- PHPMailer for email functionality

---

**Note**: This is an active development project. For production deployment, ensure proper security configuration, SSL certificates, and database credentials management.
