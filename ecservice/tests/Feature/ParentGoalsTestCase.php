<?php

namespace Tests\Feature;

use App\Models\System\System;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Shared fixture for parent goal visibility HTTP tests: an isolated in-memory SQLite database
 * (the local MySQL database is never used) and a temporary local storage root for media files.
 */
abstract class ParentGoalsTestCase extends TestCase
{
    protected const CENTER = 1;

    protected const OTHER_CENTER = 2;

    protected User $parent;

    protected User $staff;

    protected User $admin;

    protected int $currentTerm;

    protected int $pastTerm;

    protected string $storageRoot;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is required for parent goal visibility tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'motabaa.files.storage' => 'local',
            'motabaa.bunny.url' => '',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->storageRoot = sys_get_temp_dir().DIRECTORY_SEPARATOR.'parent-goals-'.uniqid();
        File::ensureDirectoryExists($this->storageRoot.'/app/public');
        $this->app->useStoragePath($this->storageRoot);
        config(['filesystems.disks.local.root' => $this->storageRoot.'/app']);
        Storage::forgetDisk('local');
        unset($GLOBALS['__file_request_named'], $GLOBALS['__file_request_listings']);

        $this->createSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('centers')->insert([
            ['id' => self::CENTER, 'title' => 'Center A'],
            ['id' => self::OTHER_CENTER, 'title' => 'Center B'],
        ]);
        foreach (['access_treatment-goals', 'access_education-goals'] as $permission) {
            DB::table('permissions')->insert(['name' => $permission, 'guard_name' => 'web']);
        }
        $this->currentTerm = $this->makeTerm(now()->subMonths(2), now()->addMonths(4));
        $this->pastTerm = $this->makeTerm(now()->subYear(), now()->subMonths(3));

        $this->parent = $this->makeUser('parent@example.test', 'parent');
        $this->staff = $this->makeUser('manager@example.test', 'manager');
        $this->admin = $this->makeUser('admin@example.test', 'admin');
        $this->parent->givePermissionTo(['access_treatment-goals', 'access_education-goals']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->parent = $this->parent->fresh();
    }

    protected function tearDown(): void
    {
        if (isset($this->storageRoot)) {
            File::deleteDirectory($this->storageRoot);
        }
        unset($GLOBALS['__file_request_named'], $GLOBALS['__file_request_listings']);

        parent::tearDown();
    }

