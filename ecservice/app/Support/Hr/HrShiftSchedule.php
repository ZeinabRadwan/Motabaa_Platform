<?php

namespace App\Support\Hr;

use App\Models\Hr\Branch;
use App\Models\Hr\Employee;
use App\Models\Hr\EmployeeShiftAssignment;
use App\Models\Hr\EmployeeWorkAssignment;
use App\Models\Hr\Shift;
use App\Models\Hr\ShiftException;
use App\Models\Hr\ShiftVersion;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Resolves what each employee is scheduled to work on each calendar day. Precedence per day:
 *  1. outside every employment period: not employed;
 *  2. a shift exception (employee scope > branch scope > whole center);
 *  3. the shift assignment in force that day, using the shift version in force that day:
 *     - rotating: the cycle day counted from the assignment start (plus its offset);
 *     - fixed: the employee's working / rest day overrides, else the shift's days;
 *  4. otherwise unscheduled.
 * Times are local to the employee's branch timezone (general.timezone when the branch has none).
 * Working days carry the attendance rules in force that day (HrAttendanceRules): the employee configuration and
 * policy version of that date, never today's. Grace: late = employee > shift > policy > setting; early leave =
 * shift > policy > setting.
 */
final class HrShiftSchedule
{
    /** @var array<int, Collection<int, EmployeeShiftAssignment>> */
    private array $assignments = [];

    /** @var array<int, Collection<int, EmployeeWorkAssignment>> */
    private array $workAssignments = [];

    /** @var Collection<int, ShiftException> */
    private Collection $exceptions;

    /** @var array<int, Shift> */
    private array $shifts = [];

    /** @var array<int, ?string> */
    private array $branchTimezones = [];

    /** @var array<string, ShiftDefinition> */
    private array $definitions = [];

    private string $timezone;

    private HrAttendanceRules $rules;

    private function __construct(private readonly int $centerId)
    {
        $this->exceptions = collect();
        $this->timezone = (string) (HrSettings::get($centerId, 'general.timezone') ?: config('app.timezone'));
    }

    /**
     * @param  Collection<int, Employee>  $employees  employees of one center
     * @return array<int, array<string, ScheduledShiftDay>> employee id => date => day
     */
    public static function forEmployees(int $centerId, Collection $employees, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->startOfDay();
        if ($employees->isEmpty() || $to->lt($from)) {
            return [];
        }

        $employees = EloquentCollection::make($employees->all());
        $resolver = new self($centerId);
        $resolver->load($employees, $from, $to);

        $days = [];
        foreach ($employees as $employee) {
            for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
                $date = $day->toDateString();
                $days[$employee->id][$date] = $resolver->resolve($employee, $date);
            }
        }

