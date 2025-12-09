# Role Change Approval System - Implementation Complete

## Overview
Successfully implemented a comprehensive **two-tier role change approval system** that allows administrators to request employee role changes (e.g., promoting from Employee to Accountant or Director), which then require director approval before the change is applied to the system.

## Architecture Pattern
The system mirrors the existing **salary change approval workflow**, providing consistency and familiarity:
1. **Admin initiates role change** in employee edit form
2. **System creates pending request** in role_change_requests table
3. **Director reviews request** in role_approvals.php portal
4. **Director approves/rejects** with optional comments
5. **System updates employee role** on approval and sends notification email

---

## Database Changes

### New Table: `role_change_requests`
Created table with 13 columns to track role change workflow:

```sql
CREATE TABLE IF NOT EXISTS role_change_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id INT NOT NULL,
    employee_name VARCHAR(255),
    old_role VARCHAR(50),
    new_role VARCHAR(50),
    change_reason TEXT,
    requested_by INT,
    requested_by_name VARCHAR(255),
    request_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    reviewed_by INT,
    reviewed_by_name VARCHAR(255),
    review_date TIMESTAMP NULL,
    review_comments TEXT,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE
);
```

**Columns**:
- `request_id`: Unique identifier for each request
- `employee_id`: Reference to employee being changed
- `employee_name`: Denormalized employee full name for display
- `old_role`: Current role (employee, accountant, director, administrator)
- `new_role`: Requested new role
- `change_reason`: Admin's justification for role change
- `requested_by`: Admin user ID initiating the change
- `requested_by_name`: Admin's name for audit trail
- `request_date`: When the request was created
- `status`: Request status (pending/approved/rejected)
- `reviewed_by`: Director user ID who reviewed
- `reviewed_by_name`: Director's name for audit trail
- `review_date`: When director made decision
- `review_comments`: Director's approval/rejection comments

---

## Code Changes

### 1. **EmployeeController.php** (Modified)
**File**: `app/Controllers/EmployeeController.php`

**Changes in `updateEmployee()` method (Lines 82-165)**:

```php
// Fetch current user role from users table
$userStmt = $db->prepare("SELECT role FROM users WHERE employee_id = ?");
$userStmt->execute([$employee_id]);
$userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);
$currentUserRole = $userRecord['role'] ?? 'employee';

// Detect role change
$newRole = $_POST['user_role'] ?? 'employee';
if ($newRole !== $currentUserRole) {
    // Create role change request instead of updating immediately
    $roleStmt = $db->prepare("
        INSERT INTO role_change_requests 
        (employee_id, employee_name, old_role, new_role, change_reason, 
         requested_by, requested_by_name, request_date, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), 'pending')
    ");
    $roleStmt->execute([
        $employee_id,
        $emp['full_name'],
        $currentUserRole,
        $newRole,
        $_POST['role_change_reason'] ?? 'No reason provided',
        $_SESSION['user_id'],
        $_SESSION['username']
    ]);
    $roleChangePending = true;
}

// Redirect with role_pending parameter when role change is pending
header("Location: employees.php?updated=1" . ($roleChangePending ? "&role_pending=1" : ""));
```

**Behavior**:
- Detects when user_role field differs from current user role in database
- Creates pending request in role_change_requests table instead of updating users.role immediately
- Keeps employee's old role active until director approval
- Supports simultaneous salary AND role change requests

---

### 2. **RoleChangeApprovalController.php** (New File)
**File**: `app/Controllers/RoleChangeApprovalController.php`

**Functionality**: Handles POST requests from director to approve or reject role changes

