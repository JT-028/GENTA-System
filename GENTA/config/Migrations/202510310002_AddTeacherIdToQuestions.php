<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddTeacherIdToQuestions extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('questions');

        if (!$table->hasColumn('teacher_id')) {
            $table->addColumn('teacher_id', 'integer', [
                'null' => true,
                'after' => 'id',
            ])->update();
        }

        // Add index on teacher_id if missing
        if (!$table->hasIndex(['teacher_id'])) {
            $table->addIndex(['teacher_id'], ['name' => 'idx_questions_teacher_id'])->update();
        }

        // Add FK if not present (guard via information_schema)
        $sql = <<<'SQL'
SET @cnt := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_questions_teacher' AND TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions');
SET @s := IF(@cnt = 0, 'ALTER TABLE `questions` ADD CONSTRAINT `fk_questions_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;', 'SELECT "fk_exists";');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SQL;

        $this->execute($sql);
    }

    public function down(): void
    {
        $table = $this->table('questions');

        if ($table->hasIndex(['teacher_id'])) {
            $table->removeIndex(['teacher_id']);
        }

        if ($table->hasColumn('teacher_id')) {
            $table->removeColumn('teacher_id');
        }
    }
}
