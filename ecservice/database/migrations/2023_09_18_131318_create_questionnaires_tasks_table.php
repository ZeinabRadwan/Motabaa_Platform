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
        Schema::create('questionnaires_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('questionnaire_id')->constrained()->on('questionnaires')->onDelete('cascade');
            $table->foreignId('term_id')->constrained()->on('terms')->onDelete('cascade');
            $table->date('starts_at');
            $table->date('ends_at');
            $table->tinyInteger('count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('questionnaires_tasks');
    }
};
