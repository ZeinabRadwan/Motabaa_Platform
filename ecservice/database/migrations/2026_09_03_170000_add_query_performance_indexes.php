<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('scase_user', 'scase_user_user_id_relationship_type_index', ['user_id', 'relationship_type']);
        $this->addIndex('scase_user', 'scase_user_scase_id_relationship_type_index', ['scase_id', 'relationship_type']);
        $this->addIndex('terms', 'terms_center_id_starts_at_ends_at_deleted_at_index', ['center_id', 'starts_at', 'ends_at', 'deleted_at']);
        $this->addIndex('term_user', 'term_user_user_id_term_id_index', ['user_id', 'term_id']);
        $this->addIndex('logs', 'logs_center_id_created_by_index', ['center_id', 'created_by']);
        $this->addIndex('logs', 'logs_center_id_model_type_index', ['center_id', 'model_type']);
        $this->addIndex('scases_payments', 'scases_payments_case_fee_id_status_index', ['case_fee_id', 'status']);
        $this->addIndex('users', 'users_status_index', ['status']);
        $this->addIndex('users', 'users_contract_end_date_index', ['contract_end_date']);
        $this->addIndex('users', 'users_id_expiry_date_index', ['id_expiry_date']);
        $this->addIndex('scase_disability', 'scase_disability_disability_id_scase_id_index', ['disability_id', 'scase_id']);
        $this->addIndex('scase_service', 'scase_service_service_id_scase_id_index', ['service_id', 'scase_id']);
    }

    public function down(): void
    {
        $this->dropIndex('scase_user', 'scase_user_user_id_relationship_type_index');
        $this->dropIndex('scase_user', 'scase_user_scase_id_relationship_type_index');
        $this->dropIndex('terms', 'terms_center_id_starts_at_ends_at_deleted_at_index');
        $this->dropIndex('term_user', 'term_user_user_id_term_id_index');
        $this->dropIndex('logs', 'logs_center_id_created_by_index');
        $this->dropIndex('logs', 'logs_center_id_model_type_index');
        $this->dropIndex('scases_payments', 'scases_payments_case_fee_id_status_index');
        $this->dropIndex('users', 'users_status_index');
        $this->dropIndex('users', 'users_contract_end_date_index');
        $this->dropIndex('users', 'users_id_expiry_date_index');
        $this->dropIndex('scase_disability', 'scase_disability_disability_id_scase_id_index');
        $this->dropIndex('scase_service', 'scase_service_service_id_scase_id_index');
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
