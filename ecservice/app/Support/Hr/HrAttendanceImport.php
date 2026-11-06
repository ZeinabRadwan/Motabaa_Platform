<?php

namespace App\Support\Hr;

use App\Imports\BaseImport;
use App\Models\Hr\AttendanceRecord;
use App\Models\Hr\Employee;
use App\Models\Hr\Shift;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Manual attendance from an Excel / CSV file (sheet "attendance" of the template, or the first sheet): one row per
 * employee and date with employee_number, attendance_date, check_in, check_out and optional shift_code / notes.
 *
 *  - Dates and times may be typed as text (2026-03-01, 01/03/2026, 08:00, 8:00 PM) or stored as Excel dates.
 *  - Rows without any time are skipped (a prefilled template lists employees who may not have attended).
 *  - Each row goes through HrManualAttendance like a form entry (same rules, permissions and calculations).
 *  - A dry run reports every row without saving; the import then saves the valid rows and reports the others.
 */
final class HrAttendanceImport
{
    public const MAX_ROWS = 500;

    public function __construct(
        private readonly HrManualAttendance $service,
        private readonly int $centerId,
        private readonly ?array $branchScope,
    ) {
    }

    /**
     * @param  array<string, mixed>  $common  entry_reason, entry_reason_notes
     * @return array{summary: array<string, int>, rows: array<int, array<string, mixed>>}
     *
     * @throws HrException when the file cannot be read or has no usable sheet
     */
    public function run(UploadedFile $file, array $common, bool $commit, CarbonInterface $now): array
    {
        $rows = $this->read($file);
        $employees = $this->employees($rows);
        $shifts = $this->shifts($rows);

        $report = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $entry = $this->entry($row, $employees, $shifts);
            $base = [
                'row' => $line,
                'employee_number' => $entry['employee_number'],
                'employee_name' => $entry['employee']?->user?->name ?? ($row['employee_name'] ?? null),
                'attendance_date' => $entry['values']['attendance_date'] ?? null,
                'check_in' => $entry['values']['check_in'] ?? null,
                'check_out' => $entry['values']['check_out'] ?? null,
            ];

            if ($entry['skip']) {
                $report[] = $base + ['status' => 'skipped', 'message' => __('validation.hr.attendance_import_row_empty')];

                continue;
            }
            if ($entry['error'] !== null) {
                $report[] = $base + ['status' => 'invalid', 'field' => $entry['error'][0], 'message' => __('validation.hr.'.$entry['error'][1], $entry['error'][2] ?? [])];

                continue;
            }

            $key = $entry['values']['employee_id'].'|'.$entry['values']['attendance_date'];
            if (isset($seen[$key])) {
                $report[] = $base + ['status' => 'invalid', 'field' => 'attendance_date', 'message' => __('validation.hr.attendance_import_duplicate_row', ['row' => $seen[$key]])];

                continue;
            }
            $seen[$key] = $line;

            try {
                if ($commit) {
                    $record = $this->service->create(array_merge($common, $entry['values']), AttendanceRecord::SOURCE_IMPORT, $now);
                    $report[] = $base + ['status' => 'created', 'record_id' => $record->id, 'attendance_status' => $record->attendance_status,
                        'late_minutes' => $record->late_minutes, 'early_departure_minutes' => $record->early_departure_minutes];
                } else {
                    $preview = $this->service->preview($entry['values'], null, $now);
                    $report[] = $base + ['status' => 'valid', 'attendance_status' => $preview['attendance_status'],
                        'late_minutes' => $preview['late_minutes'], 'early_departure_minutes' => $preview['early_departure_minutes'],
                        'warnings' => $preview['warnings']];
                }
            } catch (HrException $e) {
                $report[] = $base + ['status' => $commit ? 'failed' : 'invalid', 'field' => $e->field, 'message' => $e->localizedMessage()];
            }
        }

        $summary = ['total' => count($report)];
        foreach (['valid', 'invalid', 'skipped', 'created', 'failed'] as $status) {
            $summary[$status] = count(array_filter($report, fn ($row) => $row['status'] === $status));
        }