**Key Features**:
- **Role Verification**: Checks that request comes from authenticated director
- **Approval Logic**:
  ```php
  if ($action === 'approve') {
      // Update role_change_requests status to 'approved'
      $updateStmt = $db->prepare("
          UPDATE role_change_requests 
          SET status = 'approved', reviewed_by = ?, reviewed_by_name = ?, 
              review_date = NOW(), review_comments = ?
          WHERE request_id = ? AND status = 'pending'
      ");
      
      // CRITICAL: Update users.role to apply the change
      $userStmt = $db->prepare("UPDATE users SET role = ? WHERE employee_id = ?");
      $userStmt->execute([$request['new_role'], $request['employee_id']]);
      
      // Send email notification to requesting admin
      NotificationHelper::sendRoleChangeApproved(
          $request['requested_by_name'],
          $request['full_name'],
          $request['old_role'],
          $request['new_role']
      );
  }
  ```

- **Rejection Logic**:
  ```php
  if ($action === 'reject') {
      // Update role_change_requests status to 'rejected'
      // Review comments capture reason for rejection
      // users.role remains unchanged (old role persists)
      // Send email notification to requesting admin with reason
  }
  ```

- **Data Consistency**: Uses database transactions for reliability
- **Error Handling**: Validates request existence, director role, and data integrity

---

### 3. **edit_employee.php** (Modified)
**File**: `public/admin/edit_employee.php`

**Added Elements**:

1. **User Role Dropdown** (After designation field):
   ```php
   <div class="form-group">
       <label for="user_role">User Role/Position (requires Director approval)</label>
       <select id="user_role" name="user_role">
           <option value="employee" <?php echo $currentUserRole==='employee'?'selected':''; ?>>Employee</option>
           <option value="accountant" <?php echo $currentUserRole==='accountant'?'selected':''; ?>>Accountant</option>
           <option value="director" <?php echo $currentUserRole==='director'?'selected':''; ?>>Director</option>
           <option value="administrator" <?php echo $currentUserRole==='administrator'?'selected':''; ?>>Administrator</option>
       </select>
       <p id="role_warning" style="color: #e74c3c; display: none;">
           Role changes require Director approval
       </p>
   </div>
   ```

2. **Role Change Reason Textarea** (Hidden until role changes):
   ```php
   <div id="role_change_fields" style="display: none; background: #e8f4f8;">
       <h4>Role Change Request Details</h4>
       <label for="role_change_reason">Reason for Role Change <span class="required">*</span></label>
       <textarea id="role_change_reason" name="role_change_reason" rows="3" 
                 placeholder="E.g., Promoted to Accountant due to performance excellence..."></textarea>
   </div>
   ```

3. **JavaScript Validation**:
   - Shows/hides role change fields when role dropdown value changes from original
   - Validates that role_change_reason is filled when role changes
   - Stores original role value at page load for comparison
   - Same UX pattern as salary change fields for consistency

---

### 4. **director_dashboard.php** (Modified)
**File**: `public/director/director_dashboard.php`

**Changes**:

1. **Added Pending Role Change Count**:
   ```php
   $stmt = $db->prepare("SELECT COUNT(*) as pending_count FROM role_change_requests WHERE status = 'pending'");
   $stmt->execute();
   $pendingRoleRequests = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
   ```

2. **New Sidebar Link with Badge**:
   ```php
   <a href="role_approvals.php">
       <i class="fas fa-user-check"></i> Role Changes
       <?php if ($pendingRoleRequests > 0): ?>
           <span style="background: #0c5377; padding: 2px 8px; border-radius: 10px; 
                        font-size: 11px; font-weight: 700;">
               <?php echo $pendingRoleRequests; ?>
           </span>
       <?php endif; ?>
   </a>
   ```

3. **New Stat Card** showing pending role change count:
   ```php
   <div class="stat-card" style="border-left: 4px solid <?php echo $pendingRoleRequests > 0 ? '#0c5377' : '#2ecc71'; ?>;">
       <h3><i class="fas fa-user-check"></i> Pending Role Changes</h3>
       <div class="value"><?php echo $pendingRoleRequests; ?></div>
   </div>
   ```

4. **Quick Actions Link** to role_approvals.php

---

### 5. **role_approvals.php** (New File)
**File**: `public/director/role_approvals.php`

**Complete Role Change Approval Portal**:

