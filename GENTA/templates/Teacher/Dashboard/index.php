<!-- STATISTICS CARDS -->
<div class="row">
    <!-- Total Students Card -->
    <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card card-gradient-primary">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="card-icon-wrapper">
                        <i class="mdi mdi-account-group display-4"></i>
                    </div>
                    <div class="text-end">
                        <h2 class="mb-0 font-weight-bold"><?= $totalStudents ?></h2>
                        <p class="mb-0 text-white-50">Students</p>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-white" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-2 mb-0 text-white-50"><i class="mdi mdi-account-multiple-plus me-1"></i>Total Enrolled</p>
            </div>
        </div>
    </div>

    <!-- Total Assessments Card -->
    <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card card-gradient-success">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="card-icon-wrapper">
                        <i class="mdi mdi-clipboard-text display-4"></i>
                    </div>
                    <div class="text-end">
                        <h2 class="mb-0 font-weight-bold"><?= $totalAssessments ?></h2>
                        <p class="mb-0 text-white-50">Assessments</p>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-white" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-2 mb-0 text-white-50"><i class="mdi mdi-chart-line-variant me-1"></i>Completed Tests</p>
            </div>
        </div>
    </div>

    <!-- Question Bank Card -->
    <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card card-gradient-warning">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="card-icon-wrapper">
                        <i class="mdi mdi-lightbulb-on display-4"></i>
                    </div>
                    <div class="text-end">
                        <h2 class="mb-0 font-weight-bold"><?= $totalQuestions ?></h2>
                        <p class="mb-0 text-white-50">Questions</p>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-white" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-2 mb-0 text-white-50"><i class="mdi mdi-database me-1"></i>Question Bank</p>
            </div>
        </div>
    </div>

    <!-- Average Score Card -->
    <div class="col-xl-3 col-sm-6 grid-margin stretch-card">
        <div class="card card-gradient-info">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <div class="card-icon-wrapper">
                        <i class="mdi mdi-chart-arc display-4"></i>
                    </div>
                    <div class="text-end">
                        <h2 class="mb-0 font-weight-bold"><?= $averageScore ?>%</h2>
                        <p class="mb-0 text-white-50">Avg Score</p>
                    </div>
                </div>
                <div class="progress" style="height: 4px;">
                    <div class="progress-bar bg-white" role="progressbar" style="width: <?= $averageScore ?>%" aria-valuenow="<?= $averageScore ?>" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <p class="mt-2 mb-0 text-white-50"><i class="mdi mdi-trending-<?= $averageScore >= 75 ? 'up' : 'down' ?> me-1"></i>Class Performance</p>
            </div>
        </div>
    </div>
</div>

<style>
/* Enhanced Card Styles */
.card-gradient-primary {
    background: linear-gradient(135deg, var(--brand-primary) 0%, var(--vivid-sky) 100%);
    color: white;
    box-shadow: 0 4px 20px 0 rgba(var(--brand-primary-rgba-18), 0.4);
    border: none;
}

.card-gradient-success {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    box-shadow: 0 4px 20px 0 rgba(17, 153, 142, 0.4);
}

.card-gradient-warning {
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    box-shadow: 0 4px 20px 0 rgba(240, 147, 251, 0.4);
    border: none;
}

.card-gradient-info {
    background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    color: white;
    box-shadow: 0 4px 20px 0 rgba(79, 172, 254, 0.4);
    border: none;
}

