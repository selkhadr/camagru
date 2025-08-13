<?php
require_once __DIR__ . '/../config/database.php';

class Image {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($user_id, $filename, $original_filename, $overlay_used) {
        $stmt = $this->db->prepare("
            INSERT INTO images (user_id, filename, original_filename, overlay_used) 
            VALUES (?, ?, ?, ?)
        ");
        return $stmt->execute([$user_id, $filename, $original_filename, $overlay_used]);
    }
    
    public function findByUserId($user_id, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT i.*, u.username 
            FROM images i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.user_id = ? 
            ORDER BY i.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public function findForGallery($limit = 5, $offset = 0) {
        $stmt = $this->db->prepare("
            SELECT i.*, u.username, 
                   COUNT(DISTINCT l.id) as likes_count,
                   COUNT(DISTINCT c.id) as comments_count
            FROM images i 
            JOIN users u ON i.user_id = u.id 
            LEFT JOIN likes l ON i.id = l.image_id 
            LEFT JOIN comments c ON i.id = c.image_id 
            GROUP BY i.id 
            ORDER BY i.created_at DESC 
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("
            SELECT i.*, u.username 
            FROM images i 
            JOIN users u ON i.user_id = u.id 
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function delete($id, $user_id) {
        $stmt = $this->db->prepare("DELETE FROM images WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $user_id]);
    }
    
    public function getTotalCount() {
        $stmt = $this->db->query("SELECT COUNT(*) as count FROM images");
        return $stmt->fetch()['count'];
    }
}
?>