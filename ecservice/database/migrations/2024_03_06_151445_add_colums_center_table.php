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
        Schema::table('centers', function (Blueprint $table) {
            $table->string('commission')->nullable()->after('phone');
            $table->string('cr_number')->nullable()->after('commission');
            $table->string('vat_number')->nullable()->after('cr_number');
            $table->string('email')->nullable()->after('vat_number');
            $table->string('url')->nullable()->after('email');
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
