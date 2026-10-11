<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questionnaires_questions')) {
            return;
        }

        if (Schema::hasColumn('questionnaires_questions', 'section_id')) {
            return;
        }

        if (! Schema::hasTable('questionnaires_sections')) {
            return;
        }

        $sectionIdType = $this->referenceColumnSqlType('questionnaires_sections', 'id') ?? 'BIGINT UNSIGNED';
        $canForeignKey = $this->tableUsesInnoDb('questionnaires_sections')
            && $this->tableUsesInnoDb('questionnaires_questions');

        $fkSql = $canForeignKey
            ? ', ADD CONSTRAINT `questionnaires_questions_section_id_foreign` FOREIGN KEY (`section_id`) REFERENCES `questionnaires_sections` (`id`) ON DELETE SET NULL'
            : '';

        $alterWithFk = "ALTER TABLE `questionnaires_questions`
            ADD COLUMN `section_id` {$sectionIdType} NULL AFTER `questionnaire_id`,
            ADD KEY `questionnaires_questions_section_id_index` (`section_id`)
            {$fkSql}";

        try {
            DB::statement($alterWithFk);
        } catch (\Throwable $exception) {
            if (! $canForeignKey || $fkSql === '') {
                throw $exception;
            }

            DB::statement("ALTER TABLE `questionnaires_questions`
                ADD COLUMN `section_id` {$sectionIdType} NULL AFTER `questionnaire_id`,
                ADD KEY `questionnaires_questions_section_id_index` (`section_id`)");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('questionnaires_questions') || ! Schema::hasColumn('questionnaires_questions', 'section_id')) {
            return;
        }

        if ($this->foreignKeyExists('questionnaires_questions', 'questionnaires_questions_section_id_foreign')) {
            DB::statement('ALTER TABLE `questionnaires_questions` DROP FOREIGN KEY `questionnaires_questions_section_id_foreign`');
        }

        Schema::table('questionnaires_questions', function (Blueprint $table) {
            $table->dropColumn('section_id');
        });
    }

    private function foreignKeyExists(string $table, string $constraintName): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ?
             AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$database, $table, $constraintName, 'FOREIGN KEY']
        );

        return $row !== null;
    }

    private function referenceColumnSqlType(string $table, string $column): ?string
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$database, $table, $column]
        );

        $columnType = strtoupper(trim((string) ($row->COLUMN_TYPE ?? '')));

        return $columnType !== '' ? $columnType : null;
    }

    private function tableUsesInnoDb(string $table): bool
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT ENGINE FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? LIMIT 1',
            [$database, $table]
        );

        return strtoupper((string) ($row->ENGINE ?? '')) === 'INNODB';
    }

};
