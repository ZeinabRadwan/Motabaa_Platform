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
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('scases')->nullOnDelete();
            $table->text('title');
            $table->text('title_local')->nullable();
            $table->foreignId('assessment_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('category')->nullable()->index();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('value');
            $table->timestamps();

            $table->unique(['term_id','case_id','assessment_id']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goals');
    }
};
