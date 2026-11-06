<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR profile of a staff user inside one center. Identity, contact and the legacy employment columns
 * (job_title, contract_type, hire_date, contract_end_date, id_expiry_date, nationality) stay on users;
 * this table only adds the organizational assignment the HR module needs.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_employees')) {
            Schema::create('hr_employees', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('employee_number', 30);
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->foreignId('department_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                $table->foreignId('work_location_id')->nullable()->constrained('hr_work_locations')->nullOnDelete();
                $table->foreignId('manager_id')->nullable()->constrained('hr_employees')->nullOnDelete();
                $table->string('employment_status', 20)->default('active');
                $table->string('attendance_mode', 20)->default('manual');
                $table->date('terminated_at')->nullable();
                $table->string('termination_reason', 50)->nullable();
                $table->text('notes')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['center_id', 'user_id']);
                $table->unique(['center_id', 'employee_number']);
                $table->index(['center_id', 'employment_status']);
                $table->index(['center_id', 'branch_id']);
                $table->index(['center_id', 'department_id']);
            });
        }

        if (! Schema::hasColumn('hr_branches', 'manager_employee_id')) {
            Schema::table('hr_branches', function (Blueprint $table) {
                $table->foreignId('manager_employee_id')->nullable()->after('is_active')->constrained('hr_employees')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('hr_departments', 'manager_employee_id')) {
            Schema::table('hr_departments', function (Blueprint $table) {
                $table->foreignId('manager_employee_id')->nullable()->after('is_active')->constrained('hr_employees')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['hr_departments', 'hr_branches'] as $tableName) {
            if (Schema::hasColumn($tableName, 'manager_employee_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('manager_employee_id');
                });
            }
        }

        Schema::dropIfExists('hr_employees');
    }
};
