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
        Schema::create('scases_fees_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_fee_id')->nullable()->constrained('scases_fees')->nullOnDelete();
            $table->string('service');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scases_fees_services');
    }
};
