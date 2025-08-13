<?php
require_once __DIR__ . '/../models/User.php';

class Auth {
    public static function login($email, $password) {
        $user = new User();
        $userData = $user->findByEmail($email);
        
        if ($userData && password_verify($password, $userData['password_hash'])) {
            if (!$userData['is_verified']) {
                return ['success' => false, 'error' => 'Please verify your email first'];
            }
            
            session_start();
            $_SESSION['user_id'] = $userData['id'];
            $_SESSION['username'] = $userData['username'];
            $_SESSION['email'] = $userData['email'];
            
            return ['success' => true, 'user' => $userData];
        }
        
        return ['success' => false, 'error' => 'Invalid credentials'];
    }
    
    public static function logout() {
        session_start();
        session_destroy();
        return ['success' => true];
    }
    
    public static function isLoggedIn() {
        session_start();
        return isset($_SESSION['user_id']);
    }
    
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email']
        ];
    }
    
    public static function generateToken() {
        return bin2hex(random_bytes(32));
    }
}
?>