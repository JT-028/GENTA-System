<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN">
<html>
<head>
    <title><?= $this->fetch('title') ?></title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #4CAF50; color: white; padding: 20px; text-align: center;">
        <h1 style="margin: 0;">GENTA - Email Verification</h1>
    </div>
    
    <div style="background-color: #f9f9f9; padding: 30px; border: 1px solid #ddd;">
        <h2 style="color: #4CAF50;">Hello <?= h($firstName) ?> <?= h($lastName) ?>!</h2>
        
        <p>Thank you for registering with GENTA using your DepEd email address.</p>
        
        <p><strong>To complete your registration, please verify your email address by clicking the button below:</strong></p>
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="<?= $verificationUrl ?>" 
               style="background-color: #4CAF50; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold; display: inline-block;">
                Verify Email Address
            </a>
        </div>
        
        <p style="color: #666; font-size: 14px;">
            If the button doesn't work, copy and paste this link into your browser:<br>
            <a href="<?= $verificationUrl ?>" style="color: #4CAF50; word-break: break-all;"><?= $verificationUrl ?></a>
        </p>
        
        <p style="color: #d9534f; font-weight: bold;">
            ⚠️ This verification link will expire in 24 hours.
        </p>
        
        <p style="color: #666; font-size: 14px; margin-top: 30px;">
            After verification, your account will be sent to the administrator for approval. You will receive another email once your account has been approved.
        </p>
        
        <hr style="border: none; border-top: 1px solid #ddd; margin: 30px 0;">
        
        <p style="color: #999; font-size: 12px;">
            If you did not create an account with GENTA, please ignore this email or contact us if you have concerns.
        </p>
    </div>
    
    <div style="text-align: center; padding: 20px; color: #999; font-size: 12px;">
        <p>© <?= date('Y') ?> GENTA - Department of Education Learning Management System</p>
    </div>
</body>
</html>
