<?php

namespace App\Support;

use App\Models\QuestionnaireTask;
use App\Models\QuestionnaireTaskSubmission;
use App\Models\User;
use App\Notifications\QuestionnaireAvailableNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class QuestionnaireTaskNotifier
{
    public static function syncForTask(QuestionnaireTask $task): void
    {
        if (! $task->isOpen() || ! Schema::hasTable('notifications')) {
            return;
        }

        $task->loadMissing('questionnaire');
        $eligibleIds = QuestionnaireTaskAudienceAccess::eligibleUserIdsForTask($task);
        if ($eligibleIds->isEmpty()) {
            return;
        }

        $completedUserIds = collect();
        if (Schema::hasTable('questionnaire_task_submissions')) {
            $completedUserIds = QuestionnaireTaskSubmission::query()
                ->where('task_id', $task->id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);
        }

        $alreadyNotified = collect();
        if (Schema::hasTable('questionnaire_task_notified_users')) {
            $alreadyNotified = DB::table('questionnaire_task_notified_users')
                ->where('task_id', $task->id)
                ->pluck('user_id')
                ->map(fn ($id) => (int) $id);
        }

        $toNotify = $eligibleIds
            ->diff($completedUserIds)
            ->diff($alreadyNotified)
            ->values();

        if ($toNotify->isEmpty()) {
            return;
        }

        $title = __('tr.questionnaires.notification_new_title');
        $body = __('tr.questionnaires.notification_new_body');
        $url = rtrim((string) config('app.front_url'), '/').'/questionnaires/questions/'.$task->id;

        User::query()
            ->whereIn('id', $toNotify->all())
            ->get()
            ->each(function (User $user) use ($task, $title, $body, $url, $toNotify) {
                $user->notify(new QuestionnaireAvailableNotification($task, $title, $body, $url));

                if (Schema::hasTable('questionnaire_task_notified_users')) {
                    DB::table('questionnaire_task_notified_users')->insertOrIgnore([
                        'task_id' => $task->id,
                        'user_id' => $user->id,
                        'notified_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }
}
