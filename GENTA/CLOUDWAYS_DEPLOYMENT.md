# GENTA - Cloudways Deployment Guide

## Prerequisites
- Cloudways account (Sign up at https://cloudways.com)
- Git repository (GitHub recommended)
- Access to your local database (phpMyAdmin in XAMPP)

---

## Step 1: Prepare Your Local Environment

### 1.1 Export Your Database
1. Open XAMPP Control Panel and start MySQL
2. Open phpMyAdmin (http://localhost/phpmyadmin)
3. Select your GENTA database
4. Click "Export" tab
5. Choose "Quick" export method
6. Format: SQL
7. Click "Go" to download the `.sql` file
8. **Save this file** - you'll need it later

### 1.2 Update .gitignore
Make sure your `.gitignore` includes:
```
/config/app_local.php
/.env
/tmp/*
/logs/*
/vendor/*
/webroot/uploads/profile_images/*
!webroot/uploads/profile_images/.gitkeep
```

### 1.3 Create Required Folders (if not exist)
Create these empty placeholder files to preserve folder structure in Git:
```
webroot/uploads/profile_images/.gitkeep
tmp/.gitkeep
logs/.gitkeep
```

---

## Step 2: Set Up Cloudways Server

### 2.1 Create New Server
1. Log in to Cloudways dashboard
2. Click "Add Server"
3. Choose:
   - **Cloud Provider**: DigitalOcean (recommended for beginners)
   - **Server Size**: Start with $12/month plan (1GB RAM)
   - **Location**: Choose closest to your users
   - **Application**: PHP Stack (select PHP 8.1 or 8.2)
   - **Server Name**: "GENTA-Production"
   - **Project Name**: "GENTA"

4. Click "Launch Now" (takes 5-10 minutes)

### 2.2 Configure Application
After server is ready:
1. Go to "Applications" tab
2. Click on your application
3. Note down these details:
   - **Application URL**
   - **Database Name**
   - **Database Username**
   - **Database Password**
   - **MySQL Host** (usually localhost)
   - **MySQL Port** (usually 3306)

---

## Step 3: Deploy Your Code

### Option A: Deploy via Git (Recommended)

#### 3.1 Push to GitHub
```bash
cd C:\xampp\htdocs\GENTA
git add .
git commit -m "Prepare for production deployment"
git push origin main
```

#### 3.2 Set Up Git Deployment in Cloudways
1. In Cloudways dashboard, go to your application
2. Click "Deployment via Git"
3. Enter your GitHub repository URL
4. Branch: `main`
5. Deployment path: Leave as default (usually `/public_html`)
6. Click "Start Deployment"

### Option B: Deploy via SFTP (Alternative)

1. In Cloudways, go to "Server Management" → "Master Credentials"
2. Note down:
   - SFTP/SSH Host
   - SFTP/SSH Username
   - SFTP/SSH Password (or use SSH key)
3. Use FileZilla or WinSCP to connect
4. Upload all files from `C:\xampp\htdocs\GENTA` to `/public_html/`
5. **Do NOT upload**: `config/app_local.php`, `.env`, `/tmp/*`, `/logs/*`

---

## Step 4: Configure Production Environment

### 4.1 Connect via SSH
1. In Cloudways dashboard, go to "Server Management" → "Master Credentials"
2. Download your SSH private key (or use password)
3. Use PuTTY (Windows) or Terminal to connect:
   ```
   ssh [username]@[server-ip]
   ```

### 4.2 Navigate to Application
```bash
cd applications/[your-app-name]/public_html
```

### 4.3 Create Production Configuration
```bash
# Copy the production template
cp config/app_local.production.php config/app_local.php

# Create .env file
cp .env.example .env

# Edit the files with your credentials
nano config/app_local.php
# or
nano .env
```

### 4.4 Update Configuration Values
Edit `config/app_local.php` or `.env`:

```php
// In app_local.php:
'debug' => false,  // MUST be false in production
'Security' => [
    'salt' => 'YOUR_GENERATED_SALT_HERE',  // Generate new one
],
'Datasources' => [
    'default' => [
        'host' => 'localhost',
        'username' => 'your_cloudways_db_username',
        'password' => 'your_cloudways_db_password',
        'database' => 'your_cloudways_db_name',
    ],
],
```

**Generate a new Security Salt:**
```bash
php bin/cake.php security generate_salt
```

---

## Step 5: Import Database

### 5.1 Via Cloudways Database Manager
1. In Cloudways dashboard, go to your application
2. Click "Database Management"
3. Click "Launch Database Manager" (opens phpMyAdmin)
4. Select your database
5. Click "Import" tab
6. Choose your `.sql` file from Step 1.1
7. Click "Go"

### 5.2 Via Command Line (Alternative)
```bash
# Upload your SQL file via SFTP to /tmp/ folder first
mysql -u [db_username] -p [db_name] < /tmp/your_database.sql
```

---

## Step 6: Set Correct Permissions

```bash
# Navigate to your app directory
cd /home/[username]/applications/[app-name]/public_html

# Set ownership
chown -R [username]:www-data .

# Set folder permissions
find . -type d -exec chmod 755 {} \;

# Set file permissions
find . -type f -exec chmod 644 {} \;

# Make writable directories
chmod -R 775 tmp/
chmod -R 775 logs/
chmod -R 775 webroot/uploads/

# Ensure cache directories exist
mkdir -p tmp/cache/models
mkdir -p tmp/cache/persistent
mkdir -p tmp/cache/views
mkdir -p tmp/sessions
mkdir -p tmp/tests

chmod -R 775 tmp/
```

---

## Step 7: Configure Web Server

### 7.1 Set Document Root
1. In Cloudways dashboard, go to your application
2. Click "Application Settings"
3. Find "Webroot" setting
4. Change from `/public_html` to `/public_html/webroot`
5. Click "Save Changes"

### 7.2 Configure .htaccess (if needed)
Ensure `webroot/.htaccess` exists with:
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
```

---

## Step 8: Test Your Deployment

1. Open your Cloudways application URL in a browser
2. Test login functionality
3. Test file uploads (profile pictures)
4. Check that tours/walkthrough works
5. Verify database operations (CRUD for students, questions)

### Common Issues:

**White screen / 500 error:**
- Check `logs/error.log`
- Ensure `debug` is `false` in `app_local.php`
- Verify database credentials
- Check folder permissions

**Can't upload files:**
- Check `webroot/uploads/` permissions (should be 775)
- Verify ownership: `chown -R [username]:www-data webroot/uploads/`

**CSS/JS not loading:**
- Verify webroot is set correctly to `/public_html/webroot`
- Clear browser cache
- Check Cloudways "Varnish" cache settings

---

## Step 9: Enable SSL (HTTPS)

1. In Cloudways dashboard, go to your application
2. Click "SSL Certificate"
3. If you have a custom domain:
   - Add your domain in "Domain Management" first
   - Then generate Let's Encrypt SSL certificate (free)
4. If using Cloudways subdomain:
   - SSL is automatically enabled

---

## Step 10: Ongoing Maintenance

### Deploy Updates (Git Method)
```bash
# On your local machine, after making changes:
git add .
git commit -m "Your update message"
git push origin main

# In Cloudways dashboard:
# Go to "Deployment via Git" and click "Pull" to update the server
```

### Clear Cache (if needed)
```bash
cd /home/[username]/applications/[app-name]/public_html
rm -rf tmp/cache/*
php bin/cake.php cache clear_all
```

### Backup Your Database
Cloudways provides automatic backups, but you can also:
1. Go to "Backup and Restore"
2. Create manual backup
3. Download via SFTP from `/backup/` folder

---

## Security Checklist

- [ ] `debug` is set to `false` in production
- [ ] Generated new unique `Security.salt`
- [ ] Database credentials are secured and not in Git
- [ ] SSL certificate is active (HTTPS)
- [ ] File permissions are correct (not 777)
- [ ] `app_local.php` and `.env` are not in Git repository
- [ ] Cloudways firewall is enabled
- [ ] Regular backups are configured

---

## Troubleshooting

### View Error Logs
```bash
# Via SSH
tail -f /home/[username]/applications/[app-name]/public_html/logs/error.log
```

### Check PHP Version
```bash
php -v
```

### Restart Web Server
In Cloudways dashboard:
1. Go to "Server Management"
2. Click "Services"
3. Restart Apache/Nginx and PHP-FPM

---

## Support Resources
- Cloudways Support: https://support.cloudways.com/
- CakePHP Documentation: https://book.cakephp.org/
- GENTA Application: Check your project README for app-specific details

---

**Deployment completed!** 🎉

Your GENTA application should now be live and accessible via your Cloudways URL or custom domain.
