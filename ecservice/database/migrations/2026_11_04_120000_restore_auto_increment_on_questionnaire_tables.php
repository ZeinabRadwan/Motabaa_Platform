<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * LIVE lost AUTO_INCREMENT on questionnaires.id (likely a database import), so every new questionnaire insert
 * failed with "1364 Field 'id' doesn't have a default value". Restores AUTO_INCREMENT on the questionnaire
 * module tables without touching row data; tables that are already correct are skipped.
 */
return new class extends Migration
{
    private const TABLES = [
        'questionnaires',
        'questionnaires_tasks',
        'questionnaires_questions',
        'questionnaires_sections',
        'questionnaires_answers',
        'questionnaire_task_submissions',
        'questionnaire_task_starts',
        'questionnaire_task_notified_users',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::TABLES as $table) {
            $this->restoreAutoIncrement($table);
        }
    }

    public function down(): void
    {
        // Intentionally empty: removing AUTO_INCREMENT would break inserts again.
    }

    private function restoreAutoIncrement(string $table): void
    {
        $column = DB::selectOne(
            'SELECT COLUMN_TYPE, COLUMN_KEY, EXTRA FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$table, 'id']
        );
        if (! $column || str_contains(strtolower((string) $column->EXTRA), 'auto_increment')) {
            return;
        }

        $this->assertIdsAreUsable($table);

        $keySql = '';
        if ((string) $column->COLUMN_KEY === '') {
            $keySql = $this->tableHasPrimaryKey($table) ? ', ADD UNIQUE KEY `'.$table.'_id_unique` (`id`)' : ', ADD PRIMARY KEY (`id`)';
        }

        // MySQL refuses to add AUTO_INCREMENT to a column referenced by foreign keys, so they are
        // dropped and recreated with their original rules (recreation re-validates every row).
        $foreignKeys = $this->foreignKeysReferencing($table);
        foreach ($foreignKeys as $foreignKey) {
            DB::statement(sprintf('ALTER TABLE `%s` DROP FOREIGN KEY `%s`', $foreignKey['table'], $foreignKey['name']));
        }

        try {
            DB::statement(sprintf(
                'ALTER TABLE `%s` MODIFY `id` %s NOT NULL AUTO_INCREMENT%s',
                $table,
                $column->COLUMN_TYPE,
                $keySql
            ));
        } finally {
            foreach ($foreignKeys as $foreignKey) {
                DB::statement(sprintf(
                    'ALTER TABLE `%s` ADD CONSTRAINT `%s` FOREIGN KEY (%s) REFERENCES `%s` (%s) ON DELETE %s ON UPDATE %s',
                    $foreignKey['table'],
                    $foreignKey['name'],
                    $this->columnList($foreignKey['columns']),
                    $table,
                    $this->columnList($foreignKey['referenced_columns']),
                    $foreignKey['delete_rule'],
                    $foreignKey['update_rule']
                ));
            }
        }
    }

    /**
     * Adding AUTO_INCREMENT renumbers 0/NULL ids and fails on duplicates; refuse instead of rewriting data.
     */
    private function assertIdsAreUsable(string $table): void
    {
        $invalid = DB::table($table)->where(fn ($query) => $query->whereNull('id')->orWhere('id', '<=', 0))->count();
        $duplicated = DB::table($table)->select('id')->groupBy('id')->havingRaw('COUNT(*) > 1')->exists();

        if ($invalid > 0 || $duplicated) {
            throw new RuntimeException(sprintf(
                'Cannot restore AUTO_INCREMENT on %s.id: %d row(s) with empty/zero id, duplicates: %s. Fix these rows manually first.',
                $table,
                $invalid,
                $duplicated ? 'yes' : 'no'
            ));
        }
    }

    private function tableHasPrimaryKey(string $table): bool
    {
        return DB::selectOne(
            'SELECT 1 AS found FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND CONSTRAINT_TYPE = ? LIMIT 1',
            [$table, 'PRIMARY KEY']
        ) !== null;
    }

    /**
     * @return array<int, array{name: string, table: string, columns: array<int, string>, referenced_columns: array<int, string>, delete_rule: string, update_rule: string}>
     */
    private function foreignKeysReferencing(string $table): array
    {
        $rows = DB::select(
            'SELECT k.CONSTRAINT_NAME, k.TABLE_NAME, k.COLUMN_NAME, k.REFERENCED_COLUMN_NAME, r.DELETE_RULE, r.UPDATE_RULE
             FROM information_schema.KEY_COLUMN_USAGE k
             INNER JOIN information_schema.REFERENTIAL_CONSTRAINTS r
                ON r.CONSTRAINT_SCHEMA = k.CONSTRAINT_SCHEMA
               AND r.CONSTRAINT_NAME = k.CONSTRAINT_NAME
               AND r.TABLE_NAME = k.TABLE_NAME
             WHERE k.CONSTRAINT_SCHEMA = DATABASE()
               AND k.REFERENCED_TABLE_NAME = ?
               AND k.CONSTRAINT_NAME IN (
                   SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                   WHERE CONSTRAINT_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = ? AND REFERENCED_COLUMN_NAME = ?
               )
             ORDER BY k.TABLE_NAME, k.CONSTRAINT_NAME, k.ORDINAL_POSITION',
            [$table, $table, 'id']
        );

        $foreignKeys = [];
        foreach ($rows as $row) {
            $key = $row->TABLE_NAME.'.'.$row->CONSTRAINT_NAME;
            $foreignKeys[$key] ??= [
                'name' => $row->CONSTRAINT_NAME,
                'table' => $row->TABLE_NAME,
                'columns' => [],
                'referenced_columns' => [],
                'delete_rule' => $row->DELETE_RULE,
                'update_rule' => $row->UPDATE_RULE,
            ];
            $foreignKeys[$key]['columns'][] = $row->COLUMN_NAME;
            $foreignKeys[$key]['referenced_columns'][] = $row->REFERENCED_COLUMN_NAME;
        }

        return array_values($foreignKeys);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function columnList(array $columns): string
    {
        return implode(', ', array_map(fn ($column) => '`'.$column.'`', $columns));
    }
};
