<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('questionnaires_sections')) {
            if ($this->sectionsTableUsable()) {
                return;
            }

            Schema::dropIfExists('questionnaires_sections');
        }

        if (! Schema::hasTable('questionnaires')) {
            return;
        }

        $questionnaireIdType = $this->referenceColumnSqlType('questionnaires', 'id');
        if ($questionnaireIdType === null) {
            $questionnaireIdType = 'BIGINT UNSIGNED';
        }

        $charset = $this->tableCharset('questionnaires');
        $canForeignKey = $this->tableUsesInnoDb('questionnaires');

        $fkSql = $canForeignKey
            ? ', CONSTRAINT `questionnaires_sections_questionnaire_id_foreign` FOREIGN KEY (`questionnaire_id`) REFERENCES `questionnaires` (`id`) ON DELETE CASCADE'
            : '';

        $createSql = "CREATE TABLE `questionnaires_sections` (
            `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            `questionnaire_id` {$questionnaireIdType} NOT NULL,
            `title` VARCHAR(255) NOT NULL,
            `title_local` VARCHAR(255) NULL,
            `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
            `created_at` TIMESTAMP NULL,
            `updated_at` TIMESTAMP NULL,
            `deleted_at` TIMESTAMP NULL,
            PRIMARY KEY (`id`),
            KEY `questionnaires_sections_questionnaire_id_sort_order_index` (`questionnaire_id`, `sort_order`)
            {$fkSql}
        ) ENGINE=InnoDB DEFAULT CHARSET={$charset['charset']} COLLATE={$charset['collation']}";

        try {
            DB::statement($createSql);
        } catch (\Throwable $firstAttempt) {
            if (! $canForeignKey || $fkSql === '') {
                throw $firstAttempt;
            }

            $createSqlWithoutFk = "CREATE TABLE `questionnaires_sections` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `questionnaire_id` {$questionnaireIdType} NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `title_local` VARCHAR(255) NULL,
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` TIMESTAMP NULL,
                `updated_at` TIMESTAMP NULL,
                `deleted_at` TIMESTAMP NULL,
                PRIMARY KEY (`id`),
                KEY `questionnaires_sections_questionnaire_id_sort_order_index` (`questionnaire_id`, `sort_order`),
                KEY `questionnaires_sections_questionnaire_id_index` (`questionnaire_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET={$charset['charset']} COLLATE={$charset['collation']}";

            DB::statement($createSqlWithoutFk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaires_sections');
    }

    private function sectionsTableUsable(): bool
    {
        if (! Schema::hasColumn('questionnaires_sections', 'questionnaire_id')) {
            return false;
        }

        return Schema::hasColumn('questionnaires_sections', 'title')
            && Schema::hasColumn('questionnaires_sections', 'sort_order');
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

    /**
     * @return array{charset: string, collation: string}
     */
    private function tableCharset(string $table): array
    {
        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT CCSA.CHARACTER_SET_NAME AS charset_name, T.TABLE_COLLATION AS table_collation
             FROM information_schema.TABLES T
             INNER JOIN information_schema.COLLATION_CHARACTER_SET_APPLICABILITY CCSA
                ON CCSA.COLLATION_NAME = T.TABLE_COLLATION
             WHERE T.TABLE_SCHEMA = ? AND T.TABLE_NAME = ? LIMIT 1',
            [$database, $table]
        );

        $charset = (string) ($row->charset_name ?? 'utf8mb4');
        $collation = (string) ($row->table_collation ?? 'utf8mb4_unicode_ci');

        return [
            'charset' => $charset !== '' ? $charset : 'utf8mb4',
            'collation' => $collation !== '' ? $collation : 'utf8mb4_unicode_ci',
        ];
    }
};
