<div class="text-center" style="padding: 40px 20px;">
    <div style="max-width: 600px; margin: 0 auto;">
        <?php if (isset($verified) && $verified): ?>
            <div class="alert alert-success" role="alert">
                <h3 class="alert-heading">✓ Email Verified Successfully!</h3>
                <hr>
                <p class="mb-0">
                    Your DepEd email address has been verified. Your account is now pending administrator approval.
                    You will receive an email notification once your account has been approved and you can login.
                </p>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                <h3 class="alert-heading">⏳ Registration Pending</h3>
                <hr>
                <p class="mb-0">
                    Your account registration is pending. Please check your DepEd email inbox for a verification link.
                    After verification, an administrator will review and approve your account.
                </p>
            </div>
        <?php endif; ?>
        
        <div class="mt-4">
            <?= $this->Html->link(
                'Return to Login',
                ['controller' => 'Users', 'action' => 'login'],
                ['class' => 'btn btn-primary btn-lg']
            ) ?>
        </div>
    </div>
</div>