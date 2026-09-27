<?php

namespace App\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait MigrationForeignKeyHelpers
{
    /**
     * @param  'cascade'|'set null'  $onDelete
     */
    protected function addForeignKeyColumn(
        Blueprint $table,
        string $column,
        string $referencedTable,
        string $referencedColumn,
        bool $nullable,
        string $onDelete = 'cascade',
        ?string $after = null
    ): void {
        $columnType = $this->referencedColumnType($referencedTable, $referencedColumn);

        $definition = match ($columnType) {
            'bigInteger' => $table->bigInteger($column),
            'unsignedBigInteger' => $table->unsignedBigInteger($column),
            'integer' => $table->integer($column),
            default => $table->unsignedInteger($column),
        };

        if ($nullable) {
            $definition->nullable();
        }

        if ($after !== null) {
            $definition->after($after);
        }

        if ($this->canAddForeignKey($referencedTable, $referencedColumn)) {
            $foreign = $table->foreign($column)
                ->references($referencedColumn)
                ->on($referencedTable);

            if ($onDelete === 'set null') {
                $foreign->nullOnDelete();
            } else {
                $foreign->cascadeOnDelete();
            }
        } else {
            $table->index($column);
        }
    }

    protected function dropForeignKeyIfExists(string $table, string $column): void
    {
        $database = Schema::getConnection()->getDatabaseName();

        $constraints = DB::select(
            'SELECT CONSTRAINT_NAME AS name
             FROM information_schema.KEY_COLUMN_USAGE
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
               AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, $table, $column]
        );

        foreach ($constraints as $constraint) {
            DB::statement(sprintf(
                'ALTER TABLE `%s` DROP FOREIGN KEY `%s`',
                $table,
                $constraint->name
            ));
        }
    }

    protected function referencedColumnType(string $table, string $column): string
    {
        $database = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE AS column_type
             FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
             LIMIT 1',
            [$database, $table, $column]
        );

        $type = strtolower($row->column_type ?? 'bigint(20) unsigned');

        if (str_contains($type, 'bigint') && ! str_contains($type, 'unsigned')) {
            return 'bigInteger';
        }
        if (str_contains($type, 'bigint')) {
            return 'unsignedBigInteger';
        }
        if (str_contains($type, 'int') && ! str_contains($type, 'unsigned')) {
            return 'integer';
        }

        return 'unsignedInteger';
    }

    protected function canAddForeignKey(string $table, string $column): bool
    {
        $database = Schema::getConnection()->getDatabaseName();

        $parent = DB::selectOne(
            'SELECT ENGINE AS engine
             FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?
             LIMIT 1',
            [$database, $table]
        );

        if (! $parent || ! in_array(strtolower((string) $parent->engine), ['innodb', 'xtradb'], true)) {
            return false;
        }

        $key = DB::selectOne(
            'SELECT INDEX_NAME
             FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1
             LIMIT 1',
            [$database, $table, $column]
        );

        return $key !== null;
    }
}
