<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Branch details (country, email) and effective-dated branch / work location assignments. The branch_id and
 * work_location_id columns of hr_employees stay as a cache of the assignment in force today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hr_branches', function (Blueprint $table) {
            if (! Schema::hasColumn('hr_branches', 'country')) {
                $table->char('country', 2)->default('SA')->after('city');
            }
            if (! Schema::hasColumn('hr_branches', 'email')) {
                $table->string('email', 191)->nullable()->after('phone');
            }
        });

        if (! Schema::hasTable('hr_employee_work_assignments')) {
            Schema::create('hr_employee_work_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('hr_branches');
                $table->foreignId('work_location_id')->nullable()->constrained('hr_work_locations');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'effective_from']);
                $table->index(['center_id', 'branch_id', 'effective_from'], 'hr_work_assignments_branch_index');
                $table->index(['center_id', 'work_location_id', 'effective_from'], 'hr_work_assignments_location_index');
            });

            $this->backfill();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_work_assignments');

        Schema::table('hr_branches', function (Blueprint $table) {
            foreach (['country', 'email'] as $column) {
                if (Schema::hasColumn('hr_branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    /** One open assignment per employee with a branch, starting on the employment start date. */
    private function backfill(): void
    {
        $now = now();

        DB::table('hr_employees')
            ->whereNotNull('branch_id')
            ->orderBy('id')
            ->select(['id', 'center_id', 'branch_id', 'work_location_id', 'employment_start_date', 'created_at'])
            ->chunk(500, function ($employees) use ($now) {
                DB::table('hr_employee_work_assignments')->insert($employees->map(fn ($employee) => [
                    'center_id' => $employee->center_id,
                    'employee_id' => $employee->id,
                    'branch_id' => $employee->branch_id,
                    'work_location_id' => $employee->work_location_id,
                    'effective_from' => $employee->employment_start_date
                        ?? ($employee->created_at ? substr((string) $employee->created_at, 0, 10) : $now->toDateString()),
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });
    }
};
