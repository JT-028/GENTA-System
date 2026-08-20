<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Students Model
 *
 * @property \App\Model\Table\StudentQuizTable&\Cake\ORM\Association\HasMany $StudentQuiz
 *
 * @method \App\Model\Entity\Student newEmptyEntity()
 * @method \App\Model\Entity\Student newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Student[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Student get($primaryKey, $options = [])
 * @method \App\Model\Entity\Student findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Student patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Student[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Student|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Student saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Student[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Student[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Student[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Student[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class StudentsTable extends Table
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

        $this->setTable('students');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('StudentQuiz', [
            'foreignKey' => 'student_id',
        ]);
        // Associate students to the user (teacher) who added/owns them
        $this->belongsTo('Users', [
            'foreignKey' => 'teacher_id',
            'joinType' => 'LEFT',
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('lrn')
            // LRN: must be a 12-digit numeric string (Learner Reference Number)
            ->maxLength('lrn', 12)
            ->minLength('lrn', 12)
            ->requirePresence('lrn', 'create')
            ->notEmptyString('lrn')
            ->add('lrn', 'format', [
                'rule' => ['custom', '/^[0-9]{12}$/'],
                'message' => 'LRN must be a 12-digit number.'
            ]);

        $validator
            ->integer('grade')
            ->requirePresence('grade', 'create')
            ->notEmptyString('grade')
            ->range('grade', [1, 6], 'Grade must be between 1 and 6');

        $validator
            ->scalar('section')
            ->maxLength('section', 255)
            ->requirePresence('section', 'create')
            ->notEmptyString('section');

        $validator
            ->scalar('remarks')
            ->allowEmptyString('remarks');

        return $validator;
    }

    /**
     * Application rules for ensuring data integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules Rules checker instance.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        // Ensure lrn is unique
    $rules->add($rules->isUnique(['lrn'], 'A student with this LRN already exists.'));

        return $rules;
    }

    /**
     * Finder to scope records to an owner (teacher)
     * Usage: $this->Students->find('owned', ['ownerId' => $id])
     *
     * @param \Cake\ORM\Query $query The query to modify
     * @param array $options Options array. Recognized: ownerId
     * @return \Cake\ORM\Query
     */
    public function findOwned(Query $query, array $options)
    {
        $ownerId = $options['ownerId'] ?? null;
        if ($ownerId) {
            $alias = $this->getAlias();
            $query->where(["{$alias}.teacher_id" => $ownerId]);
        }

        return $query;
    }
}
