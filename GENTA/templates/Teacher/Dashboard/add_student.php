<?php
/** @var \\App\\View\\AppView $this */
/** @var \\App\\Model\\Entity\\Student $student */
?>

<div class="row">
    <div class="col-8 mx-auto">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title"><?= __('Add Student') ?></h4>

                <?= $this->Form->create($student, ['id' => 'addStudentForm', 'novalidate' => true]) ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $this->Form->control('lrn', [
                            'label' => 'LRN (Learner Reference Number)', 
                            'maxlength' => 12, 
                            'placeholder' => 'e.g. 123456789012',
                            'id' => 'lrnInput',
                            'autocomplete' => 'off'
                        ]) ?>
                        <div id="lrnFeedback" class="mb-3" style="font-size: 0.85rem; font-weight: 500; min-height: 20px;"></div>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('name') ?>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <?= $this->Form->control('grade') ?>
                    </div>
                    <div class="col-md-6">
                        <?= $this->Form->control('section') ?>
                    </div>
                </div>

                <?= $this->Form->control('remarks', ['type' => 'textarea']) ?>

                <div class="mt-3 d-flex gap-2">
                    <?= $this->Form->button(__('Save'), [
                        'class' => 'btn btn-success', 
                        'id' => 'saveStudentBtn'
                    ]) ?>
                    <?= $this->Html->link(__('Cancel'), ['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher'], ['class' => 'btn btn-secondary']) ?>
                </div>

                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Run this logic immediately when the modal/page content loads
    (function() {
        var lrnInput = document.getElementById('lrnInput');
        var feedback = document.getElementById('lrnFeedback');
        var saveBtn = document.getElementById('saveStudentBtn');

        function validateLRN() {
            var value = lrnInput.value;
            // Remove non-digits
            var cleanValue = value.replace(/\D/g, '');
            
            // Update input value if non-digits were found
            if (value !== cleanValue) {
                lrnInput.value = cleanValue;
            }

            var length = cleanValue.length;

            if (length === 0) {
                // Empty state
                feedback.textContent = '';
                feedback.className = 'mb-3';
                saveBtn.disabled = false; // Optional: let backend handle empty if required, or disable
            } else if (length < 12) {
                // Too short - RED
                feedback.textContent = 'LRN must be 12 numbers (' + length + '/12)';
                feedback.className = 'mb-3 text-danger'; // Bootstrap red
                saveBtn.disabled = true; // Block saving
            } else if (length === 12) {
                // Correct - GREEN
                feedback.textContent = 'LRN is valid';
                feedback.className = 'mb-3 text-success'; // Bootstrap green
                saveBtn.disabled = false; // Allow saving
            }
        }

        if (lrnInput) {
            // Check immediately (in case editing existing data)
            validateLRN();

            // Check on every keystroke
            lrnInput.addEventListener('input', validateLRN);
        }
    })();
</script>