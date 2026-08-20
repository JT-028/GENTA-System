<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Controller\Controller;
use Cake\Event\EventInterface;

/**
 * Application Controller
 *
 * Add your application-wide methods in the class below, your controllers
 * will inherit them.
 *
 * @link https://book.cakephp.org/4/en/controllers.html#the-app-controller
 */
class AppController extends Controller
{
    /**
     * Initialization hook method.
     *
     * Use this method to add common initialization code like loading components.
     *
     * e.g. `$this->loadComponent('FormProtection');`
     *
     * @return void
     */
    public function initialize(): void
    {
        parent::initialize();

        $this->loadComponent('RequestHandler');
        $this->loadComponent('Flash');

        /*
         * Enable the following component for recommended CakePHP form protection settings.
         * see https://book.cakephp.org/4/en/controllers/components/form-protection.html
         */
        //$this->loadComponent('FormProtection');

        // AUTHENTICATION COMPONENT
        $this->loadComponent('Authentication.Authentication');
        
        // SECURITY COMPONENT for session timeout
        $this->loadComponent('Security');
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);

        // UNAUTHENTICATED PAGES
        // Allow the approvalCallback endpoint to be called by the Flask admin (server-to-server)
        // without an authenticated session. Keep login/register public as well.
        $this->Authentication->addUnauthenticatedActions(['login', 'register', 'approvalCallback']);

        // Check session timeout and account lock status for authenticated users
        $identity = $this->Authentication->getIdentity();
        if ($identity) {
            // CRITICAL SECURITY: Check if account is locked (global check for all authenticated requests)
            try {
                $userId = null;
                if (is_object($identity) && property_exists($identity, 'id')) {
                    $userId = $identity->id;
                } elseif (is_array($identity) && array_key_exists('id', $identity)) {
                    $userId = $identity['id'];
                }
                
                if ($userId) {
                    $usersTable = $this->fetchTable('Users');
                    $user = $usersTable->find()->where(['id' => $userId])->first();
                    
                    if ($user && $user->account_locked_until) {
                        $lockoutTime = ($user->account_locked_until instanceof \Cake\I18n\FrozenTime) 
                            ? $user->account_locked_until 
                            : new \Cake\I18n\FrozenTime($user->account_locked_until);
                        $now = \Cake\I18n\FrozenTime::now();
                        
                        if ($lockoutTime > $now) {
                            $diff = $now->diff($lockoutTime);
                            $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i + 1;
                            
                            \Cake\Log\Log::write('warning', 'Locked account attempted access: user=' . $userId . ', action=' . $this->request->getParam('action'));
                            $this->Authentication->logout();
                            $this->Flash->error(__('This account is temporarily locked due to too many failed login attempts. Please try again in {0} minutes.', $minutes));
                            
                            return $this->redirect([
                                'controller' => 'Users',
                                'action' => 'login'
                            ]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Cake\Log\Log::write('error', 'Error checking account lock status in AppController: ' . $e->getMessage());
            }
            
            $sessionCheck = $this->Security->checkSession();
            
            if (!$sessionCheck['valid']) {
                // Session expired - log out user
                $this->Authentication->logout();
                
                $message = 'Your session has expired due to inactivity. Please log in again.';
                if ($sessionCheck['reason'] === 'absolute_timeout') {
                    $message = 'Your session has expired. Please log in again.';
                }
                
                $this->Flash->warning(__($message));
                
                // Redirect to login with redirect parameter
                $currentUrl = $this->request->getRequestTarget();
                return $this->redirect([
                    'controller' => 'Users',
                    'action' => 'login',
                    '?' => ['redirect' => $currentUrl]
                ]);
            }
        }

        // SET LAYOUT FOR ALL PAGES ON GUEST
        $this->viewBuilder()->setLayout('guest-layout');
    }
}
