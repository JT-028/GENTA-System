# GENTA Security Implementation Guide

## ✅ Implemented Security Features

### 1. **DepEd Email Restriction (@deped.gov.ph)**
- Only email addresses ending with `@deped.gov.ph` are accepted during registration
- Frontend validation: HTML5 pattern matching
- Backend validation: Custom regex validation in UsersTable
- User-friendly error messages guide users to use proper DepEd emails

### 2. **Email Verification System**
- **Two-step verification process:**
  1. User registers with DepEd email → receives verification email
  2. User clicks verification link → email confirmed → sent to admin for approval

- **Security features:**
  - Unique verification tokens (64-character random hex)
  - Token expiration (24 hours)
  - One-time use tokens (cleared after verification)
  - Database-backed verification status

### 3. **Enhanced Password Security**
- **Requirements:**
  - 8-16 characters only
  - Alphanumeric characters only (letters and numbers)
  - No special characters allowed (simplifies and standardizes)

- **User experience:**
  - Real-time password strength indicator
  - Brief character visibility (500ms) when typing
  - Color-coded feedback (red warning → green success)
  - Password match confirmation

### 4. **Name Field Validation**
- **Letters-only policy:**
  - First name and last name accept only alphabetic characters and spaces
  - Real-time filtering removes invalid characters as user types
  - Prevents SQL injection attempts through name fields

---

## 📧 Email Verification Flow

### Registration Process:
```
1. User fills registration form with @deped.gov.ph email
   ↓
2. System validates all fields (names, email domain, password)
   ↓
3. Account created with:
   - email_verified = 0 (unverified)
   - status = 0 (pending approval)
   - verification_token = random 64-char hex
   - verification_token_expires = +24 hours
   ↓
4. Verification email sent to DepEd address
   ↓
5. User clicks link in email within 24 hours
   ↓
6. System verifies token and marks email_verified = 1
   ↓
7. Admin notification sent (Flask system)
   ↓
8. Admin approves account → User receives approval email
   ↓
9. User can now login
```

### Why This Is Secure:
- **Proves ownership**: User must have access to the actual DepEd mailbox
- **Time-limited**: Tokens expire in 24 hours
- **Single-use**: Token is cleared after verification
- **Admin gate**: Even after email verification, admin must approve
- **Audit trail**: All verification attempts logged

---

## 🔒 Additional Security Recommendations

### **Immediate Actions (High Priority):**

#### 1. **Configure Email Transport (SMTP)**
Currently using default PHP mail(). For production, configure SMTP:

**In `config/app_local.php`:**
```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'smtp.gmail.com', // or DepEd SMTP server
        'port' => 587,
        'username' => 'your-deped-email@deped.gov.ph',
        'password' => 'your-app-password',
        'tls' => true,
        'client' => null,
    ],
],

'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => ['noreply@deped.gov.ph' => 'GENTA System'],
        'charset' => 'utf-8',
        'headerCharset' => 'utf-8',
    ],
],
```

**Options:**
- **Gmail SMTP**: Use Google Workspace / Gmail (requires app password)
- **DepEd SMTP**: If DepEd has internal SMTP server (best option)
- **SendGrid/Mailgun**: Third-party email services (reliable, paid)
- **AWS SES**: Amazon email service (cost-effective for bulk)

#### 2. **Enable HTTPS/SSL**
- Already available in Cloudways → SSL Certificate → Let's Encrypt (free)
- **Why:** Encrypts all data in transit (login credentials, session cookies)
- **Action:** Go to Cloudways dashboard → Install SSL certificate

#### 3. **Verify DepEd Email Domain Ownership (SPF/DKIM)**
To prevent email spoofing:
- Add SPF record to your domain DNS
- Configure DKIM signing
- Set up DMARC policy

**This proves emails from GENTA are legitimate DepEd communications**

#### 4. **Rate Limiting for Registration**
Prevent spam registrations:

**Install CakePHP Rate Limiter plugin:**
```bash
composer require cakephp/rate-limiter
```

**Add to UsersController::register():**
```php
use Cake\RateLimit\Limit;

public function beforeFilter(\Cake\Event\EventInterface $event)
{
    parent::beforeFilter($event);
    
    // Limit registration attempts: 3 per hour per IP
    if ($this->request->getParam('action') === 'register') {
        $this->Authentication->allowUnauthenticated(['register']);
        $this->RateLimit->apply('registration', Limit::perHour(3));
    }
}
```

#### 5. **CAPTCHA Integration (Prevent Bots)**
Add Google reCAPTCHA v3 to registration form:

**Install:**
```bash
composer require google/recaptcha
```

**Add to register.php template:**
```php
<script src="https://www.google.com/recaptcha/api.js?render=YOUR_SITE_KEY"></script>
<script>
grecaptcha.ready(function() {
    grecaptcha.execute('YOUR_SITE_KEY', {action: 'register'}).then(function(token) {
        document.getElementById('recaptcha-token').value = token;
    });
});
</script>
<input type="hidden" name="recaptcha_token" id="recaptcha-token">
```

**Validate in controller:**
```php
$recaptcha = new \ReCaptcha\ReCaptcha('YOUR_SECRET_KEY');
$resp = $recaptcha->verify($this->request->getData('recaptcha_token'), $this->request->clientIp());
if (!$resp->isSuccess()) {
    $this->Flash->error('CAPTCHA verification failed. Please try again.');
    return;
}
```

---

### **Medium Priority:**