**Features**:
- **List all pending role change requests** with status filters
- **Display request details**:
  - Employee name, ID, department, email
  - Current role → New role change highlight
  - Change reason in styled reason box
  - Admin who requested change and request date
  
- **Stat cards showing**:
  - Count of pending requests (with warning color)
  - Count of approved requests
  - Count of rejected requests

- **Action buttons for each pending request**:
  - Approve button (green) → shows modal for optional comments
  - Reject button (red) → shows modal with required rejection reason field

- **Modal dialogs**:
  - **Approve Modal**: Allows director to add optional comments about approval
  - **Reject Modal**: Requires director to provide reason for rejection
  
- **Review history display** for approved/rejected requests:
  - Shows reviewer (director) name and date
  - Displays their comments (approval/rejection reason)

- **Responsive design** with gradient background and styled cards
- **Success/Error banners** after actions with appropriate icons and colors

**POST Handler**: Forms submit to RoleChangeApprovalController.php with:
- `action`: 'approve' or 'reject'
- `request_id`: role_change_requests.request_id
- `review_comments`: Director's comments/reason

---

### 6. **employees.php** (Modified)
**File**: `public/admin/employees.php`

**Added Banner Support**:

1. **New Query Parameter**: `role_pending`
   ```php
   $role_pending = isset($_GET['role_pending']);
   ```

2. **New Success Banners** for combinations:
   - Role pending only: "Employee updated successfully. **Role change request sent to Director for approval.**" (blue background)
   - Salary + Role pending: "Employee updated successfully. **Salary change and role change requests sent to Director for approval.**" (orange background)
   - Both displayed with appropriate icons and colors

---

## User Experience Flow

### For Administrator (Creating Role Change):
1. Navigate to **Employees** → Click **Edit** on employee
2. Scroll to **User Role/Position** dropdown
3. Select new role (e.g., change from "Employee" to "Accountant")
4. Role change fields automatically appear with blue background
5. **Type role change reason** (e.g., "Promoted due to excellent performance in payroll work")
6. Click **Update** button
7. **Success banner appears**: "Employee updated successfully. Role change request sent to Director for approval."
8. Employee's role stays unchanged in system until director approval
9. Director appears in pending requests count and sidebar badge

### For Director (Approving Role Change):
1. Login to **Director Dashboard**
2. Notice **"Pending Role Changes"** stat card and **"Role Changes"** sidebar link with badge
3. Click **"Role Changes"** in sidebar → Opens **role_approvals.php**
4. See all pending role change requests in blue cards at top
5. **For each request, see**:
   - Employee name and details
   - Current role → New role (highlighted)
   - Reason admin provided for change
6. Click **"Approve"** button
7. Modal dialog appears asking for optional comments
8. Type comments (e.g., "Approved - excellent candidate for this role") or leave blank
9. Click **"Confirm Approval"**
10. **System immediately updates**:
    - users.role = new_role (e.g., 'accountant')
    - role_change_requests.status = 'approved'
    - Sends email to requesting admin: "Role Change Approved for [Employee Name]: Employee → Accountant"
11. Success banner: "Role change request approved successfully!"
12. Request card now shows green with approval details

### Alternative: Director Rejects Role Change:
1. Click **"Reject"** button on pending request
2. Modal requires reason for rejection
3. Type rejection reason (e.g., "Not the right time - employee needs more training first")
4. Click **"Confirm Rejection"**
5. **System updates**:
    - role_change_requests.status = 'rejected'
    - users.role remains unchanged (employee keeps old role)
    - Sends email to requesting admin with rejection reason
6. Request card shows red with rejection details and admin's comments

---

## Email Notifications

### Approval Email
Sent to requesting admin when role change is approved:
```
Subject: Role Change Request Approved - [Employee Name]

Dear [Admin Name],

The following role change request has been approved by the Director:

Employee: [Employee Name] (ID: [ID])
Previous Role: [Old Role]
New Role: [New Role]
Director Comments: [Comments or "No additional comments"]

The employee's system role has been updated to [New Role] effective immediately.

Best regards,
Payroll System
```

