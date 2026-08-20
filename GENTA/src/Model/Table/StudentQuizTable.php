<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Event\EventInterface;
use Cake\Datasource\EntityInterface;
use ArrayObject;

/**
 * StudentQuiz Model
 *
 * @property \App\Model\Table\StudentsTable&\Cake\ORM\Association\BelongsTo $Students
 * @property \App\Model\Table\SubjectsTable&\Cake\ORM\Association\BelongsTo $Subjects
 *
 * @method \App\Model\Entity\StudentQuiz newEmptyEntity()
 * @method \App\Model\Entity\StudentQuiz newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuiz[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuiz get($primaryKey, $options = [])
 * @method \App\Model\Entity\StudentQuiz findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\StudentQuiz patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuiz[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\StudentQuiz|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\StudentQuiz saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\StudentQuiz[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\StudentQuiz[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\StudentQuiz[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\StudentQuiz[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class StudentQuizTable extends Table
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

        $this->setTable('student_quiz');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Students', [
            'foreignKey' => 'student_id',
            'joinType' => 'INNER',
        ]);

        $this->belongsTo('Subjects', [
            'foreignKey' => 'subject_id',
            'joinType' => 'INNER',
        ]);

        // Optional association to quiz versions used when the attempt was taken
        $this->belongsTo('QuizVersions', [
            'foreignKey' => 'quiz_version_id',
            'joinType' => 'LEFT'
        ]);

        $this->hasMany('StudentQuizQuestions', [
            'foreignKey' => 'student_quiz_id',
        ]);
    }

    /**
     * Before save hook - attempt to tag new student_quiz rows with the latest
     * quiz_versions entry for the same subject (if any exist).
     *
     * This makes tagging resilient: any code that creates StudentQuiz records
     * will get the most recent version for the subject automatically.
     */
    public function beforeSave(EventInterface $event, EntityInterface $entity, ArrayObject $options)
    {
        // Only apply for new entities and when subject_id is present and quiz_version_id not set
        if ($entity->isNew() && !empty($entity->subject_id) && empty($entity->quiz_version_id)) {
            try {
                $tableLocator = $this->getTableLocator();
                if ($tableLocator->exists('QuizVersions')) {
                    $qvTable = $tableLocator->get('QuizVersions');
                    $conn = $qvTable->getConnection();

                    // Try JSON_EXTRACT-based query to find latest version for this subject (works on MySQL 5.7+)
                    $sql = "SELECT id FROM `quiz_versions` WHERE JSON_EXTRACT(metadata, '$.subject_id') = ? ORDER BY version_number DESC, id DESC LIMIT 1";
                    $stmt = null;
                    try {
                        $stmt = $conn->execute($sql, [$entity->subject_id]);
                    } catch (\Throwable $e) {
                        // JSON_EXTRACT may not be available or metadata empty; fallback below
                        $stmt = null;
                    }

                    if ($stmt) {
                        $row = $stmt->fetch('assoc');
                        if (!empty($row['id'])) {
                            $entity->quiz_version_id = (int)$row['id'];
                            return;
                        }
                    }

                    // Fallback: search metadata text for subject_id (best-effort)
                    $found = $qvTable->find()
                        ->where(['metadata LIKE' => '%"subject_id":' . (int)$entity->subject_id . '%'])
                        ->order(['version_number' => 'DESC', 'id' => 'DESC'])
                        ->first();
                    if ($found) {
                        $entity->quiz_version_id = $found->id;
                        return;
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal - tagging is best-effort. Log if needed.
                // Log::warning('Failed to auto-tag quiz_version_id: ' . $e->getMessage());
            }
        }
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
            ->integer('student_id')
            ->requirePresence('student_id', 'create')
            ->notEmptyString('student_id');

        $validator
            ->integer('subject_id')
            ->requirePresence('subject_id', 'create')
            ->notEmptyString('subject_id');

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
        $rules->add($rules->existsIn('student_id', 'Students'), ['errorField' => 'student_id']);
        $rules->add($rules->existsIn('subject_id', 'Subjects'), ['errorField' => 'subject_id']);

        return $rules;
    }
}
