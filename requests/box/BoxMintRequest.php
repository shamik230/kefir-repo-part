<?php

namespace Marketplace\Tokens\Requests\Box;

use Illuminate\Validation\Validator;
use Marketplace\Collections\Models\Collection;
use Marketplace\BlockchainAccount\Models\BlockchainAccount;
use Marketplace\BoxDrop\Models\BoxDrop;
use Marketplace\BoxDrop\Models\BoxDropSettings;
use Marketplace\BoxDrop\Models\BoxDropStatus;
use Marketplace\Categories\Models\Category;
use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Tokens\Services\ChainBoxService;
use Project\Requests\FormRequestFactory;
use RainLab\User\Facades\Auth;
use RainLab\User\Models\User;

class BoxMintRequest extends FormRequestFactory
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        return [
            'address' => 'required|string',
            'stage' => 'required|string',
        ];
    }

    public function withValidator(Validator $validator)
    {
        $validator->after(function ($validator) {
            $chainBoxService = app(ChainBoxService::class);

            $user = Auth::user();
            $address = strtolower($this->address);
            $stage = strtolower($this->stage);
            $mintBoxPrice = BoxDropSettings::get('box_price_kefirium');

            // Collection
            $collection = Collection::firstOrCreate(
                [
                    'contract_address' => env('BOXES_CONTRACT_ADDRESS')
                ],
                [
                    'name' => 'BOXES',
                    'description' => 'BOXES',
                    'category_id' => Category::CATEGORY_ITEM_ID,
                    'user_id' => User::first()->id,
                    'moderation_status_id' => ModerationStatus::MODERATED_ID,
                    'modarated_at' => now()
                ]
            );

            // Save validated data
            $this->merge([
                'user' => $user,
                'address' => $address,
                'stage' => $stage,
                'mint_box_price' => $mintBoxPrice,
                'collection' => $collection,
            ]);

            // Open stage
            if ($stage == 'public') {
                // Kefirium balance
                if ($user->kefirium < $mintBoxPrice) {
                    $validator->errors()->add('balance', "Недостаточно Кефира для покупки. Цена: {$mintBoxPrice} л, баланс: {$user->kefirium} л.");
                    return;
                }
            }

            // Close stage
            if ($stage != 'public') {
                // Verify stage
                $boxDrop = BoxDrop::where('type_code', $stage)->first();
                if (!$boxDrop) {
                    $validator->errors()->add('stage', "Не найдена раздача для stage: {$stage}.");
                    return;
                }
                if ($boxDrop->status_code != BoxDropStatus::ACTIVE) {
                    $validator->errors()->add('whitelist', "Раздача для stage {$boxDrop->type_code} не активна.");
                    return;
                }

                // Whitelist
                $isWhitelistedAddress = $chainBoxService->isWhitelisted($address);
                if (!$isWhitelistedAddress) {
                    $validator->errors()->add('whitelist', "Адрес {$address} не находится в белом списке.");
                    return;
                }
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
