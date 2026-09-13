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
        Schema::create('scases', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('beneficiary_number')->nullable();
            $table->tinyInteger('period');
            $table->string('id_or_residence_number');
            $table->string('nationality', 3);
            $table->date('birthdate');
            $table->string('emergency_contact');
            $table->string('phone')->nullable();
            $table->string('blood_type',4)->nullable();
            $table->string('address_city',100)->nullable();
            $table->string('address_area',150)->nullable();
            $table->string('address_street',150)->nullable();
            $table->string('address_building',50)->nullable();
            $table->string('address_number',50)->nullable();
            $table->string('address_unit',100)->nullable();
            $table->string('address_zipcode',50)->nullable();
            $table->json('general_questions');
            $table->json('case_study');
            $table->json('psychological_study');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scases');
    }
};
