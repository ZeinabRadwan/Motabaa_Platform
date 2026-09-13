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
        Schema::create('scase_disability', function (Blueprint $table) {
            $table->foreignId('scase_id')->constrained()->onDelete('cascade');
            $table->foreignId('disability_id')->constrained()->onDelete('cascade');
            $table->primary(['scase_id', 'disability_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scase_disability');
    }
};
