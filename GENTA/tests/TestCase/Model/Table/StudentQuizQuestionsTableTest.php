<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\StudentQuizQuestionsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\StudentQuizQuestionsTable Test Case
 */
class StudentQuizQuestionsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\StudentQuizQuestionsTable
     */
    protected $StudentQuizQuestions;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.StudentQuizQuestions',
    ];

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $config = $this->getTableLocator()->exists('StudentQuizQuestions') ? [] : ['className' => StudentQuizQuestionsTable::class];
        $this->StudentQuizQuestions = $this->getTableLocator()->get('StudentQuizQuestions', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->StudentQuizQuestions);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\StudentQuizQuestionsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
