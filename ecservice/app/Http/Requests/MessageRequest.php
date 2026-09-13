<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $rules = [
            'content' => 'required|string|max:2048',
            'goal_id' => 'sometimes|exists:goals,id',
            'log_work' => 'sometimes|boolean',
            'parents_can_see' => 'sometimes|boolean',
            'meeting_room_id' => 'sometimes|exists:meeting_rooms,id',
            'image' => 'sometimes|image|mimes:jpeg,png,jpg|max:10240',
            'file' => 'sometimes|max:102400',
            'video' => 'sometimes|mimes:mp4,mov,ogg,webm||max:102400',
        ];

        return $rules;
    }
}
