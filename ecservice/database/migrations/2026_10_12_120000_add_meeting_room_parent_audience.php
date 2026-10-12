<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('meeting_rooms') && ! Schema::hasColumn('meeting_rooms', 'audience_type')) {
            Schema::table('meeting_rooms', function (Blueprint $table) {
                $table->string('audience_type', 32)->default('general')->after('type');
            });
        }

        if (! Schema::hasTable('meeting_room_parents')) {
            Schema::create('meeting_room_parents', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_room_id')->constrained('meeting_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['meeting_room_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('meeting_room_notified_users')) {
            Schema::create('meeting_room_notified_users', function (Blueprint $table) {
                $table->id();
                $table->foreignId('meeting_room_id')->constrained('meeting_rooms')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->timestamp('notified_at')->nullable();
                $table->timestamps();
                $table->unique(['meeting_room_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_room_notified_users');
        Schema::dropIfExists('meeting_room_parents');

        if (Schema::hasTable('meeting_rooms') && Schema::hasColumn('meeting_rooms', 'audience_type')) {
            Schema::table('meeting_rooms', function (Blueprint $table) {
                $table->dropColumn('audience_type');
            });
        }
    }
};
