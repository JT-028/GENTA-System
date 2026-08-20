<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\SubjectsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\SubjectsTable Test Case
 */
class SubjectsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\SubjectsTable
     */
    protected $Subjects;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Subjects',
        'app.Questions',
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
        $config = $this->getTableLocator()->exists('Subjects') ? [] : ['className' => SubjectsTable::class];
        $this->Subjects = $this->getTableLocator()->get('Subjects', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Subjects);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\SubjectsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
