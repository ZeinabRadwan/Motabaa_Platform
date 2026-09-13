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
        Schema::create('evaluation_methods_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evaluation_method_id')->nullable()->constrained('evaluation_methods')->nullOnDelete();
            $table->string('title', 64);
            $table->string('value', 32);
            $table->string('ability', 32);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_methods_values');
    }
};
