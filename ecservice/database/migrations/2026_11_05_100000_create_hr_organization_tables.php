<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HR organization structure, scoped per center: branches, their GPS work locations, and departments.
 * Manager columns pointing at hr_employees are added by the hr_employees migration (circular reference).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('hr_branches')) {
            Schema::create('hr_branches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->string('code', 30);
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                $table->string('city', 100)->nullable();
                $table->string('address', 255)->nullable();
                $table->string('phone', 30)->nullable();
                $table->string('cr_number', 30)->nullable();
                $table->string('mol_establishment_number', 30)->nullable();
                $table->string('gosi_registration_number', 30)->nullable();
                $table->string('timezone', 64)->default('Asia/Riyadh');
                $table->boolean('is_headquarters')->default(false);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['center_id', 'code']);
                $table->index(['center_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('hr_work_locations')) {
            Schema::create('hr_work_locations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('branch_id')->constrained('hr_branches')->cascadeOnDelete();
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                $table->string('address', 255)->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->unsignedInteger('radius_meters')->default(100);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['center_id', 'branch_id']);
            });
        }

        if (! Schema::hasTable('hr_departments')) {
            Schema::create('hr_departments', function (Blueprint $table) {
                $table->id();
                $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('hr_departments')->nullOnDelete();
                // Matches the legacy users.department keys (User::departments()) when seeded from them.
                $table->string('code', 50);
                $table->string('name_ar', 150);
                $table->string('name_en', 150)->nullable();
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['center_id', 'code']);
                $table->index(['center_id', 'is_active']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_departments');
        Schema::dropIfExists('hr_work_locations');
        Schema::dropIfExists('hr_branches');
    }
};
