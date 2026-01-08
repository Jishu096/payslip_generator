# ATTENDANCE & ABSENTEE STATEMENT MODULE
## Complete Documentation

### 📋 MODULE OVERVIEW
Production-ready attendance and absentee statement system for NIELIT payroll with government-style register format.

---

## 🗂️ FILES CREATED

### 1. Database Schema
**File:** `database/attendance_statement_schema.sql`
- `monthly_attendance_summary` table
- `attendance_leave_details` table  
- `payroll_monthly_snapshot` table
- Employee table enhancements (employee_type, contract_end_date, employee_group, location)
- View: `vw_attendance_statement`
- Sample data for testing

### 2. Main Report Page
**File:** `public/admin/attendance_statement.php`
**URL:** `http://localhost/payslip_generator/public/admin/attendance_statement.php`

**Features:**
- Two report types: Regular & Contract employees
- Month/Year filters
- Government-style table layout
- Print-friendly design (A4 landscape)
- Official NIELIT header
- Signature blocks for approval workflow

### 3. Data Entry Page
**File:** `public/admin/add_attendance_record.php`
**URL:** `http://localhost/payslip_generator/public/admin/add_attendance_record.php`

**Features:**
- Add leave/absence records
- Support for all leave types (OD, Tour, EL, CCL, PL, CL, RH, Absent)
- Half-day (FN/AN) support
- Auto-calculates monthly summary
- Form validation

### 4. Helper Class
**File:** `app/Helpers/AttendanceStatementHelper.php`

**Methods:**
- `calculateMonthlySummary()` - Calculates attendance summary
- `calculatePayroll()` - Links attendance to salary
- `processMonthlyAttendance()` - Bulk process all employees
- `saveMonthlySummary()` - Saves summary to database
- `savePayrollSnapshot()` - Creates payroll record

---

## 📊 REPORT FORMATS

### A) REGULAR EMPLOYEES ATTENDANCE STATEMENT

**Columns:**
1. S.No.
2. Name & Designation
3. Period of Absence/OD/Tour (e.g., "10/01 to 12/01")
4. Nature of Leave/OD/Tour
5. OD/Tour Days
6. EL/CCL/PL Days
7. CL/RH Days
8. Sat/Sun/GH Days
9. Total Days
10. Working Days
11. Net Working Days (for salary)
12. Remarks

**Sample Data:**
```
Jishu Sahoo (System Administrator)
Period: 10/01 to 11/01
Nature: OD - Official meeting at NIELIT Delhi
OD: 2 days
Net Working Days: 29
```

### B) CONTRACT EMPLOYEES ABSENTEE STATEMENT

**Columns:**
1. S.No.
2. Name & Designation
3. Period of Leave (From-To)
4. Nature of Leave/OD
5. Period of Absence (From-To)
6. Absent Days
7. Remarks

**Grouped By:**
- NIELIT Bhubaneswar - Project Staff
- NIELIT Bhubaneswar - Daily Wage Workers
- NIELIT Balasore - Project Staff

**Sample Data:**
```
Group: NIELIT Bhubaneswar - Project Staff
Saimon Raj Patro (Project Assistant)
Absent: 8/01, 22/01
Absent Days: 2
Payable Days: 29
```

---

## 🧮 CALCULATION LOGIC

### Regular Employees
```
Working Days = Total days in month (e.g., 31 for January)
Net Working Days = Working Days - (EL + CCL + PL + CL + RH)

Note: OD and Tour are PAYABLE days (counted in salary)
```

### Contract Employees
```
Total Days = Days in month
Payable Days = Total Days - Absent Days - Sundays - Govt Holidays
Salary = (Basic Salary / Total Days) × Payable Days
```

### Leave Types
- **OD (Official Duty)** - Payable, counts as present
- **Tour** - Payable, counts as present
- **EL (Earned Leave)** - Deducted from net working days
- **CCL (Commuted Leave)** - Deducted from net working days
- **PL (Privilege Leave)** - Deducted from net working days
- **CL (Casual Leave)** - Deducted from net working days
- **RH (Restricted Holiday)** - Deducted from net working days
- **Absent** - Unpaid (contract staff only)
- **Half Day** - 0.5 days (FN or AN)

---

## 💰 PAYROLL INTEGRATION

### Step 1: Enter Leave/Absence Records
```php
// Add leave record
POST /public/admin/add_attendance_record.php
{
    employee_id: 18,
    leave_type: "OD",
    start_date: "2026-01-10",
    end_date: "2026-01-11",
    nature_of_leave: "Official meeting"
}
```

### Step 2: Generate Monthly Summary
```php
$helper = new AttendanceStatementHelper($db);
$summary = $helper->calculateMonthlySummary(18, 1, 2026);
$helper->saveMonthlySummary($summary);
```

### Step 3: Calculate Payroll
```php
$payroll = $helper->calculatePayroll(18, 1, 2026);
$helper->savePayrollSnapshot($payroll);
```

### Step 4: View Statement
```
http://localhost/payslip_generator/public/admin/attendance_statement.php?month=1&year=2026&report_type=regular
```

---

## 🔧 DATABASE SCHEMA DETAILS

