# 🧪 e-HRMS Manual Testing Guide

## 📋 Pre-Testing Setup

### Step 1: Setup Test Data
```bash
# Run from project root
cd /Applications/XAMPP/xamppfiles/htdocs/payslip_generator

# Import test data
mysql -u root --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock payslip_generator < setup_test_data.sql

# Verify setup
./run_all_tests.sh
```

### Step 2: Make Test Scripts Executable
```bash
chmod +x run_all_tests.sh
chmod +x test-rbac.sh
```

---

## 🔐 Test Accounts

| Role | Username | Password | Portal URL |
|------|----------|----------|------------|
| Super Admin | `superadmin` | `test123` | `/super_admin/dashboard.php` |
| Administrator | `admin` | `test123` | `/admin/admin_dashboard.php` |
| HR Officer | `hrofficer` | `test123` | `/hr_officer/dashboard.php` |
| Director | `director` | `test123` | `/director/director_dashboard.php` |
| Accountant | `accountant` | `test123` | `/accountant/accountant_dashboard.php` |
| Auditor | `auditor` | `test123` | `/auditor/dashboard.php` |
| Employee 1 | `rajesh.kumar` | `test123` | `/employee/dashboard.php` |
| Employee 2 | `priya.sharma` | `test123` | `/employee/dashboard.php` |
| Multi-Role | `multirole` | `test123` | Both portals |

**Base URL**: `http://localhost/payslip_generator/public`

---

## 🧭 Testing Workflow

### Phase 1: Authentication Testing (15 min)

#### Test 1.1: Login Page
- [ ] Navigate to `http://localhost/payslip_generator/public/auth/login.php`
- [ ] Verify page loads without errors (check browser console)
- [ ] Check CSS styling is applied correctly
- [ ] Test invalid credentials → Should show error message
- [ ] Test valid credentials → Should redirect to appropriate dashboard

#### Test 1.2: Role-Based Redirects
Test each role logs into correct portal:
- [ ] `superadmin` → Super Admin Dashboard
- [ ] `admin` → Administrator Dashboard
- [ ] `hrofficer` → HR Officer Dashboard
- [ ] `director` → Director Dashboard
- [ ] `accountant` → Accountant Dashboard
- [ ] `auditor` → Auditor Dashboard
- [ ] `rajesh.kumar` → Employee Dashboard

#### Test 1.3: Session Management
- [ ] Login successfully
- [ ] Close browser and reopen → Should still be logged in
- [ ] Click Logout → Should redirect to login page
- [ ] Try accessing dashboard URL after logout → Should redirect to login

---

### Phase 2: Administrator Portal (30 min)

**Login as**: `admin` / `test123`

#### Test 2.1: Dashboard
- [ ] Dashboard loads without errors
- [ ] All stat cards display correctly with gradient styling
- [ ] Numbers in stat cards are accurate
- [ ] Recent activities section shows data
- [ ] Charts/graphs render properly (if any)
- [ ] Sidebar navigation is visible and functional
- [ ] Hover effects work on cards

#### Test 2.2: Employee Management
**Navigate to**: Employees → View All

- [ ] **List View**:
  - [ ] Employee table loads
  - [ ] Pagination works (if more than 10 employees)
  - [ ] Search functionality works
  - [ ] Filter by department works
  - [ ] Action buttons (Edit/Delete) are visible

- [ ] **Add Employee**:
  - [ ] Click "Add Employee" button
  - [ ] Fill all required fields
  - [ ] Select department from dropdown
  - [ ] Set employment type (Permanent/Contract)
  - [ ] Submit form
  - [ ] Verify success message
  - [ ] Check new employee appears in list

- [ ] **Edit Employee**:
  - [ ] Click edit icon on any employee
  - [ ] Verify form pre-fills with existing data
  - [ ] Modify salary/designation
  - [ ] Submit changes
  - [ ] Verify changes reflected in list

- [ ] **Delete Employee**:
  - [ ] Click delete icon
  - [ ] Verify confirmation dialog appears
  - [ ] Confirm deletion
  - [ ] Verify employee removed from list (or marked inactive)

#### Test 2.3: Department Management
**Navigate to**: Departments

- [ ] Department list loads
- [ ] Add new department works
- [ ] Edit department name/description works
- [ ] Delete department works (should check for dependencies)

