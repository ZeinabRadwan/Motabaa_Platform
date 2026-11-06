<?php

namespace Tests\Feature\Hr;

use App\Models\Hr\Employee;
use App\Support\Hr\HrShiftCalculator;
use App\Support\Hr\HrShiftSchedule;
use App\Support\Hr\HrSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Shift management: regular, overnight and rotating schedules, effective-dated assignment and timing
 * changes, exceptions, and the guards around them. "Today" is Sunday 2026-03-01 (Riyadh).
 */
class HrShiftManagementApiTest extends HrTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-03-01 10:00', 'Asia/Riyadh'));
    }

    public function test_regular_shift_schedule_with_weekly_rest_days_and_grace_precedence(): void
    {
        $shift = $this->createShift(['code' => 'AM', 'start_time' => '08:00', 'end_time' => '17:00', 'break_minutes' => 60]);
        $this->getJson("/api/hr/shifts/{$shift}")
            ->assertOk()
            ->assertJsonPath('data.shift_type', 'morning')
            ->assertJsonPath('data.rest_days', [5, 6])
            ->assertJsonPath('data.effective_expected_minutes', 480)
            ->assertJsonPath('data.is_overnight', false);

        $employee = $this->createEmployee($this->makeUser('emp@example.test', 'teacher'));
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $shift, 'effective_from' => '2026-03-01'])->assertOk();

        $days = $this->employeeSchedule($employee, '2026-03-01', '2026-03-07');
        $sunday = $days['2026-03-01'];
        $this->assertSame('working', $sunday['status']);
        $this->assertSame('2026-03-01T08:00:00+03:00', $sunday['start_at']);
        $this->assertSame('2026-03-01T17:00:00+03:00', $sunday['end_at']);
        $this->assertSame(480, $sunday['expected_minutes']);
        $this->assertSame(60, $sunday['break_minutes']);
        $this->assertSame(15, $sunday['late_grace_minutes'], 'center setting');
        $this->assertSame('rest', $days['2026-03-06']['status'], 'Friday');
        $this->assertSame('rest', $days['2026-03-07']['status'], 'Saturday');

        // Shift grace beats the setting; the employee's own grace beats the shift.
        $this->putJson("/api/hr/shifts/{$shift}", ['grace_minutes' => 5, 'effective_from' => '2026-03-01'])->assertOk();
        $this->assertSame(5, $this->employeeSchedule($employee, '2026-03-02', '2026-03-02')['2026-03-02']['late_grace_minutes']);
        $this->putJson("/api/hr/employees/{$employee}/attendance", [
            'attendance_mode' => 'manual', 'grace_minutes' => 20, 'effective_from' => '2026-03-02',
        ])->assertOk();
        $graceDays = $this->employeeSchedule($employee, '2026-03-01', '2026-03-02');
        $this->assertSame(5, $graceDays['2026-03-01']['late_grace_minutes'], 'before the employee override took effect');
        $this->assertSame(20, $graceDays['2026-03-02']['late_grace_minutes']);

        // Employee-specific working days.
        $this->postJson("/api/hr/employees/{$employee}/shifts", [
            'shift_id' => $shift, 'effective_from' => '2026-03-08', 'working_days' => [0, 1, 2], 'rest_days' => [5],
        ])->assertOk()->assertJsonPath('data.has_day_overrides', true);
        $week = $this->employeeSchedule($employee, '2026-03-08', '2026-03-14');
        $this->assertSame('working', $week['2026-03-10']['status'], 'Tuesday');
        $this->assertSame('off', $week['2026-03-11']['status'], 'Wednesday is neither a working nor a rest day');
        $this->assertSame('rest', $week['2026-03-13']['status']);
    }

    public function test_overnight_shift_ends_next_day_and_punches_belong_to_the_start_day(): void
    {
        $night = $this->createShift([
            'code' => 'NIGHT', 'start_time' => '22:00', 'end_time' => '06:00', 'break_start_time' => '02:00', 'break_minutes' => 30,
        ]);
        $this->getJson("/api/hr/shifts/{$night}")
            ->assertJsonPath('data.shift_type', 'night')
            ->assertJsonPath('data.is_overnight', true)
            ->assertJsonPath('data.span_minutes', 480);

        $employeeId = $this->createEmployee($this->makeUser('night@example.test', 'teacher'));
        $this->postJson("/api/hr/employees/{$employeeId}/shifts", ['shift_id' => $night, 'effective_from' => '2026-03-01'])->assertOk();

        $day = $this->employeeSchedule($employeeId, '2026-03-01', '2026-03-01')['2026-03-01'];
        $this->assertTrue($day['is_overnight']);
        $this->assertSame('2026-03-01T22:00:00+03:00', $day['start_at']);
        $this->assertSame('2026-03-02T06:00:00+03:00', $day['end_at']);
        $this->assertSame('02:00', $day['break_start']);
        $this->assertSame(450, $day['expected_minutes']);

        $employee = Employee::query()->findOrFail($employeeId);
        $checkout = HrShiftSchedule::attributeTimestamp($employee, Carbon::parse('2026-03-02 06:10', 'Asia/Riyadh'));
        $this->assertSame('2026-03-01', $checkout->date, 'the morning check-out closes the previous night');
        $checkin = HrShiftSchedule::attributeTimestamp($employee, Carbon::parse('2026-03-02 21:50', 'Asia/Riyadh'));
        $this->assertSame('2026-03-02', $checkin->date);

        $result = HrShiftCalculator::evaluate(
            $checkout,
            Carbon::parse('2026-03-01 22:20', 'Asia/Riyadh'),
            Carbon::parse('2026-03-02 05:30', 'Asia/Riyadh'),
        );
        $this->assertSame(20, $result['late_minutes']);
        $this->assertSame(30, $result['early_departure_minutes']);
        $this->assertSame(400, $result['worked_minutes'], '430 minutes in the shift minus the 30 minute break window');
    }

    public function test_rotating_schedule_cycles_per_employee_offset(): void
    {
        $am = $this->createShift(['code' => 'AM', 'start_time' => '08:00', 'end_time' => '16:00', 'color' => '#2E7D32']);
        $pm = $this->createShift(['code' => 'PM', 'start_time' => '16:00', 'end_time' => '00:00']);

        $this->postJson('/api/hr/shifts', $this->rotating([$am, $pm]))
            ->assertStatus(422)->assertJsonPath('reason', 'shift_rest_day_required');
        $rotation = (int) $this->postJson('/api/hr/shifts', $this->rotating([$am, $am, $pm, $pm, null, null]))
            ->assertOk()
            ->assertJsonPath('data.is_rotating', true)
            ->assertJsonPath('data.cycle_length', 6)
            ->assertJsonPath('data.start_time', null)
            ->assertJsonPath('data.rotation_slots.4.is_rest', true)
            ->json('data.id');
        $this->postJson('/api/hr/shifts', $this->rotating([$rotation, null], 'ROT2'))
            ->assertStatus(422)->assertJsonPath('reason', 'shift_rotation_invalid');

        $first = $this->createEmployee($this->makeUser('rot1@example.test', 'teacher'));
        $second = $this->createEmployee($this->makeUser('rot2@example.test', 'teacher'));

        $this->postJson("/api/hr/shifts/{$rotation}/assign", ['employee_ids' => [$first], 'effective_from' => '2026-03-01'])
            ->assertOk()->assertJsonPath('data.assigned', 1);
        $this->postJson("/api/hr/employees/{$second}/shifts", ['shift_id' => $rotation, 'effective_from' => '2026-03-01', 'rotation_offset' => 6])
            ->assertStatus(422)->assertJsonPath('reason', 'rotation_offset_invalid');
        $this->postJson("/api/hr/employees/{$second}/shifts", ['shift_id' => $rotation, 'effective_from' => '2026-03-01', 'working_days' => [1]])
            ->assertStatus(422)->assertJsonPath('reason', 'shift_override_not_allowed');
        $this->postJson("/api/hr/employees/{$second}/shifts", ['shift_id' => $rotation, 'effective_from' => '2026-03-01', 'rotation_offset' => 2])
            ->assertOk();

        $codes = fn (int $employee) => array_map(
            fn ($day) => $day['status'] === 'working' ? $day['shift_code'] : $day['status'],
            array_values($this->employeeSchedule($employee, '2026-03-01', '2026-03-08'))
        );
        $this->assertSame(['AM', 'AM', 'PM', 'PM', 'rest', 'rest', 'AM', 'AM'], $codes($first));
        $this->assertSame(['PM', 'PM', 'rest', 'rest', 'AM', 'AM', 'PM', 'PM'], $codes($second));

        $day = $this->employeeSchedule($first, '2026-03-03', '2026-03-03')['2026-03-03'];
        $this->assertSame($rotation, $day['assigned_shift_id']);
        $this->assertSame(3, $day['rotation_day']);
        $this->assertSame('2026-03-04T00:00:00+03:00', $day['end_at'], 'PM ends at midnight, on the next day');

        $grid = $this->getJson('/api/hr/shift-schedule?from=2026-03-01&to=2026-03-07')->assertOk();
        $this->assertSame(2, $grid->json('data.total'));
        $this->assertCount(7, $grid->json('data.dates'));
        $this->assertCount(7, $grid->json('data.rows.0.days'));
        $this->assertEqualsCanonicalizing(['AM', 'PM'], array_column($grid->json('data.legend'), 'code'));
        $this->getJson('/api/hr/shift-schedule?from=2026-03-01&to=2026-05-01')
            ->assertStatus(422)->assertJsonPath('reason', 'schedule_range_invalid');

        $this->patchJson("/api/hr/shifts/{$am}/deactivate")->assertStatus(422)->assertJsonPath('reason', 'shift_used_in_rotation');
    }

    public function test_effective_dated_shift_assignment_changes(): void
    {
        $am = $this->createShift(['code' => 'AM', 'start_time' => '08:00', 'end_time' => '16:00']);
        $pm = $this->createShift(['code' => 'PM', 'start_time' => '14:00', 'end_time' => '22:00']);
        $night = $this->createShift(['code' => 'NT', 'start_time' => '22:00', 'end_time' => '06:00']);
        $employee = $this->createEmployee($this->makeUser('change@example.test', 'teacher'));

        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $am, 'effective_from' => '2026-03-01'])->assertOk();
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $am, 'effective_from' => '2026-03-03'])
            ->assertStatus(422)->assertJsonPath('reason', 'shift_assignment_unchanged');
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $pm, 'effective_from' => '2026-03-04', 'reason' => 'Team change'])->assertOk();

        $days = $this->employeeSchedule($employee, '2026-03-03', '2026-03-04');
        $this->assertSame('AM', $days['2026-03-03']['shift_code']);
        $this->assertSame('PM', $days['2026-03-04']['shift_code']);

        // Same-day correction replaces the scheduled row instead of stacking another one.
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $night, 'effective_from' => '2026-03-04'])->assertOk();
        $this->assertSame(2, DB::table('hr_employee_shift_assignments')->where('employee_id', $employee)->count());
        $this->assertSame('NT', $this->employeeSchedule($employee, '2026-03-04', '2026-03-04')['2026-03-04']['shift_code']);
        $this->getJson("/api/hr/employees/{$employee}")->assertJsonPath('data.attendance.next_shift.shift_code', 'NT');

        $history = $this->getJson("/api/hr/employees/{$employee}/shifts")->assertOk()->json('data');
        $this->assertSame('2026-03-03', $history[1]['effective_to'], 'previous row closes the day before');

        HrSettings::update(self::CENTER, ['payroll' => ['changes_locked_until' => '2026-03-10']], $this->hrManager);
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $am, 'effective_from' => '2026-03-08'])
            ->assertStatus(422)->assertJsonPath('reason', 'effective_date_locked');
    }

    public function test_shift_timing_changes_are_effective_dated(): void
    {
        $shift = $this->createShift(['code' => 'AM', 'start_time' => '08:00', 'end_time' => '16:00']);

        // Not used yet: corrected in place.
        $this->putJson("/api/hr/shifts/{$shift}", ['start_time' => '07:30'])->assertOk();
        $this->assertSame(1, DB::table('hr_shift_versions')->where('shift_id', $shift)->count());

        $employee = $this->createEmployee($this->makeUser('timing@example.test', 'teacher'));
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $shift, 'effective_from' => '2026-02-01'])->assertOk();

        $this->putJson("/api/hr/shifts/{$shift}", ['start_time' => '09:00'])
            ->assertStatus(422)->assertJsonPath('reason', 'shift_change_effective_date_required');
        $this->putJson("/api/hr/shifts/{$shift}", ['name_en' => 'Morning'])->assertOk();

        $this->putJson("/api/hr/shifts/{$shift}", ['start_time' => '09:00', 'effective_from' => '2026-03-10', 'reason' => 'Ramadan'])
            ->assertOk()
            ->assertJsonPath('data.start_time', '07:30')
            ->assertJsonPath('data.scheduled_version.start_time', '09:00')
            ->assertJsonPath('data.scheduled_version.effective_from', '2026-03-10');

        $days = $this->employeeSchedule($employee, '2026-02-01', '2026-03-10');
        $this->assertSame('07:30', $days['2026-02-01']['start_time'], 'dates before the first version use it');
        $this->assertSame('07:30', $days['2026-03-09']['start_time']);
        $this->assertSame('09:00', $days['2026-03-10']['start_time']);

        $versions = $this->getJson("/api/hr/shifts/{$shift}/versions")->assertOk()->json('data');
        $this->assertCount(2, $versions);
        $this->assertTrue($versions[0]['is_scheduled']);

        $this->postJson("/api/hr/shifts/{$shift}/versions/{$versions[0]['id']}/cancel")->assertOk();
        $this->assertSame('07:30', $this->employeeSchedule($employee, '2026-03-10', '2026-03-10')['2026-03-10']['start_time']);

        $this->putJson("/api/hr/shifts/{$shift}", ['start_time' => '09:00', 'effective_from' => '2026-03-10'])->assertOk();
        $this->travelTo(Carbon::parse('2026-03-10 00:10', 'Asia/Riyadh'));
        $this->artisan('hr:apply-shift-versions')->assertSuccessful();
        $this->getJson("/api/hr/shifts/{$shift}")->assertJsonPath('data.start_time', '09:00');
        $this->assertSame('07:30', $this->employeeSchedule($employee, '2026-03-09', '2026-03-09')['2026-03-09']['start_time'], 'history kept');
    }

    public function test_exceptions_and_temporary_changes_follow_scope_precedence(): void
    {
        $am = $this->createShift(['code' => 'AM', 'start_time' => '08:00', 'end_time' => '16:00']);
        $pm = $this->createShift(['code' => 'PM', 'start_time' => '14:00', 'end_time' => '22:00']);
        $employee = $this->createEmployee($this->makeUser('exc@example.test', 'teacher'));
        $this->postJson("/api/hr/employees/{$employee}/shifts", ['shift_id' => $am, 'effective_from' => '2026-03-01'])->assertOk();

        $this->postJson('/api/hr/shift-exceptions', [
            'scope' => 'center', 'exception_type' => 'day_off', 'date_from' => '2026-03-02', 'date_to' => '2026-03-02', 'reason' => 'Founding day',
        ])->assertOk();
        $this->postJson('/api/hr/shift-exceptions', [
            'scope' => 'branch', 'branch_id' => $this->defaultBranchId(), 'exception_type' => 'custom_hours',
            'date_from' => '2026-03-02', 'date_to' => '2026-03-03', 'start_time' => '10:00', 'end_time' => '14:00',
        ])->assertOk();
        $employeeException = $this->postJson('/api/hr/shift-exceptions', [
            'scope' => 'employee', 'employee_id' => $employee, 'exception_type' => 'shift', 'shift_id' => $pm,
            'date_from' => '2026-03-03', 'date_to' => '2026-03-03',
        ])->assertOk()->json('data.id');
        $this->postJson('/api/hr/shift-exceptions', [
            'scope' => 'employee', 'employee_id' => $employee, 'exception_type' => 'shift', 'shift_id' => $am,
            'date_from' => '2026-03-06', 'date_to' => '2026-03-06', 'reason' => 'Covering Friday',
        ])->assertOk();
        $this->postJson('/api/hr/shift-exceptions', [
            'scope' => 'center', 'exception_type' => 'day_off', 'date_from' => '2026-03-01', 'date_to' => '2026-03-02',
        ])->assertStatus(422)->assertJsonPath('reason', 'shift_exception_overlap');

        $days = $this->employeeSchedule($employee, '2026-03-02', '2026-03-06');
        $this->assertSame(['working', 'exception', 'custom_hours'], [$days['2026-03-02']['status'], $days['2026-03-02']['source'], $days['2026-03-02']['exception_type']], 'branch beats center');
        $this->assertSame('10:00', $days['2026-03-02']['start_time']);
        $this->assertSame(240, $days['2026-03-02']['expected_minutes']);
        $this->assertSame('PM', $days['2026-03-03']['shift_code'], 'employee beats branch');
        $this->assertSame('AM', $days['2026-03-04']['shift_code']);
        $this->assertSame('working', $days['2026-03-06']['status'], 'work on a rest day');

        $other = $this->createEmployee($this->makeUser('exc2@example.test', 'teacher'), [
            'branch_id' => $this->createBranch(['code' => 'JED', 'name_ar' => 'جدة']),
        ]);
        $this->postJson("/api/hr/employees/{$other}/shifts", ['shift_id' => $am, 'effective_from' => '2026-03-01'])->assertOk();
        $this->assertSame('off', $this->employeeSchedule($other, '2026-03-02', '2026-03-02')['2026-03-02']['status'], 'center-wide day off');

        $this->postJson("/api/hr/shift-exceptions/{$employeeException}/cancel")->assertOk();
        $this->assertSame('custom_hours', $this->employeeSchedule($employee, '2026-03-03', '2026-03-03')['2026-03-03']['exception_type']);

        $list = $this->getJson('/api/hr/shift-exceptions?period=upcoming')->assertOk();
        $this->assertSame(3, $list->json('total'));
        $this->deleteJson("/api/hr/shifts/{$pm}")->assertOk();
    }

    public function test_activation_guards_branch_default_and_permissions(): void
    {
        $branch = $this->defaultBranchId();
        $shift = $this->createShift(['code' => 'AM', 'start_time' => '08:00', 'end_time' => '16:00']);
        $unused = $this->createShift(['code' => 'SPARE', 'start_time' => '09:00', 'end_time' => '15:00']);

        $this->putJson("/api/hr/shifts/{$unused}", ['is_active' => false])->assertOk()->assertJsonPath('data.is_active', true);
        $this->patchJson("/api/hr/shifts/{$unused}/deactivate")->assertOk()->assertJsonPath('data.is_active', false);
        $this->patchJson("/api/hr/shifts/{$unused}/activate")->assertOk()->assertJsonPath('data.is_active', true);

        $this->putJson("/api/hr/branches/{$branch}", ['default_shift_id' => $shift])->assertOk()->assertJsonPath('data.default_shift.code', 'AM');
        $hired = $this->createEmployee($this->makeUser('newhire@example.test', 'teacher'));
        $this->getJson("/api/hr/employees/{$hired}")
            ->assertJsonPath('data.attendance.current_shift.shift_code', 'AM')
            ->assertJsonPath('data.attendance.today.status', 'working');

        $this->patchJson("/api/hr/shifts/{$shift}/deactivate")->assertStatus(422)->assertJsonPath('reason', 'shift_in_use');
        $this->postJson("/api/hr/employees/{$hired}/shifts", ['shift_id' => $unused, 'effective_from' => '2026-03-02'])->assertOk();
        $this->travelTo(Carbon::parse('2026-03-02 10:00', 'Asia/Riyadh'));
        DB::table('hr_employee_shift_assignments')->where('shift_id', $shift)->update(['effective_to' => '2026-03-01']);
        $this->patchJson("/api/hr/shifts/{$shift}/deactivate")->assertStatus(422)->assertJsonPath('reason', 'shift_is_branch_default');

        $rotation = (int) $this->postJson('/api/hr/shifts', $this->rotating([$shift, null]))->assertOk()->json('data.id');
        $this->putJson("/api/hr/branches/{$branch}", ['default_shift_id' => $rotation])
            ->assertStatus(422)->assertJsonPath('reason', 'shift_rotating_not_allowed');

        // Branch shifts can only be worked in that branch.
        $jeddah = $this->createBranch(['code' => 'JED', 'name_ar' => 'جدة']);
        $jeddahShift = $this->createShift(['code' => 'JED-AM', 'start_time' => '07:00', 'end_time' => '15:00', 'branch_id' => $jeddah]);
        $this->postJson("/api/hr/employees/{$hired}/shifts", ['shift_id' => $jeddahShift, 'effective_from' => '2026-03-05'])
            ->assertStatus(422)->assertJsonPath('reason', 'shift_branch_mismatch');

        $editor = $this->makeUser('editor@example.test', 'supervisor', self::CENTER, [
            'access_hr-attendance-setup', 'edit_hr-attendance-setup', 'access_hr-all-branches',
        ]);
        $this->actingAsUser($editor)->patchJson("/api/hr/shifts/{$unused}/deactivate")->assertForbidden();
        $this->actingAsUser($editor)->postJson("/api/hr/shifts/{$unused}/assign", ['employee_ids' => [$hired], 'effective_from' => '2026-03-09'])
            ->assertForbidden();
    }

    public function test_shift_validation_rules(): void
    {
        $base = ['code' => 'X', 'name_ar' => 'اختبار', 'start_time' => '08:00', 'end_time' => '16:00', 'working_days' => [0, 1, 2, 3, 4]];

        $this->actingAsUser($this->hrManager);
        $this->postJson('/api/hr/shifts', ['rest_days' => [4, 5]] + $base)->assertStatus(422)->assertJsonPath('reason', 'shift_days_overlap');
        $this->postJson('/api/hr/shifts', ['working_days' => [0, 1, 2, 3, 4, 5, 6]] + $base)
            ->assertStatus(422)->assertJsonPath('reason', 'shift_rest_day_required');
        $this->postJson('/api/hr/shifts', ['break_minutes' => 480] + $base)->assertStatus(422)->assertJsonPath('reason', 'shift_break_too_long');
        $this->postJson('/api/hr/shifts', ['break_minutes' => 30, 'break_start_time' => '15:45'] + $base)
            ->assertStatus(422)->assertJsonPath('reason', 'shift_break_outside');
        $this->postJson('/api/hr/shifts', ['expected_minutes' => 600] + $base)->assertStatus(422)->assertJsonPath('reason', 'shift_expected_too_long');
        $this->postJson('/api/hr/shifts', ['end_time' => '08:00'] + $base)->assertStatus(422)->assertJsonValidationErrors('end_time');
        $this->postJson('/api/hr/shifts', ['shift_type' => 'rotating', 'code' => 'R', 'name_ar' => 'r'])->assertStatus(422)->assertJsonValidationErrors('rotation_pattern');

        $this->postJson('/api/hr/shifts', ['shift_type' => 'evening', 'grace_minutes' => 10, 'early_leave_grace_minutes' => 5, 'color' => '#1565C0'] + $base)
            ->assertOk()
            ->assertJsonPath('data.shift_type', 'evening')
            ->assertJsonPath('data.grace_minutes', 10)
            ->assertJsonPath('data.early_leave_grace_minutes', 5);

        $lookups = $this->getJson('/api/hr/lookups')->assertOk();
        $this->assertSame(['morning', 'evening', 'night', 'rotating', 'custom'], array_column($lookups->json('data.shift_types'), 'value'));
        $this->assertSame('#1565C0', $lookups->json('data.shifts.0.color'));
    }

    private function createShift(array $overrides): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/shifts', $overrides + ['name_ar' => 'وردية', 'working_days' => [0, 1, 2, 3, 4]])
            ->assertOk()
            ->json('data.id');
    }

    private function rotating(array $pattern, string $code = 'ROT'): array
    {
        return ['code' => $code, 'name_ar' => 'تناوب', 'shift_type' => 'rotating', 'rotation_pattern' => $pattern];
    }

    /** @return array<string, array> date => day */
    private function employeeSchedule(int $employee, string $from, string $to): array
    {
        $days = $this->actingAsUser($this->hrManager)
            ->getJson("/api/hr/employees/{$employee}/schedule?from={$from}&to={$to}")
            ->assertOk()
            ->json('data.days');

        return array_column($days, null, 'date');
    }
}
