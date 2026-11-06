<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator as ValidatorFactory;
use Illuminate\Validation\Validator;

class ParentGoalMessageRequest extends FormRequest
{
    public const ATTACHMENT_FIELDS = ['image', 'video', 'file'];

    /**
     * Per-file limits in kilobytes (200 MB). LIVE sits behind Cloudflare, which rejects
     * request bodies above 100 MiB, so files larger than one chunk must go through the
     * chunked route (ParentChunkedUpload); this route only receives a whole file when it
     * fits in a single chunk.
     */
    public const MAX_KILOBYTES = [
        'image' => 204800,
        'video' => 204800,
        'file' => 204800,
    ];

    /** Client extensions accepted per field; the stored filename keeps this extension. */
    public const ALLOWED_EXTENSIONS = [
        'image' => ['jpg', 'jpeg', 'png'],
        'video' => ['mp4', 'mov', 'ogg', 'ogv', 'webm', 'm4v', '3gp', '3g2'],
        'file' => [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
            'jpg', 'jpeg', 'png', 'gif', 'webp',
            'mp4', 'mov', 'webm', 'm4v', '3gp', '3g2',
            'mp3', 'wav', 'm4a', 'aac', 'oga',
        ],
    ];

    public const VIDEO_MIME_TYPES = [
        'video/mp4', 'video/quicktime', 'video/ogg', 'video/webm', 'video/x-m4v', 'video/3gpp', 'video/3gpp2',
    ];

    /** Content-detected extensions (finfo) accepted for the generic file field. */
    private const FILE_CONTENT_EXTENSIONS = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf', 'txt', 'csv',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp4', 'mov', 'qt', 'webm', 'm4v', 'ogv', '3gp', '3g2', 'mp3', 'mpga', 'wav', 'm4a', 'aac', 'oga', 'ogg',
    ];

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
            'content' => 'nullable|string|max:2048',
            'image' => 'sometimes|nullable|image|mimes:jpeg,png,jpg|max:'.self::MAX_KILOBYTES['image'],
            'video' => 'sometimes|nullable|file|mimetypes:'.implode(',', self::VIDEO_MIME_TYPES).'|max:'.self::MAX_KILOBYTES['video'],
            'file' => 'sometimes|nullable|file|mimes:'.implode(',', self::FILE_CONTENT_EXTENSIONS).'|max:'.self::MAX_KILOBYTES['file'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $messages = [];
        foreach (self::ATTACHMENT_FIELDS as $field) {
            $mb = self::maxMegabytes($field);
            $unsupported = __('validation.parent_goal_details.unsupported_type', [
                'types' => strtoupper(implode(', ', self::ALLOWED_EXTENSIONS[$field])),
            ]);

            $messages["{$field}.max"] = __('validation.parent_goal_details.too_large', ['mb' => $mb]);
            $messages["{$field}.uploaded"] = self::uploadErrorMessage($this->file($field), $mb);
            $messages["{$field}.file"] = $unsupported;
            $messages["{$field}.image"] = $unsupported;
            $messages["{$field}.mimes"] = $unsupported;
            $messages["{$field}.mimetypes"] = $unsupported;
        }

        return $messages;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $attached = array_filter(
                self::ATTACHMENT_FIELDS,
                fn (string $field) => $this->file($field) instanceof UploadedFile
            );
            if (count($attached) !== 1) {
                $validator->errors()->add('file', __('validation.parent_goal_details.one_attachment_required'));

                return;
            }

            $field = reset($attached);
            $file = $this->file($field);
            if (! $file->isValid() || $validator->errors()->has($field)) {
                return;
            }

            $extension = strtolower((string) $file->getClientOriginalExtension());
            if (! in_array($extension, self::ALLOWED_EXTENSIONS[$field], true)) {
                $validator->errors()->add($field, __('validation.parent_goal_details.unsupported_type', [
                    'types' => strtoupper(implode(', ', self::ALLOWED_EXTENSIONS[$field])),
                ]));
            }
        });
    }

    public function attachmentField(): ?string
    {
        foreach (self::ATTACHMENT_FIELDS as $field) {
            if ($this->hasFile($field)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * Runs the same rules against a file that did not arrive in this request
     * (an upload reassembled from chunks).
     */
    public static function validatorFor(string $field, UploadedFile $file, ?string $content): Validator
    {
        $request = static::create('/', 'POST', ['content' => $content], [], [$field => $file]);
        $validator = ValidatorFactory::make($request->all(), $request->rules(), $request->messages());
        $request->withValidator($validator);

        return $validator;
    }

    public static function maxMegabytes(string $field): int
    {
        return intdiv(self::MAX_KILOBYTES[$field], 1024);
    }

    /**
     * Upload limits the parent UI mirrors before sending.
     *
     * @return array<string, array{max_kb: int, max_mb: int, extensions: array<int, string>}>
     */
    public static function clientRules(): array
    {
        $rules = [];
        foreach (self::ATTACHMENT_FIELDS as $field) {
            $rules[$field] = [
                'max_kb' => self::MAX_KILOBYTES[$field],
                'max_mb' => self::maxMegabytes($field),
                'extensions' => self::ALLOWED_EXTENSIONS[$field],
            ];
        }

        return $rules;
    }

    private static function uploadErrorMessage(mixed $file, int $mb): string
    {
        $error = $file instanceof UploadedFile ? $file->getError() : UPLOAD_ERR_OK;

        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => __('validation.parent_goal_details.too_large', ['mb' => $mb]),
            UPLOAD_ERR_PARTIAL, UPLOAD_ERR_NO_FILE => __('validation.parent_goal_details.upload_interrupted'),
            default => __('validation.parent_goal_details.upload_server_error'),
        };
    }
}
