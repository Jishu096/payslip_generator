<?php
require_once __DIR__ . "/../Config/database.php";

class Employee {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function insertEmployee($data) {

        // Validate all required fields
        $required = ['full_name', 'email', 'phone', 'designation', 'department_id', 'employment_type', 'basic_salary'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return "MISSING_FIELD_" . strtoupper($field);
            }
        }

        // 1️⃣ Check duplicate email
            $checkEmail = $this->conn->prepare("SELECT employee_id FROM employees WHERE email = :email LIMIT 1");
            $checkEmail->execute([":email" => $data['email']]);
            if ($checkEmail->fetch()) {
                return "EMAIL_EXISTS";
            }

        // 2️⃣ Check duplicate phone
            $checkPhone = $this->conn->prepare("SELECT employee_id FROM employees WHERE phone = :phone LIMIT 1");
            $checkPhone->execute([":phone" => $data['phone']]);
            if ($checkPhone->fetch()) {
                return "PHONE_EXISTS";
            }

        // 3️⃣ Optional: Check duplicate name
            $checkName = $this->conn->prepare("SELECT employee_id FROM employees WHERE full_name = :full_name LIMIT 1");
            $checkName->execute([":full_name" => $data['full_name']]);
            if ($checkName->fetch()) {
                return "NAME_EXISTS";
            }

        $sql = "INSERT INTO employees 
            (full_name, email, phone, designation, department_id, employment_type, basic_salary, address,
             city, state, pincode, emergency_contact_name, emergency_contact_phone, emergency_contact_relation,
             aadhaar_no, pan_no, bank_account_no, ifsc_code, experience_years, last_appraisal_date, remarks, join_date)
            VALUES (:full_name, :email, :phone, :designation, :department_id, :employment_type, :basic_salary, :address,
             :city, :state, :pincode, :emergency_contact_name, :emergency_contact_phone, :emergency_contact_relation,
             :aadhaar_no, :pan_no, :bank_account_no, :ifsc_code, :experience_years, :last_appraisal_date, :remarks, NOW())";

        $stmt = $this->conn->prepare($sql);

        // Fix: Default experience_years to 0 if blank or not set
        $exp_years = isset($data['experience_years']) && $data['experience_years'] !== '' ? $data['experience_years'] : 0;
        $last_appraisal_date = isset($data['last_appraisal_date']) && $data['last_appraisal_date'] !== '' ? $data['last_appraisal_date'] : null;

