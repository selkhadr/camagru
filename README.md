# camagru
# 📸 Camagru - Complete Implementation Guide

## 🎯 Project Overview

Camagru is a full-stack web application that allows users to create, edit, and share photos with fun overlays. Built with pure PHP backend and vanilla JavaScript frontend, containerized with Docker.

## ✨ Core Features Implemented

### 🔐 Authentication System
- **User Registration** with email verification
- **Secure Login/Logout** with session management
- **Password Reset** via email
- **Profile Management** with notification preferences
- **CSRF Protection** on all forms
- **Rate Limiting** to prevent brute force attacks

### 📷 Photo Editing
- **Webcam Integration** using getUserMedia API
- **File Upload** fallback for devices without webcam
- **Overlay Selection** from predefined PNG images with alpha channels
- **Real-time Preview** of overlay on webcam feed
- **Server-side Image Processing** using PHP GD extension
- **Secure File Validation** and sanitization

### 🖼️ Gallery System
- **Public Gallery** with pagination (5 images per page)
- **User Gallery** showing personal creations
- **Like System** with real-time updates
- **Comment System** with moderation capabilities
- **Email Notifications** for comments (configurable)

### 🛡️ Security Features
- **SQL Injection Prevention** with prepared statements
- **XSS Protection** with input sanitization
- **File Upload Security** with multiple validation layers
- **Session Security** with proper configuration
- **Security Headers** (CSP, HSTS, X-Frame-Options, etc.)
- **Suspicious Activity Detection** and logging

## 🏗️ Architecture

### Backend (Pure PHP)
```
src/
├── config/
│   ├── database.php      # Database connection singleton
│   └── mail.php          # Email configuration
├── models/
│   ├── User.php          # User data model
│   ├── Image.php         # Image data model
│   └── Comment.php       # Comments and likes model
├── utils/
│   ├── Auth.php          # Authentication utilities
│   ├── ImageProcessor.php # Image processing with GD
│   ├── Validator.php     # Input validation
│   ├── Email.php         # Email sending utilities
│   ├── Security.php      # Security utilities
│   └── Encryption.php    # Data encryption
└── init.sql              # Database schema
```

### Frontend (Vanilla JavaScript)
```
public/
├── index.html            # Single-page application
├── css/style.css         # Responsive styles with glassmorphism
├── js/
│   ├── app.js           # Main application controller
│   ├── auth.js          # Authentication handling
│   ├── webcam.js        # Camera and image capture
│   ├── gallery.js       # Gallery and interactions
│   └── utils.js         # Utility functions
├── api/                 # PHP API endpoints
│   ├── auth.php         # Authentication API
│   ├── upload.php       # Image upload API
│   ├── gallery.php      # Gallery API
│   ├── comments.php     # Comments/likes API
│   └── profile.php      # Profile management API
└── images/
    ├── overlays/        # PNG overlay images
    └── uploads/         # User-generated images
```

## 🚀 Installation & Setup

### Prerequisites
- Docker & Docker Compose
- Git (optional)

### Quick Start
```bash
# 1. Clone or download the project
git clone <repository-url>
cd camagru

# 2. Configure environment
cp .env.example .env
# Edit .env with your email settings

# 3. Start the application
docker-compose up -d

# 4. Generate sample overlays
docker-compose exec web php /var/www/src/create_overlays.php

# 5. Access the application
# Main app: http://localhost:8080
# phpMyAdmin: http://localhost:8081 (root/rootpassword)
```

### Environment Configuration
```env
# Database
DB_HOST=db
DB_NAME=camagru
DB_USER=root
DB_PASS=rootpassword

# Email (Gmail example)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM=your-email@gmail.com

# Application
APP_URL=http://localhost:8080
APP_SECRET=your-secret-key-here
UPLOAD_MAX_SIZE=5242880
```

## 🎨 UI/UX Features

### Modern Design Elements
- **Glassmorphism** effects with backdrop-filter
- **Gradient Backgrounds** with smooth transitions  
- **Hover Animations** and micro-interactions
- **Responsive Grid** layouts for all screen sizes
- **Loading States** with animated spinners
- **Toast Notifications** for user feedback

### Accessibility
- **Semantic HTML** structure
- **Keyboard Navigation** support
- **Screen Reader** compatibility
- **High Contrast** color schemes
- **Focus Indicators** for interactive elements

## 🔒 Security Implementation

### Authentication Security
```php
// Password hashing with Argon2ID
public static function hashPassword($password) {
    return password_hash($password, PASSWORD_ARGON2ID, [
        'memory_cost' => 65536,
        'time_cost' => 4,
        'threads' => 3
    ]);
}

// CSRF token generation
public static function generateCSRFToken() {
    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $token;
    return $token;
}
```

### File Upload Security
```php
// Multi-layer validation
public static function validateUploadedFile($file) {
    // Check upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new Exception('Upload error');
    }
    
    // Validate MIME type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($file['type'], $allowedTypes)) {
        throw new Exception('Invalid file type');
    }
    
    // Double-check with finfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $realType = finfo_file($finfo, $file['tmp_name']);
    if (!in_array($realType, $allowedTypes)) {
        throw new Exception('File type mismatch');
    }
}
```

### SQL Injection Prevention
```php
// All database queries use prepared statements
$stmt = $this->db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();
```

