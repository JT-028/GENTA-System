<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Student Entity
 *
 * @property int $id
 * @property string $name
 * @property string $lrn
 * @property int $grade
 * @property string $section
 * @property string|null $remarks
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\StudentQuiz[] $student_quiz
 */
class Student extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * Note that when '*' is set to true, this allows all unspecified fields to
     * be mass assigned. For security purposes, it is advised to set '*' to false
     * (or remove it), and explicitly make individual fields accessible as needed.
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'name' => true,
        // Support both `lrn` and legacy `student_code` during rollout; prefer `lrn`.
        'lrn' => true,
        'student_code' => true,
        'grade' => true,
        'section' => true,
        'remarks' => true,
        'created' => true,
        'modified' => true,
        'student_quiz' => true,
    ];

    protected function _getGradeSection()
    {
        return 'Grade ' . $this->grade . ' - ' . $this->section;
    }
}
