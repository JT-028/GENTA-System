# GENTA Production Deployment Checklist

Use this checklist when deploying to Cloudways or any production server.

## Pre-Deployment (Local Machine)

### Database Export
- [ ] Export database from XAMPP phpMyAdmin
- [ ] Save `.sql` file in a safe location (not in Git)
- [ ] Verify export includes all tables and data

### Code Preparation
- [ ] All code changes committed to Git
- [ ] Pushed latest changes to GitHub/GitLab
- [ ] Removed any debug code or console.logs
- [ ] Verified `.gitignore` excludes sensitive files

### Security Review
- [ ] No hardcoded passwords in code
- [ ] No API keys committed to Git
- [ ] `app_local.php` is not in Git
- [ ] `.env` is not in Git

---

## Cloudways Server Setup

### Server Creation
- [ ] Created new server on Cloudways
- [ ] Selected appropriate server size
- [ ] Chose PHP 8.1 or 8.2
- [ ] Server is fully deployed and running

### Application Configuration
- [ ] Noted down database credentials
- [ ] Noted down SFTP/SSH credentials
- [ ] Noted down application URL

---

## Code Deployment

### Git Deployment (Preferred)
- [ ] Connected Git repository to Cloudways
- [ ] Deployed code via Git
- [ ] Verified files are in `/public_html/`

### OR SFTP Deployment (Alternative)
- [ ] Uploaded all files via SFTP
- [ ] Excluded: `app_local.php`, `.env`, `/tmp/*`, `/logs/*`, `/vendor/*`
- [ ] Verified file structure is correct

---

## Server Configuration

### SSH Connection
- [ ] Successfully connected via SSH
- [ ] Navigated to application directory
- [ ] Can run commands

### Production Config Files
- [ ] Created `config/app_local.php` from template
- [ ] Set `debug => false`
- [ ] Generated new Security salt
- [ ] Added correct database credentials
- [ ] Set `App.base` to `false` or `/` (not `/GENTA`)
- [ ] Generated new `callbackSecret`

### Optional: Environment File
- [ ] Created `.env` file (if using environment variables)
- [ ] Set all required environment variables

---

## Permissions & Folders

### Directory Creation
- [ ] Created `tmp/cache/models`
- [ ] Created `tmp/cache/persistent`
- [ ] Created `tmp/cache/views`
- [ ] Created `tmp/sessions`
- [ ] Created `logs/`
- [ ] Created `webroot/uploads/profile_images/`

### Permission Setting
- [ ] Set folder permissions: `755`
- [ ] Set file permissions: `644`
- [ ] Set writable directories to `775`:
  - [ ] `tmp/` and all subdirectories
  - [ ] `logs/`
  - [ ] `webroot/uploads/`
- [ ] Set correct ownership (user:www-data)

### Verification
- [ ] Ran `cloudways-setup.sh` script (or manual commands)
- [ ] No permission errors in logs

---

## Database Setup

### Import
- [ ] Opened Cloudways Database Manager
- [ ] Selected correct database
- [ ] Imported `.sql` file successfully
- [ ] Verified all tables are present
- [ ] Checked row counts match local database

### Verification
- [ ] Can connect to database from server
- [ ] Test query runs successfully

---

## Web Server Configuration

### Document Root
- [ ] Set webroot to `/public_html/webroot` (not `/public_html`)
- [ ] Saved and applied changes
- [ ] Web server restarted

### .htaccess
- [ ] Verified `.htaccess` exists in `/webroot/`
- [ ] Verified RewriteEngine rules are present
- [ ] Tested that routing works (no 404 for `/teacher/login`)

---

## SSL/HTTPS Configuration

### SSL Certificate
- [ ] Added custom domain (if applicable)
- [ ] Generated Let's Encrypt SSL certificate
- [ ] OR verified Cloudways subdomain SSL is active
- [ ] Site loads with HTTPS
- [ ] No mixed content warnings

---

## Application Testing

