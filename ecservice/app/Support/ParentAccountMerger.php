<?php

namespace App\Support;

use App\Models\Log;
use App\Models\System\System;
use App\Models\User;
use App\Models\UserPhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ParentAccountMerger
{
    /**
     * @param  array<string, mixed>  $group  From ParentAccountMergePlanner
     * @return array<string, mixed>
     */
    public static function mergeGroup(array $group): array
    {
        $primaryId = (int) $group['primary_account_id'];
        $duplicateIds = array_map('intval', $group['merged_account_ids'] ?? []);

        if ($duplicateIds === []) {
            return [
                'merge_status' => 'skipped',
                'reason' => 'no_duplicates_in_group',
                'group_key' => $group['group_key'] ?? null,
            ];
        }

        return DB::transaction(function () use ($group, $primaryId, $duplicateIds) {
            /** @var User $primary */
            $primary = User::query()->lockForUpdate()->findOrFail($primaryId);

            if (! isParentUser($primary) || $primary->merged_into_user_id) {
                throw new \RuntimeException('Primary account is not an eligible parent user.');
            }

            $transferLog = [];
            $duplicateContacts = [];
            foreach ($duplicateIds as $duplicateId) {
                /** @var User $duplicate */
                $duplicate = User::query()->lockForUpdate()->findOrFail($duplicateId);
                if (! isParentUser($duplicate)) {
                    throw new \RuntimeException("User {$duplicateId} is not parent-only.");
                }
                if ($duplicate->merged_into_user_id) {
                    throw new \RuntimeException("User {$duplicateId} was already merged.");
                }

                if (! $primary->password && $duplicate->password) {
                    $primary->password = $duplicate->password;
                    $primary->save();
                }

                $duplicateContacts[] = ['email' => $duplicate->email, 'phone' => $duplicate->phone];
                $transferLog[$duplicateId] = self::transferRelationships($duplicate, $primary);
                $transferLog[$duplicateId]['user_phone_numbers'] = self::transferPhoneNumbers(
                    $duplicate,
                    $primary,
                    array_merge([$primaryId], $duplicateIds)
                );
                self::deactivateDuplicateAccount($duplicate, $primary);
            }

            $phoneBeforeFinalize = $primary->phone;
            $contactChanges = self::finalizePrimaryContactFields($primary, $duplicateContacts);
            if (isset($contactChanges['phone'])) {
                ParentPhoneNumberService::syncAfterAccountPhoneChange($primary, $phoneBeforeFinalize);
            }
            $primary->refresh();

            $missing = self::verifyStudentsPreserved($primaryId, $group['linked_students_after_primary'] ?? []);
            if ($missing !== []) {
                throw new \RuntimeException('Student links missing after merge: '.implode(',', $missing));
            }

            $linkedAfter = DB::table('scase_user')
                ->where('user_id', $primaryId)
                ->where('relationship_type', System::USER_TYPE_PARENT)
                ->pluck('scase_id')
                ->map(fn ($id) => (int) $id)
                ->sort()
                ->values()
                ->all();

            $audit = [
                'canonical_id' => $primaryId,
                'duplicate_ids' => $duplicateIds,
                'match_type' => $group['match_type'] ?? null,
                'normalized_email' => $group['normalized_email'] ?? null,
                'normalized_phone' => $group['normalized_phone'] ?? null,
                'case_ids' => $linkedAfter,
                'email_details' => $group['email_details'] ?? [],
                'phone_details' => $group['phone_details'] ?? [],
                'primary_contact_changes' => $contactChanges,
                'transfers' => $transferLog,
            ];

            Log::add('parent_account_merge', $primary, $primaryId, json_encode($audit), 0, null);
            \Illuminate\Support\Facades\Log::info('parent_account_merge', $audit);

            return [
                'merge_status' => 'merged',
                'group_key' => $group['group_key'],
                'primary_account_id' => $primaryId,
                'merged_account_ids' => $duplicateIds,
                'match_type' => $group['match_type'] ?? null,
                'normalized_email' => $group['normalized_email'] ?? null,
                'normalized_phone' => $group['normalized_phone'] ?? null,
                'linked_students_before' => $group['linked_students_before'] ?? [],
                'linked_students_after' => $linkedAfter,
                'email_differs' => $group['email_differs'] ?? false,
                'email_details' => $group['email_details'] ?? [],
                'phone_differs' => $group['phone_differs'] ?? false,
                'phone_details' => $group['phone_details'] ?? [],
                'primary_contact_changes' => $contactChanges,
                'review_notes' => $group['review_notes'] ?? [],
                'transfers' => $transferLog,
            ];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function transferRelationships(User $from, User $to): array
    {
        $fromId = (int) $from->id;
        $toId = (int) $to->id;
        $counts = [];

        $counts['scase_user'] = self::transferScaseUserPivots($fromId, $toId);
        $counts['centers_users'] = self::transferSimplePivot('centers_users', $fromId, $toId, ['center_id']);
        $counts['term_user'] = self::transferSimplePivot('term_user', $fromId, $toId, ['term_id']);

        if (Schema::hasTable('meeting_room_parents')) {
            $counts['meeting_room_parents'] = self::transferSimplePivot('meeting_room_parents', $fromId, $toId, ['meeting_room_id']);
        }
        if (Schema::hasTable('meeting_room_notified_users')) {
            $counts['meeting_room_notified_users'] = self::transferSimplePivot('meeting_room_notified_users', $fromId, $toId, ['meeting_room_id']);
        }
        if (Schema::hasTable('meeting_room_link_grants')) {
            $counts['meeting_room_link_grants'] = self::transferSimplePivot('meeting_room_link_grants', $fromId, $toId, ['meeting_room_id']);
        }
        if (Schema::hasTable('meeting_room_attendances')) {
            $counts['meeting_room_attendances'] = self::transferMeetingAttendances($fromId, $toId);
        }

        $counts['messages'] = self::repointColumn('messages', 'user_id', $fromId, $toId);
        $counts['questionnaires_answers'] = self::repointColumn('questionnaires_answers', 'user_id', $fromId, $toId);
        if (Schema::hasTable('questionnaire_task_starts')) {
            $counts['questionnaire_task_starts'] = self::transferUniqueTaskRows('questionnaire_task_starts', $fromId, $toId);
        }
        if (Schema::hasTable('questionnaire_task_submissions')) {
            $counts['questionnaire_task_submissions'] = self::transferUniqueTaskRows('questionnaire_task_submissions', $fromId, $toId);
        }
        if (Schema::hasTable('questionnaire_task_notified_users')) {
            $counts['questionnaire_task_notified_users'] = self::transferSimplePivot('questionnaire_task_notified_users', $fromId, $toId, ['task_id']);
        }
        if (Schema::hasTable('center_activity_entries')) {
            $counts['center_activity_entries'] = self::repointColumn('center_activity_entries', 'user_id', $fromId, $toId);
        }
        if (Schema::hasTable('media_attachment_audit_logs')) {
            $counts['media_attachment_audit_logs'] = self::repointColumn('media_attachment_audit_logs', 'user_id', $fromId, $toId);
        }

        if (Schema::hasTable('notifications')) {
            $counts['notifications'] = DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $fromId)
                ->update(['notifiable_id' => $toId]);
        }

        if (Schema::hasTable('personal_access_tokens')) {
            DB::table('personal_access_tokens')
                ->where('tokenable_type', User::class)
                ->where('tokenable_id', $fromId)
                ->delete();
            $counts['personal_access_tokens_deleted'] = true;
        }

        return $counts;
    }

    /**
     * Moves the duplicate's numbers (its rows plus its login phone) to the survivor as UNVERIFIED,
     * non-primary entries; the survivor keeps its own primary/login phone. The duplicate's rows are deleted.
     *
     * @param  array<int, int>  $groupIds  Every account in this merge (their ownership does not block a move)
     * @return array{transfer: array<int, array{phone: string, label: string|null}>, skipped: array<int, array{phone: string|null, reason: string}>}
     */
    private static function transferPhoneNumbers(User $from, User $to, array $groupIds): array
    {
        if (! User::hasPhoneNumbersTable()) {
            return ['transfer' => [], 'skipped' => []];
        }

        $fromRows = UserPhoneNumber::query()->where('user_id', $from->id)->lockForUpdate()->get();
        $candidates = array_merge(
            [['phone' => $from->phone, 'label' => null]],
            $fromRows->map(fn (UserPhoneNumber $r) => ['phone' => $r->phone, 'label' => $r->label])->all()
        );

        $primaryPhones = UserPhoneNumber::query()->where('user_id', $to->id)->lockForUpdate()->pluck('phone')->all();
        if ($primaryLogin = ParentPhoneNumberRules::normalize($to->phone)) {
            $primaryPhones[] = $primaryLogin;
        }

        $ownedElsewhere = [];
        foreach ($candidates as $candidate) {
            $normalized = ParentPhoneNumberRules::normalize($candidate['phone']);
            if ($normalized === null || isset($ownedElsewhere[$normalized])) {
                continue;
            }
            $byLogin = User::withTrashed()
                ->whereIn('phone', LoginIdentifier::phoneMatchCandidates($normalized))
                ->whereNotIn('id', $groupIds)
                ->exists();
            $byVerified = UserPhoneNumber::query()
                ->where('phone', $normalized)
                ->whereNotIn('user_id', $groupIds)
                ->whereNotNull('verified_at')
                ->exists();
            $ownedElsewhere[$normalized] = $byLogin || $byVerified;
        }

        $plan = ParentPhoneNumberRules::mergeTransferPlan($candidates, array_values(array_unique($primaryPhones)), $ownedElsewhere);

        UserPhoneNumber::query()->where('user_id', $from->id)->delete();
        foreach ($plan['transfer'] as $number) {
            UserPhoneNumber::create([
                'user_id' => $to->id,
                'phone' => $number['phone'],
                'is_primary' => false,
                'verified_at' => null,
                'verification_method' => null,
                'label' => $number['label'],
            ]);
        }

        return $plan;
    }

    private static function transferScaseUserPivots(int $fromId, int $toId): int
    {
        $rows = DB::table('scase_user')->where('user_id', $fromId)->get();
        $attached = 0;

        foreach ($rows as $row) {
            $exists = DB::table('scase_user')
                ->where('scase_id', $row->scase_id)
                ->where('user_id', $toId)
                ->where('relationship_type', $row->relationship_type)
                ->exists();

            if (! $exists) {
                DB::table('scase_user')->insert([
                    'scase_id' => $row->scase_id,
                    'user_id' => $toId,
                    'relationship_type' => $row->relationship_type,
                ]);
                $attached++;
            }
        }

        DB::table('scase_user')->where('user_id', $fromId)->delete();

        return $attached;
    }

    /**
     * @param  array<int, string>  $uniqueColumns
     */
    private static function transferSimplePivot(string $table, int $fromId, int $toId, array $uniqueColumns): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $rows = DB::table($table)->where('user_id', $fromId)->get();
        $attached = 0;

        foreach ($rows as $row) {
            $query = DB::table($table)->where('user_id', $toId);
            foreach ($uniqueColumns as $column) {
                $query->where($column, $row->{$column});
            }
            if (! $query->exists()) {
                $payload = (array) $row;
                unset($payload['id']);
                $payload['user_id'] = $toId;
                DB::table($table)->insert($payload);
                $attached++;
            }
        }

        DB::table($table)->where('user_id', $fromId)->delete();

        return $attached;
    }

    private static function transferMeetingAttendances(int $fromId, int $toId): int
    {
        if (! Schema::hasTable('meeting_room_attendances')) {
            return 0;
        }

        $rows = DB::table('meeting_room_attendances')->where('user_id', $fromId)->get();
        $moved = 0;
        foreach ($rows as $row) {
            $exists = DB::table('meeting_room_attendances')
                ->where('meeting_room_id', $row->meeting_room_id)
                ->where('user_id', $toId)
                ->exists();
            if ($exists) {
                DB::table('meeting_room_attendances')->where('id', $row->id)->delete();
            } else {
                DB::table('meeting_room_attendances')->where('id', $row->id)->update(['user_id' => $toId]);
                $moved++;
            }
        }

        return $moved;
    }

    private static function transferUniqueTaskRows(string $table, int $fromId, int $toId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $rows = DB::table($table)->where('user_id', $fromId)->get();
        $moved = 0;
        foreach ($rows as $row) {
            $exists = DB::table($table)
                ->where('task_id', $row->task_id)
                ->where('user_id', $toId)
                ->exists();
            if ($exists) {
                DB::table($table)->where('id', $row->id)->delete();
            } else {
                DB::table($table)->where('id', $row->id)->update(['user_id' => $toId]);
                $moved++;
            }
        }

        return $moved;
    }

    private static function repointColumn(string $table, string $column, int $fromId, int $toId): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        return DB::table($table)->where($column, $fromId)->update([$column => $toId]);
    }

    private static function deactivateDuplicateAccount(User $duplicate, User $primary): void
    {
        $duplicateId = (int) $duplicate->id;
        $placeholderPhone = ParentIdentity::mergedAccountPlaceholderPhone($duplicateId);

        $duplicateEmail = $duplicate->email;
        $duplicate->merged_into_user_id = (int) $primary->id;
        $duplicate->status = User::STATUS_CANNT_LOGIN;
        $duplicate->email = null;
        $duplicate->phone = $placeholderPhone;
        $duplicate->remember_token = null;
        $duplicate->save();

        $duplicate->delete();

        \Illuminate\Support\Facades\Log::info('parent_account_deactivated', [
            'duplicate_id' => $duplicateId,
            'primary_id' => (int) $primary->id,
            'former_email' => $duplicateEmail,
            'placeholder_phone' => $placeholderPhone,
        ]);
    }

    /**
     * Keeps the primary's own email/phone (phone stored in canonical 966… form). Only fills an empty
     * field from a duplicate, and never takes a value owned by another live account.
     *
     * @param  array<int, array{email: string|null, phone: string|null}>  $duplicateContacts
     * @return array<string, array{from: string|null, to: string|null}>
     */
    private static function finalizePrimaryContactFields(User $primary, array $duplicateContacts): array
    {
        $changes = [];

        $canonical = ParentIdentity::canonicalPhone($primary->phone);
        if ($canonical !== null && $canonical !== $primary->phone && ! self::phoneTakenByOther($canonical, (int) $primary->id)) {
            $changes['phone'] = ['from' => $primary->phone, 'to' => $canonical];
            $primary->phone = $canonical;
        }

        if (blank($primary->phone)) {
            foreach ($duplicateContacts as $contact) {
                $candidate = ParentIdentity::canonicalPhone($contact['phone']);
                if ($candidate !== null && ! self::phoneTakenByOther($candidate, (int) $primary->id)) {
                    $changes['phone'] = ['from' => $primary->phone, 'to' => $candidate];
                    $primary->phone = $candidate;

                    break;
                }
            }
        }

        if (blank($primary->email)) {
            foreach ($duplicateContacts as $contact) {
                $candidate = ParentIdentity::normalizeEmail($contact['email']);
                if ($candidate !== null && ! self::emailTakenByOther($candidate, (int) $primary->id)) {
                    $changes['email'] = ['from' => $primary->email, 'to' => $candidate];
                    $primary->email = $candidate;

                    break;
                }
            }
        }

        $primary->save();

        return $changes;
    }

    private static function phoneTakenByOther(string $canonical, int $exceptId): bool
    {
        return User::withTrashed()
            ->whereIn('phone', LoginIdentifier::phoneMatchCandidates($canonical))
            ->where('id', '!=', $exceptId)
            ->exists();
    }

    private static function emailTakenByOther(string $normalized, int $exceptId): bool
    {
        return User::withTrashed()
            ->whereRaw('LOWER(TRIM(email)) = ?', [$normalized])
            ->where('id', '!=', $exceptId)
            ->exists();
    }

    /**
     * @param  array<int, int>  $expectedCaseIds
     * @return array<int, int> Case ids that are no longer linked to the primary
     */
    private static function verifyStudentsPreserved(int $primaryId, array $expectedCaseIds): array
    {
        if ($expectedCaseIds === []) {
            return [];
        }

        $linked = DB::table('scase_user')
            ->where('user_id', $primaryId)
            ->where('relationship_type', System::USER_TYPE_PARENT)
            ->whereIn('scase_id', $expectedCaseIds)
            ->pluck('scase_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff(array_map('intval', $expectedCaseIds), $linked));
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array<string, mixed>
     */
    public static function executePlan(array $plan): array
    {
        $results = [];
        foreach ($plan['merge_groups'] as $group) {
            if (($group['merge_status'] ?? '') !== ParentAccountMergePlanner::STATUS_WOULD_MERGE) {
                continue;
            }
            try {
                $results[] = self::mergeGroup($group);
            } catch (\Throwable $e) {
                report($e);
                $results[] = [
                    'merge_status' => 'failed',
                    'group_key' => $group['group_key'] ?? null,
                    'error' => get_class($e).': '.$e->getMessage(),
                    'rolled_back' => true,
                ];
            }
        }

        return [
            'generated_at' => now()->toIso8601String(),
            'mode' => 'apply',
            'merged' => array_values(array_filter($results, fn ($r) => ($r['merge_status'] ?? '') === 'merged')),
            'failed' => array_values(array_filter($results, fn ($r) => ($r['merge_status'] ?? '') === 'failed')),
        ];
    }
}
