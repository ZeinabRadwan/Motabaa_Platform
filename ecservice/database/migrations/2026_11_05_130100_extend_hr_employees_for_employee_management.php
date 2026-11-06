<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employment dates and the current attendance configuration of an employee. The attendance method
 * "gps" becomes "electronic"; GPS verification is now its own policy column.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_employees', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employees', 'employment_start_date')) {
                $table->date('employment_start_date')->nullable()->after('manager_id');
            }
            if (! Schema::hasColumn('hr_employees', 'contract_start_date')) {
                $table->date('contract_start_date')->nullable()->after('employment_start_date');
            }
            if (! Schema::hasColumn('hr_employees', 'gps_policy')) {
                $table->string('gps_policy', 20)->default('none')->after('attendance_mode');
            }
            if (! Schema::hasColumn('hr_employees', 'grace_minutes')) {
                $table->unsignedSmallInteger('grace_minutes')->nullable()->after('gps_policy');
            }
            if (! Schema::hasColumn('hr_employees', 'attendance_policy_id')) {
                $table->foreignId('attendance_policy_id')->nullable()->after('grace_minutes')
                    ->constrained('hr_attendance_policies')->nullOnDelete();
            }
        });

        DB::table('hr_employees')->where('attendance_mode', 'gps')->update(['attendance_mode' => 'electronic', 'gps_policy' => 'required']);

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->index(['center_id', 'attendance_mode'], 'hr_employees_center_attendance_mode_index');
        });
    }

    public function down(): void
    {
        DB::table('hr_employees')->where('attendance_mode', 'electronic')->update(['attendance_mode' => 'gps']);

        Schema::table('hr_employees', function (Blueprint $table) {
            $table->dropIndex('hr_employees_center_attendance_mode_index');
            $table->dropConstrainedForeignId('attendance_policy_id');
            $table->dropColumn(['employment_start_date', 'contract_start_date', 'gps_policy', 'grace_minutes']);
        });
    }
};
