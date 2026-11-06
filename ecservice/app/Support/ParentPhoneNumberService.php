<?php

namespace App\Support;

use App\Models\Log;
use App\Models\User;
use App\Models\UserPhoneNumber;
use Illuminate\Support\Facades\DB;

/**
 * Writes for parent phone numbers. Every mutation locks the owning users row first, so concurrent edits of
 * the same account are serialized; that lock is what keeps "exactly one primary" true (MySQL has no
 * portable partial unique index for it). users.phone always mirrors the primary number.
 */
class ParentPhoneNumberService
{
    public static function assertManageable(User $user): void
    {
        $user->loadMissing('roles');
        if (! isParentUser($user) || $user->merged_into_user_id) {
            throw new ParentPhoneNumberException(ParentPhoneNumberRules::ERR_NOT_PARENT);
        }
    }

    /**
     * Rows for display. Accounts not yet backfilled get a virtual, read-only primary built from users.phone.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function displayList(User $user): array
    {
        $rows = $user->relationLoaded('phoneNumbers')
            ? $user->phoneNumbers
            : (User::hasPhoneNumbersTable() ? $user->phoneNumbers()->get() : collect());

        $list = $rows->map(fn (UserPhoneNumber $row) => self::present($row))->values()->all();

        if (ParentPhoneNumberRules::primaryCount(array_map(fn ($r) => ['is_primary' => $r['is_primary']], $list)) === 0
            && ($legacy = ParentPhoneNumberRules::normalize($user->phone)) !== null) {
            array_unshift($list, [
                'id' => null,
                'phone' => $legacy,
                'display_phone' => $user->phone,
                'label' => null,
                'is_primary' => true,
                'verified' => true,
                'verified_at' => null,
                'verification_method' => UserPhoneNumber::METHOD_LEGACY_PRIMARY,
                'is_virtual' => true,
                'created_at' => null,
                'updated_at' => null,
            ]);
        }

        return $list;
    }

    /**
     * @return array<string, mixed>
     */
    public static function present(UserPhoneNumber $row): array
    {
        return [
            'id' => (int) $row->id,
            'phone' => $row->phone,
            'display_phone' => $row->raw_phone ?: $row->phone,
            'label' => $row->label,
            'is_primary' => (bool) $row->is_primary,
            'verified' => $row->verified_at !== null,
            'verified_at' => $row->verified_at,
            'verification_method' => $row->verification_method,
            'is_virtual' => false,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ];
    }

    /**
     * Materializes the primary row from users.phone (the existing login identifier, hence verified with
     * method legacy_primary). Must run inside a transaction holding the users row lock.
     */
    public static function ensurePrimaryRow(User $user): ?UserPhoneNumber
    {
        $rows = UserPhoneNumber::query()->where('user_id', $user->id)->lockForUpdate()->get();
        if ($primary = $rows->firstWhere('is_primary', true)) {
            return $primary;
        }

        $decision = ParentPhoneNumberRules::backfillDecision(
            $user->phone,
            [],
            self::verifiedElsewhere((string) ParentPhoneNumberRules::normalize($user->phone), (int) $user->id)
        );
        $normalized = ParentPhoneNumberRules::normalize($user->phone);
        if ($decision !== ParentPhoneNumberRules::BACKFILL_CREATE || $normalized === null) {
            return null;
        }

        $existing = $rows->firstWhere('phone', $normalized);
        if ($existing) {
            $existing->is_primary = true;
            if (! $existing->verified_at) {
                $existing->verified_at = now();
                $existing->verification_method = UserPhoneNumber::METHOD_LEGACY_PRIMARY;
            }
            $existing->save();

            return $existing;
        }

        return UserPhoneNumber::create([
            'user_id' => $user->id,
            'phone' => $normalized,
            'raw_phone' => $user->phone !== $normalized ? $user->phone : null,
            'is_primary' => true,
            'verified_at' => now(),
            'verification_method' => UserPhoneNumber::METHOD_LEGACY_PRIMARY,
        ]);
    }

