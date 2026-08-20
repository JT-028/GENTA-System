# Cloudways Quick Start Script
# Run this script AFTER uploading files and setting up database on Cloudways
# This automates permission and folder setup

#!/bin/bash

echo "🚀 GENTA Cloudways Deployment Setup"
echo "======================================"
echo ""

# Get current directory
APP_DIR=$(pwd)

echo "📁 Current directory: $APP_DIR"
echo ""

# Create necessary directories
echo "📂 Creating required directories..."
mkdir -p tmp/cache/models
mkdir -p tmp/cache/persistent
mkdir -p tmp/cache/views
mkdir -p tmp/sessions
mkdir -p tmp/tests
mkdir -p logs
mkdir -p webroot/uploads/profile_images

echo "✅ Directories created"
echo ""

# Set permissions
echo "🔐 Setting correct permissions..."

# Get current user
CURRENT_USER=$(whoami)

# Set ownership (adjust www-data if your server uses different web server user)
echo "Setting ownership to $CURRENT_USER:www-data..."
chown -R $CURRENT_USER:www-data .

# Set folder permissions (755 = owner can write, others can read/execute)
echo "Setting folder permissions (755)..."
find . -type d -exec chmod 755 {} \;

# Set file permissions (644 = owner can write, others can read only)
echo "Setting file permissions (644)..."
find . -type f -exec chmod 644 {} \;

# Make writable directories (775 = owner and group can write)
echo "Setting writable directories (775)..."
chmod -R 775 tmp/
chmod -R 775 logs/
chmod -R 775 webroot/uploads/

echo "✅ Permissions set"
echo ""

# Check if config file exists
echo "🔍 Checking configuration files..."
if [ ! -f "config/app_local.php" ]; then
    echo "⚠️  WARNING: config/app_local.php not found!"
    echo "   Please create it from app_local.production.php"
    echo "   Command: cp config/app_local.production.php config/app_local.php"
    echo ""
else
    echo "✅ config/app_local.php exists"
fi

if [ ! -f ".env" ]; then
    echo "ℹ️  Note: .env file not found (optional)"
    echo "   You can create it from .env.example if needed"
    echo ""
else
    echo "✅ .env exists"
fi

# Clear cache
echo "🧹 Clearing cache..."
rm -rf tmp/cache/*
echo "✅ Cache cleared"
echo ""

# Display summary
echo "======================================"
echo "✅ Setup Complete!"
echo "======================================"
echo ""
echo "📋 Next Steps:"
echo "1. Verify config/app_local.php has correct database credentials"
echo "2. Set debug to false: 'debug' => false"
echo "3. Generate new security salt: php bin/cake.php security generate_salt"
echo "4. Import your database via Cloudways Database Manager"
echo "5. Set webroot to: /public_html/webroot in Cloudways dashboard"
echo "6. Test your application"
echo ""
echo "📖 Full guide: See CLOUDWAYS_DEPLOYMENT.md"
echo ""
