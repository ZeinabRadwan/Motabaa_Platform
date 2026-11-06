<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Leave management:
 *  - hr_leave_types: configurable per center (system types are created on first use and can be edited or
 *    deactivated, not deleted);
 *  - hr_leave_balances: one row per employee, leave type and year; hr_leave_balance_transactions is the append-only
 *    ledger of every change (entitlement, carry-over, adjustment, reservation, use, release, restore);
 *  - hr_leave_requests: the request and its outcome, approved through hr_approval_requests;
 *  - hr_leave_request_attachments: supporting documents on the private disk.
 * The legacy employee_leaves table is not touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_leave_types')) {
            Schema::create('hr_leave_types', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                // annual | sick | unpaid | maternity | marriage | bereavement | paternity | hajj | other
                $table->string('category', 20)->default('other');
                $table->boolean('is_system')->default(false);
                $table->boolean('is_paid')->default(true);
                // annual: leave.annual_days settings | fixed: days_per_year | none: no balance
                $table->string('balance_mode', 20)->default('none');
                $table->decimal('days_per_year', 6, 2)->nullable();
                $table->decimal('carry_over_max_days', 6, 2)->default(0);
                $table->unsignedSmallInteger('max_days_per_request')->nullable();
                // working_days: scheduled working days without holidays | calendar_days: every day of the range
                $table->string('day_counting', 20)->default('working_days');
                $table->boolean('requires_attachment')->default(false);
                $table->boolean('requires_hr_approval')->default(false);
                $table->boolean('allow_self_request')->default(true);
                $table->string('color', 9)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['center_id', 'code'], 'hr_leave_types_center_code_unique');
            });
        }

        if (! Schema::hasTable('hr_leave_balances')) {
            Schema::create('hr_leave_balances', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
                $table->unsignedSmallInteger('year');
                $table->decimal('entitled_days', 7, 2)->default(0);
                $table->decimal('carried_over_days', 7, 2)->default(0);
                $table->decimal('adjusted_days', 7, 2)->default(0);
                $table->decimal('used_days', 7, 2)->default(0);
                $table->decimal('pending_days', 7, 2)->default(0);
                $table->timestamps();

                $table->unique(['employee_id', 'leave_type_id', 'year'], 'hr_leave_balances_employee_type_year_unique');
            });
        }

        if (! Schema::hasTable('hr_leave_requests')) {
            Schema::create('hr_leave_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('hr_leave_types');
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('days', 6, 2);
                $table->unsignedSmallInteger('calendar_days');
                $table->unsignedSmallInteger('balance_year')->nullable();
                $table->text('reason');
                // pending | approved | rejected | cancelled
                $table->string('status', 20)->default('pending');
                // self | hr | recorded (entered by HR as already approved)
                $table->string('source', 20)->default('self');
                $table->foreignId('approval_request_id')->nullable()->constrained('hr_approval_requests')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('decided_at')->nullable();
                $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
                $table->dateTime('cancelled_at')->nullable();
                $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
                $table->text('cancel_reason')->nullable();
                $table->timestamps();

                $table->index(['center_id', 'status'], 'hr_leave_requests_center_status_idx');
                $table->index(['employee_id', 'start_date', 'end_date'], 'hr_leave_requests_employee_dates_idx');
            });
        }

        if (! Schema::hasTable('hr_leave_balance_transactions')) {
            Schema::create('hr_leave_balance_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('leave_balance_id')->constrained('hr_leave_balances')->cascadeOnDelete();
                $table->foreignId('employee_id')->constrained('hr_employees')->cascadeOnDelete();
                $table->foreignId('leave_type_id')->constrained('hr_leave_types')->cascadeOnDelete();
                $table->foreignId('leave_request_id')->nullable()->constrained('hr_leave_requests')->nullOnDelete();
                // entitlement | carry_over | adjustment | reserve | release | use | restore
                $table->string('kind', 20);
                $table->decimal('days', 7, 2);
                $table->text('reason')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('created_at')->nullable();

                $table->index(['leave_balance_id', 'created_at'], 'hr_leave_tx_balance_created_idx');
            });
        }

        if (! Schema::hasTable('hr_leave_request_attachments')) {
            Schema::create('hr_leave_request_attachments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('leave_request_id')->constrained('hr_leave_requests')->cascadeOnDelete();
                $table->string('disk', 30);
                $table->string('path');
                $table->string('original_name');
                $table->string('mime_type', 120)->nullable();
                $table->unsignedBigInteger('size')->default(0);
                $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_leave_request_attachments');
        Schema::dropIfExists('hr_leave_balance_transactions');
        Schema::dropIfExists('hr_leave_requests');
        Schema::dropIfExists('hr_leave_balances');
        Schema::dropIfExists('hr_leave_types');
    }
};
