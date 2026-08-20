<?php
/** @var \App\View\AppView $this */
/** @var \App\Model\Entity\Student $student */
?>

<div class="student-form-container">
    <?= $this->Form->create($student, [
        'id' => 'studentForm', 
        'novalidate' => true
        // Removed 'onsubmit' here to avoid conflicts. We handle it in the button now.
    ]) ?>

    <script>
        // 1. VALIDATION LOGIC
        function checkGlobalValidation() {
            var lrn = document.getElementById('lrnInput').value.length;
            var name = document.getElementById('nameInput').value.trim();
            var grade = document.getElementById('gradeInput').value.trim();
            var section = document.getElementById('sectionInput').value.trim();
            var btn = document.getElementById('btnSaveStudent');

            // Rules: LRN=12 chars, others not empty
            if (lrn === 12 && name !== '' && grade !== '' && section !== '') {
                btn.disabled = false;
            } else {
                btn.disabled = true;
            }
        }

        // 2. SUBMIT LOGIC (The Fix)
        function submitFormManually() {
            // Set the flag so the NEXT page knows to show the success alert
            localStorage.setItem('genta_student_added', 'true');

            // Show Loading Alert
            if(window.Swal) {
                Swal.fire({
                    title: 'Saving Student...',
                    text: 'Please wait while the page refreshes.',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => { Swal.showLoading(); }
                });
            }

            // FORCE THE SUBMIT (This guarantees a page reload)
            document.getElementById('studentForm').submit();
        }
    </script>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label fw-bold">LRN <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="lrn" 
                    id="lrnInput" 
                    class="form-control" 
                    maxlength="12" 
                    placeholder="e.g. 123456789012" 
                    autocomplete="off"
                    value="<?= h($student->lrn) ?>" 
                    oninput="
                        this.value = this.value.replace(/[^0-9]/g, '');
                        var fb = document.getElementById('lrnFeedback');
                        
                        if (this.value.length === 0) {
                            fb.innerText = 'LRN is missing';
                            fb.className = 'small fw-bold text-danger mt-1';
                        } else if (this.value.length < 12) {
                            fb.innerText = 'LRN must be 12 numbers (' + this.value.length + '/12)';
                            fb.className = 'small fw-bold text-danger mt-1';
                        } else {
                            fb.innerText = 'LRN is valid';
                            fb.className = 'small fw-bold text-success mt-1';
                        }
                        checkGlobalValidation();
                    "
                >
                <div id="lrnFeedback" class="small fw-bold text-danger mt-1" style="min-height: 20px;">
                    <?= empty($student->lrn) ? 'LRN is missing' : '' ?>
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label fw-bold">Name <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="name" 
                    id="nameInput" 
                    class="form-control" 
                    placeholder="Full Name"
                    value="<?= h($student->name) ?>"
                    oninput="
                        var fb = document.getElementById('nameFeedback');
                        if (this.value.trim() === '') {
                            fb.innerText = 'Name is missing';
                        } else {
                            fb.innerText = '';
                        }
                        checkGlobalValidation();
                    "
                >
                <div id="nameFeedback" class="small fw-bold text-danger mt-1" style="min-height: 20px;">
                    <?= empty($student->name) ? 'Name is missing' : '' ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label fw-bold">Grade <span class="text-danger">*</span></label>
                <input 
                    type="number" 
                    name="grade" 
                    id="gradeInput" 
                    class="form-control" 
                    min="1" 
                    max="6" 
                    placeholder="1-6"
                    value="<?= h($student->grade) ?>"
                    oninput="
                        if (this.value > 6) this.value = 6;
                        if (this.value < 1 && this.value !== '') this.value = '';

                        var fb = document.getElementById('gradeFeedback');
                        if (this.value.trim() === '') {
                            fb.innerText = 'Grade is missing';
                        } else {
                            fb.innerText = '';
                        }
                        checkGlobalValidation();
                    "
                >
                <div id="gradeFeedback" class="small fw-bold text-danger mt-1" style="min-height: 20px;">
                    <?= empty($student->grade) ? 'Grade is missing' : '' ?>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="mb-3">
                <label class="form-label fw-bold">Section <span class="text-danger">*</span></label>
                <input 
                    type="text" 
                    name="section" 
                    id="sectionInput" 
                    class="form-control" 
                    placeholder="Section Name"
                    value="<?= h($student->section) ?>"
                    oninput="
                        var fb = document.getElementById('sectionFeedback');
                        if (this.value.trim() === '') {
                            fb.innerText = 'Section is missing';
                        } else {
                            fb.innerText = '';
                        }
                        checkGlobalValidation();
                    "
                >
                <div id="sectionFeedback" class="small fw-bold text-danger mt-1" style="min-height: 20px;">
                    <?= empty($student->section) ? 'Section is missing' : '' ?>
                </div>
            </div>
        </div>
    </div>

    <?= $this->Form->control('remarks', [
        'type' => 'textarea', 
        'class' => 'form-control',
        'label' => ['text' => 'Remarks', 'class' => 'fw-bold']
    ]) ?>

    <div class="mt-3 d-flex gap-2 justify-content-end">
        <?php $isDisabled = empty($student->lrn) || empty($student->name) || empty($student->grade) || empty($student->section); ?>
        
        <button 
            type="button" 
            class="btn btn-primary" 
            id="btnSaveStudent" 
            onclick="submitFormManually()" 
            <?= $isDisabled ? 'disabled' : '' ?>
        >
            Save
        </button>
        
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
    </div>

    <?= $this->Form->end() ?>
</div>