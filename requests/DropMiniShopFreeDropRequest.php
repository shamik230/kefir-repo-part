<?php

namespace Marketplace\Tokens\Requests;

use Db;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Marketplace\Tokens\Models\DropMiniShop;
use Marketplace\Tokens\Models\DropMiniShopSettings;
use Marketplace\Tokens\Rules\ExistsCaseTable;
use Marketplace\Tokens\Rules\WalletWhitelistedRule;
use Project\Requests\FormRequestBase;

/**
 * @property string $wallet_address
 * @property string $type
 */
class DropMiniShopFreeDropRequest extends FormRequestBase
{
    function rules(): array
    {
        return [
            'wallet_address' => [
                'bail',
                'required',
                'string',
                new WalletWhitelistedRule($this->wallet_address, 'soc'),
                new ExistsCaseTable('marketplace_drop_mini_shop_constraints', 'wallet_address', $this->wallet_address)
            ],
        ];
    }

    function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $this->wallet_address = strtolower($this->wallet_address);
            if (!DropMiniShopSettings::currentPhase()->freeDrop()) {
                // Если сейчас бесплатный дроп не активен
                $validator->errors()->add('error', 'Бесплатная раздача не активна');
            } elseif ($this->wallet_address) {
                /** @var DropMiniShop $userConstraints */
                $userConstraints = DropMiniShop::query()
                    ->firstWhere('wallet_address', $this->wallet_address);

                if ($userConstraints && $userConstraints->free_tokens_available === 0) {
                    // Если уже приобрёл бесплатный токен
                    $validator->errors()->add('error', 'Вы уже приобрели бесплатный токен');
                } elseif ($userConstraints && $userConstraints->processing) {
                    // Если уже запущен минт по дропу, который не завершил операцию
                    $validator->errors()->add('error', 'Предыдущий минт токена в обработке и покупка временно недоступна');
                }
            }
            $dropSetting = DropMiniShopSettings::getSettings();
            switch ($dropSetting['free_drop']['type']) {
                case DropMiniShopSettings::FREE_DROP_TYPE_ACTIVE_TG:
                    $this->type = 'telegram';
                    break;
                case DropMiniShopSettings::FREE_DROP_TYPE_ACTIVE_VK:
                    $this->type = 'vk';
                    break;
                case DropMiniShopSettings::FREE_DROP_TYPE_KEFIRIUS_OWNER:
                    $this->type = 'kefirius';
            }
        });
    }
}