### Basic Functionality
- [ ] Homepage loads without errors
- [ ] CSS and JavaScript load correctly
- [ ] Can navigate to login page
- [ ] Can log in with test account

### Core Features
- [ ] Students page loads
- [ ] Can add a student
- [ ] Can edit a student
- [ ] Can delete a student
- [ ] Questions page loads
- [ ] Can add a question
- [ ] Can edit a question
- [ ] Profile page loads
- [ ] Can update profile
- [ ] Profile picture upload works

### Tour/Walkthrough
- [ ] Tour starts for new user
- [ ] Dashboard tour completes
- [ ] Students tour completes
- [ ] Questions tour completes
- [ ] Profile tour completes
- [ ] Navigation between tours works
- [ ] Scroll bar reactivates after final tour

### File Uploads
- [ ] Profile image upload works
- [ ] Uploaded images display correctly
- [ ] Images persist after page refresh

---

## Error Checking

### Logs Review
- [ ] Checked `logs/error.log` - no critical errors
- [ ] Checked Cloudways error logs - no PHP errors
- [ ] No JavaScript console errors

### Performance
- [ ] Page load time is acceptable
- [ ] Database queries are not timing out
- [ ] Images load within reasonable time

---

## Security Hardening

### Configuration Security
- [ ] `debug` is `false`
- [ ] Security salt is unique and strong
- [ ] Database password is strong
- [ ] No default passwords are in use

### File Security
- [ ] `app_local.php` has correct permissions (644)
- [ ] `.env` has correct permissions (600 if used)
- [ ] No sensitive files are publicly accessible
- [ ] `config/` directory is not web-accessible

### Server Security
- [ ] Cloudways firewall is enabled
- [ ] SSH key authentication (optional but recommended)
- [ ] Regular backups are configured

---

## Backup Configuration

### Automatic Backups
- [ ] Verified Cloudways auto-backup is enabled
- [ ] Set backup frequency (daily recommended)
- [ ] Set backup retention period

### Manual Backup
- [ ] Created first manual backup
- [ ] Verified backup can be downloaded
- [ ] Documented backup restore procedure

---

## Monitoring & Maintenance

### Monitoring Setup
- [ ] Set up Cloudways monitoring alerts
- [ ] Configured email notifications for downtime
- [ ] Configured email notifications for high resource usage

### Documentation
- [ ] Documented server credentials (secure location)
- [ ] Documented database credentials (secure location)
- [ ] Documented deployment process
- [ ] Created runbook for common issues

---

## Post-Deployment

### Team Communication
- [ ] Notified team of deployment
- [ ] Shared production URL
- [ ] Shared documentation location

### User Communication (if applicable)
- [ ] Notified users of new system
- [ ] Provided training/documentation
- [ ] Set up support channel

---

## Ongoing Maintenance Tasks

### Daily
- [ ] Monitor error logs
- [ ] Check application availability

### Weekly
- [ ] Review performance metrics
- [ ] Check disk space usage
- [ ] Verify backups are running

### Monthly
- [ ] Update CakePHP and dependencies
- [ ] Review and update documentation
- [ ] Security audit
- [ ] Performance optimization review

---

## Rollback Plan (In Case of Issues)

### Preparation
- [ ] Database backup before deployment
- [ ] Code backup before deployment
- [ ] Documented rollback steps

### Rollback Steps (if needed)
1. [ ] Restore previous code version (Git revert or SFTP upload)
2. [ ] Restore database from backup
3. [ ] Clear cache
4. [ ] Restart services
5. [ ] Verify application is working
6. [ ] Document what went wrong

---

## Sign-Off

**Deployed By:** ___________________________

**Date:** ___________________________

**Production URL:** ___________________________

**Database Name:** ___________________________

**Verified By:** ___________________________

**Notes:**
_______________________________________________
_______________________________________________
_______________________________________________

---

✅ **Deployment Complete!**

Keep this checklist for future deployments and updates.