    public static function add(User $user, string $rawPhone, ?string $label, bool $markVerified): UserPhoneNumber
    {
        return DB::transaction(function () use ($user, $rawPhone, $label, $markVerified) {
            $locked = self::lockUser($user);
            self::ensurePrimaryRow($locked);

            $normalized = ParentPhoneNumberRules::normalize($rawPhone);
            if ($normalized !== null) {
                self::lockNumber($normalized);
            }
            $error = ParentPhoneNumberRules::addError(
                $normalized,
                self::accountPhones($locked),
                $normalized !== null ? self::otherOwners($normalized, (int) $locked->id) : []
            );
            if ($error !== null) {
                throw new ParentPhoneNumberException($error);
            }

            $row = UserPhoneNumber::create([
                'user_id' => $locked->id,
                'phone' => $normalized,
                'raw_phone' => trim($rawPhone) !== $normalized ? trim($rawPhone) : null,
                'is_primary' => false,
                'verified_at' => $markVerified ? now() : null,
                'verification_method' => $markVerified ? UserPhoneNumber::METHOD_ADMIN_MANUAL : null,
                'label' => self::cleanLabel($label),
            ]);

            self::audit('parent_phone_number_add', $locked, [
                'phone_number_id' => (int) $row->id,
                'phone' => $normalized,
                'verified' => $markVerified,
                'label' => $row->label,
            ]);

            return $row;
        });
    }

    public static function update(User $user, int $numberId, ?string $rawPhone, ?string $label): UserPhoneNumber
    {
        return DB::transaction(function () use ($user, $numberId, $rawPhone, $label) {
            $locked = self::lockUser($user);
            $row = self::findRow($locked, $numberId);
            $before = ['phone' => $row->phone, 'label' => $row->label, 'verified' => $row->verified_at !== null];

            if ($rawPhone !== null && trim($rawPhone) !== '') {
                $normalized = ParentPhoneNumberRules::normalize($rawPhone);
                if ($normalized !== null && $normalized !== $row->phone) {
                    self::lockNumber($normalized);
                }
                $error = ParentPhoneNumberRules::updateError(
                    ['phone' => $row->phone, 'is_primary' => (bool) $row->is_primary],
                    $normalized,
                    self::accountPhones($locked),
                    $normalized !== null && $normalized !== $row->phone ? self::otherOwners($normalized, (int) $locked->id) : []
                );
                if ($error !== null) {
                    throw new ParentPhoneNumberException($error);
                }
                if ($normalized !== $row->phone) {
                    $row->phone = $normalized;
                    $row->verified_at = null;
                    $row->verification_method = null;
                }
                $row->raw_phone = trim($rawPhone) !== $normalized ? trim($rawPhone) : null;
            }

            $row->label = self::cleanLabel($label);
            $row->save();

            self::audit('parent_phone_number_edit', $locked, [
                'phone_number_id' => (int) $row->id,
                'before' => $before,
                'after' => ['phone' => $row->phone, 'label' => $row->label, 'verified' => $row->verified_at !== null],
            ]);

            return $row;
        });
    }

    public static function remove(User $user, int $numberId): void
    {
        DB::transaction(function () use ($user, $numberId) {
            $locked = self::lockUser($user);
            $row = self::findRow($locked, $numberId);
            if ($error = ParentPhoneNumberRules::deleteError(['is_primary' => (bool) $row->is_primary])) {
                throw new ParentPhoneNumberException($error);
            }

            $row->delete();

            self::audit('parent_phone_number_remove', $locked, [
                'phone_number_id' => $numberId,
                'phone' => $row->phone,
                'was_verified' => $row->verified_at !== null,
            ]);
        });
    }

