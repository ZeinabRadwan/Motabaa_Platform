<?php

namespace App\Support\Hr;

use Illuminate\Http\JsonResponse;

/**
 * Domain rule violation in the HR module. `reason` is a key under lang/{locale}/validation.php `hr`.
 */
class HrException extends \RuntimeException
{
    public function __construct(
        public readonly string $reason,
        public readonly string $field = 'error',
        public readonly int $status = 422,
        public readonly array $replace = [],
        /** Extra machine-readable context returned with the error (e.g. GPS distance and radius). */
        public readonly array $details = []
    ) {
        parent::__construct($reason);
    }

    public function localizedMessage(): string
    {
        return __('validation.hr.'.$this->reason, $this->replace);
    }

    public function toResponse(): JsonResponse
    {
        return response()->json(array_filter([
            'errors' => [$this->field => [$this->localizedMessage()]],
            'reason' => $this->reason,
            'details' => $this->details,
        ], fn ($value) => $value !== []), $this->status);
    }
}
