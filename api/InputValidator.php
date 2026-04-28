<?php
/**
 * Input Validator
 * Provides input validation and sanitization for API endpoints
 */

class InputValidator {
    /**
     * Sanitize string input
     * 
     * @param string $input
     * @param int $maxLength Maximum length
     * @return string
     */
    public static function sanitizeString($input, $maxLength = 255) {
        $input = trim($input);
        $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
        return substr($input, 0, $maxLength);
    }

    /**
     * Sanitize email
     * 
     * @param string $email
     * @return string|false
     */
    public static function sanitizeEmail($email) {
        $email = filter_var(trim($email), FILTER_SANITIZE_EMAIL);
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : false;
    }

    /**
     * Validate and sanitize username
     * 
     * @param string $username
     * @return string|false
     */
    public static function validateUsername($username) {
        $username = trim($username);
        
        // Username must be 3-50 characters, alphanumeric + underscore only
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) {
            return false;
        }
        
        return $username;
    }

    /**
     * Validate password strength
     * 
     * @param string $password
     * @param int $minLength
     * @return array ['valid' => bool, 'errors' => array]
     */
    public static function validatePassword($password, $minLength = 8) {
        $errors = [];
        
        if (strlen($password) < $minLength) {
            $errors[] = "Password must be at least $minLength characters";
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter';
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number';
        }
        
        if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            $errors[] = 'Password must contain at least one special character';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Validate action parameter
     * 
     * @param string $action
     * @param array $allowedActions
     * @return string|false
     */
    public static function validateAction($action, $allowedActions) {
        $action = self::sanitizeString($action, 50);
        
        if (!in_array($action, $allowedActions)) {
            return false;
        }
        
        return $action;
    }

    /**
     * Validate environment parameter
     * 
     * @param string $env
     * @return string|false
     */
    public static function validateEnvironment($env) {
        $allowed = ['prod', 'beta', 'dev', 'pim', 'dashboard', 'lms'];
        $env = self::sanitizeString($env, 20);
        
        if (!in_array($env, $allowed)) {
            return false;
        }
        
        return $env;
    }

    /**
     * Validate file path (prevent directory traversal)
     * 
     * @param string $path
     * @param string $basePath
     * @return string|false
     */
    public static function validateFilePath($path, $basePath) {
        // Remove any directory traversal attempts
        $path = basename($path);
        
        // Construct full path
        $fullPath = realpath($basePath . '/' . $path);
        
        // Check if path exists and is within base path
        if ($fullPath === false || strpos($fullPath, realpath($basePath)) !== 0) {
            return false;
        }
        
        return $fullPath;
    }

    /**
     * Validate category name
     * 
     * @param string $category
     * @return string|false
     */
    public static function validateCategory($category) {
        $category = self::sanitizeString($category, 50);
        
        // Only alphanumeric and underscore
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $category)) {
            return false;
        }
        
        return $category;
    }

    /**
     * Validate integer input
     * 
     * @param mixed $input
     * @param int $min
     * @param int $max
     * @return int|false
     */
    public static function validateInt($input, $min = 0, $max = PHP_INT_MAX) {
        if (!is_numeric($input)) {
            return false;
        }
        
        $int = (int)$input;
        
        if ($int < $min || $int > $max) {
            return false;
        }
        
        return $int;
    }

    /**
     * Validate limit parameter (for pagination)
     * 
     * @param mixed $limit
     * @return int
     */
    public static function validateLimit($limit) {
        $limit = self::validateInt($limit, 1, 1000);
        return $limit !== false ? $limit : 10;
    }

    /**
     * Validate script name
     * 
     * @param string $script
     * @return string|false
     */
    public static function validateScriptName($script) {
        $script = self::sanitizeString($script, 100);
        
        // Only allow alphanumeric, underscore, hyphen, and .php/.sh extensions
        if (!preg_match('/^[a-zA-Z0-9_\-]+\.(php|sh)$/', $script)) {
            return false;
        }
        
        return $script;
    }

    /**
     * Validate Cloudflare action
     * 
     * @param string $action
     * @return string|false
     */
    public static function validateCloudflareAction($action) {
        $allowed = [
            'purge_all', 'purge_url', 'purge_tag',
            'toggle_dev_mode', 'toggle_setting',
            'always_online', 'cache_level'
        ];
        
        $action = self::sanitizeString($action, 50);
        
        if (!in_array($action, $allowed)) {
            return false;
        }
        
        return $action;
    }

    /**
     * Validate URL
     * 
     * @param string $url
     * @return string|false
     */
    public static function validateUrl($url) {
        $url = trim($url);
        
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }
        
        // Only allow http/https
        $parsed = parse_url($url);
        if (!in_array($parsed['scheme'] ?? '', ['http', 'https'])) {
            return false;
        }
        
        return $url;
    }

    /**
     * Sanitize array of inputs
     * 
     * @param array $inputs
     * @param array $rules ['field' => 'rule']
     * @return array ['valid' => bool, 'data' => array, 'errors' => array]
     */
    public static function validateArray($inputs, $rules) {
        $data = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $inputs[$field] ?? null;
            
            // Required check
            if (strpos($rule, 'required') !== false && empty($value)) {
                $errors[$field] = "$field is required";
                continue;
            }
            
            if (empty($value)) {
                continue;
            }
            
            // Apply validation
            if (strpos($rule, 'email') !== false) {
                $validated = self::sanitizeEmail($value);
                if ($validated === false) {
                    $errors[$field] = "Invalid email format";
                    continue;
                }
                $data[$field] = $validated;
            } elseif (strpos($rule, 'int') !== false) {
                $parts = explode(':', $rule);
                $min = isset($parts[1]) ? (int)explode(',', $parts[1])[0] : 0;
                $max = isset($parts[1]) ? (int)explode(',', $parts[1])[1] : PHP_INT_MAX;
                $validated = self::validateInt($value, $min, $max);
                if ($validated === false) {
                    $errors[$field] = "Invalid integer value for $field";
                    continue;
                }
                $data[$field] = $validated;
            } else {
                $maxLength = 255;
                if (preg_match('/max:(\d+)/', $rule, $matches)) {
                    $maxLength = (int)$matches[1];
                }
                $data[$field] = self::sanitizeString($value, $maxLength);
            }
        }
        
        return [
            'valid' => empty($errors),
            'data' => $data,
            'errors' => $errors
        ];
    }
}
