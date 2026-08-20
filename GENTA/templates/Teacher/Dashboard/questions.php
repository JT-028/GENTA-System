<!-- TABLE -->
<div class="row">
    <div class="col-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <div class="row mb-4">
                    <div class="col-sm-4">
                        <h4 class="card-title">Quiz</h4>
                    </div>

                    <div class="d-flex justify-content-end col-sm-8">
                        <a href="#" class="btn btn-success btn-fw mb-3 btn-add-question" data-no-ajax="true">
                            <i class="mdi mdi-plus"></i> Add Question
                        </a>
                    </div>

                    <div class="col-xl-4 col-lg-6 col-sm-8">
                        <?= $this->Form->create(NULL, ['url' => ['controller' => 'Dashboard', 'action' => 'questions', 'prefix' => 'Teacher'], 'method' => 'get', 'id' => 'questionsSubjectForm']) ?>
                            <?= $this->Form->select('questionsSubject', $subjectOptions, ['class' => 'form-select mb-3', 'id' => 'questionsSubject', 'value' => $quesSubjectSel]) ?>
                        <?= $this->Form->end() ?>
                    </div>
                </div>

                <!-- Bulk Actions Bar for Questions -->
                <div class="bulk-actions-bar-questions mb-3" style="display: none;">
                    <div class="d-flex flex-wrap align-items-center gap-3 p-3 bg-light rounded border">
                        <span class="selected-count-questions fw-bold text-primary" style="min-width: 80px;">0 selected</span>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-danger bulk-delete-questions">
                                <i class="mdi mdi-delete"></i> Delete
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-warning bulk-suspend-questions">
                                <i class="mdi mdi-pause-circle"></i> Suspend
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-success bulk-activate-questions">
                                <i class="mdi mdi-check-circle"></i> Activate
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-secondary bulk-deselect-questions">
                                <i class="mdi mdi-close"></i> Clear
                            </button>
                        </div>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2 ms-sm-auto">
                            <button type="button" class="btn btn-sm btn-success" id="printQuestions">
                                <i class="mdi mdi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <table class="table defaultDataTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="no-sort">
                                <input type="checkbox" class="form-check-input" id="selectAllQuestions">
                            </th>
                            <th width="14%">Subject</th>
                            <th width="32%">Question</th>
                            <th width="18%">Choices</th>
                            <th width="12%">Answer</th>
                            <th width="8%">Score</th>
                            <th width="8%">Status</th>
                            <th width="8%" class="text-center actions-column">Action</th> 
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($questions as $question) { ?>
                            <tr data-question-id="<?= h($this->Encrypt->hex($question->id)) ?>" data-status="<?= $question->status ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input question-checkbox" value="<?= h($this->Encrypt->hex($question->id)) ?>">
                                </td>
                                <td class="fw-bold"><?= $question->subject->name ?></td>
                                <td class="text-wrap"><?= $question->description ?></td>
                                <td class="text-center"><?= $question->choices_string ?></td>
                                <td class="text-center"><?= $question->answer ?></td>
                                <td class="text-center"><?= $question->score ?></td>
                                <td class="text-center">
                                    <?php // Status badge: 1=Active, 2=Suspended ?>
                                    <?php if ($question->status == \App\Model\Table\QuestionsTable::STATUS_ACTIVE): ?>
                                        <span class="badge bg-success status-badge">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary status-badge">Suspended</span>
                                    <?php endif; ?>
                                </td>
                                
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <?= $this->Html->link('<i class="mdi mdi-lead-pencil"></i>', ['controller' => 'Dashboard', 'action' => 'createEditQuestion', 'prefix' => 'Teacher', $this->Encrypt->hex($question->id)], ['escape' => false, 'class' => 'btn-edit-question', 'title' => 'Edit']) ?>

                                        <a href="#" class="toggleQuestionStatusBtn" data-question-id="<?= h($this->Encrypt->hex($question->id)) ?>" title="Toggle status"><i class="mdi mdi-power-plug"></i></a>

                                        <a href="#" class="deleteQuestionBtn" data-question-id="<?= h($this->Encrypt->hex($question->id)) ?>" title="Delete"><i class="mdi mdi-close"></i></a>
                                    </div>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?= $this->element('modal/confirm_delete_question') ?>

<?php $this->start('script'); ?>
<script>
(function() {
    window.initBulkActionsQuestions = function() {
        if (!window.jQuery) {
            return;
        }
        var $ = window.jQuery;
        // Only run on Questions page
        if (!$('.question-checkbox').length && !$('#selectAllQuestions').length) {
            return;
        }
        console.info('[Questions] initBulkActionsQuestions called');

        function tableApi() {
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('.defaultDataTable')) {
                return $('.defaultDataTable').DataTable();
            }
            return null;
        }

        function visibleCheckboxes() {
            var dt = tableApi();
            if (dt) {
                return $(dt.rows({ page: 'current' }).nodes()).find('.question-checkbox');
            }
            return $('.question-checkbox');
        }

        // Bulk Actions Functionality for Questions
        function updateBulkActionsBarQuestions() {
            var selectedCount = $('.question-checkbox:checked').length;
            if (selectedCount > 0) {
                $('.bulk-actions-bar-questions').show();
                $('.selected-count-questions').text(selectedCount + ' selected');
            } else {
                $('.bulk-actions-bar-questions').hide();
            }
        }

        // Prevent DataTables header click from sorting when toggling select-all / row checkboxes
        $(document).off('click.bulkactionsstop', '#selectAllQuestions, .question-checkbox').on('click.bulkactionsstop', '#selectAllQuestions, .question-checkbox', function(e) {
            e.stopPropagation();
        });

        // Select All checkbox for questions - use event delegation (attach once)
        $(document).off('change.bulkactions', '#selectAllQuestions').on('change.bulkactions', '#selectAllQuestions', function() {
            var isChecked = $(this).prop('checked');
            visibleCheckboxes().prop('checked', isChecked);
            updateBulkActionsBarQuestions();
        });

        // Individual checkbox for questions - use event delegation
        $(document).off('change.bulkactions', '.question-checkbox').on('change.bulkactions', '.question-checkbox', function() {
            var $visible = visibleCheckboxes();
            var totalCheckboxes = $visible.length;
            var checkedCheckboxes = $visible.filter(':checked').length;
            $('#selectAllQuestions').prop('checked', totalCheckboxes === checkedCheckboxes);
            updateBulkActionsBarQuestions();
        });

        // Clear selection for questions - use event delegation
        $(document).off('click.bulkactions', '.bulk-deselect-questions').on('click.bulkactions', '.bulk-deselect-questions', function() {
            $('.question-checkbox, #selectAllQuestions').prop('checked', false);
            updateBulkActionsBarQuestions();
        });

        function ensureDataTableSync(attempts) {
            attempts = attempts || 0;
            var dtSync = tableApi();
            if (dtSync) {
                dtSync.off('draw.bulkactions').on('draw.bulkactions', function() {
                    var $visible = visibleCheckboxes();
                    var totalCheckboxes = $visible.length;
                    var checkedCheckboxes = $visible.filter(':checked').length;
                    $('#selectAllQuestions').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                });
                var $visible = visibleCheckboxes();
                var totalCheckboxes = $visible.length;
                var checkedCheckboxes = $visible.filter(':checked').length;
                $('#selectAllQuestions').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                updateBulkActionsBarQuestions();
                return;
            }
            if (attempts < 20) {
                setTimeout(function(){ ensureDataTableSync(attempts+1); }, 100);
            }
        }

        ensureDataTableSync();

        // Re-sync after AJAX page loads
        $(document).off('genta:page-ready.questions').on('genta:page-ready.questions', function(){
            ensureDataTableSync(0);
        });

        // Bulk Delete Questions
        $('.bulk-delete-questions').on('click', function() {
            var selectedIds = $('.question-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) return;

            var confirmText = 'Are you sure you want to delete ' + selectedIds.length + ' question(s)?';
            
            if (window.Swal && typeof Swal.fire === 'function') {
                Swal.fire({
                    title: 'Delete Questions?',
                    text: confirmText,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete them',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#d33'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        performBulkActionQuestions(selectedIds, 'delete');
                    }
                });
            } else {
                if (confirm(confirmText)) {
                    performBulkActionQuestions(selectedIds, 'delete');
                }
            }
        });

        // Bulk Suspend Questions
        $('.bulk-suspend-questions').on('click', function() {
            var selectedIds = $('.question-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) return;

            performBulkActionQuestions(selectedIds, 'suspend');
        });

        // Bulk Activate Questions
        $('.bulk-activate-questions').on('click', function() {
            var selectedIds = $('.question-checkbox:checked').map(function() {
                return $(this).val();
            }).get();

            if (selectedIds.length === 0) return;

            performBulkActionQuestions(selectedIds, 'activate');
        });

        function performBulkActionQuestions(selectedIds, action) {
            var csrf = $('meta[name=csrfToken]').attr('content') || '';
            var completed = 0;
            var total = selectedIds.length;
            var actionText = action === 'delete' ? 'Deleting' : (action === 'suspend' ? 'Suspending' : 'Activating');
            var actionTextPast = action === 'delete' ? 'deleted' : (action === 'suspend' ? 'suspended' : 'activated');
            
            // Show progress dialog
            if (window.Swal && typeof Swal.fire === 'function') {
                Swal.fire({
                    title: actionText + ' Questions...',
                    html: '<div class="mb-3">Processing <b>0</b> of <b>' + total + '</b> records</div>' +
                          '<div class="progress" style="height: 20px;"><div class="progress-bar progress-bar-striped progress-bar-animated ' + (action === 'delete' ? 'bg-danger' : (action === 'suspend' ? 'bg-warning' : 'bg-success')) + '" role="progressbar" style="width: 0%"></div></div>',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        processNext(0);
                    }
                });
            } else {
                processNext(0);
            }
            
            function updateProgress(current) {
                var percent = Math.round((current / total) * 100);
                var $content = $(Swal.getHtmlContainer());
                $content.find('b').first().text(current);
                $content.find('.progress-bar').css('width', percent + '%');
            }
            
            function processNext(index) {
                if (index >= total) {
                    // All done
                    if (window.Swal && typeof Swal.fire === 'function') {
                        Swal.fire({
                            title: 'Success!',
                            text: completed + ' question(s) have been ' + actionTextPast + '.',
                            icon: 'success'
                        }).then(function() {
                            window.location.reload();
                        });
                    } else {
                        alert(completed + ' question(s) have been ' + actionTextPast + '.');
                        window.location.reload();
                    }
                    return;
                }
                
                var url;
                if (action === 'delete') {
                    url = '<?= $this->Url->build(['controller' => 'Dashboard', 'action' => 'deleteQuestion', 'prefix' => 'Teacher']) ?>/' + selectedIds[index];
                } else {
                    url = '<?= $this->Url->build(['controller' => 'Dashboard', 'action' => 'toggleQuestionStatus', 'prefix' => 'Teacher']) ?>/' + selectedIds[index];
                }
                
                $.ajax({
                    url: url,
                    method: 'POST',
                    headers: { 'X-CSRF-Token': csrf },
                    dataType: 'json'
                }).always(function(data, textStatus) {
                    if (textStatus === 'success' || (data && data.success)) {
                        completed++;
                    }
                    updateProgress(index + 1);
                    processNext(index + 1);
                });
            }
        }

        // Print Functionality for Questions - use event delegation
        $(document).on('click', '#printQuestions', function() {
            var printContent = generateQuestionsPrintContent();
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
        $(document).on('click', '#exportQuestionsCSV', function() {
            exportQuestionsToCSV();
        });

        // Export Excel - use event delegation
        $(document).on('click', '#exportQuestionsExcel', function() {
            exportQuestionsToExcel();
        });

        function getQuestionsData() {
            var data = [];
            var checkedOnly = $('.question-checkbox:checked').length > 0;
            var selector = checkedOnly ? '.question-checkbox:checked' : '.question-checkbox';
            
            $(selector).each(function() {
                var $row = $(this).closest('tr');
                data.push({
                    subject: $row.find('td:eq(1)').text().trim(),
                    question: $row.find('td:eq(2)').text().trim(),
                    choices: $row.find('td:eq(3)').text().trim(),
                    answer: $row.find('td:eq(4)').text().trim(),
                    score: $row.find('td:eq(5)').text().trim(),
                    status: $row.find('td:eq(6)').text().trim()
                });
            });
            return data;
        }

        function exportQuestionsToCSV() {
            var data = getQuestionsData();
            var csv = 'Subject,Question,Choices,Answer,Score,Status\n';
            data.forEach(function(row) {
                csv += '"' + row.subject.replace(/"/g, '""') + '","' + 
                       row.question.replace(/"/g, '""') + '","' + 
                       row.choices.replace(/"/g, '""') + '","' + 
                       row.answer.replace(/"/g, '""') + '","' + 
                       row.score.replace(/"/g, '""') + '","' + 
                       row.status.replace(/"/g, '""') + '"\n';
            });
            
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'questions_report_' + new Date().getTime() + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportQuestionsToExcel() {
            var data = getQuestionsData();
            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            html += '<head><meta charset="utf-8"><style>table { border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; } th { background-color: #4B49AC; color: white; }</style></head>';
            html += '<body><h2>Questions Report</h2><p>Generated: ' + new Date().toLocaleString() + '</p>';
            html += '<table><thead><tr><th>Subject</th><th>Question</th><th>Choices</th><th>Answer</th><th>Score</th><th>Status</th></tr></thead><tbody>';
            data.forEach(function(row) {
                html += '<tr><td>' + escapeHtml(row.subject) + '</td><td>' + escapeHtml(row.question) + '</td><td>' + 
                        escapeHtml(row.choices) + '</td><td>' + escapeHtml(row.answer) + '</td><td>' + 
                        escapeHtml(row.score) + '</td><td>' + escapeHtml(row.status) + '</td></tr>';
            });
            html += '</tbody></table></body></html>';
            
            var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'questions_report_' + new Date().getTime() + '.xls');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function escapeHtml(str) {
            return String(str === undefined || str === null ? '' : str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function generateQuestionsPrintContent() {
            var today = new Date().toLocaleDateString();
            var rows = '';
            var checkedOnly = $('.question-checkbox:checked').length > 0;
            
            var selector = checkedOnly ? '.question-checkbox:checked' : '.question-checkbox';
            $(selector).each(function() {
                var $row = $(this).closest('tr');
                var subject = $row.find('td:eq(1)').text();
                var question = $row.find('td:eq(2)').text();
                var choices = $row.find('td:eq(3)').text();
                var answer = $row.find('td:eq(4)').text();
                var score = $row.find('td:eq(5)').text();
                var status = $row.find('td:eq(6)').text().trim();
                rows += '<tr><td>' + escapeHtml(subject) + '</td><td>' + escapeHtml(question) + '</td><td>' + escapeHtml(choices) + '</td><td>' + escapeHtml(answer) + '</td><td>' + escapeHtml(score) + '</td><td>' + escapeHtml(status) + '</td></tr>';
            });

            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Questions Report</title>
                    <style>
                        body { font-family: Arial, sans-serif; margin: 20px; }
                        h1 { text-align: center; color: #333; }
                        .header { text-align: center; margin-bottom: 20px; }
                        .date { text-align: right; margin-bottom: 10px; font-size: 12px; }
                        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 11px; }
                        th { background-color: #4B49AC; color: white; padding: 8px; text-align: left; border: 1px solid #ddd; }
                        td { padding: 6px; border: 1px solid #ddd; vertical-align: top; }
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
                        <h1>Questions Report</h1>
                        <p>GENTA Learning Management System</p>
                    </div>
                    <div class="date">Generated: ${today}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Subject</th>
                                <th>Question</th>
                                <th>Choices</th>
                                <th>Answer</th>
                                <th>Score</th>
                                <th>Status</th>
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
    };

    // Run on initial page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initBulkActionsQuestions);
    } else {
        window.initBulkActionsQuestions();
    }
})();
</script>
<?php $this->end(); ?>