    /**
     * Goal ids returned to the parent by `GET /api/goals`.
     *
     * @param  array<string, mixed>  $extra
     * @return array<int, int>
     */
    protected function parentGoals(int $caseId, string $category, array $extra = []): array
    {
        return array_column($this->parentGoalsResponse($caseId, $category, $extra)->json('data'), 'id');
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function parentGoalsResponse(int $caseId, string $category, array $extra = [])
    {
        Sanctum::actingAs($this->parent);

        return $this->getJson('/api/goals?'.http_build_query(['case_id' => $caseId, 'category' => $category] + $extra))
            ->assertOk();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function staffGoalsResponse(int $caseId, string $category, array $extra = [], ?User $as = null)
    {
        Sanctum::actingAs($as ?? $this->staff);

        return $this->getJson('/api/goals?'.http_build_query([
            'case_id' => $caseId, 'category' => $category, 'center_id' => self::CENTER, 'status' => 'active',
        ] + $extra))->assertOk();
    }

    protected function parentGoalDetails(int $caseId, int $goalId)
    {
        Sanctum::actingAs($this->parent);

        return $this->getJson("/api/goals/parent-details/{$goalId}?case_id={$caseId}");
    }

    /**
     * Case A (Support Services): physiotherapy session with an image; two speech therapy goals,
     * one with a video session and one whose sessions are text only; psychiatric treatment whose
     * only session is staff-only; an occupational therapy goal left in a past term.
     *
     * @return array<string, int>
     */
    protected function hajarBeneficiary(): array
    {
        $case = $this->makeCase('هاجر محمد العقيدي', self::CENTER, linkParent: true);

        return [
            'id' => $case,
            'physio_goal' => $this->makeGoal($case, 'physical therapy', media: 'image'),
            'speech_goal' => $this->makeGoal($case, 'pronouncement', media: 'legacy_video'),
            'speech_text_goal' => $this->makeGoal($case, 'pronouncement', media: 'text_only'),
            'past_ot_goal' => $this->makeGoal($case, 'occupational therapy', media: 'image', termId: $this->pastTerm),
            'psych_goal' => $this->makeGoal($case, 'psychiatric treatment', media: 'image', published: false),
        ];
    }

    /**
     * Case B (Individual Plan): 30 current-term goals as staff see them, grouped by what their
     * sessions contain. A past-term goal and a deleted goal are outside the staff total.
     *
     * @return array<string, mixed>
     */
    protected function faisalBeneficiary(): array
    {
        $case = $this->makeCase('فيصل سعد الدوسري', self::CENTER, linkParent: true);
        $kinds = ['image', 'file', 'video'];
        $goal = fn (string $media, bool $published = true, bool $started = true) => $this->makeGoal(
            $case, 'educational', media: $media, published: $published, started: $started
        );

        $groups = [
            'attachment_recorded' => [],
            'attachment_before_path_columns' => [],
            'text_only_sessions' => [$goal('text_only'), $goal('text_only')],
            'attachment_file_missing' => [$goal('legacy_without_file'), $goal('legacy_without_file')],
            'staff_only_sessions' => [$goal('image', false), $goal('legacy_video', false), $goal('file', false)],
            'no_sessions' => [$goal('none')],
            'not_started' => [$goal('image', true, false), $goal('legacy_image', true, false)],
        ];
        for ($i = 0; $i < 7; $i++) {
            $groups['attachment_recorded'][] = $goal($kinds[$i % 3]);
        }
        for ($i = 0; $i < 13; $i++) {
            $groups['attachment_before_path_columns'][] = $goal('legacy_'.$kinds[$i % 3]);
        }

        $current = array_merge(...array_values($groups));
        sort($current);

        return [
            'id' => $case,
            'groups' => $groups,
            'current_term' => $current,
            'past_term' => $this->makeGoal($case, 'educational', media: 'image', termId: $this->pastTerm),
            'deleted' => $this->makeGoal($case, 'educational', media: 'image', deleted: true),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, int>
     */
    protected function sortedIds(array $rows): array
    {
        $ids = array_map('intval', array_column($rows, 'id'));
        sort($ids);

        return $ids;
    }

    protected function makeTerm($startsAt, $endsAt, int $centerId = self::CENTER): int
    {
        return (int) DB::table('terms')->insertGetId([
            'center_id' => $centerId,
            'title' => 'Term '.$startsAt->format('Y-m'),
            'starts_at' => $startsAt->toDateString(),
            'ends_at' => $endsAt->toDateString(),
        ]);
    }

    protected function makeUser(string $email, string $role): User
    {
        $userId = (int) DB::table('users')->insertGetId([
            'name' => $email, 'email' => $email, 'password' => 'x', 'status' => 1,
        ]);
        DB::table('centers_users')->insert(['user_id' => $userId, 'center_id' => self::CENTER]);
        $roleId = DB::table('roles')->where('default_name', $role)->value('id')
            ?? DB::table('roles')->insertGetId(['name' => ucfirst($role), 'default_name' => $role, 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $roleId, 'model_type' => User::class, 'model_id' => $userId]);

        return User::query()->findOrFail($userId);
    }

    protected function makeCase(string $name, int $centerId, bool $linkParent): int
    {
        $caseId = (int) DB::table('scases')->insertGetId(['name' => $name, 'center_id' => $centerId]);
        if ($linkParent) {
            DB::table('scase_user')->insert([
                'scase_id' => $caseId, 'user_id' => $this->parent->id, 'relationship_type' => System::USER_TYPE_PARENT,
            ]);
        }

        return $caseId;
    }

    /**
     * @param  string  $media  image|file|video (stored path), legacy_image|legacy_video (NULL paths, file on disk),
     *                         legacy_without_file (NULL paths, nothing stored), text_only (paths confirmed empty),
     *                         none (no message at all)
     */
    protected function makeGoal(
        int $caseId,
        string $category,
        string $media,
        bool $published = true,
        bool $started = true,
        ?int $termId = null,
        ?int $assessmentId = null,
        bool $deleted = false
    ): int {
        $goalId = (int) DB::table('goals')->insertGetId([
            'case_id' => $caseId,
            'term_id' => $termId ?? $this->currentTerm,
            'assessment_id' => $assessmentId,
            'title' => "{$category} goal",
            'custom_first_feild' => $assessmentId ? null : 'Domain',
            'category' => $category,
            'started_session' => $started ? now()->subWeek()->toDateTimeString() : null,
            'date_from' => now()->subMonth()->toDateString(),
            'date_to' => now()->addMonth()->toDateString(),
            'deleted_at' => $deleted ? now()->toDateTimeString() : null,
        ]);

        if ($media !== 'none') {
            $this->addMessage($goalId, $media, $published);
        }

        return $goalId;
    }

    /**
     * @param  string  $media  same values as `makeGoal`, except `none`
     */
    protected function addMessage(int $goalId, string $media, bool $published = true, string $content = 'Session update'): int
    {
        $types = ['image' => 'message_image', 'file' => 'message_file', 'video' => 'message_video'];
        $legacy = str_starts_with($media, 'legacy_');
        $kind = $legacy ? substr($media, strlen('legacy_')) : $media;
        $paths = ['image_path' => '', 'file_path' => '', 'video_path' => ''];
        if ($legacy) {
            $paths = ['image_path' => null, 'file_path' => null, 'video_path' => null];
        } elseif (isset($types[$kind])) {
            $paths[$kind.'_path'] = $types[$kind].'.'.$this->extension($kind);
        }

        $messageId = (int) DB::table('messages')->insertGetId([
            'goal_id' => $goalId,
            'user_id' => $this->staff->id,
            'content' => $content,
            'type' => null,
            'log_work' => 1,
            'parents_can_see' => $published ? 1 : 0,
            'created_at' => now()->subDays(3)->toDateTimeString(),
        ] + $paths);

        if (isset($types[$kind])) {
            $directory = $this->storageRoot."/app/public/goals/{$goalId}/messages/{$messageId}";
            File::ensureDirectoryExists($directory);
            File::put($directory.'/'.$types[$kind].'.'.$this->extension($kind), 'media');
        }

        return $messageId;
    }

    private function extension(string $kind): string
    {
        return ['image' => 'jpg', 'file' => 'pdf', 'video' => 'mp4'][$kind];
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('password')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
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
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('center_id');
            $table->string('title')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('periods_count')->nullable();
            $table->boolean('assign_all_users')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('term_user', function (Blueprint $table) {
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('scases', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('name');
            $table->unsignedBigInteger('center_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('scase_user', function (Blueprint $table) {
            $table->unsignedBigInteger('scase_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedTinyInteger('relationship_type');
        });
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('parents_ids')->nullable();
            $table->string('title')->nullable();
            $table->string('title_local')->nullable();
            $table->string('category')->nullable();
            $table->unsignedBigInteger('evaluation_method_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('goals', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('term_id')->nullable();
            $table->unsignedBigInteger('case_id');
            $table->unsignedBigInteger('assessment_id')->nullable();
            $table->string('title')->nullable();
            $table->string('title_local')->nullable();
            $table->text('description')->nullable();
            $table->string('custom_first_feild')->nullable();
            $table->string('custom_general_goal')->nullable();
            $table->dateTime('started_session')->nullable();
            $table->dateTime('last_started_session')->nullable();
            $table->dateTime('ended_session')->nullable();
            $table->string('value')->nullable();
            $table->unsignedBigInteger('value_id')->nullable();
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->string('category')->nullable();
            $table->string('standard')->nullable();
            $table->string('generalization')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('goals_evaluations_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('goal_id');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->string('image_path')->nullable();
            $table->string('file_path')->nullable();
            $table->string('video_path')->nullable();
            $table->unsignedBigInteger('goal_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('meeting_room_id')->nullable();
            $table->text('content')->nullable();
            $table->string('type')->nullable();
            $table->unsignedTinyInteger('log_work')->default(0);
            $table->unsignedTinyInteger('parents_can_see')->default(0);
            $table->timestamps();
        });
    }
}
