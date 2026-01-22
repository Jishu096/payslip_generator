# 🧪 e-HRMS Testing Checklist
**Date**: 16 January 2026  
**URL**: http://localhost/payslip_generator/public/

---

## 🔐 Authentication Testing

### Login Page
- [ ] Page loads without errors
- [ ] All fields visible and styled correctly
- [ ] Login with valid credentials works
- [ ] Login with invalid credentials shows error
- [ ] Logout functionality works from all portals

### Test Credentials
| Role | Username | Password | Status |
|------|----------|----------|--------|
| Administrator | admin | admin123 | ⬜ Not Tested |
| Accountant | accountant | accountant123 | ⬜ Not Tested |
| Director | director | director123 | ⬜ Not Tested |
| Employee | employee | employee123 | ⬜ Not Tested |
| HR Officer | hr_officer | (check DB) | ⬜ Not Tested |
| Auditor | auditor | auditor123 | ⬜ Not Tested |
| Super Admin | (check DB) | (check DB) | ⬜ Not Tested |

---

## 👨‍💼 Administrator Portal

### Dashboard
- [ ] Page loads without CSS/JS errors
- [ ] All stat cards display correctly
- [ ] Gradient stats are visible
- [ ] Sidebar navigation works
- [ ] Hover effects on cards work
- [ ] Responsive on smaller screens

### Employees Management
- [ ] Employee list loads
- [ ] Add new employee works
- [ ] Edit employee works
- [ ] Delete employee works (with confirmation)
- [ ] Search/filter functions work

### Departments Management
- [ ] Department list loads
- [ ] Add department works
- [ ] Edit department works
- [ ] Delete department works

### Users Management
- [ ] User list loads
- [ ] Create user works
- [ ] Assign roles works
- [ ] Toggle user status works
- [ ] Reset password works

### Attendance Management
- [ ] Attendance records load
- [ ] Add attendance works
- [ ] Calendar view works
- [ ] Export functionality works

---

## 💰 Accountant Portal

### Dashboard
- [ ] Stats display correctly
- [ ] Gradient theme applied
- [ ] Recent activities show

### Payslip Generation
- [ ] Employee selection works
- [ ] Month/year picker works
- [ ] Standard rates pre-fill
- [ ] Real-time calculation works
- [ ] Submit creates payslip
- [ ] PDF generation works
- [ ] Download PDF works

### Payroll Management
- [ ] Payroll records load
- [ ] Filter by month/year works
- [ ] Edit payroll works
- [ ] Approval status shows

### Salary Configuration
- [ ] DA rates display
- [ ] Edit configuration works
- [ ] Monthly config updates

### Attendance Statement
- [ ] Generate statement works
- [ ] Excel export works
- [ ] Regular/Contract filter works

---

## 👔 Director Portal

### Dashboard
- [ ] Pending approvals count correct
- [ ] Total payout calculated
- [ ] Approved/rejected stats show
- [ ] Period display works

### Salary Approvals
- [ ] Pending salary changes list
- [ ] Approve action works
- [ ] Reject action works
- [ ] Approval updates in DB

### Role Approvals
- [ ] Pending role changes list
- [ ] Approve action works
- [ ] Reject action works

### Reports
- [ ] Financial reports load
- [ ] Export functionality works

---

## 👤 Employee Portal

### Dashboard
- [ ] Profile banner shows
- [ ] Basic salary displays
- [ ] Attendance stats correct
- [ ] Recent payslips load

### View Payslips
- [ ] Payslip list loads
- [ ] Filter by month/year works
- [ ] View payslip details works
- [ ] Download PDF works

### My Attendance
- [ ] Attendance records show
- [ ] Calendar view works
- [ ] Status indicators correct

### Leave Management
- [ ] Leave requests load
- [ ] Apply for leave works
- [ ] Leave balance shows

### My Profile
- [ ] Profile details display
- [ ] Edit profile works
- [ ] Update request works

