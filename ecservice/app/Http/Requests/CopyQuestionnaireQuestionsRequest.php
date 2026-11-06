<?php

namespace App\Http\Requests;

use App\Models\QuestionnaireTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CopyQuestionnaireQuestionsRequest extends FormRequest
{
    public const DESTINATION_NEW = 'new';

    public const DESTINATION_EXISTING = 'existing';

    public function authorize(): bool
    {
        return can('edit_questionnaires', $this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'question_ids' => 'required|array|min:1',
            'question_ids.*' => 'required|integer|distinct|min:1',
            'destination_type' => ['required', Rule::in([self::DESTINATION_NEW, self::DESTINATION_EXISTING])],
            'destination_task_id' => 'nullable|required_if:destination_type,'.self::DESTINATION_EXISTING.'|integer|min:1',
            'skip_duplicates' => 'sometimes|boolean',
        ];

        if ($this->isPreview()) {
            return $rules;
        }

        return array_merge($rules, [
            'title' => 'nullable|required_if:destination_type,'.self::DESTINATION_NEW.'|string|max:191',
            'target_audience_type' => ['nullable', Rule::in([QuestionnaireTask::AUDIENCE_PARENT, QuestionnaireTask::AUDIENCE_ROLE])],
            'term_id' => 'nullable|integer',
            'target_role_id' => 'nullable|integer',
            'starts_at' => 'nullable|required_if:destination_type,'.self::DESTINATION_NEW.'|date',
            'ends_at' => 'nullable|required_if:destination_type,'.self::DESTINATION_NEW.'|date',
            'allow_parent_edit_after_submit' => 'nullable',
        ]);
    }

    public function isPreview(): bool
    {
        return $this->routeIs('questionnaire.copy_questions_preview');
    }

    public function isNewDestination(): bool
    {
        return $this->input('destination_type') === self::DESTINATION_NEW;
    }

    public function skipDuplicates(): bool
    {
        return $this->boolean('skip_duplicates');
    }

    /**
     * @return array<int, int>
     */
    public function questionIds(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->input('question_ids', []))));
    }

    protected function prepareForValidation(): void
    {
        $questionIds = $this->input('question_ids');
        if (is_string($questionIds)) {
            $decoded = json_decode($questionIds, true);
            $this->merge(['question_ids' => is_array($decoded) ? $decoded : array_filter(explode(',', $questionIds))]);
        }

        if ($this->has('title') && is_string($this->input('title'))) {
            $this->merge(['title' => trim($this->input('title'))]);
        }
    }
}
