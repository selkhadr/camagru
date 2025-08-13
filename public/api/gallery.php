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
require_once __DIR__ . '/../src/models/Image.php';
require_once __DIR__ . '/../src/models/Comment.php';

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
        case 'get_gallery':
            $response = getGallery($input);
            break;
            
        case 'get_user_gallery':
            $response = getUserGallery();
            break;
            
        case 'delete_image':
            $response = deleteImage($input);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    error_log("Gallery API Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

echo json_encode($response);

function getGallery($input) {
    $page = max(1, intval($input['page'] ?? $_GET['page'] ?? 1));
    $limit = max(1, min(20, intval($input['limit'] ?? $_GET['limit'] ?? 5)));
    $offset = ($page - 1) * $limit;
    
    $imageModel = new Image();
    $commentModel = new Comment();
    
    // Get images with metadata
    $images = $imageModel->findForGallery($limit, $offset);
    $total = $imageModel->getTotalCount();
    
    // Add like status for current user
    $currentUser = Auth::getCurrentUser();
    foreach ($images as &$image) {
        $image['user_liked'] = false;
        if ($currentUser) {
            $image['user_liked'] = $commentModel->isLikedByUser($image['id'], $currentUser['id']);
        }
    }
    
    return [
        'success' => true,
        'images' => $images,
        'total' => $total,
        'page' => $page,
        'limit' => $limit,
        'total_pages' => ceil($total / $limit)
    ];
}

function getUserGallery() {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $user = Auth::getCurrentUser();
    $imageModel = new Image();
    $commentModel = new Comment();
    
    // Get user's images
    $images = $imageModel->findByUserId($user['id'], 50); // Show more for user's own gallery
    
    // Add metadata
    foreach ($images as &$image) {
        $image['likes_count'] = $commentModel->getLikesCount($image['id']);
        $image['user_liked'] = $commentModel->isLikedByUser($image['id'], $user['id']);
        
        // Count comments
        $comments = $commentModel->findByImageId($image['id']);
        $image['comments_count'] = count($comments);
    }
    
    return [
        'success' => true,
        'images' => $images
    ];
}

function deleteImage($input) {
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $imageId = intval($input['image_id'] ?? 0);
    if ($imageId <= 0) {
        return ['success' => false, 'error' => 'Invalid image ID'];
    }
    
    $user = Auth::getCurrentUser();
    $imageModel = new Image();
    
    // Get image info to verify ownership
    $image = $imageModel->findById($imageId);
    if (!$image) {
        return ['success' => false, 'error' => 'Image not found'];
    }
    
    if ($image['user_id'] != $user['id']) {
        return ['success' => false, 'error' => 'Permission denied'];
    }
    
    // Delete from database (this will cascade to comments and likes)
    if ($imageModel->delete($imageId, $user['id'])) {
        // Delete physical file
        $filepath = __DIR__ . '/../images/uploads/' . $image['filename'];
        if (file_exists($filepath)) {
            unlink($filepath);
        }
        
        return [
            'success' => true,
            'message' => 'Image deleted successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to delete image'];
}
?>