.card-icon-wrapper {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    width: 70px;
    height: 70px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.card-icon-wrapper i {
    font-size: 2.5rem;
    color: white;
}

.card-gradient-primary .card-body,
.card-gradient-success .card-body,
.card-gradient-warning .card-body,
.card-gradient-info .card-body {
    padding: 1.5rem;
}

.card-gradient-primary:hover,
.card-gradient-success:hover,
.card-gradient-warning:hover,
.card-gradient-info:hover {
    transform: translateY(-5px);
    transition: all 0.3s ease;
}

/* Compact badge for Attempts column to avoid breaking table layout */
.attempts-cell { white-space: nowrap; width: 110px; vertical-align: middle; }
.attempts-badge { display: inline-block; white-space: nowrap; padding: .22rem .45rem; font-size: .78rem; line-height: 1; min-width: 56px; text-align: center; }
.attempts-badge .mdi { vertical-align: middle; }

</style>

<style>
/* Ensure action buttons fit within a fixed column to avoid table reflow */
.actions-cell { vertical-align: middle; min-width: 120px; max-width: 160px; text-align: center; }
.actions-cell .btn { display: block; width: 100%; box-sizing: border-box; padding: .35rem .5rem; margin-bottom: .45rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.actions-cell .btn i { margin-right: .35rem; }
@media (min-width: 992px) {
    /* On wide screens keep buttons compact but allow inline where space permits */
    .actions-cell { min-width: 140px; }
}
</style>

<!-- TABLE -->
<div class="row">
    <div class="col-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title mb-4">Students' Assessments Summary</h4>
                <p class="text-muted mb-4">
                    <i class="mdi mdi-information-outline me-1"></i>
                    This table groups assessments by student and subject. If a student took the same subject multiple times, 
                    you'll see their latest score, best score, and total attempts.
                </p>

                <!-- Bulk Actions Bar for Assessments -->
                <div class="bulk-actions-bar-assessments mb-3" style="display: none;">
                    <div class="d-flex flex-wrap align-items-center gap-3 p-3 bg-light rounded border">
                        <span class="selected-count-assessments fw-bold text-primary" style="min-width: 80px;">0 selected</span>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary bulk-deselect-assessments">
                                <i class="mdi mdi-close"></i> Clear
                            </button>
                        </div>
                        <div class="vr d-none d-sm-block"></div>
                        <div class="d-flex flex-wrap gap-2 ms-sm-auto">
                            <button type="button" class="btn btn-sm btn-success" id="printAssessments">
                                <i class="mdi mdi-printer"></i> Print
                            </button>
                        </div>
                    </div>
                </div>

                <table class="table defaultDataTable">
                    <thead>
                        <tr>
                            <th style="width: 40px;" class="no-sort">
                                <input type="checkbox" class="form-check-input" id="selectAllAssessments">
                            </th>
                            <th>LRN</th>
                            <th>Name</th>
                            <th>Grade-Section</th>
                            <th>Subject</th>
                            <th>Version</th>
                            <th>Attempts</th>
                            <th>Latest Score</th>
                            <th>Best Score</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($groupedAssessments as $assessment) { 
                            $student = $assessment['student'];
                            $subject = $assessment['subject'];
                            $attempts = $assessment['attempts'];
                            $latestQuiz = $assessment['latest_quiz'];
                            
                            $latestScoreDisplay = $assessment['latest_score_total'] > 0 
                                ? $assessment['latest_score_raw'] . '/' . $assessment['latest_score_total']
                                : 'N/A';
                            
                            $bestScoreDisplay = $assessment['best_score_total'] > 0 
                                ? $assessment['best_score_raw'] . '/' . $assessment['best_score_total']
                                : 'N/A';
                            
                            // Calculate percentage for color coding
                            $latestPercent = $assessment['latest_score_total'] > 0 
                                ? round(($assessment['latest_score_raw'] / $assessment['latest_score_total']) * 100, 1)
                                : 0;
                            $bestPercent = $assessment['best_score_total'] > 0 
                                ? round(($assessment['best_score_raw'] / $assessment['best_score_total']) * 100, 1)
                                : 0;
                            
                            // Color classes based on score
                            $latestScoreClass = $latestPercent >= 75 ? 'text-success fw-bold' : ($latestPercent >= 50 ? 'text-warning fw-bold' : 'text-danger fw-bold');
                            $bestScoreClass = $bestPercent >= 75 ? 'text-success fw-bold' : ($bestPercent >= 50 ? 'text-warning fw-bold' : 'text-danger fw-bold');
                        ?>
                            <tr data-assessment-key="<?= h($student->id . '_' . $subject->id) ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input assessment-checkbox" value="<?= h($student->id . '_' . $subject->id) ?>" data-lrn="<?= h($student->lrn ?? 'N/A') ?>" data-name="<?= h($student->name) ?>" data-grade="<?= h($student->grade . ' - ' . $student->section) ?>" data-subject="<?= h($subject->name ?? 'N/A') ?>" data-version="<?= isset($latestQuiz->quiz_version) && $latestQuiz->quiz_version ? h($latestQuiz->quiz_version->version_number) : '—' ?>" data-attempts="<?= $attempts ?>" data-latest="<?= $latestScoreDisplay ?>" data-best="<?= $bestScoreDisplay ?>">
                                </td>
                                <td class="fw-bold"><?= h($student->lrn ?? 'N/A') ?></td>
                                <td><?= h($student->name) ?></td>
                                <td><?= h($student->grade . ' - ' . $student->section) ?></td>
                                <td><?= h($subject->name ?? 'N/A') ?></td>
                                <td><?= isset($latestQuiz->quiz_version) && $latestQuiz->quiz_version ? h($latestQuiz->quiz_version->version_number) : '—' ?></td>
                                <td class="attempts-cell">
                                    <span class="badge bg-primary attempts-badge">
                                        <?= $attempts ?> <?= $attempts > 1 ? 'attempts' : 'attempt' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?= $latestScoreClass ?>">
                                        <?= $latestScoreDisplay ?>
                                    </span>
                                    <?php if ($latestPercent > 0): ?>
                                        <small class="text-muted">(<?= $latestPercent ?>%)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= $bestScoreClass ?>">
                                        <?= $bestScoreDisplay ?>
                                    </span>
                                    <?php if ($bestPercent > 0): ?>
                                        <small class="text-muted">(<?= $bestPercent ?>%)</small>
                                    <?php endif; ?>
                                    <?php if ($attempts > 1 && $bestPercent > $latestPercent): ?>
                                        <i class="mdi mdi-trophy text-warning ms-1" title="Best score achieved"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center actions-cell">
                                    <?php if ($attempts == 1): ?>
                                        <?= $this->Html->link(
                                            '<i class="mdi mdi-file-document-outline"></i> View',
                                            ['controller' => 'Dashboard', 'action' => 'studentQuiz', 'prefix' => 'Teacher', $this->Encrypt->hex($latestQuiz->id)],
                                            ['escape' => false, 'class' => 'btn btn-sm btn-info text-white']
                                        ) ?>
                                    <?php else: ?>
                                        <?php
                                            // Build a lightweight array of attempts for the modal
                                            $quizItems = [];
                                            // Number attempts correctly: most recent gets highest number
                                            $totalAttempts = count($assessment['all_quizzes']);
                                            $attemptNum = $totalAttempts;
                                            foreach($assessment['all_quizzes'] as $quiz) {
                                                // Prefer numeric fields provided by the entity: studentScore and totalScore
                                                $studentScore = isset($quiz->score['studentScore']) ? (int)$quiz->score['studentScore'] : 0;
                                                $totalScore = isset($quiz->score['totalScore']) ? (int)$quiz->score['totalScore'] : 0;

                                                if ($totalScore > 0) {
                                                    $quizScore = $studentScore . '/' . $totalScore;
                                                    $quizPercent = round(($studentScore / $totalScore) * 100, 1);
                                                } else {
                                                    // If total is unknown, show numerator/0 when numerator exists, otherwise N/A
                                                    $quizScore = $studentScore > 0 ? ($studentScore . '/0') : 'N/A';
                                                    $quizPercent = 0;
                                                }

                                                $isBest = ($assessment['best_score_raw'] == $studentScore && $assessment['best_score_total'] == $totalScore);

                                                // include quiz version info if available
                                                $versionNumber = null;
                                                if (!empty($quiz->quiz_version) && !empty($quiz->quiz_version->version_number)) {
                                                    $versionNumber = (int)$quiz->quiz_version->version_number;
                                                }
                                                $versionSuffix = $versionNumber ? ' - v' . $versionNumber : '';

                                                // extract metadata from quiz_version if available
                                                $snapshotAt = null;
                                                $creator = null;
                                                if (!empty($quiz->quiz_version)) {
                                                    $qv = $quiz->quiz_version;
                                                    // prefer metadata JSON
                                                    if (!empty($qv->metadata)) {
                                                        $metaArr = @json_decode($qv->metadata, true);
                                                        if (is_array($metaArr)) {
                                                            if (!empty($metaArr['snapshot_at'])) $snapshotAt = $metaArr['snapshot_at'];
                                                            if (!empty($metaArr['teacher_id'])) $creator = $metaArr['teacher_id'];
                                                            if (!empty($metaArr['created_by'])) $creator = $metaArr['created_by'];
                                                        }
                                                    }
                                                    if (empty($creator) && !empty($qv->created_by)) $creator = $qv->created_by;
                                                }

                                                // resolve creator name if available
                                                $creatorName = null;
                                                if (!empty($creator) && !empty($creatorNames) && isset($creatorNames[$creator])) {
                                                    $creatorName = $creatorNames[$creator];
                                                }

                                                $quizItems[] = [
                                                    'id' => $this->Encrypt->hex($quiz->id),
                                                    'label' => 'Attempt #' . $attemptNum . ' - ' . $quizScore . ' (' . $quizPercent . '%)' . $versionSuffix,
                                                    'isBest' => $isBest,
                                                    'version' => $versionNumber,
                                                    'snapshot_at' => $snapshotAt,
                                                    'created_by' => $creator,
                                                    'created_by_name' => $creatorName
                                                ];

                                                $attemptNum--;
                                            }
                                            $jsonQuizzes = h(json_encode($quizItems));
                                        ?>

                                        <button class="btn btn-sm btn-info btn-view-all" type="button" data-quizzes='<?= $jsonQuizzes ?>'>
                                            <i class="mdi mdi-file-document-outline"></i> View All
                                        </button>
                                        <!-- Delete all attempts for this student+subject -->
                                        <?php $studentHash = $this->Encrypt->hex($student->id); $subjectHash = $this->Encrypt->hex($subject->id); ?>
                                        <button class="btn btn-sm btn-danger deleteAssessmentsBtn" type="button" data-student="<?= h($studentHash) ?>" data-subject="<?= h($subjectHash) ?>">
                                            <i class="mdi mdi-trash-can-outline"></i> Delete
                                        </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
// Ensure Bootstrap dropdowns work properly inside DataTables
(function() {
    function initDashboardDropdowns() {
        // Wait for jQuery and Bootstrap to be available
        if (typeof jQuery === 'undefined' || typeof bootstrap === 'undefined') {
            setTimeout(initDashboardDropdowns, 100);
            return;
        }
        
        var $ = jQuery;
        
        // Re-initialize dropdowns after DataTables pagination/sort
        $('.defaultDataTable').on('draw.dt', function() {
            setupDropdowns();
        });
        
        // Initial dropdown setup
        setupDropdowns();
        
        function setupDropdowns() {
            // Initialize Bootstrap dropdowns
            var dropdownElementList = [].slice.call(document.querySelectorAll('[data-bs-toggle="dropdown"]'));
            dropdownElementList.forEach(function(dropdownToggleEl) {
                // Dispose existing instance if any
                var existingInstance = bootstrap.Dropdown.getInstance(dropdownToggleEl);
                if (existingInstance) {
                    existingInstance.dispose();
                }
                // Create new instance
                new bootstrap.Dropdown(dropdownToggleEl);
            });
            
            // Prevent DataTable row click when clicking dropdown
            $('.dropdown-toggle, .dropdown-menu').off('click.dropdownstop').on('click.dropdownstop', function(e) {
                e.stopPropagation();
            });
        }
        
        // Handle dropdown item clicks (navigate via AJAX)
        $(document).on('click', '.dropdown-item', function(e) {
            e.stopPropagation();
            var href = $(this).attr('href');
            if (href && typeof loadPage === 'function') {
                e.preventDefault();
                loadPage(href);
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initDashboardDropdowns);
    } else {
        initDashboardDropdowns();
    }
})();
</script>

<!-- Attempts Modal (used by View All) -->
<div class="modal fade" id="attemptsModal" tabindex="-1" aria-labelledby="attemptsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="attemptsModalLabel">Attempts</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group" id="attemptsModalList"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
// Handler to show attempts modal when View All is clicked
(function() {
    function initAttemptsModal() {
        if (typeof jQuery === 'undefined' || typeof bootstrap === 'undefined') {
            setTimeout(initAttemptsModal, 100);
            return;
        }
        var $ = jQuery;

    $(document).on('click', '.btn-view-all', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var el = this;
            var data = el.getAttribute('data-quizzes') || $(el).attr('data-quizzes');
            console.log('[AttemptsModal] button clicked, raw data attr:', data);
            if (!data) {
                console.warn('[AttemptsModal] no data-quizzes attribute found');
                return;
            }

            var quizzes = null;
            try {
                quizzes = JSON.parse(data);
            } catch (err) {
                // Try to decode HTML entities and parse again
                try {
                    var ta = document.createElement('textarea');
                    ta.innerHTML = data;
                    var decoded = ta.value;
                    console.log('[AttemptsModal] decoded data attr:', decoded);
                    quizzes = JSON.parse(decoded);
                } catch (err2) {
                    console.error('[AttemptsModal] Failed to parse quizzes JSON', err, err2);
                    // Show a user-friendly message
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({icon: 'error', title: 'Error', text: 'Unable to show attempts. Please try again.'});
                    } else {
                        alert('Unable to show attempts. See console for details.');
                    }
                    return;
                }
            }

            var $list = $('#attemptsModalList');
            $list.empty();
                quizzes.forEach(function(q){
                var href = (typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : (window.location.origin || (window.location.protocol + '//' + window.location.host) ) + '/') + 'teacher/dashboard/studentQuiz/' + q.id;
                var $li = $('<li class="list-group-item d-flex justify-content-between align-items-start"></li>');
                var $wrap = $('<div></div>');
                var $link = $('<a class="me-3 d-block" href="' + href + '"></a>').text(q.label);
                $link.on('click', function(ev){
                    ev.preventDefault();
                    ev.stopPropagation();
                    try {
                        // Prefer to hide an existing Bootstrap modal instance safely
                        var modalEl = document.getElementById('attemptsModal');
                        if (window.bootstrap && bootstrap.Modal && modalEl) {
                            var inst = bootstrap.Modal.getInstance(modalEl);
                            if (inst && typeof inst.hide === 'function') {
                                inst.hide();
                            } else {
                                // If no instance, ensure backdrop removed as fallback and do a defensive cleanup
                                modalEl.classList.remove('show');
                                document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
                                // also remove DataTables responsive modal elements and restore body state
                                document.querySelectorAll('.dtr-modal-background, .dtr-modal').forEach(function(b){ b.remove(); });
                                document.body.classList.remove('modal-open');
                                document.body.style.overflow = '';
                                document.body.style.paddingRight = '';
                            }
                        } else {
                            // Fallback: remove any native overlay created by our fallback UI
                            var existingOverlay = document.getElementById('simpleAttemptsOverlay');
                            if (existingOverlay && existingOverlay.parentNode) existingOverlay.parentNode.removeChild(existingOverlay);
                        }
                    } catch (e) {
                        console.warn('Could not hide modal before navigation', e);
                    }
                    if (typeof loadPage === 'function') { loadPage(href); } else { window.location.href = href; }
                });
                $wrap.append($link);
                // add metadata small text if available
                    if (q.snapshot_at || q.created_by_name || q.created_by) {
                    var metaText = [];
                    if (q.snapshot_at) {
                        // show only date/time portion
                        var dt = q.snapshot_at;
                        try { dt = new Date(dt).toLocaleString(); } catch(e) { /* leave as-is */ }
                        metaText.push('Snapshot: ' + dt);
                    }
                    if (q.created_by_name) {
                        metaText.push('By ' + q.created_by_name);
                    } else if (q.created_by) {
                        metaText.push('By teacher #' + q.created_by);
                    }
                    $wrap.append('<div class="small text-muted mt-1">' + metaText.join(' • ') + '</div>');
                }

                $li.append($wrap);
                if (q.isBest) {
                    $li.append('<span class="badge bg-warning text-dark">Best</span>');
                }
                $list.append($li);
            });

            var modalEl = document.getElementById('attemptsModal');
            // Cleanup helper to defensively remove any leftover overlays and restore body state
            function _cleanupModalBackdrops() {
                try {
                    // Remove Bootstrap backdrops
                    document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
                    // Remove DataTables Responsive modal background if any
                    document.querySelectorAll('.dtr-modal-background, .dtr-modal').forEach(function(b){ b.remove(); });
                    // Ensure modal-open class removed from body and restore scrolling
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                } catch (e) {
                    console.warn('[AttemptsModal] cleanup error', e);
                }
            }

            var modal = new bootstrap.Modal(modalEl);
            // Attach hidden handler to do a defensive cleanup after bootstrap completes hide
            modalEl.addEventListener('hidden.bs.modal', function(){ setTimeout(_cleanupModalBackdrops, 10); }, { once: true });
            modal.show();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAttemptsModal);
    } else {
        initAttemptsModal();
    }
})();
</script>

<!-- Native fallback: ensure clicks on .btn-view-all are handled even if jQuery/bootstrap init hasn't run -->
<script>
document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.btn-view-all');
    if (!btn) return;
    try {
        console.log('[AttemptsModal][native] btn clicked');
        e.preventDefault();
        e.stopPropagation();
        var data = btn.getAttribute('data-quizzes');
        if (!data) {
            console.warn('[AttemptsModal][native] no data-quizzes');
            return;
        }
        // decode HTML entities if present
        try {
            var quizzes = JSON.parse(data);
        } catch (err) {
            var ta = document.createElement('textarea');
            ta.innerHTML = data;
            var decoded = ta.value;
            try {
                quizzes = JSON.parse(decoded);
            } catch (err2) {
                console.error('[AttemptsModal][native] parse failed', err, err2);
                return;
            }
        }

        // Build list HTML
        var listEl = document.getElementById('attemptsModalList');
        if (listEl) {
            listEl.innerHTML = '';
                quizzes.forEach(function(q){
                var li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-start';
                var left = document.createElement('div');
                left.style.flex = '1 1 auto';
                var a = document.createElement('a');
                a.className = 'me-3 d-block';
                a.href = (typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : (window.location.origin || (window.location.protocol + '//' + window.location.host) ) + '/') + 'teacher/dashboard/studentQuiz/' + q.id;
                a.textContent = q.label;
                a.addEventListener('click', function(ev){
                    ev.preventDefault();
                    ev.stopPropagation();
                    try {
                        // Hide bootstrap modal if available, otherwise remove any native overlay
                        var modalEl = document.getElementById('attemptsModal');
                        if (window.bootstrap && bootstrap.Modal && modalEl) {
                            var inst = bootstrap.Modal.getInstance(modalEl);
                            if (inst && typeof inst.hide === 'function') {
                                    inst.hide();
                                } else {
                                    modalEl.classList.remove('show');
                                    document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
                                    document.querySelectorAll('.dtr-modal-background, .dtr-modal').forEach(function(b){ b.remove(); });
                                    document.body.classList.remove('modal-open');
                                    document.body.style.overflow = '';
                                    document.body.style.paddingRight = '';
                                }
                        } else {
                            var existingOverlay = document.getElementById('simpleAttemptsOverlay');
                            if (existingOverlay && existingOverlay.parentNode) existingOverlay.parentNode.removeChild(existingOverlay);
                        }
                    } catch (ex) {
                        console.warn('[AttemptsModal][native] error hiding modal/overlay before navigate', ex);
                    }
                    if (typeof loadPage === 'function') { loadPage(a.href); } else { window.location.href = a.href; }
                });
                left.appendChild(a);
                // metadata
                if (q.snapshot_at || q.created_by_name || q.created_by) {
                    var metaDiv = document.createElement('div');
                    metaDiv.className = 'small text-muted mt-1';
                    var parts = [];
                    if (q.snapshot_at) {
                        try {
                            var d = new Date(q.snapshot_at);
                            parts.push('Snapshot: ' + d.toLocaleString());
                        } catch(e) { parts.push('Snapshot: ' + q.snapshot_at); }
                    }
                    if (q.created_by_name) parts.push('By ' + q.created_by_name);
                    else if (q.created_by) parts.push('By teacher #' + q.created_by);
                    metaDiv.textContent = parts.join(' • ');
                    left.appendChild(metaDiv);
                }

                li.appendChild(left);
                if (q.isBest) {
                    var span = document.createElement('span');
                    span.className = 'badge bg-warning text-dark';
                    span.textContent = 'Best';
                    li.appendChild(span);
                }
                listEl.appendChild(li);
            });
        }

        // Show modal if bootstrap available
        if (window.bootstrap && bootstrap.Modal) {
            var modalEl = document.getElementById('attemptsModal');
            // defensive cleanup helper
            function _cleanupModalBackdrops_native() {
                try {
                    document.querySelectorAll('.modal-backdrop').forEach(function(b){ b.remove(); });
                    document.querySelectorAll('.dtr-modal-background, .dtr-modal').forEach(function(b){ b.remove(); });
                    document.body.classList.remove('modal-open');
                    document.body.style.overflow = '';
                    document.body.style.paddingRight = '';
                } catch (e) { console.warn('[AttemptsModal][native] cleanup error', e); }
            }
            var modal = new bootstrap.Modal(modalEl);
            modalEl.addEventListener('hidden.bs.modal', function(){ setTimeout(_cleanupModalBackdrops_native, 10); }, { once: true });
            modal.show();
            return;
        }

        // If bootstrap is not available, show a simple native modal overlay instead of navigating away
        if (quizzes && quizzes.length) {
            showSimpleModal(quizzes);
            return;
        }
        
        function showSimpleModal(quizzes) {
            // Remove existing overlay if present
            var existing = document.getElementById('simpleAttemptsOverlay');
            if (existing) existing.remove();

            var overlay = document.createElement('div');
            overlay.id = 'simpleAttemptsOverlay';
            overlay.style.position = 'fixed';
            overlay.style.top = '0';
            overlay.style.left = '0';
            overlay.style.width = '100%';
            overlay.style.height = '100%';
            overlay.style.background = 'rgba(0,0,0,0.6)';
            overlay.style.zIndex = '20000';
            overlay.style.display = 'flex';
            overlay.style.alignItems = 'center';
            overlay.style.justifyContent = 'center';

            var box = document.createElement('div');
            box.style.background = '#fff';
            box.style.borderRadius = '6px';
            box.style.width = '720px';
            box.style.maxWidth = '95%';
            box.style.maxHeight = '80%';
            box.style.overflow = 'auto';
            box.style.boxShadow = '0 10px 30px rgba(0,0,0,0.3)';
            box.style.padding = '16px';

            var title = document.createElement('h5');
            title.textContent = 'Attempts';
            title.style.marginTop = '0';
            box.appendChild(title);

            var list = document.createElement('ul');
            list.className = 'list-group';
            list.id = 'simpleAttemptsList';
            quizzes.forEach(function(q){
                var li = document.createElement('li');
                li.className = 'list-group-item d-flex justify-content-between align-items-center';
                var a = document.createElement('a');
                a.className = 'me-3';
                a.href = (typeof window.APP_BASE !== 'undefined' ? window.APP_BASE : (window.location.origin || (window.location.protocol + '//' + window.location.host) ) + '/') + 'teacher/dashboard/studentQuiz/' + q.id;
                a.textContent = q.label;
                a.addEventListener('click', function(ev){
                    ev.preventDefault(); ev.stopPropagation();
                    try {
                        var existingOverlay = document.getElementById('simpleAttemptsOverlay');
                        if (existingOverlay && existingOverlay.parentNode) existingOverlay.parentNode.removeChild(existingOverlay);
                    } catch (ex) { /* ignore */ }
                    if (typeof loadPage === 'function') { loadPage(a.href); } else { window.location.href = a.href; }
                });
                li.appendChild(a);
                if (q.isBest) {
                    var span = document.createElement('span');
                    span.className = 'badge bg-warning text-dark';
                    span.textContent = 'Best';
                    li.appendChild(span);
                }
                list.appendChild(li);
            });
            box.appendChild(list);

            var footer = document.createElement('div');
            footer.style.textAlign = 'right';
            footer.style.marginTop = '12px';
            var closeBtn = document.createElement('button');
            closeBtn.className = 'btn btn-secondary';
            closeBtn.textContent = 'Close';
            closeBtn.addEventListener('click', function(){ overlay.remove(); });
            footer.appendChild(closeBtn);
            box.appendChild(footer);

            overlay.appendChild(box);
            document.body.appendChild(overlay);
        }
    } catch (ex) {
        console.error('[AttemptsModal][native] error', ex);
    }
});
</script>

<script>
// Bulk Actions Functionality for Assessments Summary
(function() {
    window.initBulkActionsAssessments = function() {
        if (!window.jQuery) {
            return;
        }
        var $ = window.jQuery;
        // Only run on Assessments Summary page
        if (!$('.assessment-checkbox').length && !$('#selectAllAssessments').length) {
            return;
        }
        console.info('[Assessments] initBulkActionsAssessments called');

        function tableApi() {
            if ($.fn.DataTable && $.fn.DataTable.isDataTable('.defaultDataTable')) {
                return $('.defaultDataTable').DataTable();
            }
            return null;
        }

        function visibleCheckboxes() {
            var dt = tableApi();
            if (dt) {
                return $(dt.rows({ page: 'current' }).nodes()).find('.assessment-checkbox');
            }
            return $('.assessment-checkbox');
        }

        function updateBulkActionsBarAssessments() {
            var selectedCount = $('.assessment-checkbox:checked').length;
            if (selectedCount > 0) {
                $('.bulk-actions-bar-assessments').show();
                $('.selected-count-assessments').text(selectedCount + ' selected');
            } else {
                $('.bulk-actions-bar-assessments').hide();
            }
        }

        // Prevent DataTables header click from sorting when toggling select-all / row checkboxes
        $(document).off('click.bulkactionsstop', '#selectAllAssessments, .assessment-checkbox').on('click.bulkactionsstop', '#selectAllAssessments, .assessment-checkbox', function(e) {
            e.stopPropagation();
        });

        // Select All checkbox for assessments - use event delegation (attach once)
        $(document).off('change.bulkactions', '#selectAllAssessments').on('change.bulkactions', '#selectAllAssessments', function() {
            var isChecked = $(this).prop('checked');
            visibleCheckboxes().prop('checked', isChecked);
            updateBulkActionsBarAssessments();
        });

        // Individual checkbox for assessments - use event delegation
        $(document).off('change.bulkactions', '.assessment-checkbox').on('change.bulkactions', '.assessment-checkbox', function() {
            var $visible = visibleCheckboxes();
            var totalCheckboxes = $visible.length;
            var checkedCheckboxes = $visible.filter(':checked').length;
            $('#selectAllAssessments').prop('checked', totalCheckboxes === checkedCheckboxes);
            updateBulkActionsBarAssessments();
        });

        // Clear selection for assessments - use event delegation
        $(document).off('click.bulkactions', '.bulk-deselect-assessments').on('click.bulkactions', '.bulk-deselect-assessments', function() {
            $('.assessment-checkbox, #selectAllAssessments').prop('checked', false);
            updateBulkActionsBarAssessments();
        });

        function ensureDataTableSync(attempts) {
            attempts = attempts || 0;
            var dtSync = tableApi();
            if (dtSync) {
                dtSync.off('draw.bulkactions').on('draw.bulkactions', function() {
                    var $visible = visibleCheckboxes();
                    var totalCheckboxes = $visible.length;
                    var checkedCheckboxes = $visible.filter(':checked').length;
                    $('#selectAllAssessments').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                });
                var $visible = visibleCheckboxes();
                var totalCheckboxes = $visible.length;
                var checkedCheckboxes = $visible.filter(':checked').length;
                $('#selectAllAssessments').prop('checked', totalCheckboxes > 0 && totalCheckboxes === checkedCheckboxes);
                updateBulkActionsBarAssessments();
                return;
            }
            if (attempts < 20) {
                setTimeout(function(){ ensureDataTableSync(attempts+1); }, 100);
            }
        }

        ensureDataTableSync();

        // Re-sync after AJAX page loads
        $(document).off('genta:page-ready.assessments').on('genta:page-ready.assessments', function(){
            ensureDataTableSync(0);
        });

        // Print Functionality for Assessments is now handled globally in script.js using printViaIframe
        // No duplicate handler needed here

        // Export CSV - use event delegation
        $(document).on('click', '#exportAssessmentsCSV', function() {
            exportAssessmentsToCSV();
        });

        // Export Excel - use event delegation
        $(document).on('click', '#exportAssessmentsExcel', function() {
            exportAssessmentsToExcel();
        });

        function getAssessmentsData() {
            var data = [];
            var checkedOnly = $('.assessment-checkbox:checked').length > 0;
            var selector = checkedOnly ? '.assessment-checkbox:checked' : '.assessment-checkbox';
            
            $(selector).each(function() {
                var $checkbox = $(this);
                data.push({
                    lrn: $checkbox.data('lrn'),
                    name: $checkbox.data('name'),
                    grade: $checkbox.data('grade'),
                    subject: $checkbox.data('subject'),
                    version: $checkbox.data('version'),
                    attempts: $checkbox.data('attempts'),
                    latest: $checkbox.data('latest'),
                    best: $checkbox.data('best')
                });
            });
            return data;
        }

        function exportAssessmentsToCSV() {
            var data = getAssessmentsData();
            var csv = 'LRN,Name,Grade-Section,Subject,Version,Attempts,Latest Score,Best Score\n';
            data.forEach(function(row) {
                csv += '"' + String(row.lrn).replace(/"/g, '""') + '","' + 
                       String(row.name).replace(/"/g, '""') + '","' + 
                       String(row.grade).replace(/"/g, '""') + '","' + 
                       String(row.subject).replace(/"/g, '""') + '","' + 
                       String(row.version).replace(/"/g, '""') + '","' + 
                       String(row.attempts).replace(/"/g, '""') + '","' + 
                       String(row.latest).replace(/"/g, '""') + '","' + 
                       String(row.best).replace(/"/g, '""') + '"\n';
            });
            
            var blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'assessments_report_' + new Date().getTime() + '.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function exportAssessmentsToExcel() {
            var data = getAssessmentsData();
            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
            html += '<head><meta charset="utf-8"><style>table { border-collapse: collapse; } th, td { border: 1px solid #ddd; padding: 8px; } th { background-color: #4B49AC; color: white; }</style></head>';
            html += '<body><h2>Students\' Assessments Summary Report</h2><p>Generated: ' + new Date().toLocaleString() + '</p>';
            html += '<table><thead><tr><th>LRN</th><th>Name</th><th>Grade-Section</th><th>Subject</th><th>Version</th><th>Attempts</th><th>Latest Score</th><th>Best Score</th></tr></thead><tbody>';
            data.forEach(function(row) {
                html += '<tr><td>' + escapeHtml(row.lrn) + '</td><td>' + escapeHtml(row.name) + '</td><td>' + 
                        escapeHtml(row.grade) + '</td><td>' + escapeHtml(row.subject) + '</td><td>' + 
                        escapeHtml(row.version) + '</td><td>' + escapeHtml(row.attempts) + '</td><td>' + 
                        escapeHtml(row.latest) + '</td><td>' + escapeHtml(row.best) + '</td></tr>';
            });
            html += '</tbody></table></body></html>';
            
            var blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            var link = document.createElement('a');
            var url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'assessments_report_' + new Date().getTime() + '.xls');
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

        function generateAssessmentsPrintContent() {
            var today = new Date().toLocaleDateString();
            var rows = '';
            var checkedOnly = $('.assessment-checkbox:checked').length > 0;
            
            var selector = checkedOnly ? '.assessment-checkbox:checked' : '.assessment-checkbox';
            $(selector).each(function() {
                var $checkbox = $(this);
                rows += '<tr><td>' + escapeHtml($checkbox.data('lrn')) + '</td><td>' + 
                        escapeHtml($checkbox.data('name')) + '</td><td>' + 
                        escapeHtml($checkbox.data('grade')) + '</td><td>' + 
                        escapeHtml($checkbox.data('subject')) + '</td><td>' + 
                        escapeHtml($checkbox.data('version')) + '</td><td>' + 
                        escapeHtml($checkbox.data('attempts')) + '</td><td>' + 
                        escapeHtml($checkbox.data('latest')) + '</td><td>' + 
                        escapeHtml($checkbox.data('best')) + '</td></tr>';
            });

            return `
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Students' Assessments Summary Report</title>
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
                        <h1>Students' Assessments Summary Report</h1>
                        <p>GENTA Learning Management System</p>
                    </div>
                    <div class="date">Generated: ${today}</div>
                    <table>
                        <thead>
                            <tr>
                                <th>LRN</th>
                                <th>Name</th>
                                <th>Grade-Section</th>
                                <th>Subject</th>
                                <th>Version</th>
                                <th>Attempts</th>
                                <th>Latest Score</th>
                                <th>Best Score</th>
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
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', window.initBulkActionsAssessments);
    } else {
        window.initBulkActionsAssessments();
    }
})();
</script>