<?php
declare(strict_types=1);

namespace App\Test\TestCase\View\Helper;

use App\View\Helper\EncryptHelper;
use Cake\TestSuite\TestCase;
use Cake\View\View;

/**
 * App\View\Helper\EncryptHelper Test Case
 */
class EncryptHelperTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\View\Helper\EncryptHelper
     */
    protected $Encrypt;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $view = new View();
        $this->Encrypt = new EncryptHelper($view);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Encrypt);

        parent::tearDown();
    }
}
