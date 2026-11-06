<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Generic multi-step approvals shared by every HR request type (leave, manual attendance, additional
 * working days, payroll runs). Workflows are templates; each request snapshots its steps so later
 * workflow edits never rewrite the history of requests already submitted.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_approval_workflows')) {
            Schema::create('hr_approval_workflows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->string('request_type', 50);
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->unique(['center_id', 'request_type']);
            });
        }

        if (! Schema::hasTable('hr_approval_workflow_steps')) {
            Schema::create('hr_approval_workflow_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('workflow_id')->constrained('hr_approval_workflows')->cascadeOnDelete();
                $table->unsignedTinyInteger('step_order');
                $table->string('approver_type', 30);
                $table->string('approver_value', 100)->nullable();
                $table->timestamps();

                $table->unique(['workflow_id', 'step_order']);
            });
        }

        if (! Schema::hasTable('hr_approval_requests')) {
            Schema::create('hr_approval_requests', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->foreignId('workflow_id')->nullable()->constrained('hr_approval_workflows')->nullOnDelete();
                $table->string('request_type', 50);
                $table->morphs('approvable');
                $table->foreignId('employee_id')->nullable()->constrained('hr_employees')->nullOnDelete();
                $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('status', 20)->default('pending');
                $table->unsignedTinyInteger('current_step')->default(1);
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();

                $table->index(['center_id', 'status']);
                $table->index(['center_id', 'request_type']);
            });
        }

        if (! Schema::hasTable('hr_approval_request_steps')) {
            Schema::create('hr_approval_request_steps', function (Blueprint $table) {
                $table->id();
                $table->foreignId('approval_request_id')->constrained('hr_approval_requests')->cascadeOnDelete();
                $table->unsignedTinyInteger('step_order');
                $table->string('approver_type', 30);
                $table->string('approver_value', 100)->nullable();
                $table->string('status', 20)->default('pending');
                $table->foreignId('acted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('acted_at')->nullable();
                $table->text('comment')->nullable();
                $table->timestamps();

                $table->unique(['approval_request_id', 'step_order']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_approval_request_steps');
        Schema::dropIfExists('hr_approval_requests');
        Schema::dropIfExists('hr_approval_workflow_steps');
        Schema::dropIfExists('hr_approval_workflows');
    }
};
