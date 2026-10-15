<?php

namespace App\Http\Requests;

use App\Models\MeetingRoom;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MeetingRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:191',
            'type' => 'sometimes|integer',
            'audience_type' => ['required', Rule::in([
                MeetingRoom::AUDIENCE_GENERAL,
                MeetingRoom::AUDIENCE_PARENTS,
                MeetingRoom::AUDIENCE_LINK_INVITE,
            ])],
            'parent_ids' => 'nullable|array',
            'parent_ids.*' => 'integer|exists:users,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('audience_type') || $this->input('audience_type') === '') {
            $this->merge(['audience_type' => MeetingRoom::AUDIENCE_GENERAL]);
        }
    }
}
