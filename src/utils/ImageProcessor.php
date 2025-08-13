<?php
class ImageProcessor {
    private $upload_dir;
    private $overlays_dir;
    private $max_size;
    
    public function __construct() {
        $this->upload_dir = __DIR__ . '/../../public/images/uploads/';
        $this->overlays_dir = __DIR__ . '/../../public/images/overlays/';
        $this->max_size = $_ENV['UPLOAD_MAX_SIZE'] ?? 5242880; // 5MB default
        
        // Create upload directory if it doesn't exist
        if (!is_dir($this->upload_dir)) {
            mkdir($this->upload_dir, 0755, true);
        }
    }
    
    public function processWebcamImage($base64_data, $overlay_name) {
        // Remove data URL prefix
        $image_data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64_data));
        
        if (!$image_data) {
            throw new Exception('Invalid image data');
        }
        
        // Create image from string
        $image = imagecreatefromstring($image_data);
        if (!$image) {
            throw new Exception('Could not create image from data');
        }
        
        // Apply overlay
        $final_image = $this->applyOverlay($image, $overlay_name);
        
        // Save image
        $filename = $this->generateFilename('jpg');
        $filepath = $this->upload_dir . $filename;
        
        if (!imagejpeg($final_image, $filepath, 90)) {
            throw new Exception('Could not save image');
        }
        
        // Clean up memory
        imagedestroy($image);
        imagedestroy($final_image);
        
        return $filename;
    }
    
    public function processUploadedImage($file, $overlay_name) {
        // Validate file
        $this->validateUploadedFile($file);
        
        // Create image from uploaded file
        $image = $this->createImageFromFile($file['tmp_name'], $file['type']);
        
        // Resize if necessary
        $image = $this->resizeImage($image, 800, 600);
        
        // Apply overlay
        $final_image = $this->applyOverlay($image, $overlay_name);
        
        // Save image
        $filename = $this->generateFilename('jpg');
        $filepath = $this->upload_dir . $filename;
        
        if (!imagejpeg($final_image, $filepath, 90)) {
            throw new Exception('Could not save image');
        }
        
        // Clean up memory
        imagedestroy($image);
        imagedestroy($final_image);
        
        return $filename;
    }
    
    private function validateUploadedFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error');
        }
        
        if ($file['size'] > $this->max_size) {
            throw new Exception('File too large');
        }
        
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($file['type'], $allowed_types)) {
            throw new Exception('Invalid file type');
        }
        
        // Additional security check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            throw new Exception('Invalid file type');
        }
    }
    
    private function createImageFromFile($filepath, $mime_type) {
        switch ($mime_type) {
            case 'image/jpeg':
                return imagecreatefromjpeg($filepath);
            case 'image/png':
                return imagecreatefrompng($filepath);
            case 'image/gif':
                return imagecreatefromgif($filepath);
            default:
                throw new Exception('Unsupported image type');
        }
    }
    
    private function resizeImage($image, $max_width, $max_height) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        if ($width <= $max_width && $height <= $max_height) {
            return $image;
        }
        
        $ratio = min($max_width / $width, $max_height / $height);
        $new_width = intval($width * $ratio);
        $new_height = intval($height * $ratio);
        
        $new_image = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($new_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        
        return $new_image;
    }
    
    private function applyOverlay($base_image, $overlay_name) {
        $overlay_path = $this->overlays_dir . $overlay_name;
        
        if (!file_exists($overlay_path)) {
            throw new Exception('Overlay not found');
        }
        
        $overlay = imagecreatefrompng($overlay_path);
        if (!$overlay) {
            throw new Exception('Could not load overlay');
        }
        
        // Enable alpha blending
        imagealphablending($base_image, true);
        imagesavealpha($base_image, true);
        
        // Get dimensions
        $base_width = imagesx($base_image);
        $base_height = imagesy($base_image);
        $overlay_width = imagesx($overlay);
        $overlay_height = imagesy($overlay);
        
        // Calculate position (center the overlay)
        $x = ($base_width - $overlay_width) / 2;
        $y = ($base_height - $overlay_height) / 2;
        
        // Apply overlay
        imagecopy($base_image, $overlay, $x, $y, 0, 0, $overlay_width, $overlay_height);
        
        // Clean up overlay
        imagedestroy($overlay);
        
        return $base_image;
    }
    
    private function generateFilename($extension) {
        return uniqid('img_', true) . '.' . $extension;
    }
    
    public function getAvailableOverlays() {
        $overlays = [];
        $files = glob($this->overlays_dir . '*.png');
        
        foreach ($files as $file) {
            $overlays[] = basename($file);
        }
        
        return $overlays;
    }
}
?>