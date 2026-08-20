<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * Subjects Model
 *
 * @property \App\Model\Table\QuestionsTable&\Cake\ORM\Association\HasMany $Questions
 * @property \App\Model\Table\StudentQuizTable&\Cake\ORM\Association\HasMany $StudentQuiz
 *
 * @method \App\Model\Entity\Subject newEmptyEntity()
 * @method \App\Model\Entity\Subject newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Subject[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Subject get($primaryKey, $options = [])
 * @method \App\Model\Entity\Subject findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Subject patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Subject[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Subject|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Subject saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Subject[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Subject[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Subject[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Subject[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class SubjectsTable extends Table
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

        $this->setTable('subjects');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('Questions', [
            'foreignKey' => 'subject_id',
        ]);
        $this->hasMany('StudentQuiz', [
            'foreignKey' => 'subject_id',
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
            ->scalar('description')
            ->allowEmptyString('description');

        return $validator;
    }

    public function searchOptions($all = true)
    {
        $subjects = $this->find('all');

        if ($all) $options['All'] = 'All';

        foreach($subjects as $subject)
        {
            $options[$subject->id] = $subject->name;
        }

        return $options;
    }

    public function searchSelection($selection)
    {
        $subjectIDs = [];
        if ($selection === 'All')
        {
            $subjects = $this->find('all');
            foreach($subjects as $subject)
            {
                $subjectIDs[] = $subject->id;
            }

        } else {

            $subjectIDs[] = $selection;
        }

        return $subjectIDs;
    }
}
