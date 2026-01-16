<?php
/**
 * SalaryConfig Model
 * Manages salary configuration for Contractual and Intern employees
 * - DA rates per employee category
 * - Tour DA rates
 * - Monthly enable/disable settings
 */

class SalaryConfig {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../Config/database.php';
        $dbObj = new Database();
        $this->db = $dbObj->connect();
    }
    
    /**
     * Get salary configuration for a specific month and year
     * @param int $month (1-12)
     * @param int $year (YYYY)
     * @return array|false Configuration data or false if not found
     */
    public function getConfigByMonth($month, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM salary_config 
                WHERE month = ? AND year = ?
            ");
            $stmt->execute([$month, $year]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SalaryConfig::getConfigByMonth - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all configurations for a year
     * @param int $year (YYYY)
     * @return array Array of configurations
     */
    public function getConfigsByYear($year) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM salary_config 
                WHERE year = ? 
                ORDER BY month ASC
            ");
            $stmt->execute([$year]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SalaryConfig::getConfigsByYear - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Update or insert salary configuration for a month
     * @param array $data Configuration data
     * @return bool Success status
     */
    public function upsertConfig($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO salary_config 
                (month, year, da_rate_contractual, da_rate_intern, 
                 tour_da_rate_contractual, tour_da_rate_intern,
                 office_da_rate_contractual, office_da_rate_intern,
                 da_enabled, updated_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    da_rate_contractual = VALUES(da_rate_contractual),
                    da_rate_intern = VALUES(da_rate_intern),
                    tour_da_rate_contractual = VALUES(tour_da_rate_contractual),
                    tour_da_rate_intern = VALUES(tour_da_rate_intern),
                    office_da_rate_contractual = VALUES(office_da_rate_contractual),
                    office_da_rate_intern = VALUES(office_da_rate_intern),
                    da_enabled = VALUES(da_enabled),
                    updated_by = VALUES(updated_by),
                    notes = VALUES(notes),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            return $stmt->execute([
                $data['month'],
                $data['year'],
                $data['da_rate_contractual'],
                $data['da_rate_intern'],
                $data['tour_da_rate_contractual'],
                $data['tour_da_rate_intern'],
                $data['office_da_rate_contractual'],
                $data['office_da_rate_intern'],
                $data['da_enabled'],
                $data['updated_by'],
                $data['notes'] ?? ''
            ]);
        } catch (PDOException $e) {
            error_log("SalaryConfig::upsertConfig - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Toggle DA enabled/disabled for a month
     * @param int $month
     * @param int $year
     * @param int $enabled (0 or 1)
     * @param int $userId
     * @return bool Success status
     */
    public function toggleDA($month, $year, $enabled, $userId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE salary_config 
                SET da_enabled = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP
                WHERE month = ? AND year = ?
            ");
            return $stmt->execute([$enabled, $userId, $month, $year]);
        } catch (PDOException $e) {
            error_log("SalaryConfig::toggleDA - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get DA rate for a specific employee type and month
     * @param string $employeeType (contractual, intern)
     * @param int $month
     * @param int $year
     * @param string $daType (regular, tour, office)
     * @return float|false DA rate or false if not found
     */
    public function getDARate($employeeType, $month, $year, $daType = 'regular') {
        try {
            $config = $this->getConfigByMonth($month, $year);
            
            if (!$config || $config['da_enabled'] == 0) {
                return 0.00; // DA disabled for this month
            }
            
            // Determine which rate to return
            $rateField = '';
            if ($daType === 'tour') {
                $rateField = $employeeType === 'contractual' ? 'tour_da_rate_contractual' : 'tour_da_rate_intern';
            } elseif ($daType === 'office') {
                $rateField = $employeeType === 'contractual' ? 'office_da_rate_contractual' : 'office_da_rate_intern';
            } else {
                $rateField = $employeeType === 'contractual' ? 'da_rate_contractual' : 'da_rate_intern';
            }
            
            return isset($config[$rateField]) ? (float)$config[$rateField] : 0.00;
        } catch (Exception $e) {
            error_log("SalaryConfig::getDARate - " . $e->getMessage());
            return 0.00;
        }
    }
    
    /**
     * Check if DA is enabled for a month
     * @param int $month
     * @param int $year
     * @return bool
     */
    public function isDAEnabled($month, $year) {
        try {
            $config = $this->getConfigByMonth($month, $year);
            return $config && $config['da_enabled'] == 1;
        } catch (Exception $e) {
            error_log("SalaryConfig::isDAEnabled - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Create default configuration for all months of a year
     * @param int $year
     * @param int $userId
     * @return bool Success status
     */
    public function createDefaultConfigsForYear($year, $userId) {
        try {
            $this->db->beginTransaction();
            
            // Default DA rates (can be adjusted)
            $defaults = [
                'da_rate_contractual' => 300.00,
                'da_rate_intern' => 200.00,
                'tour_da_rate_contractual' => 500.00,
                'tour_da_rate_intern' => 300.00,
                'office_da_rate_contractual' => 300.00,
                'office_da_rate_intern' => 200.00,
                'da_enabled' => 1,
                'updated_by' => $userId,
                'notes' => "Default DA rates for year $year"
            ];
            
            for ($month = 1; $month <= 12; $month++) {
                $defaults['month'] = $month;
                $defaults['year'] = $year;
                
                if (!$this->upsertConfig($defaults)) {
                    $this->db->rollBack();
                    return false;
                }
            }
            
            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("SalaryConfig::createDefaultConfigsForYear - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get audit trail of configuration changes
     * @param int $limit Number of records to fetch
     * @return array Configuration history
     */
    public function getConfigHistory($limit = 50) {
        try {
            $stmt = $this->db->prepare("
                SELECT sc.*, u.username as updated_by_name
                FROM salary_config sc
                LEFT JOIN users u ON sc.updated_by = u.user_id
                ORDER BY sc.updated_at DESC
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("SalaryConfig::getConfigHistory - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Validate configuration data
     * @param array $data Configuration data to validate
     * @return array Validation result [success => bool, errors => array]
     */
    public function validateConfig($data) {
        $errors = [];
        
        // Month validation
        if (!isset($data['month']) || $data['month'] < 1 || $data['month'] > 12) {
            $errors[] = "Invalid month. Must be between 1 and 12.";
        }
        
        // Year validation
        if (!isset($data['year']) || $data['year'] < 2020 || $data['year'] > 2100) {
            $errors[] = "Invalid year. Must be between 2020 and 2100.";
        }
        
        // DA rate validations
        $rateFields = [
            'da_rate_contractual', 'da_rate_intern',
            'tour_da_rate_contractual', 'tour_da_rate_intern',
            'office_da_rate_contractual', 'office_da_rate_intern'
        ];
        
        foreach ($rateFields as $field) {
            if (isset($data[$field])) {
                $value = (float)$data[$field];
                if ($value < 0 || $value > 100000) {
                    $errors[] = "$field must be between 0 and 100000.";
                }
            }
        }
        
        // DA enabled validation
        if (isset($data['da_enabled']) && !in_array($data['da_enabled'], [0, 1, '0', '1'])) {
            $errors[] = "da_enabled must be 0 or 1.";
        }
        
        return [
            'success' => empty($errors),
            'errors' => $errors
        ];
    }
}
