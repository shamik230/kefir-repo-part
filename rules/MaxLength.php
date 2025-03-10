<?php

namespace Marketplace\Tokens\Rules;

use Illuminate\Contracts\Validation\Rule;

class MaxLength implements Rule
{
    protected $maxLength;
    public function __construct($maxLength)
    {
        $this->maxLength = $maxLength;
    }

    public function passes($attribute, $value): bool
    {
        return strlen($value) <= $this->maxLength;
    }

    public function message(): string
    {
        return 'Длина :attribute не должна превышать ' . $this->maxLength . ' символов.';
    }
}
