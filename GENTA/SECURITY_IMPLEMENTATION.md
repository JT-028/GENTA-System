# GENTA Security Implementation Summary

## Overview
This document outlines the comprehensive security improvements implemented in the GENTA application.

## Implementation Date
December 6, 2024

---

## High Priority Security Features (Implemented ✅)

### 1. Rate Limiting
**Location:** `src/Controller/Component/SecurityComponent.php`

**Configuration:**
- Maximum failed attempts: 5 per IP/email combination
- Rate limit window: 300 seconds (5 minutes)
- Account lockout duration: 900 seconds (15 minutes)
- Tracking: File-based cache system

**How it works:**
- Tracks failed login attempts by both IP address and email
- After 5 failed attempts within 5 minutes, account is locked for 15 minutes
- Automatically clears rate limits after successful login
- Shows remaining attempts to user

**Files Modified:**
- `src/Controller/Component/SecurityComponent.php` (created)
- `config/app.php` (added 'security' cache configuration)
- `src/Controller/UsersController.php` (integrated rate limiting in login method)

---

### 2. CAPTCHA
**Location:** `src/Controller/Component/CaptchaComponent.php`

**Configuration:**
- Type: Math-based (addition and subtraction)
- Trigger: After 2 failed login attempts
- Expiration: 5 minutes
- Operations: Numbers 1-10

**How it works:**
- Generates simple math problems (e.g., "What is 5 + 3?")
- Stores answer in session with timestamp
- Validates user input against stored answer
- Automatically expires after 5 minutes

**Files Modified:**
- `src/Controller/Component/CaptchaComponent.php` (created)
- `src/Controller/UsersController.php` (integrated CAPTCHA verification)
- `templates/Users/login.php` (added CAPTCHA display)
- `webroot/assets/css/login.css` (added CAPTCHA styling)
- `templates/layout/guest-layout.php` (included login.css)

---

### 3. Session Security
**Location:** `src/Controller/Component/SecurityComponent.php`

**Configuration:**
- Idle timeout: 1800 seconds (30 minutes)
- Absolute timeout: 43200 seconds (12 hours)
- Session regeneration: On every login
- Metadata tracking: IP address, user agent, timestamps

**How it works:**
- Regenerates session ID on successful login (prevents session fixation)
- Tracks last activity time and invalidates after 30 minutes idle
- Enforces absolute maximum session duration of 12 hours
- Stores IP and user agent for session validation
- Automatically logs out user on session timeout

**Files Modified:**
- `src/Controller/Component/SecurityComponent.php` (session methods)
- `src/Controller/UsersController.php` (calls regenerateSession on login)

---

### 4. Password Reset Flow
**Location:** `src/Controller/UsersController.php`

**Configuration:**
- Token length: 64 characters (hex)
- Token expiration: 3600 seconds (1 hour)
- Security: No email enumeration (always shows success message)

**How it works:**
1. User requests password reset via email
2. System generates cryptographically secure token
3. Token and expiration time saved to database
4. Email sent with reset link (if email exists)
5. User clicks link and enters new password
6. Token validated, expiration checked
7. Password updated, token cleared, account unlocked

**Files Modified:**
- `src/Controller/UsersController.php` (added forgotPassword and resetPassword methods)
- `templates/Users/forgot_password.php` (created)
- `templates/Users/reset_password.php` (created)
- `templates/email/html/password_reset.php` (created)
- `templates/Users/login.php` (added "Forgot Password?" link)

---

## Medium Priority Security Features

### 5. Account Lockout
**Status:** ✅ Implemented (integrated with rate limiting)

**Configuration:**
- Lockout trigger: 5 failed attempts
- Lockout duration: 15 minutes
- Automatic unlock: After lockout period expires
- Manual unlock: Via password reset

**Database Fields:**
- `failed_login_attempts` - Counter for failed attempts
- `account_locked_until` - Datetime when lockout expires

---

### 6. Two-Factor Authentication (2FA)
**Status:** ⚠️ Structure created, implementation pending

**Database Fields:**
- `two_factor_secret` - Stores TOTP secret key
- `two_factor_enabled` - Boolean flag

**To implement:**
1. Install TOTP library (e.g., `composer require pragmarx/google2fa`)
2. Create 2FA setup page with QR code generation
3. Add verification code input on login
4. Generate and store backup codes
5. Update login flow to check 2FA status

---

## Additional Improvements

### 7. Name Validation Fix
**Location:** `src/Model/Table/UsersTable.php`

**Changes:**
- Removed `alphaNumeric()` requirement from first_name and last_name
- Added custom pattern: `/^[a-zA-Z\s\'-]+$/`
- Now supports: spaces, hyphens, apostrophes

**Examples supported:**
- "Mary Jane Smith"
- "O'Connor"
- "Jean-Pierre"
- "De La Cruz"

---

## Database Schema Changes

**Migration File:** `config/schema/20251206_add_security_fields.sql`

**New columns added to `users` table:**
```sql
- password_reset_token (VARCHAR 255, indexed)
- password_reset_expires (DATETIME)
- failed_login_attempts (INT, default 0)
- account_locked_until (DATETIME)
- two_factor_secret (VARCHAR 255)
- two_factor_enabled (TINYINT 1, default 0)
```

**Migration Status:** ✅ Applied to local database (`my_app`)

---

## Security Best Practices Implemented

