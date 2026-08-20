# GENTA Admin Panel - Login Security

## Default Login Credentials

**⚠️ IMPORTANT: Change these credentials immediately after first login!**

- **Username:** `admin`
- **Password:** `genta2024`

## Changing Admin Credentials

### Method 1: Using Environment Variables (Recommended for Production)

Set the following environment variables before starting the Flask server:

```powershell
# Set custom username
$env:ADMIN_USERNAME = "your_username"

# Set custom password (will be automatically hashed)
# To generate password hash, run in Python:
# import hashlib
# print(hashlib.sha256('your_password'.encode()).hexdigest())
$env:ADMIN_PASSWORD_HASH = "your_password_hash_here"

# Or simply set the password and it will be hashed automatically
# (Note: This is less secure as password is in environment)
```

### Method 2: Modifying the Code

Edit `GENTA_Flask.py` around line 59:

```python
ADMIN_CREDENTIALS = {
    'username': 'your_new_username',
    'password_hash': hashlib.sha256('your_new_password'.encode()).hexdigest()
}
```

### Generating a Password Hash

Run this Python code to generate a hash for your password:

```python
import hashlib
password = "your_secure_password"
password_hash = hashlib.sha256(password.encode()).hexdigest()
print(f"Your password hash: {password_hash}")
```

## Security Features

### 1. **Login Rate Limiting**
- Maximum 5 failed login attempts per IP address
- 15-minute lockout after exceeding attempt limit
- Automatic reset after successful login

### 2. **Session Security**
- Secure session cookies with HTTP-only flag
- Session expires after 12 hours of inactivity
- "Remember me" option for persistent sessions
- Session validation on every request

### 3. **Password Security**
- Passwords hashed using SHA-256
- No plaintext password storage
- Password fields automatically cleared on page unload

### 4. **Protected Routes**
All admin panel routes require authentication:
- `/` - Main admin panel
- `/api/sync_teachers` - Teacher sync
- `/api/pending_teachers` - Pending teacher list
- `/api/approve_teacher` - Teacher approval
- `/admin/backup*` - All backup operations

### 5. **Audit Logging**
- All login attempts logged with IP address
- Failed login attempts tracked
- Logout events logged

## Best Practices

1. **Change Default Credentials Immediately**
   - Use a strong password (min 12 characters)
   - Mix uppercase, lowercase, numbers, and symbols
   - Avoid common words or patterns

2. **Use Environment Variables in Production**
   - Never commit credentials to version control
   - Use `.env` files (ensure they're in `.gitignore`)
   - Rotate credentials regularly

3. **Enable HTTPS in Production**
   - Edit `GENTA_Flask.py` line 48:
     ```python
     app.config['SESSION_COOKIE_SECURE'] = True
     ```
   - Use ngrok or proper SSL certificates

4. **Monitor Login Attempts**
   - Check Flask logs for failed login attempts
   - Investigate suspicious activity
   - Consider IP whitelisting for additional security

5. **Regular Security Updates**
   - Keep Flask and dependencies updated
   - Monitor security advisories
   - Review access logs periodically

## Troubleshooting

### "Too many failed attempts" Error
- Wait 15 minutes for automatic reset
- Or restart the Flask server to clear lockout
- Check if correct username/password is being used

### Session Expires Too Quickly
- Adjust `PERMANENT_SESSION_LIFETIME` in `GENTA_Flask.py`:
  ```python
  app.config['PERMANENT_SESSION_LIFETIME'] = datetime.timedelta(hours=24)
  ```

### Cannot Access Admin Panel
- Ensure Flask server is running
- Check if you're accessing the correct URL
- Clear browser cookies and try again
- Check Flask logs for errors

## Environment Variables Reference

| Variable | Description | Default |
|----------|-------------|---------|
| `ADMIN_USERNAME` | Admin username | `admin` |
| `ADMIN_PASSWORD_HASH` | SHA-256 hash of admin password | Hash of `genta2024` |
| `FLASK_SECRET_KEY` | Secret key for session encryption | Auto-generated |
| `SESSION_COOKIE_SECURE` | Enable secure cookies (HTTPS only) | `False` |

## Additional Security Recommendations

1. **Firewall Configuration**
   - Restrict Flask port access to trusted IPs
   - Use ngrok authentication features

2. **Two-Factor Authentication** (Future Enhancement)
   - Consider implementing 2FA for critical operations
   - Use TOTP (Time-based One-Time Password)

3. **Database Backup Encryption**
   - Consider encrypting sensitive backup files
   - Store backups in secure, access-controlled locations

4. **API Key Protection**
   - If using external APIs, rotate keys regularly
   - Store API keys in environment variables only

## Support

For security concerns or issues:
1. Check Flask logs: Look for `[Auth]` prefixed messages
2. Review this documentation
3. Contact system administrator

---

**Last Updated:** December 2025  
**Security Level:** Enhanced with login authentication and rate limiting
