<?php

namespace App\Http\Requests\Hr;

use App\Models\Hr\Employee;
use App\Rules\SaudiIdNumber;
use App\Support\Hr\SaudiIdentity;
use Illuminate\Validation\Rule;

/**
 * Create (link an existing staff user or register a new one) and edit of an employee: personal data stored on
 * users plus the HR employment fields. Attendance configuration is edited through EmployeeAttendanceRequest.
 */
class EmployeeRequest extends HrFormRequest
{
    /** Minimum working age (Saudi Labor Law, Article 162). */
    public const MIN_AGE = 15;

    protected function prepareForValidation(): void
    {
        $merge = [];
        if ($this->has('id_or_residence_number')) {
            $merge['id_or_residence_number'] = SaudiIdentity::normalize((string) $this->input('id_or_residence_number'));
        }
        if ($this->filled('nationality')) {
            $merge['nationality'] = strtoupper((string) $this->input('nationality'));
        }
        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $creating = $this->isCreate('employee');
        $required = $creating ? 'required' : 'sometimes';
        $userId = $this->targetUserId();
        $newUser = $creating && $this->input('mode') === 'new';

        $rules = [
            'mode' => $creating ? ['required', Rule::in(['existing', 'new'])] : ['prohibited'],
            'user_id' => $creating ? ['required_if:mode,existing', 'nullable', 'integer'] : ['prohibited'],
            'role_id' => $newUser ? ['required', 'integer'] : ['prohibited'],

            'name' => [$required, 'string', 'max:255'],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)->whereNull('deleted_at')],
            'phone' => [$required, 'string', 'max:20', 'regex:/^\+?[0-9]{7,15}$/', Rule::unique('users', 'phone')->ignore($userId)->whereNull('deleted_at')],
            'nationality' => [$required, 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'gender' => [$required, Rule::in([1, 2, '1', '2'])],
            'birthdate' => [$required, 'date', 'before_or_equal:'.now()->subYears(self::MIN_AGE)->toDateString(), 'after:1900-01-01'],
            'id_or_residence_number' => [$required, 'string', new SaudiIdNumber($this->nationalityForIdCheck())],
            'id_expiry_date' => ['nullable', 'date'],
            'address_city' => ['nullable', 'string', 'max:100'],
            'address_area' => ['nullable', 'string', 'max:150'],
            'address_street' => ['nullable', 'string', 'max:150'],
            'address_building' => ['nullable', 'string', 'max:50'],
            'address_number' => ['nullable', 'string', 'max:50'],
            'address_unit' => ['nullable', 'string', 'max:100'],
            'address_zipcode' => ['nullable', 'string', 'max:50'],

            'employee_number' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^[A-Za-z0-9_-]+$/'],
            'job_title' => [$required, 'string', 'max:150'],
            'branch_id' => [$required, 'integer'],
            'department_id' => [$required, 'integer'],
            'manager_id' => ['nullable', 'integer'],
            'work_location_id' => ['nullable', 'integer'],
            'hire_date' => [$required, 'date'],
            'employment_start_date' => array_merge([$required, 'date'], $this->filled('hire_date') ? ['after_or_equal:hire_date'] : []),
            'contract_type' => ['nullable', Rule::in(['permanent', 'temporary', 'part_time'])],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => array_merge(
                ['nullable', 'date', 'required_if:contract_type,temporary'],
                $this->filled('contract_start_date') ? ['after:contract_start_date'] : [],
                $this->filled('hire_date') ? ['after:hire_date'] : []
            ),
            'employment_status' => ['sometimes', Rule::in($creating
                ? [Employee::STATUS_ACTIVE, Employee::STATUS_PROBATION]
                : Employee::EMPLOYED_STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];

        if ($creating) {
            $rules += [
                'attendance_mode' => ['sometimes', Rule::in(Employee::ATTENDANCE_MODES)],
                'gps_policy' => ['nullable', Rule::in(Employee::GPS_POLICIES)],
                'grace_minutes' => ['nullable', 'integer', 'between:0,240'],
                'attendance_policy_id' => ['nullable', 'integer'],
                'shift_id' => ['nullable', 'integer'],
            ];
        } else {
            $rules += [
                'effective_date' => ['nullable', 'date', 'before_or_equal:today'],
                'change_reason' => ['nullable', 'string', 'max:500'],
            ];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return [
            'id_or_residence_number' => __('tr.hr.fields.id_number'),
            'employment_start_date' => __('tr.hr.fields.employment_start_date'),
            'hire_date' => __('tr.hr.fields.hire_date'),
            'birthdate' => __('tr.hr.fields.birthdate'),
        ];
    }

    public function messages(): array
    {
        return [
            'birthdate.before_or_equal' => __('validation.hr.employee_under_minimum_age', ['age' => self::MIN_AGE]),
            'phone.regex' => __('validation.hr.phone_invalid'),
        ];
    }

    /** User whose own email / phone must not count as duplicates. */
    private function targetUserId(): ?int
    {
        $routeId = $this->route('employee');
        if ($routeId !== null) {
            $userId = Employee::query()->forCenter($this->centerId())->whereKey((int) $routeId)->value('user_id');

            return $userId ? (int) $userId : null;
        }

        return $this->input('mode') === 'existing' && $this->filled('user_id') ? (int) $this->input('user_id') : null;
    }

    /** On edit without a nationality in the payload, check the ID against the stored nationality. */
    private function nationalityForIdCheck(): ?string
    {
        if ($this->filled('nationality')) {
            return (string) $this->input('nationality');
        }
        $routeId = $this->route('employee');
        if ($routeId === null) {
            return null;
        }
        $employee = Employee::query()->forCenter($this->centerId())->with('user:id,nationality')->find((int) $routeId);

        return $employee?->user?->nationality;
    }
}
