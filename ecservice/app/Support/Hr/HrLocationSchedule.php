<?php

namespace App\Support\Hr;

use Carbon\CarbonImmutable;

/**
 * When the periodic location checks of an attendance session are due. Pure (no database).
 *
 *  - The first check is due one interval after the check-in; after every received location the next one is due one
 *    interval after it (a late check moves the schedule instead of asking twice).
 *  - Checks are only scheduled during working hours: a slot at or after the end of monitoring (the scheduled shift
 *    end) is never due, so no check is requested once the shift is over.
 *  - Slots that passed without a location (closed or suspended tab, no network) are listed by dueSlots() so they can
 *    be stored as pending: a missing location is never assumed to be a successful verification.
 */
final class HrLocationSchedule
{
    /** A location received up to this long before a slot counts for that slot (client timers are not exact). */
    public const EARLY_TOLERANCE_SECONDS = 60;

    /** A due check without a location is reported as overdue after this long. */
    public const OVERDUE_AFTER_SECONDS = 300;

    /** Safety cap on the slots created in one pass (a 5-minute interval over a 20-hour session is 240). */
    private const MAX_SLOTS = 400;

    /** The check due one interval after $from, or null when that is no longer inside working hours. */
    public static function nextAfter(CarbonImmutable $from, int $intervalMinutes, ?CarbonImmutable $until): ?CarbonImmutable
    {
        $next = $from->addMinutes($intervalMinutes);

        return $until !== null && $next->gte($until) ? null : $next;
    }

    /**
     * The slots due from $nextDue up to $upTo (inclusive), one interval apart and before $until, and the next slot
     * after them (null once working hours end).
     *
     * @return array{0: CarbonImmutable[], 1: ?CarbonImmutable}
     */
    public static function dueSlots(CarbonImmutable $nextDue, CarbonImmutable $upTo, int $intervalMinutes, ?CarbonImmutable $until): array
    {
        $slots = [];
        $slot = $nextDue;
        while ($slot !== null && $slot->lte($upTo) && count($slots) < self::MAX_SLOTS) {
            if ($until !== null && $slot->gte($until)) {
                return [$slots, null];
            }
            $slots[] = $slot;
            $slot = self::nextAfter($slot, $intervalMinutes, $until);
        }

        return [$slots, $slot];
    }

    /** Whether a check may be submitted now for the slot due at $nextDue. */
    public static function isDue(?CarbonImmutable $nextDue, CarbonImmutable $now): bool
    {
        return $nextDue !== null && $now->getTimestamp() >= $nextDue->getTimestamp() - self::EARLY_TOLERANCE_SECONDS;
    }

    public static function isOverdue(?CarbonImmutable $nextDue, CarbonImmutable $now): bool
    {
        return $nextDue !== null && $now->getTimestamp() > $nextDue->getTimestamp() + self::OVERDUE_AFTER_SECONDS;
    }

    /**
     * The scheduled slot a location taken at $checkedAt answers: the latest of $pendingDue within one interval before
     * it (or just after it, within the early tolerance). Older slots stay unanswered.
     *
     * @param  CarbonImmutable[]  $pendingDue
     */
    public static function slotFor(array $pendingDue, CarbonImmutable $checkedAt, int $intervalMinutes): ?CarbonImmutable
    {
        $from = $checkedAt->getTimestamp() - $intervalMinutes * 60;
        $to = $checkedAt->getTimestamp() + self::EARLY_TOLERANCE_SECONDS;
        $best = null;
        foreach ($pendingDue as $due) {
            $at = $due->getTimestamp();
            if ($at > $from && $at <= $to && ($best === null || $at > $best->getTimestamp())) {
                $best = $due;
            }
        }

        return $best;
    }
}
