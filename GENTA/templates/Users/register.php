<link rel="stylesheet" href="<?= $this->Url->build('/assets/css/mascot.css') ?>">

<?php $fieldErrors = $this->Field->errors($user ?? null, ['first_name', 'last_name', 'email', 'password']); ?>

<!-- Mascot centered above the original single-column register form -->
<div id="genta-mascot" class="genta-mascot" aria-hidden="true">
    <div id="genta-mascot-container" class="frame-circle" aria-hidden="true"></div>
</div>

<div class="text-center mb-4">
    <h2 class="auth-title mb-2" style="font-weight: 600; color: #2c3e50;">Create Your Account ✨</h2>
    <p class="auth-subtitle" style="color: #6c757d; font-size: 0.95rem;">Join GENTA and start creating engaging quizzes for your students</p>
</div>

<!-- REGISTER FORM -->
<?= $this->Form->create(NULL, ['url' => ['controller' => 'Users', 'action' => 'register'], 'novalidate' => true]) ?>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group">
                <?= $this->Form->text('first_name', ['class' => 'form-control form-control-lg ' . ($fieldErrors['first_name']['class'] ?? ''), 'id' => 'first_name', 'placeholder' => 'First Name', 'required' => 'required', 'pattern' => '[a-zA-Z\s\'-]+', 'title' => 'Only letters, spaces, hyphens, and apostrophes allowed']) ?>
                <div class="invalid-feedback"><?= $fieldErrors['first_name']['message'] ?? '' ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                <?= $this->Form->text('last_name', ['class' => 'form-control form-control-lg ' . ($fieldErrors['last_name']['class'] ?? ''), 'id' => 'last_name', 'placeholder' => 'Last Name', 'required' => 'required', 'pattern' => '[a-zA-Z\s\'-]+', 'title' => 'Only letters, spaces, hyphens, and apostrophes allowed']) ?>
                <div class="invalid-feedback"><?= $fieldErrors['last_name']['message'] ?? '' ?></div>
            </div>
        </div>
    </div>
    <div class="form-group">
        <?= $this->Form->email('email', ['class' => 'form-control form-control-lg ' . ($fieldErrors['email']['class'] ?? ''), 'id' => 'email', 'placeholder' => 'Email Address', 'required' => 'required', 'title' => 'Enter your email address']) ?>
        <small id="email-instruction" class="form-text text-muted mt-1" style="font-size: 0.8rem;">
            <i class="mdi mdi-information-outline"></i> Use a valid email format (e.g., user@example.com)
        </small>
        <div class="invalid-feedback"><?= $fieldErrors['email']['message'] ?? '' ?></div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="form-group position-relative">
                <input type="text" name="password" class="form-control form-control-lg <?= $fieldErrors['password']['class'] ?? '' ?>" id="password" placeholder="Password" required minlength="8" maxlength="32" autocomplete="off" style="padding-right: 45px;" />
                
                <button type="button" id="toggle-password-visibility" class="password-toggle-icon" aria-label="Toggle password visibility" tabindex="-1">
                    <i class="mdi mdi-eye-off-outline" aria-hidden="true"></i>
                </button>
                
                <div class="invalid-feedback"><?= $fieldErrors['password']['message'] ?? '' ?></div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="form-group position-relative">
                <input type="text" name="confirm_password" class="form-control form-control-lg" id="confirm_password" placeholder="Confirm Password" autocomplete="off" style="padding-right: 45px;" />
                
                <small id="password-match-indicator" class="form-text" style="font-size: 0.75rem; min-height: 20px; display: block; margin-top: 0.25rem;" aria-live="polite"></small>
            </div>
        </div>
    </div>
    <div id="password-instruction" class="small mb-2 px-2 py-1 text-muted" style="border-radius: 4px; font-size: 0.8rem; background-color: #f8f9fa; border: 1px solid #e9ecef;">
        <i class="mdi mdi-information-outline"></i> Password must have: 8-32 characters, uppercase, lowercase, number, special character (@,#,!,$,%)
    </div>
    <div id="password-strength-indicator" class="small mb-2 px-2 py-1" style="display:none; border-radius: 4px; font-size: 0.8rem;" role="alert">
        <i class="mdi mdi-information-outline"></i> <span id="password-strength-text">Password requirements</span>
    </div>
    <div class="mb-3">
        <div class="form-check custom-checkbox">
            <?= $this->Form->checkbox('terms_and_conditions', ['class' => 'form-check-input', 'required' => 'required', 'id' => 'terms_checkbox']) ?>
            <label class="form-check-label text-muted" for="terms_checkbox">
                I agree to the <a href="#" id="open-terms-modal" class="text-primary">Terms & Conditions</a>
            </label>
        </div>
    </div>
    <div class="mt-3">
        <?= $this->Form->button('SIGN UP', ['class' => 'btn btn-primary btn-block btn-lg', 'type' => 'submit']) ?>
    </div>
    <div class="text-center mt-3 font-weight-light">
        Already have an account? <?= $this->Html->link('Login', ['controller' => 'Users', 'action' => 'login'], ['escape' => false, 'class' => 'text-primary']) ?>
    </div>
<?= $this->Form->end() ?>

<script src="<?= $this->Url->build('/assets/js/mascot.js') ?>?v=<?= filemtime(WWW_ROOT . 'assets/js/mascot.js') ?>" defer></script>
<script>
(function() {
    // --- 1. ELEMENT SELECTION ---
    const passwordField = document.getElementById('password');
    const confirmPasswordField = document.getElementById('confirm_password');
    const strengthIndicator = document.getElementById('password-strength-indicator');
    const strengthText = document.getElementById('password-strength-text');
    const matchIndicator = document.getElementById('password-match-indicator');
    
    // --- 2. PASSWORD VARIABLES ---
    let actualPassword = '';
    let actualConfirmPassword = '';
    let lastCharTimer = null;
    let confirmLastCharTimer = null;
    let passwordVisible = false;
    let confirmPasswordVisible = false;
    let isToggling = false;

    // --- 3. PASSWORD FIELD INITIALIZATION ---
    if (passwordField) {
        passwordField.setAttribute('type', 'text'); 
        passwordField.removeAttribute('data-password'); 
    }
    if (confirmPasswordField) {
        confirmPasswordField.setAttribute('type', 'text');
    }

    // Initialize Mascot Eyes (Closed by default)
    setTimeout(() => {
        if (typeof window.showEyes === 'function') window.showEyes('closed', true);
    }, 10);

    // --- 4. MASKING LOGIC (THE FIX IS HERE) ---
    function handleMasking(field, actualValueRef, timerRef, isVisibleRef) {
        if (!field) return;

        field.addEventListener('input', function(e) {
            // Prevent interference during toggle animation
            if (isToggling) return;

            // CASE A: Password is VISIBLE (Plain Text) -> SAVE IT DIRECTLY
            if (isVisibleRef()) {
                updateActualValue(this.value); // Capture what user typed
                
                // Trigger validations immediately so UI updates
                if (field.id === 'password') validatePasswordStrength(this.value);
                checkPasswordMatch(); 
                return; // Stop here, don't do masking logic
            }

            // CASE B: Password is HIDDEN -> Apply Masking Logic
            const currentValue = this.value;
            const cursorPos = this.selectionStart;
            const previousValue = actualValueRef(); 
            
            // Handle bulk changes (Paste/Autofill)
            if (currentValue.length > previousValue.length + 1 || (currentValue && !currentValue.includes('•'))) {
                updateActualValue(currentValue);
                this.value = '•'.repeat(currentValue.length);
                this.setSelectionRange(cursorPos, cursorPos);
            } 
            // Handle single character typing
            else if (currentValue.length > previousValue.length) {
                const newChar = e.data || currentValue.slice(-1); 
                const insertedVal = previousValue.slice(0, cursorPos - 1) + newChar + previousValue.slice(cursorPos - 1);
                updateActualValue(insertedVal);

                // Show character briefly then mask
                this.value = '•'.repeat(insertedVal.length - 1) + newChar; 
                this.setSelectionRange(cursorPos, cursorPos);

                clearTimeout(timerRef());
                const newTimer = setTimeout(() => {
                    if (!isVisibleRef()) {
                        this.value = '•'.repeat(insertedVal.length);
                        this.setSelectionRange(cursorPos, cursorPos);
                    }
                }, 500);
                updateTimer(newTimer);
            } 
            // Handle Deletion
            else if (currentValue.length < previousValue.length) {
                const diff = previousValue.length - currentValue.length;
                const removedVal = previousValue.slice(0, cursorPos) + previousValue.slice(cursorPos + diff);
                updateActualValue(removedVal);
                this.value = '•'.repeat(removedVal.length);
                this.setSelectionRange(cursorPos, cursorPos);
            }

            if (field.id === 'password') validatePasswordStrength(actualValueRef());
            checkPasswordMatch();
        });

        function updateActualValue(val) {
            if (field.id === 'password') actualPassword = val;
            else actualConfirmPassword = val;
        }
        function updateTimer(timer) {
            if (field.id === 'password') lastCharTimer = timer;
            else confirmLastCharTimer = timer;
        }
    }

    handleMasking(passwordField, () => actualPassword, () => lastCharTimer, () => passwordVisible);
    handleMasking(confirmPasswordField, () => actualConfirmPassword, () => confirmLastCharTimer, () => confirmPasswordVisible);

    // --- 5. SECURE SUBMIT HANDLER ---
    const form = passwordField ? passwordField.closest('form') : null;
    if (form) {
        form.addEventListener('submit', function(e) {
            // Validate Match
            if (actualPassword !== actualConfirmPassword) {
                e.preventDefault();
                confirmPasswordField.focus();
                if(matchIndicator) {
                    matchIndicator.className = 'form-text text-danger';
                    matchIndicator.innerHTML = '<i class="mdi mdi-close-circle"></i> Passwords do not match';
                }
                return false;
            }

            // Create Hidden Inputs for Real Data
            const hiddenPass = document.createElement('input');
            hiddenPass.type = 'hidden';
            hiddenPass.name = 'password'; 
            hiddenPass.value = actualPassword;
            this.appendChild(hiddenPass);

            const hiddenConfirm = document.createElement('input');
            hiddenConfirm.type = 'hidden';
            hiddenConfirm.name = 'confirm_password';
            hiddenConfirm.value = actualConfirmPassword;
            this.appendChild(hiddenConfirm);

            // Remove 'name' from visible fields so bullets are NOT submitted
            if (passwordField) passwordField.removeAttribute('name');
            if (confirmPasswordField) confirmPasswordField.removeAttribute('name');
        });
    }

    // --- 6. TOGGLE VISIBILITY (Eye Icon) ---
    const togglePasswordBtn = document.getElementById('toggle-password-visibility');
    if (togglePasswordBtn && passwordField) {
        togglePasswordBtn.addEventListener('click', function(e) {
            e.preventDefault();
            isToggling = true;
            passwordVisible = !passwordVisible;
            confirmPasswordVisible = !confirmPasswordVisible;

            const icon = this.querySelector('i');
            
            if (passwordVisible) {
                // Show Text
                icon.className = 'mdi mdi-eye-outline';
                passwordField.value = actualPassword;
                if(confirmPasswordField) confirmPasswordField.value = actualConfirmPassword;
                
                // ✅ CORRECT FIX: Set the flag, don't force the eyes
                window.__passwordRevealed = true; 

            } else {
                // Mask Text
                icon.className = 'mdi mdi-eye-off-outline';
                passwordField.value = '•'.repeat(actualPassword.length);
                if(confirmPasswordField) confirmPasswordField.value = '•'.repeat(actualConfirmPassword.length);
                
                // ✅ CORRECT FIX: Reset the flag
                window.__passwordRevealed = false;
            }

            // ✅ CRITICAL STEP: Ask the mascot to check the flag we just set
            if (typeof window.passwordStateRefresh === 'function') {
                window.passwordStateRefresh(true);
            } else if (typeof window.showEyes === 'function') {
                // Fallback only if the refresh function is missing
                window.showEyes(passwordVisible ? 'peak' : 'closed', true);
            }

            setTimeout(() => isToggling = false, 200);
        });
    }

    // --- 7. HELPERS (Validation) ---
    function validatePasswordStrength(password) {
        if (!strengthIndicator) return;
        if (!password) { strengthIndicator.style.display = 'none'; return; }
        strengthIndicator.style.display = 'block';
        
        const reqs = [
            { regex: /.{8,32}/, label: '8-32 chars' },
            { regex: /[A-Z]/, label: 'uppercase' },
            { regex: /[a-z]/, label: 'lowercase' },
            { regex: /[0-9]/, label: 'number' },
            { regex: /[@#!$%&*()_+\-=\[\]{};:'",.<>?\/\\|`~]/, label: 'special' }
        ];
        const missing = reqs.filter(r => !r.regex.test(password)).map(r => r.label);
        
        if (missing.length === 0) {
            strengthIndicator.className = 'small mb-2 px-2 py-1 text-success';
            strengthIndicator.style.backgroundColor = '#d4edda';
            strengthIndicator.style.border = '1px solid #c3e6cb';
            strengthText.innerHTML = '<i class="mdi mdi-check-circle"></i> Strong password';
        } else {
            strengthIndicator.className = 'small mb-2 px-2 py-1 text-warning';
            strengthIndicator.style.backgroundColor = '#fff3cd';
            strengthIndicator.style.border = '1px solid #ffeaa7';
            strengthText.innerHTML = '<i class="mdi mdi-alert"></i> Missing: ' + missing.join(', ');
        }
    }

    function checkPasswordMatch() {
        if (!matchIndicator || !confirmPasswordField) return;
        
        // Clear message if confirm is empty
        if (!actualConfirmPassword) { 
            matchIndicator.innerHTML = ''; 
            confirmPasswordField.classList.remove('is-valid', 'is-invalid');
            return; 
        }
        
        if (actualPassword === actualConfirmPassword) {
            matchIndicator.className = 'form-text text-success';
            matchIndicator.innerHTML = '<i class="mdi mdi-check-circle"></i> Passwords match';
            confirmPasswordField.classList.remove('is-invalid');
            confirmPasswordField.classList.add('is-valid');
        } else {
            matchIndicator.className = 'form-text text-danger';
            matchIndicator.innerHTML = '<i class="mdi mdi-close-circle"></i> Passwords do not match';
            confirmPasswordField.classList.remove('is-valid');
            confirmPasswordField.classList.add('is-invalid');
        }
    }

    function validateNameField(field) {
        if (field) field.addEventListener('input', function() { this.value = this.value.replace(/[^a-zA-Z\s]/g, ''); });
    }
    validateNameField(document.getElementById('first_name'));
    validateNameField(document.getElementById('last_name'));

    // --- 8. RESTORED: TERMS & CONDITIONS MODAL ---
    const openTermsModal = document.getElementById('open-terms-modal');
    const termsCheckbox = document.getElementById('terms_checkbox');
    
    if (openTermsModal) {
        openTermsModal.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            showTermsModal();
        });
    }
    
    if (termsCheckbox) {
        termsCheckbox.addEventListener('change', function(e) {
            if (this.checked && !this.dataset.acceptedViaModal) {
                e.preventDefault();
                this.checked = false;
                showTermsModal();
            } else if (!this.checked) {
                delete this.dataset.acceptedViaModal;
            }
        });
    }
    
    function showTermsModal() {
        const modalHTML = `
            <div class="modal fade show" id="termsModal" tabindex="-1" style="display: block; background: rgba(0,0,0,0.5); z-index: 9999;">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <h5 class="modal-title"><i class="mdi mdi-file-document-outline"></i> Terms & Conditions</h5>
                            <button type="button" class="close text-white" style="background:none; border:none; font-size:1.5rem;" data-dismiss="modal">&times;</button>
                        </div>
                        <div class="modal-body" style="max-height: 60vh; overflow-y: auto;">
                            <h6 class="font-weight-bold">1. Acceptance of Terms</h6>
                            <p>By creating an account on GENTA (Gamified Educational and Networking Tool for Academics), you agree to comply with and be bound by these Terms and Conditions.</p>
                            
                            <h6 class="font-weight-bold">2. User Accounts</h6>
                            <p>You are responsible for maintaining the confidentiality of your account credentials. You must:</p>
                            <ul>
                                <li>Provide accurate and complete information during registration</li>
                                <li>Keep your password secure and not share it with others</li>
                                <li>Notify us immediately of any unauthorized access to your account</li>
                                <li>Be responsible for all activities that occur under your account</li>
                            </ul>
                            
                            <h6 class="font-weight-bold">3. Acceptable Use</h6>
                            <p>You agree to use GENTA only for lawful educational purposes. You will not:</p>
                            <ul>
                                <li>Upload or share inappropriate, offensive, or copyrighted content</li>
                                <li>Attempt to gain unauthorized access to the system</li>
                                <li>Interfere with or disrupt the service</li>
                                <li>Impersonate others or misrepresent your identity</li>
                                <li>Use the platform for commercial purposes without authorization</li>
                            </ul>
                            
                            <h6 class="font-weight-bold">4. Privacy and Data Protection</h6>
                            <p>We respect your privacy and are committed to protecting your personal data:</p>
                            <ul>
                                <li>Your personal information will be collected, stored, and processed in accordance with applicable data protection laws</li>
                                <li>We will not share your data with third parties without your consent, except as required by law</li>
                                <li>You have the right to access, correct, or delete your personal information</li>
                            </ul>
                            
                            <h6 class="font-weight-bold">5. Academic Integrity</h6>
                            <p>All users must maintain academic honesty:</p>
                            <ul>
                                <li>Quiz answers must be your own work</li>
                                <li>Sharing quiz answers or questions with others is prohibited</li>
                                <li>Cheating or plagiarism will result in account suspension</li>
                            </ul>
                            
                            <h6 class="font-weight-bold">6. Intellectual Property</h6>
                            <p>All content, features, and functionality of GENTA are owned by the platform and protected by intellectual property laws. You may not:</p>
                            <ul>
                                <li>Copy, modify, or distribute platform content without permission</li>
                                <li>Reverse engineer or attempt to extract source code</li>
                                <li>Remove copyright or proprietary notices</li>
                            </ul>
                            
                            <h6 class="font-weight-bold">7. Termination</h6>
                            <p>We reserve the right to suspend or terminate your account if you violate these Terms and Conditions or engage in conduct that we deem harmful to the platform or other users.</p>
                            
                            <h6 class="font-weight-bold">8. Disclaimer of Warranties</h6>
                            <p>GENTA is provided "as is" without warranties of any kind. We do not guarantee that the service will be uninterrupted, secure, or error-free.</p>
                            
                            <h6 class="font-weight-bold">9. Limitation of Liability</h6>
                            <p>We shall not be liable for any indirect, incidental, special, or consequential damages arising from your use of the platform.</p>
                            
                            <h6 class="font-weight-bold">10. Changes to Terms</h6>
                            <p>We reserve the right to modify these Terms and Conditions at any time. Continued use of the platform after changes constitutes acceptance of the updated terms.</p>
                            
                            <h6 class="font-weight-bold">11. Contact Information</h6>
                            <p>For questions about these Terms and Conditions, please contact the GENTA administration team.</p>
                            
                            <hr>
                            <p class="text-muted small"><strong>Last Updated:</strong> December 6, 2025</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="accept-terms-btn">I Accept</button>
                        </div>
                    </div>
                </div>
            </div>`;
        
        document.body.insertAdjacentHTML('beforeend', modalHTML);
        
        const modal = document.getElementById('termsModal');
        const acceptBtn = document.getElementById('accept-terms-btn');
        
        function closeModal() {
            modal.style.display = 'none';
            setTimeout(() => modal.remove(), 300);
        }
        
        modal.querySelectorAll('[data-dismiss="modal"]').forEach(btn => btn.addEventListener('click', closeModal));
        
        acceptBtn.addEventListener('click', function() {
            if(termsCheckbox) {
                termsCheckbox.dataset.acceptedViaModal = 'true';
                termsCheckbox.checked = true;
            }
            closeModal();
        });
    }
})();


</script>

<style>
/* Custom checkbox styling to match site theme */
.custom-checkbox {
    display: flex;
    align-items: center;
    gap: 10px;
}

.custom-checkbox .form-check-input {
    width: 20px;
    height: 20px;
    margin: 0;
    border: 2px solid #667eea;
    border-radius: 4px;
    cursor: pointer;
    position: relative;
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background-color: white;
    transition: all 0.2s ease;
    flex-shrink: 0;
}

.custom-checkbox .form-check-input:checked {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-color: #667eea;
}

.custom-checkbox .form-check-input:checked::after {
    content: '✓';
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    color: white;
    font-size: 13px;
    font-weight: bold;
    line-height: 1;
}

.custom-checkbox .form-check-input:hover {
    border-color: #764ba2;
    box-shadow: 0 0 0 0.15rem rgba(102, 126, 234, 0.2);
}

.custom-checkbox .form-check-input:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
}

