# Email SMTP Configuration Guide

## 🎯 Quick Setup Guide

### Step 1: Choose Your Email Provider

**I recommend starting with Gmail** (easiest to set up for testing), then switch to DepEd email server for production.

---

## 📧 Option 1: Gmail (Recommended for Getting Started)

### A. Generate Gmail App Password

1. **Go to your Google Account**: https://myaccount.google.com
2. **Click "Security"** in the left menu
3. **Enable 2-Step Verification** (if not already enabled):
   - Click "2-Step Verification"
   - Follow the setup wizard
4. **Generate App Password**:
   - Go back to Security page
   - Click "App passwords" (you'll only see this if 2-Step Verification is on)
   - Select "Mail" and "Other (Custom name)"
   - Type "GENTA" as the name
   - Click "Generate"
   - **Copy the 16-character password** (it looks like: `abcd efgh ijkl mnop`)

### B. Update Your Cloudways app_local.php

In WinSCP, edit `/applications/zfepxctexd/public_html/config/app_local.php`:

Find the `EmailTransport` section and update:

```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'your-gmail@gmail.com',  // Your actual Gmail address
        'password' => 'abcdefghijklmnop',      // Your 16-char app password (no spaces)
        'tls' => true,
        'client' => null,
        'url' => env('EMAIL_TRANSPORT_DEFAULT_URL', null),
    ],
],

'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => ['noreply@yourdomain.com' => 'GENTA System'],
        'charset' => 'utf-8',
        'headerCharset' => 'utf-8',
    ],
],
```

**Important**: Replace:
- `your-gmail@gmail.com` with your actual Gmail address
- `abcdefghijklmnop` with the 16-character app password (remove spaces)
- `noreply@yourdomain.com` with your actual domain or your Gmail

### C. Test It!

1. Try registering a new account with a @deped.gov.ph email
2. Check the email inbox for the verification email
3. Check Cloudways logs if email doesn't arrive: Applications → Application Logs

---

## 📧 Option 2: DepEd Email Server (Production Recommended)

### Requirements:
- Contact **DepEd ICT department** for SMTP server details
- Ask for:
  - SMTP host address (e.g., `smtp.deped.gov.ph`)
  - SMTP port (usually 587 or 465)
  - Your email credentials
  - TLS/SSL requirements

### Configuration:

```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'smtp.deped.gov.ph',      // Provided by DepEd IT
        'port' => 587,                       // Provided by DepEd IT
        'username' => 'your-user@deped.gov.ph',
        'password' => 'your-password',
        'tls' => true,
        'client' => null,
    ],
],

'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => ['genta-noreply@deped.gov.ph' => 'GENTA System'],
        'charset' => 'utf-8',
    ],
],
```

---

## 📧 Option 3: SendGrid (Good for Production)

### Setup:

1. **Sign up**: https://sendgrid.com/free
   - Free tier: 100 emails/day (enough for testing)
   - Paid: Starting at $15/month for 40,000 emails

2. **Verify your domain** (optional but recommended):
   - Settings → Sender Authentication
   - Follow DNS setup instructions

3. **Create API Key**:
   - Settings → API Keys → Create API Key
   - Name it "GENTA SMTP"
   - Select "Full Access"
   - **Copy the API key** (you won't see it again!)

### Configuration:

```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'smtp.sendgrid.net',
        'port' => 587,
        'username' => 'apikey',              // Literally the word "apikey"
        'password' => 'SG.xxxxxxxxxxxxxxx',  // Your SendGrid API key
        'tls' => true,
        'client' => null,
    ],
],

'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => ['noreply@yourdomain.com' => 'GENTA System'],
        'charset' => 'utf-8',
    ],
],
```

---

## 📧 Option 4: Mailgun (Good for Production)

### Setup:

1. **Sign up**: https://www.mailgun.com
   - Free tier: 5,000 emails/month for first 3 months
   - Pay-as-you-go after: $0.80 per 1,000 emails

2. **Add and verify your domain**:
   - Sending → Domains → Add New Domain
   - Follow DNS setup instructions

3. **Get SMTP credentials**:
   - Sending → Domain Settings → SMTP Credentials
   - Click "Reset Password" to get new password

### Configuration:

```php
'EmailTransport' => [
    'default' => [
        'className' => 'Smtp',
        'host' => 'smtp.mailgun.org',
        'port' => 587,
        'username' => 'postmaster@yourdomain.mailgun.org',
        'password' => 'your-mailgun-password',
        'tls' => true,
        'client' => null,
    ],
],

'Email' => [
    'default' => [
        'transport' => 'default',
        'from' => ['noreply@yourdomain.com' => 'GENTA System'],
        'charset' => 'utf-8',
    ],
],
```

---

## 🔒 Enable SSL Certificate (Cloudways)

### Step-by-Step:

1. **Login to Cloudways Dashboard**: https://platform.cloudways.com

2. **Go to your application**:
   - Click "Applications" tab
   - Select your GENTA application

3. **Access SSL Certificate section**:
   - In the left sidebar, click **"SSL Certificate"**

4. **Install Let's Encrypt**:
   - You'll see "Let's Encrypt" option
   - Click **"Install Certificate"**
   - Wait 1-2 minutes for installation

5. **Verify HTTPS works**:
   - Visit your site with `https://` instead of `http://`
   - You should see a padlock icon in the browser

6. **Force HTTPS** (recommended):
   - Still in SSL Certificate section
   - Enable **"Force HTTPS Redirection"** toggle
   - Now all HTTP traffic will redirect to HTTPS

### Troubleshooting SSL:

**If SSL installation fails:**
- Make sure your domain is properly pointed to Cloudways server
- Wait 24 hours after DNS changes for propagation
- Try "Verify Domain" button first
- Contact Cloudways support (they're usually very helpful)

**If you see "Your connection is not private":**
- Clear browser cache
- Check if certificate was actually installed
- Verify domain name matches certificate

---

## 🧪 Testing Email Configuration

### Test 1: Register a Test Account

1. Go to your site registration page
2. Register with a **real @deped.gov.ph email you have access to**
3. Submit the form
4. Check your DepEd email inbox (and spam folder!)

### Test 2: Check Logs

If email doesn't arrive, check:

**Cloudways Logs:**
- Applications → Your App → Application Logs
- Look for entries with "verification email" or "Mailer"

**Error patterns:**
```
Failed to send verification email: Connection refused
→ Wrong SMTP host or port

Failed to send verification email: Authentication failed
→ Wrong username or password

Failed to send verification email: Could not authenticate
→ Need to enable "Less secure apps" or use app password (Gmail)
```

### Test 3: Manual Email Test

Create a test file: `webroot/test-email.php`

```php
<?php
require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/bootstrap.php';

use Cake\Mailer\Mailer;

try {
    $mailer = new Mailer('default');
    $mailer
        ->setTo('your-test-email@deped.gov.ph')
        ->setSubject('GENTA Email Test')
        ->deliver('This is a test email from GENTA. If you receive this, email is working!');
    
    echo "✅ Email sent successfully!";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
```

Visit: `https://yourdomain.com/test-email.php`

**Delete this file after testing!**

---

## 🚀 Quick Start Recommendation

**For immediate deployment:**

1. **Use Gmail SMTP** (easiest to set up):
   - Takes 5 minutes
   - No cost
   - Reliable delivery
   - Good for up to ~100 registrations/day

2. **Enable SSL** (required):
   - Free with Let's Encrypt
   - Takes 2 minutes
   - Essential for security

3. **Later, migrate to**:
   - DepEd email server (when you get credentials)
   - SendGrid/Mailgun (if you need higher volume)

---

## 📋 Deployment Checklist

- [ ] Choose email provider (Gmail recommended for start)
- [ ] Get SMTP credentials
- [ ] Update `app_local.php` on Cloudways server
- [ ] Enable SSL certificate in Cloudways
- [ ] Force HTTPS redirection
- [ ] Test registration with real @deped.gov.ph email
- [ ] Verify email arrives in inbox
- [ ] Click verification link and confirm it works
- [ ] Check Flask admin notification works (if using)
- [ ] Set `debug => false` in production app_local.php
- [ ] Delete test-email.php if created

---

## 🆘 Common Issues

### "Email not sending"
- Check SMTP credentials are correct
- Verify port is not blocked by firewall
- Check Cloudways logs for specific error
- Try sending test email from command line

### "Email goes to spam"
- Add SPF and DKIM records to DNS
- Use a proper "from" address (not gmail.com if domain is different)
- Verify domain with email provider
- Start with small volume, then increase

### "SSL won't install"
- Verify domain DNS points to Cloudways server
- Wait 24-48 hours after DNS change
- Check domain is not using CDN/proxy (Cloudflare orange cloud)
- Contact Cloudways support

### "Verification link doesn't work"
- Check database migration ran (email_verified column exists)
- Verify route is configured correctly
- Check token hasn't expired (24 hours)
- Look for errors in Cloudways logs

---

## 💡 Pro Tips

1. **Start with Gmail** - Don't overthink it, get it working first
2. **Test thoroughly** - Register with your own DepEd email first
3. **Monitor logs** - First few days, check logs daily for issues
4. **Backup plan** - Have admin approval process even if email fails
5. **Document** - Keep SMTP credentials in secure password manager
6. **Rate limits** - Gmail has daily sending limits (~500/day), plan accordingly

---

Need help with any of these steps? Let me know!
