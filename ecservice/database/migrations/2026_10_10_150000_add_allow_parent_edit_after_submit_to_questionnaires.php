<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('questionnaires')) {
            return;
        }

        if (Schema::hasColumn('questionnaires', 'allow_parent_edit_after_submit')) {
            return;
        }

        Schema::table('questionnaires', function (Blueprint $table) {
            $table->boolean('allow_parent_edit_after_submit')->default(false)->after('title_local');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('questionnaires') || ! Schema::hasColumn('questionnaires', 'allow_parent_edit_after_submit')) {
            return;
        }

        Schema::table('questionnaires', function (Blueprint $table) {
            $table->dropColumn('allow_parent_edit_after_submit');
        });
    }
};
