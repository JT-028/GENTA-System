<link rel="stylesheet" href="<?= $this->Url->build('/assets/css/mascot.css') ?>">

<!-- Mascot centered above the form -->
<div id="genta-mascot" class="genta-mascot" aria-hidden="true">
    <div id="genta-mascot-container" class="frame-circle" aria-hidden="true"></div>
</div>

<!-- FORGOT PASSWORD TITLE -->
<div class="text-center mb-4">
    <h2 class="auth-title mb-2" style="font-weight: 600; color: #2c3e50;"><i class="mdi mdi-lock-question"></i> Forgot Password?</h2>
    <p class="auth-subtitle" style="color: #6c757d; font-size: 0.95rem;">No worries! Enter your email and we'll send you a password reset link</p>
</div>

<!-- FORGOT PASSWORD FORM -->
<?= $this->Form->create(null, ['url' => ['controller' => 'Users', 'action' => 'forgotPassword', 'prefix' => false]]) ?>
    <div class="form-group">
        <?= $this->Form->email('email', [
            'class' => 'form-control form-control-lg', 
            'id' => 'email', 
            'placeholder' => 'Email Address', 
            'required' => 'required', 
            'aria-label' => 'Email',
            'autocomplete' => 'email'
        ]) ?>
    </div>
    
    <div class="mt-3">
        <?= $this->Form->button('Send Reset Link', [
            'class' => 'btn btn-primary btn-block btn-lg font-weight-medium', 
            'type' => 'submit'
        ]) ?>
    </div>
    
    <div class="text-center mt-4 font-weight-light">
        <i class="mdi mdi-arrow-left"></i>
        <?= $this->Html->link('Back to Login', ['controller' => 'Users', 'action' => 'login'], ['class' => 'text-primary']) ?>
    </div>
<?= $this->Form->end() ?>

<script src="<?= $this->Url->build('/assets/js/mascot.js') ?>?v=<?= filemtime(WWW_ROOT . 'assets/js/mascot.js') ?>" defer></script>
