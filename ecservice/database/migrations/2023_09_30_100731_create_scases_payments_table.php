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
        Schema::create('scases_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scase_id')->nullable()->constrained('scases')->nullOnDelete();
            $table->foreignId('term_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->foreignId('case_fee_id')->nullable()->constrained('scases_fees')->nullOnDelete();
            $table->tinyInteger('batch');
            $table->float('amount', 8, 2);
            $table->tinyInteger('status');
            $table->date('payment_date')->nullable();
            $table->date('due_date');
            $table->string('paid_by')->nullable();
            $table->tinyInteger('method')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('created_by');
            $table->timestamps();
            
            $table->unique(['scase_id', 'term_id', 'batch']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scases_fees');
    }
};
