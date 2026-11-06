<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shift and attendance policy definitions employees are assigned to. Definitions only: attendance
 * processing (late/absence calculation) is a later phase.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_shifts')) {
            Schema::create('hr_shifts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
                $table->string('code', 30);
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                $table->time('start_time');
                $table->time('end_time');
                $table->unsignedSmallInteger('break_minutes')->default(0);
                $table->string('working_days', 50)->nullable();
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['center_id', 'code']);
            });
        }

        if (! Schema::hasTable('hr_attendance_policies')) {
            Schema::create('hr_attendance_policies', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('late_grace_minutes')->default(15);
                $table->unsignedSmallInteger('early_leave_grace_minutes')->default(15);
                $table->unsignedSmallInteger('absence_after_minutes')->nullable();
                $table->boolean('allow_manual_attendance')->default(true);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['center_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_attendance_policies');
        Schema::dropIfExists('hr_shifts');
    }
};
