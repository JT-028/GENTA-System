<?php
declare(strict_types=1);

namespace App\Controller\Teacher;

use App\Controller\Teacher\AppController;
use Authentication\PasswordHasher\DefaultPasswordHasher;
use Cake\Utility\Security;
use Cake\Log\Log;

/**
 * Dashboard Controller
 *
 * @method \App\Model\Entity\Teacher\Dashboard[]|\Cake\Datasource\ResultSetInterface paginate($object = null, array $settings = [])
 */
class DashboardController extends AppController
{
    public $Users = null;
    public $StudentQuiz = null;
    public $Students = null;
    public $Questions = null;
    public $Subjects = null;

    // STUDENT QUIZ LIST
    public function index()
    {
        $studentQuizTable = $this->loadModel('StudentQuiz');
        $studentsTable = $this->loadModel('Students');
        $questionsTable = $this->loadModel('Questions');

        $userSession = $this->Authentication->getIdentity();

        // Only show quizzes that belong to this teacher's students
        $studentQuizzes = $studentQuizTable->find()
            ->contain(['Students', 'Subjects', 'StudentQuizQuestions', 'QuizVersions'])
            ->innerJoinWith('Students', function ($q) use ($userSession) {
                if ($userSession && isset($userSession->id)) {
                    return $q->where(['Students.teacher_id' => $userSession->id]);
                }
                return $q;
            })
            ->order(['StudentQuiz.created' => 'DESC'])
            ->all();

        // Group assessments by student and subject, showing summary with attempts
        $groupedAssessments = [];
        foreach ($studentQuizzes as $quiz) {
            $studentId = $quiz->student->id ?? 0;
            $subjectId = $quiz->subject_id ?? 0;
            $key = $studentId . '_' . $subjectId;
            
            if (!isset($groupedAssessments[$key])) {
                $groupedAssessments[$key] = [
                    'student' => $quiz->student,
                    'subject' => $quiz->subject,
                    'attempts' => 0,
                    'latest_quiz' => null,
                    'latest_score_raw' => 0,
                    'latest_score_total' => 0,
                    'best_score_raw' => 0,
                    'best_score_total' => 0,
                    'all_quizzes' => []
                ];
            }
            
            $groupedAssessments[$key]['attempts']++;
            $groupedAssessments[$key]['all_quizzes'][] = $quiz;
            
            // Parse score
            if (isset($quiz->score['overallScore'])) {
                $scoreData = explode('/', $quiz->score['overallScore']);
                if (count($scoreData) == 2) {
                    $score = (int)$scoreData[0];
                    $total = (int)$scoreData[1];
                    
                    // Update latest (first in DESC order)
                    if ($groupedAssessments[$key]['latest_quiz'] === null) {
                        $groupedAssessments[$key]['latest_quiz'] = $quiz;
                        $groupedAssessments[$key]['latest_score_raw'] = $score;
                        $groupedAssessments[$key]['latest_score_total'] = $total;
                    }
                    
                    // Update best score
                    if ($total > 0) {
                        $currentBestPercent = $groupedAssessments[$key]['best_score_total'] > 0 
                            ? ($groupedAssessments[$key]['best_score_raw'] / $groupedAssessments[$key]['best_score_total']) * 100 
                            : 0;
                        $thisPercent = ($score / $total) * 100;
                        
                        if ($thisPercent > $currentBestPercent) {
                            $groupedAssessments[$key]['best_score_raw'] = $score;
                            $groupedAssessments[$key]['best_score_total'] = $total;
                        }
                    }
                }
            }
        }

        // Calculate statistics
        $totalStudents = $studentsTable->find()
            ->where(['teacher_id' => $userSession->id])
            ->count();

        $totalAssessments = $studentQuizzes->count();

        $totalQuestions = $questionsTable->find()
            ->where(['teacher_id' => $userSession->id])
            ->count();

        // Calculate average score
        $totalScore = 0;
        $scoreCount = 0;
        foreach ($studentQuizzes as $quiz) {
            if (isset($quiz->score['overallScore'])) {
                $scoreData = explode('/', $quiz->score['overallScore']);
                if (count($scoreData) == 2) {
                    $score = (int)$scoreData[0];
                    $total = (int)$scoreData[1];
                    if ($total > 0) {
                        $totalScore += ($score / $total) * 100;
                        $scoreCount++;
                    }
                }
            }
        }
        $averageScore = $scoreCount > 0 ? round($totalScore / $scoreCount, 1) : 0;

        // Collect creator IDs from quiz_versions referenced in grouped assessments
        $creatorIds = [];
        foreach ($groupedAssessments as $ga) {
            if (!empty($ga['all_quizzes']) && is_array($ga['all_quizzes'])) {
                foreach ($ga['all_quizzes'] as $q) {
                    if (!empty($q->quiz_version)) {
                        $cv = $q->quiz_version;
                        // prefer explicit created_by column
                        if (!empty($cv->created_by)) {
                            $creatorIds[] = (int)$cv->created_by;
                        } elseif (!empty($cv->metadata)) {
                            $meta = @json_decode($cv->metadata, true);
                            if (is_array($meta)) {
                                if (!empty($meta['teacher_id'])) $creatorIds[] = (int)$meta['teacher_id'];
                                if (!empty($meta['created_by'])) $creatorIds[] = (int)$meta['created_by'];
                            }
                        }
                    }
                }
            }
        }
        $creatorIds = array_values(array_unique(array_filter($creatorIds)));
        $creatorNames = [];
        if (!empty($creatorIds)) {
            $usersTable = $this->loadModel('Users');
            $users = $usersTable->find()->select(['id', 'full_name'])->where(['id IN' => $creatorIds])->all();
            foreach ($users as $u) {
                $creatorNames[(int)$u->id] = $u->full_name;
            }
        }

        $this->set(compact('groupedAssessments', 'totalStudents', 'totalAssessments', 'totalQuestions', 'averageScore', 'creatorNames'));
    }

