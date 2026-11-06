<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shift management: shift types, rest days, grace periods and rotation cycles on hr_shifts; effective-dated
 * definition versions (hr_shifts keeps a cache of the version in force today); per-employee schedule overrides
 * on assignments; a branch default shift for new hires; and dated schedule exceptions.
 * Rotating shifts keep placeholder times in the NOT NULL start/end columns; their days come from the cycle.
 */
return new class extends Migration
{
    private const SHIFT_COLUMNS = [
        'shift_type', 'color', 'break_start_time', 'expected_minutes', 'grace_minutes',
        'early_leave_grace_minutes', 'rest_days', 'rotation_pattern', 'current_version_id',
    ];

    public function up(): void
    {
        Schema::table('hr_shifts', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_shifts', 'shift_type')) {
                $table->string('shift_type', 20)->default('custom')->after('code');
                $table->string('color', 7)->nullable()->after('name_en');
                $table->time('break_start_time')->nullable()->after('end_time');
                $table->unsignedSmallInteger('expected_minutes')->nullable()->after('break_minutes');
                $table->unsignedSmallInteger('grace_minutes')->nullable()->after('expected_minutes');
                $table->unsignedSmallInteger('early_leave_grace_minutes')->nullable()->after('grace_minutes');
                $table->string('rest_days', 20)->nullable()->after('working_days');
                $table->json('rotation_pattern')->nullable()->after('rest_days');
                // Version copied into the timing columns (no FK: the versions table references hr_shifts).
                $table->unsignedBigInteger('current_version_id')->nullable()->after('rotation_pattern');
            }
        });

        if (! Schema::hasTable('hr_shift_versions')) {
            Schema::create('hr_shift_versions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained('hr_shifts')->cascadeOnDelete();
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->time('break_start_time')->nullable();
                $table->unsignedSmallInteger('break_minutes')->default(0);
                $table->unsignedSmallInteger('expected_minutes')->nullable();
                $table->unsignedSmallInteger('grace_minutes')->nullable();
                $table->unsignedSmallInteger('early_leave_grace_minutes')->nullable();
                $table->string('working_days', 20)->nullable();
                $table->string('rest_days', 20)->nullable();
                $table->json('rotation_pattern')->nullable();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['shift_id', 'effective_from']);
                $table->index(['center_id', 'effective_from']);
            });

            $this->backfillShifts();
        }

        Schema::table('hr_employee_shift_assignments', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_employee_shift_assignments', 'rotation_offset')) {
                $table->unsignedSmallInteger('rotation_offset')->nullable()->after('shift_id');
                $table->string('working_days', 20)->nullable()->after('rotation_offset');
                $table->string('rest_days', 20)->nullable()->after('working_days');
            }
        });

        Schema::table('hr_branches', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_branches', 'default_shift_id')) {
                $table->foreignId('default_shift_id')->nullable()->after('manager_employee_id')
                    ->constrained('hr_shifts')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('hr_shift_exceptions')) {
            Schema::create('hr_shift_exceptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                // Scope: an employee, a branch, or (both null) the whole center.
                $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->cascadeOnDelete();
                $table->date('date_from');
                $table->date('date_to');
                $table->string('exception_type', 20);
                $table->foreignId('shift_id')->nullable()->constrained('hr_shifts');
                $table->time('start_time')->nullable();
                $table->time('end_time')->nullable();
                $table->unsignedSmallInteger('break_minutes')->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['center_id', 'date_from', 'date_to']);
                $table->index(['employee_id', 'date_from']);
                $table->index(['branch_id', 'date_from']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_shift_exceptions');

        if (Schema::hasColumn('hr_branches', 'default_shift_id')) {
            Schema::table('hr_branches', fn (Blueprint $table) => $table->dropConstrainedForeignId('default_shift_id'));
        }

        Schema::table('hr_employee_shift_assignments', function (Blueprint $table) {
            foreach (['rotation_offset', 'working_days', 'rest_days'] as $column) {
                if (Schema::hasColumn('hr_employee_shift_assignments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists('hr_shift_versions');

        Schema::table('hr_shifts', function (Blueprint $table) {
            foreach (self::SHIFT_COLUMNS as $column) {
                if (Schema::hasColumn('hr_shifts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /**
     * Existing shifts get a type from their start time, rest days = the days they do not work, and one open
     * version starting on their first use (or creation) date.
     */
    private function backfillShifts(): void
    {
        $now = now();

        DB::table('hr_shifts')->orderBy('id')->get()->each(function ($shift) use ($now) {
            $working = $shift->working_days === null || $shift->working_days === ''
                ? []
                : array_map('intval', explode(',', $shift->working_days));
            $rest = array_values(array_diff(range(0, 6), $working));

            DB::table('hr_shifts')->where('id', $shift->id)->update([
                'shift_type' => $this->typeFor((string) $shift->start_time, (string) $shift->end_time),
                'rest_days' => $rest ? implode(',', $rest) : null,
            ]);

            $firstUse = DB::table('hr_employee_shift_assignments')->where('shift_id', $shift->id)->min('effective_from');
            $created = $shift->created_at ? substr((string) $shift->created_at, 0, 10) : $now->toDateString();
            $from = $firstUse !== null && substr((string) $firstUse, 0, 10) < $created ? substr((string) $firstUse, 0, 10) : $created;

            $versionId = DB::table('hr_shift_versions')->insertGetId([
                'center_id' => $shift->center_id,
                'shift_id' => $shift->id,
                'start_time' => $shift->start_time,
                'end_time' => $shift->end_time,
                'break_minutes' => (int) $shift->break_minutes,
                'working_days' => $shift->working_days,
                'rest_days' => $rest ? implode(',', $rest) : null,
                'effective_from' => $from,
                'created_by' => $shift->created_by,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('hr_shifts')->where('id', $shift->id)->update(['current_version_id' => $versionId]);
        });
    }

    private function typeFor(string $start, string $end): string
    {
        $startHour = (int) substr($start, 0, 2);
        if (substr($end, 0, 5) < substr($start, 0, 5) || $startHour >= 20 || $startHour < 4) {
            return 'night';
        }

        return $startHour < 12 ? 'morning' : 'evening';
    }
};
