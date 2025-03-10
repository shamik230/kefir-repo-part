<?php

namespace Marketplace\Tokens\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;

/**
 * @property string $wallet_address
 * @property string $user_id
 */
class DropMiniShopIndexRequest extends FormRequest
{
    function rules(): array
    {
        return [
            'wallet_address' => 'bail|sometimes|nullable|required|string',
            'user_id' => [
                'bail',
                'sometimes',
                'nullable',
                'required',
                'integer',
                'exists:users,id',
            ],
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw (new ValidationException(
            $validator,
            response()->json(['error' => $validator->errors()->first()], 422))
        );
    }
}
