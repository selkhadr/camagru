<?php
// src/utils/Security.php
// Comprehensive security utilities for Camagru

class Security {
    
    // CSRF Token Management
    public static function generateCSRFToken() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        $_SESSION['csrf_token_time'] = time();
        
        return $token;
    }
    
    public static function validateCSRFToken($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists and is not expired (30 minutes)
        if (!isset($_SESSION['csrf_token']) || 
            !isset($_SESSION['csrf_token_time']) ||
            (time() - $_SESSION['csrf_token_time']) > 1800) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Rate Limiting
    public static function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 300) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . hash('sha256', $identifier);
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window has passed
        if ((time() - $data['first_attempt']) > $timeWindow) {
            $_SESSION[$key] = ['count' => 1, 'first_attempt' => time()];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= $maxAttempts) {
            return false;
        }
        
        $_SESSION[$key]['count']++;
        return true;
    }
    
    // Input Sanitization
    public static function sanitizeInput($input, $type = 'string') {
        switch ($type) {
            case 'email':
                return filter_var($input, FILTER_SANITIZE_EMAIL);
            case 'int':
                return filter_var($input, FILTER_SANITIZE_NUMBER_INT);
            case 'float':
                return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            case 'url':
                return filter_var($input, FILTER_SANITIZE_URL);
            case 'string':
            default:
                return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
        }
    }
    
    // File Upload Security
    public static function validateUploadedFile($file, $allowedTypes = ['image/jpeg', 'image/png', 'image/gif']) {
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        // Check file size (5MB max)
        $maxSize = 5 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new Exception('File too large. Maximum size is 5MB.');
        }
        
        // Validate MIME type
        if (!in_array($file['type'], $allowedTypes)) {
            throw new Exception('Invalid file type. Only images are allowed.');
        }
        
        // Double-check with finfo
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('Invalid file type detected.');
        }
        
        // Check for malicious content in image
        $imageInfo = getimagesize($file['tmp_name']);
        if ($imageInfo === false) {
            throw new Exception('File is not a valid image.');
        }
        
        return true;
    }
    
    // SQL Injection Prevention Helper
    public static function preparePlaceholders($count) {
        return implode(',', array_fill(0, $count, '?'));
    }
    
    // Password Security
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3          // 3 threads
        ]);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // Session Security
    public static function secureSession() {
        // Prevent session fixation
        if (session_status() == PHP_SESSION_NONE) {
            // Secure session configuration
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
            ini_set('session.cookie_samesite', 'Strict');
            ini_set('session.use_strict_mode', 1);
            ini_set('session.gc_maxlifetime', 1800); // 30 minutes
            
            session_start();
            
            // Regenerate session ID periodically
            if (!isset($_SESSION['created'])) {
                $_SESSION['created'] = time();
            } else if (time() - $_SESSION['created'] > 1800) {
                session_regenerate_id(true);
                $_SESSION['created'] = time();
            }
        }
    }
    
    // Header Security
    public static function setSecurityHeaders() {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Content-Security-Policy: default-src \'self\'; img-src \'self\' data:; style-src \'self\' \'unsafe-inline\'; script-src \'self\';');
        
        // HSTS for HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }
    
    // Log Security Events
    public static function logSecurityEvent($event, $details = []) {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'details' => $details
        ];
        
        $logFile = __DIR__ . '/../logs/security.log';
        $logDir = dirname($logFile);
        
        if (!is_dir($logDir)) {
            mkdir($logDir, 0750, true);
        }
        
        error_log(json_encode($logEntry) . PHP_EOL, 3, $logFile);
    }
    
    // Detect suspicious activity
    public static function detectSuspiciousActivity($request) {
        $suspicious = false;
        $reasons = [];
        
        // Check for SQL injection patterns
        $sqlPatterns = [
            '/\b(union|select|insert|update|delete|drop|create|alter)\b/i',
            '/[\'";].*(\bor\b|\band\b)/i',
            '/\b(exec|execute|sp_|xp_)\b/i'
        ];
        
        foreach ($request as $key => $value) {
            if (is_string($value)) {
                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $suspicious = true;
                        $reasons[] = "SQL injection attempt in $key";
                    }
                }
            }
        }
        
        // Check for XSS patterns
        $xssPatterns = [
            '/<script[^>]*>.*<\/script>/i',
            '/javascript:/i',
            '/on\w+\s*=/i',
            '/<iframe[^>]*>/i'
        ];
        
        foreach ($request as $key => $value) {
            if (is_string($value)) {
                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        $suspicious = true;
                        $reasons[] = "XSS attempt in $key";
                    }
                }
            }
        }
        
        // Check for path traversal
        if (preg_match('/\.\.[\/\\\\]/', implode('', $request))) {
            $suspicious = true;
            $reasons[] = "Path traversal attempt";
        }
        
        if ($suspicious) {
            self::logSecurityEvent('suspicious_activity', ['reasons' => $reasons, 'request' => $request]);
        }
        
        return $suspicious;
    }
}

// src/utils/Encryption.php
// Data encryption utilities

class Encryption {
    private static $method = 'AES-256-CBC';
    
    public static function encrypt($data, $key = null) {
        $key = $key ?: $_ENV['APP_SECRET'];
        $key = hash('sha256', $key);
        
        $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(self::$method));
        $encrypted = openssl_encrypt($data, self::$method, $key, 0, $iv);
        
        return base64_encode($encrypted . '::' . $iv);
    }
    
    public static function decrypt($data, $key = null) {
        $key = $key ?: $_ENV['APP_SECRET'];
        $key = hash('sha256', $key);
        
        list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
        return openssl_decrypt($encrypted_data, self::$method, $key, 0, $iv);
    }
}

// Security middleware for API endpoints
class SecurityMiddleware {
    public static function apply() {
        // Start secure session
        Security::secureSession();
        
        // Set security headers
        Security::setSecurityHeaders();
        
        // Check for suspicious activity
        $request = array_merge($_GET, $_POST, $_COOKIE);
        if (Security::detectSuspiciousActivity($request)) {
            http_response_code(403);
            echo json_encode(['error' => 'Suspicious activity detected']);
            exit();
        }
        
        // Rate limiting for API endpoints
        $endpoint = $_SERVER['REQUEST_URI'] ?? '';
        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . ':' . $endpoint;
        
        if (!Security::checkRateLimit($identifier, 60, 300)) { // 60 requests per 5 minutes
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded']);
            exit();
        }
    }
}

// Example usage in API files:
/*
// At the beginning of each API file:
require_once __DIR__ . '/../src/utils/Security.php';

// Apply security middleware
SecurityMiddleware::apply();

// For forms that need CSRF protection:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!Security::validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit();
    }
}
*/