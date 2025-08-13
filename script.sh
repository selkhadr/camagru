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
public/images/uploads

echo "🐳 Starting Docker containers..."
cd camagru
docker-compose up -d --build
