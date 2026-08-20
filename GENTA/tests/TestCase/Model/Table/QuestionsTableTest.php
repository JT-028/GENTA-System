<?php
declare(strict_types=1);

namespace App\Test\TestCase\Model\Table;

use App\Model\Table\QuestionsTable;
use Cake\TestSuite\TestCase;

/**
 * App\Model\Table\QuestionsTable Test Case
 */
class QuestionsTableTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Model\Table\QuestionsTable
     */
    protected $Questions;

    /**
     * Fixtures
     *
     * @var array<string>
     */
    protected $fixtures = [
        'app.Questions',
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
        $config = $this->getTableLocator()->exists('Questions') ? [] : ['className' => QuestionsTable::class];
        $this->Questions = $this->getTableLocator()->get('Questions', $config);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Questions);

        parent::tearDown();
    }

    /**
     * Test validationDefault method
     *
     * @return void
     * @uses \App\Model\Table\QuestionsTable::validationDefault()
     */
    public function testValidationDefault(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }

    /**
     * Test buildRules method
     *
     * @return void
     * @uses \App\Model\Table\QuestionsTable::buildRules()
     */
    public function testBuildRules(): void
    {
        $this->markTestIncomplete('Not implemented yet.');
    }
}
