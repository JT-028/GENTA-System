<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;

/**
 * Questions Model
 *
 * @property \App\Model\Table\SubjectsTable&\Cake\ORM\Association\BelongsTo $Subjects
 *
 * @method \App\Model\Entity\Question newEmptyEntity()
 * @method \App\Model\Entity\Question newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Question[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Question get($primaryKey, $options = [])
 * @method \App\Model\Entity\Question findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Question patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Question[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Question|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Question saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Question[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class QuestionsTable extends Table
{
    const STATUS_ACTIVE = 1;
    const STATUS_DELETED = 2;

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('questions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Subjects', [
            'foreignKey' => 'subject_id',
            'joinType' => 'INNER',
        ]);
        // Associate questions to the user (teacher) who created them
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
            ->integer('subject_id')
            ->requirePresence('subject_id', 'create')
            ->notEmptyString('subject_id', __('Subject is required.'));

        $validator
            ->scalar('description')
            ->requirePresence('description', 'create')
            ->notEmptyString('description', __('Question is required.'))
            ->add('description', 'notOnlyWhitespace', [
                'rule' => function ($value, $context) {
                    return !empty(trim($value));
                },
                'message' => __('Question cannot be empty or contain only spaces.')
            ]);

        $validator
            ->scalar('image')
            ->allowEmptyString('image');

        $validator
            ->scalar('choices')
            ->requirePresence('choices', 'create')
            ->notEmptyString('choices', __('Choices are required.'));

        $validator
            ->scalar('answer')
            ->requirePresence('answer', 'create')
            ->notEmptyString('answer', __('Answer is required.'));

        $validator
            ->integer('score')
            ->notEmptyString('score');

        $validator
            ->integer('status')
            ->notEmptyString('status');

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
        $rules->add($rules->existsIn('subject_id', 'Subjects'), ['errorField' => 'subject_id']);

        return $rules;
    }

    public function beforeFind(EventInterface $event, Query $query, $options, $primary)
    {
        // By default, ensure only active questions are returned and preserve the existing query object.
        // Caller may disable this behavior by passing the option 'status_filter' => false to find().
        // Use the Query object's options (getOptions) to reliably read the flag.
        $opts = $query->getOptions();
        $applyStatusFilter = true;
        if (is_array($opts) && array_key_exists('status_filter', $opts) && $opts['status_filter'] === false) {
            $applyStatusFilter = false;
        }

        if ($applyStatusFilter) {
            $query->where(['Questions.status' => self::STATUS_ACTIVE]);
        }

        if (empty($query->clause('order'))) {
            $query->order(['Questions.id' => 'ASC']);
        }

        return $query;
    }

    /**
     * Finder to scope questions to an owner (teacher)
     * Usage: $this->Questions->find('owned', ['ownerId' => $id])
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
