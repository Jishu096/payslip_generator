-- ============================================
-- Add Employee Code System (EMP001 format)
-- ============================================

USE payslip_generator;

-- Step 1: Add employee_code column
ALTER TABLE employees 
ADD COLUMN employee_code VARCHAR(20) UNIQUE NULL AFTER employee_id;

-- Step 2: Create function to generate next employee code
DELIMITER $$

CREATE FUNCTION get_next_employee_code()
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE next_num INT;
    DECLARE new_code VARCHAR(20);
    
    -- Get the highest numeric part from existing codes
    SELECT COALESCE(MAX(CAST(SUBSTRING(employee_code, 4) AS UNSIGNED)), 0) + 1
    INTO next_num
    FROM employees
    WHERE employee_code LIKE 'EMP%';
    
    -- Format as EMP001, EMP002, etc.
    SET new_code = CONCAT('EMP', LPAD(next_num, 3, '0'));
    
    RETURN new_code;
END$$

DELIMITER ;

-- Step 3: Create trigger to auto-generate employee code on insert
DELIMITER $$

CREATE TRIGGER before_employee_insert
BEFORE INSERT ON employees
FOR EACH ROW
BEGIN
    IF NEW.employee_code IS NULL THEN
        SET NEW.employee_code = get_next_employee_code();
    END IF;
END$$

DELIMITER ;

-- Step 4: Update existing employees with codes
SET @counter = 0;
UPDATE employees 
SET employee_code = CONCAT('EMP', LPAD(@counter := @counter + 1, 3, '0'))
WHERE employee_code IS NULL
ORDER BY employee_id;

-- Step 5: Make employee_code NOT NULL after initial population
ALTER TABLE employees 
MODIFY COLUMN employee_code VARCHAR(20) UNIQUE NOT NULL;

-- Verification
SELECT 'Employee Code System Installed!' AS status;
SELECT employee_id, employee_code, full_name FROM employees ORDER BY employee_id LIMIT 10;

SELECT '' AS '';
SELECT '✅ Employee codes generated successfully!' AS message;
SELECT 'Format: EMP001, EMP002, EMP003, etc.' AS format;
SELECT 'New employees will auto-generate codes' AS note;
