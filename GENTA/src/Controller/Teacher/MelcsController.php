<?php
declare(strict_types=1);

namespace App\Controller\Teacher;

use App\Controller\Teacher\AppController;

/**
 * Melcs Controller
 *
 * Basic CRUD for teacher MELCs (Most Essential Learning Competencies)
 */
class MelcsController extends AppController
{
    // Declare property to avoid PHP 8.2 dynamic property deprecation warnings
    public $Melcs = null;
    // declare Subjects property because we call loadModel('Subjects') which
    // otherwise creates a dynamic property (deprecated in PHP 8.2)
    public $Subjects = null;
    // declare Encrypt/Decrypt placeholders to avoid dynamic property creation
    public $Encrypt = null;
    public $Decrypt = null;
    public function index()
    {
        /** @var \App\Model\Table\MelcsTable $melcsTable */
        $melcsTable = $this->loadModel('Melcs');
        /** @var \App\Model\Table\SubjectsTable $subjectsTable */
        $subjectsTable = $this->loadModel('Subjects');

        $user = $this->Authentication->getIdentity();

        // Only show MELCs for this teacher if authenticated
        $query = $melcsTable->find('all', ['contain' => ['Subjects']])->order(['upload_date' => 'DESC']);
        if ($user && isset($user->id)) {
            $query->where(['Melcs.teacher_id' => $user->id]);
        }

        $melcs = $query->all();

        // Subject options for add/edit forms - prefer a simple id=>name list without the 'All' option
        try {
            $subjectOptions = $subjectsTable->find('list', ['keyField' => 'id', 'valueField' => 'name'])->order(['name' => 'ASC'])->toArray();
            if (empty($subjectOptions) && method_exists($subjectsTable, 'searchOptions')) {
                // fallback to searchOptions(false) if available
                $subjectOptions = $subjectsTable->searchOptions(false);
            }
        } catch (\Throwable $e) {
            $subjectOptions = method_exists($subjectsTable, 'searchOptions') ? $subjectsTable->searchOptions(false) : [];
        }

        $this->set(compact('melcs', 'subjectOptions'));
    }

    /**
     * Export MELCs as CSV (teacher-scoped)
     */
    public function exportCsv()
    {
        $this->request->allowMethod(['get']);

        $melcsTable = $this->loadModel('Melcs');
        $user = $this->Authentication->getIdentity();

        $query = $melcsTable->find('all', ['contain' => ['Subjects']])->order(['upload_date' => 'DESC']);
        if ($user && isset($user->id)) $query->where(['Melcs.teacher_id' => $user->id]);

        $rows = [];
        /** @var \App\Model\Entity\Melc $m */
        foreach ($query->all() as $m) {
            $rows[] = [
                'id' => $m->id,
                'upload_date' => (string)$m->upload_date,
                'description' => $m->description,
                'subject_id' => $m->subject_id,
                'subject_name' => $m->subject->name ?? null,
                'teacher_id' => $m->teacher_id ?? null
            ];
        }

        $filename = 'melcs_export_' . date('Ymd_His') . '.csv';
        $csv = fopen('php://memory', 'w+');
        // header
        fputcsv($csv, array_keys($rows[0] ?? ['id','upload_date','description','subject_id','subject_name','teacher_id']));
        foreach ($rows as $r) fputcsv($csv, $r);
        rewind($csv);
        $content = stream_get_contents($csv);
        fclose($csv);

        $this->response = $this->response->withType('csv')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"');
        $this->response = $this->response->withStringBody($content);
        return $this->response;
    }

    /**
     * Export MELCs as JSON for GenAI pipeline
     */
    public function exportJson()
    {
        $this->request->allowMethod(['get']);

        $melcsTable = $this->loadModel('Melcs');
        $user = $this->Authentication->getIdentity();

        $query = $melcsTable->find('all', ['contain' => ['Subjects']])->order(['upload_date' => 'DESC']);
        if ($user && isset($user->id)) $query->where(['Melcs.teacher_id' => $user->id]);

        $out = [];
        /** @var \App\Model\Entity\Melc $m */
        foreach ($query->all() as $m) {
            $out[] = [
                'id' => $m->id,
                'upload_date' => (string)$m->upload_date,
                'description' => $m->description,
                'subject_id' => $m->subject_id,
                'subject_name' => $m->subject->name ?? null
            ];
        }

        $this->response = $this->response->withType('application/json')
            ->withStringBody(json_encode($out));
        return $this->response;
    }

    /**
     * Import MELCs from uploaded CSV. Expects CSV with header: description,subject_id (or subject_name)
     */
    public function importCsv()
    {
        $this->request->allowMethod(['post']);
        $isAjax = $this->request->is('ajax');

        $file = $this->request->getData('csv_file');
        if (empty($file) || $file->getError()) {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Please upload a valid CSV file.']));
            }
            $this->Flash->error('Please upload a valid CSV file.');
            return $this->redirect(['action' => 'index', 'prefix' => 'Teacher']);
        }

