<?php

namespace Marketplace\Tokens\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Lang;
use Input;
use Log;
use Marketplace\Payments\Services\OwnerNodeService;
use Marketplace\Tokens\Models\Token;

class WalletCheck implements Rule
{
    private $token;
    private $ownerNodeService;
    private $errorMessage;

    public function __construct(OwnerNodeService $ownerNodeService, Token $token = null)
    {
        $this->token = $token;
        $this->ownerNodeService = $ownerNodeService;
    }

    public function passes($attribute, $value): bool
    {

        if (empty($this->token->external_address)) {
            $this->errorMessage = 'Токен не имеет связи с блокчейном';
            return false;
        }

        $validationNodeOwnerWallet = $this->ownerNodeService->getOwner($this->token->external_id, $this->token->external_address);
        Log::info('request_NodeOwnerWallet: ' . $this->token->external_id. " ". $this->token->external_address);
        Log::info('validationNodeOwnerWallet: ' . var_export($validationNodeOwnerWallet, true));
        if ($validationNodeOwnerWallet->getStatusCode() != 200) {
            $this->errorMessage = 'При проверке кошелька владельца токена произошла ошибка';
            return false;
        }
        if (strtolower($validationNodeOwnerWallet->getData()->currentOwner) != strtolower(Input::get('owner_wallet'))) {
            $this->errorMessage = 'Вы не являетесь владельцем токена';
            return false;
        }
        return true;
    }

    public function message(): string
    {
        return $this->errorMessage ?? 'Error';
    }
}
