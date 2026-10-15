<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_room_invitation_tokens')) {
            Schema::create('meeting_room_invitation_tokens', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_room_id')->constrained('meeting_rooms')->cascadeOnDelete();
                $table->string('token_hash', 64);
                $table->timestamp('expires_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();

                $table->index(['meeting_room_id', 'revoked_at']);
                $table->index('token_hash');
            });
        }

        if (! Schema::hasTable('meeting_room_link_grants')) {
            Schema::create('meeting_room_link_grants', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_room_id')->constrained('meeting_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('granted_at');
                $table->timestamps();

                $table->unique(['meeting_room_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_room_link_grants');
        Schema::dropIfExists('meeting_room_invitation_tokens');
    }
};
