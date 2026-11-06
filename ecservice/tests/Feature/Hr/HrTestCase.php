<?php

namespace Tests\Feature\Hr;

use App\Models\User;
use App\Support\Hr\HrSettings;
use App\Support\PermissionCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * In-memory SQLite with the minimal platform tables HR depends on, plus the real HR migrations.
 * The local MySQL database is never touched.
 */
abstract class HrTestCase extends TestCase
{
    protected const CENTER = 1;

    protected const OTHER_CENTER = 2;

    protected const HR_MIGRATIONS = [
        'database/migrations/2026_11_05_100000_create_hr_organization_tables.php',
        'database/migrations/2026_11_05_100100_create_hr_employees_table.php',
        'database/migrations/2026_11_05_100200_create_hr_settings_table.php',
        'database/migrations/2026_11_05_100300_create_hr_approval_tables.php',
        'database/migrations/2026_11_05_100400_create_hr_audit_logs_table.php',
        'database/migrations/2026_11_05_130000_create_hr_attendance_setup_tables.php',
        'database/migrations/2026_11_05_130100_extend_hr_employees_for_employee_management.php',
        'database/migrations/2026_11_05_130200_create_hr_employee_records_tables.php',
        'database/migrations/2026_11_05_140000_create_hr_employee_work_assignments_table.php',
        'database/migrations/2026_11_05_150000_extend_hr_shifts_for_shift_management.php',
        'database/migrations/2026_11_05_160000_create_hr_attendance_configuration_tables.php',
        'database/migrations/2026_11_05_170000_create_hr_attendance_records_tables.php',
        'database/migrations/2026_11_05_180000_create_hr_location_monitoring_tables.php',
        'database/migrations/2026_11_05_190000_add_manual_attendance_to_hr_attendance_records.php',
        'database/migrations/2026_11_06_100000_create_hr_holidays_table.php',
        'database/migrations/2026_11_06_100100_create_hr_leave_tables.php',
        'database/migrations/2026_11_06_100200_create_hr_attendance_processing_tables.php',
        'database/migrations/2026_11_06_110000_create_hr_additional_work_days_table.php',
    ];

    /** Has every HR permission. */
    protected User $hrManager;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is required for HR API tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createPlatformSchema();
        $this->artisan('migrate', ['--path' => self::HR_MIGRATIONS])->assertSuccessful();

        DB::table('centers')->insert([
            ['id' => self::CENTER, 'title' => 'Center A'],
            ['id' => self::OTHER_CENTER, 'title' => 'Center B'],
        ]);
        foreach (PermissionCatalog::definitions() as $name => $definition) {
            if (str_contains($name, '_hr-')) {
                DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web']);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        HrSettings::forget();

        $this->hrManager = $this->makeUser('hr@example.test', 'hr_manager', self::CENTER, $this->hrPermissions());
    }

    protected function hrPermissions(): array
    {
        return DB::table('permissions')->pluck('name')->filter(fn ($name) => str_contains($name, '_hr-'))->values()->all();
    }

    protected function makeUser(string $email, string $role, int $centerId = self::CENTER, array $permissions = [], array $attributes = []): User
    {
        $userId = (int) DB::table('users')->insertGetId($attributes + [
            'name' => $email, 'email' => $email, 'password' => 'x', 'status' => 1, 'search_text' => "#{$email}",
        ]);
        DB::table('centers_users')->insert(['user_id' => $userId, 'center_id' => $centerId]);
        $roleId = DB::table('roles')->where('default_name', $role)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => ucfirst($role), 'default_name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $userId]);

        $user = User::query()->findOrFail($userId);
        if ($permissions !== []) {
            $user->givePermissionTo($permissions);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    protected function actingAsUser(User $user): static
    {
        Sanctum::actingAs($user->fresh());

        return $this;
    }

    protected function createBranch(array $overrides = []): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/branches', $overrides + ['code' => 'RUH', 'name_ar' => 'فرع الرياض', 'name_en' => 'Riyadh'])
            ->assertOk()
            ->json('data.id');
    }

    protected function createEmployee(User $user, array $overrides = []): int
    {
        return (int) $this->actingAsUser($this->hrManager)
            ->postJson('/api/hr/employees', $overrides + $this->employeePayload($user))
            ->assertOk()
            ->json('data.id');
    }

    /** Complete, valid create payload linking an existing staff user. */
    protected function employeePayload(User $user, bool $saudi = true): array
    {
        return [
            'mode' => 'existing',
            'user_id' => $user->id,
            'name' => $user->name,
            'phone' => '05'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT),
            'nationality' => $saudi ? 'SA' : 'EG',
            'gender' => 1,
            'birthdate' => '1990-05-01',
            'id_or_residence_number' => self::validIdNumber($saudi ? '1' : '2', $user->id),
            'job_title' => 'Teacher',
            'branch_id' => $this->defaultBranchId(),
            'department_id' => $this->defaultDepartmentId(),
            'hire_date' => '2025-01-01',
            'employment_start_date' => '2025-01-01',
            'attendance_mode' => 'manual',
        ];
    }

    /** National ID (prefix 1) or Iqama (prefix 2) with a correct check digit. */
    protected static function validIdNumber(string $prefix, int $sequence): string
    {
        $body = $prefix.str_pad((string) $sequence, 8, '0', STR_PAD_LEFT);
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $digit = (int) $body[$i];
            if ($i % 2 === 0) {
                $doubled = $digit * 2;
                $sum += intdiv($doubled, 10) + ($doubled % 10);
            } else {
                $sum += $digit;
            }
        }

        return $body.((10 - $sum % 10) % 10);
    }

    protected function defaultBranchId(): int
    {
        return (int) (DB::table('hr_branches')->where('center_id', self::CENTER)->whereNull('deleted_at')->orderBy('id')->value('id')
            ?? $this->createBranch(['code' => 'HQ', 'name_ar' => 'المقر الرئيسي', 'name_en' => 'Head office']));
    }

    protected function defaultDepartmentId(): int
    {
        return (int) (DB::table('hr_departments')->where('center_id', self::CENTER)->whereNull('deleted_at')->orderBy('id')->value('id')
            ?? $this->actingAsUser($this->hrManager)
                ->postJson('/api/hr/departments', ['code' => 'GEN', 'name_ar' => 'عام', 'name_en' => 'General'])
                ->assertOk()
                ->json('data.id'));
    }

    private function createPlatformSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->string('nationality', 3)->nullable();
            $table->string('id_or_residence_number')->nullable();
            $table->date('birthdate')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            foreach (['address_city', 'address_area', 'address_street', 'address_building', 'address_number', 'address_unit', 'address_zipcode'] as $column) {
                $table->string($column)->nullable();
            }
            $table->string('job_title')->nullable();
            $table->string('department')->nullable();
            $table->string('contract_type')->nullable();
            $table->date('hire_date')->nullable();
            $table->date('contract_end_date')->nullable();
            $table->date('id_expiry_date')->nullable();
            $table->text('search_text')->nullable();
            $table->unsignedBigInteger('merged_into_user_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('centers', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('centers_users', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('center_id');
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('default_name')->nullable();
            $table->string('guard_name');
            $table->timestamps();
        });
        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });
        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }
}
