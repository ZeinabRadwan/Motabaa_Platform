<?php

namespace App\Support;

use App\Models\Goal;
use App\Models\Message;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\JsonResponse;

/**
 * Parent-facing read-only view of one programme (educational) or treatment goal
 * with the session messages staff shared with parents (attachments optional).
 */
class ParentGoalDetails
{
    public const SECTION_PROGRAM = 'program';

    public const SECTION_TREATMENT = 'treatment';

    public const MEDIA_PER_PAGE = 20;

    public const DENY_NOT_PARENT = 'not_parent';

    public const DENY_NOT_FOUND = 'not_found';

    public const DENY_UNSUPPORTED_SECTION = 'unsupported_section';

    public const DENY_SECTION_MISMATCH = 'section_mismatch';

    public const DENY_BENEFICIARY_MISMATCH = 'beneficiary_mismatch';

    public const DENY_NO_PERMISSION = 'no_permission';

    public const DENY_NOT_LINKED = 'not_linked';

    /** @var array<string, string> Permission module that gates each parent list page. */
    public const SECTION_PERMISSION_MODULES = [
        self::SECTION_PROGRAM => 'education-goals',
        self::SECTION_TREATMENT => 'treatment-goals',
    ];

    public const DENY_TERM_NOT_VISIBLE = 'term_not_visible';

