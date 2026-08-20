# Gmail App Password Setup - Step by Step

## 🎯 Getting Your Gmail App Password (5 minutes)

### Step 1: Enable 2-Step Verification

1. **Go to Google Account Security**: https://myaccount.google.com/security
2. **Sign in** with your Gmail account
3. Under "Signing in to Google", click **"2-Step Verification"**
4. If it says OFF:
   - Click **"Get Started"**
   - Follow the wizard (you'll add your phone number)
   - Choose SMS or Google Authenticator app
   - Complete the setup
5. If already ON, continue to Step 2

### Step 2: Generate App Password

1. **Still on the Security page**: https://myaccount.google.com/security
2. Under "Signing in to Google", look for **"App passwords"**
   
   **🔍 Can't find "App passwords"?** Try these:
   
   #### Option A: Direct Link
   Go directly to: https://myaccount.google.com/apppasswords
   
   #### Option B: Use Search
   - At the top of the security page, there's a search box
   - Type "app passwords" and click the result
   
   #### Option C: Check Account Type
   If you still can't see it, your account might be:
   - **Google Workspace** (work/school) - Admin might have disabled it
   - **Personal Gmail** with Advanced Protection - App passwords not available
   - **Not fully verified** - Wait 5 minutes after enabling 2-Step, then refresh

3. **Once you're on App Passwords page**:
   - Click **"Select app"** dropdown → Choose **"Mail"**
   - Click **"Select device"** dropdown → Choose **"Other (Custom name)"**
   - Type: **"GENTA"**
   - Click **"Generate"**

4. **You'll see a 16-character password** (4 groups of 4 letters):
   ```
   Example: abcd efgh ijkl mnop
   ```
   
5. **COPY THIS PASSWORD NOW** - You won't see it again!
   - Remove the spaces: `abcdefghijklmnop`
   - Save it somewhere safe

### Step 3: Update Your Local Configuration

In your local `config/app_local.php`, update the EmailTransport section:

```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-actual-gmail@gmail.com',  // ← Your Gmail address
        'password' => 'abcdefghijklmnop',             // ← Your 16-char app password (no spaces)
        'tls' => true,
        'client' => null,
        'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
    ],
],

'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => ['your-actual-gmail@gmail.com' => 'GENTA System'],
        'charset' => 'utf-8',
        'headerCharset' => 'utf-8',
    ],
],
```

### Step 4: Update Cloudways Production Config

**Via WinSCP:**
1. Connect to your Cloudways server
2. Navigate to: `/applications/zfepxctexd/public_html/config/app_local.php`
3. Edit the file
4. Update the same EmailTransport section with your Gmail credentials
5. Save the file

---

## 🧪 Test Your Email Setup

### Quick Test File

Create: `webroot/test-email.php`

```php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Mailer\Mailer;

echo "<h1>GENTA Email Test</h1>";
echo "<p>Testing email configuration...</p>";

try {
    $mailer = new Mailer('default');
    $mailer
        ->setTo('your-test-email@gmail.com')  // ← Use your own email
        ->setSubject('GENTA Email Test - ' . date('Y-m-d H:i:s'))
        ->deliver('✅ Success! If you receive this email, your SMTP configuration is working correctly.');
    
    echo "<div style='color: green; font-weight: bold;'>✅ Email sent successfully!</div>";
    echo "<p>Check your inbox at: your-test-email@gmail.com</p>";
} catch (Exception $e) {
    echo "<div style='color: red; font-weight: bold;'>❌ Error sending email:</div>";
    echo "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
}

echo "<hr>";
echo "<p><strong>⚠️ IMPORTANT:</strong> Delete this file after testing!</p>";
echo "<p><code>rm webroot/test-email.php</code></p>";
```

**To test:**
1. Upload this file to: `webroot/test-email.php` on Cloudways
2. Visit: `https://your-domain.com/test-email.php`
3. Check the output
4. Check your email inbox
5. **DELETE the file after testing!**

---

## 🔧 Troubleshooting

### "App passwords" link doesn't appear

**Reason 1: Account Type**
- If you're using a Google Workspace (work/school) account, your admin might have disabled app passwords
- **Solution**: Use a personal Gmail account OR contact your admin

**Reason 2: Advanced Protection Program**
- If enrolled in Advanced Protection, app passwords are disabled
- **Solution**: Use OAuth2 (more complex) OR use a different email provider

**Reason 3: 2-Step Verification not fully active**
- Sometimes takes 5-10 minutes to propagate
- **Solution**: Sign out, wait 10 minutes, sign back in

**Reason 4: Using security keys only**
- If you only use security keys for 2-Step, app passwords won't work
- **Solution**: Add another 2-Step method (SMS/Authenticator app)

### "Username and Password not accepted"

**Error message:**
```
Failed to send email: SMTP Error: 535 5.7.8 Username and Password not accepted
```

**Solutions:**
1. **Check credentials**:
   - Username must be full email: `youremail@gmail.com`
   - Password must be the 16-char app password (not your Gmail password)
   - Remove all spaces from app password

2. **Enable "Less secure app access"** (older accounts):
   - Go to: https://myaccount.google.com/lesssecureapps
   - Turn it ON
   - Try again

3. **Allow access from new device**:
   - Check your email for "Google blocked sign-in attempt"
   - Click "Yes, it was me"
   - Try sending email again

### "Connection timed out"

**Error message:**
```
Failed to send email: SMTP Error: Could not connect to SMTP host
```

**Solutions:**
1. **Check firewall**: Cloudways server might block outbound SMTP
   - Contact Cloudways support to unblock port 587
   
2. **Try port 465** (SSL instead of TLS):
   ```php
   'port' => 465,
   'tls' => false,  // ← Change to false
   ```

3. **Check SMTP host**: Make sure it's `smtp.gmail.com`

---

## 📋 Configuration Checklist

- [ ] 2-Step Verification enabled on Gmail
- [ ] App Password generated (16 characters)
- [ ] Updated local `config/app_local.php` with Gmail credentials
- [ ] Updated Cloudways `config/app_local.php` with same credentials
- [ ] Uploaded test-email.php to Cloudways
- [ ] Visited test URL and confirmed email sent
- [ ] Checked inbox and received test email
- [ ] Deleted test-email.php from Cloudways
- [ ] Tested actual registration flow with email verification

---

## 🔐 Security Notes

### Storing App Passwords Securely

**Never commit app passwords to Git!**

Your `config/app_local.php` is already in `.gitignore`, but double-check:

```bash
# In your local Git repo
git status
# app_local.php should NOT appear if it has credentials
```

**For team deployments**, use environment variables:

```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => env('SMTP_HOST', 'smtp.gmail.com'),
        'port' => env('SMTP_PORT', 587),
        'username' => env('SMTP_USERNAME'),
        'password' => env('SMTP_PASSWORD'),
        'tls' => true,
    ],
],
```

Then set these in Cloudways Application Settings → Environment Variables.

### Rotating App Passwords

**Best practice**: Change app passwords every 90 days

1. Go to https://myaccount.google.com/apppasswords
2. Find "GENTA" in the list
3. Click "Revoke"
4. Generate a new one
5. Update your config files

---

## ✅ Alternative: Use Gmail OAuth2

If app passwords don't work, you can use OAuth2 (more secure, but complex).

**Would you like me to set this up?** Let me know and I can create the OAuth2 configuration for you.

---

## 🎯 Summary

1. Enable 2-Step Verification on Gmail
2. Generate App Password (16 chars)
3. Update `config/app_local.php` with credentials
4. Test with `test-email.php`
5. Test registration flow
6. Remember to re-enable @deped.gov.ph restriction later!

**Need help?** Common issue is finding the App Passwords link - try the direct link: https://myaccount.google.com/apppasswords

**Still stuck?** Let me know what error message you're seeing and I'll help troubleshoot!
