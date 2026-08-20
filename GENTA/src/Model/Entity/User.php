<?php
declare(strict_types=1);

namespace App\Model\Entity;

use Cake\ORM\Entity;
use Authentication\PasswordHasher\DefaultPasswordHasher;

/**
 * User Entity
 *
 * @property int $id
 * @property string $email
 * @property string $password
 * @property string|null $token
 * @property string $first_name
 * @property string $last_name
 * @property int $status
 * @property int $type
 * @property \Cake\I18n\FrozenTime $created
 * @property \Cake\I18n\FrozenTime $modified
 */
class User extends Entity
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
        'email' => true,
        'password' => true,
        'token' => true,
        'first_name' => true,
        'last_name' => true,
        'status' => true,
        'type' => true,
        'created' => true,
        'modified' => true,
        'profile_image' => true,
        'walkthrough_shown' => true,
        'email_verified' => true,
        'verification_token' => true,
        'verification_token_expires' => true,
        'password_reset_token' => true,
        'password_reset_expires' => true,
        'failed_login_attempts' => true,
        'account_locked_until' => true,
        'lockout_count' => true,
        'two_factor_secret' => true,
        'two_factor_enabled' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected $_hidden = [
        'password',
        'token',
        'verification_token',
        'password_reset_token',
        'two_factor_secret',
    ];

    protected function _setEmail(string $email) : ?string
    {
        return strtolower($email);
    }

    protected function _setPassword(string $password) : ?string
    {
        if (strlen($password) > 0) {
            return (new DefaultPasswordHasher())->hash($password);
        }
    }

    protected function _getFullName()
    {
        return ucfirst(strtolower($this->first_name)) . ' ' . ucfirst(strtolower($this->last_name));
    }
}
