<?php
namespace App\Controller\Teacher;

use App\Controller\AppController;
use Cake\Event\EventInterface;
use Cake\Http\Response;

class WalkthroughController extends AppController
{
    public function complete()
    {
        $this->request->allowMethod(['post']);
        $user = $this->Authentication->getIdentity();
        if ($user && isset($user->id)) {
            $usersTable = $this->fetchTable('Users');
            $entity = $usersTable->get($user->id);
            $entity->is_new = false;
            $usersTable->save($entity);
            // Update session identity
            $user = $usersTable->get($user->id);
            $this->Authentication->setIdentity($user);
        }
        $this->set(['success' => true, '_serialize' => ['success']]);
        return $this->response->withType('application/json');
    }
}
