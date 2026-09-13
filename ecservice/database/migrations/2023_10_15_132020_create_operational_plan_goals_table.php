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
        if ( !Schema::hasTable('operational_plans_goals') ) {
            Schema::create('operational_plans_goals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('operational_plan_id');
                $table->string('department');
                $table->text('goals');
                $table->text('activities_and_programs')->nullable();
                $table->text('targeted_by')->nullable();
                $table->text('implemented_by')->nullable();
                $table->text('goals_services')->nullable();
                $table->text('performance_indicator')->nullable();
                $table->text('reference_feed')->nullable();
                $table->tinyInteger('status')->default(0);
                $table->date('implemented_at')->nullable();
                $table->foreignId('action_by')->nullable()->constrained('users')->nullOnDelete();
                $table->date('action_at')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operational_plans_goals');
    }
};
