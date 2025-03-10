<?php

namespace Marketplace\Tokens\Actions\Commission;

use Marketplace\Tokens\Models\Token;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use RainLab\User\Models\User;

class CalculateCommissionForEntityAction
{
    function execute(Token $token, User $seller): float
    {
        $price = $token->price;

        switch (true) {
            case $this->privilegedAuthor($token):
                return $price * 0.035;
            case $this->sellerHasBonusToken($seller):
                return $price * 0.035;
//            case $this->secondSaleOrExternalToken($token):
//                return $price * 0.05 + 35;
            default: // case $this->firstSale($token):
                return $price * 0.10 + 35;
        }
    }

    private function privilegedAuthor(Token $token): bool
    {
        $token->loadMissing(['author.legal']);
        return $token->author->legal->inn == env('TAL_INN');
    }

    private function sellerHasBonusToken(User $seller): bool
    {
        return $seller->tokens()
            ->where('collection_id', env('COLLECTION_ID_KOTKEFIRIUM'))
            ->exists();
    }

    private function secondSaleOrExternalToken(Token $token): bool
    {
        return $token->transactions()
                ->where('transaction_types_id', TransactionTypes::TYPE_SALE_ID)
                ->count() > 0;
    }

    private function firstSale(Token $token): bool
    {
        return $token->transactions()
                ->where('transaction_types_id', TransactionTypes::TYPE_SALE_ID)
                ->count() == 0;
    }
}
