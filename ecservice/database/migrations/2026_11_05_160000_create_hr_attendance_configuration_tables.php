<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance method configuration and attendance policies:
 *  - attendance policy rules (check-in / check-out windows, late / early departure handling, missing check-out,
 *    duplicate punches, GPS requirement) become effective-dated hr_attendance_policy_versions; hr_attendance_policies
 *    keeps a cache of the version in force today;
 *  - an employee's attendance method, GPS verification, policy and grace period become effective-dated
 *    hr_employee_attendance_configs rows; hr_employees keeps a cache of the row in force today.
 * Existing data is backfilled with one open row each, so history starts from the current configuration.
 */
return new class extends Migration
{
    private const NEW_POLICY_COLUMNS = [
        'check_in_before_minutes', 'check_in_after_minutes', 'check_out_before_minutes', 'check_out_after_minutes',
        'late_counting', 'late_action', 'early_leave_counting', 'early_leave_action', 'missing_checkout_action',
        'duplicate_punch_action', 'duplicate_window_minutes', 'gps_requirement', 'current_version_id',
    ];

    public function up(): void
    {
        Schema::table('hr_attendance_policies', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_attendance_policies', 'check_in_before_minutes')) {
                $this->ruleColumns($table, false);
                // Version copied into the rule columns (no FK: the versions table references this one).
                $table->unsignedBigInteger('current_version_id')->nullable();
            }
        });

        if (! Schema::hasTable('hr_attendance_policy_versions')) {
            Schema::create('hr_attendance_policy_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('attendance_policy_id')->constrained('hr_attendance_policies')->cascadeOnDelete();
                $this->ruleColumns($table, true);
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['attendance_policy_id', 'effective_from'], 'hr_att_policy_versions_policy_from_unique');
                $table->index(['center_id', 'effective_from']);
            });

            $this->backfillPolicies();
        }

        if (! Schema::hasTable('hr_employee_attendance_configs')) {
            Schema::create('hr_employee_attendance_configs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->string('attendance_mode', 20);
                // required | flexible | none, or "policy" (follow the attendance policy). Always "none" unless electronic.
                $table->string('gps_policy', 20)->default('none');
                $table->foreignId('attendance_policy_id')->nullable()->constrained('hr_attendance_policies')->nullOnDelete();
                $table->unsignedSmallInteger('grace_minutes')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'effective_from'], 'hr_emp_att_configs_employee_from_unique');
                $table->index(['center_id', 'effective_from']);
                $table->index('attendance_policy_id');
            });

            $this->backfillEmployeeConfigs();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_attendance_configs');
        Schema::dropIfExists('hr_attendance_policy_versions');

        Schema::table('hr_attendance_policies', function (Blueprint $table) {
            foreach (self::NEW_POLICY_COLUMNS as $column) {
                if (Schema::hasColumn('hr_attendance_policies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /** Rule columns; the version table also carries the rules that already existed on hr_attendance_policies. */
    private function ruleColumns(Blueprint $table, bool $withExisting): void
    {
        if ($withExisting) {
            $table->unsignedSmallInteger('late_grace_minutes')->default(15);
            $table->unsignedSmallInteger('early_leave_grace_minutes')->default(15);
            $table->unsignedSmallInteger('absence_after_minutes')->nullable();
            $table->boolean('allow_manual_attendance')->default(true);
        }
        // Check-in accepted from N minutes before the shift start until N minutes after it (null = until the shift ends).
        $table->unsignedSmallInteger('check_in_before_minutes')->default(60);
        $table->unsignedSmallInteger('check_in_after_minutes')->nullable();
        // Check-out accepted from N minutes before the shift end (null = any time after check-in) until N minutes after it.
        $table->unsignedSmallInteger('check_out_before_minutes')->nullable();
        $table->unsignedSmallInteger('check_out_after_minutes')->default(240);
        $table->string('late_counting', 20)->default('full');
        $table->string('late_action', 30)->default('record_only');
        $table->string('early_leave_counting', 20)->default('full');
        $table->string('early_leave_action', 30)->default('record_only');
        $table->string('missing_checkout_action', 30)->default('flag_for_review');
        $table->string('duplicate_punch_action', 30)->default('first_in_last_out');
        $table->unsignedSmallInteger('duplicate_window_minutes')->default(5);
        $table->string('gps_requirement', 20)->default('required');
    }

    /**
     * One open version per policy, starting on its creation date or the first employment start of an employee
     * using it, whichever is earlier. Lateness counting follows the center's attendance.count_after_grace setting.
     */
    private function backfillPolicies(): void
    {
        $now = now();

        DB::table('hr_attendance_policies')->orderBy('id')->get()->each(function ($policy) use ($now) {
            $countAfterGrace = DB::table('hr_settings')
                ->where('center_id', $policy->center_id)
                ->where('key', 'attendance.count_after_grace')
                ->value('value');
            $counting = in_array(strtolower((string) $countAfterGrace), ['1', 'true'], true) ? 'excess_only' : 'full';

            $created = $policy->created_at ? substr((string) $policy->created_at, 0, 10) : $now->toDateString();
            $firstUse = DB::table('hr_employees')->where('attendance_policy_id', $policy->id)->min('employment_start_date');
            $from = $firstUse !== null && substr((string) $firstUse, 0, 10) < $created ? substr((string) $firstUse, 0, 10) : $created;

            $rules = [
                'late_grace_minutes' => (int) $policy->late_grace_minutes,
                'early_leave_grace_minutes' => (int) $policy->early_leave_grace_minutes,
                'absence_after_minutes' => $policy->absence_after_minutes,
                'allow_manual_attendance' => (bool) $policy->allow_manual_attendance,
                'late_counting' => $counting,
                'early_leave_counting' => $counting,
            ];

            $versionId = DB::table('hr_attendance_policy_versions')->insertGetId($rules + [
                'center_id' => $policy->center_id,
                'attendance_policy_id' => $policy->id,
                'effective_from' => $from,
                'created_by' => $policy->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('hr_attendance_policies')->where('id', $policy->id)->update([
                'late_counting' => $counting,
                'early_leave_counting' => $counting,
                'current_version_id' => $versionId,
            ]);
        });
    }

    /** One open configuration row per employee from the employment start, with the current cached values. */
    private function backfillEmployeeConfigs(): void
    {
        $now = now();

        DB::table('hr_employees')->orderBy('id')->get()->each(function ($employee) use ($now) {
            $from = $employee->employment_start_date
                ? substr((string) $employee->employment_start_date, 0, 10)
                : ($employee->created_at ? substr((string) $employee->created_at, 0, 10) : $now->toDateString());

            DB::table('hr_employee_attendance_configs')->insert([
                'center_id' => $employee->center_id,
                'employee_id' => $employee->id,
                'attendance_mode' => $employee->attendance_mode ?: 'manual',
                'gps_policy' => $employee->attendance_mode === 'electronic' ? ($employee->gps_policy ?: 'required') : 'none',
                'attendance_policy_id' => $employee->attendance_policy_id,
                'grace_minutes' => $employee->grace_minutes,
                'effective_from' => $from,
                'created_by' => $employee->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });
    }
};