---

## 👥 HR Officer Portal

### Dashboard
- [ ] Pending verifications count
- [ ] Attendance issues show
- [ ] Disputes count correct
- [ ] Active employees count

### Verify Attendance
- [ ] Pending sheets load
- [ ] Verify action works
- [ ] Bulk verify works

### Leave Management
- [ ] Leave requests show
- [ ] Approve/reject works

### Manual Entry
- [ ] Manual attendance entry works
- [ ] Bulk upload works

---

## 🔍 Auditor Portal

### Dashboard
- [ ] Monitoring stats show
- [ ] Period display works
- [ ] Quick action links work

### Attendance Reports
- [ ] Reports load
- [ ] Filters work
- [ ] Export CSV works
- [ ] Date range selection works

### Payroll Reports
- [ ] Financial stats correct
- [ ] Filters work
- [ ] Export works

### Approval History
- [ ] History loads
- [ ] Status filters work
- [ ] Timeline display correct

### Audit Trail
- [ ] Audit logs load
- [ ] User filter works
- [ ] Date filter works
- [ ] Action type filter works

---

## 👑 Super Admin Portal

### Dashboard
- [ ] System status shows ONLINE
- [ ] User count correct
- [ ] Security alerts (should be 0)
- [ ] Audit logs count

### Users Management
- [ ] Advanced user management
- [ ] Permission assignment works

### Roles Management
- [ ] Role list loads
- [ ] Create role works
- [ ] Edit permissions works

### Security
- [ ] Security settings load
- [ ] Audit log access
- [ ] System logs viewable

---

## 🎨 UI/UX Testing

### Consistency Across Portals
- [ ] All portals have unified sidebar
- [ ] Gradient stats in all dashboards
- [ ] Hover effects consistent
- [ ] Color scheme uniform
- [ ] Font sizes consistent

### Responsive Design
- [ ] Mobile view (320px-480px)
- [ ] Tablet view (768px-1024px)
- [ ] Desktop view (1280px+)
- [ ] Sidebar collapses on mobile
- [ ] Tables scroll horizontally on small screens

### Browser Compatibility
- [ ] Chrome/Edge (test first)
- [ ] Firefox
- [ ] Safari

---

## 🔒 Security Testing

### Access Control
- [ ] Unauthorized users redirected to login
- [ ] Role-based page access enforced
- [ ] Multi-role users can access multiple portals
- [ ] Session timeout works
- [ ] Logout clears session

### SQL Injection Prevention
- [ ] Test with `' OR '1'='1` in login
- [ ] Test with SQL in search fields
- [ ] Verify prepared statements used

### XSS Prevention
- [ ] Test with `<script>alert('XSS')</script>`
- [ ] Verify htmlspecialchars() used
- [ ] Check form inputs sanitized

---

## ⚡ Performance Testing

### Page Load Times
- [ ] Login page < 2 seconds
- [ ] Dashboard pages < 3 seconds
- [ ] Reports page < 5 seconds

### Database Queries
- [ ] No N+1 query problems
- [ ] Large lists paginated
- [ ] Indexes on foreign keys

---

## 🐛 Bug Tracking

### Issues Found
1. 
2. 
3. 

### Fixed Issues
1. ✅ Calendar overflow in attendance_calendar.php (16 Jan 2026)

---

## ✅ Test Summary

**Total Tests**: 150+  
**Passed**: ___  
**Failed**: ___  
**Blocked**: ___  

**Overall Status**: 🟡 In Progress

---

## 📝 Notes

- Priority: Test core functionality first (login, payslip generation, attendance)
- Test with sample data from database/sample_data.sql
- Check browser console for JavaScript errors
- Check PHP error logs in /Applications/XAMPP/xamppfiles/logs/

**Next Steps After Testing**:
1. Fix critical bugs
2. Polish UI issues
3. Document known limitations
