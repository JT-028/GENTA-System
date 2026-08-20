<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

class RenameStudentCodeToLrn extends AbstractMigration
{
    public function up(): void
    {
        $table = $this->table('students');

        // If student_code exists and lrn does not, perform rename and update indexes
        if ($table->hasColumn('student_code') && !$table->hasColumn('lrn')) {
            // Safely drop the old unique index (if it exists) using information_schema guard
            $sql = <<<'SQL'
SET @cnt := (SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'ux_students_student_code');
SET @s := IF(@cnt = 1, 'ALTER TABLE `students` DROP INDEX `ux_students_student_code`;', 'SELECT "idx_missing";');
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SQL;
            $this->execute($sql);

            // Rename column
            $table->renameColumn('student_code', 'lrn')->update();

            // Add unique index on new lrn column if missing
            if (!$table->hasIndex(['lrn'])) {
                $table->addIndex(['lrn'], ['unique' => true, 'name' => 'ux_students_lrn'])->update();
            }
        }
    }

    public function down(): void
    {
        $table = $this->table('students');

        // If lrn exists and student_code does not, rename back and restore index
        if ($table->hasColumn('lrn') && !$table->hasColumn('student_code')) {
            // Remove index on lrn if present
            if ($table->hasIndex(['lrn'])) {
                $table->removeIndex(['lrn']);
            }

            // Rename back
            $table->renameColumn('lrn', 'student_code')->update();

            // Recreate unique index on student_code if missing
            if (!$table->hasIndex(['student_code'])) {
                $table->addIndex(['student_code'], ['unique' => true, 'name' => 'ux_students_student_code'])->update();
            }
        }
    }
}