    private const IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];

    private const VIDEO_EXTENSIONS = ['mp4', 'mov', 'ogg', 'webm', 'm4v'];

    private const AUDIO_EXTENSIONS = ['mp3', 'wav', 'm4a', 'aac', 'oga'];

    private const OFFICE_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv'];

    public static function sectionForCategory(?string $category): ?string
    {
        if ($category === 'educational') {
            return self::SECTION_PROGRAM;
        }

        if (GoalParentVisibility::isTreatmentCategory($category)) {
            return self::SECTION_TREATMENT;
        }

        return null;
    }

    /**
     * Checks that need no database: goal shape, requested section and selected beneficiary.
     */
    public static function structuralDenyReason(?Goal $goal, ?int $expectedCaseId, ?string $expectedSection): ?string
    {
        if (! $goal || ! $goal->case_id) {
            return self::DENY_NOT_FOUND;
        }

        $section = self::sectionForCategory($goal->category);
        if ($section === null) {
            return self::DENY_UNSUPPORTED_SECTION;
        }

        if ($expectedSection !== null && $expectedSection !== '' && $expectedSection !== $section) {
            return self::DENY_SECTION_MISMATCH;
        }

        if ($expectedCaseId !== null && $expectedCaseId > 0 && $expectedCaseId !== (int) $goal->case_id) {
            return self::DENY_BENEFICIARY_MISMATCH;
        }

        return null;
    }

    public static function denyReason(
        User $user,
        ?Goal $goal,
        ?int $expectedCaseId,
        ?string $expectedSection,
        ?int $centerId
    ): ?string {
        if (! isParentUser($user)) {
            return self::DENY_NOT_PARENT;
        }

        $structural = self::structuralDenyReason($goal, $expectedCaseId, $expectedSection);
        if ($structural !== null) {
            return $structural;
        }

        if (! self::userHasSectionPermission($user, self::sectionForCategory($goal->category))) {
            return self::DENY_NO_PERMISSION;
        }

        if (! GoalParentVisibility::parentCanAccessCase($user, (int) $goal->case_id, $centerId)) {
            return self::DENY_NOT_LINKED;
        }

        if (Term::abortIfInaccessible($user, $centerId, $goal->term_id) !== null) {
            return self::DENY_TERM_NOT_VISIBLE;
        }

        return null;
    }

    public static function userHasSectionPermission(User $user, ?string $section): bool
    {
        $module = self::SECTION_PERMISSION_MODULES[$section] ?? null;
        if ($module === null) {
            return false;
        }

        return $user->can("access_{$module}") || $user->can("admin_{$module}");
    }

    public static function deniedResponse(string $reason): JsonResponse
    {
        $status = $reason === self::DENY_NOT_FOUND ? 404 : 403;

        return response()->json([
            'errors' => ['error' => [__('validation.parent_goal_details.not_accessible')]],
            'reason' => $reason,
        ], $status);
    }

    /**
     * @return array<string, mixed>
     */
    public static function goalPayload(Goal $goal): array
    {
        $assessment = $goal->assesment;
        $generalGoal = $goal->custom_general_goal
            ?: ($assessment?->parent?->assessment_title);
        $domain = $goal->custom_first_feild
            ?: ($assessment?->getFirstFeild()?->assessment_title);

        $section = self::sectionForCategory($goal->category);

        return [
            'id' => $goal->id,
            'section' => $section,
            'category' => $goal->category,
            'service_label' => $section === self::SECTION_TREATMENT
                ? GoalParentVisibility::categoryLabel((string) $goal->category)
                : null,
            'title' => $goal->goal_title,
            'description' => $goal->description,
            'general_goal' => $generalGoal ?: null,
            'domain' => $domain ?: null,
            'standard' => $goal->standard,
            'generalization' => $goal->generalization,
            'date_from' => $goal->date_from,
            'date_to' => $goal->date_to,
            'case' => $goal->case ? ['id' => $goal->case->id, 'name' => $goal->case->name] : null,
            'term_id' => $goal->term_id,
            'sessions' => [
                'count' => (int) ($goal->started_sessions_count ?? 0),
                'first_started_at' => $goal->started_session,
                'last_started_at' => $goal->last_started_session,
            ],
        ];
    }

    /**
     * Messages shared with parents for the goal (newest first), with or without attachments.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function mediaQuery(Goal $goal)
    {
        $query = Message::query()
            ->with(['user:id,name', 'user.roles:id,name,default_name'])
            ->where('goal_id', $goal->id)
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc');

        GoalParentVisibility::applyParentVisibleMessageConstraints($query);

        return $query;
    }

    /**
     * One shared message; `image`, `video` and `document` are null when it has no such attachment.
     *
     * @return array<string, mixed>|null
     */
    public static function mediaItem(Message $message): ?array
    {
        if (! GoalParentVisibility::messageIsParentVisible($message)) {
            return null;
        }

        $attachments = ! $message->skipsAttachmentLookup();
        $image = $attachments ? self::fileMeta($message->urlImage()) : null;
        $video = $attachments ? self::fileMeta($message->urlVideo()) : null;
        $document = null;

        $file = $attachments ? $message->urlFile() : null;
        if ($file && ! empty($file['file'])) {
            $meta = self::fileMeta($file['file']);
            if ($meta) {
                $kind = self::documentKind($meta['extension']);
                if ($kind === 'video' && ! $video) {
                    $video = $meta;
                } elseif ($kind === 'image' && ! $image) {
                    $image = $meta;
                } else {
                    $document = $meta + [
                        'kind' => $kind,
                        'previewable' => in_array($kind, ['pdf', 'image', 'video', 'audio'], true),
                    ];
                }
            }
        }

        return [
            'id' => $message->id,
            'content' => $message->content,
            'is_session_activity' => (int) $message->log_work === 1,
            'is_own' => auth()->id() !== null && (int) $message->user_id === (int) auth()->id(),
            'created_at' => $message->created_at,
            'author' => $message->user ? [
                'name' => $message->user->name,
                'roles' => collect($message->user->roles)->pluck('name')->filter()->values(),
            ] : null,
            'image' => $image,
            'video' => $video,
            'document' => $document,
        ];
    }

    public static function documentKind(?string $extension): string
    {
        $extension = strtolower((string) $extension);

        if ($extension === 'pdf') {
            return 'pdf';
        }
        if (in_array($extension, self::IMAGE_EXTENSIONS, true)) {
            return 'image';
        }
        if (in_array($extension, self::VIDEO_EXTENSIONS, true)) {
            return 'video';
        }
        if (in_array($extension, self::AUDIO_EXTENSIONS, true)) {
            return 'audio';
        }
        if (in_array($extension, self::OFFICE_EXTENSIONS, true)) {
            return 'office';
        }

        return 'other';
    }

    /**
     * @param  array<string, mixed>|null  $file
     * @return array{url: string, name: string, extension: string}|null
     */
    private static function fileMeta($file): ?array
    {
        if (! is_array($file) || empty($file['file_url'])) {
            return null;
        }

        $extension = strtolower((string) ($file['file_extension'] ?? ''));
        $name = (string) ($file['file_name'] ?? '');

        return [
            'url' => (string) $file['file_url'],
            'name' => $extension !== '' ? "{$name}.{$extension}" : $name,
            'extension' => $extension,
        ];
    }
}
