<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

class MelcsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('melcs');
        $this->setDisplayField('description');
        $this->setPrimaryKey('id');

        // optional timestamp behaviour if upload_date/modified exist
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'upload_date' => 'new',
                ]
            ]
        ]);

        // Associations
        $this->belongsTo('Subjects', [
            'foreignKey' => 'subject_id',
            'joinType' => 'LEFT'
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('description')
            ->maxLength('description', 1000)
            ->requirePresence('description', 'create')
            ->notEmptyString('description');

        $validator
            ->integer('teacher_id')
            ->allowEmptyString('teacher_id');

        $validator
            ->integer('subject_id')
            ->allowEmptyString('subject_id');

        return $validator;
    }
}