        // Required professional fields validation
        $required = ['full_name','email','phone','designation','department_id','employment_type','basic_salary'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || trim($data[$field]) === '') {
                return "MISSING_FIELD_" . strtoupper($field);
            }
        }

        $stmt->execute([
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':designation' => $data['designation'],
            ':department_id' => $data['department_id'],
            ':employment_type' => $data['employment_type'],
            ':basic_salary' => $data['basic_salary'],
            ':address' => $data['address'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state' => $data['state'] ?? null,
            ':pincode' => $data['pincode'] ?? null,
            ':emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ':emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
            ':aadhaar_no' => $data['aadhaar_no'] ?? null,
            ':pan_no' => $data['pan_no'] ?? null,
            ':bank_account_no' => $data['bank_account_no'] ?? null,
            ':ifsc_code' => $data['ifsc_code'] ?? null,
            ':experience_years' => $exp_years,
            ':last_appraisal_date' => $last_appraisal_date,
            ':remarks' => $data['remarks'] ?? null
        ]);

        return $this->conn->lastInsertId();   // ⭐ Return new employee_id
    }

    public function getAllEmployees() {
        $sql = "SELECT e.*, d.department_name 
                FROM employees e 
                LEFT JOIN departments d ON e.department_id = d.department_id";
        $stmt = $this->conn->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function deleteEmployeeById($id) {
        try {
            // Start transaction
            $this->conn->beginTransaction();
            
            // 1. Delete payslips first (references payroll and employee)
            $sql = "DELETE FROM payslips WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // 2. Delete payroll records
            $sql = "DELETE FROM payroll WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // 3. Delete attendance records (if table exists)
            $sql = "DELETE FROM attendance WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // 4. Delete role change requests (if table exists)
            $sql = "DELETE FROM role_change_requests WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // 5. Delete salary change requests (if table exists)
            $sql = "DELETE FROM salary_change_requests WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // 6. Get associated user_id (if any)
            $sql = "SELECT user_id FROM users WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $userId = $user['user_id'];
                
                // 7. Delete user_roles
                $sql = "DELETE FROM user_roles WHERE user_id = :user_id";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
                
                // 8. Delete user account
                $sql = "DELETE FROM users WHERE user_id = :user_id";
                $stmt = $this->conn->prepare($sql);
                $stmt->bindParam(':user_id', $userId);
                $stmt->execute();
            }
            
            // 9. Finally, delete the employee
            $sql = "DELETE FROM employees WHERE employee_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            
            // Commit transaction
            $this->conn->commit();
            return true;
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollBack();
            error_log("Error deleting employee: " . $e->getMessage());
            return false;
        }
    }

    public function updateEmployee($id, $data) {
        $sql = "UPDATE employees SET full_name=:full_name, email=:email, phone=:phone,
            designation=:designation, department_id=:department_id,
            employment_type=:employment_type, employee_type=:employee_type, basic_salary=:basic_salary,
            address=:address, city=:city, state=:state, pincode=:pincode,
            emergency_contact_name=:emergency_contact_name,
            emergency_contact_phone=:emergency_contact_phone,
            emergency_contact_relation=:emergency_contact_relation,
            aadhaar_no=:aadhaar_no, pan_no=:pan_no,
            bank_account_no=:bank_account_no, ifsc_code=:ifsc_code,
            experience_years=:experience_years, last_appraisal_date=:last_appraisal_date,
            remarks=:remarks,
            contract_end_date=:contract_end_date,
            resignation_date=:resignation_date,
            retirement_date=:retirement_date,
            internship_duration=:internship_duration
            WHERE employee_id=:id";

        $stmt = $this->conn->prepare($sql);

        // Handle empty date strings - convert to NULL
        $last_appraisal_date = isset($data['last_appraisal_date']) && $data['last_appraisal_date'] !== '' ? $data['last_appraisal_date'] : null;
        $experience_years = isset($data['experience_years']) && $data['experience_years'] !== '' ? $data['experience_years'] : null;
        $contract_end_date = !empty($data['contract_end_date']) ? $data['contract_end_date'] : null;
        $resignation_date = !empty($data['resignation_date']) ? $data['resignation_date'] : null;
        $retirement_date = !empty($data['retirement_date']) ? $data['retirement_date'] : null;
        $internship_duration = !empty($data['internship_duration']) ? $data['internship_duration'] : null;

        // Map employment_type to employee_type for report filtering
        $employee_type = 'regular'; // Default
        if ($data['employment_type'] === 'contract') {
            $employee_type = 'contract';
        } elseif ($data['employment_type'] === 'intern') {
            $employee_type = 'intern';
        } elseif ($data['employment_type'] === 'permanent') {
            $employee_type = 'regular';
        }

        return $stmt->execute([
            ':id' => $id,
            ':full_name' => $data['full_name'],
            ':email' => $data['email'],
            ':phone' => $data['phone'],
            ':designation' => $data['designation'],
            ':department_id' => $data['department_id'],
            ':employment_type' => $data['employment_type'],
            ':employee_type' => $employee_type,
            ':basic_salary' => $data['basic_salary'],
            ':address' => $data['address'] ?? null,
            ':city' => $data['city'] ?? null,
            ':state' => $data['state'] ?? null,
            ':pincode' => $data['pincode'] ?? null,
            ':emergency_contact_name' => $data['emergency_contact_name'] ?? null,
            ':emergency_contact_phone' => $data['emergency_contact_phone'] ?? null,
            ':emergency_contact_relation' => $data['emergency_contact_relation'] ?? null,
            ':aadhaar_no' => $data['aadhaar_no'] ?? null,
            ':pan_no' => $data['pan_no'] ?? null,
            ':bank_account_no' => $data['bank_account_no'] ?? null,
            ':ifsc_code' => $data['ifsc_code'] ?? null,
            ':experience_years' => $experience_years,
            ':last_appraisal_date' => $last_appraisal_date,
            ':remarks' => $data['remarks'] ?? null,
            ':contract_end_date' => $contract_end_date,
            ':resignation_date' => $resignation_date,
            ':retirement_date' => $retirement_date,
            ':internship_duration' => $internship_duration
        ]);
    }
    public function getEmployeeById($id) {
        $sql = "SELECT * FROM employees WHERE employee_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([":id" => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }


}
