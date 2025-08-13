<?php
session_start();

// Load environment variables
if (file_exists(__DIR__ . '/../../.env')) {
    $env = parse_ini_file(__DIR__ . '/../../.env');
    foreach ($env as $key => $value) {
        $_ENV[$key] = $value;
    }
}

require_once __DIR__ . '/../src/utils/Auth.php';
require_once __DIR__ . '/../src/utils/Validator.php';
require_once __DIR__ . '/../src/utils/Email.php';
require_once __DIR__ . '/../src/models/User.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$response = ['success' => false, 'error' => 'Invalid request'];

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $action = $input['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'register':
            $response = handleRegister($input);
            break;
            
        case 'login':
            $response = handleLogin($input);
            break;
            
        case 'logout':
            $response = handleLogout();
            break;
            
        case 'check':
            $response = checkAuthStatus();
            break;
            
        case 'verify':
            $response = verifyEmail($_GET['token'] ?? '');
            break;
            
        case 'forgot_password':
            $response = handleForgotPassword($input);
            break;
            
        case 'reset_password':
            $response = handleResetPassword($input);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    error_log("Auth API Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

echo json_encode($response);

function handleRegister($input) {
    $username = Validator::sanitizeString($input['username'] ?? '');
    $email = Validator::sanitizeString($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    // Validation
    if (!Validator::validateUsername($username)) {
        return ['success' => false, 'error' => 'Invalid username. Must be 3-20 characters, letters, numbers, and underscores only.'];
    }
    
    if (!Validator::validateEmail($email)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    
    if (!Validator::validatePassword($password)) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters with uppercase, lowercase, and number'];
    }
    
    $user = new User();
    
    // Check if username exists
    if ($user->findByUsername($username)) {
        return ['success' => false, 'error' => 'Username already taken'];
    }
    
    // Check if email exists
    if ($user->findByEmail($email)) {
        return ['success' => false, 'error' => 'Email already registered'];
    }
    
    // Create user
    if ($user->create($username, $email, $password)) {
        // Send verification email
        $userData = $user->findByEmail($email);
        $emailService = new Email();
        
        if ($userData && $emailService->sendVerificationEmail($email, $username, $userData['verification_token'])) {
            return ['success' => true, 'message' => 'Registration successful! Please check your email to verify your account.'];
        } else {
            return ['success' => true, 'message' => 'Registration successful! However, verification email could not be sent.'];
        }
    }
    
    return ['success' => false, 'error' => 'Registration failed'];
}

function handleLogin($input) {
    $email = Validator::sanitizeString($input['email'] ?? '');
    $password = $input['password'] ?? '';
    
    if (!Validator::validateEmail($email)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    
    if (empty($password)) {
        return ['success' => false, 'error' => 'Password is required'];
    }
    
    $result = Auth::login($email, $password);
    return $result;
}

function handleLogout() {
    return Auth::logout();
}

function checkAuthStatus() {
    if (Auth::isLoggedIn()) {
        return [
            'success' => true,
            'user' => Auth::getCurrentUser()
        ];
    }
    
    return ['success' => false, 'error' => 'Not authenticated'];
}

function verifyEmail($token) {
    if (empty($token)) {
        return ['success' => false, 'error' => 'Verification token is required'];
    }
    
    $user = new User();
    if ($user->verifyUser($token)) {
        return ['success' => true, 'message' => 'Email verified successfully!'];
    }
    
    return ['success' => false, 'error' => 'Invalid or expired verification token'];
}

function handleForgotPassword($input) {
    $email = Validator::sanitizeString($input['email'] ?? '');
    
    if (!Validator::validateEmail($email)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    
    $user = new User();
    $userData = $user->findByEmail($email);
    
    if (!$userData) {
        // Don't reveal if email exists or not for security
        return ['success' => true, 'message' => 'If the email exists, a reset link has been sent.'];
    }
    
    $resetToken = Auth::generateToken();
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    if ($user->setResetToken($email, $resetToken, $expires)) {
        $emailService = new Email();
        
        if ($emailService->sendPasswordResetEmail($email, $userData['username'], $resetToken)) {
            return ['success' => true, 'message' => 'Password reset link sent to your email'];
        }
    }
    
    return ['success' => false, 'error' => 'Failed to send reset email'];
}

function handleResetPassword($input) {
    $token = $input['token'] ?? '';
    $password = $input['password'] ?? '';
    
    if (empty($token)) {
        return ['success' => false, 'error' => 'Reset token is required'];
    }
    
    if (!Validator::validatePassword($password)) {
        return ['success' => false, 'error' => 'Password must be at least 8 characters with uppercase, lowercase, and number'];
    }
    
    $user = new User();
    if ($user->resetPassword($token, $password)) {
        return ['success' => true, 'message' => 'Password reset successfully'];
    }
    
    return ['success' => false, 'error' => 'Invalid or expired reset token'];
}
?>