ACCOUNTANT ROLE - FINANCIAL & PAYROLL ACCESS
=============================================

Overview
The Accountant role has complete access to all financial and payroll operations in the system.
Accountants can manage employee salaries, view detailed payroll information, and generate reports.

Access Control
- Restricted to 'accountant' role via session verification
- Cannot access administrator or director functions
- Can view employee data for payroll purposes
- Can access payroll tables and salary information

Pages Available to Accountant

1. accountant_dashboard.php (Main Dashboard)
   - Displays key metrics: total employees, monthly payroll, payslips generated, current month
   - Quick action links to all payroll functions
   - Shows total monthly payroll calculation in real-time
   - Role-based sidebar navigation

2. payroll_management.php (Payroll Management Portal)
   Location: /accountant/payroll_management.php
   Features:
   - View all employees with salary details
   - Display: Name, Designation, Department, Employment Type, Basic Salary, Role, Email
   - Search functionality (by employee name)
   - Filter by department
   - Summary cards showing:
     * Total employees count
     * Total monthly payroll (sum of all basic salaries)
     * Active accountants count
     * Current month indicator
   - Real-time calculation of total payroll
   - Responsive table design with hover effects
   - Role badges showing employee system role (Employee, Accountant, Director, Administrator)

3. financial_reports.php (Financial Reports)
   Location: /accountant/financial_reports.php
   Features:
   - Three report types (selectable via tabs):
     a) Payroll Summary
        * Total employees count
        * Total monthly payroll amount
        * Average employee salary
        * Salary range (min - max)
        * CSV export functionality
     
     b) By Department
        * Department-wise payroll breakdown
        * Employee count per department
        * Total salary per department
        * Average salary per department
        * Sorted by total payroll (highest to lowest)
        * CSV export functionality
     
     c) Employee Details
        * Full employee information: Name, Designation, Department, Employment Type
        * Basic salary for each employee
        * System role (Employee/Accountant/Director/Administrator)
        * Email address
        * Search by employee name
        * Filter by department
        * CSV export functionality

4. generate_payslip.php (Payslip Generation)
   Location: /accountant/generate_payslip.php
   Status: Template available (empty - ready for implementation)
   Intended features:
   - Generate payslips for individual employees
   - Calculate salary components (basic, HRA, DA, etc.)
   - Apply deductions (taxes, insurance, etc.)
   - Support for bonuses
   - Save/upload payslips
   - Email delivery to employees

5. Employees Access (View Only)
   Location: /admin/employees.php
   - Can view all employee records
   - Can view employee details and salary information
   - Limited to read-only access for payroll purposes

Sidebar Navigation (Accountant)
- Dashboard: accountant_dashboard.php
- Payroll Management: payroll_management.php
- Financial Reports: financial_reports.php
- Generate Payslip: generate_payslip.php
- Employees: ../admin/employees.php (external link)
- Logout: Redirect to login page

Key Features & Capabilities

1. Payroll Visibility
   - Complete view of all employee salary information
   - Department-wise payroll analysis
   - Real-time payroll calculations
   - Salary statistics (avg, min, max)

2. Financial Reporting
   - Summary reports for accounting purposes
   - Department-level financial breakdowns
   - Detailed employee salary reports
   - CSV export for integration with accounting software

3. Data Analysis
   - Search and filter employees by various criteria
   - Department-wise payroll distribution
   - Employee role visibility for payroll accuracy
   - Email contact information for communication

4. Salary Management
   - Access to all basic salary information
   - View employment type (Full-time, Part-time, Contract)
   - Track designation changes affecting salary

Database Tables Accessed by Accountant
- employees (read-only): For salary and employee information
- departments (read-only): For department names and payroll rollups
- users (read-only): For employee role information (Accountant, Director, etc.)

Security Measures
- Role verification on each page (session['role'] === 'accountant')
- SQL prepared statements to prevent injection
- Read-only access to sensitive tables
- No modification privileges without explicit coding

Session Requirements
For accountant functionality:
- $_SESSION['role'] must equal 'accountant'
- $_SESSION['username'] contains accountant's name
- $_SESSION['user_id'] identifies the accountant user

Role Badges in Reports
Displays employee system roles with color coding:
- Accountant: Blue (#667eea)
- Director: Dark Blue (#0c5377)
- Administrator: Red (#e74c3c)
- Employee: Gray (#95a5a6)

Report Export Feature
- All financial reports support CSV export
- Downloads automatically when export button clicked
- Includes timestamp in filename for easy identification
- Formats: payroll_report_YYYYMMDD_HHMMSS.csv

Filter & Search Capabilities
1. Employee Name Search (Real-time)
   - Searches full name field
   - Case-insensitive
   - Instant results

2. Department Filter
   - Dropdown selection
   - Filters all displayed results
   - All departments option available

3. Combined Filtering
   - Search and department filter work together
   - Row shows only if matches both criteria

Mobile Responsive Design
- Tables responsive on smaller screens
- Sidebar collapses appropriately
- Touch-friendly buttons and filters
- Readable text sizes on all devices

Color Scheme (Accountant Portal)
- Primary: #667eea (Purple Blue) - Dashboard and navigation
- Secondary: #764ba2 (Purple) - Gradients
- Success: #28a745 (Green) - Positive numbers, export buttons
- Info: #0c5377 (Dark Blue) - Neutral information
- Alert: #e74c3c (Red) - Important notices

Dashboard Statistics
- Real-time calculations from database
- Updated on each page load
- SUM() for total payroll
- COUNT() for employee counts
- AVG() for average calculations

File Structure
/accountant/
  ├── accountant_dashboard.php     - Main dashboard with quick access
  ├── payroll_management.php       - Detailed payroll view and management
  ├── financial_reports.php        - Multi-tab reporting system
  ├── generate_payslip.php         - Template for payslip generation
  └── (shared styles via /admin/includes/)

Usage Workflow for Accountant

1. Login as Accountant
   - Enter credentials at /auth/login.php
   - Role must be set to 'accountant' in database

2. Dashboard Review
   - Check total monthly payroll
   - See employee count and current period

3. Payroll Management
   - Navigate to "Payroll Management"
   - Search/filter employees as needed
   - Review salary details

4. Financial Reports
   - Select report type (Payroll Summary, By Department, or Employees)
   - Review financial metrics
   - Export data as CSV for accounting software

5. Generate Payslips (When Implemented)
   - Select employees
   - Calculate salary components
   - Generate and distribute payslips

6. Employee Access
   - View full employee records
   - Check designation and department
   - Access contact information

Accounting Integration Points
- Payroll total for monthly accounting entries
- Department-wise cost allocation
- Employee salary records for audit trails
- CSV exports for accounting software integration

Performance Considerations
- Database queries optimized with prepared statements
- Minimal joins for efficient data retrieval
- Pagination ready for future large datasets
- Sidebar navigation loads consistently

Status Summary
✓ Accountant Dashboard - COMPLETE
✓ Payroll Management Portal - COMPLETE
✓ Financial Reports (3 report types) - COMPLETE
✓ Role-based Access Control - COMPLETE
✓ Data Export (CSV) - TEMPLATE READY
✓ Search & Filter - COMPLETE
✓ Responsive Design - COMPLETE
- Generate Payslip - TEMPLATE AVAILABLE (ready for implementation)

All accountant portal pages pass PHP syntax validation.
