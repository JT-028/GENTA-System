<!-- HEADER WITH BACK BUTTON -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="page-title mb-0">
        <span class="page-title-icon bg-gradient-primary text-white me-2">
            <i class="mdi mdi-file-document-outline"></i>
        </span> Student Assessment Details
    </h3>
    <a href="<?= $this->Url->build(['controller' => 'Dashboard', 'action' => 'index', 'prefix' => 'Teacher']) ?>" class="btn btn-gradient-primary btn-rounded">
        <i class="mdi mdi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<!-- TABLE -->
<div class="row">
    <?php
    // Debug: Check if student_quiz_questions exists and has data
    if (empty($studentQuiz->student_quiz_questions)) {
        echo '<div class="col-12"><div class="alert alert-warning">No quiz questions found. Data: ' . 
             json_encode($studentQuiz) . '</div></div>';
    }
    ?>
    <!-- STUDENT DETAILS -->
    <div class="col-xl-5 col-lg-6 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Student Details</h4>
                <table class="table">
                    <tbody>
                        <tr>
                            <td class="fw-bold">Name</td>
                            <td><?= h($studentQuiz->student->name) ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">LRN (Learner Reference Number)</td>
                            <td><?= $studentQuiz->student->lrn ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Grade - Section</td>
                            <td><?= $studentQuiz->student->grade_section ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Subject</td>
                            <td><?= $studentQuiz->subject->name ?></td>
                        </tr>
                        <tr>
                            <td class="fw-bold">Date</td>
                            <td><?= $studentQuiz->created->format('Y/m/d') ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <!-- RESULTS -->
    <div class="col-xl-7 col-lg-6 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Results</h4>
                <div class="row">
                    <?php foreach($studentQuiz->student_quiz_questions as $key => $question) { ?>
                        <div class="col-xl-2 col-lg-3 col-sm-2 col-xs-3">
                            <table class="table table-bordered mb-3">
                                <tbody>
                                    <tr>
                                        <td class="text-center"><p class="fw-bold m-0">Q<?= $key + 1 ?></p></td>
                                    </tr>
                                    <tr>
                                        <td class="text-center">
                                            <p class="h3 text-<?= $question->is_correct ? 'success' : 'danger' ?> m-0"><i class="mdi mdi-<?= $question->is_correct ? 'check' : 'close' ?>-circle-outline"></i></p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
    <!-- QUESTIONS -->
    <div class="col-12 grid-margin">
        <div class="card">
            <div class="card-body">
                <h4 class="card-title">Questions</h4>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th width="40%">Question</th>
                                <th width="15%" class="text-center">Correct Answer</th>
                                <th width="15%" class="text-center">Student's Answer</th>
                                <th width="15%" class="text-center">Evaluation</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($studentQuiz->student_quiz_questions as $key => $question) { ?>
                                <tr class="table-<?= $question->is_correct ? 'success' : 'danger' ?>">
                                    <td class="text-wrap">Q<?= $key + 1 ?>. <?= $question->description ?></td>
                                    <td class="text-center"><?= $question->answer ?></td>
                                    <td class="text-center"><?= $question->student_answer ?></td>
                                    <td class="text-center">
                                        <p class="h3 text-<?= $question->is_correct ? 'success' : 'danger' ?> m-0"><i class="mdi mdi-<?= $question->is_correct ? 'check' : 'close' ?>-circle-outline"></i></p>
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