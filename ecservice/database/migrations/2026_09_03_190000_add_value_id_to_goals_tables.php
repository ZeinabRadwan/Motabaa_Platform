<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Goal and GoalEvaluation both resolve `value` to an evaluation method value
     * on save, and the assessments period filter reads `goals.value_id`, but no
     * migration ever created the column. Environments built from migrations
     * alone therefore fail on those writes and on any period after the first.
     */
    private array $tables = ['goals', 'goal_evaluations'];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || Schema::hasColumn($table, 'value_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->foreignId('value_id')
                    ->nullable()
                    ->after('value')
                    ->constrained('evaluation_methods_values')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'value_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropConstrainedForeignId('value_id');
            });
        }
    }
};
