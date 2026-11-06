<?php

namespace Tests\Unit;

use App\Models\Hr\EmployeeChange;
use App\Models\Hr\EmployeeInsurance;
use App\Models\Hr\EmployeeSalary;
use App\Support\Hr\HrEffectiveDating;
use App\Support\Hr\SaudiIdentity;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class HrEmployeeRulesTest extends TestCase
{
    public function test_saudi_id_checksum_and_prefix(): void
    {
        $this->assertTrue(SaudiIdentity::hasValidChecksum('1000000008'));
        $this->assertTrue(SaudiIdentity::hasValidChecksum('2000000006'));
        $this->assertFalse(SaudiIdentity::hasValidChecksum('1000000001'), 'wrong check digit');
        $this->assertFalse(SaudiIdentity::hasValidChecksum('3000000004'), 'only 1 (citizen) and 2 (resident) prefixes exist');
        $this->assertFalse(SaudiIdentity::hasValidChecksum('100000008'), 'nine digits');
        $this->assertFalse(SaudiIdentity::hasValidChecksum('10000000a8'));

        $this->assertSame('1000000008', SaudiIdentity::normalize(' ١٠٠٠٠٠٠٠٠٨ '));
        $this->assertSame(SaudiIdentity::TYPE_IQAMA, SaudiIdentity::type('2000000006'));
    }

    public function test_id_must_match_nationality(): void
    {
        $this->assertNull(SaudiIdentity::validationError('1000000008', 'SA'));
        $this->assertNull(SaudiIdentity::validationError('2000000006', 'EG'));
        $this->assertNull(SaudiIdentity::validationError('2000000006'), 'without a nationality only the format is checked');
        $this->assertSame('id_number_nationality_mismatch', SaudiIdentity::validationError('2000000006', 'sa'));
        $this->assertSame('id_number_nationality_mismatch', SaudiIdentity::validationError('1000000008', 'IN'));
        $this->assertSame('id_number_invalid', SaudiIdentity::validationError('1000000001', 'SA'));
        $this->assertSame('id_number_invalid', SaudiIdentity::validationError(null, 'SA'));
    }

    public function test_effective_dates_must_move_forward(): void
    {
        $latest = Carbon::parse('2026-03-01');

        $this->assertNull(HrEffectiveDating::orderingError(null, Carbon::parse('2020-01-01')));
        $this->assertNull(HrEffectiveDating::orderingError($latest, Carbon::parse('2026-03-02')));
        $this->assertSame('effective_date_not_after_latest', HrEffectiveDating::orderingError($latest, Carbon::parse('2026-03-01')));
        $this->assertSame('effective_date_not_after_latest', HrEffectiveDating::orderingError($latest, Carbon::parse('2026-02-01')));
    }

    public function test_insurance_category_follows_nationality(): void
    {
        $this->assertTrue(EmployeeInsurance::categoryMatchesNationality('saudi', 'SA'));
        $this->assertFalse(EmployeeInsurance::categoryMatchesNationality('saudi', 'EG'));
        $this->assertTrue(EmployeeInsurance::categoryMatchesNationality('gcc', 'KW'));
        $this->assertFalse(EmployeeInsurance::categoryMatchesNationality('non_saudi', 'BH'), 'GCC nationals use the GCC category');
        $this->assertTrue(EmployeeInsurance::categoryMatchesNationality('non_saudi', 'IN'));
        $this->assertTrue(EmployeeInsurance::categoryMatchesNationality('exempt', 'SA'));
    }

    public function test_gross_salary_sums_components_without_float_drift(): void
    {
        $salary = new EmployeeSalary([
            'basic_salary' => '0.10', 'housing_allowance' => '0.20', 'transportation_allowance' => '0.00', 'other_allowances' => '1234.56',
        ]);

        $this->assertSame('1234.86', $salary->grossSalary());
    }

    public function test_change_display_prefers_the_frozen_label_in_the_current_locale(): void
    {
        $change = new EmployeeChange([
            'change_type' => 'branch', 'old_value' => '3', 'new_value' => '4',
            'new_label' => json_encode(['ar' => 'جدة', 'en' => 'Jeddah'], JSON_UNESCAPED_UNICODE),
        ]);

        app()->setLocale('ar');
        $this->assertSame('جدة', $change->display('new'));
        app()->setLocale('en');
        $this->assertSame('Jeddah', $change->display('new'));
        $this->assertSame('3', $change->display('old'));

        $status = new EmployeeChange(['change_type' => 'attendance_method', 'new_value' => 'electronic']);
        $this->assertSame('Electronic', $status->display('new'));
    }
}