#### 6. **Session Security**
**Add to `config/app_local.php`:**
```php
'Session' => [
    'defaults' => 'php',
    'timeout' => 1440, // 24 hours
    'cookie' => [
        'name' => 'GENTA_SESSION',
        'httponly' => true, // Prevent JavaScript access
        'secure' => true,   // HTTPS only (after SSL enabled)
        'samesite' => 'Lax' // CSRF protection
    ],
],
```

#### 7. **Password Reset with Email Verification**
Similar flow to registration:
- User requests reset → email sent with token
- Token expires in 1 hour
- New password must meet same requirements

#### 8. **Account Lockout After Failed Login Attempts**
Prevent brute force attacks:
- Lock account after 5 failed login attempts
- Require email verification to unlock
- Log all failed attempts with IP addresses

#### 9. **Two-Factor Authentication (2FA)**
For admin accounts:
- Use Google Authenticator / Authy
- Time-based one-time passwords (TOTP)
- Backup codes for account recovery

#### 10. **Database Migration for Email Verification**
**You need to run this SQL on your Cloudways database:**

```sql
-- Add email verification fields
ALTER TABLE `users` 
ADD COLUMN `email_verified` TINYINT(1) NOT NULL DEFAULT 0 AFTER `token`,
ADD COLUMN `verification_token` VARCHAR(255) NULL AFTER `email_verified`,
ADD COLUMN `verification_token_expires` DATETIME NULL AFTER `verification_token`;

-- Add index for faster lookups
ALTER TABLE `users` ADD INDEX `idx_verification_token` (`verification_token`);
```

**How to run:**
1. Go to Cloudways → Applications → Database Access
2. Click "Launch phpMyAdmin"
3. Select your database
4. Go to SQL tab
5. Paste the migration SQL
6. Click "Go"

---

### **Low Priority (Nice to Have):**

#### 11. **IP Whitelisting for Admin Actions**
Restrict admin approval actions to specific IP ranges (e.g., DepEd office IPs)

#### 12. **Email Verification Resend**
Allow users to request a new verification email if original expired

#### 13. **Account Activity Log**
Track all login attempts, password changes, email changes

#### 14. **Security Headers**
Add in webroot/.htaccess:
```apache
Header set X-Frame-Options "SAMEORIGIN"
Header set X-Content-Type-Options "nosniff"
Header set X-XSS-Protection "1; mode=block"
Header set Referrer-Policy "strict-origin-when-cross-origin"
```

#### 15. **Content Security Policy (CSP)**
Prevent XSS attacks by controlling what resources can load

---

## 🧪 Testing the Email Verification

### Local Testing (Development):
Since localhost can't send real emails, use **MailHog** or **Mailtrap**:

**MailHog (Free, Local):**
```bash
# Download from https://github.com/mailhog/MailHog
# Run: ./MailHog.exe

# In config/app_local.php:
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'localhost',
        'port' => 1025,
        'username' => null,
        'password' => null,
    ],
],
```
Open http://localhost:8025 to see caught emails

**Mailtrap (Free tier available):**
- Sign up at https://mailtrap.io
- Get SMTP credentials
- Configure in app_local.php

### Production Testing:
1. Register with your real DepEd email
2. Check your DepEd inbox for verification email
3. Click the link
4. Verify you see "Email verified successfully" message
5. Check admin gets notified

---

## 📋 Security Checklist

### Before Going Live:
- [ ] Run database migration for email verification fields
- [ ] Configure production SMTP (Gmail/DepEd server)
- [ ] Enable HTTPS/SSL certificate in Cloudways
- [ ] Set `debug => false` in app_local.php
- [ ] Generate unique Security.salt (64 random characters)
- [ ] Set secure session configuration
- [ ] Test email verification flow end-to-end
- [ ] Verify Flask admin notification works
- [ ] Add rate limiting to registration
- [ ] (Optional) Add reCAPTCHA v3
- [ ] Document email configuration for future admins
- [ ] Set up email monitoring/alerts

### Regular Maintenance:
- [ ] Monitor failed login attempts weekly
- [ ] Review registered accounts monthly
- [ ] Update CakePHP and dependencies quarterly
- [ ] Backup database daily (Cloudways has this)
- [ ] Check error logs for suspicious activity
- [ ] Rotate Security.salt annually

---

## 🆘 Troubleshooting

### Email Not Sending:
1. Check logs: `logs/error.log` and `logs/debug.log`
2. Verify SMTP credentials in app_local.php
3. Test with simple email first
4. Check firewall allows outbound SMTP (port 587/465)
5. Gmail users: Enable "Less secure app access" or use app password

### Verification Link Not Working:
1. Check if token exists in database: `SELECT * FROM users WHERE verification_token IS NOT NULL`
2. Verify token hasn't expired: `SELECT NOW(), verification_token_expires FROM users WHERE id = X`
3. Check routes are loaded: `bin/cake routes | grep verify`
4. Check URL is correct: Should be `https://yourdomain.com/users/verify-email/{token}`

### User Can't Register:
1. Check email domain: Must end with @deped.gov.ph
2. Check password: Must be 8-16 alphanumeric only
3. Check names: Must be letters only
4. Check database migration ran successfully
5. Check form validation errors in browser console

---

## 📚 Next Steps

1. **Run the database migration** (priority #1)
2. **Configure SMTP** for email sending
3. **Enable SSL** in Cloudways
4. **Test complete flow** with your DepEd email
5. **Consider rate limiting** and CAPTCHA

Would you like me to help you with any of these implementations?
