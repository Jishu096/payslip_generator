#!/bin/bash

# ============================================
# e-HRMS Complete Testing Script
# Tests all major functionality
# ============================================

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Database connection
DB_USER="root"
DB_NAME="payslip_generator"
DB_SOCKET="/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock"

echo ""
echo "╔════════════════════════════════════════════════════════╗"
echo "║         e-HRMS COMPREHENSIVE TESTING SUITE             ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# ============================================
# Function to run SQL query
# ============================================
run_query() {
    mysql -u $DB_USER --socket=$DB_SOCKET $DB_NAME -e "$1" 2>/dev/null
}

# ============================================
# TEST 1: Database Connection
# ============================================
echo -e "${BLUE}[TEST 1]${NC} Database Connection Test"
if mysql -u $DB_USER --socket=$DB_SOCKET $DB_NAME -e "SELECT 1;" &> /dev/null; then
    echo -e "${GREEN}✓ PASS${NC} - Database connected successfully"
else
    echo -e "${RED}✗ FAIL${NC} - Cannot connect to database"
    exit 1
fi
echo ""

# ============================================
# TEST 2: Required Tables Exist
# ============================================
echo -e "${BLUE}[TEST 2]${NC} Database Schema Test"
REQUIRED_TABLES=("users" "employees" "departments" "payroll" "payslips" "attendance" "leave_requests" "holidays" "roles")
MISSING_TABLES=0

for table in "${REQUIRED_TABLES[@]}"; do
    if run_query "SHOW TABLES LIKE '$table';" | grep -q "$table"; then
        echo -e "${GREEN}✓${NC} Table '$table' exists"
    else
        echo -e "${RED}✗${NC} Table '$table' is missing"
        MISSING_TABLES=$((MISSING_TABLES + 1))
    fi
done

