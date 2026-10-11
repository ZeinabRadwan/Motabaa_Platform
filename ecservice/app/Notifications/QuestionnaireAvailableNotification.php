<?php

namespace App\Notifications;

use App\Models\QuestionnaireTask;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class QuestionnaireAvailableNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly QuestionnaireTask $task,
        private readonly string $title,
        private readonly string $body,
        private readonly string $url,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->title,
            'message' => $this->body,
            'url' => $this->url,
            'task_id' => (int) $this->task->id,
            'questionnaire_title' => $this->task->questionnaire
                ? $this->task->questionnaire->getNameAttribute()
                : '',
        ];
    }
}