    // STUDENT QUIZ DATA
    public function studentQuiz($studentQuizIDHash)
    {
        $studentQuizID = $this->Decrypt->hex($studentQuizIDHash);

        $studentQuizTable = $this->loadModel('StudentQuiz');

        $studentQuiz = $studentQuizTable->get($studentQuizID, [
            'contain' => [
                'Students', 'Subjects', 'StudentQuizQuestions', 'QuizVersions'
            ]
        ]);

        // Ensure the authenticated teacher owns this student's quiz
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if (empty($studentQuiz->student) || ($studentQuiz->student->teacher_id ?? null) != $userSession->id) {
                throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to view this quiz.');
            }
        }

        $this->set(compact('studentQuiz'));
    }

    // STUDENT LIST
    public function students()
    {
        $studentsTable = $this->loadModel('Students');

        $userSession = $this->Authentication->getIdentity();

        // Scope students to the authenticated teacher using the table finder
        if ($userSession && isset($userSession->id)) {
            $students = $studentsTable->find('owned', ['ownerId' => $userSession->id]);
        } else {
            $students = $studentsTable->find();
        }

        $this->set(compact('students'));
    }

    /**
     * Add a new student
     */
    public function addStudent()
    {
        $studentsTable = $this->loadModel('Students');
        $student = $studentsTable->newEmptyEntity();

        // If requested via AJAX (GET), return the form fragment without layout
        if ($this->request->is('ajax') && $this->request->is('get')) {
            // Return only the element fragment for AJAX requests (no layout)
            $this->viewBuilder()->setLayout(null);
            $this->set(compact('student'));
            // Render the element template as the response body for AJAX requests
            // Use controller render to return the element fragment (layout already disabled)
            return $this->render('/element/student_form');
        }

        // If a user navigates directly to the add page (non-AJAX GET), redirect to students list
        // with a query parameter so the client can auto-open the Add modal.
        if ($this->request->is('get') && !$this->request->is('ajax')) {
            return $this->redirect([
                'controller' => 'Dashboard',
                'action' => 'students',
                'prefix' => 'Teacher',
                '?' => ['open' => 'add']
            ]);
        }

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            // Normalize data if form was submitted with a 'student' wrapper
            if (isset($data['student']) && is_array($data['student'])) {
                $data = $data['student'];
            }

            // Prevent duplicate lrn on create
            $lrn = isset($data['lrn']) ? trim((string)$data['lrn']) : '';
            if ($lrn && $studentsTable->exists(['lrn' => $lrn])) {
                if ($this->request->is('ajax')) {
                    $payload = [
                        'success' => false,
                        'message' => 'A student with that LRN already exists.',
                        'errors' => ['lrn' => ['A student with that LRN already exists.']]
                    ];
                    return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                }
                $this->Flash->error(__('A student with that LRN already exists.'));
                return $this->redirect(['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher']);
            }

            $student = $studentsTable->patchEntity($student, $data);

            // Assign the currently authenticated teacher as the owner/creator of the student
            $userSession = $this->Authentication->getIdentity();
            if ($userSession && isset($userSession->id)) {
                $student->teacher_id = $userSession->id;
            }

            if (!$student->hasErrors()) {
                try {
                    $saved = $studentsTable->save($student);
                } catch (\Throwable $e) {
                    if ($this->request->is('ajax')) {
                        $payload = [
                            'success' => false,
                            'message' => 'Exception saving student: ' . $e->getMessage(),
                            'trace' => (defined('DEBUG') && DEBUG) ? $e->getTraceAsString() : null,
                        ];
                        return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                    }
                    // Non-AJAX: rethrow so Cake handles it
                    throw $e;
                }

                if ($saved) {
                // If AJAX request, return JSON
                if ($this->request->is('ajax')) {
                    $payload = [
                        'success' => true,
                        'message' => 'Student added successfully.',
                    'student' => [
                'id' => $this->Encrypt->hex($student->id),
                'name' => $student->name,
                'lrn' => $student->lrn,
                'grade' => $student->grade,
                'section' => $student->section,
                'grade_section' => $student->grade_section,
            ]
                    ];
                    return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                }

                $this->Flash->success(__('Student added successfully.'));
                return $this->redirect(['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher']);
                }
            }

            if ($this->request->is('ajax')) {
                $payload = [
                    'success' => false,
                    'message' => 'Error adding student. Please check the form and try again.',
                    'errors' => $student->getErrors(),
                ];
                return $this->response->withType('application/json')->withStringBody(json_encode($payload));
            }

            $this->Flash->error(__('Error adding student. Please check the form and try again.'));
        }

        $this->set(compact('student'));
    }

    /**
     * Edit an existing student
     * @param string|null $studentIDHash
     */
    public function editStudent($studentIDHash = null)
    {
        if (!$studentIDHash) {
            return $this->redirect(['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher']);
        }

        $studentID = $this->Decrypt->hex($studentIDHash);
        $studentsTable = $this->loadModel('Students');

        $student = $studentsTable->get($studentID);

        // Verify ownership - only the teacher who owns the student may edit
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if (($student->teacher_id ?? null) != $userSession->id) {
                throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to edit this student.');
            }
        }

        // If requested via AJAX (GET), return the form fragment without layout
        if ($this->request->is('ajax') && $this->request->is('get')) {
            // Return only the element fragment for AJAX requests (no layout)
            $this->viewBuilder()->setLayout(null);
            $this->set(compact('student'));
            // Render the element template as the response body for AJAX requests
            return $this->render('/element/student_form');
        }

        // If a user navigates directly to the edit page (non-AJAX GET), redirect to students list
        // with a query parameter so the client can auto-open the Edit modal for the given id.
        if ($this->request->is('get') && !$this->request->is('ajax')) {
            return $this->redirect([
                'controller' => 'Dashboard',
                'action' => 'students',
                'prefix' => 'Teacher',
                '?' => ['open' => 'edit', 'id' => $studentIDHash]
            ]);
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $data = $this->request->getData();
            // Normalize data if form was submitted with a 'student' wrapper
            if (isset($data['student']) && is_array($data['student'])) {
                $data = $data['student'];
            }

            // Prevent duplicate lrn on update (exclude current id)
            $lrn = isset($data['lrn']) ? trim((string)$data['lrn']) : '';
                if ($lrn) {
                    $exists = $studentsTable->find()
                        ->where(['lrn' => $lrn])
                        ->andWhere(function($exp, $q) use ($student) {
                            return $exp->notEq('id', $student->id);
                        })
                        ->count();
                if ($exists) {
                    if ($this->request->is('ajax')) {
                        $payload = [
                            'success' => false,
                            'message' => 'Another student with that LRN already exists.',
                            'errors' => ['lrn' => ['Another student with that LRN already exists.']]
                        ];
                        return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                    }
                    $this->Flash->error(__('Another student with that LRN already exists.'));
                    return $this->redirect(['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher']);
                }
            }

            $student = $studentsTable->patchEntity($student, $data);
            if (!$student->hasErrors()) {
                try {
                    $saved = $studentsTable->save($student);
                } catch (\Throwable $e) {
                    if ($this->request->is('ajax')) {
                        $payload = [
                            'success' => false,
                            'message' => 'Exception updating student: ' . $e->getMessage(),
                            'trace' => (defined('DEBUG') && DEBUG) ? $e->getTraceAsString() : null,
                        ];
                        return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                    }
                    throw $e;
                }

                if ($saved) {
                if ($this->request->is('ajax')) {
                    $payload = [
                        'success' => true,
                        'message' => 'Student updated successfully.',
                                'student' => [
                                'id' => $this->Encrypt->hex($student->id),
                                'name' => $student->name,
                                'lrn' => $student->lrn,
                                'grade' => $student->grade,
                                'section' => $student->section,
                                'grade_section' => $student->grade_section,
                            ]
                    ];
                    return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                }

                $this->Flash->success(__('Student updated successfully.'));
                return $this->redirect(['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher']);
                }
            }

            if ($this->request->is('ajax')) {
                $payload = [
                    'success' => false,
                    'message' => 'Error updating student. Please check the form and try again.',
                    'errors' => $student->getErrors(),
                ];
                return $this->response->withType('application/json')->withStringBody(json_encode($payload));
            }

            $this->Flash->error(__('Error updating student. Please check the form and try again.'));
        }

        $this->set(compact('student'));
    }

    /**
     * Delete a student (POST/DELETE only)
     * @param string $studentIDHash
     */
    public function deleteStudent($studentIDHash)
    {
        $this->request->allowMethod(['post', 'delete']);

        $studentID = $this->Decrypt->hex($studentIDHash);
        $studentsTable = $this->loadModel('Students');

        $student = $studentsTable->get($studentID);

        // Verify ownership before deleting
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if (($student->teacher_id ?? null) != $userSession->id) {
                throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to delete this student.');
            }
        }

        if ($studentsTable->delete($student)) {
            if ($this->request->is('ajax')) {
                $payload = ['success' => true, 'message' => 'Student deleted successfully.'];
                return $this->response->withType('application/json')->withStringBody(json_encode($payload));
            }
            $this->Flash->success(__('Student deleted successfully.'));
        } else {
            if ($this->request->is('ajax')) {
                $payload = ['success' => false, 'message' => 'Error deleting student.'];
                return $this->response->withType('application/json')->withStringBody(json_encode($payload));
            }
            $this->Flash->error(__('Error deleting student.'));
        }

        return $this->redirect(['controller' => 'Dashboard', 'action' => 'students', 'prefix' => 'Teacher']);
    }

    public function student($studentIDHash)
    {
        $studentID = $this->Decrypt->hex($studentIDHash);

        $studentsTable = $this->loadModel('Students');

        $student = $studentsTable->get($studentID, [
            'contain' => [
                'StudentQuiz.StudentQuizQuestions', 'StudentQuiz.Subjects', 'StudentQuiz.QuizVersions'
            ]
        ]);

        // Verify ownership before allowing view/update
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if (($student->teacher_id ?? null) != $userSession->id) {
                throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to view this student.');
            }
        }

        if ($this->request->is('post'))
        {
            $student = $studentsTable->patchEntity($student, [
                'remarks' => $this->request->getData('remarks')
            ]);

            if ($studentsTable->save($student))
            {
                $this->Flash->success(__('Student details updated successfully!'));

            } else {

                $this->Flash->error(__('Error updating student details.'));
            }
        }

        $this->set(compact('student'));
    }

    public function questions()
    {
        $questionsTable = $this->loadModel('Questions');
        $subjectsTable = $this->loadModel('Subjects');

        $userSession = $this->Authentication->getIdentity();

        // DROPDOWN SELECTIONS
        $subjectOptions = $subjectsTable->searchOptions();

        // QUESTIONS FILTERS
        $quesSubjectSel = $this->request->getQuery('questionsSubject') ?: 'All';
        $quesSubjectCond = $subjectsTable->searchSelection($quesSubjectSel);

        // Show all questions belonging to the teacher regardless of status (active/suspended).
        if ($userSession && isset($userSession->id)) {
            // disable the automatic status filter in QuestionsTable::beforeFind by passing status_filter => false
            $questions = $questionsTable->find('owned', ['ownerId' => $userSession->id, 'status_filter' => false])
                ->where(['Questions.subject_id IN ' => $quesSubjectCond])
                ->contain(['Subjects']);
        } else {
            $questions = $questionsTable->find()
                ->where(['Questions.subject_id IN ' => $quesSubjectCond])
                ->contain(['Subjects']);
        }

        $this->set(compact('quesSubjectSel', 'subjectOptions', 'questions'));
    }

    public function createEditQuestion($questionIDHash = NULL)
    {
        try {
            $questionsTable = $this->loadModel('Questions');
            $subjectsTable = $this->loadModel('Subjects');

            $userSession = $this->Authentication->getIdentity();

            // DROPDOWN SELECTIONS
            $subjectOptions = $subjectsTable->searchOptions(false);

            if ($questionIDHash)
            {
                $questionID = $this->Decrypt->hex($questionIDHash);

                // Use a find() with 'status_filter' => false to allow loading questions
                // that may be suspended or have a non-active status. Using get()
                // will apply the default status filter in beforeFind and may throw
                // RecordNotFound for questions that exist but are not active.
                $question = $questionsTable->find()
                    ->where(['Questions.id' => $questionID])
                    ->applyOptions(['status_filter' => false])
                    ->contain(['Subjects'])
                    ->first();

                if (!$question) {
                    throw new \Cake\Datasource\Exception\RecordNotFoundException('Question not found');
                }

                // Verify ownership - only the teacher who created the question can edit it
                if ($userSession && isset($userSession->id)) {
                    if (($question->teacher_id ?? null) != $userSession->id) {
                        throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to edit this question.');
                    }
                }

            } else {

                $question = $questionsTable->newEmptyEntity();
            }

            if ($this->request->is('post'))
            {
                $question = $questionsTable->patchEntity($question, $this->request->getData());

                // If creating a new question, assign teacher_id to the authenticated user
                if ($question->isNew() && $userSession && isset($userSession->id)) {
                    $question->teacher_id = $userSession->id;
                }

                if (!$question->hasErrors())
                {
                    if ($questionsTable->save($question))
                    {
                        // Check if this is an AJAX request
                        if ($this->request->is('ajax')) {
                            return $this->response
                                ->withType('application/json')
                                ->withStringBody(json_encode([
                                    'success' => true,
                                    'message' => 'Question saved successfully!'
                                ]));
                        }
                        
                        $this->Flash->success(__('Question saved!'));
                        return $this->redirect(['controller' => 'Dashboard', 'action' => 'questions', 'prefix' => 'Teacher']);

                    } else {
                        
                        // Check if this is an AJAX request
                        if ($this->request->is('ajax')) {
                            return $this->response
                                ->withType('application/json')
                                ->withStringBody(json_encode([
                                    'success' => false,
                                    'message' => 'Error saving question.'
                                ]));
                        }

                        $this->Flash->error(__('Error saving question.'));
                    }
                } else {
                    // Return validation errors for AJAX requests
                    if ($this->request->is('ajax')) {
                        return $this->response
                            ->withType('application/json')
                            ->withStringBody(json_encode([
                                'success' => false,
                                'message' => 'Please fix the validation errors.',
                                'errors' => $question->getErrors()
                            ]));
                    }
                }
            }

            // For AJAX GET requests, render without layout
            $isAjax = $this->request->is('ajax') || 
                      $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
            
            if ($isAjax) {
                // Disable layout for AJAX requests. setLayout(false) causes a TypeError
                // because setLayout expects a string or null. Use disableAutoLayout()
                // which is the correct way to render without layout in CakePHP.
                $this->viewBuilder()->disableAutoLayout();
            }

            $this->set(compact('subjectOptions', 'question'));
            
        } catch (\Exception $e) {
            // Log the error
            \Cake\Log\Log::error('Error in createEditQuestion: ' . $e->getMessage());
            \Cake\Log\Log::error('Stack trace: ' . $e->getTraceAsString());
            
            // Return error response for AJAX
            $isAjax = $this->request->is('ajax') || 
                      $this->request->getHeaderLine('X-Requested-With') === 'XMLHttpRequest';
            
            if ($isAjax) {
                return $this->response
                    ->withType('application/json')
                    ->withStatus(500)
                    ->withStringBody(json_encode([
                        'success' => false,
                        'message' => 'Server error: ' . $e->getMessage()
                    ]));
            }
            
            throw $e;
        }
    }

    public function deleteQuestion($questionIDHash)
    {
        $this->request->allowMethod(['post']);
        
        // Decrypt the question ID
        $questionID = $this->Decrypt->hex($questionIDHash);
        if (!$questionID) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid question ID']));
        }

        $questionsTable = $this->loadModel('Questions');
        $userSession = $this->Authentication->getIdentity();
        
        // Get question without status filter (to allow deleting suspended questions)
        $connection = $questionsTable->getConnection();
        $question = $connection->execute(
            'SELECT id, teacher_id, status FROM questions WHERE id = ? LIMIT 1',
            [$questionID]
        )->fetch('assoc');
        
        if (!$question) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Question not found']));
        }
        
        // Check ownership
        if ($userSession && isset($userSession->id)) {
            if ((int)$question['teacher_id'] !== (int)$userSession->id) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Permission denied']));
            }
        }
        
        // Hard delete: permanently remove from database
        $result = $connection->execute(
            'DELETE FROM questions WHERE id = ?',
            [$questionID]
        );
        
        if ($result->rowCount() > 0) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => true, 'message' => 'Question deleted successfully']));
        }
        
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'message' => 'Failed to delete question']));
    }

    /**
     * Delete all assessments (student_quiz rows) for a given student and subject
     * @param string $studentIDHash
     * @param string $subjectIDHash
     */
    public function deleteAssessments($studentIDHash = null, $subjectIDHash = null)
    {
        $this->request->allowMethod(['post']);

        if (!$studentIDHash || !$subjectIDHash) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid parameters']));
        }

        $studentID = $this->Decrypt->hex($studentIDHash);
        $subjectID = $this->Decrypt->hex($subjectIDHash);

        if (!$studentID || !$subjectID) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid IDs']));
        }

        $studentsTable = $this->loadModel('Students');
        $student = $studentsTable->get($studentID);

        // Verify ownership
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if ((int)($student->teacher_id ?? 0) !== (int)$userSession->id) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Permission denied']));
            }
        }

        // Perform deletion using direct SQL for performance and to avoid ORM cascade issues
        $studentQuizTable = $this->loadModel('StudentQuiz');
        $connection = $studentQuizTable->getConnection();

        try {
            // Delete associated student_quiz_questions first
            $connection->execute(
                'DELETE sqq FROM student_quiz_questions sqq JOIN student_quiz sq ON sqq.student_quiz_id = sq.id WHERE sq.student_id = ? AND sq.subject_id = ?',
                [$studentID, $subjectID]
            );

            // Delete the student_quiz rows
            $result = $connection->execute(
                'DELETE FROM student_quiz WHERE student_id = ? AND subject_id = ?',
                [$studentID, $subjectID]
            );

            if ($result->rowCount() > 0) {
                // Attempt to also remove generated tailored module and analysis files if present.
                $deletedFiles = [];
                try {
                    $safeName = str_replace(' ', '_', $student->name);
                    $lrn = $student->lrn;
                    $tailoredBasename = 'tailored_module_' . $safeName . '_' . $lrn . '.docx';
                    $analysisBasename = 'analysis_result_' . $safeName . '_' . $lrn . '.docx';

                    // Common local locations to check
                    // Also include developer's OneDrive path where generated docs are stored
                    $possibleDirs = [
                        WWW_ROOT . 'uploads' . DS,
                        WWW_ROOT,
                        // Prefer the developer's OneDrive IoT MAIN_SYSTEM uploads folder (requested new location)
                        'C:\\Users\\vonti\\OneDrive\\Desktop\\GENTA_MAIN_SYSTEM_IoT\\MAIN_SYSTEM\\uploads\\',
                        // Also check older IoT location (kept for compatibility)
                        'C:\\Users\\vonti\\OneDrive\\Desktop\\GENTA_MAIN_SYSTEM_IoT\\uploads\\',
                        // Legacy OneDrive location kept as fallback
                        'C:\\Users\\vonti\\OneDrive\\Desktop\\GENTA SYS\\MAIN_SYSTEM\\uploads\\'
                    ];
                    foreach ($possibleDirs as $d) {
                        $f1 = $d . $tailoredBasename;
                        if (file_exists($f1)) {
                            @unlink($f1);
                            $deletedFiles[] = $f1;
                        }
                        $f2 = $d . $analysisBasename;
                        if (file_exists($f2)) {
                            @unlink($f2);
                            $deletedFiles[] = $f2;
                        }
                    }
                } catch (\Throwable $e) {
                    // Log and continue - file deletion is best-effort
                    Log::warning('Error while attempting to delete tailored/analysis files: ' . $e->getMessage());
                }

                $payload = ['success' => true, 'message' => 'Assessments deleted successfully'];
                if (!empty($deletedFiles)) $payload['deletedFiles'] = $deletedFiles;

                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode($payload));
            }

            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'No assessments found to delete']));
        } catch (\Throwable $e) {
            Log::error('Error deleting assessments: ' . $e->getMessage());
            return $this->response
                ->withType('application/json')
                ->withStatus(500)
                ->withStringBody(json_encode(['success' => false, 'message' => 'Server error deleting assessments']));
        }
    }

    /**
     * Check if a tailored module or analysis document exists for a student.
     * Returns JSON { exists: bool, url: string|null }
     * @param string|null $studentIDHash
     * @param string|null $type ('tailored'|'analysis')
     */
    public function checkDocument($studentIDHash = null, $type = null)
    {
        $this->request->allowMethod(['get', 'post']);

        if (!$studentIDHash || !$type) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => false, 'url' => null]));
        }

        $studentID = $this->Decrypt->hex($studentIDHash);
        if (!$studentID) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => false, 'url' => null]));
        }

        $studentsTable = $this->loadModel('Students');
        try {
            $student = $studentsTable->get($studentID);
        } catch (\Throwable $e) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => false, 'url' => null]));
        }

        // Verify ownership
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if ((int)($student->teacher_id ?? 0) !== (int)$userSession->id) {
                return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => false, 'url' => null]));
            }
        }

        $safeName = str_replace(' ', '_', $student->name);
        $lrn = $student->lrn;
        if ($type === 'tailored') {
            $basename = 'tailored_module_' . $safeName . '_' . $lrn . '.docx';
        } else {
            $basename = 'analysis_result_' . $safeName . '_' . $lrn . '.docx';
        }

        // Check common local locations first (include developer OneDrive folder)
        $possibleDirs = [
            WWW_ROOT . 'uploads' . DS,
            WWW_ROOT,
            // Prefer the developer's OneDrive IoT MAIN_SYSTEM uploads folder (requested new location)
            'C:\\Users\\vonti\\OneDrive\\Desktop\\GENTA_MAIN_SYSTEM_IoT\\MAIN_SYSTEM\\uploads\\',
            // Also check older IoT location (kept for compatibility)
            'C:\\Users\\vonti\\OneDrive\\Desktop\\GENTA_MAIN_SYSTEM_IoT\\uploads\\',
            // Legacy OneDrive location kept as fallback
            'C:\\Users\\vonti\\OneDrive\\Desktop\\GENTA SYS\\MAIN_SYSTEM\\uploads\\'
        ];
        foreach ($possibleDirs as $d) {
            $f = $d . $basename;
                if (file_exists($f)) {
                // Return a URL that points to our downloadDocument controller action
                // so we can securely stream files that may live outside webroot
                $url = \Cake\Routing\Router::url([
                    'prefix' => 'Teacher',
                    'controller' => 'Dashboard',
                    'action' => 'downloadDocument',
                    $studentIDHash,
                    $type
                ], true);
                return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => true, 'url' => $url]));
            }
        }

        // As a fallback, check remote URL used in the student template (best-effort)
        $remoteHost = 'https://nonbasic-bob-inimical.ngrok-free.dev/';
        $remoteUrl = rtrim($remoteHost, '/') . '/' . $basename;
        try {
            $hdrs = @get_headers($remoteUrl);
            if ($hdrs && is_array($hdrs)) {
                foreach ($hdrs as $h) {
                    if (stripos($h, '200') !== false) {
                        return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => true, 'url' => $remoteUrl]));
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore remote check errors
        }

        return $this->response->withType('application/json')->withStringBody(json_encode(['exists' => false, 'url' => null]));
    }

    /**
     * Stream a tailored module or analysis document for download.
     * Looks for the file in webroot/uploads, webroot, and the developer OneDrive folder.
     * @param string|null $studentIDHash
     * @param string|null $type
     */
    public function downloadDocument($studentIDHash = null, $type = null)
    {
        $this->request->allowMethod(['get']);

        if (!$studentIDHash || !$type) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $studentID = $this->Decrypt->hex($studentIDHash);
        if (!$studentID) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        $studentsTable = $this->loadModel('Students');
        try {
            $student = $studentsTable->get($studentID);
        } catch (\Throwable $e) {
            throw new \Cake\Http\Exception\NotFoundException();
        }

        // Verify ownership
        $userSession = $this->Authentication->getIdentity();
        if ($userSession && isset($userSession->id)) {
            if ((int)($student->teacher_id ?? 0) !== (int)$userSession->id) {
                throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to download this file.');
            }
        }

        $safeName = str_replace(' ', '_', $student->name);
        $lrn = $student->lrn;
        
        // Fetch report from ngrok tunnel using secure proxy
        $ngrokConfig = \Cake\Core\Configure::read('Ngrok');
        if (!$ngrokConfig) {
            throw new \Cake\Http\Exception\InternalErrorException('Ngrok configuration not found');
        }

        $baseUrl = rtrim($ngrokConfig['baseUrl'], '/');
        $apiKey = $ngrokConfig['apiKey'];
        $timeout = $ngrokConfig['timeout'] ?? 30;

        // Determine endpoint and filename
        if ($type === 'tailored') {
            $endpoint = $ngrokConfig['tailoredEndpoint'];
            $basename = 'tailored_module_' . $safeName . '_' . $lrn . '.docx';
        } elseif ($type === 'analysis') {
            $endpoint = $ngrokConfig['analysisEndpoint'];
            $basename = 'analysis_result_' . $safeName . '_' . $lrn . '.docx';
        } else {
            throw new \Cake\Http\Exception\NotFoundException('Invalid document type');
        }

        // Build request URL with LRN parameter
        $requestUrl = $baseUrl . $endpoint . '?' . http_build_query(['lrn' => $lrn]);

        // Make authenticated request to ngrok tunnel
        $http = new \Cake\Http\Client(['timeout' => $timeout]);
        
        try {
            $response = $http->get($requestUrl, [], [
                'headers' => [
                    'X-GENTA-API-KEY' => $apiKey,
                    'Accept' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                ]
            ]);

            if (!$response->isOk()) {
                $statusCode = $response->getStatusCode();
                if ($statusCode === 404) {
                    throw new \Cake\Http\Exception\NotFoundException('Report not found for this student. The student may not have completed a quiz yet.');
                } elseif ($statusCode === 401) {
                    throw new \Cake\Http\Exception\InternalErrorException('API authentication failed');
                } else {
                    throw new \Cake\Http\Exception\InternalErrorException('Failed to fetch report from server (HTTP ' . $statusCode . ')');
                }
            }

            // Return the file for download
            $fileContent = $response->getStringBody();
            if (empty($fileContent)) {
                throw new \Cake\Http\Exception\NotFoundException('Report file is empty');
            }

            // Stream the file to the user
            return $this->response
                ->withStringBody($fileContent)
                ->withType('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
                ->withDownload($basename);

        } catch (\Cake\Http\Exception\HttpException $e) {
            // Re-throw HTTP exceptions (404, 401, etc.)
            throw $e;
        } catch (\Throwable $e) {
            // Log the error and return user-friendly message
            $this->log('Ngrok fetch error: ' . $e->getMessage(), 'error');
            throw new \Cake\Http\Exception\InternalErrorException(
                'Unable to fetch report. The report service may be temporarily unavailable. Please try again later.'
            );
        }
    }

    /**
     * Toggle question status between active (1) and suspended (2)
     */
    public function toggleQuestionStatus($questionIDHash)
    {
        $this->request->allowMethod(['post']);
        
        // Decrypt the question ID
        $questionID = $this->Decrypt->hex($questionIDHash);
        if (!$questionID) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Invalid question ID']));
        }

        $questionsTable = $this->loadModel('Questions');
        $userSession = $this->Authentication->getIdentity();
        
        // Get question without status filter (to allow toggling suspended questions)
        $connection = $questionsTable->getConnection();
        $question = $connection->execute(
            'SELECT id, teacher_id, status FROM questions WHERE id = ? LIMIT 1',
            [$questionID]
        )->fetch('assoc');
        
        if (!$question) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode(['success' => false, 'message' => 'Question not found']));
        }
        
        // Check ownership
        if ($userSession && isset($userSession->id)) {
            if ((int)$question['teacher_id'] !== (int)$userSession->id) {
                return $this->response
                    ->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Permission denied']));
            }
        }
        
        // Toggle status: 1 -> 2 or 2 -> 1
        $newStatus = ((int)$question['status'] === 1) ? 2 : 1;
        
        $result = $connection->execute(
            'UPDATE questions SET status = ? WHERE id = ?',
            [$newStatus, $questionID]
        );
        
        if ($result->rowCount() > 0) {
            return $this->response
                ->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true, 
                    'message' => 'Status updated', 
                    'newStatus' => $newStatus
                ]));
        }
        
        return $this->response
            ->withType('application/json')
            ->withStringBody(json_encode(['success' => false, 'message' => 'Failed to update status']));
    }

    /**
     * Create a new Quiz Version snapshot for a subject (AJAX POST)
     * Records the ordered list of active question IDs for the subject and
     * stores subject_id in metadata for later lookup.
     * @param string $subjectIDHash
     */
    public function createQuizVersion($subjectIDHash = null)
    {
        $this->request->allowMethod(['post']);

        if (!$subjectIDHash) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['success' => false, 'message' => 'Missing subject id']));
        }

        $subjectID = $this->Decrypt->hex($subjectIDHash);
        if (!$subjectID) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['success' => false, 'message' => 'Invalid subject id']));
        }

        $userSession = $this->Authentication->getIdentity();

        $questionsTable = $this->loadModel('Questions');
        // Fetch active questions for this teacher and subject
        $questions = $questionsTable->find('owned', ['ownerId' => $userSession->id])
            ->where(['Questions.subject_id' => $subjectID])
            ->order(['Questions.id' => 'ASC'])
            ->all();

        $ids = [];
        foreach ($questions as $q) $ids[] = (int)$q->id;

        if (empty($ids)) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['success' => false, 'message' => 'No active questions found for this subject']));
        }

        $qvTable = $this->loadModel('QuizVersions');

        // Determine next version number for this subject
        $nextVersion = 1;
        try {
            $conn = $qvTable->getConnection();
            // Try JSON_EXTRACT first
            $sql = "SELECT MAX(version_number) AS mv FROM quiz_versions WHERE JSON_EXTRACT(metadata, '$.subject_id') = ?";
            $res = null;
            try {
                $res = $conn->execute($sql, [$subjectID])->fetch('assoc');
            } catch (\Throwable $e) {
                $res = null;
            }
            if ($res && !empty($res['mv'])) {
                $nextVersion = (int)$res['mv'] + 1;
            } else {
                // Fallback: simple count of rows that mention the subject in metadata
                $count = $qvTable->find()->where(['metadata LIKE' => '%"subject_id":' . (int)$subjectID . '%'])->count();
                $nextVersion = $count + 1;
            }
        } catch (\Throwable $e) {
            $nextVersion = 1;
        }

        $entity = $qvTable->newEntity([
            'quiz_id' => null,
            'version_number' => $nextVersion,
            'question_ids' => json_encode($ids),
            'metadata' => json_encode(['subject_id' => (int)$subjectID, 'teacher_id' => $userSession->id, 'snapshot_at' => date('c')]),
            'created_by' => $userSession->id
        ]);

        if ($qvTable->save($entity)) {
            return $this->response->withType('application/json')->withStringBody(json_encode(['success' => true, 'message' => 'Quiz version created', 'id' => $entity->id, 'version_number' => $entity->version_number]));
        }

        return $this->response->withType('application/json')->withStringBody(json_encode(['success' => false, 'message' => 'Failed to create quiz version']));
    }

    public function profile()
    {
        $userSession = $this->Authentication->getIdentity();

        $usersTable = $this->loadModel('Users');

        $user = $usersTable->get($userSession->id);

        if ($this->request->is(['post', 'put'])) {
            // Debug: Log request type
            Log::write('debug', '[Profile] Request method: ' . $this->request->getMethod());
            Log::write('debug', '[Profile] Is AJAX: ' . ($this->request->is('ajax') ? 'YES' : 'NO'));
            Log::write('debug', '[Profile] X-Requested-With header: ' . $this->request->getHeaderLine('X-Requested-With'));
            Log::write('debug', '[Profile] Submit button value: ' . $this->request->getData('submit'));
            
            $data = $this->request->getData();

            // Handle file upload
            $file = $this->request->getData('profile_image');
            $fileUploaded = false;
            
            Log::write('debug', '[Profile] File object type: ' . (is_object($file) ? get_class($file) : gettype($file)));
            
            if ($file && is_object($file) && method_exists($file, 'getError')) {
                $uploadError = $file->getError();
                Log::write('debug', '[Profile] Upload error code: ' . $uploadError);
                
                if ($uploadError === UPLOAD_ERR_OK) {
                    // Validate file type
                    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                    $fileType = $file->getClientMediaType();
                    
                    if (in_array($fileType, $allowedTypes)) {
                        $filename = uniqid() . '-' . $file->getClientFilename();
                        $uploadPath = WWW_ROOT . 'uploads' . DS . 'profile_images' . DS;
                        if (!file_exists($uploadPath)) {
                            mkdir($uploadPath, 0777, true);
                        }
                        $file->moveTo($uploadPath . $filename);
                        $data['profile_image'] = $filename;
                        $fileUploaded = true;
                    } else {
                        if ($this->request->is('ajax')) {
                            $payload = ['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and GIF are allowed.'];
                            return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                        }
                        $this->Flash->error(__('Invalid file type. Only JPG, PNG, and GIF are allowed.'));
                        $this->set(compact('user'));
                        return;
                    }
                } elseif ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    if ($this->request->is('ajax')) {
                        $payload = ['success' => false, 'message' => 'File is too large. Maximum size is 2MB.'];
                        return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                    }
                    $this->Flash->error(__('File is too large. Maximum size is 2MB.'));
                    $this->set(compact('user'));
                    return;
                } elseif ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    // Some other error occurred
                    if ($this->request->is('ajax')) {
                        $payload = ['success' => false, 'message' => 'File upload failed. Error code: ' . $uploadError];
                        return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                    }
                    $this->Flash->error(__('File upload failed. Please try again.'));
                    $this->set(compact('user'));
                    return;
                }
            }
            
            // If no file was uploaded, don't include profile_image in the data to update
            if (!$fileUploaded) {
                unset($data['profile_image']);
                Log::write('debug', '[Profile] No file uploaded, profile_image unset from data');
            } else {
                Log::write('debug', '[Profile] File uploaded successfully: ' . $data['profile_image']);
            }

            // PASSWORD BEFORE PATCH
            $oldPassword = $user->password;

            // PATCH AND CHECK FOR FIELD ERRORS
            $user = $usersTable->patchEntity($user, $data);
            
            Log::write('debug', '[Profile] Has errors: ' . ($user->hasErrors() ? 'YES' : 'NO'));
            if ($user->hasErrors()) {
                Log::write('debug', '[Profile] Errors: ' . json_encode($user->getErrors()));
            }

            if (!$user->hasErrors()) {
                Log::write('debug', '[Profile] Submit value: ' . $this->request->getData('submit'));
                if ($this->request->getData('submit') === 'profile') {
                    Log::write('debug', '[Profile] Entered profile save block');
                    if ($usersTable->save($user)) {
                        Log::write('debug', '[Profile] Save successful, returning JSON');
                        // Update the session identity so the new image is used immediately
                        $identityData = $user->toArray();
                        $identityData['full_name'] = $user->full_name;
                        $identity = new \Authentication\Identity($identityData);
                        $this->Authentication->setIdentity($identity);
                        $this->request = $this->request->withAttribute('identity', $identity);
                        // If AJAX, return JSON with updated avatar URL and fullname
                        if ($this->request->is('ajax')) {
                            // Use Router::url so the controller (no view helper) can generate an app-aware URL
                            $profileUrl = null;
                            if (!empty($user->profile_image)) {
                                $filename = basename((string)$user->profile_image);
                                $uploadPath = WWW_ROOT . 'uploads' . DS . 'profile_images' . DS . $filename;
                                $assetPath = WWW_ROOT . 'assets' . DS . 'images' . DS . $filename;

                                if (file_exists($uploadPath)) {
                                    $profileUrl = \Cake\Routing\Router::url('/uploads/profile_images/' . $filename);
                                } elseif (file_exists($assetPath)) {
                                    $profileUrl = \Cake\Routing\Router::url('/assets/images/' . $filename);
                                } else {
                                    $profileUrl = \Cake\Routing\Router::url('/assets/images/faces-clipart/pic-1.png');
                                }
                            }
                            $payload = [
                                'success' => true,
                                'message' => 'Profile changes saved!',
                                'profile_image' => $profileUrl,
                                'full_name' => $user->full_name
                            ];
                            return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                        }
                        $this->Flash->success(__('Profile changes saved!'));
                    } else {
                        // Save failed
                        if ($this->request->is('ajax')) {
                            $payload = ['success' => false, 'message' => 'Failed to save profile changes.'];
                            return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                        }
                        $this->Flash->error(__('Failed to save profile changes.'));
                    }
                }

                if ($this->request->getData('submit') === 'password') {
                    $hasher = new DefaultPasswordHasher();
                    $currentPassword = $hasher->check($this->request->getData('current_password'), $oldPassword);

                    if ($currentPassword) {
                        if ($this->request->getData('password') === $this->request->getData('confirm_password')) {
                            if ($usersTable->save($user)) {
                                if ($this->request->is('ajax')) {
                                    $payload = ['success' => true, 'message' => 'Password updated!'];
                                }
                                $this->Flash->success(__('Password updated!'));
                            } else {
                                // Password save failed
                                if ($this->request->is('ajax')) {
                                    $payload = ['success' => false, 'message' => 'Failed to update password. Please try again.'];
                                    return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                                }
                                $this->Flash->error(__('Failed to update password. Please try again.'));
                            }
                        } else {
                            if ($this->request->is('ajax')) {
                                $payload = ['success' => false, 'message' => 'Confirm Password did not match your new password.'];
                                return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                            }
                            $this->Flash->error(__('Confirm Password did not match your new password.'));
                        }
                    } else {
                        if ($this->request->is('ajax')) {
                            $payload = ['success' => false, 'message' => 'Current Password did not match.'];
                            return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                        }
                        $this->Flash->error(__('Current Password did not match.'));
                    }
                }
            } else {
                // Validation errors exist
                if ($this->request->is('ajax')) {
                    $errors = [];
                    foreach ($user->getErrors() as $field => $messages) {
                        $errors[$field] = implode(', ', $messages);
                    }
                    $payload = [
                        'success' => false, 
                        'message' => 'Please fix the validation errors.',
                        'errors' => $errors
                    ];
                    return $this->response->withType('application/json')->withStringBody(json_encode($payload));
                }
                $this->Flash->error(__('Please fix the validation errors.'));
            }
        }

        $this->set(compact('user'));
    }

    /**
     * Temporary debug endpoint to show detected host info and Router fullBaseUrl.
     * Use only for debugging and remove when done.
     */
    public function debugHost()
    {
        $this->request->allowMethod(['get']);
        $data = [
            'router_fullBaseUrl' => \Cake\Routing\Router::fullBaseUrl(),
            'http_host' => isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : null,
            'server_name' => isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : null,
            'server_addr' => isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : null,
            'request_uri' => isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : null,
        ];

        return $this->response->withType('application/json')->withStringBody(json_encode($data));
    }
}
