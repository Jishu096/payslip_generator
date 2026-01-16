# Contractual & Intern Salary System - Implementation Guide
**NIELIT e-HRMS Payroll Module**

## Overview
This system extends the existing permanent employee payroll to support **Contractual** and **Intern** employees with day-wise calculations and DA allowances.

---

## ✅ COMPLETED IMPLEMENTATIONS

### 1. Database Schema ✅
**File:** `database/contractual_intern_salary_schema.sql`

**Created Tables:**
- `salary_config` - Stores DA rates per month/year with enable/disable controls
- Updated `employees` table - Already has `employee_type`, `daily_rate`, `stipend` columns
- Updated `payroll` table - Added `employee_type`, `daily_rate`, `stipend`, `working_days`, `od_tour_days`, `da_amount`

**Default Data:**
- 12 months of salary configurations for 2026 ✅
- DA rates: Contractual ₹300/day, Intern ₹200/day
- Tour DA rates: Contractual ₹500/day, Intern ₹300/day

**Verification:**
```sql
SELECT * FROM salary_config LIMIT 3;
-- Shows 3 records with config_id 1, 2, 3 for Jan-Mar 2026
```

---

### 2. SalaryConfig Model ✅
**File:** `app/Models/SalaryConfig.php`

**Key Methods:**
- `getConfigByMonth($month, $year)` - Get DA rates for specific month
- `upsertConfig($data)` - Update/insert configuration
- `getDARate($employeeType, $month, $year, $daType)` - Get specific DA rate
- `isDAEnabled($month, $year)` - Check if DA is active
- `createDefaultConfigsForYear($year, $userId)` - Initialize year configs
- `validateConfig($data)` - Validation logic

**Usage Example:**
```php
require_once 'app/Models/SalaryConfig.php';
$config = new SalaryConfig();
$daRate = $config->getDARate('contractual', 1, 2026, 'regular'); // Returns 300.00
```

---

### 3. Payroll Model with Multi-Category Logic ✅
**File:** `app/Models/Payroll.php`

**Core Calculation Methods:**
- `applyEmployeeTypeRules($employee, $data)` - Main dispatcher
- `calculatePermanentSalary()` - Existing logic (Basic + HRA + DA - PF - Tax)
- `calculateContractualSalary()` - New: Daily Rate × Days + DA
- `calculateInternSalary()` - New: Stipend + DA

**Key Features:**
- Automatic employee type detection
- Attendance-based working days calculation
- OD/Tour days extraction from attendance records
- Weekend and holiday exclusion
- DA rate lookup from `salary_config`

**Formulas Implemented:**
```
PERMANENT:
  Gross = Basic + HRA + DA + TA + DA_on_TA + Bonus
  Net = Gross - (EPF + NPS + Tax + Prof_Tax + Other)

CONTRACTUAL:
  Base Pay = Daily Rate × Net Working Days
  DA = DA Rate × OD/Tour Days
  Net = Base Pay + DA  (No deductions)

INTERN:
  Stipend = Fixed Amount
  DA = DA Rate × OD/Tour Days
  Net = Stipend + DA  (No deductions)
```

---

### 4. Accountant Salary Configuration UI ✅
**File:** `public/accountant/manage_salary_config.php`

**Features:**
- Month/Year selector with dropdown navigation
- Edit DA rates for all 6 types (contractual, intern, tour, office)
- Enable/Disable DA per month toggle
- Notes field for configuration changes
- Live preview section showing current rates
- Year overview table showing all 12 months
- Sample calculation examples
- Form validation with error messages
- Purple gradient theme matching system design

**Access:**
- URL: `http://localhost/payslip_generator/public/accountant/manage_salary_config.php`
- Navigation: Accountant Dashboard → "Salary Configuration" button
- Sidebar: "Salary Configuration" menu item

**UI Components:**
1. **Header Section** - Purple gradient banner with title
2. **Info Box** - Explains salary calculation rules
3. **Month Selector** - Jump to any month/year
4. **Configuration Form** - 6 DA rate inputs + enable toggle + notes
5. **Preview Card** - Shows current rates and sample calculations
6. **Year Overview Table** - All 12 months at a glance

