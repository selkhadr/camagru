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
require_once __DIR__ . '/../src/utils/ImageProcessor.php';
require_once __DIR__ . '/../src/models/Image.php';

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
    // Handle both JSON and form data
    if ($_SERVER['CONTENT_TYPE'] && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false) {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
    } else {
        $input = array_merge($_GET, $_POST);
    }
    
    $action = $input['action'] ?? '';

    switch ($action) {
        case 'get_overlays':
            $response = getOverlays();
            break;
            
        case 'save_image':
            $response = saveImage($input);
            break;
            
        default:
            $response = ['success' => false, 'error' => 'Unknown action'];
    }
    
} catch (Exception $e) {
    error_log("Upload API Error: " . $e->getMessage());
    $response = ['success' => false, 'error' => 'Server error occurred'];
}

echo json_encode($response);

function getOverlays() {
    $imageProcessor = new ImageProcessor();
    $overlays = $imageProcessor->getAvailableOverlays();
    
    return [
        'success' => true,
        'overlays' => $overlays
    ];
}

function saveImage($input) {
    // Check authentication
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    $user = Auth::getCurrentUser();
    $imageData = $input['image_data'] ?? '';
    $overlayName = $input['overlay'] ?? '';
    $isWebcam = !empty($input['is_webcam']);
    
    if (empty($imageData)) {
        return ['success' => false, 'error' => 'No image data provided'];
    }
    
    if (empty($overlayName)) {
        return ['success' => false, 'error' => 'No overlay selected'];
    }
    
    try {
        $imageProcessor = new ImageProcessor();
        
        // Process the image
        if ($isWebcam) {
            $filename = $imageProcessor->processWebcamImage($imageData, $overlayName);
        } else {
            // For uploaded images, we need to handle the base64 data
            $filename = $imageProcessor->processWebcamImage($imageData, $overlayName);
        }
        
        // Save to database
        $imageModel = new Image();
        $originalFilename = 'webcam_capture_' . date('Y-m-d_H-i-s') . '.jpg';
        
        if ($imageModel->create($user['id'], $filename, $originalFilename, $overlayName)) {
            return [
                'success' => true,
                'message' => 'Image saved successfully',
                'filename' => $filename
            ];
        } else {
            // Clean up the file if database save failed
            $filepath = __DIR__ . '/../images/uploads/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            return ['success' => false, 'error' => 'Failed to save image to database'];
        }
        
    } catch (Exception $e) {
        error_log("Image processing error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function handleFileUpload($input) {
    // Check authentication
    if (!Auth::isLoggedIn()) {
        return ['success' => false, 'error' => 'Authentication required'];
    }
    
    if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error'];
    }
    
    $overlayName = $input['overlay'] ?? '';
    if (empty($overlayName)) {
        return ['success' => false, 'error' => 'No overlay selected'];
    }
    
    $user = Auth::getCurrentUser();
    
    try {
        $imageProcessor = new ImageProcessor();
        $filename = $imageProcessor->processUploadedImage($_FILES['image'], $overlayName);
        
        // Save to database
        $imageModel = new Image();
        $originalFilename = $_FILES['image']['name'];
        
        if ($imageModel->create($user['id'], $filename, $originalFilename, $overlayName)) {
            return [
                'success' => true,
                'message' => 'Image uploaded and processed successfully',
                'filename' => $filename
            ];
        } else {
            // Clean up the file if database save failed
            $filepath = __DIR__ . '/../images/uploads/' . $filename;
            if (file_exists($filepath)) {
                unlink($filepath);
            }
            return ['success' => false, 'error' => 'Failed to save image to database'];
        }
        
    } catch (Exception $e) {
        error_log("File upload error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>