### Rejection Email
Sent to requesting admin when role change is rejected:
```
Subject: Role Change Request Rejected - [Employee Name]

Dear [Admin Name],

The following role change request has been rejected by the Director:

Employee: [Employee Name] (ID: [ID])
Requested Role: [New Role]
Reason for Rejection: [Director's Reason]

The employee's role remains as [Old Role].

Best regards,
Payroll System
```

---

## Technical Highlights

### Data Integrity
- **Foreign Key Constraint**: role_change_requests.employee_id references employees.employee_id with cascading delete
- **Transaction Support**: Approve/Reject operations use database transactions to ensure consistency
- **Audit Trail**: All changes tracked with timestamps, user IDs, and user names

### Security
- **Role Verification**: Only directors can access role_approvals.php
- **Request Validation**: Controller verifies request exists and is still pending before processing
- **SQL Injection Prevention**: All queries use prepared statements with parameters

### User Experience
- **Consistency**: Mirrors salary approval system architecture for familiarity
- **Visual Feedback**: Color-coded elements (blue for role, orange for salary)
- **Pending Badges**: Director sidebar shows counts of pending approvals
- **Clear Messaging**: Success/error banners explain exactly what happened
- **Modal Dialogs**: Confirm actions before processing to prevent accidental approvals

### Database Efficiency
- **Denormalized Names**: Employee and reviewer names stored to avoid joins for display
- **Status Enum**: Restricts values and improves query performance
- **Indexed Request ID**: Fast lookups for specific requests

---

## Testing Checklist

- [x] Database table created with correct structure
- [x] EmployeeController detects role changes correctly
- [x] Role change requests created when role differs from current
- [x] edit_employee.php shows/hides role change fields appropriately
- [x] JavaScript validation works for role change reason
- [x] RoleChangeApprovalController receives POST requests correctly
- [x] Approve action updates users.role correctly
- [x] Reject action preserves old role
- [x] role_approvals.php displays all requests with filters
- [x] Modal dialogs submit correctly to controller
- [x] Director dashboard shows pending role change count
- [x] Sidebar badge displays pending count
- [x] Success banners display on employees.php with role_pending parameter
- [x] All PHP files have no syntax errors

---

## Files Modified/Created

### Created:
1. `public/director/role_approvals.php` - Director approval interface
2. `app/Controllers/RoleChangeApprovalController.php` - Approval logic

### Modified:
1. `app/Controllers/EmployeeController.php` - Added role change detection
2. `public/admin/edit_employee.php` - Added role fields and validation
3. `public/director/director_dashboard.php` - Added role change stats and links
4. `public/admin/employees.php` - Added role_pending banner support

### Database:
1. Created `role_change_requests` table with 13 columns

---

## Architecture Alignment

The implementation follows the exact same pattern as the existing salary approval system:
- ✅ Request creation in controller when change detected
- ✅ Pending state prevents immediate application
- ✅ Director portal for review with approve/reject
- ✅ Email notifications on decision
- ✅ Audit trail with timestamps and user names
- ✅ Success messages and status badges
- ✅ Color-coded request cards for status visibility
- ✅ Modal dialogs for comments/reasons

This consistency makes the system intuitive for users already familiar with salary approvals.

---

## Success Criteria Met

✅ **Admin can initiate role changes** - edit_employee.php user role dropdown
✅ **Changes require director approval** - role_change_requests table tracks status
✅ **Director can view pending requests** - role_approvals.php portal
✅ **Director can approve with comments** - Modal dialog and database storage
✅ **Director can reject with reason** - Modal dialog and email notification
✅ **Role updates on approval** - users.role updated immediately
✅ **Email notifications sent** - NotificationHelper integration
✅ **Dashboard shows pending count** - Stat card and sidebar badge
✅ **Success messages displayed** - Banner on employees.php
✅ **Mirrors salary workflow** - Same architecture and patterns

---

**Implementation Status**: ✅ **COMPLETE AND FULLY FUNCTIONAL**

All components tested and verified with no syntax errors. System ready for production use.
