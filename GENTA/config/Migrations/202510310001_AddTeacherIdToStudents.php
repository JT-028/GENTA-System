<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class AddTeacherIdToStudents extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('students');

        if (!$table->hasColumn('teacher_id')) {
            $table->addColumn('teacher_id', 'integer', [
                'null' => true,
                'after' => 'id',
            ])->update();
        }

        // Add index on teacher_id if missing
        if (!$table->hasIndex(['teacher_id'])) {
            $table->addIndex(['teacher_id'], ['name' => 'idx_students_teacher_id'])->update();
        }

        // Add unique index on student_code if missing
        if (!$table->hasIndex(['student_code'])) {
            $table->addIndex(['student_code'], ['unique' => true, 'name' => 'ux_students_student_code'])->update();
        }

        // Add FK if it's not present (guarded via information_schema)
        $sql = <<<'SQL'
SET @cnt := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_NAME = 'fk_students_teacher' AND TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students');
SET @s := IF(@cnt = 0, 'ALTER TABLE `students` ADD CONSTRAINT `fk_students_teacher` FOREIGN KEY (`teacher_id`) REFERENCES `users`(`id`) ON DELETE SET NULL ON UPDATE CASCADE;', 'SELECT "fk_exists";');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SQL;

        $this->execute($sql);
    }

    public function down(): void
    {
        $table = $this->table('students');

        if ($table->hasIndex(['student_code'])) {
            $table->removeIndex(['student_code']);
        }

        if ($table->hasIndex(['teacher_id'])) {
            $table->removeIndex(['teacher_id']);
        }

        if ($table->hasColumn('teacher_id')) {
            $table->removeColumn('teacher_id');
        }
    }
}
