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
        Schema::create('goals_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')->nullable()->constrained('goals')->nullOnDelete();
            $table->string('procedural_objectives');
            $table->unsignedSmallInteger('attempts')->nullable();
            $table->unsignedSmallInteger('successful_attempts')->nullable();
            $table->unsignedTinyInteger('performance_evaluation')->nullable();
            $table->string('reinforcement')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals_steps');
    }
};
