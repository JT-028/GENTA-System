<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * StudentQuizQuestion Entity
 *
 * @property int $id
 * @property int $student_quiz_id
 * @property string|null $description
 * @property string|null $image
 * @property string $choices
 * @property string $answer
 * @property string|null $student_answer
 * @property int $score
 * @property int $status
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 */
class StudentQuizQuestion extends Entity
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
        'student_quiz_id' => true,
        'description' => true,
        'image' => true,
        'choices' => true,
        'answer' => true,
        'student_answer' => true,
        'score' => true,
        'status' => true,
        'created' => true,
        'modified' => true,
    ];

    protected function _getIsCorrect()
    {
        return $this->answer === $this->student_answer;
    }
}
