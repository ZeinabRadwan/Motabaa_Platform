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
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('type');
            $table->foreignId('parent_id')->nullable()->constrained('assessments')->nullOnDelete();
            $table->string('title');
            $table->string('title_local')->nullable();
            $table->string('category')->nullable();
            $table->foreignId('evaluation_method_id')->nullable()->constrained('evaluation_methods')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};
