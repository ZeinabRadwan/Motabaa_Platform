<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('questionnaire_task_notified_users');

        Schema::create('questionnaire_task_notified_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('notified_at');
            $table->timestamps();

            $table->unique(['task_id', 'user_id']);
            $table->index('task_id');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questionnaire_task_notified_users');
    }
};