        return ['summary' => $summary, 'rows' => $report];
    }

    /** @return array<int, array<string, mixed>> rows keyed by heading */
    private function read(UploadedFile $file): array
    {
        try {
            $sheets = Excel::toArray(new BaseImport(), $file);
        } catch (\Throwable) {
            throw new HrException('attendance_import_unreadable', 'file');
        }

        $rows = $sheets[0] ?? [];
        $rows = array_values(array_filter($rows, fn ($row) => is_array($row) && array_filter($row, fn ($value) => $value !== null && trim((string) $value) !== '') !== []));
        if ($rows === [] || ! array_key_exists('employee_number', $rows[0])) {
            throw new HrException('attendance_import_invalid_template', 'file');
        }
        if (count($rows) > self::MAX_ROWS) {
            throw new HrException('attendance_import_too_many_rows', 'file', 422, ['max' => self::MAX_ROWS]);
        }

        return $rows;
    }

    /** @return array<string, Employee> employee number (lower case) => employee */
    private function employees(array $rows): array
    {
        $numbers = array_values(array_unique(array_filter(array_map(fn ($row) => self::cell($row['employee_number'] ?? null), $rows))));
        if ($numbers === []) {
            return [];
        }

        $query = Employee::query()->forCenter($this->centerId)->with('user')->whereIn('employee_number', $numbers);
        HrBranchScope::restrict($query, $this->branchScope, 'hr_employees.branch_id');

        return $query->get()->keyBy(fn (Employee $employee) => mb_strtolower((string) $employee->employee_number))->all();
    }

    /** @return array<string, Shift> shift code (lower case) => shift */
    private function shifts(array $rows): array
    {
        $codes = array_values(array_unique(array_filter(array_map(fn ($row) => self::cell($row['shift_code'] ?? null), $rows))));
        if ($codes === []) {
            return [];
        }

        return Shift::query()->forCenter($this->centerId)->whereIn('code', $codes)->get()
            ->keyBy(fn (Shift $shift) => mb_strtolower((string) $shift->code))
            ->all();
    }

    /**
     * @return array{employee_number: ?string, employee: ?Employee, values: array<string, mixed>, skip: bool, error: ?array}
     */
    private function entry(array $row, array $employees, array $shifts): array
    {
        $number = self::cell($row['employee_number'] ?? null);
        $employee = $number !== null ? ($employees[mb_strtolower($number)] ?? null) : null;
        $date = self::date($row['attendance_date'] ?? null);
        $in = self::time($row['check_in'] ?? null);
        $out = self::time($row['check_out'] ?? null);
        $shiftCode = self::cell($row['shift_code'] ?? null);

        $result = [
            'employee_number' => $number,
            'employee' => $employee,
            'values' => array_filter([
                'employee_id' => $employee?->id,
                'attendance_date' => $date,
                'check_in' => is_string($in) ? $in : null,
                'check_out' => is_string($out) ? $out : null,
                'notes' => self::cell($row['notes'] ?? null),
            ], fn ($value) => $value !== null),
            'skip' => false,
            'error' => null,
        ];

        $result['error'] = match (true) {
            $in === null && $out === null && self::cell($row['check_in'] ?? null) === null && self::cell($row['check_out'] ?? null) === null => null,
            $number === null => ['employee_number', 'attendance_import_employee_required'],
            $employee === null => ['employee_number', 'attendance_import_employee_unknown', ['number' => $number]],
            $date === null => ['attendance_date', 'attendance_import_date_invalid'],
            $in === false => ['check_in', 'attendance_import_time_invalid'],
            $out === false => ['check_out', 'attendance_import_time_invalid'],
            $shiftCode !== null && ! isset($shifts[mb_strtolower($shiftCode)]) => ['shift_code', 'attendance_import_shift_unknown', ['code' => $shiftCode]],
            default => null,
        };
        $result['skip'] = $result['error'] === null && $in === null && $out === null;
        if ($shiftCode !== null && isset($shifts[mb_strtolower($shiftCode)])) {
            $result['values']['shift_id'] = $shifts[mb_strtolower($shiftCode)]->id;
        }

        return $result;
    }

    private static function cell(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** Y-m-d from a text date (Y-m-d, d/m/Y, Y/m/d) or an Excel date serial; null when not a valid date. */
    public static function date(mixed $value): ?string
    {
        if (is_numeric($value) && (float) $value > 1) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
            } catch (\Throwable) {
                return null;
            }
        }
        $text = self::cell($value);
        if ($text === null) {
            return null;
        }
        foreach (['Y-m-d', 'd/m/Y', 'Y/m/d', 'd-m-Y'] as $format) {
            $date = \DateTimeImmutable::createFromFormat('!'.$format, $text);
            if ($date !== false && $date->format($format) === $text) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /**
     * "H:i" from a text time (8:00, 08:00:00, 8:00 PM) or an Excel time fraction / date-time serial; null when empty,
     * false when not a time.
     */
    public static function time(mixed $value): string|false|null
    {
        if (is_numeric($value)) {
            $fraction = (float) $value - floor((float) $value);
            $minutes = (int) round($fraction * 1440) % 1440;

            return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
        }
        $text = self::cell($value);
        if ($text === null) {
            return null;
        }
        $text = strtoupper(str_replace(['ص', 'م'], ['AM', 'PM'], $text));
        if (preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?\s*(AM|PM)?$/', $text, $match) !== 1) {
            return false;
        }
        [$hour, $minute] = [(int) $match[1], (int) $match[2]];
        if (isset($match[3]) && $match[3] !== '') {
            if ($hour < 1 || $hour > 12) {
                return false;
            }
            $hour = $hour % 12 + ($match[3] === 'PM' ? 12 : 0);
        }

        return $hour <= 23 && $minute <= 59 ? sprintf('%02d:%02d', $hour, $minute) : false;
    }
}
