<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\FieldHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/**
 * App\View\Helper\FieldHelper Test Case
 */
class FieldHelperTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\View\Helper\FieldHelper
     */
    protected $Field;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->Field = new FieldHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Field);

        parent::tearDown();
    }
}