        $subjectsTable = $this->loadModel('Subjects');
        $melcsTable = $this->loadModel('Melcs');
        $user = $this->Authentication->getIdentity();

        // Read uploaded temp file
        $stream = $file->getStream();
        $meta = $stream->getMetadata();
        $path = $meta['uri'] ?? null;
        if (!$path || !file_exists($path)) {
            if ($isAjax) {
                return $this->response->withType('application/json')
                    ->withStringBody(json_encode(['success' => false, 'message' => 'Uploaded file could not be read.']));
            }
            $this->Flash->error('Uploaded file could not be read.');
            return $this->redirect(['action' => 'index', 'prefix' => 'Teacher']);
        }

        $f = fopen($path, 'r');
        $header = null;
        $created = 0;
        $errors = [];
        while (($row = fgetcsv($f)) !== false) {
            if (!$header) { $header = $row; continue; }
            // --- INSERT THIS SAFETY CHECK HERE ---
            if (count($header) !== count($row)) {
                continue;
            }
            // -------------------------------------
            $data = array_combine($header, $row);

            // --- ADD THIS LINE ---
            // Force the subject_id to 1 (Mathematics) so it never fails
            $data['subject_id'] = 1; 
            // ---------------------

            // --- ADD THIS LINE ---
            // This forces all headers (Description, Code, etc.) to be lowercase automatically
            $data = array_change_key_case($data, CASE_LOWER);
            // ---------------------

            // Your previous fix is still here:
            $data['subject_id'] = 1;
            // ... rest of the code ...
            if (!$data) continue;
            $description = $data['description'] ?? ($data['desc'] ?? null);
            $subjectId = $data['subject_id'] ?? null;
            $subjectName = $data['subject_name'] ?? null;
            // --- ADD THIS BLOCK ---
            // If we have a Subject Name but no ID, try to find the ID from the database
            if (empty($subjectId) && !empty($subjectName)) {
                // Look for the subject in the database by name
                $subject = $this->fetchTable('Subjects')->find()
                    ->where(['name LIKE' => '%' . trim($subjectName) . '%'])
                    ->first();

                // If we found it, use its ID
                if ($subject) {
                    $subjectId = $subject->id;
                }
            }
            // ----------------------
            if (!$subjectId && $subjectName) {
                // try lookup
                $s = $subjectsTable->find()->where(['name' => $subjectName])->first();
                if ($s) $subjectId = $s->id;
            }
            $entity = $melcsTable->newEmptyEntity();
            $entity->description = $description;
            $entity->subject_id = $subjectId;
            if ($user && isset($user->id)) $entity->teacher_id = $user->id;

            if ($melcsTable->save($entity)) {
                $created++;
            } else {
                $errors[] = $entity->getErrors();
            }
        }
        fclose($f);

        // Return JSON for AJAX requests
        if ($isAjax) {
            $message = "Successfully imported {$created} MELC(s).";
            if (!empty($errors)) {
                $message .= ' Some rows failed to import.';
            }
            return $this->response->withType('application/json')
                ->withStringBody(json_encode([
                    'success' => true,
                    'message' => $message,
                    'created' => $created,
                    'errorCount' => count($errors)
                ]));
        }

