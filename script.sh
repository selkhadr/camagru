#!/bin/bash

# Camagru Setup Script
# This script sets up the complete Camagru application

echo "🎯 Setting up Camagru Web Application..."

# Check if Docker and Docker Compose are installed
if ! command -v docker &> /dev/null; then
    echo "❌ Docker is not installed. Please install Docker first."
    exit 1
fi

if ! command -v docker-compose &> /dev/null; then
    echo "❌ Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create project directory structure
echo "📁 Creating project directory structure..."
mkdir -p camagru/{public/{css,js,images/{overlays,uploads}},src/{config,models,utils},public/api}

# Create necessary directories
mkdir -p camagru/public/images/uploads
mkdir -p camagru/public/images/overlays

# Create .gitkeep files
touch camagru/public/images/uploads/.gitkeep

# Copy or create .env file
if [ ! -f "camagru/.env" ]; then
    echo "⚙️ Creating environment configuration..."
    cat > camagru/.env << 'EOF'
# Database Configuration
DB_HOST=db
DB_NAME=camagru
DB_USER=root
DB_PASS=rootpassword

# Email Configuration (Update with your SMTP settings)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM=your-email@gmail.com

# Application Configuration
APP_URL=http://localhost:8080
APP_SECRET=camagru-secret-key-change-this-in-production
UPLOAD_MAX_SIZE=5242880
EOF
    echo "✅ Created .env file. Please update email settings before running."
fi

