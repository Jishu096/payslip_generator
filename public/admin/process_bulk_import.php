<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['employee_file'])) {
    header("Location: bulk_import_employees.php?error=" . urlencode("No file uploaded"));
    exit;
}

$file = $_FILES['employee_file'];
$fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

// Validate file type
if (!in_array($fileExt, ['csv', 'xls', 'xlsx'])) {
    header("Location: bulk_import_employees.php?error=" . urlencode("Invalid file type. Only CSV and Excel files are allowed"));
    exit;
}

// Process CSV file
$importedCount = 0;
$errors = [];

try {
    if ($fileExt === 'csv') {
        $handle = fopen($file['tmp_name'], 'r');
        
        // Read header row
        $header = fgetcsv($handle);
        
        // Expected columns
        $expectedColumns = ['full_name', 'email', 'phone', 'designation', 'department_id', 'employment_type', 'basic_salary'];
        
        // Validate header
        $headerLower = array_map('strtolower', array_map('trim', $header));
        foreach ($expectedColumns as $col) {
            if (!in_array($col, $headerLower)) {
                fclose($handle);
                header("Location: bulk_import_employees.php?error=" . urlencode("Missing required column: $col"));
                exit;
            }
        }
        
        $employeeModel = new Employee();
        $rowNumber = 1;
        
        // Process each row
        while (($row = fgetcsv($handle)) !== false) {
            $rowNumber++;
            
            // Create associative array from row
            $data = array_combine($headerLower, $row);
            
            // Validate required fields
            $missingFields = [];
            foreach ($expectedColumns as $col) {
                if (empty($data[$col])) {
                    $missingFields[] = $col;
                }
            }
            
            if (!empty($missingFields)) {
                $errors[] = "Row $rowNumber: Missing fields - " . implode(', ', $missingFields);
                continue;
            }
            
            // Prepare employee data
            $employeeData = [
                'full_name' => trim($data['full_name']),
                'email' => trim($data['email']),
                'phone' => trim($data['phone']),
                'designation' => trim($data['designation']),
                'department_id' => intval($data['department_id']),
                'employment_type' => trim($data['employment_type']),
                'basic_salary' => floatval($data['basic_salary']),
                'status' => isset($data['status']) ? trim($data['status']) : 'active',
                'address' => isset($data['address']) ? trim($data['address']) : '',
                'city' => isset($data['city']) ? trim($data['city']) : '',
                'state' => isset($data['state']) ? trim($data['state']) : '',
                'pincode' => isset($data['pincode']) ? trim($data['pincode']) : '',
                'emergency_contact_name' => isset($data['emergency_contact_name']) ? trim($data['emergency_contact_name']) : '',
                'emergency_contact_phone' => isset($data['emergency_contact_phone']) ? trim($data['emergency_contact_phone']) : '',
                'emergency_contact_relation' => isset($data['emergency_contact_relation']) ? trim($data['emergency_contact_relation']) : '',
                'aadhaar_no' => isset($data['aadhaar_no']) ? trim($data['aadhaar_no']) : '',
                'pan_no' => isset($data['pan_no']) ? trim($data['pan_no']) : '',
                'bank_account_no' => isset($data['bank_account_no']) ? trim($data['bank_account_no']) : '',
                'ifsc_code' => isset($data['ifsc_code']) ? trim($data['ifsc_code']) : '',
                'experience_years' => isset($data['experience_years']) ? floatval($data['experience_years']) : 0,
                'last_appraisal_date' => isset($data['last_appraisal_date']) && !empty($data['last_appraisal_date']) ? $data['last_appraisal_date'] : null,
                'remarks' => isset($data['remarks']) ? trim($data['remarks']) : ''
            ];
            
            // Insert employee
            $result = $employeeModel->insertEmployee($employeeData);
            
            if (is_string($result)) {
                // Error occurred
                $errors[] = "Row $rowNumber ({$employeeData['email']}): " . $result;
            } else {
                $importedCount++;
            }
        }
        
        fclose($handle);
    }
    
    // Success redirect
    if ($importedCount > 0) {
        $message = "success=1&imported=$importedCount";
        if (!empty($errors)) {
            $message .= "&warnings=" . urlencode(count($errors) . " row(s) had errors");
        }
        header("Location: bulk_import_employees.php?$message");
    } else {
        header("Location: bulk_import_employees.php?error=" . urlencode("No employees were imported. Please check the file format."));
    }
    
} catch (Exception $e) {
    header("Location: bulk_import_employees.php?error=" . urlencode("Import failed: " . $e->getMessage()));
}
?>
