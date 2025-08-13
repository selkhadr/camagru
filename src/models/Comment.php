<?php
require_once __DIR__ . '/../config/database.php';

class Comment {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($image_id, $user_id, $content) {
        $stmt = $this->db->prepare("
            INSERT INTO comments (image_id, user_id, content) 
            VALUES (?, ?, ?)
        ");
        return $stmt->execute([$image_id, $user_id, $content]);
    }
    
    public function findByImageId($image_id) {
        $stmt = $this->db->prepare("
            SELECT c.*, u.username 
            FROM comments c 
            JOIN users u ON c.user_id = u.id 
            WHERE c.image_id = ? 
            ORDER BY c.created_at ASC
        ");
        $stmt->execute([$image_id]);
        return $stmt->fetchAll();
    }
    
    public function delete($id, $user_id) {
        $stmt = $this->db->prepare("DELETE FROM comments WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $user_id]);
    }
    
    public function toggleLike($image_id, $user_id) {
        // Check if like exists
        $stmt = $this->db->prepare("SELECT id FROM likes WHERE image_id = ? AND user_id = ?");
        $stmt->execute([$image_id, $user_id]);
        $like = $stmt->fetch();
        
        if ($like) {
            // Unlike
            $stmt = $this->db->prepare("DELETE FROM likes WHERE image_id = ? AND user_id = ?");
            $stmt->execute([$image_id, $user_id]);
            return false;
        } else {
            // Like
            $stmt = $this->db->prepare("INSERT INTO likes (image_id, user_id) VALUES (?, ?)");
            $stmt->execute([$image_id, $user_id]);
            return true;
        }
    }
    
    public function getLikesCount($image_id) {
        $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM likes WHERE image_id = ?");
        $stmt->execute([$image_id]);
        return $stmt->fetch()['count'];
    }
    
    public function isLikedByUser($image_id, $user_id) {
        $stmt = $this->db->prepare("SELECT id FROM likes WHERE image_id = ? AND user_id = ?");
        $stmt->execute([$image_id, $user_id]);
        return $stmt->fetch() !== false;
    }
}
?>