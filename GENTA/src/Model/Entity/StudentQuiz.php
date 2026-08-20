<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * StudentQuiz Entity
 *
 * @property int $id
 * @property int $student_id
 * @property int $subject_id
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Student $student
 * @property \App\Model\Entity\Subject $subject
 */
class StudentQuiz extends Entity
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
        'student_id' => true,
        'subject_id' => true,
        'created' => true,
        'modified' => true,
        'student' => true,
        'subject' => true,
    ];

    protected function _getScore()
    {
        $score = [
            'studentScore' => 0,
            'totalScore' => 0,
            'overallScore' => 'N/A',
            'percentage' => 0
        ];

        // Preferred denominator: explicit total_questions column on student_quiz record
        $denominator = 0;
        if (!empty($this->total_questions) && intval($this->total_questions) > 0) {
            $denominator = (int)$this->total_questions;
        }

        // Collect related rows. Support different relation naming conventions and lazy-load if needed.
        $rows = [];
        if (!empty($this->student_quiz_questions)) {
            $rows = $this->student_quiz_questions;
        } elseif (!empty($this->studentQuizQuestions)) {
            $rows = $this->studentQuizQuestions;
        }

        // If no rows were present as a relation, try lazy-loading via the table locator.
        if (empty($rows) && !empty($this->id)) {
            $tableLocator = \Cake\ORM\TableRegistry::getTableLocator();
            if ($tableLocator->exists('StudentQuizQuestions')) {
                $sqqTable = $tableLocator->get('StudentQuizQuestions');
                $rows = $sqqTable->find('all')->where(['student_quiz_id' => $this->id])->all();
            }
        }

        // If denominator is not set from total_questions, fall back to number of rows
        if ($denominator === 0) {
            if (!empty($rows)) {
                // Count number of question rows
                $denominator = is_array($rows) ? count($rows) : $rows->count();
            }
        }

        // Numerator: count correct answers (not sum of per-question points)
        $correctCount = 0;
        if (!empty($rows)) {
            foreach ($rows as $r) {
                if (!empty($r->is_correct)) {
                    $correctCount++;
                }
            }
        }

        // If we couldn't determine denominator or numerator, leave as N/A
        if ($denominator > 0) {
            $score['studentScore'] = $correctCount;
            $score['totalScore'] = $denominator;
            $score['overallScore'] = $score['studentScore'] . '/' . $score['totalScore'];
            $score['percentage'] = (int) round(($score['studentScore'] / $score['totalScore']) * 100);
        } elseif ($denominator === 0 && $correctCount > 0) {
            // If denominator missing but there are correct answers, show numerator/0 is not helpful — use correct/unknown
            $score['studentScore'] = $correctCount;
            $score['totalScore'] = 0;
            $score['overallScore'] = $score['studentScore'] . '/0';
            $score['percentage'] = 0;
        }

        return $score;
    }
}
