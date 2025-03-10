<?php

namespace Marketplace\Tokens\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\ValidationException;
use Project\Requests\FormRequestBase;

/**
 * @property string $wallet_address
 */
class DropMiniShopSheetWhiteListRequest extends FormRequestBase
{
    function rules(): array
    {
        return [
            'wallet_address' => 'bail|required|string',
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
