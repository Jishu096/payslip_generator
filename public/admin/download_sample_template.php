<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

// Set headers for CSV download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="employee_import_template.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// CSV Header - Required columns first, then optional
$headers = [
    'full_name',
    'email',
    'phone',
    'designation',
    'department_id',
    'employment_type',
    'basic_salary',
    'status',
    'address',
    'city',
    'state',
    'pincode',
    'emergency_contact_name',
    'emergency_contact_phone',
    'emergency_contact_relation',
    'aadhaar_no',
    'pan_no',
    'bank_account_no',
    'ifsc_code',
    'experience_years',
    'last_appraisal_date',
    'remarks'
];

fputcsv($output, $headers);

// Sample data rows
$sample1 = [
    'Rajesh Kumar',
    'rajesh.kumar@company.com',
    '9876543210',
    'Software Engineer',
    '1',
    'permanent',
    '45000',
    'active',
    '123 MG Road',
    'Bangalore',
    'Karnataka',
    '560001',
    'Priya Kumar',
    '9876543211',
    'Spouse',
    '123456789012',
    'ABCDE1234F',
    '1234567890123456',
    'SBIN0001234',
    '5',
    '2023-01-15',
    'Team Lead'
];

$sample2 = [
    'Priya Sharma',
    'priya.sharma@company.com',
    '9876543220',
    'HR Manager',
    '2',
    'permanent',
    '55000',
    'active',
    '456 Park Street',
    'Mumbai',
    'Maharashtra',
    '400001',
    'Amit Sharma',
    '9876543221',
    'Spouse',
    '234567890123',
    'BCDEF2345G',
    '2345678901234567',
    'HDFC0002345',
    '8',
    '2022-06-10',
    'Senior HR'
];

fputcsv($output, $sample1);
fputcsv($output, $sample2);

fclose($output);
exit;
?>
