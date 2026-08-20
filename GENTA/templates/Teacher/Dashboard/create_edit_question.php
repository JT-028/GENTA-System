<!-- QUESTION FORM -->
<div class="row">
    <!-- QUESTION DATA -->
    <div class="col-12">
        <h4 class="mb-4">Question <?= $question->id ? '#' . $question->id : '' ?></h4>

        <?= $this->Form->create($question, ['type' => 'post']) ?>
            <div class="form-group mb-3">
                <label for="subject_id">Subject <span class="text-danger">*</span></label>
                <?= $this->Form->select('subject_id', $subjectOptions, [
                    'class' => 'form-select' . ($question->hasErrors('subject_id') ? ' is-invalid' : ''), 
                    'id' => 'subject_id', 
                    'empty' => '--- Please Select ---', 
                    'required' => true
                ]) ?>
                <?php if ($question->hasErrors('subject_id')): ?>
                    <div class="invalid-feedback d-block">
                        <?= implode(', ', $question->getError('subject_id')) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group mb-3">
                <label for="description">Question <span class="text-danger">*</span></label>
                <?= $this->Form->textarea('description', [
                    'class' => 'form-control' . ($question->hasErrors('description') ? ' is-invalid' : ''), 
                    'id' => 'description', 
                    'rows' => 3,
                    'required' => true,
                    'onkeyup' => 'this.value = this.value.replace(/^\s+/, "");',
                    'onblur' => 'if (this.value.trim() === "") { this.value = ""; }'
                ]) ?>
                <?php if ($question->hasErrors('description')): ?>
                    <div class="invalid-feedback d-block">
                        <?= implode(', ', $question->getError('description')) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group mb-3">
                <label for="choices">Choices (Comma Separated) <span class="text-danger">*</span></label>
                <?= $this->Form->textarea('choices', [
                    'class' => 'form-control' . ($question->hasErrors('choices') ? ' is-invalid' : ''), 
                    'id' => 'choices', 
                    'value' => $question->has('choices_string') ? $question->choices_string : '',
                    'rows' => 2,
                    'placeholder' => 'e.g., Option A, Option B, Option C',
                    'required' => true
                ]) ?>
                <?php if ($question->hasErrors('choices')): ?>
                    <div class="invalid-feedback d-block">
                        <?= implode(', ', $question->getError('choices')) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="form-group mb-3">
                <label for="answer">Correct Answer <span class="text-danger">*</span></label>
                <?= $this->Form->text('answer', [
                    'class' => 'form-control' . ($question->hasErrors('answer') ? ' is-invalid' : ''), 
                    'id' => 'answer', 
                    'required' => true
                ]) ?>
                <?php if ($question->hasErrors('answer')): ?>
                    <div class="invalid-feedback d-block">
                        <?= implode(', ', $question->getError('answer')) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2">
                <?= $this->Form->button('Save Question', ['class' => 'btn btn-gradient-primary', 'type' => 'submit']) ?>
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            </div>
        <?= $this->Form->end() ?>
    </div>
</div>