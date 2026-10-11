<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questionnaires_tasks')) {
            return;
        }

        Schema::table('questionnaires_tasks', function (Blueprint $table) {
            if (! Schema::hasColumn('questionnaires_tasks', 'target_audience_type')) {
                $table->string('target_audience_type', 20)->default('parent')->after('center_id');
            }
            if (! Schema::hasColumn('questionnaires_tasks', 'target_role_id')) {
                $table->unsignedBigInteger('target_role_id')->nullable()->after('target_audience_type');
                $table->index('target_role_id');
            }
        });

        if (Schema::hasColumn('questionnaires_tasks', 'term_id')) {
            $database = Schema::getConnection()->getDatabaseName();
            $row = \Illuminate\Support\Facades\DB::selectOne(
                'SELECT IS_NULLABLE FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1',
                [$database, 'questionnaires_tasks', 'term_id']
            );
            if ($row && strtoupper((string) $row->IS_NULLABLE) === 'NO') {
                \Illuminate\Support\Facades\DB::statement(
                    'ALTER TABLE `questionnaires_tasks` MODIFY `term_id` BIGINT UNSIGNED NULL'
                );
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('questionnaires_tasks')) {
            return;
        }

        Schema::table('questionnaires_tasks', function (Blueprint $table) {
            if (Schema::hasColumn('questionnaires_tasks', 'target_role_id')) {
                $table->dropIndex(['target_role_id']);
                $table->dropColumn('target_role_id');
            }
            if (Schema::hasColumn('questionnaires_tasks', 'target_audience_type')) {
                $table->dropColumn('target_audience_type');
            }
        });
    }
};
