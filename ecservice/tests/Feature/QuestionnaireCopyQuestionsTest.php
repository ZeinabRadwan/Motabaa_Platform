<?php

namespace Tests\Feature;

use App\Models\QuestionnaireQuestion;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * HTTP tests for copying questionnaire questions, run against an isolated in-memory SQLite
 * database with only the tables these endpoints touch (the local MySQL database is never used).
 */
class QuestionnaireCopyQuestionsTest extends TestCase
{
    private const CENTER = 1;

    private const OTHER_CENTER = 2;

    private User $editor;

    private int $roleId;

    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', \PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite is required for questionnaire copy tests.');
        }

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->createSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        DB::table('centers')->insert([
            ['id' => self::CENTER, 'title' => 'Center A'],
            ['id' => self::OTHER_CENTER, 'title' => 'Center B'],
        ]);
        foreach (['access_questionnaires', 'show_questionnaires', 'edit_questionnaires', 'admin_questionnaires'] as $name) {
            DB::table('permissions')->insert(['name' => $name, 'guard_name' => 'web']);
        }
        $this->roleId = (int) DB::table('roles')->insertGetId(['name' => 'Teacher', 'default_name' => 'teacher', 'guard_name' => 'web']);

        $this->editor = $this->makeUser('editor@example.test', ['access_questionnaires', 'show_questionnaires', 'edit_questionnaires']);
    }

    public function test_copy_all_questions_into_new_questionnaire_preserves_structure(): void
    {
        $source = $this->makeSectionedSource();
        $this->seedResponses($source['task_id'], $source['question_ids']);
        $sourceSnapshot = $this->questionSnapshot($source['questionnaire_id']);
        $answersBefore = DB::table('questionnaires_answers')->orderBy('id')->get()->toArray();
        $submissionsBefore = DB::table('questionnaire_task_submissions')->orderBy('id')->get()->toArray();

        $response = $this->copy($source['task_id'], $this->newDestinationPayload($source['question_ids']));

        $response->assertOk()->assertJsonPath('status', true)->assertJsonPath('data.copied_count', 4);
        $newTaskId = (int) $response->json('data.task_id');
        $newQuestionnaireId = (int) $response->json('data.questionnaire_id');

        $this->assertNotSame($source['questionnaire_id'], $newQuestionnaireId);
        $this->assertDatabaseHas('questionnaires_tasks', [
            'id' => $newTaskId,
            'questionnaire_id' => $newQuestionnaireId,
            'center_id' => self::CENTER,
            'target_audience_type' => 'role',
            'target_role_id' => $this->roleId,
        ]);
        $this->assertDatabaseHas('questionnaires', ['id' => $newQuestionnaireId, 'title' => 'Copied questionnaire']);

        $copies = $this->questionSnapshot($newQuestionnaireId);
        $this->assertCount(4, $copies);
        $this->assertEmpty(array_intersect(array_column($copies, 'id'), $source['question_ids']));
        foreach (['title', 'title_local', 'type', 'required', 'options'] as $field) {
            $this->assertSame(array_column($sourceSnapshot, $field), array_column($copies, $field), "Field {$field} differs");
        }
        $this->assertSame(['Option C', 'Option A', 'Option B'], json_decode($copies[1]['options'], true));

        $newSections = DB::table('questionnaires_sections')->where('questionnaire_id', $newQuestionnaireId)->orderBy('sort_order')->get();
        $this->assertSame(['Communication', 'Behaviour'], $newSections->pluck('title')->all());
        $this->assertSame(
            [$newSections[0]->id, $newSections[0]->id, $newSections[1]->id, $newSections[1]->id],
            array_map('intval', array_column($copies, 'section_id'))
        );

        $this->assertSame($sourceSnapshot, $this->questionSnapshot($source['questionnaire_id']));
        $this->assertEquals($answersBefore, DB::table('questionnaires_answers')->orderBy('id')->get()->toArray());
        $this->assertEquals($submissionsBefore, DB::table('questionnaire_task_submissions')->orderBy('id')->get()->toArray());
        $this->assertSame(0, DB::table('questionnaires_answers')->where('task_id', $newTaskId)->count());
        $this->assertSame(0, DB::table('questionnaire_task_submissions')->where('task_id', $newTaskId)->count());
        $this->assertDatabaseHas('logs', ['type' => 'copy_questionnaire_questions', 'model_id' => $newTaskId]);
    }

    public function test_copy_ignores_extra_generated_columns_on_live_tables(): void
    {
        DB::statement('ALTER TABLE questionnaires_questions ADD COLUMN title_search TEXT GENERATED ALWAYS AS (lower(title)) VIRTUAL');
        DB::statement('ALTER TABLE questionnaires_sections ADD COLUMN title_search TEXT GENERATED ALWAYS AS (lower(title)) VIRTUAL');
        $source = $this->makeSectionedSource();

        $response = $this->copy($source['task_id'], $this->newDestinationPayload($source['question_ids']));

        $response->assertOk()->assertJsonPath('data.copied_count', 4);
        $copies = $this->questionSnapshot((int) $response->json('data.questionnaire_id'));
        $this->assertSame(['Text question', 'Choice question', 'Yes/no legacy', 'Behaviour text'], array_column($copies, 'title'));
        $this->assertSame(['Option C', 'Option A', 'Option B'], json_decode($copies[1]['options'], true));
    }

    public function test_copy_all_31_questions_into_new_questionnaire(): void
    {
        $source = $this->makeQuestionnaireTask('Thirty one', self::CENTER);
        $sectionIds = [];
        foreach (['Part A', 'Part B', 'Part C'] as $index => $sectionTitle) {
            $sectionIds[] = (int) DB::table('questionnaires_sections')->insertGetId([
                'questionnaire_id' => $source['questionnaire_id'], 'title' => $sectionTitle, 'title_local' => $sectionTitle, 'sort_order' => $index,
            ]);
        }
        $questionIds = [];
        for ($i = 0; $i < 31; $i++) {
            $isChoice = $i % 3 === 0;
            $questionIds[] = $this->addQuestion(
                $source['questionnaire_id'], 'سؤال رقم '.($i + 1), $isChoice ? 2 : 1, $i, $sectionIds[intdiv($i, 11)], $i % 2,
                null, $isChoice ? ['نعم', 'لا', 'أحياناً'] : null
            );
        }
        $this->seedResponses($source['task_id'], $questionIds);
        $sourceSnapshot = $this->questionSnapshot($source['questionnaire_id']);
        $answerCount = DB::table('questionnaires_answers')->count();

        $response = $this->copy($source['task_id'], $this->newDestinationPayload($questionIds));

        $response->assertOk()->assertJsonPath('data.copied_count', 31)->assertJsonPath('data.skipped_count', 0);
        $newQuestionnaireId = (int) $response->json('data.questionnaire_id');
        $copies = $this->questionSnapshot($newQuestionnaireId);
        $this->assertCount(31, $copies);
        foreach (['title', 'title_local', 'type', 'required', 'options'] as $field) {
            $this->assertSame(array_column($sourceSnapshot, $field), array_column($copies, $field), "Field {$field} differs");
        }
        $this->assertSame(['Part A', 'Part B', 'Part C'], DB::table('questionnaires_sections')
            ->where('questionnaire_id', $newQuestionnaireId)->orderBy('sort_order')->pluck('title')->all());
        $this->assertSame($sourceSnapshot, $this->questionSnapshot($source['questionnaire_id']));
        $this->assertSame($answerCount, DB::table('questionnaires_answers')->count());
        $this->assertSame(0, DB::table('questionnaires_answers')->where('task_id', (int) $response->json('data.task_id'))->count());
    }

    public function test_failed_copy_rolls_back_and_reports_the_failing_table(): void
    {
        $source = $this->makeSectionedSource();
        DB::statement('ALTER TABLE questionnaires RENAME TO questionnaires_old');
        DB::statement('CREATE TABLE questionnaires (id INTEGER PRIMARY KEY AUTOINCREMENT, title VARCHAR NOT NULL, title_local VARCHAR NULL,
            allow_parent_edit_after_submit TINYINT NOT NULL DEFAULT 0, legacy_required_column VARCHAR NOT NULL,
            created_at DATETIME NULL, updated_at DATETIME NULL, deleted_at DATETIME NULL)');
        DB::statement('INSERT INTO questionnaires (id, title, title_local, allow_parent_edit_after_submit, legacy_required_column)
            SELECT id, title, title_local, allow_parent_edit_after_submit, \'x\' FROM questionnaires_old');
        $counts = fn () => [
            DB::table('questionnaires')->count(),
            DB::table('questionnaires_tasks')->count(),
            DB::table('questionnaires_sections')->count(),
            DB::table('questionnaires_questions')->count(),
        ];
        $before = $counts();

        $response = $this->copy($source['task_id'], $this->newDestinationPayload($source['question_ids']));

        $response->assertStatus(500);
        $this->assertStringContainsString('questionnaires', (string) $response->json('errors.error.0'));
        $this->assertStringContainsString('QuestionnaireController.php', (string) $response->json('errors.error.0'));
        $this->assertSame($before, $counts());
    }

    public function test_copied_questionnaire_appears_in_list_and_search_for_staff(): void
    {
        [$pastTerm] = $this->makeTerms();
        DB::table('term_user')->insert(['term_id' => $pastTerm, 'user_id' => $this->editor->id]);
        $source = $this->makeSectionedSource();
        $sourceSnapshot = $this->questionSnapshot($source['questionnaire_id']);

        $roleCopy = $this->copy($source['task_id'], ['title' => 'تجريبي'] + $this->newDestinationPayload($source['question_ids']));
        $parentCopy = $this->copy($source['task_id'], [
            'title' => 'أسئلة الفصل السابق',
            'target_audience_type' => 'parent',
            'target_role_id' => null,
            'term_id' => $pastTerm,
        ] + $this->newDestinationPayload($source['question_ids']));

        $roleCopy->assertOk();
        $parentCopy->assertOk();
        $roleTaskId = (int) $roleCopy->json('data.task_id');
        $parentTaskId = (int) $parentCopy->json('data.task_id');

        $list = $this->listQuestionnaires();
        $list->assertOk();
        $titles = collect($list->json('data'))->pluck('title', 'id')->all();
        $this->assertSame('تجريبي', $titles[$roleTaskId] ?? null);
        $this->assertSame('أسئلة الفصل السابق', $titles[$parentTaskId] ?? null);
        $this->assertSame('Source', $titles[$source['task_id']] ?? null);

        $search = $this->listQuestionnaires(['q' => 'تجريبي']);
        $search->assertOk();
        $this->assertSame([$roleTaskId], collect($search->json('data'))->pluck('id')->all());
        $this->assertSame([$parentTaskId], collect($this->listQuestionnaires(['q' => 'أسئلة'])->json('data'))->pluck('id')->all());

        $this->assertSame(4, DB::table('questionnaires_questions')->where('questionnaire_id', (int) $roleCopy->json('data.questionnaire_id'))->count());
        $this->assertSame($sourceSnapshot, $this->questionSnapshot($source['questionnaire_id']));
        $this->assertDatabaseHas('questionnaires', ['id' => $source['questionnaire_id'], 'title' => 'Source']);
    }

    public function test_list_keeps_term_restrictions(): void
    {
        [$pastTerm, $currentTerm, $otherTerm] = $this->makeTerms();
        DB::table('term_user')->insert(['term_id' => $pastTerm, 'user_id' => $this->editor->id]);
        $inPast = $this->makeQuestionnaireTask('In past term', self::CENTER, $pastTerm);
        $inOther = $this->makeQuestionnaireTask('In unassigned term', self::CENTER, $otherTerm);
        $foreignCenter = $this->makeQuestionnaireTask('Other center', self::OTHER_CENTER);

        $ids = collect($this->listQuestionnaires()->json('data'))->pluck('id')->all();
        $this->assertContains($inPast['task_id'], $ids);
        $this->assertNotContains($inOther['task_id'], $ids);
        $this->assertNotContains($foreignCenter['task_id'], $ids);

        $this->assertSame([$inPast['task_id']], collect($this->listQuestionnaires(['term_id' => $pastTerm])->json('data'))->pluck('id')->all());
        $this->listQuestionnaires(['term_id' => $otherTerm])->assertStatus(403);

        $manager = $this->makeUser('manager@example.test', ['access_questionnaires', 'show_questionnaires', 'edit_questionnaires']);
        $managerRole = (int) DB::table('roles')->insertGetId(['name' => 'مدير', 'default_name' => 'manager', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => $managerRole, 'model_type' => User::class, 'model_id' => $manager->id]);
        Sanctum::actingAs($manager->fresh());
        $managerIds = collect($this->getJson('/api/questionnaires?center_id='.self::CENTER)->json('data'))->pluck('id')->all();
        $this->assertContains($inPast['task_id'], $managerIds);
        $this->assertContains($inOther['task_id'], $managerIds);
        $this->assertNotContains($foreignCenter['task_id'], $managerIds);
    }

    public function test_copy_selected_questions_only_keeps_source_order(): void
    {
        $source = $this->makeSectionedSource();
        $selected = [$source['question_ids'][3], $source['question_ids'][1]];

        $response = $this->copy($source['task_id'], $this->newDestinationPayload($selected));

        $response->assertOk()->assertJsonPath('data.copied_count', 2);
        $copies = $this->questionSnapshot((int) $response->json('data.questionnaire_id'));
        $this->assertSame(['Choice question', 'Behaviour text'], array_column($copies, 'title'));
        $this->assertSame([2, 1], array_column($copies, 'type'));
    }

    public function test_copy_into_existing_flat_questionnaire_appends_questions(): void
    {
        $source = $this->makeSectionedSource();
        $destination = $this->makeQuestionnaireTask('Destination', self::CENTER);
        $this->addQuestion($destination['questionnaire_id'], 'Existing one', 1, 0);
        $this->addQuestion($destination['questionnaire_id'], 'Existing two', 1, 1);

        $response = $this->copy($source['task_id'], [
            'question_ids' => [$source['question_ids'][0], $source['question_ids'][1]],
            'destination_type' => 'existing',
            'destination_task_id' => $destination['task_id'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.task_id', $destination['task_id'])
            ->assertJsonPath('data.copied_count', 2);

        $questions = $this->questionSnapshot($destination['questionnaire_id']);
        $this->assertSame(['Existing one', 'Existing two', 'Text question', 'Choice question'], array_column($questions, 'title'));
        $this->assertSame([0, 1, 2, 3], array_map('intval', array_column($questions, 'sort_order')));
        $this->assertSame([null, null, null, null], array_column($questions, 'section_id'));
        $this->assertSame(0, DB::table('questionnaires_sections')->where('questionnaire_id', $destination['questionnaire_id'])->count());
    }

    public function test_copy_into_existing_sectioned_questionnaire_reuses_sections_by_title(): void
    {
        $source = $this->makeSectionedSource();
        $destination = $this->makeQuestionnaireTask('Destination', self::CENTER);
        $sectionId = (int) DB::table('questionnaires_sections')->insertGetId([
            'questionnaire_id' => $destination['questionnaire_id'], 'title' => 'communication', 'title_local' => 'communication', 'sort_order' => 0,
        ]);
        $this->addQuestion($destination['questionnaire_id'], 'Existing', 1, 0, $sectionId);

        $response = $this->copy($source['task_id'], [
            'question_ids' => $source['question_ids'],
            'destination_type' => 'existing',
            'destination_task_id' => $destination['task_id'],
        ]);

        $response->assertOk()->assertJsonPath('data.copied_count', 4);
        $sections = DB::table('questionnaires_sections')->where('questionnaire_id', $destination['questionnaire_id'])->orderBy('sort_order')->get();
        $this->assertSame(['communication', 'Behaviour'], $sections->pluck('title')->all());

        $copied = DB::table('questionnaires_questions')
            ->where('questionnaire_id', $destination['questionnaire_id'])
            ->where('title', '!=', 'Existing')
            ->orderBy('sort_order')
            ->pluck('section_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $this->assertSame([$sectionId, $sectionId, (int) $sections[1]->id, (int) $sections[1]->id], $copied);
    }

    public function test_preview_reports_duplicates_and_copy_can_skip_them(): void
    {
        $source = $this->makeQuestionnaireTask('Source', self::CENTER);
        $duplicateId = $this->addQuestion($source['questionnaire_id'], 'هَلْ يتواصل الطفل بصرياً؟', 1, 0);
        $uniqueId = $this->addQuestion($source['questionnaire_id'], 'سؤال جديد', 1, 1);
        $destination = $this->makeQuestionnaireTask('Destination', self::CENTER);
        $this->addQuestion($destination['questionnaire_id'], 'هل يتواصل الطفل بصريا', 1, 0);
        $this->seedResponses($destination['task_id'], DB::table('questionnaires_questions')->where('questionnaire_id', $destination['questionnaire_id'])->pluck('id')->all());

        $payload = [
            'question_ids' => [$duplicateId, $uniqueId],
            'destination_type' => 'existing',
            'destination_task_id' => $destination['task_id'],
        ];

        Sanctum::actingAs($this->editor);
        $preview = $this->postJson("/api/questionnaires/{$source['task_id']}/copy-questions/preview", $payload + ['center_id' => self::CENTER]);
        $preview->assertOk()
            ->assertJsonPath('data.selected_count', 2)
            ->assertJsonPath('data.duplicate_count', 1)
            ->assertJsonPath('data.duplicates.0.source_question_id', $duplicateId)
            ->assertJsonPath('data.destination.has_responses', true)
            ->assertJsonPath('data.destination.question_count', 1);

        $this->copy($source['task_id'], $payload + ['skip_duplicates' => true])
            ->assertOk()
            ->assertJsonPath('data.copied_count', 1)
            ->assertJsonPath('data.skipped_question_ids', [$duplicateId]);
        $this->assertSame(2, DB::table('questionnaires_questions')->where('questionnaire_id', $destination['questionnaire_id'])->count());

        $this->copy($source['task_id'], $payload + ['skip_duplicates' => false])
            ->assertOk()
            ->assertJsonPath('data.copied_count', 2);
        $this->assertSame(4, DB::table('questionnaires_questions')->where('questionnaire_id', $destination['questionnaire_id'])->count());
    }

    public function test_skipping_when_every_question_is_duplicate_is_rejected(): void
    {
        $source = $this->makeQuestionnaireTask('Source', self::CENTER);
        $questionId = $this->addQuestion($source['questionnaire_id'], 'Same question', 1, 0);
        $destination = $this->makeQuestionnaireTask('Destination', self::CENTER);
        $this->addQuestion($destination['questionnaire_id'], 'same   question?', 1, 0);

        $this->copy($source['task_id'], [
            'question_ids' => [$questionId],
            'destination_type' => 'existing',
            'destination_task_id' => $destination['task_id'],
            'skip_duplicates' => true,
        ])->assertStatus(422);

        $this->assertSame(1, DB::table('questionnaires_questions')->where('questionnaire_id', $destination['questionnaire_id'])->count());
    }

    public function test_user_without_edit_permission_is_rejected(): void
    {
        $source = $this->makeSectionedSource();
        $payload = $this->newDestinationPayload($source['question_ids']);

        $viewer = $this->makeUser('viewer@example.test', ['access_questionnaires', 'show_questionnaires']);
        Sanctum::actingAs($viewer);
        $this->postJson("/api/questionnaires/{$source['task_id']}/copy-questions", $payload)->assertForbidden();

        $adminOnly = $this->makeUser('admin-only@example.test', ['admin_questionnaires']);
        Sanctum::actingAs($adminOnly);
        $this->postJson("/api/questionnaires/{$source['task_id']}/copy-questions", $payload)->assertForbidden();

        $this->assertSame(1, DB::table('questionnaires')->count());
    }

    public function test_source_from_another_center_is_rejected(): void
    {
        $foreign = $this->makeQuestionnaireTask('Foreign', self::OTHER_CENTER);
        $questionId = $this->addQuestion($foreign['questionnaire_id'], 'Foreign question', 1, 0);

        $this->copy($foreign['task_id'], $this->newDestinationPayload([$questionId]))->assertStatus(422);

        $this->assertSame(1, DB::table('questionnaires')->count());
        $this->assertSame(1, DB::table('questionnaires_questions')->count());
    }

    public function test_destination_from_another_center_or_shared_template_is_rejected(): void
    {
        $source = $this->makeSectionedSource();
        $foreign = $this->makeQuestionnaireTask('Foreign', self::OTHER_CENTER);
        $shared = $this->makeQuestionnaireTask('Shared', self::CENTER);
        DB::table('questionnaires_tasks')->insert([
            'questionnaire_id' => $shared['questionnaire_id'], 'center_id' => self::OTHER_CENTER, 'target_audience_type' => 'role',
            'target_role_id' => $this->roleId, 'starts_at' => '2020-01-01', 'ends_at' => '2020-01-31', 'count' => 0,
        ]);

        foreach ([$foreign['task_id'], $shared['task_id']] as $destinationTaskId) {
            $this->copy($source['task_id'], [
                'question_ids' => $source['question_ids'],
                'destination_type' => 'existing',
                'destination_task_id' => $destinationTaskId,
            ])->assertStatus(422);
        }

        $this->assertSame(0, DB::table('questionnaires_questions')->whereIn('questionnaire_id', [$foreign['questionnaire_id'], $shared['questionnaire_id']])->count());
    }

    public function test_invalid_selection_and_same_questionnaire_are_rejected(): void
    {
        $source = $this->makeSectionedSource();
        $other = $this->makeQuestionnaireTask('Other', self::CENTER);
        $otherQuestionId = $this->addQuestion($other['questionnaire_id'], 'Not in source', 1, 0);

        $this->copy($source['task_id'], $this->newDestinationPayload([$source['question_ids'][0], $otherQuestionId]))
            ->assertStatus(422);

        $sameTemplateTaskId = (int) DB::table('questionnaires_tasks')->insertGetId([
            'questionnaire_id' => $source['questionnaire_id'], 'center_id' => self::CENTER, 'target_audience_type' => 'role',
            'target_role_id' => $this->roleId, 'starts_at' => '2020-01-01', 'ends_at' => '2020-01-31', 'count' => 0,
        ]);
        $this->copy($source['task_id'], [
            'question_ids' => $source['question_ids'],
            'destination_type' => 'existing',
            'destination_task_id' => $sameTemplateTaskId,
        ])->assertStatus(422);

        $this->copy($source['task_id'], ['question_ids' => [], 'destination_type' => 'new'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['question_ids', 'title', 'starts_at', 'ends_at']);

        $this->assertSame(2, DB::table('questionnaires')->count());
        $this->assertSame(5, DB::table('questionnaires_questions')->count());
    }

    private function copy(int $taskId, array $payload)
    {
        Sanctum::actingAs($this->editor);

        return $this->postJson("/api/questionnaires/{$taskId}/copy-questions", $payload + ['center_id' => self::CENTER]);
    }

    private function newDestinationPayload(array $questionIds): array
    {
        return [
            'question_ids' => $questionIds,
            'destination_type' => 'new',
            'title' => 'Copied questionnaire',
            'target_audience_type' => 'role',
            'target_role_id' => $this->roleId,
            'starts_at' => '2020-02-01',
            'ends_at' => '2020-02-28',
            'allow_parent_edit_after_submit' => 0,
        ];
    }

    /**
     * Two titled sections; questions 1-2 in "Communication", 3-4 in "Behaviour".
     *
     * @return array{task_id: int, questionnaire_id: int, question_ids: array<int, int>}
     */
    private function makeSectionedSource(): array
    {
        $source = $this->makeQuestionnaireTask('Source', self::CENTER);
        $communication = (int) DB::table('questionnaires_sections')->insertGetId([
            'questionnaire_id' => $source['questionnaire_id'], 'title' => 'Communication', 'title_local' => 'Communication', 'sort_order' => 0,
        ]);
        $behaviour = (int) DB::table('questionnaires_sections')->insertGetId([
            'questionnaire_id' => $source['questionnaire_id'], 'title' => 'Behaviour', 'title_local' => 'Behaviour', 'sort_order' => 1,
        ]);

        $source['question_ids'] = [
            $this->addQuestion($source['questionnaire_id'], 'Text question', 1, 0, $communication, 1, 'Text question EN'),
            $this->addQuestion($source['questionnaire_id'], 'Choice question', 2, 1, $communication, 0, null, ['Option C', 'Option A', 'Option B']),
            $this->addQuestion($source['questionnaire_id'], 'Yes/no legacy', 2, 2, $behaviour, 1),
            $this->addQuestion($source['questionnaire_id'], 'Behaviour text', 1, 3, $behaviour, 0),
        ];

        return $source;
    }

    /**
     * @return array{task_id: int, questionnaire_id: int}
     */
    private function makeQuestionnaireTask(string $title, int $centerId, ?int $termId = null): array
    {
        $questionnaireId = (int) DB::table('questionnaires')->insertGetId([
            'title' => $title, 'title_local' => $title, 'allow_parent_edit_after_submit' => 0,
        ]);
        $taskId = (int) DB::table('questionnaires_tasks')->insertGetId([
            'questionnaire_id' => $questionnaireId, 'center_id' => $centerId,
            'target_audience_type' => $termId ? 'parent' : 'role', 'target_role_id' => $termId ? null : $this->roleId,
            'term_id' => $termId, 'starts_at' => '2020-01-01', 'ends_at' => '2020-01-31', 'count' => 0,
        ]);

        return ['task_id' => $taskId, 'questionnaire_id' => $questionnaireId];
    }

    /**
     * @return array{0: int, 1: int, 2: int}  past, current and another past term of the main center
     */
    private function makeTerms(): array
    {
        $insert = fn (string $title, string $from, string $to) => (int) DB::table('terms')->insertGetId([
            'center_id' => self::CENTER, 'title' => $title, 'starts_at' => $from, 'ends_at' => $to,
        ]);

        return [
            $insert('Past term', '2019-09-01', '2020-06-30'),
            $insert('Current term', now()->subMonth()->toDateString(), now()->addMonth()->toDateString()),
            $insert('Unassigned term', '2018-09-01', '2019-06-30'),
        ];
    }

    private function listQuestionnaires(array $query = [])
    {
        Sanctum::actingAs($this->editor);

        return $this->getJson('/api/questionnaires?'.http_build_query(['center_id' => self::CENTER] + $query));
    }

    private function addQuestion(
        int $questionnaireId,
        string $title,
        int $type,
        int $sortOrder,
        ?int $sectionId = null,
        int $required = 0,
        ?string $titleLocal = null,
        ?array $options = null
    ): int {
        return (int) DB::table('questionnaires_questions')->insertGetId([
            'questionnaire_id' => $questionnaireId,
            'section_id' => $sectionId,
            'title' => $title,
            'title_local' => $titleLocal ?? $title,
            'type' => $type,
            'required' => $required,
            'sort_order' => $sortOrder,
            'options' => $options === null ? null : json_encode($options, JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function seedResponses(int $taskId, array $questionIds): void
    {
        $respondentId = (int) DB::table('users')->insertGetId([
            'name' => 'Respondent', 'email' => 'respondent'.$taskId.'@example.test', 'password' => 'x', 'status' => 1,
        ]);
        foreach ($questionIds as $questionId) {
            DB::table('questionnaires_answers')->insert([
                'task_id' => $taskId, 'user_id' => $respondentId, 'question_id' => $questionId, 'answer' => 'answer '.$questionId,
            ]);
        }
        DB::table('questionnaire_task_submissions')->insert([
            'task_id' => $taskId, 'user_id' => $respondentId, 'submitted_at' => '2020-01-10 10:00:00',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function questionSnapshot(int $questionnaireId): array
    {
        return DB::table('questionnaires_questions')
            ->where('questionnaire_id', $questionnaireId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['id', 'section_id', 'title', 'title_local', 'type', 'required', 'sort_order', 'options', 'deleted_at'])
            ->map(fn ($row) => (array) $row)
            ->all();
    }

    private function makeUser(string $email, array $permissions): User
    {
        $userId = (int) DB::table('users')->insertGetId([
            'name' => $email, 'email' => $email, 'password' => 'x', 'status' => 1,
        ]);
        DB::table('centers_users')->insert(['user_id' => $userId, 'center_id' => self::CENTER]);

        $user = User::query()->findOrFail($userId);
        $user->givePermissionTo($permissions);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->fresh();
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedTinyInteger('status')->default(1);
            $table->text('search_text')->nullable();
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
        Schema::create('questionnaires', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('title_local')->nullable();
            $table->boolean('allow_parent_edit_after_submit')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('questionnaires_sections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('questionnaire_id');
            $table->string('title')->nullable();
            $table->string('title_local')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('questionnaires_questions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('questionnaire_id');
            $table->unsignedBigInteger('section_id')->nullable();
            $table->string('title');
            $table->string('title_local')->nullable();
            $table->tinyInteger('type');
            $table->unsignedTinyInteger('required')->default(0);
            $table->json('options')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('questionnaires_tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('questionnaire_id');
            $table->unsignedBigInteger('term_id')->nullable();
            $table->string('target_audience_type')->default('parent');
            $table->unsignedBigInteger('target_role_id')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->string('title');
            $table->string('title_local')->nullable();
            $table->date('starts_at');
            $table->date('ends_at');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('term_user', function (Blueprint $table) {
            $table->unsignedBigInteger('term_id');
            $table->unsignedBigInteger('user_id');
        });
        Schema::create('questionnaires_answers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('question_id');
            $table->text('answer');
            $table->timestamps();
        });
        Schema::create('questionnaire_task_submissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('task_id');
            $table->unsignedBigInteger('user_id');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('last_edited_at')->nullable();
            $table->timestamps();
        });
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('center_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('type');
            $table->text('description')->nullable();
            $table->string('model_type');
            $table->integer('model_id');
            $table->string('url');
            $table->text('search_text')->nullable();
            $table->timestamps();
        });
    }
}
