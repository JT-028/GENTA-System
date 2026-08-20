<link rel="stylesheet" href="<?= $this->Url->build('/assets/css/mascot.css') ?>">

<div id="genta-mascot" class="genta-mascot" aria-hidden="true">
    <div id="genta-mascot-container" class="frame-circle" aria-hidden="true"></div>
</div>

<div class="text-center mb-4">
    <h2 class="auth-title mb-2" style="font-weight: 600; color: #2c3e50;">Welcome Back! 👋</h2>
    <p class="auth-subtitle" style="color: #6c757d; font-size: 0.95rem;">Sign in to continue managing your quizzes, students, and results</p>
</div>

<?= $this->Form->create(null, ['url' => ['controller' => 'Users', 'action' => 'login', 'prefix' => false], 'id' => 'loginForm']) ?>
    <div class="form-group">
        <?= $this->Form->email('email', ['class' => 'form-control form-control-lg', 'id' => 'email', 'placeholder' => 'Email Address', 'required' => 'required', 'aria-label' => 'Email']) ?>
    </div>
    
    <div class="form-group position-relative">
        <input type="password" name="password" class="form-control form-control-lg" id="password" placeholder="Password" required aria-label="Password" autocomplete="current-password" style="padding-right: 45px;">
        
        <button type="button" id="toggle-password-visibility" class="password-toggle-icon" aria-label="Toggle password visibility" tabindex="-1">
            <i class="mdi mdi-eye-off-outline" aria-hidden="true"></i>
        </button>
    </div>
    
    <?php if (isset($showCaptcha) && $showCaptcha): ?>
    <div class="form-group captcha-group">
        <label class="font-weight-medium">Security Check</label>
        <div class="captcha-challenge mb-2">
            <span class="captcha-question"><?= h($captchaChallenge) ?></span>
        </div>
        <?= $this->Form->control('captcha', [
            'class' => 'form-control form-control-lg',
            'placeholder' => 'Enter your answer',
            'required' => true,
            'label' => false,
            'type' => 'text',
            'autocomplete' => 'off'
        ]) ?>
    </div>
    <?php endif; ?>
    
    <?php if (isset($remainingAttempts) && $remainingAttempts < 5 && $remainingAttempts > 0): ?>
    <div class="alert alert-warning mb-3" role="alert">
        <i class="mdi mdi-alert-outline"></i>
        <strong>Warning:</strong> <?= $remainingAttempts ?> login attempt(s) remaining before temporary lockout.
    </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div></div>
        <div class="text-right">
            <?= $this->Html->link('Forgot Password?', ['controller' => 'Users', 'action' => 'forgotPassword'], ['class' => 'text-primary font-weight-medium']) ?>
        </div>
    </div>
    
    <div class="mt-3">
        <?= $this->Form->button('LOG IN', ['class' => 'btn btn-primary btn-block btn-lg font-weight-medium', 'type' => 'submit']) ?>
    </div>
    <div class="text-center mt-4 font-weight-light"> 
        <?= $this->Html->link('Create new account', ['controller' => 'Users', 'action' => 'register'], ['escape' => false, 'class' => 'text-primary']) ?>
    </div>
<?= $this->Form->end() ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    try {
        var urlParams = new URLSearchParams(window.location.search);
        var redirectParam = urlParams.get('redirect');
        if (redirectParam && typeof Swal !== 'undefined') {
            Swal.fire({
                title: 'Session Expired',
                html: '<div style="text-align: center;">Your account has been logged out due to a long period of inactivity. Please log in again to continue.</div>',
                icon: 'warning',
                confirmButtonText: 'OK'
            });
        }
    } catch (err) {
        console.warn('Session timeout helper error', err);
    }
});
</script>

