<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * Question Entity
 *
 * @property int $id
 * @property int $subject_id
 * @property string|null $description
 * @property string|null $image
 * @property string $choices
 * @property string $answer
 * @property int $score
 * @property int $status
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 *
 * @property \App\Model\Entity\Subject $subject
 */
class Question extends Entity
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
        'subject_id' => true,
        'description' => true,
        'image' => true,
        'choices' => true,
        'answer' => true,
        'score' => true,
        'status' => true,
        'created' => true,
        'modified' => true,
        'subject' => true,
    ];

    /**
     * Virtual fields that should be exposed
     *
     * @var array<string>
     */
    protected $_virtual = ['choices_string'];

    protected function _setChoices(string $choices) : ?string
    {
        return json_encode(explode(',', $choices));
    }

    protected function _getChoicesString()
    {
        if (!$this->choices) {
            return '';
        }
        
        try {
            $decoded = json_decode($this->choices);
            if (is_array($decoded)) {
                return implode(',', $decoded);
            }
            return '';
        } catch (\Exception $e) {
            return '';
        }
    }
}
