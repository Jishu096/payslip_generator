<?php

require_once __DIR__ . "/../Config/database.php";

class Payslip {

    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
    }

    // Fetch all payslips for employee
    public function getPayslipsByEmployee($employee_id) {
        $sql = "SELECT p.payslip_id, p.file_path, p.generated_at,
                        pr.month, pr.year
                        FROM payslips p
                        JOIN payroll pr ON p.payroll_id = pr.payroll_id
                        WHERE p.employee_id = :eid
                        ORDER BY pr.year DESC, pr.month DESC";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([":eid" => $employee_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
