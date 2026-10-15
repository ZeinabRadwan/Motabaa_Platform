<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_room_attendances')) {
            return;
        }

        Schema::create('meeting_room_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_room_id')->constrained('meeting_rooms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('center_id');
            $table->timestamp('joined_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['meeting_room_id', 'user_id']);
            $table->index(['meeting_room_id', 'joined_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_room_attendances');
    }
};
