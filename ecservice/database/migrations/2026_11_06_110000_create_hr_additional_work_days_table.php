<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Additional working days (whole or half days, never hours). One row per employee and work date while the record
 * is active (draft, pending, approved, paid): `duplicate_guard` holds the work date for active rows and is cleared
 * when a record is rejected or cancelled, so the unique index blocks duplicates while history is kept.
 * attendance_period_id is the payroll period of the work date; paid_period_id the period whose payroll paid it
 * (different when an approved day was carried forward). The pay figures are a snapshot taken at settlement.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_additional_work_days')) {
            return;
        }

        Schema::create('hr_additional_work_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $table->date('work_date');
            $table->decimal('days', 4, 2);
            // working | rest | off | unscheduled | holiday: the employee's schedule on the work date
            $table->string('day_type', 20)->nullable();
            $table->text('reason');
            // draft | pending | approved | rejected | cancelled | paid
            $table->string('status', 20)->default('draft');
            $table->date('duplicate_guard')->nullable();
            $table->foreignId('attendance_period_id')->nullable()->constrained('hr_attendance_periods')->nullOnDelete();
            $table->foreignId('paid_period_id')->nullable()->constrained('hr_attendance_periods')->nullOnDelete();
            $table->dateTime('paid_at')->nullable();
            $table->decimal('gross_salary', 12, 2)->nullable();
            $table->decimal('daily_rate', 12, 2)->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->foreignId('approval_request_id')->nullable()->constrained('hr_approval_requests')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->timestamps();

            $table->unique(['employee_id', 'duplicate_guard'], 'hr_additional_work_days_employee_guard_unique');
            $table->index(['center_id', 'status'], 'hr_additional_work_days_center_status_idx');
            $table->index(['center_id', 'work_date'], 'hr_additional_work_days_center_date_idx');
            $table->index(['paid_period_id'], 'hr_additional_work_days_paid_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_additional_work_days');
    }
};
