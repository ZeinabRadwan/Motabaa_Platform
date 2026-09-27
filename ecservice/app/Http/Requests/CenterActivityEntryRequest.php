<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CenterActivityEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => 'required|string|max:2048',
            'parents_can_see' => 'sometimes|boolean',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:10240',
            'file' => 'sometimes|nullable|file|max:102400',
            'video' => 'sometimes|nullable|file|mimetypes:video/mp4,video/quicktime,video/ogg,video/webm,video/x-m4v|max:102400',
        ];
    }
}