        return $days;
    }

    /** @return array<string, ScheduledShiftDay> date => day */
    public static function forEmployee(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        return self::forEmployees($employee->center_id, collect([$employee]), $from, $to)[$employee->id] ?? [];
    }

    public static function dayFor(Employee $employee, CarbonInterface|string $date): ScheduledShiftDay
    {
        $day = Carbon::parse($date)->toDateString();

        return self::forEmployee($employee, $day, $day)[$day];
    }

    /**
     * The scheduled day a punch at $at belongs to: the working day (yesterday's or today's) whose hours, widened
     * by attendance.punch_window_minutes or the day's check-in / check-out windows when wider, contain it; the
     * nearer shift wins when both do. A check-out at 06:10 after a 22:00 - 06:00 night shift belongs to the
     * previous calendar day.
     */
    public static function attributeTimestamp(Employee $employee, CarbonInterface $at): ?ScheduledShiftDay
    {
        $window = (int) HrSettings::get($employee->center_id, 'attendance.punch_window_minutes');
        $timezone = $employee->branch?->timezone ?: (string) (HrSettings::get($employee->center_id, 'general.timezone') ?: config('app.timezone'));
        $local = Carbon::instance($at)->setTimezone($timezone);

        $best = null;
        foreach (self::forEmployee($employee, $local->copy()->subDay(), $local) as $day) {
            if ($day->punchWindowContains($at, $window)
                && ($best === null || $day->distanceMinutes($at) < $best->distanceMinutes($at))) {
                $best = $day;
            }
        }

        return $best;
    }

    private function load(Collection $employees, Carbon $from, Carbon $to): void
    {
        $employees->loadMissing(['employmentPeriods']);
        $this->rules = HrAttendanceRules::load($this->centerId, $employees, $from, $to);
        $ids = $employees->pluck('id')->all();
        $first = $from->toDateString();
        $last = $to->toDateString().' 23:59:59';

        $overlapping = fn ($query) => $query->whereIn('employee_id', $ids)
            ->where('effective_from', '<=', $last)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', $first))
            ->orderBy('effective_from');

        $this->assignments = $overlapping(EmployeeShiftAssignment::query())->get()->groupBy('employee_id')->all();
        $this->workAssignments = $overlapping(EmployeeWorkAssignment::query())->get()->groupBy('employee_id')->all();

        $branchIds = $employees->pluck('branch_id')->filter()->all();
        foreach ($this->workAssignments as $rows) {
            $branchIds = array_merge($branchIds, $rows->pluck('branch_id')->all());
        }

        $this->exceptions = ShiftException::query()
            ->forCenter($this->centerId)
            ->where('date_from', '<=', $last)
            ->where('date_to', '>=', $first)
            ->where(fn ($q) => $q->whereIn('employee_id', $ids)
                ->orWhere(fn ($inner) => $inner->whereNull('employee_id')
                    ->where(fn ($branch) => $branch->whereNull('branch_id')->orWhereIn('branch_id', array_unique($branchIds)))))
            ->orderBy('id')
            ->get();

        $this->branchTimezones = Branch::withTrashed()->forCenter($this->centerId)
            ->whereIn('id', array_unique($branchIds))
            ->pluck('timezone', 'id')
            ->all();

        $shiftIds = $this->exceptions->pluck('shift_id')->filter()->all();
        foreach ($this->assignments as $rows) {
            $shiftIds = array_merge($shiftIds, $rows->pluck('shift_id')->all());
        }
        $this->loadShifts($shiftIds);
    }

    /** Loads shifts with their versions, then the fixed shifts their rotation cycles point to. */
    private function loadShifts(array $ids): void
    {
        for ($level = 0; $level < 2 && $ids !== []; $level++) {
            $loaded = Shift::withTrashed()->forCenter($this->centerId)
                ->whereIn('id', array_unique($ids))
                ->with(['versions' => fn ($q) => $q->orderBy('effective_from')])
                ->get();

            $ids = [];
            foreach ($loaded as $shift) {
                $this->shifts[$shift->id] = $shift;
                if ($shift->isRotating()) {
                    $patterns = $shift->versions->pluck('rotation_pattern')->push($shift->rotation_pattern);
                    foreach ($patterns as $pattern) {
                        foreach ((array) $pattern as $slot) {
                            if ($slot !== null && ! isset($this->shifts[(int) $slot])) {
                                $ids[] = (int) $slot;
                            }
                        }
                    }
                }
            }
        }
    }

    private function resolve(Employee $employee, string $date): ScheduledShiftDay
    {
        if (! $this->employedOn($employee, $date)) {
            return new ScheduledShiftDay($date, ScheduledShiftDay::STATUS_NOT_EMPLOYED);
        }

        $assignment = ($this->assignments[$employee->id] ?? collect())
            ->first(fn (EmployeeShiftAssignment $row) => $row->isEffectiveOn($date));
        $branchId = $this->branchOn($employee, $date);
        $timezone = ($branchId !== null ? ($this->branchTimezones[$branchId] ?? null) : null) ?: $this->timezone;

        $regular = $this->regularDay($employee, $assignment, $date, $timezone);
        $exception = $this->exceptionFor($employee->id, $branchId, $date);

        return $exception === null ? $regular : $this->applyException($employee, $exception, $regular, $date, $timezone);
    }

    private function regularDay(Employee $employee, ?EmployeeShiftAssignment $assignment, string $date, string $timezone): ScheduledShiftDay
    {
        $shift = $assignment !== null ? ($this->shifts[$assignment->shift_id] ?? null) : null;
        if ($shift === null) {
            return new ScheduledShiftDay($date, ScheduledShiftDay::STATUS_UNSCHEDULED);
        }

        $definition = $this->definitionOn($shift, $date);
        $context = ['assigned_shift_id' => $shift->id, 'assignment_id' => $assignment->id];

        if ($definition->isRotating()) {
            $length = $definition->cycleLength();
            if ($length === 0) {
                return new ScheduledShiftDay($date, ScheduledShiftDay::STATUS_UNSCHEDULED);
            }
            $index = HrShiftMath::rotationIndex($assignment->effective_from, Carbon::parse($date), $length, (int) $assignment->rotation_offset);
            $slot = $definition->rotationPattern[$index] ?? null;
            $slotShift = $slot !== null ? ($this->shifts[$slot] ?? null) : null;
            if ($slotShift === null) {
                return new ScheduledShiftDay($date, ScheduledShiftDay::STATUS_REST, ScheduledShiftDay::SOURCE_ASSIGNMENT, $definition, $shift->id, $assignment->id, $index);
            }

            return $this->workingDay($employee, $date, $this->definitionOn($slotShift, $date), $timezone, ScheduledShiftDay::SOURCE_ASSIGNMENT, $context + ['rotation_day' => $index]);
        }

        $weekday = Carbon::parse($date)->dayOfWeek;
        $working = $assignment->workingDaysOverride() ?? $definition->workingDays;
        if (in_array($weekday, $working, true)) {
            return $this->workingDay($employee, $date, $definition, $timezone, ScheduledShiftDay::SOURCE_ASSIGNMENT, $context);
        }

        $rest = $assignment->restDaysOverride() ?? array_values(array_diff($definition->restDays, $working));
        $status = in_array($weekday, $rest, true) ? ScheduledShiftDay::STATUS_REST : ScheduledShiftDay::STATUS_OFF;

        return new ScheduledShiftDay($date, $status, ScheduledShiftDay::SOURCE_ASSIGNMENT, $definition, $shift->id, $assignment->id);
    }

    private function applyException(Employee $employee, ShiftException $exception, ScheduledShiftDay $regular, string $date, string $timezone): ScheduledShiftDay
    {
        $context = [
            'assigned_shift_id' => $regular->assignedShiftId,
            'assignment_id' => $regular->assignmentId,
            'exception_id' => $exception->id,
            'exception_type' => $exception->exception_type,
            'exception_reason' => $exception->reason,
        ];

        if ($exception->exception_type === ShiftException::TYPE_SHIFT && isset($this->shifts[$exception->shift_id])) {
            $definition = $this->definitionOn($this->shifts[$exception->shift_id], $date);
            if (! $definition->isRotating()) {
                return $this->workingDay($employee, $date, $definition, $timezone, ScheduledShiftDay::SOURCE_EXCEPTION, $context);
            }
        }

        if ($exception->exception_type === ShiftException::TYPE_CUSTOM_HOURS && $exception->start_time && $exception->end_time) {
            $base = $regular->isWorking() ? $regular->shift : null;
            $definition = ShiftDefinition::custom(
                substr((string) $exception->start_time, 0, 5),
                substr((string) $exception->end_time, 0, 5),
                (int) $exception->break_minutes,
                $base,
            );

            return $this->workingDay($employee, $date, $definition, $timezone, ScheduledShiftDay::SOURCE_EXCEPTION, $context);
        }

        return new ScheduledShiftDay(
            date: $date,
            status: ScheduledShiftDay::STATUS_OFF,
            source: ScheduledShiftDay::SOURCE_EXCEPTION,
            shift: $regular->shift,
            assignedShiftId: $regular->assignedShiftId,
            assignmentId: $regular->assignmentId,
            rotationDay: $regular->rotationDay,
            exceptionId: $exception->id,
            exceptionType: $exception->exception_type,
            exceptionReason: $exception->reason,
        );
    }

    private function workingDay(Employee $employee, string $date, ShiftDefinition $definition, string $timezone, string $source, array $context): ScheduledShiftDay
    {
        $rules = $this->rules->resolve($employee, $date);

        return ScheduledShiftDay::working(
            $date,
            $definition,
            $timezone,
            $rules->lateGrace($definition->graceMinutes),
            $rules->earlyLeaveGrace($definition->earlyLeaveGraceMinutes),
            $source,
            $context,
            $rules,
        );
    }

    /** Version in force on $date; dates before the first version use the first one. */
    private function definitionOn(Shift $shift, string $date): ShiftDefinition
    {
        $version = null;
        foreach ($shift->versions as $candidate) {
            /** @var ShiftVersion $candidate */
            if ($candidate->effective_from->toDateString() > $date) {
                break;
            }
            $version = $candidate;
        }
        $version ??= $shift->versions->first();

        $key = $shift->id.':'.($version?->id ?? 'columns');

        return $this->definitions[$key] ??= ShiftDefinition::fromShift($shift, $version);
    }

    private function exceptionFor(int $employeeId, ?int $branchId, string $date): ?ShiftException
    {
        $best = null;
        $bestLevel = 0;
        foreach ($this->exceptions as $exception) {
            if (! $exception->covers($date)) {
                continue;
            }
            $level = match (true) {
                (int) $exception->employee_id === $employeeId => 3,
                $exception->employee_id === null && $exception->branch_id !== null && (int) $exception->branch_id === (int) $branchId => 2,
                $exception->employee_id === null && $exception->branch_id === null => 1,
                default => 0,
            };
            if ($level > 0 && $level >= $bestLevel) {
                $best = $exception;
                $bestLevel = $level;
            }
        }

        return $best;
    }

    private function employedOn(Employee $employee, string $date): bool
    {
        if ($employee->employmentPeriods->isEmpty()) {
            return $employee->employment_start_date === null || $employee->employment_start_date->toDateString() <= $date;
        }

        return $employee->employmentPeriods->contains(fn ($period) => $period->start_date->toDateString() <= $date
            && ($period->end_date === null || $period->end_date->toDateString() >= $date));
    }

    private function branchOn(Employee $employee, string $date): ?int
    {
        $row = ($this->workAssignments[$employee->id] ?? collect())
            ->first(fn (EmployeeWorkAssignment $assignment) => $assignment->isEffectiveOn($date));

        $branchId = $row?->branch_id ?? $employee->branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }
}
