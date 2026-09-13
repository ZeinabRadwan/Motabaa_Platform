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
            $table->string('name', 50)->unique()->after('user_id');
            $table->string('email', 50)->unique()->after('name');
            $table->string('phone_number', 20)->unique()->after('email');
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