    public static function setPrimary(User $user, int $numberId, bool $markVerified): UserPhoneNumber
    {
        return DB::transaction(function () use ($user, $numberId, $markVerified) {
            $locked = self::lockUser($user);
            self::ensurePrimaryRow($locked);
            $rows = UserPhoneNumber::query()->where('user_id', $locked->id)->lockForUpdate()->get();
            $target = $rows->firstWhere('id', $numberId);
            if (! $target) {
                throw new \Illuminate\Database\Eloquent\ModelNotFoundException();
            }
            if ($target->is_primary) {
                return $target;
            }

            $error = ParentPhoneNumberRules::setPrimaryError(
                ['is_primary' => false, 'verified' => $target->verified_at !== null],
                $markVerified
            );
            if ($error !== null) {
                throw new ParentPhoneNumberException($error);
            }

            self::lockNumber($target->phone);
            $owners = self::otherOwners($target->phone, (int) $locked->id);
            if ($conflict = ParentPhoneNumberRules::ownerConflict($owners)) {
                throw new ParentPhoneNumberException($conflict);
            }

            $previousPhone = $locked->phone;
            $previousPrimary = $rows->firstWhere('is_primary', true);
            $verifiedNow = false;

            foreach ($rows as $row) {
                if ($row->is_primary && $row->id !== $target->id) {
                    $row->is_primary = false;
                    $row->save();
                }
            }
            if (! $target->verified_at) {
                $target->verified_at = now();
                $target->verification_method = UserPhoneNumber::METHOD_ADMIN_MANUAL;
                $verifiedNow = true;
            }
            $target->is_primary = true;
            $target->save();

            $locked->phone = $target->phone;
            $locked->save();

            self::audit('parent_phone_number_primary', $locked, [
                'phone_number_id' => (int) $target->id,
                'previous_primary_id' => $previousPrimary ? (int) $previousPrimary->id : null,
                'account_phone' => ['from' => $previousPhone, 'to' => $target->phone],
                'verified_in_same_action' => $verifiedNow,
            ]);

            return $target;
        });
    }

    public static function setVerified(User $user, int $numberId, bool $verified): UserPhoneNumber
    {
        return DB::transaction(function () use ($user, $numberId, $verified) {
            $locked = self::lockUser($user);
            $row = self::findRow($locked, $numberId);

            if (! $verified) {
                if ($error = ParentPhoneNumberRules::unverifyError(['is_primary' => (bool) $row->is_primary])) {
                    throw new ParentPhoneNumberException($error);
                }
                $row->verified_at = null;
                $row->verification_method = null;
            } elseif (! $row->verified_at) {
                self::lockNumber($row->phone);
                if ($conflict = ParentPhoneNumberRules::ownerConflict(self::otherOwners($row->phone, (int) $locked->id))) {
                    throw new ParentPhoneNumberException($conflict);
                }
                $row->verified_at = now();
                $row->verification_method = UserPhoneNumber::METHOD_ADMIN_MANUAL;
            }
            $row->save();

            self::audit($verified ? 'parent_phone_number_verify' : 'parent_phone_number_unverify', $locked, [
                'phone_number_id' => (int) $row->id,
                'phone' => $row->phone,
                'method' => $row->verification_method,
            ]);

            return $row;
        });
    }

    /**
     * Error code when an account's login phone (users.phone) is being changed to a number that another
     * account holds as a verified number; null when allowed. users.phone uniqueness itself is validated by
     * the existing form requests.
     */
    public static function accountPhoneConflict(User $user, ?string $newPhone): ?string
    {
        $normalized = ParentPhoneNumberRules::normalize($newPhone);
        if ($normalized === null || ! User::hasPhoneNumbersTable()) {
            return null;
        }

        return self::verifiedElsewhere($normalized, (int) $user->id) ? ParentPhoneNumberRules::ERR_CONFLICT : null;
    }

    /**
     * Keeps the primary row in step after users.phone was edited through the existing profile/user forms.
     * Accounts without rows are left alone (their primary is still derived from users.phone).
     */
    public static function syncAfterAccountPhoneChange(User $user, ?string $previousPhone): void
    {
        if (! User::hasPhoneNumbersTable()) {
            return;
        }
        $normalized = ParentPhoneNumberRules::normalize($user->phone);
        if ($normalized === null || $normalized === ParentPhoneNumberRules::normalize($previousPhone)) {
            return;
        }

        DB::transaction(function () use ($user, $normalized, $previousPhone) {
            $locked = self::lockUser($user, false);
            $rows = UserPhoneNumber::query()->where('user_id', $locked->id)->lockForUpdate()->get();
            if ($rows->isEmpty()) {
                return;
            }

            foreach ($rows as $row) {
                if ($row->is_primary && $row->phone !== $normalized) {
                    $row->is_primary = false;
                    $row->save();
                }
            }

            $target = $rows->firstWhere('phone', $normalized) ?? new UserPhoneNumber([
                'user_id' => $locked->id,
                'phone' => $normalized,
            ]);
            $target->is_primary = true;
            if (! $target->verified_at) {
                $target->verified_at = now();
                $target->verification_method = UserPhoneNumber::METHOD_ACCOUNT_PHONE_CHANGE;
            }
            $target->raw_phone = $locked->phone !== $normalized ? $locked->phone : null;
            $target->save();

            self::audit('parent_phone_number_primary', $locked, [
                'phone_number_id' => (int) $target->id,
                'account_phone' => ['from' => $previousPhone, 'to' => $locked->phone],
                'source' => 'account_phone_change',
            ]);
        });
    }

