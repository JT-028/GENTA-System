<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\StudentQuizTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\StudentQuizTable Test Case
 */
class StudentQuizTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\StudentQuizTable
     */
    protected $StudentQuiz;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.StudentQuiz',
        'app.Students',
        'app.Subjects',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('StudentQuiz') ? [] : ['className' => StudentQuizTable::class];
        $this->StudentQuiz = $this->getTableLocator()->get('StudentQuiz', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->StudentQuiz);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\StudentQuizTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\StudentQuizTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
