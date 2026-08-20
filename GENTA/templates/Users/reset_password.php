<link rel="stylesheet" href="<?= $this->Url->build('/assets/css/mascot.css') ?>">

<!-- Mascot centered above the form -->
<div id="genta-mascot" class="genta-mascot" aria-hidden="true">
    <div id="genta-mascot-container" class="frame-circle" aria-hidden="true"></div>
</div>

<!-- RESET PASSWORD TITLE -->
<div class="text-center mb-4">
    <h2 class="auth-title mb-2" style="font-weight: 600; color: #2c3e50;"><i class="mdi mdi-shield-lock"></i> Create New Password</h2>
    <p class="auth-subtitle" style="color: #6c757d; font-size: 0.95rem;">Choose a strong password to keep your account secure</p>
</div>

<!-- RESET PASSWORD FORM -->
<?= $this->Form->create(null, ['url' => ['controller' => 'Users', 'action' => 'resetPassword', 'prefix' => false]]) ?>
    <?= $this->Form->hidden('token', ['value' => $token]) ?>
    
    <div class="form-group position-relative">
        <?= $this->Form->password('password', [
            'class' => 'form-control form-control-lg', 
            'id' => 'password', 
            'placeholder' => 'New Password', 
            'required' => 'required',
            'minlength' => '8',
            'maxlength' => '32',
            'aria-label' => 'New Password',
            'autocomplete' => 'new-password'
        ]) ?>
        <button type="button" id="toggle-password-visibility" class="password-toggle-icon" aria-label="Toggle password visibility">
            <i class="mdi mdi-eye-off-outline" aria-hidden="true"></i>
        </button>
    </div>
    
    <div id="password-instruction" class="small mb-2 px-2 py-1 text-muted" style="border-radius: 4px; font-size: 0.8rem; background-color: #f8f9fa; border: 1px solid #e9ecef;">
        <i class="mdi mdi-information-outline"></i> Password must have: 8-32 characters, uppercase, lowercase, number, special character (@,#,!,$,%)
    </div>
    
    <div id="password-strength-indicator" class="small mb-2 px-2 py-1" style="display:none; border-radius: 4px; font-size: 0.8rem;" role="alert">
        <i class="mdi mdi-information-outline"></i> <span id="password-strength-text">Password requirements</span>
    </div>
    
    <div class="form-group position-relative">
        <?= $this->Form->password('password_confirm', [
            'class' => 'form-control form-control-lg', 
            'id' => 'password_confirm', 
            'placeholder' => 'Confirm New Password', 
            'required' => 'required', 
            'aria-label' => 'Confirm Password',
            'autocomplete' => 'new-password'
        ]) ?>
        <button type="button" id="toggle-password-confirm" class="password-toggle-icon" aria-label="Toggle password confirmation visibility">
            <i class="mdi mdi-eye-off-outline" aria-hidden="true"></i>
        </button>
        <small id="password-match-indicator" class="form-text" style="font-size: 0.75rem; min-height: 20px; display: block; margin-top: 0.25rem;" aria-live="polite"></small>
    </div>
    
    <div class="mt-3">
        <?= $this->Form->button('Reset Password', [
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
<script>
(function() {
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('password_confirm');
    const strengthIndicator = document.getElementById('password-strength-indicator');
    const strengthText = document.getElementById('password-strength-text');
    const matchIndicator = document.getElementById('password-match-indicator');
    const passwordInstruction = document.getElementById('password-instruction');
    let hideTimeout = null;

    // Password field with character-by-character masking when hidden
    let actualPassword = '';
    let lastCharTimer = null;
    let passwordVisible = false;
    
    if (passwordField) {
        passwordField.addEventListener('input', function(e) {
            if (passwordVisible) {
                // When password is visible, just validate directly
                actualPassword = this.value;
                validatePasswordStrength(actualPassword);
            } else {
                // When password is hidden, use character-by-character masking
                const currentValue = this.value;
                const cursorPos = this.selectionStart;
                
                if (currentValue.length > actualPassword.length) {
                    const addedChars = currentValue.length - actualPassword.length;
                    const insertPos = cursorPos - addedChars;
                    const newChars = currentValue.substring(insertPos, cursorPos);
                    
                    actualPassword = actualPassword.substring(0, insertPos) + newChars + actualPassword.substring(insertPos);
                    
                    clearTimeout(lastCharTimer);
                    const maskedValue = '•'.repeat(actualPassword.length - 1) + actualPassword.charAt(actualPassword.length - 1);
                    this.value = maskedValue;
                    this.setSelectionRange(cursorPos, cursorPos);
                    
                    lastCharTimer = setTimeout(() => {
                        if (!passwordVisible && passwordField.value.length === actualPassword.length) {
                            passwordField.value = '•'.repeat(actualPassword.length);
                            passwordField.setSelectionRange(cursorPos, cursorPos);
                        }
                    }, 500);
                } else if (currentValue.length < actualPassword.length) {
                    const deletedCount = actualPassword.length - currentValue.length;
                    actualPassword = actualPassword.substring(0, cursorPos) + actualPassword.substring(cursorPos + deletedCount);
                    
                    clearTimeout(lastCharTimer);
                    this.value = '•'.repeat(actualPassword.length);
                    this.setSelectionRange(cursorPos, cursorPos);
                }
                
                validatePasswordStrength(actualPassword);
            }
            
            if (confirmPasswordField && confirmPasswordField.value) {
                checkPasswordMatch();
            }
        });
        
        const form = passwordField.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                passwordField.value = actualPassword;
            });
        }
    }

    // Confirm password field with character-by-character masking when hidden
    let actualConfirmPassword = '';
    let confirmLastCharTimer = null;
    let confirmPasswordVisible = false;
    
    if (confirmPasswordField) {
        confirmPasswordField.addEventListener('input', function(e) {
            if (confirmPasswordVisible) {
                actualConfirmPassword = this.value;
            } else {
                const currentValue = this.value;
                const cursorPos = this.selectionStart;
                
                if (currentValue.length > actualConfirmPassword.length) {
                    const addedChars = currentValue.length - actualConfirmPassword.length;
                    const insertPos = cursorPos - addedChars;
                    const newChars = currentValue.substring(insertPos, cursorPos);
                    
                    actualConfirmPassword = actualConfirmPassword.substring(0, insertPos) + newChars + actualConfirmPassword.substring(insertPos);
                    
                    clearTimeout(confirmLastCharTimer);
                    const maskedValue = '•'.repeat(actualConfirmPassword.length - 1) + actualConfirmPassword.charAt(actualConfirmPassword.length - 1);
                    this.value = maskedValue;
                    this.setSelectionRange(cursorPos, cursorPos);
                    
                    confirmLastCharTimer = setTimeout(() => {
                        if (!confirmPasswordVisible && confirmPasswordField.value.length === actualConfirmPassword.length) {
                            confirmPasswordField.value = '•'.repeat(actualConfirmPassword.length);
                            confirmPasswordField.setSelectionRange(cursorPos, cursorPos);
                        }
                    }, 500);
                } else if (currentValue.length < actualConfirmPassword.length) {
                    const deletedCount = actualConfirmPassword.length - currentValue.length;
                    actualConfirmPassword = actualConfirmPassword.substring(0, cursorPos) + actualConfirmPassword.substring(cursorPos + deletedCount);
                    
                    clearTimeout(confirmLastCharTimer);
                    this.value = '•'.repeat(actualConfirmPassword.length);
                    this.setSelectionRange(cursorPos, cursorPos);
                }
            }
            
            checkPasswordMatch();
        });
        
        const form = confirmPasswordField.closest('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                confirmPasswordField.value = actualConfirmPassword;
            });
        }
    }

    function validatePasswordStrength(password) {
        if (!password) {
            strengthIndicator.style.display = 'none';
            if (passwordInstruction) passwordInstruction.style.display = 'block';
            return;
        }
        
        // Hide initial instruction, show dynamic indicator
        if (passwordInstruction) passwordInstruction.style.display = 'none';
        strengthIndicator.style.display = 'block';
        
        const isValidLength = password.length >= 8 && password.length <= 32;
        const hasUppercase = /[A-Z]/.test(password);
        const hasLowercase = /[a-z]/.test(password);
        const hasNumber = /[0-9]/.test(password);
        const hasSpecial = /[@#!$%&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/.test(password);
        
        const requirements = [];
        if (!isValidLength) requirements.push('8-32 chars');
        if (!hasUppercase) requirements.push('uppercase');
        if (!hasLowercase) requirements.push('lowercase');
        if (!hasNumber) requirements.push('number');
        if (!hasSpecial) requirements.push('special (@,#,!,$,%)');
        
        if (requirements.length === 0) {
            strengthIndicator.className = 'small mb-2 px-2 py-1 text-success';
            strengthIndicator.style.backgroundColor = '#d4edda';
            strengthIndicator.style.border = '1px solid #c3e6cb';
            strengthText.innerHTML = '<i class="mdi mdi-check-circle"></i> Strong password';
            passwordField.setCustomValidity('');
        } else {
            strengthIndicator.className = 'small mb-2 px-2 py-1 text-warning';
            strengthIndicator.style.backgroundColor = '#fff3cd';
            strengthIndicator.style.border = '1px solid #ffeaa7';
            strengthText.innerHTML = '<i class="mdi mdi-alert"></i> Need: ' + requirements.join(', ');
            passwordField.setCustomValidity('Password does not meet requirements');
        }
        
        // Also check password match if confirm field has value
        if (confirmPasswordField && actualConfirmPassword) {
            checkPasswordMatchWithActual();
        }
    }

    function checkPasswordMatch() {
        if (!actualConfirmPassword) {
            matchIndicator.innerHTML = '';
            return;
        }
        
        if (actualPassword === actualConfirmPassword) {
            matchIndicator.className = 'form-text text-success';
            matchIndicator.innerHTML = '<i class="mdi mdi-check-circle"></i> Passwords match';
            confirmPasswordField.setCustomValidity('');
        } else {
            matchIndicator.className = 'form-text text-danger';
            matchIndicator.innerHTML = '<i class="mdi mdi-close-circle"></i> Passwords do not match';
            confirmPasswordField.setCustomValidity('Passwords must match');
        }
    }

    // Password visibility toggle - switches between masking and full visibility
    const togglePasswordBtn = document.getElementById('toggle-password-visibility');
    if (togglePasswordBtn && passwordField) {
        togglePasswordBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            clearTimeout(lastCharTimer);
            passwordVisible = !passwordVisible;
            
            if (passwordVisible) {
                passwordField.value = actualPassword;
                icon.classList.remove('mdi-eye-off-outline');
                icon.classList.add('mdi-eye-outline');
            } else {
                passwordField.value = '•'.repeat(actualPassword.length);
                icon.classList.remove('mdi-eye-outline');
                icon.classList.add('mdi-eye-off-outline');
            }
        });
    }

    // Confirm password visibility toggle
    const togglePasswordConfirmBtn = document.getElementById('toggle-password-confirm');
    if (togglePasswordConfirmBtn && confirmPasswordField) {
        togglePasswordConfirmBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            clearTimeout(confirmLastCharTimer);
            confirmPasswordVisible = !confirmPasswordVisible;
            
            if (confirmPasswordVisible) {
                confirmPasswordField.value = actualConfirmPassword;
                icon.classList.remove('mdi-eye-off-outline');
                icon.classList.add('mdi-eye-outline');
            } else {
                confirmPasswordField.value = '•'.repeat(actualConfirmPassword.length);
                icon.classList.remove('mdi-eye-outline');
                icon.classList.add('mdi-eye-off-outline');
            }
        });
    }
})();
</script>