# Create .gitignore
cat > camagru/.gitignore << 'EOF'
.env
public/images/uploads/*
!public/images/uploads/.gitkeep
.DS_Store
*.log
node_modules/
vendor/
*.tmp
*.bak
EOF

# Create README.md
cat > camagru/README.md << 'EOF'
# 📸 Camagru - Photo Editor Web Application

A Instagram-like web application that allows users to create, edit and share photos with fun overlays.

## ✨ Features

- **User Authentication**: Register, login, email verification, password reset
- **Photo Capture**: Take photos using webcam or upload existing images
- **Photo Editing**: Apply transparent PNG overlays to photos
- **Gallery**: View public gallery with pagination
- **Social Features**: Like and comment on photos
- **Email Notifications**: Get notified when someone comments on your photos
- **Responsive Design**: Works on desktop and mobile devices

## 🛠️ Tech Stack

- **Backend**: Pure PHP 8.1 (no frameworks)
- **Frontend**: Vanilla JavaScript, HTML5, CSS3
- **Database**: MySQL 8.0
- **Server**: Apache with mod_rewrite
- **Containerization**: Docker & Docker Compose
- **Image Processing**: PHP GD extension

## 🚀 Quick Start

### Prerequisites
- Docker and Docker Compose installed
- Git (optional)

### Installation

1. **Clone or download the project**
```bash
git clone <repository-url>
cd camagru
```

2. **Configure environment**
```bash
cp .env.example .env
# Edit .env file with your email settings
```

3. **Start the application**
```bash
docker-compose up -d
```

4. **Generate sample overlays**
```bash
docker-compose exec web php /var/www/src/create_overlays.php
```

5. **Access the application**
- Main app: http://localhost:8080
- phpMyAdmin: http://localhost:8081

## 📱 Usage

1. **Register** a new account (email verification required)
2. **Login** to access photo editing features
3. **Create photos** using webcam or upload existing images
4. **Select overlays** and capture/edit your photos
5. **Browse gallery** to see all public photos
6. **Interact** with photos by liking and commenting

## 🔧 Development

### Project Structure
```
camagru/
├── docker-compose.yml          # Container orchestration
├── Dockerfile                  # Web server container
├── public/                     # Web root directory
│   ├── index.html             # Main application page
│   ├── css/style.css          # Application styles
│   ├── js/                    # JavaScript modules
│   ├── images/                # Static images and uploads
│   └── api/                   # PHP API endpoints
├── src/                       # PHP source code
│   ├── config/                # Configuration files
│   ├── models/                # Database models
│   ├── utils/                 # Utility classes
│   └── init.sql              # Database schema
└── .env                       # Environment variables
```

### API Endpoints

- `POST /api/auth.php` - Authentication (register, login, logout, etc.)
- `POST /api/upload.php` - Image upload and processing
- `GET /api/gallery.php` - Gallery and image management
- `POST /api/comments.php` - Comments and likes
- `POST /api/profile.php` - User profile management

### Database Schema

- `users` - User accounts and settings
- `images` - Uploaded/created images
- `comments` - Photo comments
- `likes` - Photo likes

## 🔒 Security Features

- Password hashing with bcrypt
- SQL injection protection with prepared statements
- XSS protection with input sanitization
- CSRF protection for forms
- File upload validation
- Email verification for new accounts
- Rate limiting considerations

## 🐳 Docker Services

- **web**: Apache + PHP 8.1 with extensions (GD, PDO)
- **db**: MySQL 8.0 with persistent data
- **phpmyadmin**: Database administration interface

## 📧 Email Configuration

Update the following in your `.env` file:

```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USERNAME=your-email@gmail.com
SMTP_PASSWORD=your-app-password
SMTP_FROM=your-email@gmail.com
```

For Gmail, you'll need to:
1. Enable 2-factor authentication
2. Generate an "App Password"
3. Use the app password in SMTP_PASSWORD

## 🚀 Deployment

For production deployment:

1. **Update environment variables**
2. **Configure proper SMTP settings**
3. **Set up SSL/TLS certificates**
4. **Configure proper file permissions**
5. **Set up database backups**
6. **Configure monitoring and logging**

## 📝 License

This project is part of the 42 School curriculum.

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## 📞 Support

For issues and questions, please check the documentation or create an issue in the repository.
EOF

echo "📖 Created README.md with comprehensive documentation"

# Create a simple offline page
cat > camagru/public/offline.html << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camagru - Offline</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .offline-content {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        .offline-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        h1 {
            margin-bottom: 1rem;
        }
        p {
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        .retry-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 25px;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        .retry-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
    </style>
</head>
<body>
    <div class="offline-content">
        <div class="offline-icon">📡</div>
        <h1>You're Offline</h1>
        <p>It looks like you've lost your internet connection.<br>
        Please check your connection and try again.</p>
        <button class="retry-btn" onclick="window.location.reload()">
            Try Again
        </button>
    </div>
</body>
</html>
EOF

# Create web app manifest for PWA
cat > camagru/public/manifest.json << 'EOF'
{
  "name": "Camagru Photo Editor",
  "short_name": "Camagru",
  "description": "Create and share photos with fun overlays",
  "start_url": "/",
  "display": "standalone",
  "orientation": "portrait-primary",
  "theme_color": "#667eea",
  "background_color": "#667eea",
  "icons": [
    {
      "src": "images/icon-192x192.png",
      "sizes": "192x192",
      "type": "image/png"
    },
    {
      "src": "images/icon-512x512.png",
      "sizes": "512x512",
      "type": "image/png"
    }
  ]
}
EOF

# Create Apache configuration
cat > camagru/apache.conf << 'EOF'
<VirtualHost *:80>
    ServerName localhost
    DocumentRoot /var/www/html
    
    <Directory /var/www/html>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
        
        # Security headers
        Header always set X-Content-Type-Options nosniff
        Header always set X-Frame-Options DENY
        Header always set X-XSS-Protection "1; mode=block"
        Header always set Referrer-Policy "strict-origin-when-cross-origin"
    </Directory>
    
    # PHP configuration
    php_value post_max_size 10M
    php_value upload_max_filesize 5M
    php_value memory_limit 256M
    php_value max_execution_time 30
    
    # Log configuration
    ErrorLog ${APACHE_LOG_DIR}/camagru_error.log
    CustomLog ${APACHE_LOG_DIR}/camagru_access.log combined
    
    # Gzip compression
    <Location />
        SetOutputFilter DEFLATE
        SetEnvIfNoCase Request_URI \\.(?:gif|jpe?g|png)$ no-gzip dont-vary
        SetEnvIfNoCase Request_URI \\.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
    </Location>
</VirtualHost>
EOF

echo "🔧 Created Apache configuration"

# Create icon placeholders (these should be replaced with actual icons)
create_placeholder_icon() {
    local size=$1
    local output=$2
    
    convert -size ${size}x${size} xc:'#667eea' \
            -font Arial -pointsize $((size/8)) -fill white \
            -gravity center -annotate 0 "📸" \
            "$output" 2>/dev/null || echo "Warning: ImageMagick not available for icon creation"
}

# Try to create placeholder icons (requires ImageMagick)
if command -v convert &> /dev/null; then
    echo "🖼️  Creating placeholder icons..."
    create_placeholder_icon 192 "camagru/public/images/icon-192x192.png"
    create_placeholder_icon 512 "camagru/public/images/icon-512x512.png"
else
    echo "⚠️  ImageMagick not found. You'll need to create icon files manually:"
    echo "   - public/images/icon-192x192.png (192x192 pixels)"
    echo "   - public/images/icon-512x512.png (512x512 pixels)"
fi

# Make the overlay creation script executable
chmod +x camagru/create_overlays.php 2>/dev/null || true

echo ""
echo "🎉 Camagru setup completed successfully!"
echo ""
echo "📋 Next steps:"
echo "1. Edit camagru/.env with your email settings"
echo "2. Run: cd camagru && docker-compose up -d"
echo "3. Run: docker-compose exec web php /var/www/src/create_overlays.php"
echo "4. Visit: http://localhost:8080"
echo ""
echo "🔧 Additional services:"
echo "- phpMyAdmin: http://localhost:8081 (root/rootpassword)"
echo ""
echo "📖 See README.md for detailed documentation"