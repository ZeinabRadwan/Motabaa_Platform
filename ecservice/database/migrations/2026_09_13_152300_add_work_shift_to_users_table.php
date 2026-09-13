<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('users', 'work_shift')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('work_shift', 20)->nullable()->after('department');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'work_shift')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('work_shift');
            });
        }
    }
};
