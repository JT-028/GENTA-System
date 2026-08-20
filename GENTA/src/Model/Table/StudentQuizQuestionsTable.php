<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;

/**
 * StudentQuizQuestions Model
 *
 * @method \App\Model\Entity\StudentQuizQuestion newEmptyEntity()
 * @method \App\Model\Entity\StudentQuizQuestion newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion get($primaryKey, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\StudentQuizQuestion[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class StudentQuizQuestionsTable extends Table
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

        $this->setTable('student_quiz_questions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('StudentQuiz', [
            'foreignKey' => 'student_quiz_id',
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
            ->integer('student_quiz_id')
            ->requirePresence('student_quiz_id', 'create')
            ->notEmptyString('student_quiz_id');

        $validator
            ->scalar('description')
            ->allowEmptyString('description');

        $validator
            ->scalar('image')
            ->allowEmptyString('image');

        $validator
            ->scalar('choices')
            ->requirePresence('choices', 'create')
            ->notEmptyString('choices');

        $validator
            ->scalar('answer')
            ->requirePresence('answer', 'create')
            ->notEmptyString('answer');

        $validator
            ->scalar('student_answer')
            ->allowEmptyString('student_answer');

        $validator
            ->integer('score')
            ->notEmptyString('score');

        $validator
            ->integer('status')
            ->notEmptyString('status');

        return $validator;
    }

    public function beforeFind(EventInterface $event, Query $query, $options, $primary)
    {
        // Ensure only active questions are returned and preserve the existing query object.
        // Using ->where() is the correct way to augment the incoming Query in a beforeFind.
        $query->where(['StudentQuizQuestions.status' => self::STATUS_ACTIVE]);

        // Optionally ensure a deterministic ordering if none specified.
        if (empty($query->clause('order'))) {
            $query->order(['StudentQuizQuestions.id' => 'ASC']);
        }

        return $query;
    }
}
