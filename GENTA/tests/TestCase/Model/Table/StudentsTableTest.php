<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\StudentsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\StudentsTable Test Case
 */
class StudentsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\StudentsTable
     */
    protected $Students;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Students',
        'app.StudentQuiz',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('Students') ? [] : ['className' => StudentsTable::class];
        $this->Students = $this->getTableLocator()->get('Students', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Students);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\StudentsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
