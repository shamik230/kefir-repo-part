<?php

namespace Marketplace\Tokens\Requests\Box;

use Project\Requests\FormRequestFactory;

class BoxWhitelistRequest extends FormRequestFactory
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'address' => 'required|string',
        ];
    }
}
