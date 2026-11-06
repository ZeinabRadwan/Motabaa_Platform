<?php

namespace App\Http\Requests;

use App\Support\ParentChunkedUpload;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ParentGoalMessageChunkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'upload_id' => ['required', 'string', 'regex:'.ParentChunkedUpload::UPLOAD_ID_PATTERN],
            'field' => ['required', 'string', Rule::in(ParentGoalMessageRequest::ATTACHMENT_FIELDS)],
            'file_name' => 'required|string|max:255',
            'total_size' => 'required|integer|min:1',
            'total_chunks' => 'required|integer|min:1',
            'chunk_index' => 'required|integer|min:0',
            'chunk' => 'required|file|max:'.intdiv(ParentChunkedUpload::CHUNK_BYTES, 1024),
            'content' => 'nullable|string|max:2048',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'chunk.uploaded' => __('validation.parent_goal_details.upload_interrupted'),
            'chunk.required' => __('validation.parent_goal_details.upload_interrupted'),
            'chunk.file' => __('validation.parent_goal_details.upload_interrupted'),
            'chunk.max' => __('validation.parent_goal_details.chunk_invalid'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $field = (string) $this->input('field');
            $totalSize = (int) $this->input('total_size');
            $totalChunks = (int) $this->input('total_chunks');
            $index = (int) $this->input('chunk_index');

            if ($totalSize > ParentGoalMessageRequest::MAX_KILOBYTES[$field] * 1024) {
                $validator->errors()->add($field, __('validation.parent_goal_details.too_large', [
                    'mb' => ParentGoalMessageRequest::maxMegabytes($field),
                ]));

                return;
            }

            $extension = strtolower(pathinfo((string) $this->input('file_name'), PATHINFO_EXTENSION));
            if (! in_array($extension, ParentGoalMessageRequest::ALLOWED_EXTENSIONS[$field], true)) {
                $validator->errors()->add($field, __('validation.parent_goal_details.unsupported_type', [
                    'types' => strtoupper(implode(', ', ParentGoalMessageRequest::ALLOWED_EXTENSIONS[$field])),
                ]));

                return;
            }

            $chunk = $this->file('chunk');
            if (
                $totalChunks !== ParentChunkedUpload::expectedChunks($totalSize)
                || $index >= $totalChunks
                || $chunk->getSize() !== ParentChunkedUpload::expectedChunkSize($index, $totalSize)
            ) {
                $validator->errors()->add('chunk', __('validation.parent_goal_details.chunk_invalid'));
            }
        });
    }

    /**
     * Fields that must stay identical for every chunk of one upload.
     *
     * @return array<string, mixed>
     */
    public function uploadMeta(int $goalId): array
    {
        return [
            'goal_id' => $goalId,
            'field' => (string) $this->input('field'),
            'file_name' => (string) $this->input('file_name'),
            'total_size' => (int) $this->input('total_size'),
            'total_chunks' => (int) $this->input('total_chunks'),
        ];
    }
}
