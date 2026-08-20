<?php
declare(strict_types=1);

namespace App\Model\Table;

use ArrayObject;
use Cake\Event\EventInterface;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Datasource\EntityInterface;
use Cake\Validation\Validator;

/**
 * Users Model
 *
 * @method \App\Model\Entity\User newEmptyEntity()
 * @method \App\Model\Entity\User newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\User[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\User get($primaryKey, $options = [])
 * @method \App\Model\Entity\User findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\User patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\User[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\User|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\User[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class UsersTable extends Table
{
    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('users');
        $this->setDisplayField('email');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->email('email')
            ->maxLength('email', 255)
            ->requirePresence('email', 'create')
            ->notEmptyString('email', __('Email is required.'));
            // TEMPORARILY DISABLED: DepEd email restriction
            // TODO: UNCOMMENT THE LINES BELOW when you have @deped.gov.ph email access
            // ->add('email', 'depedDomain', [
            //     'rule' => function($value, $context) {
            //         return preg_match('/@deped\.gov\.ph$/i', $value) === 1;
            //     },
            //     'message' => __('Only DepEd email addresses (@deped.gov.ph) are allowed.')
            // ]);

        $validator
            ->scalar('password')
            ->minLength('password', 8, __('Password must be at least 8 characters.'))
            ->maxLength('password', 32, __('Password must not exceed 32 characters.'))
            ->add('password', 'strongPassword', [
                'rule' => function($value, $context) {
                    // Must contain at least one uppercase, one lowercase, one number, and one special character
                    $hasUppercase = preg_match('/[A-Z]/', $value);
                    $hasLowercase = preg_match('/[a-z]/', $value);
                    $hasNumber = preg_match('/[0-9]/', $value);
                    $hasSpecial = preg_match('/[@#!$%&*()_+\-=\[\]{};:\'",.<>?\/\\|`~]/', $value);
                    
                    return $hasUppercase && $hasLowercase && $hasNumber && $hasSpecial;
                },
                'message' => __('Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character (@, #, !, $, %, etc.).')
            ])
            ->requirePresence('password', 'create')
            ->notEmptyString('password',  __('Password is required.'));

        $validator
            ->scalar('token')
            ->allowEmptyString('token');

        $validator
            ->boolean('email_verified')
            ->allowEmptyString('email_verified');

        $validator
            ->scalar('verification_token')
            ->maxLength('verification_token', 255)
            ->allowEmptyString('verification_token');

        $validator
            ->dateTime('verification_token_expires')
            ->allowEmptyDateTime('verification_token_expires');

        $validator
            ->scalar('first_name')
            ->maxLength('first_name', 255)
            ->add('first_name', 'lettersAndSpaces', [
                'rule' => function($value, $context) {
                    // Allow letters, spaces, hyphens, apostrophes (for names like Mary-Jane, O'Brien, etc.)
                    return preg_match('/^[a-zA-Z\s\'-]+$/', trim($value)) === 1;
                },
                'message' => __('First name must contain only letters, spaces, hyphens, and apostrophes.')
            ])
            ->requirePresence('first_name', 'create')
            ->notEmptyString('first_name', __('First Name is required.'));

        $validator
            ->scalar('last_name')
            ->maxLength('last_name', 255)
            ->add('last_name', 'lettersAndSpaces', [
                'rule' => function($value, $context) {
                    // Allow letters, spaces, hyphens, apostrophes (for names like De La Cruz, O'Connor, etc.)
                    return preg_match('/^[a-zA-Z\s\'-]+$/', trim($value)) === 1;
                },
                'message' => __('Last name must contain only letters, spaces, hyphens, and apostrophes.')
            ])
            ->requirePresence('last_name', 'create')
            ->notEmptyString('last_name', __('Last Name is required.'));

        $validator
            ->integer('status')
            ->notEmptyString('status');

        $validator
            ->integer('type')
            ->notEmptyString('type');

        return $validator;
    }

    /**
     * Finder to return only active/approved users.
     * This is used by the authentication resolver so users with
     * status != 1 cannot be identified and therefore cannot log in.
     *
     * @param \Cake\ORM\Query $query The query to modify
     * @param array $options Options array (unused)
     * @return \Cake\ORM\Query
     */
    public function findActive(Query $query, array $options)
    {
        return $query->where(['Users.status' => 1]);
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['email'], 'This email address is already in use.'), ['errorField' => 'email']);

        return $rules;
    }

    /**
     * Ensure newly created users have a default profile image when none provided.
     * This guarantees templates can always reference a profile image filename.
     *
     * @param \Cake\Event\EventInterface $event
     * @param \Cake\Datasource\EntityInterface $entity
     * @param \ArrayObject $options
     * @return void
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        try {
            if ($entity->isNew() && (empty($entity->profile_image) || $entity->profile_image === null)) {
                // Use a simple filename; templates typically resolve this to the uploads path
                $entity->profile_image = 'default_profile.png';
            }
        } catch (\Throwable $_) {
            // swallow errors to avoid blocking saves; logging can be added if desired
        }
    }
}
