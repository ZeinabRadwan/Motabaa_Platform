<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('questionnaire_task_starts');

        $taskIdColumn = $this->matchingReferenceColumn('questionnaires_tasks', 'id');
        $userIdColumn = $this->matchingReferenceColumn('users', 'id');

        Schema::create('questionnaire_task_starts', function (Blueprint $table) use ($taskIdColumn, $userIdColumn) {
            $table->engine = 'InnoDB';
            $table->id();
            $table->{$taskIdColumn}('task_id');
            $table->{$userIdColumn}('user_id');
            $table->timestamp('started_at');
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index('task_id');
            $table->index(['task_id', 'started_at']);
        });

        if (! Schema::hasTable('questionnaires_answers')) {
            return;
        }

        $now = now();
        $rows = DB::table('questionnaires_answers')
            ->select('task_id', 'user_id', DB::raw('MIN(COALESCE(created_at, updated_at)) as started_at'))
            ->where('user_id', '>', 0)
            ->groupBy('task_id', 'user_id')
            ->get();

        foreach ($rows as $row) {
            if (! DB::table('questionnaires_tasks')->where('id', $row->task_id)->exists()) {
                continue;
            }
            if (! DB::table('users')->where('id', $row->user_id)->exists()) {
                continue;
            }

            DB::table('questionnaire_task_starts')->insertOrIgnore([
                'task_id' => $row->task_id,
                'user_id' => $row->user_id,
                'started_at' => $row->started_at ?? $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_task_starts');
    }

    private function matchingReferenceColumn(string $table, string $column): string
    {
        if (! Schema::hasTable($table)) {
            return 'unsignedBigInteger';
        }

        $database = DB::getDatabaseName();
        $row = DB::selectOne(
            'SELECT COLUMN_TYPE FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
            [$database, $table, $column]
        );

        $columnType = strtolower((string) ($row->COLUMN_TYPE ?? ''));

        if (str_contains($columnType, 'bigint')) {
            return 'unsignedBigInteger';
        }

        if (str_contains($columnType, 'int')) {
            return 'unsignedInteger';
        }

        return 'unsignedBigInteger';
    }
};