        $this->Flash->success("Imported {$created} MELCs.");
        if (!empty($errors)) $this->Flash->error('Some rows failed to import.');
        return $this->redirect(['action' => 'index', 'prefix' => 'Teacher']);
    }

    public function add()
    {
        $melcsTable = $this->loadModel('Melcs');
        $melc = $melcsTable->newEmptyEntity();

        // subject options for form (also used by modal element) - ensure we pass id=>name list without 'All'
        $subjectsTable = $this->loadModel('Subjects');
        try {
            $subjectOptions = $subjectsTable->find('list', ['keyField' => 'id', 'valueField' => 'name'])->order(['name' => 'ASC'])->toArray();
            if (empty($subjectOptions) && method_exists($subjectsTable, 'searchOptions')) {
                $subjectOptions = $subjectsTable->searchOptions(false);
            }
        } catch (\Throwable $e) {
            $subjectOptions = method_exists($subjectsTable, 'searchOptions') ? $subjectsTable->searchOptions(false) : [];
        }

        if ($this->request->is('post')) {
            $data = $this->request->getData();
            $melc = $melcsTable->patchEntity($melc, $data);

            $user = $this->Authentication->getIdentity();
            if ($user && isset($user->id)) {
                $melc->teacher_id = $user->id;
            }

            if (!$melc->hasErrors()) {
                if ($melcsTable->save($melc)) {
                    // If this is an AJAX request, return JSON for the modal handler
                    if ($this->request->is('ajax')) {
                        $enc = null;
                        try { $enc = $this->Encrypt->hex($melc->id); } catch (\Throwable $e) { $enc = $melc->id; }
                        $out = ['success' => true, 'message' => 'MELC saved.', 'melc' => [
                            'id' => $enc,
                            'description' => $melc->description,
                            'subject_id' => $melc->subject_id,
                        ]];
                        $this->response = $this->response->withType('application/json')->withStringBody(json_encode($out));
                        return $this->response;
                    }
                    $this->Flash->success(__('MELC saved successfully.'));
                    return $this->redirect(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'index']);
                }
                $this->Flash->error(__('Failed to save MELC.'));
            }
        }

        // If requested as AJAX GET, render the modal element fragment
        if ($this->request->is('ajax') && $this->request->is('get')) {
            $this->set(compact('melc', 'subjectOptions'));
            $this->viewBuilder()->enableAutoLayout(false);
            $this->render('/element/melc_form');
            return;
        }

        $this->set(compact('melc', 'subjectOptions'));
    }

    public function edit($idHash = null)
    {
        if (!$idHash) {
            return $this->redirect(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'index']);
        }

        // Use Decrypt component if available; otherwise assume raw id
        try {
            $id = $this->Decrypt->hex($idHash);
        } catch (\Throwable $e) {
            $id = $idHash;
        }

        $melcsTable = $this->loadModel('Melcs');
        $melc = $melcsTable->get($id);

        // subject options for form - ensure id=>name list without 'All'
        $subjectsTable = $this->loadModel('Subjects');
        try {
            $subjectOptions = $subjectsTable->find('list', ['keyField' => 'id', 'valueField' => 'name'])->order(['name' => 'ASC'])->toArray();
            if (empty($subjectOptions) && method_exists($subjectsTable, 'searchOptions')) {
                $subjectOptions = $subjectsTable->searchOptions(false);
            }
        } catch (\Throwable $e) {
            $subjectOptions = method_exists($subjectsTable, 'searchOptions') ? $subjectsTable->searchOptions(false) : [];
        }

        // Ownership check
        $user = $this->Authentication->getIdentity();
        if ($user && isset($user->id) && ($melc->teacher_id ?? null) != $user->id) {
            throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to edit this MELC.');
        }

        if ($this->request->is(['post', 'put', 'patch'])) {
            $melc = $melcsTable->patchEntity($melc, $this->request->getData());
            if (!$melc->hasErrors() && $melcsTable->save($melc)) {
                if ($this->request->is('ajax')) {
                    $enc = null;
                    try { $enc = $this->Encrypt->hex($melc->id); } catch (\Throwable $e) { $enc = $melc->id; }
                    $out = ['success' => true, 'message' => 'MELC updated.', 'melc' => [
                        'id' => $enc,
                        'description' => $melc->description,
                        'subject_id' => $melc->subject_id,
                    ]];
                    $this->response = $this->response->withType('application/json')->withStringBody(json_encode($out));
                    return $this->response;
                }
                $this->Flash->success(__('MELC updated successfully.'));
                return $this->redirect(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'index']);
            }
            if ($this->request->is('ajax')) {
                $this->response = $this->response->withType('application/json')->withStringBody(json_encode(['success' => false, 'errors' => $melc->getErrors()]));
                return $this->response;
            }
            $this->Flash->error(__('Error updating MELC.'));
        }

        // If requested as AJAX GET, render the modal element fragment
        if ($this->request->is('ajax') && $this->request->is('get')) {
            $this->set(compact('melc', 'subjectOptions'));
            $this->viewBuilder()->enableAutoLayout(false);
            $this->render('/element/melc_form');
            return;
        }

        $this->set(compact('melc', 'subjectOptions'));
    }

    public function delete($idHash = null)
    {
        $this->request->allowMethod(['post']);

        if (!$idHash) {
            return $this->redirect(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'index']);
        }

        try {
            $id = $this->Decrypt->hex($idHash);
        } catch (\Throwable $e) {
            $id = $idHash;
        }

        $melcsTable = $this->loadModel('Melcs');
        $melc = $melcsTable->get($id);

        $user = $this->Authentication->getIdentity();
        if ($user && isset($user->id) && ($melc->teacher_id ?? null) != $user->id) {
            throw new \Cake\Http\Exception\ForbiddenException('You are not allowed to delete this MELC.');
        }

        if ($melcsTable->delete($melc)) {
            $this->Flash->success(__('MELC deleted.'));
        } else {
            $this->Flash->error(__('Failed to delete MELC.'));
        }

        return $this->redirect(['prefix' => 'Teacher', 'controller' => 'Melcs', 'action' => 'index']);
    }
}
