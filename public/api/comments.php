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
require_once __DIR__ . '/../src/models/Comment.php';
require_once __DIR__ . '/../src/models/Image.php';
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
        case 'get_comments':
            $response = getComments($input);
            break;
            
        case 'add_comment':
            $response = addComment($input);
            break;
            
        case 'delete_comment':
            $response = deleteComment($input);
            break;
            
        case 'toggle_like':
            $response = toggleLike($input);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    error_log("Comments API Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

echo json_encode($response);

function getComments($input) {
    $imageId = intval($input['image_id'] ?? $_GET['image_id'] ?? 0);
    
    if ($imageId <= 0) {
        return ['success' => false, 'error' => 'Invalid image ID'];
    }
    
    $commentModel = new Comment();
    $imageModel = new Image();
    
    // Get image info
    $image = $imageModel->findById($imageId);
    if (!$image) {
        return ['success' => false, 'error' => 'Image not found'];
    }
    
    // Get comments
    $comments = $commentModel->findByImageId($imageId);
    
    return [
        'success' => true,
        'comments' => $comments,
        'image' => $image
    ];
}

function addComment($input) {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $imageId = intval($input['image_id'] ?? 0);
    $content = Validator::sanitizeString($input['content'] ?? '');
    
    if ($imageId <= 0) {
        return ['success' => false, 'error' => 'Invalid image ID'];
    }
    
    if (!Validator::validateComment($content)) {
        return ['success' => false, 'error' => 'Comment must be between 1 and 1000 characters'];
    }
    
    $user = Auth::getCurrentUser();
    $commentModel = new Comment();
    $imageModel = new Image();
    
    // Verify image exists
    $image = $imageModel->findById($imageId);
    if (!$image) {
        return ['success' => false, 'error' => 'Image not found'];
    }
    
    // Add comment
    if ($commentModel->create($imageId, $user['id'], $content)) {
        // Send notification email to image owner if enabled
        sendCommentNotification($image, $user);
        
        return [
            'success' => true,
            'message' => 'Comment added successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to add comment'];
}

function deleteComment($input) {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $commentId = intval($input['comment_id'] ?? 0);
    
    if ($commentId <= 0) {
        return ['success' => false, 'error' => 'Invalid comment ID'];
    }
    
    $user = Auth::getCurrentUser();
    $commentModel = new Comment();
    
    // Delete comment (only if user owns it)
    if ($commentModel->delete($commentId, $user['id'])) {
        return [
            'success' => true,
            'message' => 'Comment deleted successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to delete comment or permission denied'];
}

function toggleLike($input) {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $imageId = intval($input['image_id'] ?? 0);
    
    if ($imageId <= 0) {
        return ['success' => false, 'error' => 'Invalid image ID'];
    }
    
    $user = Auth::getCurrentUser();
    $commentModel = new Comment();
    $imageModel = new Image();
    
    // Verify image exists
    $image = $imageModel->findById($imageId);
    if (!$image) {
        return ['success' => false, 'error' => 'Image not found'];
    }
    
    // Users cannot like their own images
    if ($image['user_id'] == $user['id']) {
        return ['success' => false, 'error' => 'Cannot like your own image'];
    }
    
    // Toggle like
    $liked = $commentModel->toggleLike($imageId, $user['id']);
    $likesCount = $commentModel->getLikesCount($imageId);
    
    return [
        'success' => true,
        'liked' => $liked,
        'likes_count' => $likesCount
    ];
}

function sendCommentNotification($image, $commenter) {
    try {
        // Don't send notification if commenter is the image owner
        if ($image['user_id'] == $commenter['id']) {
            return;
        }
        
        $userModel = new User();
        $imageOwner = $userModel->findById($image['user_id']);
        
        // Check if user has notifications enabled
        if (!$imageOwner || !$imageOwner['notifications_enabled']) {
            return;
        }
        
        $emailService = new Email();
        $emailService->sendCommentNotification(
            $imageOwner['email'],
            $imageOwner['username'],
            $commenter['username'],
            $image['id']
        );
    } catch (Exception $e) {
        error_log("Failed to send comment notification: " . $e->getMessage());
    }
}
?>