.custom-checkbox .form-check-label {
    cursor: pointer;
    user-select: none;
    margin: 0;
    padding: 0;
    font-size: 0.95rem;
    line-height: 1.4;
}

#open-terms-modal {
    text-decoration: none;
    font-weight: 500;
    transition: color 0.2s;
}

#open-terms-modal:hover {
    text-decoration: underline;
    color: #764ba2 !important;
}
/* FIXED PASSWORD ICON POSITIONING */
.form-group.position-relative .password-toggle-icon {
    position: absolute !important;
    top: 0 !important;               /* Stick to the top of the input */
    bottom: auto !important;         /* Prevent stretching */
    right: 0 !important;             /* Align to right edge */
    height: 3.175rem !important;     /* EXACT height of your large input field */
    width: 45px !important;
    
    transform: none !important;      /* CRITICAL: Disables the old 'center-y' logic */
    margin: 0 !important;
    padding: 0 !important;
    
    background: transparent !important;
    border: none !important;
    z-index: 5 !important;
    color: #6c757d;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
}

.password-toggle-icon:hover {
    color: #2c3e50;
}

.password-toggle-icon:focus {
    outline: none;
}

/* Ensure the input text doesn't run under the icon */
#password, #confirm_password {
    padding-right: 45px !important;
}

/* Force the error message to display as a block below the input */
.invalid-feedback {
    display: none;
    width: 100%;
    margin-top: 0.25rem;
    font-size: 80%;
    color: #dc3545;
}

.form-control.is-invalid ~ .invalid-feedback {
    display: block !important;
}
</style>