#### Test 2.4: User Management
**Navigate to**: Users → Manage Users

- [ ] User list loads with role information
- [ ] Create new user account works
- [ ] Assign role to user works
- [ ] Toggle user active/inactive status works
- [ ] Reset user password works

#### Test 2.5: Attendance Management
**Navigate to**: Attendance

- [ ] View attendance records
- [ ] Add manual attendance record
- [ ] Edit existing attendance
- [ ] Filter by date range
- [ ] Filter by employee
- [ ] Export to Excel works

---

### Phase 3: Accountant Portal (45 min)

**Login as**: `accountant` / `test123`

#### Test 3.1: Dashboard
- [ ] Dashboard loads
- [ ] Financial summary cards display
- [ ] Recent payroll activities shown
- [ ] Month-wise statistics visible

#### Test 3.2: Generate Payslip
**Navigate to**: Generate Payslip

- [ ] **Form Pre-fill**:
  - [ ] Select employee → Basic salary auto-fills
  - [ ] Select month and year
  - [ ] Standard rates show (HRA, DA, PF, NPS)

- [ ] **Allowances Section**:
  - [ ] Enter HRA (or use auto-calculated)
  - [ ] Enter DA (or use 58% of basic)
  - [ ] Enter TA
  - [ ] Enter DA on TA
  - [ ] Enter Bonus
  - [ ] Verify Gross Salary calculates correctly

- [ ] **Deductions Section**:
  - [ ] Enter Tax
  - [ ] Enter PF (or use 12% of basic)
  - [ ] Enter NPS (or use 10% of basic)
  - [ ] Enter Professional Tax (default ₹200)
  - [ ] Enter other deductions
  - [ ] Verify Net Salary calculates correctly

- [ ] **Submit**:
  - [ ] Click Generate Payslip
  - [ ] Verify success message
  - [ ] Check payslip created in database
  - [ ] Download PDF works
  - [ ] PDF contains correct information

#### Test 3.3: Payroll Management
**Navigate to**: Payroll Management

- [ ] View all payroll records
- [ ] Filter by month/year
- [ ] Filter by employee
- [ ] Edit existing payroll
- [ ] View payslip details
- [ ] Export payroll report

#### Test 3.4: Financial Reports
**Navigate to**: Financial Reports

- [ ] Generate monthly payroll report
- [ ] Generate yearly summary
- [ ] Export to Excel
- [ ] Print report functionality

---

### Phase 4: Director Portal (20 min)

**Login as**: `director` / `test123`

#### Test 4.1: Dashboard
- [ ] Dashboard loads with overview statistics
- [ ] Pending approvals count visible
- [ ] Recent activities displayed

#### Test 4.2: Salary Approvals
**Navigate to**: Salary Approvals

- [ ] View pending salary change requests
- [ ] Approve a salary change
- [ ] Reject a salary change with reason
- [ ] View approval history

#### Test 4.3: Role Change Approvals
**Navigate to**: Role Approvals

- [ ] View pending role change requests
- [ ] Approve role change
- [ ] Reject role change
- [ ] View approval audit trail

---

### Phase 5: Employee Portal (30 min)

**Login as**: `rajesh.kumar` / `test123`

#### Test 5.1: Dashboard
- [ ] Dashboard loads
- [ ] Welcome message shows employee name
- [ ] Quick stats display (Attendance %, Leaves, etc.)
- [ ] Sidebar navigation works
- [ ] Profile avatar displays

#### Test 5.2: My Profile
**Navigate to**: My Profile

- [ ] View profile page loads
- [ ] Avatar with initials displays
- [ ] Personal information section shows all details
- [ ] Employment information section correct
- [ ] Salary information visible
- [ ] Profile cards have proper styling

#### Test 5.3: Edit Profile
**Navigate to**: Edit Profile

- [ ] Form loads with current data
- [ ] Update phone number
- [ ] Update address
- [ ] Update emergency contact
- [ ] Save changes
- [ ] Verify success message
- [ ] Check changes reflected in My Profile

#### Test 5.4: View Payslips
**Navigate to**: Payslips

- [ ] Payslip list loads
- [ ] Filter by year works
- [ ] Filter by month works
- [ ] View payslip details modal/page
- [ ] Download PDF works
- [ ] PDF displays correctly
- [ ] Print payslip works

