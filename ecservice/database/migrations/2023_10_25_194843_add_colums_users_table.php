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
        Schema::table('users', function (Blueprint $table) {
            $table->tinyInteger('on_center_sponsorship')->nullable()->after('address_zipcode');
            $table->string('qualification')->nullable()->after('on_center_sponsorship');
            $table->string('specialization')->nullable()->after('qualification');
            $table->string('precise_specialization')->nullable()->after('specialization');
            $table->string('current_work')->nullable()->after('precise_specialization');
        
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
