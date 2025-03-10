<?php

namespace Marketplace\Tokens\Requests\Box;

use RainLab\User\Facades\Auth;
use Illuminate\Validation\Validator;
use Marketplace\BlockchainAccount\Models\BlockchainAccount;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Models\TokenType;
use Marketplace\Transactions\Models\Transaction;
use Project\Requests\FormRequestFactory;

class BoxOpenRequest extends FormRequestFactory
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'token_id' => 'required|numeric',
            'address' => 'required|string',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $user = Auth::user();

            $token = Token::query()
                ->withTrashed()
                ->find($this->token_id);

            $address = strtolower($this->address);

            $this->merge([
                'user' => $user,
                'token' => $token,
                'address' => $address,
            ]);

            if (!$token) {
                $validator->errors()->add('token', 'Бокс не найден.');
                return;
            }

            if (!empty($token->deleted_at)) {
                $validator->errors()->add('token', 'Бокс уже открыт или удален.');
                return;
            }

            if ($token->token_type_id != TokenType::BOX_TOKEN) {
                $validator->errors()->add('token', 'Токен не является боксом.');
                return;
            }

            if ($token->user_id != $user->id) {
                $validator->errors()->add('token', 'Вы не являетесь владельцем данного токена.');
                return;
            }

            // Check token state
            if ((bool)$token->is_sale) {
                $validator->errors()->add('token', 'Токен выставлен на продажу');
                return;
            }
            if ((bool)$token->is_booked) {
                $validator->errors()->add('token', 'Токен забронирован');
                return;
            }
            if ((bool)$token->in_progress) {
                $validator->errors()->add('token', 'Токен в обработке');
                return;
            }

            // Check token transactions
            $existPendingTransaction = Transaction::query()
                ->where('token_id', $token->id)
                ->where('status', Transaction::STATUS_PENDING)
                ->exists();

            if ($existPendingTransaction) {
                $validator->errors()->add('token', 'У токена есть транзакция в обработке');
                return;
            }

            // Blockchain account
            $blockchainAccount = BlockchainAccount::query()
                ->where('address', $address)
                ->first();

            if (!$blockchainAccount) {
                $validator->errors()->add('address', "Адрес {$address} не привязан ни к одному пользователю.");
                return;
            }

            if ($blockchainAccount->user_id !== $user->id) {
                $validator->errors()->add('address', "Адрес {$address} привязан к другому пользователю.");
                return;
            }
        });
    }
}
