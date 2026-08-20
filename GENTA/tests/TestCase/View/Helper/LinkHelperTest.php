<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\LinkHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/**
 * App\View\Helper\LinkHelper Test Case
 */
class LinkHelperTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\View\Helper\LinkHelper
     */
    protected $Link;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->Link = new LinkHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Link);

        parent::tearDown();
    }
}
