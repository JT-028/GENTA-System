<div class="row">
    <div class="col-12">
        <h4 class="mb-3">Edit MELC <?= $melc->id ? '#'.h($melc->id) : '' ?></h4>

        <?= $this->Form->create($melc, ['type' => 'post']) ?>
            <div class="form-group mb-3">
                <label for="description">Description <span class="text-danger">*</span></label>
                <?= $this->Form->textarea('description', ['class' => 'form-control' . ($melc->hasErrors('description') ? ' is-invalid' : ''), 'rows' => 4, 'required' => true]) ?>
                <?php if ($melc->hasErrors('description')): ?>
                    <div class="invalid-feedback d-block"><?= implode(', ', $melc->getError('description')) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-group mb-3">
                <label for="subject_id">Subject</label>
                <?= $this->Form->control('subject_id', ['type' => 'select', 'options' => $subjectOptions ?? [], 'empty' => '--- Select ---', 'class' => 'form-select' . ($melc->hasErrors('subject_id') ? ' is-invalid' : '')]) ?>
                <?php if ($melc->hasErrors('subject_id')): ?>
                    <div class="invalid-feedback d-block"><?= implode(', ', $melc->getError('subject_id')) ?></div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <?= $this->Form->button('Save Changes', ['class' => 'btn btn-gradient-primary']) ?>
                <?= $this->Html->link('Cancel', ['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'index'], ['class' => 'btn btn-outline-secondary']) ?>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>