## 📊 Database Schema

### Users Table
```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    reset_token VARCHAR(255),
    reset_expires DATETIME,
    notifications_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Images Table  
```sql
CREATE TABLE images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    overlay_used VARCHAR(100),
    likes_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

## 🔧 API Endpoints

### Authentication
- `POST /api/auth.php?action=register` - User registration
- `POST /api/auth.php?action=login` - User login
- `POST /api/auth.php?action=logout` - User logout
- `GET /api/auth.php?action=verify&token=xxx` - Email verification
- `POST /api/auth.php?action=forgot_password` - Password reset request
- `POST /api/auth.php?action=reset_password` - Password reset

### Images
- `GET /api/upload.php?action=get_overlays` - Get available overlays
- `POST /api/upload.php` - Upload and process image
- `GET /api/gallery.php?action=get_gallery` - Get public gallery
- `GET /api/gallery.php?action=get_user_gallery` - Get user's images
- `POST /api/gallery.php` - Delete user's image

### Social Features
- `GET /api/comments.php?action=get_comments&image_id=x` - Get comments
- `POST /api/comments.php` - Add/delete comment or toggle like
- `POST /api/profile.php` - Update user profile

## 🧪 Testing

### Unit Tests
```bash
# Install PHPUnit
composer install

# Run tests
./vendor/bin/phpunit tests/

# Generate coverage report
./vendor/bin/phpunit --coverage-html coverage/
```

### Test Categories
- **Authentication Tests** - Login, registration, validation
- **Image Processing Tests** - Overlay application, resizing
- **Security Tests** - Input validation, CSRF protection
- **API Tests** - Endpoint functionality and error handling

## 🚀 Production Deployment

### Security Checklist
- [ ] Update all default passwords
- [ ] Configure proper SMTP settings
- [ ] Set up SSL certificates
- [ ] Enable proper logging
- [ ] Configure database backups
- [ ] Set up monitoring alerts
- [ ] Review file permissions
- [ ] Enable security headers

### Production Docker
```bash
# Use production compose file
docker-compose -f docker-compose.prod.yml up -d

# Enable SSL with Let's Encrypt
docker run --rm -v $(pwd)/ssl:/etc/letsencrypt \
  certbot/certbot certonly --webroot \
  -w /var/www/html -d yourdomain.com
```

## 🎯 Features Implemented vs Requirements

✅ **Required Features**
- [x] User registration with email verification
- [x] Login/logout with secure session management  
- [x] Password reset via email
- [x] Webcam photo capture with getUserMedia
- [x] File upload fallback
- [x] Overlay selection and application
- [x] Server-side image processing with GD
- [x] Public gallery with pagination
- [x] Like and comment system
- [x] Email notifications for comments
- [x] Responsive design
- [x] Input validation and sanitization
- [x] No external JS frameworks (vanilla JS only)
- [x] Pure PHP backend (no frameworks)
- [x] Docker containerization

🎁 **Bonus Features**
- [x] Live preview of overlays on webcam
- [x] Progressive Web App (PWA) capabilities
- [x] Advanced security features
- [x] Modern UI with glassmorphism effects
- [x] Comprehensive error handling
- [x] Logging and monitoring
- [x] Rate limiting
- [x] CSRF protection
- [x] Service worker for offline functionality

## 📈 Performance Optimizations

### Frontend
- **Lazy Loading** for gallery images
- **Debounced Search** for better UX
- **Image Compression** before upload
- **Service Worker** caching
- **CSS Animations** with GPU acceleration

### Backend  
- **Database Indexing** on frequently queried columns
- **Prepared Statements** for all queries
- **Image Optimization** with quality settings
- **Session Optimization** with proper garbage collection
- **Efficient Pagination** queries

## 🔍 Monitoring & Logging

### Security Logs
```php
// Automatic logging of suspicious activities
Security::logSecurityEvent('failed_login', [
    'email' => $email,
    'ip' => $_SERVER['REMOTE_ADDR'],
    'attempts' => $attemptCount
]);
```

### Application Logs
- **Error Logging** with stack traces
- **Performance Monitoring** for slow queries
- **User Activity** tracking
- **System Health** checks

## 🎓 Learning Outcomes

This project demonstrates mastery of:

1. **Full-Stack Development** with separation of concerns
2. **Security Best Practices** for web applications  
3. **Modern Frontend Techniques** without frameworks
4. **Database Design** with proper relationships
5. **API Development** with RESTful principles
6. **Container Orchestration** with Docker
7. **Image Processing** with PHP GD
8. **Email Integration** with SMTP
9. **Responsive Design** principles
10. **Testing Methodologies** for web apps

## 🚀 Next Steps

Potential enhancements for future versions:

1. **Real-time Features** with WebSockets
2. **Mobile Apps** with React Native/Flutter  
3. **Advanced Filters** with Canvas/WebGL
4. **Social Features** (followers, feed algorithm)
5. **AI Integration** for automatic tagging
6. **CDN Integration** for global image delivery
7. **Microservices** architecture for scaling
8. **GraphQL API** for better data fetching

---

**Camagru** represents a complete, production-ready web application showcasing modern development practices, security consciousness, and user experience design. The codebase serves as an excellent foundation for learning full-stack development and can be extended with additional features as needed.