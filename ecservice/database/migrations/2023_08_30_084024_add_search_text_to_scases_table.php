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
        Schema::table('scases', function (Blueprint $table) {
            $table->string('search_text')->nullable()->after('psychological_study')->index('scases_search_text_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('scases', function (Blueprint $table) {
            //
        });
    }
};
