<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * QuizVersion Entity
 *
 * @property int $id
 * @property int|null $quiz_id
 * @property int|null $subject_id
 * @property int $version_number
 * @property array $question_ids
 * @property array|null $metadata
 * @property \Cake\I18n\FrozenTime $created
 * @property int|null $created_by
 */
class QuizVersion extends Entity
{
    protected $_accessible = [
        '*' => true,
        'id' => false,
    ];

    protected function _getQuestionIdsAttribute($value)
    {
        // Ensure JSON is returned as array
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : [];
        }
        return $value ?: [];
    }

    protected function _getMetadataAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return $decoded !== null ? $decoded : [];
        }
        return $value ?: [];
    }
}
