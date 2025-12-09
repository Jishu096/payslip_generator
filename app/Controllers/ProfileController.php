<?php
require_once __DIR__ . "/../Config/database.php";

class ProfileController {

    public function requestUpdate() {

        if (!isset($_SESSION['employee_id'])) {
            die("Unauthorized.");
        }

        $employeeId = $_SESSION['employee_id'];

        $fields = [
            'phone', 'address', 'city', 'state', 'pincode',
            'emergency_contact_name', 'emergency_contact_relation', 'emergency_contact_phone'
        ];

        $db = new Database();
        $conn = $db->connect();

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {

                $newValue = trim($_POST[$field]);

                // Get old value
                $stmt = $conn->prepare("SELECT $field FROM employees WHERE employee_id = :id");
                $stmt->execute([":id" => $employeeId]);
                $oldValue = $stmt->fetchColumn();

                // Insert request
                $insert = $conn->prepare("
                    INSERT INTO profile_update_requests 
                    (employee_id, field_name, old_value, new_value)
                    VALUES (:eid, :field, :old, :new)
                ");
                $insert->execute([
                    ":eid" => $employeeId,
                    ":field" => $field,
                    ":old" => $oldValue,
                    ":new" => $newValue
                ]);
            }
        }

        echo "<script>alert('Profile update request sent! Awaiting approval.'); window.location.href='../../frontend/views/employee_profile.php';</script>";
    }
}
