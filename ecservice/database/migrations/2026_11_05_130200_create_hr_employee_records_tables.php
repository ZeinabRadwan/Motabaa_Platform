<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Employee history:
 *  - hr_employment_periods: start/end of each employment (payroll eligibility, re-hiring);
 *  - hr_employee_salaries, hr_employee_insurances, hr_employee_shift_assignments: effective-dated rows,
 *    never edited; a new row closes the previous one so any past date resolves to the value in force;
 *  - hr_employee_documents: files on a private disk, soft-deleted;
 *  - hr_employee_changes: append-only timeline of every employment / attendance / financial change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_employment_periods')) {
            Schema::create('hr_employment_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->date('start_date');
                $table->date('end_date')->nullable();
                $table->string('end_reason', 50)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['employee_id', 'start_date']);
                $table->index(['center_id', 'start_date', 'end_date']);
            });

            $this->backfillEmploymentPeriods();
        }

        if (! Schema::hasTable('hr_employee_salaries')) {
            Schema::create('hr_employee_salaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->decimal('basic_salary', 12, 2);
                $table->decimal('housing_allowance', 12, 2)->default(0);
                $table->decimal('transportation_allowance', 12, 2)->default(0);
                $table->decimal('other_allowances', 12, 2)->default(0);
                $table->string('other_allowances_note', 255)->nullable();
                $table->char('currency', 3)->default('SAR');
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'effective_from']);
            });
        }

        if (! Schema::hasTable('hr_employee_insurances')) {
            Schema::create('hr_employee_insurances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('category', 20);
                $table->decimal('employee_rate', 5, 2)->default(0);
                $table->decimal('employer_rate', 5, 2)->default(0);
                $table->date('contribution_start_date')->nullable();
                $table->string('subscription_number', 30)->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'effective_from']);
            });
        }

        if (! Schema::hasTable('hr_employee_shift_assignments')) {
            Schema::create('hr_employee_shift_assignments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('shift_id')->constrained('hr_shifts');
                $table->date('effective_from');
                $table->date('effective_to')->nullable();
                $table->string('reason', 500)->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['employee_id', 'effective_from']);
                $table->index(['shift_id', 'effective_to']);
            });
        }

        if (! Schema::hasTable('hr_employee_documents')) {
            Schema::create('hr_employee_documents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->string('category', 20);
                $table->string('document_type', 40);
                $table->string('title', 191);
                $table->string('reference_number', 100)->nullable();
                $table->date('issue_date')->nullable();
                $table->date('expiry_date')->nullable();
                $table->string('disk', 30);
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 150)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->text('notes')->nullable();
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['employee_id', 'category']);
                $table->index(['center_id', 'expiry_date']);
            });
        }

        if (! Schema::hasTable('hr_employee_changes')) {
            Schema::create('hr_employee_changes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->string('change_type', 40);
                $table->string('action', 20)->default('set');
                $table->text('old_value')->nullable();
                $table->text('new_value')->nullable();
                $table->text('old_label')->nullable();
                $table->text('new_label')->nullable();
                $table->date('effective_date');
                $table->string('reason', 500)->nullable();
                $table->string('source_type', 100)->nullable();
                $table->unsignedBigInteger('source_id')->nullable();
                $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->index(['employee_id', 'effective_date']);
                $table->index(['employee_id', 'change_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_employee_changes');
        Schema::dropIfExists('hr_employee_documents');
        Schema::dropIfExists('hr_employee_shift_assignments');
        Schema::dropIfExists('hr_employee_insurances');
        Schema::dropIfExists('hr_employee_salaries');
        Schema::dropIfExists('hr_employment_periods');
    }

    /**
     * Profiles created before this migration get one period: start = employment start, hire date or creation
     * date; terminated profiles are closed at their termination date.
     */
    private function backfillEmploymentPeriods(): void
    {
        $rows = DB::table('hr_employees')
            ->leftJoin('users', 'users.id', '=', 'hr_employees.user_id')
            ->whereNull('hr_employees.deleted_at')
            ->get([
                'hr_employees.id', 'hr_employees.center_id', 'hr_employees.employment_start_date',
                'hr_employees.employment_status', 'hr_employees.terminated_at', 'hr_employees.termination_reason',
                'hr_employees.created_at', 'users.hire_date',
            ]);

        foreach ($rows as $row) {
            $start = $row->employment_start_date ?? $row->hire_date ?? substr((string) $row->created_at, 0, 10);
            $terminated = $row->employment_status === 'terminated' && $row->terminated_at;

            DB::table('hr_employment_periods')->insert([
                'center_id' => $row->center_id,
                'employee_id' => $row->id,
                'start_date' => $start ?: now()->toDateString(),
                'end_date' => $terminated ? $row->terminated_at : null,
                'end_reason' => $terminated ? $row->termination_reason : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
