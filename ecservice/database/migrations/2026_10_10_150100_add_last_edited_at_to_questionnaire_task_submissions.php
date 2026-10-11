<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questionnaire_task_submissions')) {
            return;
        }

        if (Schema::hasColumn('questionnaire_task_submissions', 'last_edited_at')) {
            return;
        }

        Schema::table('questionnaire_task_submissions', function (Blueprint $table) {
            $table->timestamp('last_edited_at')->nullable()->after('submitted_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('questionnaire_task_submissions') || ! Schema::hasColumn('questionnaire_task_submissions', 'last_edited_at')) {
            return;
        }

        Schema::table('questionnaire_task_submissions', function (Blueprint $table) {
            $table->dropColumn('last_edited_at');
        });
    }
};
