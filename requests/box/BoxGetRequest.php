<?php

namespace Marketplace\Tokens\Requests\Box;

use Project\Requests\FormRequestFactory;

class BoxGetRequest extends FormRequestFactory
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => 'sometimes|integer|min:0'
        ];
    }
}
