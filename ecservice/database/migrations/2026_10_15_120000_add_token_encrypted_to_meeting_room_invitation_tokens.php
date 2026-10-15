<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('meeting_room_invitation_tokens')) {
            return;
        }

        Schema::table('meeting_room_invitation_tokens', function (Blueprint $table) {
            if (! Schema::hasColumn('meeting_room_invitation_tokens', 'token_encrypted')) {
                $table->text('token_encrypted')->nullable()->after('token_hash');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('meeting_room_invitation_tokens')) {
            return;
        }

        Schema::table('meeting_room_invitation_tokens', function (Blueprint $table) {
            if (Schema::hasColumn('meeting_room_invitation_tokens', 'token_encrypted')) {
                $table->dropColumn('token_encrypted');
            }
        });
    }
};
