<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * PasswordHistory Model
 *
 * @property \App\Model\Table\UsersTable&\Cake\ORM\Association\BelongsTo $Users
 *
 * @method \App\Model\Entity\PasswordHistory newEmptyEntity()
 * @method \App\Model\Entity\PasswordHistory newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\PasswordHistory[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\PasswordHistory get($primaryKey, $options = [])
 * @method \App\Model\Entity\PasswordHistory findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\PasswordHistory patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\PasswordHistory[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\PasswordHistory|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\PasswordHistory saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\PasswordHistory[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\PasswordHistory[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\PasswordHistory[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\PasswordHistory[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class PasswordHistoryTable extends Table
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

        $this->setTable('password_history');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new'
                ]
            ]
        ]);

        $this->belongsTo('Users', [
            'foreignKey' => 'user_id',
            'joinType' => 'INNER',
        ]);
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
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->scalar('password_hash')
            ->maxLength('password_hash', 255)
            ->requirePresence('password_hash', 'create')
            ->notEmptyString('password_hash');

        return $validator;
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
        $rules->add($rules->existsIn('user_id', 'Users'), ['errorField' => 'user_id']);

        return $rules;
    }

    /**
     * Clean up old password history entries for a user
     * Keep only the most recent entries based on retention policy
     *
     * @param int $userId The user ID
     * @param int $keepCount Number of recent passwords to keep (default: 5)
     * @return int Number of deleted records
     */
    public function cleanupOldPasswords(int $userId, int $keepCount = 5): int
    {
        // Get IDs of passwords to keep
        $keepIds = $this->find()
            ->select(['id'])
            ->where(['user_id' => $userId])
            ->order(['created' => 'DESC'])
            ->limit($keepCount)
            ->extract('id')
            ->toArray();

        if (empty($keepIds)) {
            return 0;
        }

        // Delete all others for this user
        return $this->deleteAll([
            'user_id' => $userId,
            'id NOT IN' => $keepIds
        ]);
    }
}
