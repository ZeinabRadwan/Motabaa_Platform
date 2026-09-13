<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->string('parents_ids')->nullable()->index()->after('title_local');
            $table->text('search_text')->nullable()->after('category');
            $table->softDeletes()->after('evaluation_method_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropColumn(['parents_ids', 'search_text', 'deleted_at']);
        });
    }
};
