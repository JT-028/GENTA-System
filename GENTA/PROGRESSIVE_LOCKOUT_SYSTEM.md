# Progressive Account Lockout System

## Overview
The system implements a three-tier progressive lockout mechanism to protect against brute-force attacks while giving legitimate users opportunities to regain access.

## Lockout Tiers

### Tier 1: First Lockout (15 minutes)
- **Trigger**: 5 failed login attempts
- **Duration**: 15 minutes
- **lockout_count**: Set to 1
- **Status**: Account remains active (status = 1)
- **Message**: "Account locked due to too many failed attempts. Please try again in 15 minutes."

### Tier 2: Second Lockout (30 minutes)
- **Trigger**: 5 failed login attempts after first lockout expires
- **Duration**: 30 minutes
- **lockout_count**: Set to 2
- **Status**: Account remains active (status = 1)
- **Message**: "Account locked due to repeated failed attempts. Please try again in 30 minutes. Next lockout will be permanent."

### Tier 3: Permanent Lockout (Admin Required)
- **Trigger**: 5 failed login attempts after second lockout expires
- **Duration**: Permanent (no expiry)
- **lockout_count**: Set to 3 or higher
- **Status**: Account deactivated (status = 0)
- **account_locked_until**: Set to NULL
- **Message**: "Account permanently locked due to repeated security violations. Please contact an administrator to reactivate your account."

## Reset Behavior

### Successful Login
When a user successfully logs in:
- `failed_login_attempts` → 0
- `lockout_count` → 0
- `account_locked_until` → NULL
- This provides a fresh start regardless of previous lockouts

### Lockout Expiration (Tiers 1 & 2)
When a lockout timer expires and user attempts to login:
- `failed_login_attempts` → 0
- `lockout_count` → Remains unchanged
- `account_locked_until` → NULL
- User gets 5 new attempts, but lockout_count tracks escalation

### Permanent Lockout (Tier 3)
- No automatic reset
- Admin must manually reactivate by setting status = 1
- Optionally, admin can reset lockout_count to 0 for a fresh start

## Database Schema

```sql
-- Required column in users table
lockout_count INT DEFAULT 0 NOT NULL 
COMMENT 'Number of times account has been locked (0=never, 1=first 15min, 2=second 30min, 3+=permanent)'
```

## Implementation Files

1. **UsersController.php** (Lines 250-430)
   - Login authentication
   - Lockout checks
   - Progressive lockout logic
   - Reset logic

2. **cloudways_add_lockout_count.sql**
   - SQL migration to add lockout_count column
   - Run on Cloudways database

## Admin Reactivation Process

To reactivate a permanently locked account:

```sql
-- Reactivate account
UPDATE users SET status = 1 WHERE id = <user_id>;

-- Optionally reset lockout counter for fresh start
UPDATE users SET lockout_count = 0 WHERE id = <user_id>;

-- Clear any remaining lockout data
UPDATE users SET failed_login_attempts = 0, account_locked_until = NULL WHERE id = <user_id>;
```

## Logging

All lockout events are logged with appropriate severity:
- `info`: Lockout expiration, successful resets
- `warning`: Failed login attempts, first/second lockouts
- `error`: Permanent lockouts (3rd+ tier)
- `debug`: Reset attempts process tracking

## Testing Scenarios

### Scenario 1: First Lockout
1. Fail login 5 times → Account locked for 15 minutes
2. Wait 15 minutes (or manually clear account_locked_until)
3. Login with correct password → All counters reset to 0

### Scenario 2: Second Lockout
1. Fail login 5 times → First lockout (15 min)
2. Wait 15 minutes
3. Fail login 5 times again → Second lockout (30 min)
4. Wait 30 minutes
5. Login with correct password → All counters reset to 0

### Scenario 3: Permanent Lockout
1. Fail login 5 times → First lockout (15 min)
2. Wait 15 minutes
3. Fail login 5 times → Second lockout (30 min)
4. Wait 30 minutes
5. Fail login 5 times → Permanent lockout, status = 0
6. Must contact admin for reactivation

## Security Considerations

- Lockout timers use server time (not client time)
- All database updates use direct SQL queries to avoid ORM caching issues
- Status check prevents any login for permanently locked accounts
- Lockout count persists across sessions to track repeated violations
- Successful login fully resets the security state
