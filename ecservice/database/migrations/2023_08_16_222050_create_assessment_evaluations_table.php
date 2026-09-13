<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('assessment_evaluations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->nullable()->constrained('scases')->nullOnDelete();
            $table->foreignId('assesment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('value')->nullable();
            $table->foreignId('value_id')->nullable()->constrained('evaluation_methods_values')->nullOnDelete();
            $table->string('ability')->index()->nullable();
            $table->timestamps();

            $table->unique(['case_id','assesment_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessment_evaluations');
    }
};
