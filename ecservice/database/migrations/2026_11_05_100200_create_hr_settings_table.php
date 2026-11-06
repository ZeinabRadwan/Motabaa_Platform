<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-center overrides of HR settings. Keys, types and defaults live in App\Support\Hr\HrSettingsRegistry;
 * a missing row means "use the default".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('hr_settings')) {
            return;
        }

        Schema::create('hr_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('center_id')->constrained('centers')->cascadeOnDelete();
            $table->string('key', 100);
            $table->text('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['center_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hr_settings');
    }
};
