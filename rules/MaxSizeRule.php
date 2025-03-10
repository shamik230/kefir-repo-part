<?php

namespace Marketplace\Tokens\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Lang;

class MaxSizeRule implements Rule
{
    private $maxSizeMb;


    public function __construct(float $maxSizeMb)
    {
        $this->maxSizeMb = $maxSizeMb;
    }

    public function passes($attribute, $value): bool
    {
        $megabytes = $value->getSize() / 1024 / 1024;

        return $megabytes < $this->maxSizeMb;
    }

    public function message(): string
    {
        $message = Lang::get('marketplace.tokens::validation.max_size_rule');
        return str_replace(':max', $this->maxSizeMb, $message);
    }
}
