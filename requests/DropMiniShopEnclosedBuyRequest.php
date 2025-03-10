<?php

namespace Marketplace\Tokens\Requests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Marketplace\Tokens\Models\DropMiniShop;
use Marketplace\Tokens\Models\DropMiniShopSettings;
use Marketplace\Tokens\Rules\ExistsCaseTable;
use Marketplace\Tokens\Rules\WalletWhitelistedRule;
use Project\Requests\FormRequestBase;

/**
 * @property string $wallet_address
 * @property int $amount
 * @property string $type
 * @property int $price
 */
class DropMiniShopEnclosedBuyRequest extends FormRequestBase
{
    function rules(): array
    {
        return [
            'wallet_address' => [
                'bail',
                'required',
                'string',
                new WalletWhitelistedRule($this->wallet_address, 'private'),
                new ExistsCaseTable('marketplace_drop_mini_shop_constraints', 'wallet_address', $this->wallet_address)
            ],
            'amount' => [
                'bail',
                'required',
                'integer',
                'in:' . DropMiniShopSettings::CLOSE_DROP_COUNT_ONE . ',' . DropMiniShopSettings::CLOSE_DROP_COUNT_THREE,
            ],
        ];
    }

    function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $this->wallet_address = strtolower($this->wallet_address);
            if (!DropMiniShopSettings::currentPhase()->enclosedSales()) {
                // Если сейчас закрытый дроп не активен
                $validator->errors()->add('error', 'Закрытая распродажа не активна');
            } elseif ($this->wallet_address) {
                /** @var DropMiniShop $userConstraints */
                $userConstraints = DropMiniShop::query()
                    ->firstWhere('wallet_address', $this->wallet_address);

                if ($userConstraints) {
                    if (((int)$this->amount) > 0 && $this->amount > $userConstraints->tokens_available) {
                        $validator->errors()->add(
                            'error',
                            "Вы не можете приобрести $this->amount токенов. Доступно для покупки: $userConstraints->tokens_available"
                        );
                    } elseif ($userConstraints->processing) {
                        $validator->errors()->add('error', 'Предыдущий минт токена в обработке и покупка временно недоступна');
                    }
                }
            }
            $this->type = DropMiniShopSettings::CLOSE_DROP_KEY;
            $this->price = ($this->amount == 1) ? DropMiniShopSettings::get(DropMiniShopSettings::ENCLOSED_SALES_PRICE_ONE_TOKEN) :
                DropMiniShopSettings::get(DropMiniShopSettings::ENCLOSED_SALES_PRICE_THREE_TOKEN);
        });
    }
}
