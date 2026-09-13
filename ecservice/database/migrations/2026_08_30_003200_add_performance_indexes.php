<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('centers', 'centers_status_deleted_at_index', ['status', 'deleted_at']);
        $this->addIndex('scases', 'scases_center_id_deleted_at_index', ['center_id', 'deleted_at']);
        $this->addIndex('goals', 'goals_case_id_term_id_deleted_at_index', ['case_id', 'term_id', 'deleted_at']);
        $this->addIndex('goals', 'goals_case_id_category_deleted_at_index', ['case_id', 'category', 'deleted_at']);
        $this->addIndex('messages', 'messages_goal_id_type_index', ['goal_id', 'type']);
        $this->addIndex('messages', 'messages_goal_id_created_at_index', ['goal_id', 'created_at']);
        $this->addIndex('logs', 'logs_center_id_created_at_index', ['center_id', 'created_at']);
        $this->addIndex('goal_evaluations', 'goal_evaluations_goal_id_updated_at_index', ['goal_id', 'updated_at']);
        $this->addIndex('goal_evaluations', 'goal_evaluations_goal_id_period_index', ['goal_id', 'period']);
        $this->addIndex('centers_payments', 'centers_payments_center_id_expiry_date_index', ['center_id', 'expiry_date']);
        $this->addIndex('attendances', 'attendances_case_id_attendance_at_status_index', ['case_id', 'attendance_at', 'status']);
        $this->addIndex('employees_attendance', 'employees_attendance_user_id_attendance_at_status_index', ['user_id', 'attendance_at', 'status']);
        $this->addIndex('employee_leaves', 'employee_leaves_status_date_from_date_to_index', ['status', 'date_from', 'date_to']);
    }

    public function down(): void
    {
        $this->dropIndex('centers', 'centers_status_deleted_at_index');
        $this->dropIndex('scases', 'scases_center_id_deleted_at_index');
        $this->dropIndex('goals', 'goals_case_id_term_id_deleted_at_index');
        $this->dropIndex('goals', 'goals_case_id_category_deleted_at_index');
        $this->dropIndex('messages', 'messages_goal_id_type_index');
        $this->dropIndex('messages', 'messages_goal_id_created_at_index');
        $this->dropIndex('logs', 'logs_center_id_created_at_index');
        $this->dropIndex('goal_evaluations', 'goal_evaluations_goal_id_updated_at_index');
        $this->dropIndex('goal_evaluations', 'goal_evaluations_goal_id_period_index');
        $this->dropIndex('centers_payments', 'centers_payments_center_id_expiry_date_index');
        $this->dropIndex('attendances', 'attendances_case_id_attendance_at_status_index');
        $this->dropIndex('employees_attendance', 'employees_attendance_user_id_attendance_at_status_index');
        $this->dropIndex('employee_leaves', 'employee_leaves_status_date_from_date_to_index');
    }

    private function addIndex(string $table, string $index, array $columns): void
    {
        if (!Schema::hasTable($table) || $this->indexExists($table, $index)) {
            return;
        }

        foreach ($columns as $column) {
            if (!Schema::hasColumn($table, $column)) {
                return;
            }
        }

        try {
            Schema::table($table, function (Blueprint $blueprint) use ($columns, $index) {
                $blueprint->index($columns, $index);
            });
        } catch (\Throwable $e) {
            // Index may already exist under another name.
        }
    }

    private function dropIndex(string $table, string $index): void
    {
        if (!Schema::hasTable($table) || !$this->indexExists($table, $index)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($index) {
            $blueprint->dropIndex($index);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = DB::select("SHOW INDEX FROM `{$table}`");
        foreach ($rows as $row) {
            if (($row->Key_name ?? null) === $index) {
                return true;
            }
        }

        return false;
    }
};
