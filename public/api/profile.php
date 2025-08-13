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
        case 'get_profile':
            $response = getProfile();
            break;
            
        case 'update':
            $response = updateProfile($input);
            break;
            
        case 'change_password':
            $response = changePassword($input);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    error_log("Profile API Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

echo json_encode($response);

function getProfile() {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $user = Auth::getCurrentUser();
    $userModel = new User();
    $userData = $userModel->findById($user['id']);
    
    if ($userData) {
        // Remove sensitive data
        unset($userData['password_hash']);
        unset($userData['verification_token']);
        unset($userData['reset_token']);
        unset($userData['reset_expires']);
        
        return [
            'success' => true,
            'user' => $userData
        ];
    }
    
    return ['success' => false, 'error' => 'User not found'];
}

function updateProfile($input) {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $username = Validator::sanitizeString($input['username'] ?? '');
    $email = Validator::sanitizeString($input['email'] ?? '');
    $notificationsEnabled = !empty($input['notifications_enabled']);
    
    // Validation
    if (!Validator::validateUsername($username)) {
        return ['success' => false, 'error' => 'Invalid username. Must be 3-20 characters, letters, numbers, and underscores only.'];
    }
    
    if (!Validator::validateEmail($email)) {
        return ['success' => false, 'error' => 'Invalid email address'];
    }
    
    $currentUser = Auth::getCurrentUser();
    $userModel = new User();
    
    // Check if username is taken by another user
    $existingUser = $userModel->findByUsername($username);
    if ($existingUser && $existingUser['id'] != $currentUser['id']) {
        return ['success' => false, 'error' => 'Username already taken'];
    }
    
    // Check if email is taken by another user
    $existingUser = $userModel->findByEmail($email);
    if ($existingUser && $existingUser['id'] != $currentUser['id']) {
        return ['success' => false, 'error' => 'Email already registered'];
    }
    
    // Update profile
    if ($userModel->updateProfile($currentUser['id'], $username, $email, $notificationsEnabled)) {
        // Update session data
        $_SESSION['username'] = $username;
        $_SESSION['email'] = $email;
        
        return [
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => [
                'id' => $currentUser['id'],
                'username' => $username,
                'email' => $email,
                'notifications_enabled' => $notificationsEnabled
            ]
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to update profile'];
}

function changePassword($input) {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $currentPassword = $input['current_password'] ?? '';
    $newPassword = $input['new_password'] ?? '';
    $confirmPassword = $input['confirm_password'] ?? '';
    
    // Validation
    if (empty($currentPassword)) {
        return ['success' => false, 'error' => 'Current password is required'];
    }
    
    if (!Validator::validatePassword($newPassword)) {
        return ['success' => false, 'error' => 'New password must be at least 8 characters with uppercase, lowercase, and number'];
    }
    
    if ($newPassword !== $confirmPassword) {
        return ['success' => false, 'error' => 'New passwords do not match'];
    }
    
    $currentUser = Auth::getCurrentUser();
    $userModel = new User();
    $userData = $userModel->findById($currentUser['id']);
    
    // Verify current password
    if (!$userData || !password_verify($currentPassword, $userData['password_hash'])) {
        return ['success' => false, 'error' => 'Current password is incorrect'];
    }
    
    // Update password
    $newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $pdo = Database::getInstance()->getConnection();
    
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    if ($stmt->execute([$newPasswordHash, $currentUser['id']])) {
        return [
            'success' => true,
            'message' => 'Password changed successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to change password'];
}
?>