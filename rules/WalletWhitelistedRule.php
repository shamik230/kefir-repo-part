<?php

namespace Marketplace\Tokens\Rules;

use Illuminate\Contracts\Validation\Rule;
use Marketplace\Payments\Services\BlockchainService;
use Marketplace\Tokens\Services\DropMiniShopService;

class WalletWhitelistedRule implements Rule
{
    protected $dropMiniservice;
    protected $walletAddress;
    protected $stage;

    function __construct(string $walletAddress, string $stage)
    {
        $this->dropMiniservice = new DropMiniShopService();
        $this->walletAddress = $walletAddress;
        $this->stage = $stage;
    }

    function passes($attribute, $value): bool
    {
        return $this->dropMiniservice->isInDropWhitelist($this->walletAddress, $this->stage);
    }

    function message(): string
    {
        return 'Кошелек не прошел WhiteList';
    }
}
