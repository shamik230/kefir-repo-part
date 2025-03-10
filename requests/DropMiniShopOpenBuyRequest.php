<?php

namespace Marketplace\Tokens\Requests;

use App;
use Illuminate\Validation\Validator;
use Marketplace\Tokens\Models\DropMiniShop;
use Marketplace\Tokens\Models\DropMiniShopSettings;
use Project\Requests\FormRequestBase;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User;

/**
 * @property string $wallet_address
 * @property string $currency
 * @property string $type
 * @property int $price_rur
 * @property int $price_kefir
 * @property User $authUser
 */
class DropMiniShopOpenBuyRequest extends FormRequestBase
{
    function rules(): array
    {
        return [
            'wallet_address' => [
                'bail',
                'required',
                'string',
            ],
            'currency' => [
                'bail',
                'required',
                'string',
                'in:' . implode(',', [DropMiniShopSettings::CURRENCY_CASH, DropMiniShopSettings::CURRENCY_KEFIRIUM]),
            ],
        ];
    }

    function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $this->wallet_address = strtolower($this->wallet_address);
            $this->authUser = $user = Auth::user();
            /** @var DropMiniShop $userConstraints */
            $userConstraints = DropMiniShop::query()
                ->firstWhere('wallet_address', $this->wallet_address);
            if (!DropMiniShopSettings::currentPhase()->openSales()) {
                // Если сейчас открытый дроп не активен
                $validator->errors()->add('error', 'Открытая распродажа не активна');
            } elseif ($userConstraints && $userConstraints->processing) {
                $validator->errors()->add('error', 'Предыдущий минт токена в обработке и покупка временно недоступна');
            } elseif ($this->currency == DropMiniShopSettings::CURRENCY_KEFIRIUM) {
                /** @var User $user */
                if ($user->kefirium < ($price = DropMiniShopSettings::openSalesKefiriumPrice())) {
                    $validator->errors()->add('error', "У вас недостаточно Кефира для покупки. Цена: $price л, баланс: $user->kefirium л");
                }
            }
            $this->type = DropMiniShopSettings::OPEN_DROP_KEY;
            $this->price_rur = DropMiniShopSettings::get(DropMiniShopSettings::OPEN_SALES_PRICE_TOKEN_RUR);
            $this->price_kefir = DropMiniShopSettings::get(DropMiniShopSettings::OPEN_SALES_PRICE_TOKEN_KEFIR);
        });
    }
}
