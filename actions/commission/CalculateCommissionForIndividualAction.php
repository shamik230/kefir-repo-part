<?php

namespace Marketplace\Tokens\Actions\Commission;

use Marketplace\Payments\Models\PaymentSettings;
use Marketplace\Tokens\Models\Token;
use Marketplace\Traits\Logger;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use RainLab\User\Models\User;

class CalculateCommissionForIndividualAction
{
    use Logger;

    function execute(Token $token, User $seller, int $paymentId): float
    {
        $this->logPayment('Commission calculation for: ', [
            'token' => $token->id,
            'seller' => $seller->id,
            'payment' => $paymentId
        ]);
        switch (true) {
            case $this->privilegedSeller($seller):
                [$const, $percent] = PaymentSettings::indPrivilegedSeller();
                $logTitle = 'Privileged seller';
                break;
            case $this->sellerWithBonus($seller):
                [$const, $percent] = PaymentSettings::indSellerWithBonus();
                $logTitle = 'The seller is the owner of the bonus NFT';
                break;
            case $this->secondSaleOrPolygon($token):
                [$const, $percent] = PaymentSettings::indSecondSaleOrPolygon();
                $logTitle = 'Second sale or Polygon token';
                break;
            default: // first sale
                [$const, $percent] = PaymentSettings::indFirstSale();
                $logTitle = 'Default commission';
                break;
        }
        $this->logPayment($logTitle, [
            'const' => $const,
            'percent' => $percent,
            'price' => $token->price,
            'commission' => round(($token->price * $percent / 100) + $const, 2),
        ]);

        return round(($token->price * $percent / 100) + $const, 2);
    }

    protected function privilegedSeller(User $seller): bool
    {
        return $seller->legal->inn == env('TAL_INN');
    }

    protected function sellerWithBonus(User $seller): bool
    {
        return $seller->tokens()
            ->where('collection_id', env('COLLECTION_ID_KOTKEFIRIUM'))
            ->exists();
    }

    protected function secondSaleOrPolygon(Token $token): bool
    {
        return $token->transactions()
                ->where('transaction_types_id', TransactionTypes::TYPE_SALE_ID)
                ->count() > 1
            || $token->external_id;
    }
}
