<!-- USER PROFILE -->
<div class="row">
    <!-- GET ERROR MESSAGES -->
    <?php 
        $fieldErrors = $this->Field->errors($user, ['first_name', 'last_name', 'email', 'password']);
    ?>

    <!-- USER DATA -->
    <div class="col-xl-6 col-lg-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">Profile</h4>
                <?= $this->Form->create($user, ['type' => 'file', 'url' => ['controller' => 'Dashboard', 'action' => 'profile', 'prefix' => 'Teacher'], 'id' => 'profileForm']) ?>
<div class="form-group mb-3">
    <label for="profile_image">Profile Picture</label>
    <?= $this->Form->file('profile_image', ['accept' => 'image/*', 'class' => 'form-control']) ?>
    <?php if (!empty($user->profile_image)): ?>
        <img src="<?= $this->Url->build('/uploads/profile_images/' . h($user->profile_image)) ?>" alt="Current Profile" style="max-width:100px; margin-top:10px;">
    <?php endif; ?>
</div>
                    <div class="form-group">
                        <label for="first_name">First Name</label>
                        <?= $this->Form->text('first_name', ['class' => 'form-control form-control-lg ' . $fieldErrors['first_name']['class'], 'id' => 'first_name', 'placeholder' => 'First Name', 'required' => 'required']) ?>
                        <div class="invalid-feedback">
                            <?= $fieldErrors['first_name']['message'] ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name</label>
                        <?= $this->Form->text('last_name', ['class' => 'form-control form-control-lg ' . $fieldErrors['last_name']['class'], 'id' => 'last_name', 'placeholder' => 'Last Name', 'required' => 'required']) ?>
                        <div class="invalid-feedback">
                            <?= $fieldErrors['last_name']['message'] ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <?= $this->Form->email('email', ['class' => 'form-control form-control-lg ' . $fieldErrors['email']['class'], 'id' => 'email', 'placeholder' => 'Email Address', 'required' => 'required', 'disabled' => 'disabled']) ?>
                        <div class="invalid-feedback">
                            <?= $fieldErrors['email']['message'] ?>
                        </div>
                    </div>

                    <?= $this->Form->button('Edit', ['class' => 'btn btn-gradient-primary', 'type' => 'submit', 'name' => 'submit', 'value' => 'profile']) ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>

    <!-- CHANGE PASSWORD -->
    <div class="col-xl-6 col-lg-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">Change Password</h4>
                <?= $this->Form->create(NULL, ['url' => ['controller' => 'Dashboard', 'action' => 'profile', 'prefix' => 'Teacher'], 'id' => 'passwordForm']) ?>
                    <div class="form-group">
                        <label for="current_password">Current Password</label>
                        <?= $this->Form->password('current_password', ['class' => 'form-control form-control-lg', 'id' => 'current_password', 'placeholder' => 'Current Password', 'required' => 'required']) ?>
                    </div>
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <?= $this->Form->password('password', ['class' => 'form-control form-control-lg ' . $fieldErrors['password']['class'], 'id' => 'password', 'placeholder' => 'Password', 'required' => 'required']) ?>
                        <div class="invalid-feedback">
                            <?= $fieldErrors['password']['message'] ?>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <?= $this->Form->password('confirm_password', ['class' => 'form-control form-control-lg', 'id' => 'confirm_password', 'placeholder' => 'Confirm Password', 'required' => 'required']) ?>
                    </div>

                    <?= $this->Form->button('Change Password', ['class' => 'btn btn-gradient-primary', 'type' => 'submit', 'name' => 'submit', 'value' => 'password']) ?>
                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>