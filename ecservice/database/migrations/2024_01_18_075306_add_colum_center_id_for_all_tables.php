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
        Schema::table('assessments', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
        });
        Schema::table('disabilities', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
            $table->tinyInteger('center_id')->nullable()->after('id');
        });
        Schema::table('evaluation_methods', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
            $table->tinyInteger('center_id')->nullable()->after('id');
        });
        Schema::table('logs', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
            $table->tinyInteger('center_id')->after('id');
        });
        Schema::table('meeting_rooms', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
        });
        Schema::table('operational_plans', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
        });
        Schema::table('questionnaires_tasks', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
        });
        Schema::table('scases', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
        });
        Schema::table('services', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
        });
        Schema::table('terms', function (Blueprint $table) {
            $table->foreignId('center_id')->after('id')->nullable()->constrained('centers')->nullOnDelete();
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
