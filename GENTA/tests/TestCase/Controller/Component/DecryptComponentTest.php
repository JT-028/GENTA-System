<?php
declare(strict_types=1);

namespace App\Test\TestCase\Controller\Component;

use App\Controller\Component\DecryptComponent;
use Cake\Controller\ComponentRegistry;
use Cake\TestSuite\TestCase;

/**
 * App\Controller\Component\DecryptComponent Test Case
 */
class DecryptComponentTest extends TestCase
{
    /**
     * Test subject
     *
     * @var \App\Controller\Component\DecryptComponent
     */
    protected $Decrypt;

    /**
     * setUp method
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();
        $registry = new ComponentRegistry();
        $this->Decrypt = new DecryptComponent($registry);
    }

    /**
     * tearDown method
     *
     * @return void
     */
    protected function tearDown(): void
    {
        unset($this->Decrypt);

        parent::tearDown();
    }
}