### Table: monthly_attendance_summary
```sql
CREATE TABLE monthly_attendance_summary (
    summary_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    
    -- Regular employee fields
    od_days DECIMAL(4,1),
    tour_days DECIMAL(4,1),
    el_days DECIMAL(4,1),
    ccl_days DECIMAL(4,1),
    pl_days DECIMAL(4,1),
    cl_days DECIMAL(4,1),
    rh_days DECIMAL(4,1),
    working_days DECIMAL(4,1),
    net_working_days DECIMAL(4,1),
    
    -- Contract employee fields
    absent_days DECIMAL(4,1),
    total_days DECIMAL(4,1),
    payable_days DECIMAL(4,1),
    
    remarks TEXT,
    UNIQUE KEY (employee_id, month, year)
);
```

### Table: attendance_leave_details
```sql
CREATE TABLE attendance_leave_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type ENUM('OD','Tour','EL','CCL','PL','CL','RH','Absent','Half_Day'),
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(4,1),
    is_half_day BOOLEAN,
    half_day_type ENUM('FN','AN'),
    nature_of_leave VARCHAR(255),
    status ENUM('pending','approved','rejected') DEFAULT 'approved'
);
```

### Table: payroll_monthly_snapshot
```sql
CREATE TABLE payroll_monthly_snapshot (
    snapshot_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    basic_salary DECIMAL(10,2),
    per_day_salary DECIMAL(10,2),
    payable_days DECIMAL(4,1),
    gross_salary DECIMAL(10,2),
    net_salary DECIMAL(10,2),
    attendance_summary_id INT,
    payment_status ENUM('pending','processed','paid'),
    UNIQUE KEY (employee_id, month, year)
);
```

---

## 📝 USAGE EXAMPLES

### Example 1: Add OD Record
```
1. Go to: add_attendance_record.php
2. Select: Jishu Sahoo
3. Leave Type: OD (Official Duty)
4. Start: 2026-01-10
5. End: 2026-01-11
6. Nature: "Official meeting at NIELIT Delhi"
7. Click: Save Record

Result: 2 OD days added, net working days = 31 (unchanged as OD is payable)
```

### Example 2: Add Absent Record (Contract Staff)
```
1. Go to: add_attendance_record.php
2. Select: Saimon Raj Patro (Contract)
3. Leave Type: Absent
4. Start: 2026-01-08
5. End: 2026-01-08
6. Click: Save Record

Result: 1 absent day, payable days reduced from 31 to 30
```

### Example 3: Half Day Leave
```
1. Leave Type: Half_Day
2. Check: "This is a half day"
3. Select: FN (Forenoon)
4. Start/End: Same date

Result: 0.5 days deducted
```

---

## 🖨️ PRINTING GUIDELINES

### Print Settings:
- **Page Size:** A4 Landscape
- **Margins:** 15mm
- **Headers/Footers:** Enabled (auto-generated)
- **Background Graphics:** Disabled

### What Prints:
✅ NIELIT Header
✅ Report title and period
✅ Complete data table
✅ Signature blocks
✅ Generation timestamp

### What Doesn't Print:
❌ Filter section
❌ Navigation buttons
❌ Print button itself

---

## 🔐 SECURITY & ACCESS

- **Admin Only:** Session-based authentication required
- **Role Check:** Validates `$_SESSION['role']`
- **SQL Injection:** Prepared statements used throughout
- **XSS Protection:** `htmlspecialchars()` on all output
- **CSRF Protection:** Consider adding tokens for production

---

## 📈 TESTING DATA

Sample data inserted for January 2026:

**Regular Employees:**
- Jishu Sahoo (ID: 18) - Full attendance, 21 net working days
- Suraj Kumar Mahali (ID: 19) - 2 OD days, 1 CL, 20 net working days
- Namita Barik (ID: 20) - 1 Tour, 1 CL, 20 net working days

**Contract Employees:**
- Saimon Raj Patro (ID: 21) - 2 absent days, 29 payable days
- Kumar Dinesh Behera (ID: 22) - Full attendance, 31 payable days

---

## 🚀 NEXT STEPS

### Recommended Enhancements:
1. **PDF Export** - Integrate TCPDF/DOMPDF for PDF generation
2. **Excel Export** - Add PHPSpreadsheet for Excel download
3. **Email Notifications** - Send reports to employees
4. **Dashboard Widget** - Add summary cards to admin dashboard
5. **Bulk Import** - Excel/CSV upload for leave records
6. **Approval Workflow** - Multi-level approval for leaves
7. **Leave Balance Tracking** - Track remaining leave quotas
8. **Biometric Integration** - Auto-mark absent if no punch

### Performance Optimization:
- Add indexes on frequently queried columns
- Implement caching for static data (holidays)
- Use database views for complex queries
- Consider pagination for large datasets

---

## 📞 SUPPORT & MAINTENANCE

### Common Issues:

**Q: "No data available" message?**
A: Add leave records first using `add_attendance_record.php`

**Q: Net working days incorrect?**
A: Check holiday table data and weekend calculations

**Q: Print layout broken?**
A: Use A4 Landscape, check browser print settings

**Q: Payroll not calculating?**
A: Ensure salary table has basic_salary for employee

---

## 📄 FILE STRUCTURE
```
payslip_generator/
├── database/
│   └── attendance_statement_schema.sql
├── app/
│   └── Helpers/
│       └── AttendanceStatementHelper.php
└── public/
    └── admin/
        ├── attendance_statement.php
        ├── add_attendance_record.php
        └── holidays_nielit.php
```

---

**Version:** 1.0.0  
**Last Updated:** January 8, 2026  
**Developed For:** NIELIT Bhubaneswar Payroll System  
**License:** Internal Use Only
