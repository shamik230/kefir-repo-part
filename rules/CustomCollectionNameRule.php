<?php

namespace Marketplace\Tokens\Rules;

use Illuminate\Contracts\Validation\Rule;
use Auth;

class CustomCollectionNameRule implements Rule
{
    private $errorMessage;
    public function passes($attribute, $value): bool
    {
        if (!Auth::user()->collections->isEmpty()){
            $this->errorMessage = 'У пользователя есть существующие коллекции';
            return false;
        }
        return true;
    }

    public function message(): string
    {
        return $this->errorMessage ?? 'Error';
    }


}
