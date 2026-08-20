<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller;

use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\UsersController Test Case
 *
 * @uses \App\Controller\UsersController
 */
class UsersControllerTest extends TestCase
{
    use IntegrationTestTrait;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Users',
    ];

    public function setUp(): void
    {
        parent::setUp();
        $this->enableCsrfToken();
        $this->enableSecurityToken();
    }

    /**
     * Test index method
     *
     * @return void
     * @uses \App\Controller\UsersController::index()
     */
    public function testIndex(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test view method
     *
     * @return void
     * @uses \App\Controller\UsersController::view()
     */
    public function testView(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test add method
     *
     * @return void
     * @uses \App\Controller\UsersController::add()
     */
    public function testAdd(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test edit method
     *
     * @return void
     * @uses \App\Controller\UsersController::edit()
     */
    public function testEdit(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test delete method
     *
     * @return void
     * @uses \App\Controller\UsersController::delete()
     */
    public function testDelete(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Assert that pending (status != 1) users cannot authenticate
     */
    public function testPendingUserCannotLogin(): void
    {
        $users = $this->getTableLocator()->get('Users');

        $hasher = new \Authentication\PasswordHasher\DefaultPasswordHasher();
        $plain = 'TestPassword123!';
        $hash = $hasher->hash($plain);

        $user = $users->newEntity([
            'email' => 'pending@example.test',
            'password' => $hash,
            'first_name' => 'Pending',
            'last_name' => 'User',
            'status' => 0,
            'type' => 1,
        ]);
        $this->assertEmpty($user->getErrors(), 'Fixture user entity should have no validation errors');
        $this->assertNotFalse($users->save($user), 'Failed to save pending user for test');

        $this->post('/users/login', ['email' => 'pending@example.test', 'password' => $plain]);

        $this->assertResponseContains('Your account is not active. It may be pending admin approval.');
        $this->assertResponseCode(200);
    }
}
