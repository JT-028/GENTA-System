<!DOCTYPE html>
<html>
<head>
    <title>Password Reset Request</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
        <h2 style="color: #2c3e50;">Password Reset Request</h2>
        
        <p>Hello <?= h($firstName) ?>,</p>
        
        <p>We received a request to reset your password for your GENTA account. If you didn't make this request, please ignore this email.</p>
        
        <p>To reset your password, click the button below:</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?= h($resetUrl) ?>" 
               style="background-color: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">
                Reset Password
            </a>
        </div>
        
        <p>Or copy and paste this link into your browser:</p>
        <p style="word-break: break-all; color: #3498db;"><?= h($resetUrl) ?></p>
        
        <p><strong>This link will expire in 1 hour.</strong></p>
        
        <p>For security reasons, if you didn't request this password reset, please contact support immediately.</p>
        
        <hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">
        
        <p style="font-size: 12px; color: #7f8c8d;">
            This is an automated message from GENTA (Gamified Educational and Networking Tool for Academics).<br>
            Please do not reply to this email.
        </p>
    </div>
</body>
</html>