<script>
(function() {
    document.addEventListener('DOMContentLoaded', function() {
        const passwordField = document.getElementById('password');
        const toggleBtn = document.getElementById('toggle-password-visibility');
        const form = document.getElementById('loginForm');
        
        let actualPassword = '';
        let lastCharTimer = null;
        let passwordVisible = false;
        let isToggling = false;

        // 1. INITIALIZATION
        if (passwordField) {
            passwordField.setAttribute('type', 'text');
            passwordField.removeAttribute('data-password'); 
            
            // Check for browser autofill on load
            setTimeout(() => {
                if(passwordField.value && passwordField.value !== '') {
                    actualPassword = passwordField.value;
                    passwordField.value = '•'.repeat(actualPassword.length);
                }
            }, 100);
        }

        // 2. MASKING LOGIC
        if (passwordField) {
            passwordField.addEventListener('input', function(e) {
                if (isToggling) return;

                if (passwordVisible) {
                    actualPassword = this.value;
                    return;
                }

                const currentValue = this.value;
                const cursorPos = this.selectionStart;
                const previousValue = actualPassword;

                // Handle Bulk (Paste/Autofill)
                if (currentValue.length > previousValue.length + 1 || (currentValue && !currentValue.includes('•'))) {
                    actualPassword = currentValue;
                    this.value = '•'.repeat(currentValue.length);
                    this.setSelectionRange(cursorPos, cursorPos);
                }
                // Handle Single Character
                else if (currentValue.length > previousValue.length) {
                    const newChar = e.data || currentValue.slice(-1);
                    const insertedVal = previousValue.slice(0, cursorPos - 1) + newChar + previousValue.slice(cursorPos - 1);
                    actualPassword = insertedVal;

                    this.value = '•'.repeat(insertedVal.length - 1) + newChar;
                    this.setSelectionRange(cursorPos, cursorPos);

                    clearTimeout(lastCharTimer);
                    lastCharTimer = setTimeout(() => {
                        if (!passwordVisible) {
                            this.value = '•'.repeat(insertedVal.length);
                            this.setSelectionRange(cursorPos, cursorPos);
                        }
                    }, 500);
                }
                // Handle Deletion
                else if (currentValue.length < previousValue.length) {
                    const diff = previousValue.length - currentValue.length;
                    const removedVal = previousValue.slice(0, cursorPos) + previousValue.slice(cursorPos + diff);
                    actualPassword = removedVal;
                    this.value = '•'.repeat(removedVal.length);
                    this.setSelectionRange(cursorPos, cursorPos);
                }
            });
        }

        // 3. SECURE SUBMIT
        if (form) {
            form.addEventListener('submit', function(e) {
                const hiddenPass = document.createElement('input');
                hiddenPass.type = 'hidden';
                hiddenPass.name = 'password'; 
                hiddenPass.value = actualPassword;
                this.appendChild(hiddenPass);

                if (passwordField) passwordField.removeAttribute('name');
            });
        }

        // 4. TOGGLE VISIBILITY
        if (toggleBtn && passwordField) {
            toggleBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                isToggling = true;
                passwordVisible = !passwordVisible;
                const icon = this.querySelector('i');
                const cursorPos = passwordField.selectionStart;

                if (passwordVisible) {
                    icon.className = 'mdi mdi-eye-outline';
                    passwordField.value = actualPassword;
                    window.__passwordRevealed = true;
                } else {
                    icon.className = 'mdi mdi-eye-off-outline';
                    passwordField.value = '•'.repeat(actualPassword.length);
                    window.__passwordRevealed = false;
                }

                requestAnimationFrame(() => {
                    passwordField.focus();
                    passwordField.setSelectionRange(cursorPos, cursorPos);
                    if (typeof window.passwordStateRefresh === 'function') {
                        window.passwordStateRefresh(true);
                    }
                });

                setTimeout(() => isToggling = false, 200);
            });

            toggleBtn.addEventListener('mousedown', function(e) { e.preventDefault(); });
        }
    });
})();
</script>

<script src="<?= $this->Url->build('/assets/js/mascot.js') ?>?v=<?= filemtime(WWW_ROOT . 'assets/js/mascot.js') ?>" defer></script>

<style>
/* CSS FIX: Vertically center the icon regardless of input height */
.form-group.position-relative .password-toggle-icon {
    position: absolute !important;
    top: 50% !important;           /* Position from top 50% */
    right: 0 !important;           /* Align right */
    transform: translateY(-50%) !important; /* Pull back up by 50% of its own height */
    width: 45px !important;        /* Fixed width */
    height: 45px !important;       /* Fixed height (touch target) */
    
    background: transparent !important;
    border: none !important;
    z-index: 10 !important;
    color: #6c757d;
    cursor: pointer;
    
    /* Flex to center the icon inside the button */
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    margin: 0 !important;
    padding: 0 !important;
}

.password-toggle-icon:hover { color: #2c3e50; }
.password-toggle-icon:focus { outline: none; }
</style>