    /**
     * Accounts other than $exceptUserId that hold the number as their login phone (any state, since
     * users.phone is unique across soft-deleted rows too) or as a verified additional number.
     *
     * @return array<int, array{user_id: int, is_parent: bool, source: string}>
     */
    public static function otherOwners(string $normalized, int $exceptUserId): array
    {
        $owners = [];
        $byAccountPhone = User::withTrashed()
            ->with('roles:id,default_name')
            ->whereIn('phone', LoginIdentifier::phoneMatchCandidates($normalized))
            ->where('id', '!=', $exceptUserId)
            ->get(['id']);
        foreach ($byAccountPhone as $other) {
            $owners[] = ['user_id' => (int) $other->id, 'is_parent' => isParentUser($other), 'source' => 'account_phone'];
        }

        if (User::hasPhoneNumbersTable()) {
            $byVerified = UserPhoneNumber::query()
                ->with('user.roles:id,default_name')
                ->where('phone', $normalized)
                ->where('user_id', '!=', $exceptUserId)
                ->whereNotNull('verified_at')
                ->get();
            foreach ($byVerified as $row) {
                $owners[] = [
                    'user_id' => (int) $row->user_id,
                    'is_parent' => $row->user ? isParentUser($row->user) : true,
                    'source' => 'verified_number',
                ];
            }
        }

        return $owners;
    }

    public static function verifiedElsewhere(string $normalized, int $exceptUserId): bool
    {
        if ($normalized === '' || ! User::hasPhoneNumbersTable()) {
            return false;
        }

        return UserPhoneNumber::query()
            ->where('phone', $normalized)
            ->where('user_id', '!=', $exceptUserId)
            ->whereNotNull('verified_at')
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    private static function accountPhones(User $user): array
    {
        $phones = UserPhoneNumber::query()->where('user_id', $user->id)->pluck('phone')->all();
        if ($legacy = ParentPhoneNumberRules::normalize($user->phone)) {
            $phones[] = $legacy;
        }

        return array_values(array_unique($phones));
    }

    private static function lockUser(User $user, bool $requireParent = true): User
    {
        /** @var User $locked */
        $locked = User::query()->lockForUpdate()->findOrFail($user->id);
        $locked->setRelation('roles', $user->relationLoaded('roles') ? $user->roles : $locked->roles()->get());
        if ($requireParent) {
            self::assertManageable($locked);
        }

        return $locked;
    }

    /**
     * Locks existing rows (and the index gap) for one number so two accounts cannot verify it concurrently.
     */
    private static function lockNumber(string $normalized): void
    {
        UserPhoneNumber::query()->where('phone', $normalized)->lockForUpdate()->get(['id']);
    }

    private static function findRow(User $user, int $numberId): UserPhoneNumber
    {
        /** @var UserPhoneNumber $row */
        $row = UserPhoneNumber::query()
            ->where('user_id', $user->id)
            ->lockForUpdate()
            ->findOrFail($numberId);

        return $row;
    }

    private static function cleanLabel(?string $label): ?string
    {
        $label = $label !== null ? trim($label) : null;

        return $label === '' ? null : mb_substr($label, 0, 50);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private static function audit(string $type, User $user, array $details): void
    {
        $user->loadMissing('centers');
        Log::add($type, $user, (int) $user->id, json_encode($details, JSON_UNESCAPED_UNICODE), $user->centers[0]->id ?? 0);
    }
}
