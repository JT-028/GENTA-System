<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * QuizVersions Table
 *
 * This table stores snapshots (versions) of question sets for a subject.
 * It is intentionally flexible and does not assume a separate `quizzes` table
 * exists in the schema. The subject id is recorded in the `metadata` JSON
 * so we can locate versions per subject without requiring a hard FK.
 */
class QuizVersionsTable extends Table
{
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('quiz_versions');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        // Only set created timestamp when new records are inserted
        $this->addBehavior('Timestamp', [
            'events' => [
                'Model.beforeSave' => [
                    'created' => 'new'
                ]
            ]
        ]);
    }

    public function validationDefault(Validator $validator): Validator
    {
        // quiz_id is optional in this schema (project may not have a quizzes table)
        $validator
            ->integer('quiz_id')
            ->allowEmptyString('quiz_id');

        $validator
            ->integer('version_number')
            ->allowEmptyString('version_number');

        // question_ids should be provided and stored as JSON
        $validator
            ->requirePresence('question_ids', 'create')
            ->notEmptyString('question_ids');

        return $validator;
    }
}
