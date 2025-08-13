<?php
require_once __DIR__ . '/../config/database.php';

class User {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }
    
    public function create($username, $email, $password) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $verification_token = bin2hex(random_bytes(32));
        
        $stmt = $this->db->prepare("
            INSERT INTO users (username, email, password_hash, verification_token) 
            VALUES (?, ?, ?, ?)
        ");
        
        return $stmt->execute([$username, $email, $password_hash, $verification_token]);
    }
    
    public function findByEmail($email) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch();
    }
    
    public function findByUsername($username) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        return $stmt->fetch();
    }
    
    public function findById($id) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
    
    public function verifyUser($token) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET is_verified = TRUE, verification_token = NULL 
            WHERE verification_token = ?
        ");
        return $stmt->execute([$token]);
    }
    
    public function setResetToken($email, $token, $expires) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET reset_token = ?, reset_expires = ? 
            WHERE email = ?
        ");
        return $stmt->execute([$token, $expires, $email]);
    }
    
    public function resetPassword($token, $new_password) {
        $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $this->db->prepare("
            UPDATE users 
            SET password_hash = ?, reset_token = NULL, reset_expires = NULL 
            WHERE reset_token = ? AND reset_expires > NOW()
        ");
        return $stmt->execute([$password_hash, $token]);
    }
    
    public function updateProfile($user_id, $username, $email, $notifications_enabled) {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET username = ?, email = ?, notifications_enabled = ? 
            WHERE id = ?
        ");
        return $stmt->execute([$username, $email, $notifications_enabled, $user_id]);
    }
}
?>