#### Test 5.5: Attendance
**Navigate to**: Attendance

- [ ] Attendance records load
- [ ] Summary cards show correct counts:
  - [ ] Attendance Rate %
  - [ ] Present Days (green)
  - [ ] Absent Days (red)
  - [ ] Leave Days (amber)
- [ ] Status badges color-coded correctly
- [ ] Date format displays properly
- [ ] Empty state shows if no records

#### Test 5.6: Attendance Calendar
**Navigate to**: Attendance Calendar

- [ ] **Calendar View**:
  - [ ] Calendar loads with current month
  - [ ] Full week headers (Sun-Sat) display
  - [ ] Today's date highlighted with star icon
  - [ ] Attendance indicators (colored dots) show on dates
  - [ ] Holiday badges display correctly
  - [ ] Leave badges show approved leaves

- [ ] **Navigation**:
  - [ ] Previous month button works
  - [ ] Next month button works
  - [ ] "Today" button returns to current month

- [ ] **Filters**:
  - [ ] Month selector works
  - [ ] Employee filter works (if applicable)
  - [ ] Apply filter button functions

- [ ] **Summary Stats**:
  - [ ] Present days count correct (green card)
  - [ ] Absent days count correct (red card)
  - [ ] Leave days count correct (amber card)
  - [ ] Holidays count correct (orange card)

- [ ] **Legend**:
  - [ ] All legend items visible
  - [ ] Color dots match calendar indicators

- [ ] **Holidays Section**:
  - [ ] Government holidays list displays
  - [ ] Holiday dates formatted correctly
  - [ ] Optional tag shows for optional holidays

- [ ] **Hover Effects**:
  - [ ] Calendar cells have hover animation
  - [ ] Stat cards elevate on hover
  - [ ] Tooltips show on indicator dots

- [ ] **Responsive**:
  - [ ] Calendar works on mobile view
  - [ ] Stats stack vertically on small screens
  - [ ] Touch navigation works

#### Test 5.7: Leave Management
**Navigate to**: Leave Management

- [ ] Apply for leave form
- [ ] Select leave type (Casual/Sick/Earned)
- [ ] Select date range
- [ ] Enter reason
- [ ] Submit leave request
- [ ] View pending leave requests
- [ ] View leave history
- [ ] Cancel pending leave

---

### Phase 6: Multi-Role Testing (15 min)

**Login as**: `multirole` / `test123`

#### Test 6.1: Role Switching
- [ ] Login redirects to primary role portal
- [ ] Session shows `has_multiple_roles = true`
- [ ] Can manually navigate to `/employee/dashboard.php`
- [ ] Can manually navigate to `/accountant/accountant_dashboard.php`
- [ ] Both portals function correctly
- [ ] Role switcher displays (if implemented)

#### Test 6.2: Multi-Role Permissions
- [ ] As employee: Can view own payslips
- [ ] As accountant: Can generate payslips for others
- [ ] Cannot access admin functions
- [ ] Cannot access director functions

---

### Phase 7: Security Testing (15 min)

#### Test 7.1: Unauthorized Access
- [ ] Logout completely
- [ ] Try accessing `/admin/admin_dashboard.php` → Should redirect to login
- [ ] Login as employee
- [ ] Try accessing `/admin/employees.php` → Should deny access
- [ ] Try accessing `/accountant/generate_payslip.php` → Should deny access

#### Test 7.2: SQL Injection Protection
- [ ] In login form, try: `' OR '1'='1`
- [ ] Should not bypass authentication
- [ ] In search fields, try SQL injection patterns
- [ ] Should handle safely

#### Test 7.3: XSS Protection
- [ ] In any form, try: `<script>alert('XSS')</script>`
- [ ] Should sanitize and not execute script

---

### Phase 8: UI/UX Testing (20 min)

