<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;

/**
 * PasswordHistory Entity
 *
 * @property int $id
 * @property int $user_id
 * @property string $password_hash
 * @property \Cake\I18n\FrozenTime $created
 *
 * @property \App\Model\Entity\User $user
 */
class PasswordHistory extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected $_accessible = [
        'user_id' => true,
        'password_hash' => true,
        'created' => true,
        'user' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected $_hidden = [
        'password_hash',
    ];
}
