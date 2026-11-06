<?php

namespace App\Http\Controllers\Hr;

use App\Exports\Hr\AttendanceImportTemplate;
use App\Http\Requests\Hr\AttendanceImportRequest;
use App\Http\Requests\Hr\BulkManualAttendanceRequest;
use App\Http\Requests\Hr\ManualAttendanceRequest;
use App\Http\Resources\Hr\AttendancePunchResource;
use App\Http\Resources\Hr\AttendanceRecordChangeResource;
use App\Http\Resources\Hr\AttendanceRecordResource;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\System\ExportExcel;
use App\Support\Hr\HrAttendanceImport;
use App\Support\Hr\HrAttendanceRules;
use App\Support\Hr\HrBranchScope;
use App\Support\Hr\HrEffectiveDating;
use App\Support\Hr\HrManualAttendance;
use App\Support\Hr\HrSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Central attendance records (module hr-attendance-records): electronic and manual records together, each with its
 * original method. Viewing: access_; manual entry, bulk entry, import and corrections: edit_ (HrManualAttendance
 * enforces the history limit, payroll lock and own-record rule). Branch-scoped through the employee's branch.
 * The employee profile endpoints (records / punches) stay under hr-employees.
 */
class AttendanceRecordController extends HrController
{
    private const RELATIONS = ['employee.branch', 'employee.user', 'shift', 'workLocation', 'creator', 'updater'];

