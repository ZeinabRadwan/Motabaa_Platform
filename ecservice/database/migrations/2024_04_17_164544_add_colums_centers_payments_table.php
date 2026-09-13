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
        Schema::table('centers_payments', function (Blueprint $table) {
            $table->tinyInteger('payment_type')->after('package_id');
            $table->integer('payment_duration')->after('payment_type');
            $table->date('expiry_date')->after('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
