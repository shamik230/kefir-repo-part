<?php

namespace Marketplace\Tokens\Requests;

use Auth;
use Illuminate\Contracts\Validation\Validator;
use Marketplace\Tokens\Models\Token;
use Project\Requests\FormRequestBase;

/**
 * @property int $token_id
 * @property Token $token
 */
class TokenRefreshRequest extends FormRequestBase
{
    function rules(): array
    {
        return [
            'token_id' => 'required|exists:marketplace_tokens_tokens,id'
        ];
    }

    function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $this->token = Token::find($this->token_id);
            if (!$this->token) {
                $validator->errors()->add('error', 'Токен не найден');
            } elseif (!$this->token->isModerated()) {
                $validator->errors()->add('error', 'Токен не промодерирован');
            }
        });
    }
}