if [ $MISSING_TABLES -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - All required tables exist"
else
    echo -e "${RED}✗ FAIL${NC} - $MISSING_TABLES table(s) missing"
fi
echo ""

# ============================================
# TEST 3: Test Users Setup
# ============================================
echo -e "${BLUE}[TEST 3]${NC} Test Users Verification"
USER_COUNT=$(run_query "SELECT COUNT(*) FROM users WHERE is_active = 1;" | tail -n 1)
echo "Total active users: $USER_COUNT"

echo ""
echo "Test accounts by role:"
run_query "SELECT role, username, email FROM users WHERE is_active = 1 ORDER BY FIELD(role, 'super_admin', 'administrator', 'hr_officer', 'director', 'accountant', 'auditor', 'employee');"

if [ "$USER_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Test users exist"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - No test users found. Run setup_test_data.sql first"
fi
echo ""

# ============================================
# TEST 4: RBAC System Test
# ============================================
echo -e "${BLUE}[TEST 4]${NC} RBAC System Test"
ROLE_COUNT=$(run_query "SELECT COUNT(*) FROM roles WHERE is_active = 1;" | tail -n 1)
echo "Active roles: $ROLE_COUNT"

if [ "$ROLE_COUNT" -ge 7 ]; then
    echo -e "${GREEN}✓ PASS${NC} - All 7 roles configured"
    run_query "SELECT role_name, display_name FROM roles WHERE is_active = 1 ORDER BY role_id;"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - Expected 7 roles, found $ROLE_COUNT"
fi
echo ""

# ============================================
# TEST 5: Multi-Role Users Test
# ============================================
echo -e "${BLUE}[TEST 5]${NC} Multi-Role Users Test"
MULTIROLE_COUNT=$(run_query "SELECT COUNT(DISTINCT user_id) FROM user_roles_new;" | tail -n 1)
if [ -n "$MULTIROLE_COUNT" ] && [ "$MULTIROLE_COUNT" -gt 0 ]; then
    echo "Users with multiple roles: $MULTIROLE_COUNT"
    run_query "SELECT u.username, GROUP_CONCAT(r.role_name) as roles FROM user_roles_new urn JOIN users u ON urn.user_id = u.user_id JOIN roles r ON urn.role_id = r.role_id GROUP BY u.username;"
    echo -e "${GREEN}✓ PASS${NC} - Multi-role system configured"
else
    echo -e "${YELLOW}⚠ INFO${NC} - No multi-role users configured"
fi
echo ""

# ============================================
# TEST 6: Employee Data Test
# ============================================
echo -e "${BLUE}[TEST 6]${NC} Employee Data Test"
EMP_COUNT=$(run_query "SELECT COUNT(*) FROM employees WHERE is_active = 1;" | tail -n 1)
echo "Total active employees: $EMP_COUNT"

if [ "$EMP_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Employee data exists"
    echo ""
    echo "Sample employees:"
    run_query "SELECT employee_id, CONCAT(first_name, ' ', last_name) as name, designation, employment_type FROM employees WHERE is_active = 1 LIMIT 5;"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - No employees found. Run setup_test_data.sql first"
fi
echo ""

# ============================================
# TEST 7: Department Data Test
# ============================================
echo -e "${BLUE}[TEST 7]${NC} Department Data Test"
DEPT_COUNT=$(run_query "SELECT COUNT(*) FROM departments;" | tail -n 1)
echo "Total departments: $DEPT_COUNT"

if [ "$DEPT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Department data exists"
    run_query "SELECT department_id, department_name FROM departments LIMIT 5;"
else
    echo -e "${YELLOW}⚠ WARNING${NC} - No departments found"
fi
echo ""

# ============================================
# TEST 8: Attendance Records Test
# ============================================
echo -e "${BLUE}[TEST 8]${NC} Attendance Records Test"
ATT_COUNT=$(run_query "SELECT COUNT(*) FROM attendance;" | tail -n 1)
echo "Total attendance records: $ATT_COUNT"

if [ "$ATT_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Attendance data exists"
    echo ""
    echo "Recent attendance (last 5 records):"
    run_query "SELECT a.date, CONCAT(e.first_name, ' ', e.last_name) as employee, a.status FROM attendance a JOIN employees e ON a.employee_id = e.employee_id ORDER BY a.date DESC LIMIT 5;"
else
    echo -e "${YELLOW}⚠ INFO${NC} - No attendance records found"
fi
echo ""

# ============================================
# TEST 9: Payroll Records Test
# ============================================
echo -e "${BLUE}[TEST 9]${NC} Payroll Records Test"
PAYROLL_COUNT=$(run_query "SELECT COUNT(*) FROM payroll;" | tail -n 1)
echo "Total payroll records: $PAYROLL_COUNT"

if [ "$PAYROLL_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Payroll data exists"
    echo ""
    echo "Recent payroll (last 3 records):"
    run_query "SELECT CONCAT(e.first_name, ' ', e.last_name) as employee, p.month, p.year, p.net_salary FROM payroll p JOIN employees e ON p.employee_id = e.employee_id ORDER BY p.year DESC, p.month DESC LIMIT 3;"
else
    echo -e "${YELLOW}⚠ INFO${NC} - No payroll records found"
fi
echo ""

# ============================================
# TEST 10: Holidays Test
# ============================================
echo -e "${BLUE}[TEST 10]${NC} Holidays Configuration Test"
HOLIDAY_COUNT=$(run_query "SELECT COUNT(*) FROM holidays WHERE is_active = 1;" | tail -n 1)
echo "Active holidays: $HOLIDAY_COUNT"

if [ "$HOLIDAY_COUNT" -gt 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - Holidays configured"
    echo ""
    echo "Upcoming holidays (2026):"
    run_query "SELECT holiday_date, holiday_name, holiday_type FROM holidays WHERE is_active = 1 AND YEAR(holiday_date) = 2026 ORDER BY holiday_date;"
else
    echo -e "${YELLOW}⚠ INFO${NC} - No holidays configured"
fi
echo ""

# ============================================
# TEST 11: File Structure Test
# ============================================
echo -e "${BLUE}[TEST 11]${NC} File Structure Test"
REQUIRED_DIRS=("public/admin" "public/accountant" "public/director" "public/employee" "public/auth" "app/Models" "app/Controllers" "app/Helpers")
MISSING_DIRS=0

for dir in "${REQUIRED_DIRS[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} Directory '$dir' exists"
    else
        echo -e "${RED}✗${NC} Directory '$dir' is missing"
        MISSING_DIRS=$((MISSING_DIRS + 1))
    fi
done

if [ $MISSING_DIRS -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - All required directories exist"
else
    echo -e "${RED}✗ FAIL${NC} - $MISSING_DIRS director(y|ies) missing"
fi
echo ""

# ============================================
# TEST 12: Critical Files Test
# ============================================
echo -e "${BLUE}[TEST 12]${NC} Critical Files Test"
REQUIRED_FILES=(
    "public/index.php"
    "public/auth/login.php"
    "app/Config/database.php"
    "app/Models/User.php"
    "app/Models/Employee.php"
    "app/Controllers/LoginController.php"
    "app/Helpers/RBACHelper.php"
)
MISSING_FILES=0

for file in "${REQUIRED_FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} File '$file' exists"
    else
        echo -e "${RED}✗${NC} File '$file' is missing"
        MISSING_FILES=$((MISSING_FILES + 1))
    fi
done

if [ $MISSING_FILES -eq 0 ]; then
    echo -e "${GREEN}✓ PASS${NC} - All critical files exist"
else
    echo -e "${RED}✗ FAIL${NC} - $MISSING_FILES file(s) missing"
fi
echo ""

# ============================================
# SUMMARY
# ============================================
echo ""
echo "╔════════════════════════════════════════════════════════╗"
echo "║                    TEST SUMMARY                        ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""
echo "📊 Database Statistics:"
run_query "SELECT 'Users' as Category, COUNT(*) as Count FROM users WHERE is_active = 1 UNION ALL SELECT 'Employees', COUNT(*) FROM employees WHERE is_active = 1 UNION ALL SELECT 'Departments', COUNT(*) FROM departments UNION ALL SELECT 'Attendance Records', COUNT(*) FROM attendance UNION ALL SELECT 'Payroll Records', COUNT(*) FROM payroll UNION ALL SELECT 'Holidays', COUNT(*) FROM holidays WHERE is_active = 1;"

echo ""
echo "🔑 Test Credentials (All passwords: test123):"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
run_query "SELECT username, role FROM users WHERE is_active = 1 ORDER BY FIELD(role, 'super_admin', 'administrator', 'hr_officer', 'director', 'accountant', 'auditor', 'employee');"

echo ""
echo "🌐 Login URL: http://localhost/payslip_generator/public/auth/login.php"
echo ""
echo -e "${GREEN}✅ TESTING COMPLETE!${NC}"
echo ""
echo "📝 Next Steps:"
echo "   1. Open browser and navigate to login page"
echo "   2. Test each role using the accounts above"
echo "   3. Refer to TESTING_CHECKLIST.md for detailed test cases"
echo ""