---

### 5. Navigation & Routes ✅
**Updated Files:**
- `public/accountant/includes/accountant_sidebar.php` - Added "Salary Configuration" link
- `public/accountant/accountant_dashboard.php` - Added "Salary Configuration" quick action button

**Access Points:**
1. Sidebar: Click "Salary Configuration" (cog icon)
2. Dashboard: Click "Salary Configuration" quick action card (orange border)

---

## ⏳ PENDING IMPLEMENTATIONS

### 6. Update Payslip Generation Logic
**File to Update:** `public/accountant/generate_payslip.php`

**Required Changes:**
1. **Employee Selection Dropdown:**
   ```php
   // Add employee_type column to query
   SELECT e.employee_id, e.full_name, e.designation, e.employee_type,
          e.basic_salary, e.daily_rate, e.stipend
   ```

2. **Form Dynamic Fields (JavaScript):**
   ```javascript
   // When employee selected, fetch employee_type
   // Show different form sections:
   // - Permanent: Show Basic, HRA, DA, PF, Tax fields
   // - Contractual: Show Daily Rate field only
   // - Intern: Show Stipend field only
   ```

3. **Backend Processing:**
   ```php
   // Use Payroll model's createPayroll() instead of manual insert
   require_once __DIR__ . '/../../app/Models/Payroll.php';
   $payrollModel = new Payroll();
   $result = $payrollModel->createPayroll([
       'employee_id' => $_POST['employee_id'],
       'month' => $_POST['month'],
       'year' => $_POST['year'],
       'daily_rate' => $_POST['daily_rate'] ?? null,  // For contractual
       'stipend' => $_POST['stipend'] ?? null,         // For intern
       'basic_salary' => $_POST['basic_salary'] ?? null, // For permanent
       // ... other fields
   ]);
   ```

4. **Preview/Breakdown:**
   - Show "Working Days: X" for contractual
   - Show "OD/Tour Days: X" for contractual/intern
   - Show "DA Calculation: ₹300 × 3 days = ₹900"

**Implementation Steps:**
1. Read existing `generate_payslip.php` lines 1-100
2. Add `employee_type` to employee query
3. Add AJAX endpoint to fetch employee details with type
4. Create JavaScript to toggle form fields based on type
5. Replace manual payroll insert with `Payroll::createPayroll()`
6. Add breakdown display section

---

### 7. Update Payslip PDF Format
**File to Update:** `public/accountant/generate_payslip_pdf.php`

**Required Changes:**
1. **Fetch employee_type from payroll:**
   ```php
   $stmt = $db->prepare("
       SELECT p.*, e.employee_type, e.full_name, e.designation
       FROM payroll p
       JOIN employees e ON p.employee_id = e.employee_id
       WHERE p.payroll_id = ?
   ");
   ```

2. **Conditional PDF Sections:**
   ```php
   if ($employeeType === 'permanent') {
       // Show: Basic, HRA, DA, TA, Deductions
   } elseif ($employeeType === 'contractual') {
       // Show: Daily Rate, Working Days, Base Pay, DA Amount, Net
   } elseif ($employeeType === 'intern') {
       // Show: Stipend, DA Amount, Net
   }
   ```

3. **Update TCPDF Content:**
   - Add employee type label: "Employee Category: Contractual"
   - For contractual: "Daily Rate: ₹800 × 22 days = ₹17,600"
   - For intern: "Monthly Stipend: ₹10,000"
   - Show DA separately: "DA (3 OD days × ₹300): ₹900"

**Implementation Steps:**
1. Read existing PDF generation logic
2. Add employee_type to query
3. Create separate PDF templates for each type
4. Use TCPDF conditional blocks
5. Test with sample contractual/intern employees

---

## 🧪 TESTING GUIDE

### Test Scenario 1: Configure DA Rates
1. Login as Accountant
2. Navigate to "Salary Configuration"
3. Select "January 2026"
4. Update rates:
   - Contractual DA: ₹350
   - Intern DA: ₹250
5. Save and verify in year overview

**Expected:** Success message, year table updates

---

