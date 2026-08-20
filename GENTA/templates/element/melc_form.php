<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Melc $melc */
?>

<div class="melc-form-container">
    <?= $this->Form->create($melc, ['id' => 'melcForm', 'novalidate' => true]) ?>

    <div class="form-group mb-3">
        <?php
            // Use subjectOptions passed by controller when available. If not available,
            // fall back to a single Math option. Provide a neutral empty option so
            // the dropdown has no preselected subject when the modal opens.
            $opts = !empty($subjectOptions) ? $subjectOptions : [1 => 'Math'];
        ?>
        <?= $this->Form->control('subject_id', [
            'type' => 'select',
            'options' => $opts,
            // Do NOT include an empty option - require a specific subject selection
            'empty' => false,
            'label' => 'Subject',
            'class' => 'form-select' . ($melc->hasErrors('subject_id') ? ' is-invalid' : ''),
        ]) ?>
        <div class="invalid-feedback d-none" data-field="subject_id"></div>
    </div>

    <div class="form-group mb-3">
        <?= $this->Form->control('description', [
            'type' => 'textarea',
            'label' => 'Description',
            'class' => 'form-control' . ($melc->hasErrors('description') ? ' is-invalid' : ''),
            'rows' => 4,
            'required' => true,
        ]) ?>
        <div class="invalid-feedback d-none" data-field="description"></div>
    </div>

    <div class="mt-3 d-flex gap-2 justify-content-end">
        <?= $this->Form->button(__('Save'), ['class' => 'btn btn-primary']) ?>
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('Cancel') ?></button>
    </div>

    <?= $this->Form->end() ?>
</div>
