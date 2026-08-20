<!-- Page Header -->
<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h3 class="mb-1">Most Essential Competencies</h3>
                <p class="text-muted mb-0">These entries are used as training data for the GenAI pipeline.</p>
            </div>
            <div>
                <?php $addUrl = $this->Url->build(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'add']); ?>
                <?= $this->Html->link('Add MELC', '#', ['class' => 'btn btn-gradient-primary btn-add-melc', 'data-no-ajax' => 'true', 'data-href' => $addUrl]) ?>
            </div>
        </div>
    </div>
</div>

<!-- Export/Import Section -->
<div class="row mb-4">
    <div class="col-lg-6 grid-margin">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center mb-3">
                    <div class="icon-wrapper bg-primary-subtle rounded p-2 me-3">
                        <i class="mdi mdi-download text-primary" style="font-size: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Export Data</h5>
                        <p class="text-muted mb-0 small">Download MELCs in various formats</p>
                    </div>
                </div>
                
                <div class="mb-3 p-2 bg-light rounded">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="text-muted small">Total Records:</span>
                        <span class="badge bg-primary"><?= count($melcs) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted small">Export Formats:</span>
                        <span class="small text-muted">CSV, JSON</span>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <?php 
                        $count = count($melcs);
                        $hasData = $count > 0;
                        
                        // CSV Button Logic
                        $csvUrl = $hasData 
                            ? ['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'exportCsv'] 
                            : 'javascript:void(0);';
                            
                        $csvAttributes = [
                            'id' => 'exportCsvBtn',
                            'data-count' => $count,
                            'class' => 'btn btn-outline-primary btn-sm flex-fill ' . (!$hasData ? 'disabled-export' : ''),
                            'escape' => false
                        ];
                        
                        // JSON Button Logic
                        $jsonUrl = $hasData 
                            ? ['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'exportJson'] 
                            : 'javascript:void(0);';
                            
                        $jsonAttributes = [
                            'id' => 'exportJsonBtn',
                            'data-count' => $count,
                            'class' => 'btn btn-outline-secondary btn-sm flex-fill ' . (!$hasData ? 'disabled-export' : ''),
                            'escape' => false,
                        ];
                        
                        // Only add target blank if we actually have data (prevents opening empty tab)
                        if ($hasData) {
                            $jsonAttributes['target'] = '_blank';
                        }
                    ?>

                    <?= $this->Html->link(
                        '<i class="mdi mdi-file-delimited me-1"></i> Export CSV', 
                        $csvUrl, 
                        $csvAttributes
                    ) ?>
                    
                    <?= $this->Html->link(
                        '<i class="mdi mdi-code-braces me-1"></i> Export JSON', 
                        $jsonUrl, 
                        $jsonAttributes
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-6 grid-margin">
        <div class="card h-100">
            <div class="card-body py-3">
                <div class="d-flex align-items-center mb-2">
                    <div class="icon-wrapper bg-success-subtle rounded p-2 me-3">
                        <i class="mdi mdi-upload text-success" style="font-size: 20px;"></i>
                    </div>
                    <div>
                        <h5 class="mb-0">Import Data</h5>
                        <p class="text-muted mb-0 small">Restore MELCs from CSV file</p>
                    </div>
                </div>
                <?= $this->Form->create(null, [
                            'url' => ['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'importCsv'],
                            'type' => 'file', // This automatically sets enctype="multipart/form-data"
                            'id' => 'melcImportForm'
                        ]) ?>
                    <div class="file-drop-area" id="melcDropArea" style="border: 2px dashed #dee2e6; border-radius: 8px; padding: 40px 20px; text-align: center; background-color: #fafafa; cursor: pointer; transition: all 0.3s;">
                        <i class="mdi mdi-upload text-muted" style="font-size: 48px; display: block; margin-bottom: 12px;"></i>
                        <p class="mb-2 text-muted">Drag and drop backup file here or click to browse</p>
                        <input type="file" name="csv_file" id="melcFileInput" accept=".csv" style="display: none;"
    onchange="if(this.files.length > 0) { document.getElementById('melcFileName').textContent = this.files[0].name; document.getElementById('melcFileNameDisplay').style.display = 'block'; }">
                        <button type="button" class="btn btn-success" onclick="document.getElementById('melcFileInput').click(); return false;">
                            Choose File
                        </button>
                        <p class="text-muted small mt-3 mb-0">Supports CSV format</p>
                        <p class="text-success small mt-1 mb-0" id="melcFileNameDisplay" style="display: none;">
                            <i class="mdi mdi-file-check"></i> <span id="melcFileName"></span>
                        </p>
                    </div>
                    <button type="submit" class="btn btn-success w-100 mt-3" id="melcImportBtn">
                        <i class="mdi mdi-upload me-1"></i> Import CSV
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- MELCs Table -->
<div class="row">
    <div class="col-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">MELC Records</h4>
                
                <!-- Bulk Actions Bar for MELCs -->
                <div class="bulk-actions-bar-melcs mb-3" style="display: none;">
                    <div class="d-flex flex-wrap align-items-center gap-3 p-3 bg-light rounded border">
                        <span class="selected-count-melcs fw-bold text-primary" style="min-width: 80px;">0 selected</span>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-danger bulk-delete-melcs">
                                <i class="mdi mdi-delete"></i> Delete
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary bulk-deselect-melcs">
                                <i class="mdi mdi-close"></i> Clear
                            </button>
                        </div>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2 ms-sm-auto">
                            <button type="button" class="btn btn-sm btn-success" id="printMelcs">
                                <i class="mdi mdi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table defaultDataTable">
                        <thead>
                            <tr>
                                <th style="width: 40px;" class="no-sort">
                                    <input type="checkbox" class="form-check-input" id="selectAllMelcs">
                                </th>
                                <th>Upload Date</th>
                                <th>Description</th>
                                <th>Subject</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($melcs as $m): ?>
                                <tr data-melc-id="<?= h($this->Encrypt->hex($m->id)) ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input melc-checkbox" value="<?= h($this->Encrypt->hex($m->id)) ?>">
                                    </td>
                                    <td><?= h($m->upload_date) ?></td>
                                    <td><?= h($m->description) ?></td>
                                    <td><?= h($m->subject->name ?? $m->subject_id) ?></td>
                                    <td class="text-center">
                                        <?php $editUrl = $this->Url->build(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'edit', $this->Encrypt->hex($m->id)]); ?>
                                        <?= $this->Html->link('Edit', '#', ['class' => 'btn btn-sm btn-outline-primary btn-edit-melc', 'data-no-ajax' => 'true', 'data-href' => $editUrl]) ?>
                                        <button type="button" class="btn btn-sm btn-danger ms-2 btn-delete-melc-single" 
                                            data-id="<?= h($this->Encrypt->hex($m->id)) ?>"
                                            data-url="<?= $this->Url->build(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'delete', $this->Encrypt->hex($m->id)]) ?>">
                                            Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>


<?php $this->start('script'); ?>
<script>
(function() {
    // --- 1. GLOBAL CLICK INTERCEPTOR (For Export Alerts Only) ---
    document.addEventListener('click', function(e) {
        var exportBtn = e.target.closest('#exportCsvBtn, #exportJsonBtn');
        
        if (exportBtn) {
            var count = parseInt(exportBtn.getAttribute('data-count') || 0);
            
            // If empty, intercept and show alert
            if (count === 0) {
                e.preventDefault(); 
                e.stopImmediatePropagation();
                
                var isCSV = exportBtn.id === 'exportCsvBtn';
                var message = isCSV 
                    ? 'There are no MELC records to export as CSV.' 
                    : 'There are no MELC records to export as JSON.';

                if (window.Swal) {
                    Swal.fire({ icon: 'warning', title: 'No MELCs to Export', text: message });
                } else {
                    alert(message);
                }
            }
        }
    });

    // --- 2. IMPORT FORM SUBMIT ---
    document.addEventListener('submit', function(e) {
        if (e.target && e.target.id === 'melcImportForm') {
            e.preventDefault();
            
            var form = e.target;
            var fileInput = document.getElementById('melcFileInput');
            
            if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                window.Swal ? Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Please choose a file first!' }) : alert('Please choose a file!');
                return;
            }
            
            if (window.Swal) {
                Swal.fire({
                    title: 'Importing Data...',
                    html: 'Please wait while we process your CSV file.',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function() { Swal.showLoading(); }
                });
            }
            
            var formData = new FormData(form);
            var csrf = document.querySelector('meta[name="csrfToken"]');
            
            fetch(form.action, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': csrf ? csrf.getAttribute('content') : ''
                }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    localStorage.setItem('genta_is_importing', '1');
                    window.location.reload();
                } else {
                    Swal.fire({ icon: 'error', title: 'Import Failed', text: data.message });
                }
            })
            .catch(function(err) {
                Swal.fire({ icon: 'error', title: 'Import Error', text: 'An error occurred.' });
            });
        }
    });

    // --- 3. DRAG AND DROP LOGIC ---
    var dragEvents = ['dragenter', 'dragover', 'dragleave', 'drop'];
    dragEvents.forEach(function(eventName) {
        document.addEventListener(eventName, function(e) {
            var dropArea = document.getElementById('melcDropArea');
            if (!dropArea) return;

            var isInside = e.target.closest('#melcDropArea');

            // Always prevent default if inside area, or if it's a drop event
            if (isInside || eventName === 'drop') {
                if (eventName === 'drop' || eventName === 'dragenter' || eventName === 'dragover') {
                    e.preventDefault();
                    e.stopPropagation();
                }
            }

            // Styling
            if (isInside && (eventName === 'dragenter' || eventName === 'dragover')) {
                dropArea.style.borderColor = '#198754';
                dropArea.style.backgroundColor = '#f0fdf4';
            } else if (eventName === 'dragleave' || eventName === 'drop') {
                dropArea.style.borderColor = '#dee2e6';
                dropArea.style.backgroundColor = '#fafafa';
            }

            // Handle File Drop
            if (eventName === 'drop' && isInside) {
                var dt = e.dataTransfer;
                var files = dt.files;
                if (files && files.length > 0) {
                    var fileInput = document.getElementById('melcFileInput');
                    fileInput.files = files;
                    
                    // Manually trigger the visual update because dragging bypasses the inline 'onchange'
                    document.getElementById('melcFileName').textContent = files[0].name;
                    document.getElementById('melcFileNameDisplay').style.display = 'block';
                }
            }
        });
    });

    // --- 4. INIT TASKS ---
    // Toast
    if (localStorage.getItem('genta_is_importing') === '1') {
        localStorage.removeItem('genta_is_importing');
        setTimeout(function() {
            if (window.Swal) Swal.fire({ icon: 'success', title: 'Import Successful!', text: 'Records added.', timer: 3000, showConfirmButton: false });
        }, 300);
    }
    
    // DataTables
    function initTables() {
        if (window.jQuery) {
            var $ = window.jQuery;
            var table = $('.defaultDataTable');
            if (table.length > 0 && !$.fn.DataTable.isDataTable(table)) {
                table.DataTable({ "pageLength": 10, "columnDefs": [ { "orderable": false, "targets": 0 } ] });
            }
        }
    }
    initTables();
    window.addEventListener('load', initTables);

})();
</script>
<?php $this->end(); ?>

<!-- Modal placeholder for Add/Edit MELC -->
<div class="modal fade" id="melcModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">MELC</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body py-3">
                <div class="text-center">Loading...</div>
            </div>
        </div>
    </div>
</div>

<style>
    .disabled-export {
        opacity: 0.6 !important;
        cursor: not-allowed !important;
    }
</style>