    /**
     * ?employee_id, ?branch_id, ?from / ?to (Y-m-d), ?attendance_status (one or many), ?method, ?entry_source,
     * ?needs_review, ?q (employee name or number); sortable by date and employee number.
     */
    public function index(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = $this->filteredRecords($request, $centerId)
                ->with(self::RELATIONS)
                ->select('hr_attendance_records.*');

            $sortKeys = collect((array) $request->input('options.sortBy', []))->map(fn ($sort) => is_array($sort) ? ($sort['key'] ?? null) : null);
            if ($sortKeys->contains('employee_number')) {
                $query->join('hr_employees as sort_employees', 'sort_employees.id', '=', 'hr_attendance_records.employee_id');
            }
            if ($sortKeys->intersect(['attendance_date', 'employee_number'])->isEmpty()) {
                $query->orderByDesc('hr_attendance_records.attendance_date')->orderBy('hr_attendance_records.employee_id');
            }

            return $this->paginated($request, $query, AttendanceRecordResource::class, [
                'attendance_date' => 'hr_attendance_records.attendance_date',
                'employee_number' => 'sort_employees.employee_number',
            ]);
        });
    }

    /**
     * Counts per attendance status and method for the same filters except status and review (the cards show the
     * whole breakdown), and what the user may enter.
     */
    public function summary(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $base = $this->filteredRecords($request, $centerId, ['attendance_status', 'needs_review']);
            $byStatus = (clone $base)->selectRaw('attendance_status, COUNT(*) as total')->groupBy('attendance_status')->pluck('total', 'attendance_status');
            $byMethod = (clone $base)->selectRaw('attendance_method, COUNT(*) as total')->groupBy('attendance_method')->pluck('total', 'attendance_method');

            return success([
                'total' => (int) $byStatus->sum(),
                'by_status' => collect(AttendanceRecord::ATTENDANCE_STATUSES)->mapWithKeys(fn ($status) => [$status => (int) ($byStatus[$status] ?? 0)])->all(),
                'by_method' => collect(AttendanceRecord::METHODS)->mapWithKeys(fn ($method) => [$method => (int) ($byMethod[$method] ?? 0)])->all(),
                'needs_review' => (clone $base)->where('needs_review', true)->count(),
                'limits' => $this->limits($request, $centerId),
            ]);
        });
    }

    /** Employees the user may see (filter and form options): ?q, ?branch_id, ?ids[], ?limit (50, max 200), ?date (attendance method that day). */
    public function employees(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $query = Employee::query()->forCenter($centerId)->with(['user', 'branch']);
            HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');
            $ids = array_filter(array_map('intval', (array) $request->get('ids', [])));
            if ($ids !== []) {
                $query->whereIn('hr_employees.id', $ids);
            } else {
                $query->employed();
            }
            if ((int) $request->get('branch_id') > 0) {
                $query->where('hr_employees.branch_id', (int) $request->get('branch_id'));
            }
            $term = trim((string) $request->get('q'));
            if ($term !== '') {
                $query->where(fn (Builder $inner) => $inner
                    ->where('hr_employees.employee_number', 'like', "%{$term}%")
                    ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%")));
            }

            $limit = $ids !== [] ? count($ids) : min(BulkManualAttendanceRequest::MAX_ENTRIES, max(1, (int) $request->get('limit', 50)));
            $employees = $query->orderBy('employee_number')->limit($limit)->get();
            $date = $this->date($request->get('date'));
            $rules = $date !== null ? HrAttendanceRules::load($centerId, $employees, $date, $date) : null;
            $recorded = $date !== null
                ? AttendanceRecord::query()->whereIn('employee_id', $employees->modelKeys())->whereDate('attendance_date', $date)->pluck('attendance_method', 'employee_id')
                : collect();

            return success($employees->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'value' => $employee->id,
                'title' => trim(($employee->user?->name ?? '').' ('.$employee->employee_number.')'),
                'name' => $employee->user?->name,
                'employee_number' => $employee->employee_number,
                'branch_id' => $employee->branch_id,
                'branch' => $employee->branch?->name,
                'attendance_mode' => $rules?->resolve($employee, $date)->attendanceMode ?? $employee->attendance_mode,
                // Method of the record already saved on ?date (null when none).
                'recorded_method' => $recorded[$employee->id] ?? null,
            ])->values());
        });
    }

    public function show(Request $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            return success((new AttendanceRecordResource($this->findRecord($request, $centerId, $record)->load(self::RELATIONS)))->resolve());
        });
    }

    /** Validates and calculates without saving (?record_id for a correction). */
    public function preview(ManualAttendanceRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $record = (int) $request->get('record_id') > 0 ? $this->findRecord($request, $centerId, (int) $request->get('record_id')) : null;

            return success($this->service($request, $centerId)->preview($request->entry(), $record, now()));
        });
    }

    public function store(ManualAttendanceRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $record = $this->service($request, $centerId)->create($request->entry(), AttendanceRecord::SOURCE_FORM, now());

            return success((new AttendanceRecordResource($record->load(self::RELATIONS)))->resolve(), null, __('validation.hr.attendance_record_created'));
        });
    }

    public function update(ManualAttendanceRequest $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            $model = $this->service($request, $centerId)->update($this->findRecord($request, $centerId, $record), $request->entry(), now());

            return success((new AttendanceRecordResource($model->load(self::RELATIONS)))->resolve(), null, __('validation.hr.attendance_record_updated'));
        });
    }

    /** Every manual creation and change of the record, newest first. */
    public function history(Request $request, int $record): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $record) {
            $model = $this->findRecord($request, $centerId, $record);
            $changes = $model->history()->with('changedBy')->orderByDesc('created_at')->orderByDesc('id')->get();

            return success([
                'record' => (new AttendanceRecordResource($model->load(self::RELATIONS)))->resolve(),
                'changes' => AttendanceRecordChangeResource::collection($changes)->resolve(),
            ]);
        });
    }

    /** Several records at once; each entry is saved or reported on its own. */
    public function bulk(BulkManualAttendanceRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $result = $this->service($request, $centerId)->createMany(
                $request->validated('entries'),
                $request->common(),
                AttendanceRecord::SOURCE_BULK,
                now(),
            );

            return success($result, null, __('validation.hr.attendance_bulk_saved', ['created' => $result['created'], 'failed' => $result['failed']]));
        });
    }

    /** Import template, optionally prefilled with the employees (?branch_id) for ?date. */
    public function importTemplate(Request $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $rows = [];
            $date = $this->date($request->get('date'));
            if ($date !== null) {
                $query = Employee::query()->forCenter($centerId)->employed()->with('user')
                    ->whereDoesntHave('attendanceRecords', fn (Builder $records) => $records->whereDate('attendance_date', $date));
                HrBranchScope::restrict($query, $this->branchScope($request, $centerId), 'hr_employees.branch_id');
                if ((int) $request->get('branch_id') > 0) {
                    $query->where('hr_employees.branch_id', (int) $request->get('branch_id'));
                }
                $employees = $query->orderBy('employee_number')->limit(HrAttendanceImport::MAX_ROWS)->get();
                $rules = HrAttendanceRules::load($centerId, $employees, $date, $date);
                foreach ($employees as $employee) {
                    if ($rules->resolve($employee, $date)->attendanceMode === Employee::ATTENDANCE_MANUAL) {
                        $rows[] = [$employee->employee_number, $employee->user?->name, $date, null, null, null, null];
                    }
                }
            }

            $name = ExportExcel::defaultFileName('Attendance import'.($date !== null ? " {$date}" : ''));
            $token = ExportExcel::saveExcel(new AttendanceImportTemplate($rows), $name);

            return success(['url' => exportDownloadUrl($token), 'rows' => count($rows)]);
        });
    }

    /** ?dry_run=1 reports every row without saving; otherwise the valid rows are saved. */
    public function import(AttendanceImportRequest $request): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request) {
            $service = $this->service($request, $centerId);
            $commit = ! $request->boolean('dry_run');
            $result = (new HrAttendanceImport($service, $centerId, $this->branchScope($request, $centerId)))
                ->run($request->file('file'), $request->common(), $commit, now());

            $message = $commit
                ? __('validation.hr.attendance_import_done', ['created' => $result['summary']['created'], 'failed' => $result['summary']['failed']])
                : null;

            return success($result, null, $message);
        });
    }

    /** Employee profile: optional filters ?from= / ?to= (Y-m-d), ?needs_review=1. */
    public function records(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $query = $this->filtered($request, $model->attendanceRecords(), 'attendance_date')
                ->with(['employee.branch', 'shift', 'workLocation', 'creator', 'updater'])
                ->when($request->boolean('needs_review'), fn (Builder $q) => $q->where('needs_review', true))
                ->when(in_array($request->get('method'), AttendanceRecord::METHODS, true), fn (Builder $q) => $q->where('attendance_method', $request->get('method')))
                ->orderByDesc('attendance_date');

            return $this->paginated($request, $query, AttendanceRecordResource::class);
        });
    }

    /** Employee profile: optional filters ?from= / ?to= (Y-m-d), ?result=accepted|rejected|duplicate. */
    public function punches(Request $request, int $employee): JsonResponse
    {
        return $this->inCenter($request, function (int $centerId) use ($request, $employee) {
            $model = $this->findInCenter(Employee::class, $employee, $centerId);
            $query = $this->filtered($request, $model->attendancePunches(), 'attempted_at')
                ->with(['employee.branch', 'workLocation'])
                ->when(in_array($request->get('result'), ['accepted', 'rejected', 'duplicate'], true), fn (Builder $q) => $q->where('result', $request->get('result')))
                ->orderByDesc('attempted_at')
                ->orderByDesc('id');

            return $this->paginated($request, $query, AttendancePunchResource::class);
        });
    }

    private function service(Request $request, int $centerId): HrManualAttendance
    {
        return new HrManualAttendance($centerId, $request->user(), $this->branchScope($request, $centerId));
    }

    private function findRecord(Request $request, int $centerId, int $id): AttendanceRecord
    {
        return $this->scoped($request, AttendanceRecord::query()->forCenter($centerId), $centerId, false)->whereKey($id)->firstOrFail();
    }

    /** @param  string[]  $except  filters to ignore */
    private function filteredRecords(Request $request, int $centerId, array $except = []): Builder
    {
        $query = $this->scoped($request, AttendanceRecord::query()->forCenter($centerId), $centerId, true);

        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            $date = $this->date($request->get($key));
            if ($date !== null) {
                $query->whereDate('hr_attendance_records.attendance_date', $operator, $date);
            }
        }
        $statuses = array_values(array_intersect((array) $request->get('attendance_status', []), AttendanceRecord::ATTENDANCE_STATUSES));
        if ($statuses !== [] && ! in_array('attendance_status', $except, true)) {
            $query->whereIn('hr_attendance_records.attendance_status', $statuses);
        }
        if (in_array($request->get('method'), AttendanceRecord::METHODS, true)) {
            $query->where('hr_attendance_records.attendance_method', $request->get('method'));
        }
        if (in_array($request->get('entry_source'), AttendanceRecord::ENTRY_SOURCES, true)) {
            $query->where('hr_attendance_records.entry_source', $request->get('entry_source'));
        }
        if ($request->boolean('corrected')) {
            $query->whereNotNull('hr_attendance_records.modified_at');
        }
        if ($request->boolean('needs_review') && ! in_array('needs_review', $except, true)) {
            $query->where('hr_attendance_records.needs_review', true);
        }

        return $query;
    }

    /** User's branches, plus ?branch_id / ?employee_id / ?q when $filters. */
    private function scoped(Request $request, Builder $query, int $centerId, bool $filters): Builder
    {
        $scope = $this->branchScope($request, $centerId);
        $branchId = $filters ? (int) $request->get('branch_id') : 0;
        $term = $filters ? trim((string) $request->get('q')) : '';

        return $query
            ->whereHas('employee', function (Builder $employee) use ($scope, $branchId, $term) {
                HrBranchScope::restrict($employee, $scope, $employee->qualifyColumn('branch_id'));
                if ($branchId > 0) {
                    $employee->where($employee->qualifyColumn('branch_id'), $branchId);
                }
                if ($term !== '') {
                    $employee->where(fn (Builder $inner) => $inner
                        ->where($employee->qualifyColumn('employee_number'), 'like', "%{$term}%")
                        ->orWhereHas('user', fn (Builder $user) => $user->where('name', 'like', "%{$term}%")));
                }
            })
            ->when($filters && (int) $request->get('employee_id') > 0, fn (Builder $q) => $q->where('hr_attendance_records.employee_id', (int) $request->get('employee_id')));
    }

    /** What the signed-in user may enter: dates, history limit and payroll lock (the server enforces them). */
    private function limits(Request $request, int $centerId): array
    {
        $timezone = (string) (HrSettings::get($centerId, 'general.timezone') ?: config('app.timezone'));
        $today = CarbonImmutable::now($timezone)->toDateString();
        $days = (int) HrSettings::get($centerId, 'attendance.manual_entry_max_days_back');
        $user = $request->user();

        return [
            'today' => $today,
            'max_days_back' => $days,
            'oldest_date' => CarbonImmutable::parse($today)->subDays($days)->toDateString(),
            'locked_until' => HrEffectiveDating::lockedUntil($centerId)?->toDateString(),
            'can_edit' => canAny(['edit_hr-attendance-records', 'admin_hr-attendance-records'], $user),
            'can_enter_historical' => can(HrManualAttendance::HISTORICAL_PERMISSION, $user),
            'can_edit_own' => can(HrManualAttendance::OWN_RECORDS_PERMISSION, $user),
            'own_employee_id' => Employee::query()->forCenter($centerId)->where('user_id', $user?->id)->value('id'),
        ];
    }

    private function filtered(Request $request, HasMany $relation, string $column): Builder
    {
        $query = $relation->getQuery();
        foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->get($key))) {
                $query->whereDate($column, $operator, $request->get($key));
            }
        }

        return $query;
    }

    private function date(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
