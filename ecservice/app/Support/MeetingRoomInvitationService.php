<?php

namespace App\Support;

use App\Models\MeetingRoom;
use App\Models\MeetingRoomInvitationToken;
use App\Models\MeetingRoomLinkGrant;
use App\Models\System\System;
use App\Models\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class MeetingRoomInvitationService
{
    public static function tablesReady(): bool
    {
        try {
            return Schema::hasTable('meeting_room_invitation_tokens')
                && Schema::hasTable('meeting_room_link_grants');
        } catch (\Throwable) {
            return false;
        }
    }

    public static function hashToken(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    public static function buildInvitationUrl(string $plainToken): string
    {
        $base = rtrim((string) config('app.front_url'), '/');

        return $base.'/meeting-rooms/invite?token='.urlencode($plainToken);
    }

    /**
     * Returns an active plain token, creating one when none exists.
     */
    public static function ensureActivePlainToken(MeetingRoom $room): ?string
    {
        return self::resolveActivePlainToken($room, true);
    }

    /**
     * Returns the plain token for the room's active invitation, optionally creating one.
     */
    public static function resolveActivePlainToken(MeetingRoom $room, bool $createIfMissing = false): ?string
    {
        if (! self::tablesReady() || ! MeetingRoomAccess::isLinkInviteAudience($room)) {
            return null;
        }

        $existing = self::findActiveTokenForRoom($room);
        if ($existing) {
            return self::plainFromTokenRow($existing);
        }

        if ($createIfMissing) {
            return self::createToken($room);
        }

        return null;
    }

    public static function findActiveTokenForRoom(MeetingRoom $room): ?MeetingRoomInvitationToken
    {
        if (! self::tablesReady()) {
            return null;
        }

        return MeetingRoomInvitationToken::query()
            ->where('meeting_room_id', $room->id)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Active token exists but was stored before encrypted retrieval was available.
     */
    public static function activeInvitationTokenIsLegacy(MeetingRoom $room): bool
    {
        $token = self::findActiveTokenForRoom($room);
        if (! $token) {
            return false;
        }

        return ! self::tokenRowHasRetrievablePlain($token);
    }

    public static function plainFromTokenRow(MeetingRoomInvitationToken $token): ?string
    {
        if (! self::tokenRowHasRetrievablePlain($token)) {
            return null;
        }

        try {
            return Crypt::decryptString((string) $token->token_encrypted);
        } catch (DecryptException) {
            return null;
        }
    }

    private static function tokenRowHasRetrievablePlain(MeetingRoomInvitationToken $token): bool
    {
        return filled($token->token_encrypted);
    }

    /**
     * Revokes prior tokens and returns a new plain token.
     */
    public static function rotatePlainToken(MeetingRoom $room): ?string
    {
        if (! self::tablesReady() || ! MeetingRoomAccess::isLinkInviteAudience($room)) {
            return null;
        }

        self::revokeTokensForRoom($room);

        return self::createToken($room);
    }

    public static function revokeTokensForRoom(MeetingRoom $room): void
    {
        if (! Schema::hasTable('meeting_room_invitation_tokens')) {
            return;
        }

        MeetingRoomInvitationToken::query()
            ->where('meeting_room_id', $room->id)
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);
    }

    public static function clearGrantsForRoom(MeetingRoom $room): void
    {
        if (! Schema::hasTable('meeting_room_link_grants')) {
            return;
        }

        MeetingRoomLinkGrant::query()
            ->where('meeting_room_id', $room->id)
            ->delete();
    }

    public static function findRoomByPlainToken(string $plainToken): ?MeetingRoom
    {
        if (! self::tablesReady() || $plainToken === '') {
            return null;
        }

        $hash = self::hashToken($plainToken);

        $tokenRow = MeetingRoomInvitationToken::query()
            ->where('token_hash', $hash)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $tokenRow) {
            return null;
        }

        return MeetingRoom::query()->find($tokenRow->meeting_room_id);
    }

    public static function tokenIsValid(string $plainToken): bool
    {
        return self::findRoomByPlainToken($plainToken) !== null;
    }

    public static function parentHasLinkGrant(User $user, MeetingRoom $room): bool
    {
        if (! self::tablesReady()) {
            return false;
        }

        return MeetingRoomLinkGrant::query()
            ->where('meeting_room_id', $room->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    public static function grantParentAccess(User $user, MeetingRoom $room): void
    {
        if (! self::tablesReady()) {
            return;
        }

        MeetingRoomLinkGrant::query()->updateOrCreate(
            [
                'meeting_room_id' => $room->id,
                'user_id' => $user->id,
            ],
            ['granted_at' => now()]
        );
    }

    /**
     * Center staff who manage meeting rooms may open a valid invitation URL without a parent link grant.
     */
    public static function staffCanBypassInvitationRedeem(User $user, MeetingRoom $room): bool
    {
        if (! MeetingRoomAccess::isLinkInviteAudience($room)) {
            return false;
        }

        if (! $user->isInCenter((int) $room->center_id)) {
            return false;
        }

        if ($user->can('admin_meetings')) {
            return MeetingRoomAccess::userCanAccess($user, $room);
        }

        if ($user->can('edit_meetings')) {
            return MeetingRoomAccess::userCanAccess($user, $room);
        }

        return false;
    }

    /**
     * @return array{ok: bool, room: ?MeetingRoom, error: ?string}
     */
    public static function redeemForUser(string $plainToken, User $user): array
    {
        $room = self::findRoomByPlainToken($plainToken);
        if (! $room) {
            return ['ok' => false, 'room' => null, 'error' => 'invalid'];
        }

        if (! MeetingRoomAccess::isLinkInviteAudience($room)) {
            return ['ok' => false, 'room' => null, 'error' => 'invalid'];
        }

        if (! $user->isInCenter((int) $room->center_id)) {
            return ['ok' => false, 'room' => null, 'error' => 'center'];
        }

        if (self::staffCanBypassInvitationRedeem($user, $room)) {
            return ['ok' => true, 'room' => $room, 'error' => null];
        }

        if (! MeetingRoomAccess::userHasParentRole($user)) {
            return ['ok' => false, 'room' => null, 'error' => 'role'];
        }

        if (! User::query()
            ->where('id', $user->id)
            ->whereHas('centers', fn ($q) => $q->where('centers.id', $room->center_id))
            ->whereHas('roles', fn ($q) => $q->where('roles.default_name', System::USER_TYPE_PARENT_ROLE_NAME))
            ->exists()) {
            return ['ok' => false, 'room' => null, 'error' => 'center'];
        }

        self::grantParentAccess($user, $room);

        return ['ok' => true, 'room' => $room, 'error' => null];
    }

    private static function createToken(MeetingRoom $room): string
    {
        $plain = Str::random(48);

        MeetingRoomInvitationToken::query()->create([
            'meeting_room_id' => $room->id,
            'token_hash' => self::hashToken($plain),
            'token_encrypted' => Crypt::encryptString($plain),
            'expires_at' => null,
            'revoked_at' => null,
        ]);

        return $plain;
    }

    public static function roomHasActiveInvitationToken(MeetingRoom $room): bool
    {
        if (! self::tablesReady()) {
            return false;
        }

        return MeetingRoomInvitationToken::query()
            ->where('meeting_room_id', $room->id)
            ->whereNull('revoked_at')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }
}
