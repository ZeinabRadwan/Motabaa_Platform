<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questionnaires_questions')) {
            return;
        }

        if (Schema::hasColumn('questionnaires_questions', 'options')) {
            return;
        }

        Schema::table('questionnaires_questions', function (Blueprint $table) {
            $table->json('options')->nullable()->after('required');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('questionnaires_questions') || ! Schema::hasColumn('questionnaires_questions', 'options')) {
            return;
        }

        Schema::table('questionnaires_questions', function (Blueprint $table) {
            $table->dropColumn('options');
        });
    }
};
