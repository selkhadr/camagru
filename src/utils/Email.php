<?php
require_once __DIR__ . '/../config/mail.php';

class Email {
    private $config;
    
    public function __construct() {
        $this->config = MailConfig::getConfig();
    }
    
    public function sendVerificationEmail($email, $username, $token) {
        $subject = 'Verify Your Camagru Account';
        $verify_url = $_ENV['APP_URL'] . '/api/auth.php?action=verify&token=' . $token;
        
        $message = "
            <html>
            <head><title>Verify Your Account</title></head>
            <body>
                <h2>Welcome to Camagru, $username!</h2>
                <p>Please click the link below to verify your account:</p>
                <p><a href='$verify_url'>Verify Account</a></p>
                <p>If you didn't create this account, please ignore this email.</p>
            </body>
            </html>
        ";
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    public function sendPasswordResetEmail($email, $username, $token) {
        $subject = 'Reset Your Camagru Password';
        $reset_url = $_ENV['APP_URL'] . '/reset-password.html?token=' . $token;
        
        $message = "
            <html>
            <head><title>Reset Your Password</title></head>
            <body>
                <h2>Password Reset Request</h2>
                <p>Hi $username,</p>
                <p>You requested to reset your password. Click the link below:</p>
                <p><a href='$reset_url'>Reset Password</a></p>
                <p>This link will expire in 1 hour.</p>
                <p>If you didn't request this, please ignore this email.</p>
            </body>
            </html>
        ";
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    public function sendCommentNotification($email, $username, $commenter, $image_id) {
        $subject = 'New Comment on Your Photo';
        $image_url = $_ENV['APP_URL'] . '/#image-' . $image_id;
        
        $message = "
            <html>
            <head><title>New Comment</title></head>
            <body>
                <h2>New Comment on Your Photo</h2>
                <p>Hi $username,</p>
                <p>$commenter left a comment on your photo.</p>
                <p><a href='$image_url'>View Photo</a></p>
                <p>To disable these notifications, update your profile settings.</p>
            </body>
            </html>
        ";
        
        return $this->sendEmail($email, $subject, $message);
    }
    
    private function sendEmail($to, $subject, $message) {
        $headers = [
            'MIME-Version: 1.0',
            'Content-type: text/html; charset=UTF-8',
            'From: ' . $this->config['from_email'],
            'Reply-To: ' . $this->config['from_email'],
            'X-Mailer: PHP/' . phpversion()
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
}
?>