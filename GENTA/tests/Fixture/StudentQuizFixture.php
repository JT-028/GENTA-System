<?php
declare(strict_types=1);

namespace App\Test\Fixture;

use Cake\TestSuite\Fixture\TestFixture;

/**
 * StudentQuizFixture
 */
class StudentQuizFixture extends TestFixture
{
    /**
     * Table name
     *
     * @var string
     */
    public $table = 'student_quiz';
    /**
     * Init method
     *
     * @return void
     */
    public function init(): void
    {
        $this->records = [
            [
                'id' => 1,
                'student_id' => 1,
                'subject_id' => 1,
                'created' => '2024-02-19 07:37:11',
                'modified' => '2024-02-19 07:37:11',
            ],
        ];
        parent::init();
    }
}