#### Test 8.1: Design System Consistency
- [ ] **Colors**: Purple gradient (#667eea → #764ba2) used consistently
- [ ] **Cards**: White background, 15px border-radius, proper shadow
- [ ] **Buttons**: Gradient on hover, proper padding
- [ ] **Typography**: Roboto font loads correctly
- [ ] **Icons**: Font Awesome icons display properly

#### Test 8.2: Responsive Design
- [ ] **Desktop (>1200px)**:
  - [ ] Sidebar 260px width
  - [ ] Main content adjusts
  - [ ] Cards in grid layout

- [ ] **Tablet (768-1199px)**:
  - [ ] Sidebar collapses or remains functional
  - [ ] Card grids adjust to 2 columns
  - [ ] Navigation remains accessible

- [ ] **Mobile (≤768px)**:
  - [ ] Sidebar becomes hamburger menu
  - [ ] Cards stack vertically
  - [ ] Tables scroll horizontally
  - [ ] Forms are touch-friendly

#### Test 8.3: Animations & Interactions
- [ ] Hover effects on cards (translateY)
- [ ] Button hover effects (shadow increase)
- [ ] Loading states (if any)
- [ ] Smooth transitions (0.3s ease)
- [ ] Page load animations (slideUp)

---

### Phase 9: Data Integrity Testing (15 min)

#### Test 9.1: Attendance Records
- [ ] Add attendance for today → Should save correctly
- [ ] Try adding duplicate attendance → Should prevent or update
- [ ] View attendance calendar → Today's record shows
- [ ] Statistics update correctly

#### Test 9.2: Payroll Generation
- [ ] Generate payslip for January 2026
- [ ] Try generating again → Should prevent duplicate or update
- [ ] Check database: payroll + payslips records created
- [ ] PDF matches database values

#### Test 9.3: Leave Requests
- [ ] Apply leave for future date
- [ ] Check calendar shows leave badge
- [ ] Approve leave (as director/HR)
- [ ] Check status updated
- [ ] Verify cannot apply overlapping leave

---

### Phase 10: Performance Testing (10 min)

#### Test 10.1: Page Load Speed
- [ ] Dashboard loads < 3 seconds
- [ ] Large employee list loads < 5 seconds
- [ ] Calendar with 30 days loads < 3 seconds
- [ ] PDF generation completes < 5 seconds

#### Test 10.2: Database Queries
Check logs or enable query debugging:
- [ ] No N+1 query problems
- [ ] Proper indexes used
- [ ] Large datasets paginated

---

## 📊 Test Results Template

```
Date: __________
Tester: __________
Browser: __________
OS: macOS

PHASE 1 - Authentication: __ / __ tests passed
PHASE 2 - Administrator: __ / __ tests passed
PHASE 3 - Accountant: __ / __ tests passed
PHASE 4 - Director: __ / __ tests passed
PHASE 5 - Employee: __ / __ tests passed
PHASE 6 - Multi-Role: __ / __ tests passed
PHASE 7 - Security: __ / __ tests passed
PHASE 8 - UI/UX: __ / __ tests passed
PHASE 9 - Data Integrity: __ / __ tests passed
PHASE 10 - Performance: __ / __ tests passed

TOTAL: __ / __ tests passed (__%)

CRITICAL BUGS FOUND:
1. __________
2. __________

MINOR ISSUES:
1. __________
2. __________
```

---

## 🐛 Bug Reporting Format

When you find a bug, document it like this:

```
BUG ID: BUG-001
SEVERITY: Critical/High/Medium/Low
MODULE: Employee Portal - Attendance Calendar
DESCRIPTION: Calendar shows wrong month
STEPS TO REPRODUCE:
1. Login as employee
2. Navigate to Attendance Calendar
3. Click "Next Month" button
EXPECTED: Should show next month
ACTUAL: Shows same month
BROWSER: Chrome 120
SCREENSHOT: [attach if possible]
```

---

## ✅ Quick Start

```bash
# 1. Setup test data
mysql -u root --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock payslip_generator < setup_test_data.sql

# 2. Run automated tests
./run_all_tests.sh

# 3. Start manual testing
open http://localhost/payslip_generator/public/auth/login.php

# 4. Login with any test account (password: test123)
```

---

## 📞 Support

If you encounter issues during testing:
1. Check browser console for JavaScript errors (F12)
2. Check PHP error logs: `/Applications/XAMPP/xamppfiles/logs/php_error_log`
3. Check Apache error logs: `/Applications/XAMPP/xamppfiles/logs/error_log`
4. Verify XAMPP services are running

Happy Testing! 🚀