1. **Password Hashing:** Using CakePHP's DefaultPasswordHasher (bcrypt)
2. **CSRF Protection:** CakePHP CSRF component active
3. **SQL Injection Prevention:** Using ORM and prepared statements
4. **XSS Prevention:** Template escaping enabled by default
5. **Secure Tokens:** Using `random_bytes()` for cryptographic security
6. **Email Enumeration Prevention:** Always show same message regardless of email existence
7. **Session Regeneration:** New session ID on login
8. **Input Validation:** Server-side validation for all forms
9. **Rate Limiting:** Both IP and email-based tracking
10. **Secure Headers:** (Recommend adding security headers in production)

---

## Testing Checklist

### Rate Limiting
- [ ] Test 5 failed login attempts triggers lockout
- [ ] Verify lockout lasts 15 minutes
- [ ] Confirm lockout clears after successful login
- [ ] Check remaining attempts display correctly

### CAPTCHA
- [ ] Verify CAPTCHA appears after 2 failed attempts
- [ ] Test correct answer allows login
- [ ] Test incorrect answer blocks login
- [ ] Verify CAPTCHA expires after 5 minutes

### Session Security
- [ ] Test session expires after 30 minutes idle
- [ ] Test session expires after 12 hours absolute
- [ ] Verify session regenerates on login

### Password Reset
- [ ] Test forgot password email sent
- [ ] Test reset link works within 1 hour
- [ ] Test expired token shows error
- [ ] Test invalid token shows error
- [ ] Verify password successfully updates
- [ ] Confirm account unlocks after reset

### Name Validation
- [ ] Test names with spaces accepted (e.g., "Mary Jane")
- [ ] Test names with hyphens accepted (e.g., "Jean-Pierre")
- [ ] Test names with apostrophes accepted (e.g., "O'Connor")

---

## Production Deployment Steps

### 1. Database Migration
Run on production database (Cloudways):
```bash
mysql -u [username] -p [database] < config/schema/20251206_add_security_fields.sql
```

### 2. Clear Cache
```bash
bin/cake cache clear_all
```

### 3. Email Configuration
Ensure email settings are configured in production `config/app_local.php`:
- SMTP server
- From address
- Credentials

### 4. Test Password Reset
- Send test password reset email
- Verify email delivery
- Test reset link functionality

### 5. Monitor Security Events
Consider implementing:
- Login attempt logging
- Failed attempt alerts
- Rate limit violation alerts

---

## Configuration Options

All security settings can be adjusted in `src/Controller/Component/SecurityComponent.php`:

```php
// Rate limiting
private const MAX_ATTEMPTS = 5;
private const RATE_LIMIT_WINDOW = 300; // 5 minutes
private const LOCKOUT_DURATION = 900; // 15 minutes

// Session timeouts
private const SESSION_IDLE_TIMEOUT = 1800; // 30 minutes
private const SESSION_ABSOLUTE_TIMEOUT = 43200; // 12 hours
```

CAPTCHA settings in `src/Controller/Component/CaptchaComponent.php`:
```php
private const CAPTCHA_EXPIRY = 300; // 5 minutes
private const CAPTCHA_TRIGGER_ATTEMPTS = 2;
```

---

## Future Enhancements

### Recommended Additional Security Features:
1. **2FA Implementation** - Complete TOTP setup
2. **Login History** - Track all login attempts with timestamps and IPs
3. **Security Audit Log** - Log all security events
4. **Email Notifications** - Alert users of suspicious activity
5. **Password Strength Meter** - Visual feedback on password creation
6. **Security Headers** - Add Content-Security-Policy, X-Frame-Options, etc.
7. **Brute Force Protection** - Progressive delays after failed attempts
8. **Device Fingerprinting** - Track known devices
9. **IP Whitelist/Blacklist** - Admin-level IP management
10. **Account Activity Dashboard** - Show users their login history

---

## Support and Maintenance

### Cache Location
File-based cache stored in: `tmp/cache/security/`

### Clearing Security Cache
```bash
# Clear all security cache
rm -rf tmp/cache/security/*

# Or use CakePHP command
bin/cake cache clear security
```

### Troubleshooting

**Issue: User locked out and can't log in**
Solution: Use password reset flow to unlock account

**Issue: CAPTCHA not showing**
Solution: Clear browser cache and cookies

**Issue: Password reset email not received**
Solution: Check email configuration and spam folder

**Issue: Session timing out too quickly**
Solution: Adjust SESSION_IDLE_TIMEOUT in SecurityComponent.php

---

## File Structure Summary

```
src/
  Controller/
    Component/
      SecurityComponent.php (NEW)
      CaptchaComponent.php (NEW)
    UsersController.php (MODIFIED)
  Model/
    Table/
      UsersTable.php (MODIFIED - name validation)
    Entity/
      User.php (MODIFIED - added security fields)

config/
  app.php (MODIFIED - security cache config)
  schema/
    20251206_add_security_fields.sql (NEW)

templates/
  Users/
    login.php (MODIFIED - CAPTCHA, forgot password link)
    forgot_password.php (NEW)
    reset_password.php (NEW)
  email/
    html/
      password_reset.php (NEW)
  layout/
    guest-layout.php (MODIFIED - included login.css)

webroot/
  assets/
    css/
      login.css (NEW - CAPTCHA and security styling)
```

---

## Contact Information

For questions or issues related to security implementation:
- Review this document
- Check code comments in SecurityComponent.php and CaptchaComponent.php
- Test in development environment before deploying to production

---

**Document Version:** 1.0  
**Last Updated:** December 6, 2024  
**Status:** Implementation Complete, Testing Pending
