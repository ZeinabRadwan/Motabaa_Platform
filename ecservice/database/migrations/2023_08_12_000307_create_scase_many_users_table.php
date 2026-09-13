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
        Schema::dropIfExists('scase_parent');
        Schema::create('scase_user', function (Blueprint $table) {
            $table->foreignId('scase_id')->constrained()->on('scases')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->on('users')->onDelete('cascade');
            $table->unsignedTinyInteger('relationship_type')->nullable();
            $table->primary(['scase_id', 'user_id', 'relationship_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scase_user');
        Schema::create('scase_parent', function (Blueprint $table) {
            $table->foreignId('scase_id')->constrained()->on('scases')->onDelete('cascade');
            $table->foreignId('parent_id')->constrained()->on('users')->onDelete('cascade');
            $table->primary(['scase_id', 'parent_id']);
        });
    }
};
