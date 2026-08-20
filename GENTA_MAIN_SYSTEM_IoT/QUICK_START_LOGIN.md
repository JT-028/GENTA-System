# GENTA Admin Panel - Quick Start Guide

## 🚀 Getting Started (5 Minutes)

### Step 1: Start the Server
```powershell
cd C:\Users\vonti\OneDrive\Desktop\GENTA_MAIN_SYSTEM_IoT
python GENTA_Flask.py
```

You'll see this security notice:
```
======================================================================
🔐 GENTA ADMIN PANEL - SECURITY NOTICE
======================================================================
Admin login is now REQUIRED to access the admin panel.

DEFAULT CREDENTIALS:
  Username: admin
  Password: genta2024

⚠️  WARNING: Change these credentials immediately!
📖 See ADMIN_LOGIN_README.md for instructions
======================================================================
```

### Step 2: Access the Login Page
Open your browser and go to:
```
http://localhost:5000
```

You'll be automatically redirected to the login page.

### Step 3: Login
Enter the default credentials:
- **Username:** `admin`
- **Password:** `genta2024`

Click "Login" button.

### Step 4: You're In! 🎉
You'll see the admin panel with:
- Your username displayed in the header
- All admin features accessible
- A logout button in the top-right corner

## 🔒 IMPORTANT: Change Your Password NOW!

### Quick Password Change (2 Minutes)

1. **Generate a password hash:**
   ```powershell
   python -c "import hashlib; print(hashlib.sha256('YOUR_NEW_PASSWORD'.encode()).hexdigest())"
   ```

2. **Set environment variables (before starting Flask):**
   ```powershell
   $env:ADMIN_USERNAME = "your_new_username"
   $env:ADMIN_PASSWORD_HASH = "paste_hash_from_step_1"
   ```

3. **Restart Flask server:**
   ```powershell
   python GENTA_Flask.py
   ```

## 🎨 What You'll See

### Login Page
```
┌─────────────────────────────────────┐
│         🤖 GENTA Admin Panel        │
│  Educational AI Assistant Admin     │
├─────────────────────────────────────┤
│                                     │
│  Username: [admin_______________]  │
│  Password: [••••••••____________]  │
│                                     │
│  ☑ Remember me                      │
│                                     │
│  [        Login        ]            │
│                                     │
│  🛡️ Your session is secured        │
└─────────────────────────────────────┘
```

### Admin Panel Header (After Login)
```
┌────────────────────────────────────────────────────────┐
│  🤖 GENTA Admin Panel                                  │
│                                                         │
│  [A] Admin          🟢 Online    🔄 Refresh    🚪 Logout│
│  Administrator                                          │
└────────────────────────────────────────────────────────┘
```

## 🔐 Security Features

### ✅ What's Protected
- ✓ Main admin panel (file management)
- ✓ Teacher approval system
- ✓ Database backups and restores
- ✓ System configuration
- ✓ All API endpoints

### 🛡️ How It Protects You
- **Rate Limiting:** 5 failed attempts = 15-minute lockout
- **Session Security:** Auto-logout after 12 hours
- **Password Hashing:** Never stores plaintext passwords
- **Audit Logging:** Tracks all login attempts
- **IP Tracking:** Monitors suspicious activity

## ⚠️ Common Issues & Solutions

### Issue: "Too many failed attempts"
**Solution:** Wait 15 minutes or restart Flask server

### Issue: Session expires too quickly
**Solution:** Check "Remember me" during login

### Issue: Forgot password
**Solution:** 
1. Stop Flask server
2. Edit `GENTA_Flask.py` line 59 with new credentials
3. Restart Flask server

### Issue: Can't access admin panel
**Solution:**
1. Check if Flask server is running
2. Try `http://127.0.0.1:5000` instead
3. Clear browser cookies
4. Check Flask logs for errors

## 📋 Quick Command Reference

### Start Flask Server
```powershell
python GENTA_Flask.py
```

### Generate Password Hash
```powershell
python -c "import hashlib; print(hashlib.sha256('password'.encode()).hexdigest())"
```

### Set Custom Credentials (Temporary - Current Session)
```powershell
$env:ADMIN_USERNAME = "myusername"
$env:ADMIN_PASSWORD_HASH = "your_hash_here"
python GENTA_Flask.py
```

### Set Custom Credentials (Permanent - System Environment)
```powershell
# Run as Administrator
[System.Environment]::SetEnvironmentVariable('ADMIN_USERNAME', 'myusername', 'User')
[System.Environment]::SetEnvironmentVariable('ADMIN_PASSWORD_HASH', 'your_hash', 'User')
```

## 🎯 What to Do Next

1. ✅ **Login with default credentials**
2. ✅ **Change password immediately**
3. ✅ **Test teacher approval workflow**
4. ✅ **Create a test backup**
5. ✅ **Test logout and re-login**

## 📚 Documentation

- **`ADMIN_LOGIN_README.md`** - Detailed security documentation
- **`ADMIN_IMPLEMENTATION_SUMMARY.md`** - Technical implementation details
- **This file** - Quick start guide

## 💡 Pro Tips

1. **Use "Remember me" carefully** - Only on personal computers
2. **Change password monthly** - Good security practice
3. **Monitor Flask logs** - Check for suspicious activity
4. **Logout when done** - Especially on shared computers
5. **Use environment variables** - Never commit passwords to Git

## 🆘 Need Help?

1. Check Flask console for `[Auth]` log messages
2. Review `ADMIN_LOGIN_README.md` for detailed instructions
3. Check for errors in `GENTA_Flask.py`
4. Restart Flask server (fixes most issues)

---

**Remember:** 
- Default username: `admin`
- Default password: `genta2024`
- **CHANGE THESE IMMEDIATELY!**

🔒 **Your admin panel is now secure!** 🔒
