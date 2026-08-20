<!-- TABLE -->
<div class="row">
    <div class="col-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title mb-0">Students</h4>
                    <div>
                        <?php $addUrl = $this->Url->build(['controller' => 'Dashboard', 'action' => 'addStudent', 'prefix' => 'Teacher']); ?>
                        <?= $this->Html->link(__('Add Student'), '#', ['class' => 'btn btn-primary btn-add-student', 'data-no-ajax' => 'true', 'data-href' => $addUrl]) ?>
                    </div>
                </div>

                <!-- Bulk Actions Bar -->
                <div class="bulk-actions-bar mb-3" style="display: none;">
                    <div class="d-flex flex-wrap align-items-center gap-3 p-3 bg-light rounded border">
                        <span class="selected-count fw-bold text-primary" style="min-width: 80px;">0 selected</span>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-danger bulk-delete-students">
                                <i class="mdi mdi-delete"></i> Delete
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary bulk-deselect">
                                <i class="mdi mdi-close"></i> Clear
                            </button>
                        </div>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2 ms-sm-auto">
                            <button type="button" class="btn btn-sm btn-success" id="printStudents">
                                <i class="mdi mdi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                <table class="table table-striped table-hover defaultDataTable" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="no-sort">
                                <input type="checkbox" class="form-check-input" id="selectAllStudents">
                            </th>
                            <th>LRN (Learner Reference Number)</th>
                            <th>Name</th>
                            <th>Grade / Section</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($students as $student) { ?>
                            <tr data-id="<?= $this->Encrypt->hex($student->id) ?>" data-lrn="<?= h($student->lrn) ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input student-checkbox" value="<?= $this->Encrypt->hex($student->id) ?>">
                                </td>
                                <td class="fw-bold"><?= $student->lrn ?></td>
                                <td><?= h($student->name) ?></td>
                                <td><?= h($student->grade_section) ?></td>
                                <td class="text-center" style="white-space:nowrap">
                                                    <?= $this->Html->link('<i class="mdi mdi-eye-outline"></i>', ['controller' => 'Dashboard', 'action' => 'student', 'prefix' => 'Teacher', $this->Encrypt->hex($student->id)], ['escape' => false, 'class' => 'btn btn-sm btn-outline-secondary btn-view-student', 'title' => 'View']) ?>
                                                    <?php $editUrl = $this->Url->build(['controller' => 'Dashboard', 'action' => 'editStudent', 'prefix' => 'Teacher', $this->Encrypt->hex($student->id)]); ?>
                                                    <?= $this->Html->link('<i class="mdi mdi-pencil"></i>', '#', ['escape' => false, 'class' => 'btn btn-sm btn-outline-primary btn-edit-student', 'title' => 'Edit', 'data-no-ajax' => 'true', 'data-href' => $editUrl]) ?>
                                                    <?= $this->Html->link('<i class="mdi mdi-delete"></i>', '#', ['escape' => false, 'class' => 'btn btn-sm btn-outline-danger btn-delete-student', 'data-url' => $this->Url->build(['controller' => 'Dashboard', 'action' => 'deleteStudent', 'prefix' => 'Teacher', $this->Encrypt->hex($student->id)]), 'data-name' => h($student->name), 'title' => 'Delete']) ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $this->start('script'); ?>
<script>
(function(){
    window.initBulkActionsStudents = function() {
        if (!window.jQuery) {
            return;
        }
        var $ = window.jQuery;
        // Only run on Students page
        if (!$('.student-checkbox').length && !$('#selectAllStudents').length) {
            return;
        }
        console.info('[Students] initBulkActionsStudents called');
            
            // Small helper to escape HTML to avoid XSS when inserting values as HTML
            function escapeHtml(str) {
                return String(str === undefined || str === null ? '' : str)
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            // Helper to show/hide modal. Uses Bootstrap 5 if available, otherwise falls back to a simple class toggle.
            function showModal() {
                var $modal = $('#studentModal');
                if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                    var m = new bootstrap.Modal($modal[0]);
                    m.show();
                    $modal.data('bs.instance', m);
                } else {
                    $modal.addClass('show').css('display', 'block');
                    if ($('.modal-backdrop').length === 0) {
                        $('<div class="modal-backdrop fade show"></div>').appendTo(document.body);
                    }
                }
            }

            function hideModal() {
                var $modal = $('#studentModal');
                var inst = $modal.data('bs.instance');
                if (inst && typeof inst.hide === 'function') {
                    inst.hide();
                } else {
                    $modal.removeClass('show').css('display', 'none');
                    $('.modal-backdrop').remove();
                }
            }

            // Open form in modal (Add/Edit)
            function openFormModal(url, title) {
                $('#studentModal .modal-title').text(title);
                $('#studentModal .modal-body').html('<div class="text-center">Loading...</div>');
                showModal();
                // Use a robust AJAX GET with explicit X-Requested-With header and improved error handling
                $.ajax({
                    url: url,
                    method: 'GET',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    cache: false
                }).done(function(html, textStatus, jqXHR){
                    // Detect if the server returned the login page (session expired). If so, do a full reload
                    try {
                        if (typeof html === 'string' && /<form[^>]+action=["'][^"']*\/users?\/login["']/i.test(html)) {
                            // Session expired or login returned — reload to trigger top-level redirect to login page
                            window.location.reload();
                            return;
                        }
                    } catch (err) {
                        // ignore detection errors and proceed to inject
                    }

                    $('#studentModal .modal-body').html(html);
                    // attach form submit handler with client-side validation
                    $('#studentModal').find('form').on('submit', function(e){
                        e.preventDefault();
                        var $form = $(this);
                        // clear previous validation
                        $form.find('.is-invalid').removeClass('is-invalid');
                        $form.find('.invalid-feedback').addClass('d-none').text('');

                        // simple client-side validation
                        var errors = {};
                        var lrn = $.trim($form.find('[name="lrn"]').val() || '');
                        var name = $.trim($form.find('[name="name"]').val() || '');
                        var grade = $.trim($form.find('[name="grade"]').val() || '');
                        var section = $.trim($form.find('[name="section"]').val() || '');
                        
                        console.log('Validation - LRN:', lrn, 'Name:', name, 'Grade:', grade, 'Section:', section);
                        
                        if (!/^[0-9]{12}$/.test(lrn)) { errors.lrn = ['LRN must be a 12-digit number']; }
                        if (!name) { errors.name = ['Name is required']; }
                        if (!grade) { 
                            errors.grade = ['Grade is required']; 
                        } else {
                            var gradeNum = parseInt(grade, 10);
                            console.log('Grade validation - input:', grade, 'parsed:', gradeNum, 'isNaN:', isNaN(gradeNum));
                            if (isNaN(gradeNum) || gradeNum < 1 || gradeNum > 6) { 
                                errors.grade = ['Grade must be between 1 and 6']; 
                            }
                        }
                        if (!section) { errors.section = ['Section is required']; }
                        
                        console.log('Validation errors:', errors);

                        if (Object.keys(errors).length) {
                            // display client-side errors
                            $.each(errors, function(field, msgs){
                                var $input = $form.find('[name="' + field + '"]');
                                $input.addClass('is-invalid');
                                $form.find('.invalid-feedback[data-field="' + field + '"]').removeClass('d-none').text(msgs.join(', '));
                            });
                            return;
                        }

                        var method = ($form.attr('method') || 'POST').toUpperCase();
                        var csrf = $('meta[name=csrfToken]').attr('content') || '';
                        console.log('Submitting student form:', {url: $form.attr('action'), method: method, data: $form.serialize()});
                        $.ajax({
                            url: $form.attr('action'),
                            method: method,
                            data: $form.serialize(),
                            dataType: 'json',
                            headers: { 'X-CSRF-Token': csrf }
                        }).done(function(res){
                            console.log('Server response:', res);
                            if (res && res.success) {
                                // Show success message, then refresh the page
                                console.log('[Students] Save successful, will reload page');
                                if (window.Swal && typeof Swal.fire === 'function') {
                                    Swal.fire({icon: 'success', title: 'Success', text: res.message || 'Saved'})
                                        .then(function(){
                                            console.log('[Students] SweetAlert closed, hiding modal and reloading...');
                                            hideModal();
                                            // Force reload - use true to bypass cache
                                            window.location.reload(true);
                                        });
                                } else {
                                    alert(res.message || 'Saved');
                                    hideModal();
                                    window.location.reload(true);
                                }
                            } else {
                                // Check for duplicate LRN error specifically
                                var lrnValue = $form.find('[name="lrn"]').val() || '';
                                var isDuplicateLrn = res && res.errors && res.errors.lrn;
                                
                                if (isDuplicateLrn) {
                                    // Show SweetAlert for duplicate LRN
                                    var lrnErrorMsg = 'The LRN "' + lrnValue + '" is already assigned to another student. Please use a different LRN.';
                                    if (window.Swal && typeof Swal.fire === 'function') {
                                        Swal.fire({
                                            icon: 'warning',
                                            title: 'Duplicate LRN',
                                            text: lrnErrorMsg
                                        });
                                    } else {
                                        alert(lrnErrorMsg);
                                    }
                                    // Also highlight the LRN field
                                    var $lrnInput = $form.find('[name="lrn"]');
                                    $lrnInput.addClass('is-invalid');
                                    var $lrnFb = $('#studentModal').find('.invalid-feedback[data-field="lrn"]');
                                    $lrnFb.removeClass('d-none').text('This LRN is already in use.');
                                } else if (res && res.errors) {
                                    // Show other field errors
                                    $.each(res.errors, function(field, fieldErrs){
                                        var $input = $('#studentModal').find('[name="' + field + '"]');
                                        $input.addClass('is-invalid');
                                        var $fb = $('#studentModal').find('.invalid-feedback[data-field="' + field + '"]');
                                        $fb.removeClass('d-none').text(Object.values(fieldErrs).map(function(v){ return v.join ? v.join(', ') : v; }).join(', '));
                                    });
                                    // Show a general error SweetAlert
                                    var msg = (res && res.message) ? res.message : 'Please check the form for errors.';
                                    if (window.Swal && typeof Swal.fire === 'function') {
                                        Swal.fire({icon:'error', title:'Error', text: msg});
                                    } else {
                                        alert(msg);
                                    }
                                } else {
                                    var msg = (res && res.message) ? res.message : 'Please check the form for errors.';
                                    if (window.Swal && typeof Swal.fire === 'function') {
                                        Swal.fire({icon:'error', title:'Error', text: msg});
                                    } else {
                                        alert(msg);
                                    }
                                }
                            }
                        }).fail(function(jqXHR, textStatus, errorThrown){
                            console.error('Student save AJAX failed', {status: jqXHR.status, textStatus: textStatus, error: errorThrown, response: jqXHR.responseText});
                            console.error('Request details:', {url: $form.attr('action'), method: method, data: $form.serialize()});
                            var msg = 'Server error: ' + textStatus;
                            // Try to extract a helpful message from JSON response
                            try {
                                var json = jqXHR.responseJSON || JSON.parse(jqXHR.responseText || '{}');
                                if (json && json.message) { msg = json.message; }
                            } catch (e) {
                                // ignore parse errors
                                if (jqXHR.responseText && jqXHR.responseText.length < 200) {
                                    msg = 'Server error: ' + jqXHR.responseText;
                                }
                            }
                            if (window.Swal && typeof Swal.fire === 'function') {
                                Swal.fire({icon:'error', title:'Error', text: msg});
                            } else {
                                alert(msg);
                            }
                        });
                    });
                    }).fail(function(jqXHR, textStatus, errorThrown){
                        console.error('Failed to load student form', {url: url, status: jqXHR.status, textStatus: textStatus, error: errorThrown});
                        var response = jqXHR.responseText || '';
                        // If the server returned HTML (e.g. login page or error), show it inside the modal for debugging.
                        if (response && response.length > 50) {
                            $('#studentModal .modal-body').html(response);
                        } else {
                            $('#studentModal .modal-body').html('<div class="text-danger text-center">Failed to load form. (' + jqXHR.status + ')</div>');
                        }
                    });
            }

            // Add student (use delegated handler)
            $(document).on('click', '.btn-add-student', function(e){
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                // prefer data-href (used to avoid navigation before JS attaches), fall back to href when valid
                var rawHref = $(this).data('href') || $(this).attr('href') || '';
                var href = (rawHref && rawHref !== '#' && rawHref !== 'javascript:void(0)') ? rawHref : $(this).data('href');
                openFormModal(href, 'Add Student');
            });

            // Edit student
            $(document).on('click', '.btn-edit-student', function(e){
                e.preventDefault();
                e.stopImmediatePropagation();
                e.stopPropagation();
                // prefer data-href (used to avoid navigation before JS attaches), fall back to href when valid
                var rawHref = $(this).data('href') || $(this).attr('href') || '';
                var href = (rawHref && rawHref !== '#' && rawHref !== 'javascript:void(0)') ? rawHref : $(this).data('href');
                openFormModal(href, 'Edit Student');
            });

            // helper to generate action buttons HTML for a student object (expects encrypted id in s.id)
            function generateActionButtonsHtml(s) {
                var viewUrl = (typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : window.location.origin + '/') + 'teacher/dashboard/student/' + s.id;
                var editUrl = (typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : window.location.origin + '/') + 'teacher/dashboard/editStudent/' + s.id;
                var deleteUrl = (typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : window.location.origin + '/') + 'teacher/dashboard/deleteStudent/' + s.id;
                var escapedName = $('<div>').text(s.name).html();
                var html = '';
                html += '<a class="btn btn-sm btn-outline-secondary btn-view-student" href="' + viewUrl + '" title="View"><i class="mdi mdi-eye-outline"></i></a> ';
                html += '<a class="btn btn-sm btn-outline-primary btn-edit-student" href="' + editUrl + '" title="Edit" data-no-ajax="true"><i class="mdi mdi-pencil"></i></a> ';
                html += '<a class="btn btn-sm btn-outline-danger btn-delete-student" href="#" data-url="' + deleteUrl + '" data-name="' + escapedName + '" title="Delete"><i class="mdi mdi-delete"></i></a>';
                return html;
            }

            // Delete student via AJAX with SweetAlert confirmation
            $(document).on('click', '.btn-delete-student', function(e){
                e.preventDefault();
                var $btn = $(this);
                var url = $btn.data('url') || $btn.attr('href');
                var name = $btn.data('name') || 'this student';
                var rowId = $btn.closest('tr').data('id');
                // Use Swal if available, otherwise fallback to native confirm()
                function doDelete() {
                    $.post(url, {_csrfToken: $('meta[name=csrfToken]').attr('content')}).done(function(res){
                        if (res && res.success) {
                            if (dt && rowId) {
                                var row = $('.defaultDataTable tbody tr[data-id="' + rowId + '"]');
                                if (row.length) { dt.row(row[0]).remove().draw(false); }
                            } else {
                                location.reload();
                            }
                            if (window.Swal && typeof Swal.fire === 'function') {
                                Swal.fire({icon:'success', title:'Deleted', text: res.message || 'Deleted'});
                            } else {
                                alert(res.message || 'Deleted');
                            }
                        } else {
                            var err = (res && res.message) ? res.message : 'Could not delete.';
                            if (window.Swal && typeof Swal.fire === 'function') {
                                Swal.fire({icon:'error', title:'Error', text: err});
                            } else {
                                alert(err);
                            }
                        }
                    }).fail(function(){
                        if (window.Swal && typeof Swal.fire === 'function') {
                            Swal.fire({icon:'error', title:'Error', text:'Server error'});
                        } else {
                            alert('Server error');
                        }
                    });
                }

                if (window.Swal && typeof Swal.fire === 'function') {
                    Swal.fire({
                        title: 'Delete? ',
                        text: 'Are you sure you want to delete ' + name + '?',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete it',
                        cancelButtonText: 'Cancel'
                    }).then(function(result){
                        if (result.isConfirmed) { doDelete(); }
                    });
                } else {
                    if (confirm('Are you sure you want to delete ' + name + '?')) { doDelete(); }
                }
            });

            // Close modal handler for elements using data-bs-dismiss
            $(document).on('click', '[data-bs-dismiss="modal"]', function(e){
                e.preventDefault();
                hideModal();
            });

            function tableApi() {
                if ($.fn.DataTable && $.fn.DataTable.isDataTable('.defaultDataTable')) {
                    return $('.defaultDataTable').DataTable();
                }
                return null;
            }

            function visibleCheckboxes() {
                var dt = tableApi();
                if (dt) {
                    return $(dt.rows({ page: 'current' }).nodes()).find('.student-checkbox');
                }
                return $('.student-checkbox');
            }

            function updateBulkActionsBar() {
                var selectedCount = $('.student-checkbox:checked').length;
                if (selectedCount > 0) {
                    $('.bulk-actions-bar').show();
                    $('.selected-count').text(selectedCount + ' selected');
                } else {
                    $('.bulk-actions-bar').hide();
                }
            }

            // Prevent DataTables header click from sorting when toggling select-all / row checkboxes
            // This needs to stop propagation at multiple levels to prevent DataTables sorting
            $(document).off('click.bulkstop', '#selectAllStudents, .student-checkbox').on('click.bulkstop', '#selectAllStudents, .student-checkbox', function(e) {
                e.stopPropagation();
                e.stopImmediatePropagation();
            });
            
            // Also attach handler to the header cell containing the select-all checkbox
            $(document).off('click.bulkstop2', 'th.no-sort').on('click.bulkstop2', 'th.no-sort', function(e) {
                // Only prevent sorting if the click was on or near the checkbox
                var $target = $(e.target);
                if ($target.is('input[type="checkbox"]') || $target.closest('th').find('#selectAllStudents').length) {
                    e.stopPropagation();
                    e.stopImmediatePropagation();
                    // If clicked on the cell but not the checkbox itself, toggle the checkbox
                    if (!$target.is('input[type="checkbox"]')) {
                        var $cb = $(this).find('#selectAllStudents');
                        if ($cb.length) {
                            $cb.prop('checked', !$cb.prop('checked')).trigger('change');
                        }
                    }
                }
            });

            // Select All checkbox - use event delegation (attach once, works forever)
            $(document).off('change.bulk', '#selectAllStudents').on('change.bulk', '#selectAllStudents', function() {
                console.info('[Students] Select All triggered, checked:', $(this).prop('checked'));
                var isChecked = $(this).prop('checked');
                var $checkboxes = visibleCheckboxes();
                console.info('[Students] Found', $checkboxes.length, 'visible checkboxes');
                $checkboxes.prop('checked', isChecked);
                updateBulkActionsBar();
            });

            // Individual checkbox - use event delegation
            $(document).off('change.bulk', '.student-checkbox').on('change.bulk', '.student-checkbox', function() {
                var $visible = visibleCheckboxes();
                var totalCheckboxes = $visible.length;
                var checkedCheckboxes = $visible.filter(':checked').length;
                $('#selectAllStudents').prop('checked', totalCheckboxes === checkedCheckboxes);
                updateBulkActionsBar();
            });

            // Clear selection - use event delegation
            $(document).off('click.bulk', '.bulk-deselect').on('click.bulk', '.bulk-deselect', function() {
                $('.student-checkbox, #selectAllStudents').prop('checked', false);
                updateBulkActionsBar();
            });

            // Keep header checkbox in sync after pagination/sort
            function ensureDataTableSync(attempts) {
                attempts = attempts || 0;
                console.info('[Students] ensureDataTableSync attempt', attempts);
                var dtSync = tableApi();
                if (dtSync) {
                    console.info('[Students] DataTable found, attaching draw handler');
                    dtSync.off('draw.bulk').on('draw.bulk', function() {
                        var $visible = visibleCheckboxes();
                        var totalCheckboxes = $visible.length;
                        var checkedCheckboxes = $visible.filter(':checked').length;
                        $('#selectAllStudents').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                    });
                    // Immediate sync on attach
                    var $visible = visibleCheckboxes();
                    var totalCheckboxes = $visible.length;
                    var checkedCheckboxes = $visible.filter(':checked').length;
                    console.info('[Students] Initial sync: visible=', totalCheckboxes, 'checked=', checkedCheckboxes);
                    $('#selectAllStudents').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                    updateBulkActionsBar();
                    return;
                }
                if (attempts < 20) {
                    setTimeout(function(){ ensureDataTableSync(attempts+1); }, 100);
                } else {
                    console.warn('[Students] DataTable not found after 20 attempts');
                }
            }

            ensureDataTableSync();

            // Re-sync after AJAX page loads
            $(document).off('genta:page-ready.students').on('genta:page-ready.students', function(){
                ensureDataTableSync(0);
            });

            // Bulk Delete
            $('.bulk-delete-students').on('click', function() {
                var selectedIds = $('.student-checkbox:checked').map(function() {
                    return $(this).val();
                }).get();

                if (selectedIds.length === 0) return;

                var confirmText = 'Are you sure you want to delete ' + selectedIds.length + ' student(s)?';
                
                if (window.Swal && typeof Swal.fire === 'function') {
                    Swal.fire({
                        title: 'Delete Students?',
                        text: confirmText,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, delete them',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#d33'
                    }).then(function(result) {
                        if (result.isConfirmed) {
                            performBulkDelete(selectedIds);
                        }
                    });
                } else {
                    if (confirm(confirmText)) {
                        performBulkDelete(selectedIds);
                    }
                }
            });

            function performBulkDelete(selectedIds) {
                var csrf = $('meta[name=csrfToken]').attr('content') || '';
                var deletePromises = selectedIds.map(function(id) {
                    var deleteUrl = '<?= $this->Url->build(['controller' => 'Dashboard', 'action' => 'deleteStudent', 'prefix' => 'Teacher', '__ID__']) ?>'.replace('__ID__', id);
                    return $.ajax({
                        url: deleteUrl,
                        method: 'POST',
                        headers: { 'X-CSRF-Token': csrf },
                        dataType: 'json'
                    });
                });

                Promise.all(deletePromises).then(function(responses) {
                    var successCount = responses.filter(function(r) { return r && r.success; }).length;
                    
                    if (window.Swal && typeof Swal.fire === 'function') {
                        Swal.fire({
                            title: 'Deleted!',
                            text: successCount + ' student(s) have been deleted.',
                            icon: 'success'
                        }).then(function() {
                            window.location.reload();
                        });
                    } else {
                        alert(successCount + ' student(s) have been deleted.');
                        window.location.reload();
                    }
                }).catch(function(error) {
                    console.error('Bulk delete error:', error);
                    if (window.Swal && typeof Swal.fire === 'function') {
                        Swal.fire('Error', 'Failed to delete some students.', 'error');
                    } else {
                        alert('Failed to delete some students.');
                    }
                });
            }

            // Print Functionality - use event delegation
            $(document).on('click', '#printStudents', function() {
                var printContent = generateStudentsPrintContent();
                var printWindow = window.open('', '_blank', 'width=800,height=600');
                printWindow.document.write(printContent);
                printWindow.document.close();
                printWindow.focus();
                setTimeout(function() {
                    printWindow.print();
                    printWindow.close();
                }, 250);
            });

            // Export CSV - use event delegation
            $(document).on('click', '#exportStudentsCSV', function() {
                exportStudentsToCSV();
            });

            // Export Excel (HTML table format) - use event delegation
            $(document).on('click', '#exportStudentsExcel', function() {
                exportStudentsToExcel();
            });

            function getStudentsData() {
                var data = [];
                var checkedOnly = $('.student-checkbox:checked').length > 0;
                var selector = checkedOnly ? '.student-checkbox:checked' : '.student-checkbox';
                
                $(selector).each(function() {
                    var $row = $(this).closest('tr');
                    data.push({
                        lrn: $row.find('td:eq(1)').text().trim(),
                        name: $row.find('td:eq(2)').text().trim(),
                        gradeSection: $row.find('td:eq(3)').text().trim()
                    });
                });
                return data;
            }

            function exportStudentsToCSV() {
                var data = getStudentsData();
                var csv = 'LRN,Name,Grade/Section\n';
                data.forEach(function(row) {
                    csv += '"' + row.lrn.replace(/"/g, '""') + '","' + 
                           row.name.replace(/"/g, '""') + '","' + 
                           row.gradeSection.replace(/"/g, '""') + '"\n';
                });
                
                var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'students_report_' + new Date().getTime() + '.csv');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function exportStudentsToExcel() {
                var data = getStudentsData();
                var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
                html += '<head><meta charset="utf-8"><style>table { border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; } th { background-color: #4B49AC; color: white; }</style></head>';
                html += '<body><h2>Students Report</h2><p>Generated: ' + new Date().toLocaleString() + '</p>';
                html += '<table><thead><tr><th>LRN</th><th>Name</th><th>Grade/Section</th></tr></thead><tbody>';
                data.forEach(function(row) {
                    html += '<tr><td>' + escapeHtml(row.lrn) + '</td><td>' + escapeHtml(row.name) + '</td><td>' + escapeHtml(row.gradeSection) + '</td></tr>';
                });
                html += '</tbody></table></body></html>';
                
                var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
                var link = document.createElement('a');
                var url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', 'students_report_' + new Date().getTime() + '.xls');
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function generateStudentsPrintContent() {
                var today = new Date().toLocaleDateString();
                var rows = '';
                var checkedOnly = $('.student-checkbox:checked').length > 0;
                
                var selector = checkedOnly ? '.student-checkbox:checked' : '.student-checkbox';
                $(selector).each(function() {
                    var $row = $(this).closest('tr');
                    var lrn = $row.find('td:eq(1)').text();
                    var name = $row.find('td:eq(2)').text();
                    var gradeSection = $row.find('td:eq(3)').text();
                    rows += '<tr><td>' + escapeHtml(lrn) + '</td><td>' + escapeHtml(name) + '</td><td>' + escapeHtml(gradeSection) + '</td></tr>';
                });

                return `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Students Report</title>
                        <style>
                            body { font-family: Arial, sans-serif; margin: 20px; }
                            h1 { text-align: center; color: #333; }
                            .header { text-align: center; margin-bottom: 20px; }
                            .date { text-align: right; margin-bottom: 10px; font-size: 12px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                            th { background-color: #4B49AC; color: white; padding: 10px; text-align: left; border: 1px solid #ddd; }
                            td { padding: 8px; border: 1px solid #ddd; }
                            tr:nth-child(even) { background-color: #f9f9f9; }
                            .footer { margin-top: 30px; font-size: 12px; text-align: center; color: #666; }
                            @media print {
                                body { margin: 0; }
                                button { display: none; }
                            }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h1>Students Report</h1>
                            <p>GENTA Learning Management System</p>
                        </div>
                        <div class="date">Generated: ${today}</div>
                        <table>
                            <thead>
                                <tr>
                                    <th>LRN (Learner Reference Number)</th>
                                    <th>Name</th>
                                    <th>Grade / Section</th>
                                </tr>
                            </thead>
                            <tbody>
                                ${rows}
                            </tbody>
                        </table>
                        <div class="footer">
                            <p>© ${new Date().getFullYear()} GENTA - Department of Education</p>
                        </div>
                    </body>
                    </html>
                `;
            }

            // Auto-open modal when redirected here with query params: ?open=add or ?open=edit&id=<hash>
            (function(){
                try {
                    var params = new URLSearchParams(window.location.search || '');
                    var open = params.get('open');
                    if (!open) return;
                    if (open === 'add') {
                        // Open add modal
                        openFormModal((typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : window.location.origin + '/') + 'teacher/dashboard/addStudent', 'Add Student');
                        // remove query params to avoid reopening on refresh
                        history.replaceState(null, '', window.location.pathname);
                    } else if (open === 'edit') {
                        var id = params.get('id');
                        if (id) {
                            openFormModal((typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : window.location.origin + '/') + 'teacher/dashboard/editStudent/' + id, 'Edit Student');
                            history.replaceState(null, '', window.location.pathname);
                        }
                    }
                } catch (e) {
                    // ignore
                }
            })();
    };

    // Run on initial page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function() {
            // Small delay to ensure DataTables has finished initializing before we attach our handlers
            setTimeout(window.initBulkActionsStudents, 50);
        });
    } else {
        // Small delay to ensure DataTables has finished initializing before we attach our handlers
        setTimeout(window.initBulkActionsStudents, 50);
    }
})();
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (localStorage.getItem('genta_student_added') === 'true') {
        localStorage.removeItem('genta_student_added');
        if (window.Swal) {
            Swal.fire({
                icon: 'success',
                title: 'Student Added!',
                text: 'The list has been updated.',
                timer: 3000,
                showConfirmButton: false
            });
        }
    }
});
</script>
<?php $this->end(); ?>