<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Attendance processing and its payroll hand-over:
 *  - hr_attendance_days: the processed result per employee and date (schedule, attendance record, approved leave,
 *    holiday, policy) with HR corrections kept apart from the computed values, and the period that locked it;
 *  - hr_attendance_day_corrections: every HR correction with previous / new values and the reason;
 *  - hr_attendance_periods: payroll attendance periods (draft, processed, approved); approving locks their days;
 *  - hr_attendance_period_summaries: per employee totals of a period (absence, lateness, exceptions, leave).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_attendance_periods')) {
            Schema::create('hr_attendance_periods', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                // null = every branch of the center
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->string('name', 100);
                $table->date('period_start');
                $table->date('period_end');
                // draft | processed | approved
                $table->string('status', 20)->default('draft');
                $table->unsignedInteger('employees_count')->default(0);
                $table->json('totals')->nullable();
                $table->text('notes')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('approved_at')->nullable();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('reopened_at')->nullable();
                $table->foreignId('reopened_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('reopen_reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['center_id', 'period_start', 'period_end'], 'hr_att_periods_center_dates_idx');
            });
        }

        if (! Schema::hasTable('hr_attendance_days')) {
            Schema::create('hr_attendance_days', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->date('attendance_date');
                // working | rest | off | unscheduled | not_employed (from the shift schedule)
                $table->string('day_type', 20);
                // Final status (computed, or as corrected by HR) and the computed one.
                $table->string('status', 30);
                $table->string('computed_status', 30);
                $table->string('status_reason', 40)->nullable();

                $table->foreignId('shift_id')->nullable()->constrained('hr_shifts')->nullOnDelete();
                $table->dateTime('scheduled_start_at')->nullable();
                $table->dateTime('scheduled_end_at')->nullable();
                $table->unsignedInteger('expected_minutes')->default(0);
                $table->unsignedSmallInteger('late_grace_minutes')->default(0);

                $table->foreignId('attendance_record_id')->nullable()->constrained('hr_attendance_records')->nullOnDelete();
                $table->string('attendance_method', 20)->nullable();
                $table->dateTime('check_in_at')->nullable();
                $table->dateTime('check_out_at')->nullable();
                $table->unsignedInteger('worked_minutes')->nullable();
                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedInteger('early_departure_minutes')->default(0);
                $table->unsignedInteger('computed_late_minutes')->default(0);
                $table->unsignedInteger('computed_early_departure_minutes')->default(0);
                $table->decimal('absence_days', 3, 2)->default(0);

                $table->foreignId('leave_request_id')->nullable()->constrained('hr_leave_requests')->nullOnDelete();
                $table->foreignId('leave_type_id')->nullable()->constrained('hr_leave_types')->nullOnDelete();
                $table->boolean('leave_is_paid')->nullable();
                $table->foreignId('holiday_id')->nullable()->constrained('hr_holidays')->nullOnDelete();

                // Exceptions that need an HR decision (missing check-in / out, failed GPS attempts, ...).
                $table->json('exception_reasons')->nullable();
                // Informational flags (attendance record review reasons, policy hints); never block payroll.
                $table->json('review_flags')->nullable();
                // null (no exception) | pending | resolved
                $table->string('review_status', 20)->nullable();

                // HR correction: present | absent | excused, and / or corrected minutes.
                $table->string('override_status', 30)->nullable();
                $table->unsignedInteger('override_late_minutes')->nullable();
                $table->unsignedInteger('override_early_departure_minutes')->nullable();
                $table->text('override_reason')->nullable();
                $table->foreignId('overridden_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('overridden_at')->nullable();

                $table->foreignId('attendance_period_id')->nullable()->constrained('hr_attendance_periods')->nullOnDelete();
                $table->dateTime('locked_at')->nullable();
                $table->dateTime('processed_at')->nullable();
                $table->timestamps();

                $table->unique(['employee_id', 'attendance_date'], 'hr_att_days_employee_date_unique');
                $table->index(['center_id', 'attendance_date'], 'hr_att_days_center_date_idx');
                $table->index(['center_id', 'status'], 'hr_att_days_center_status_idx');
                $table->index(['attendance_period_id'], 'hr_att_days_period_idx');
            });
        }

        if (! Schema::hasTable('hr_attendance_day_corrections')) {
            Schema::create('hr_attendance_day_corrections', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('attendance_day_id')->constrained('hr_attendance_days')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                // corrected | reset
                $table->string('action', 20);
                $table->json('old_values')->nullable();
                $table->json('new_values')->nullable();
                $table->text('reason');
                $table->foreignId('corrected_by')->nullable()->constrained('users')->nullOnDelete();
                $table->boolean('via_impersonation')->default(false);
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->nullable();

                $table->index(['attendance_day_id', 'created_at'], 'hr_att_day_corr_day_created_idx');
            });
        }

        if (! Schema::hasTable('hr_attendance_period_summaries')) {
            Schema::create('hr_attendance_period_summaries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('attendance_period_id')->constrained('hr_attendance_periods')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->unsignedSmallInteger('scheduled_days')->default(0);
                $table->unsignedSmallInteger('present_days')->default(0);
                $table->decimal('absence_days', 5, 2)->default(0);
                $table->unsignedSmallInteger('late_days')->default(0);
                $table->unsignedInteger('late_minutes')->default(0);
                $table->unsignedSmallInteger('early_departure_days')->default(0);
                $table->unsignedInteger('early_departure_minutes')->default(0);
                $table->unsignedInteger('worked_minutes')->default(0);
                $table->unsignedInteger('expected_minutes')->default(0);
                $table->decimal('leave_days', 6, 2)->default(0);
                $table->decimal('paid_leave_days', 6, 2)->default(0);
                $table->decimal('unpaid_leave_days', 6, 2)->default(0);
                $table->unsignedSmallInteger('holiday_days')->default(0);
                $table->unsignedSmallInteger('rest_days')->default(0);
                $table->unsignedSmallInteger('exempt_days')->default(0);
                $table->unsignedSmallInteger('excused_days')->default(0);
                $table->unsignedSmallInteger('exceptions_count')->default(0);
                $table->unsignedSmallInteger('unresolved_exceptions')->default(0);
                $table->json('leave_breakdown')->nullable();
                $table->json('leave_requests')->nullable();
                $table->json('exception_details')->nullable();
                $table->timestamps();

                $table->unique(['attendance_period_id', 'employee_id'], 'hr_att_period_sum_period_employee_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_period_summaries');
        Schema::dropIfExists('hr_attendance_day_corrections');
        Schema::dropIfExists('hr_attendance_days');
        Schema::dropIfExists('hr_attendance_periods');
    }
};
