<?php

namespace App\Support;

class ParentPhoneNumberException extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct($reason);
    }

    public function localizedMessage(): string
    {
        return __('validation.parent_phone_numbers.'.$this->reason);
    }
}
