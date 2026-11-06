<?php

namespace App\Http\Controllers\Hr;

use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrException;
use App\Support\Hr\HrShiftSchedule;
use App\Support\Hr\ScheduledShiftDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Read-only schedule views built by HrShiftSchedule: a team grid (employees x days) and one employee's
 * calendar. The same resolved days are what attendance processing will compare punches against.
 */
class ShiftScheduleController extends HrController
{
    private const MAX_GRID_DAYS = 42;
    private const MAX_EMPLOYEE_DAYS = 62;

    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            [$from, $to] = $this->range($request, self::MAX_GRID_DAYS);
            $q = trim((string) $request->get('q', ''));

            $query = Employee::query()->forCenter($centerId)->employed()
                ->select('hr_employees.*')
                ->join('users', 'users.id', '=', 'hr_employees.user_id')
                ->with(['user:id,name,job_title', 'branch:id,name_ar,name_en,deleted_at', 'department:id,name_ar,name_en,deleted_at'])
                ->when($request->filled('branch_id'), fn ($query) => $query->where('hr_employees.branch_id', (int) $request->get('branch_id')))
                ->when($request->filled('department_id'), fn ($query) => $query->where('hr_employees.department_id', (int) $request->get('department_id')))
                ->when($request->filled('employee_id'), fn ($query) => $query->where('hr_employees.id', (int) $request->get('employee_id')))
                ->when($request->filled('shift_id'), fn ($query) => $query->whereHas('shiftAssignments', fn ($assignments) => $assignments
                    ->where('shift_id', (int) $request->get('shift_id'))
                    ->where('effective_from', '<=', $to->toDateString().' 23:59:59')
                    ->where(fn ($open) => $open->whereNull('effective_to')->orWhere('effective_to', '>=', $from->toDateString()))))
                ->when($q !== '', fn ($query) => $query->where(fn ($inner) => $inner
                    ->where('hr_employees.employee_number', 'like', "%{$q}%")
                    ->orWhere('users.name', 'like', "%{$q}%")))
                ->orderBy('hr_employees.employee_number');
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');

            $perPage = min(50, max(5, (int) $request->get('per_page', 15)));
            $page = $query->paginate($perPage, ['*'], 'page', max(1, (int) $request->get('page', 1)));
            $days = HrShiftSchedule::forEmployees($centerId, $page->getCollection(), $from, $to);

            $rows = [];
            $legend = [];
            foreach ($page->getCollection() as $employee) {
                $cells = [];
                foreach ($days[$employee->id] ?? [] as $day) {
                    $cells[] = $day->toArray();
                    $this->addToLegend($legend, $day);
                }
                $rows[] = [
                    'employee' => [
                        'id' => $employee->id,
                        'employee_number' => $employee->employee_number,
                        'name' => $employee->user?->name,
                        'job_title' => $employee->user?->job_title,
                        'branch_id' => $employee->branch_id,
                        'branch_name' => $employee->branch?->name,
                        'department_name' => $employee->department?->name,
                    ],
                    'days' => $cells,
                ];
            }

            return success([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'dates' => $this->dates($from, $to),
                'rows' => $rows,
                'legend' => array_values($legend),
                'total' => $page->total(),
                'page' => $page->currentPage(),
                'per_page' => $perPage,
            ]);
        });
    }

    /** One employee's resolved days (profile calendar). Mounted under the hr-employees module. */
    public function employee(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            [$from, $to] = $this->range($request, self::MAX_EMPLOYEE_DAYS);
            $model = $this->findInCenter(Employee::class, $employee, $centerId);

            $legend = [];
            $days = [];
            foreach (HrShiftSchedule::forEmployee($model, $from, $to) as $day) {
                $days[] = $day->toArray();
                $this->addToLegend($legend, $day);
            }

            return success([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'days' => $days,
                'legend' => array_values($legend),
            ]);
        });
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function range(Request $request, int $maxDays): array
    {
        $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $from = Carbon::parse($request->get('from'))->startOfDay();
        $to = Carbon::parse($request->get('to'))->startOfDay();
        if ($from->diffInDays($to) + 1 > $maxDays) {
            throw new HrException('schedule_range_invalid', 'to', 422, ['days' => $maxDays]);
        }

        return [$from, $to];
    }

    /** @return array<int, array{date: string, weekday: int}> */
    private function dates(Carbon $from, Carbon $to): array
    {
        $dates = [];
        for ($day = $from->copy(); $day->lte($to); $day->addDay()) {
            $dates[] = ['date' => $day->toDateString(), 'weekday' => $day->dayOfWeek];
        }

        return $dates;
    }

    private function addToLegend(array &$legend, ScheduledShiftDay $day): void
    {
        $shift = $day->shift;
        if ($shift === null || $shift->shiftId === null || isset($legend[$shift->shiftId]) || ! $day->isWorking()) {
            return;
        }
        $legend[$shift->shiftId] = [
            'shift_id' => $shift->shiftId,
            'code' => $shift->code,
            'name' => $shift->name,
            'color' => $shift->color,
            'shift_type' => $shift->type,
            'time_range' => $shift->startTime.' - '.$shift->endTime,
            'is_overnight' => $shift->isOvernight(),
            'shift_type_label' => Shift::typeLabels()[$shift->type] ?? $shift->type,
        ];
    }
}
