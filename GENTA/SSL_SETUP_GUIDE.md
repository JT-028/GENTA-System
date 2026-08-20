# 🔒 SSL Certificate Setup - Quick Guide

## Enable HTTPS in 2 Minutes

### Step 1: Access SSL Settings
1. Login to **Cloudways Dashboard**: https://platform.cloudways.com
2. Go to **Applications** tab
3. Select your **GENTA application**
4. Click **"SSL Certificate"** in left sidebar

### Step 2: Install Let's Encrypt Certificate
1. In SSL Certificate page, find **"Let's Encrypt"** section
2. Your domain should be listed (e.g., `phpstack-1559736-6050318.cloudwaysapps.com`)
3. Click **"Install Certificate"** button
4. Wait 1-2 minutes for installation to complete
5. You'll see ✓ "Certificate installed successfully"

### Step 3: Force HTTPS (Recommended)
1. Still on SSL Certificate page
2. Find **"Force HTTPS Redirection"** toggle
3. Turn it **ON** (blue)
4. All HTTP traffic will now redirect to HTTPS automatically

### Step 4: Verify It Works
1. Visit your site: `https://your-domain.com`
2. Look for 🔒 padlock icon in browser address bar
3. Click the padlock → should show "Connection is secure"
4. Try visiting with `http://` → should auto-redirect to `https://`

---

## ✅ That's It!

Your site now has:
- **Free SSL certificate** (auto-renews every 90 days)
- **Encrypted connections** (all data protected)
- **HTTPS enforced** (no insecure connections allowed)

---

## 🎯 Visual Guide

```
┌─────────────────────────────────────────┐
│  Cloudways Dashboard                    │
├─────────────────────────────────────────┤
│  Applications                           │
│    └─ GENTA                             │
│       ├─ Access Details                 │
│       ├─ Deployment Via Git             │
│       ├─ Application Settings           │
│       ├─ Domain Management              │
│       ├─ SSL Certificate ← CLICK HERE   │
│       └─ Cron Job Management            │
└─────────────────────────────────────────┘

┌─────────────────────────────────────────┐
│  SSL Certificate                        │
├─────────────────────────────────────────┤
│  Let's Encrypt SSL                      │
│                                         │
│  Domain: phpstack-xxx.cloudwaysapps.com │
│  Status: ⚠️ Not Installed               │
│                                         │
│  [Install Certificate] ← CLICK THIS     │
│                                         │
│  ──────────────────────────────────────│
│                                         │
│  Force HTTPS Redirection                │
│  [  ] OFF  ← TURN THIS ON               │
└─────────────────────────────────────────┘
```

---

## 🔧 Troubleshooting

### Problem: "Install Certificate" button is grayed out
**Solution**: Domain might not be verified yet. Wait a few minutes and refresh the page.

### Problem: "Certificate installation failed"
**Solution**: 
- Check if domain is properly configured
- Try again in 5 minutes
- Contact Cloudways support (chat icon in dashboard)

### Problem: Still seeing "Not Secure" in browser
**Solution**:
- Clear browser cache (Ctrl + Shift + Delete)
- Try incognito/private browsing mode
- Verify certificate actually installed (check status in Cloudways)

### Problem: Mixed content warnings
**Solution**: Check your code doesn't have hardcoded `http://` links. Update to `https://` or use protocol-relative URLs (`//`).

---

## 📱 After SSL is Enabled

### Update Your Configuration

In your production `app_local.php`, update:

```php
'Session' => [
    'defaults' => 'php',
    'timeout' => 1440,
    'cookie' => [
        'secure' => true,  // ← Add this (HTTPS only cookies)
        'httponly' => true,
    ],
],
```

This makes your session cookies more secure.

---

## ⏰ Maintenance

**Good news: SSL certificates auto-renew!**

- Let's Encrypt certificates last **90 days**
- Cloudways **automatically renews** them
- You'll get email notifications before expiry
- Check status anytime in SSL Certificate page

---

## 🎉 Benefits of HTTPS

- ✅ **SEO boost** - Google ranks HTTPS sites higher
- ✅ **User trust** - Padlock icon builds confidence
- ✅ **Security** - All data encrypted (passwords, emails, etc.)
- ✅ **Compliance** - Required for handling personal data
- ✅ **Modern features** - Some browser features require HTTPS

---

## 🚀 Next Steps After SSL

1. ✅ Test your site: https://your-domain.com
2. ✅ Update any bookmarks/links to use HTTPS
3. ✅ Test registration and email verification
4. ✅ Update any API endpoints to HTTPS
5. ✅ Inform users about the new HTTPS URL

---

**Time to complete: 2-3 minutes**
**Cost: $0 (Free with Cloudways)**
**Difficulty: ⭐☆☆☆☆ (Very Easy)**

Need help? Cloudways support is available 24/7 via chat!
