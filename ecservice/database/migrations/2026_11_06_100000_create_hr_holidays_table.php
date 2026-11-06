<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Official and company holidays per center, for all branches (branch_id null) or one branch. Attendance processing
 * never counts a holiday that falls on a working day as absence, and working-day leave does not consume it.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_holidays')) {
            return;
        }

        Schema::create('hr_holidays', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('hr_branches')->nullOnDelete();
            $table->string('name_ar', 150);
            $table->string('name_en', 150)->nullable();
            $table->date('date_from');
            $table->date('date_to');
            // official | company
            $table->string('holiday_type', 20)->default('official');
            $table->boolean('is_paid')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['center_id', 'date_from', 'date_to'], 'hr_holidays_center_dates_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_holidays');
    }
};
