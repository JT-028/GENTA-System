<?php
/** @var \\App\\View\\AppView $this */
/** @var \\App\\Model\\Entity\\Student $student */
?>

<div class="row">
    <div class="col-8 mx-auto">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title"><?= __('Edit Student') ?></h4>

                <?= $this->Form->create($student) ?>

                <div class="row">
                    <div class="col-md-6">
                        <?= $this->Form->control('lrn', ['label' => 'LRN (Learner Reference Number)', 'maxlength' => 12, 'pattern' => '\\d{12}', 'placeholder' => 'e.g. 123456789012']) ?>
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
                    <?= $this->Form->button(__('Save Changes'), ['class' => 'btn btn-primary']) ?>
                    <?= $this->Html->link(__('Cancel'), ['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher'], ['class' => 'btn btn-secondary']) ?>
                </div>

                <?= $this->Form->end() ?>
            </div>
        </div>
    </div>
</div>
