<?php
/**
 * Input Validator Helper
 * 
 * Centralized input validation and sanitization to prevent
 * XSS, SQL injection, and other security vulnerabilities.
 * 
 * @package App\Helpers
 * @version 1.0
 * @created January 10, 2026
 */

class InputValidator {
    
    /**
     * Sanitize string input (prevent XSS)
     * 
     * @param string $input The input to sanitize
     * @param bool $allowHTML Whether to allow HTML tags
     * @return string Sanitized string
     */
    public static function sanitizeString(string $input, bool $allowHTML = false): string {
        // Trim whitespace
        $input = trim($input);
        
        if ($allowHTML) {
            // Allow only safe HTML tags
            $allowedTags = '<p><br><strong><em><u><a><ul><ol><li>';
            return strip_tags($input, $allowedTags);
        }
        
        // Remove all HTML tags and encode special characters
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Validate and sanitize email
     * 
     * @param string $email The email to validate
     * @return string|false Sanitized email or false if invalid
     */
    public static function validateEmail(string $email) {
        $email = trim($email);
        $email = filter_var($email, FILTER_SANITIZE_EMAIL);
        
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        
        return false;
    }
    
    /**
     * Validate and sanitize integer
     * 
     * @param mixed $value The value to validate
     * @param int|null $min Minimum value (optional)
     * @param int|null $max Maximum value (optional)
     * @return int|false Integer value or false if invalid
     */
    public static function validateInteger($value, ?int $min = null, ?int $max = null) {
        $value = filter_var($value, FILTER_VALIDATE_INT);
        
        if ($value === false) {
            return false;
        }
        
        // Check min/max bounds
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return $value;
    }
    
    /**
     * Validate and sanitize float/decimal
     * 
     * @param mixed $value The value to validate
     * @param float|null $min Minimum value (optional)
     * @param float|null $max Maximum value (optional)
     * @return float|false Float value or false if invalid
     */
    public static function validateFloat($value, ?float $min = null, ?float $max = null) {
        $value = filter_var($value, FILTER_VALIDATE_FLOAT);
        
        if ($value === false) {
            return false;
        }
        
        // Check min/max bounds
        if ($min !== null && $value < $min) {
            return false;
        }
        
        if ($max !== null && $value > $max) {
            return false;
        }
        
        return $value;
    }
    
    /**
     * Validate phone number
     * 
     * @param string $phone The phone number to validate
     * @return string|false Sanitized phone or false if invalid
     */
    public static function validatePhone(string $phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Check if phone number is valid length (10-15 digits)
        if (strlen($phone) >= 10 && strlen($phone) <= 15) {
            return $phone;
        }
        
        return false;
    }
    
    /**
     * Validate URL
     * 
     * @param string $url The URL to validate
     * @return string|false Sanitized URL or false if invalid
     */
    public static function validateURL(string $url) {
        $url = trim($url);
        $url = filter_var($url, FILTER_SANITIZE_URL);
        
        if (filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        
        return false;
    }
    
    /**
     * Validate date format
     * 
     * @param string $date The date to validate
     * @param string $format Expected date format (default: Y-m-d)
     * @return string|false Date string or false if invalid
     */
    public static function validateDate(string $date, string $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        
        if ($d && $d->format($format) === $date) {
            return $date;
        }
        
        return false;
    }
    
    /**
     * Validate username (alphanumeric, underscore, hyphen)
     * 
     * @param string $username The username to validate
     * @param int $minLength Minimum length (default: 3)
     * @param int $maxLength Maximum length (default: 50)
     * @return string|false Username or false if invalid
     */
    public static function validateUsername(string $username, int $minLength = 3, int $maxLength = 50) {
        $username = trim($username);
        
        // Check length
        if (strlen($username) < $minLength || strlen($username) > $maxLength) {
            return false;
        }
        
        // Check format (alphanumeric, underscore, hyphen only)
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            return false;
        }
        
        return $username;
    }
    
    /**
     * Validate password strength
     * 
     * @param string $password The password to validate
     * @param int $minLength Minimum length (default: 8)
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePassword(string $password, int $minLength = 8): array {
        $errors = [];
        
        // Check length
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least {$minLength} characters long";
        }
        
        // Check for uppercase letter
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        // Check for lowercase letter
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        // Check for number
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        // Check for special character
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Validate file upload
     * 
     * @param array $file The $_FILES array element
     * @param array $allowedTypes Allowed MIME types
     * @param int $maxSize Maximum file size in bytes
     * @return array ['valid' => bool, 'error' => string|null]
     */
    public static function validateFileUpload(array $file, array $allowedTypes, int $maxSize): array {
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'error' => 'No file uploaded'];
        }
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return ['valid' => false, 'error' => 'File upload error: ' . $file['error']];
        }
        
        // Check file size
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1048576, 2);
            return ['valid' => false, 'error' => "File size exceeds maximum of {$maxSizeMB}MB"];
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            return ['valid' => false, 'error' => 'Invalid file type'];
        }
        
        return ['valid' => true, 'error' => null];
    }
    
    /**
     * Sanitize filename
     * 
     * @param string $filename The filename to sanitize
     * @return string Sanitized filename
     */
    public static function sanitizeFilename(string $filename): string {
        // Remove path information
        $filename = basename($filename);
        
        // Remove special characters
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove multiple underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        return $filename;
    }
    
    /**
     * Validate and sanitize array of values
     * 
     * @param array $data The data to validate
     * @param array $rules Validation rules ['field' => 'type']
     * @return array ['valid' => bool, 'data' => array, 'errors' => array]
     */
    public static function validateArray(array $data, array $rules): array {
        $sanitized = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }
            
            $value = $data[$field];
            
            switch ($rule) {
                case 'string':
                    $sanitized[$field] = self::sanitizeString($value);
                    break;
                    
                case 'email':
                    $result = self::validateEmail($value);
                    if ($result === false) {
                        $errors[$field] = "Invalid email format";
                    } else {
                        $sanitized[$field] = $result;
                    }
                    break;
                    
                case 'integer':
                    $result = self::validateInteger($value);
                    if ($result === false) {
                        $errors[$field] = "Invalid integer value";
                    } else {
                        $sanitized[$field] = $result;
                    }
                    break;
                    
                case 'float':
                    $result = self::validateFloat($value);
                    if ($result === false) {
                        $errors[$field] = "Invalid numeric value";
                    } else {
                        $sanitized[$field] = $result;
                    }
                    break;
                    
                case 'phone':
                    $result = self::validatePhone($value);
                    if ($result === false) {
                        $errors[$field] = "Invalid phone number";
                    } else {
                        $sanitized[$field] = $result;
                    }
                    break;
                    
                case 'url':
                    $result = self::validateURL($value);
                    if ($result === false) {
                        $errors[$field] = "Invalid URL";
                    } else {
                        $sanitized[$field] = $result;
                    }
                    break;
                    
                default:
                    $sanitized[$field] = self::sanitizeString($value);
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $sanitized,
            'errors' => $errors
        ];
    }
    
    /**
     * Prevent SQL injection by escaping special characters
     * Note: Use prepared statements instead when possible
     * 
     * @param string $value The value to escape
     * @param mysqli $connection Database connection
     * @return string Escaped value
     */
    public static function escapeSQLString(string $value, mysqli $connection): string {
        return $connection->real_escape_string($value);
    }
}