### Test Scenario 2: Create Test Employees
```sql
-- Create contractual employee
UPDATE employees 
SET employee_type = 'contractual', daily_rate = 800.00
WHERE employee_id = 1;  -- Replace with actual ID

-- Create intern
UPDATE employees 
SET employee_type = 'intern', stipend = 10000.00
WHERE employee_id = 2;  -- Replace with actual ID
```

---

### Test Scenario 3: Add Attendance Records
```sql
-- Add 22 working days + 3 OD days for contractual employee
INSERT INTO attendance (employee_id, date, status, leave_type)
VALUES 
(1, '2026-01-02', 'Present', NULL),
(1, '2026-01-03', 'Present', NULL),
-- ... repeat for 22 days
(1, '2026-01-20', 'Leave', 'OD'),
(1, '2026-01-21', 'Leave', 'OD'),
(1, '2026-01-22', 'Leave', 'OD');
```

---

### Test Scenario 4: Generate Payslip (Manual Test After UI Update)
1. Go to "Generate Payslip"
2. Select Contractual Employee
3. Select January 2026
4. System should auto-fill:
   - Daily Rate: ₹800
   - Working Days: 22
   - OD Days: 3
5. Submit
6. Verify payroll record:
   ```sql
   SELECT * FROM payroll WHERE employee_id = 1 AND month = 1 AND year = 2026;
   -- Should show:
   -- gross_salary = 17600 + 900 = 18500
   -- net_salary = 18500 (no deductions)
   ```

---

## 📊 DATABASE VERIFICATION QUERIES

### Check Salary Config
```sql
SELECT month, da_rate_contractual, da_rate_intern, da_enabled 
FROM salary_config 
WHERE year = 2026 
ORDER BY month;
```

### Check Employee Types
```sql
SELECT employee_type, COUNT(*) as count 
FROM employees 
GROUP BY employee_type;
```

### Check Payroll Records by Type
```sql
SELECT p.employee_type, COUNT(*) as count, 
       AVG(p.net_salary) as avg_salary
FROM payroll p
WHERE p.month = 1 AND p.year = 2026
GROUP BY p.employee_type;
```

---

## 🔧 TROUBLESHOOTING

### Issue: DA not calculated
**Solution:** Check `salary_config.da_enabled = 1` for that month

### Issue: Working days = 0
**Solution:** Ensure attendance records exist for the employee and month

### Issue: Employee type not detected
**Solution:** Verify `employees.employee_type` is set ('contractual' or 'intern')

### Issue: Foreign key constraint error
**Solution:** Ensure `updated_by` user_id exists in users table

---

## 🚀 DEPLOYMENT CHECKLIST

- [x] Database schema created (`salary_config` table)
- [x] SalaryConfig model implemented
- [x] Payroll model updated with multi-category logic
- [x] Salary Configuration UI created
- [x] Navigation links added
- [x] Default DA rates inserted for 2026
- [ ] Update `generate_payslip.php` (IN PROGRESS)
- [ ] Update `generate_payslip_pdf.php` (PENDING)
- [ ] Test with real contractual/intern employees
- [ ] Create user documentation
- [ ] Train accountants on new features

---

## 📝 NEXT STEPS

1. **Immediate:** Update `generate_payslip.php` to use Payroll model
2. **Short-term:** Update PDF format for multi-category payslips
3. **Medium-term:** Add bulk payslip generation for contractual employees
4. **Long-term:** Add contractor payment tracking and TDS deduction

---

## 📞 SUPPORT

**System Architecture:** PHP 8.x + MySQL 5.7+ + XAMPP on macOS  
**Database:** payslip_generator  
**Framework:** Custom MVC-like architecture  
**Documentation:** See `/docs/guides/` for detailed guides

**Key Files:**
- Schema: `database/contractual_intern_salary_schema.sql`
- Model: `app/Models/Payroll.php`, `app/Models/SalaryConfig.php`
- UI: `public/accountant/manage_salary_config.php`
- Config: `app/Config/database.php`

---

**Last Updated:** 15 January 2026  
**Version:** 1.0.0  
**Status:** Core implementation complete, UI integration pending
