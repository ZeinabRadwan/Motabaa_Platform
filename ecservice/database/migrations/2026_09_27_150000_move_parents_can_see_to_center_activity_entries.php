<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('center_activity_entries', function (Blueprint $table) {
            $table->boolean('parents_can_see')->default(false)->after('content');
        });

        Schema::table('center_activities', function (Blueprint $table) {
            $table->dropColumn('parents_can_see');
        });
    }

    public function down(): void
    {
        Schema::table('center_activities', function (Blueprint $table) {
            $table->boolean('parents_can_see')->default(false)->after('title');
        });

        Schema::table('center_activity_entries', function (Blueprint $table) {
            $table->dropColumn('parents_can_see');
        });
    }
};
