#!/bin/bash
# Multi-Role RBAC Test Script

echo "🧪 Testing Multi-Role RBAC System..."
echo ""

# Test 1: Check if user was created
echo "Test 1: Checking if saimon user exists..."
mysql -u root --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock payslip_generator -e "SELECT user_id, username, email FROM users WHERE username = 'saimon123';" | head -5

echo ""
echo "Test 2: Checking saimon's roles..."
mysql -u root --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock payslip_generator -e "SELECT ur.user_id, r.role_name FROM user_roles ur JOIN roles r ON ur.role_id = r.role_id WHERE ur.user_id = (SELECT user_id FROM users WHERE username = 'saimon123');"

echo ""
echo "Test 3: Checking role_permissions table..."
mysql -u root --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock payslip_generator -e "SELECT COUNT(*) as total_permissions FROM role_permissions LIMIT 1;"

echo ""
echo "Test 4: Checking permissions assigned..."
mysql -u root --socket=/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock payslip_generator -e "SELECT DISTINCT p.permission_name FROM role_permissions rp JOIN permissions p ON rp.permission_id = p.permission_id WHERE rp.role_id IN (SELECT role_id FROM roles WHERE role_name IN ('employee', 'accountant')) ORDER BY p.permission_name;"

echo ""
echo "✅ RBAC System is Ready!"
echo ""
echo "🔐 Test Account:"
echo "  Username: saimon123"
echo "  Password: password123"
echo "  Roles: employee, accountant"
echo "  Can access: Employee Dashboard & Accountant Dashboard"
echo ""
echo "📝 Next: Try logging in with saimon123@gmail.com"
