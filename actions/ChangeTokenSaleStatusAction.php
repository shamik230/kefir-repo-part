<?php

namespace Marketplace\Tokens\Actions;

use Auth;
use Marketplace\Tokens\Models\Token;
use Marketplace\Transactions\Actions\CreateTransactionAction;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;

class ChangeTokenSaleStatusAction
{
    function execute(
        Token   $token,
        bool    $isSale,
        ?float  $price = null,
        bool    $notOwner = false,
        bool    $sync = false,
        ?string $hash = null
    ): Token
    {
        $token->update(['pending_at' => null]);
        $price_amount = 0;
        /** @var CreateTransactionAction $createTransaction */
        $createTransaction = app(CreateTransactionAction::class);

        if ($token->price) {
            $price_amount = $price - $token->price;
        }

        if ($price) {
            $new_price = $price;

            if ($new_price != $token->price) {
                $price_amount = $new_price - $token->price;
                $token->price = $new_price;
            }
        }

        $token->loadMissing('collection'); // todo есть экшон, подсчитывающий amount коллекции
        $token->collection->amount += $price_amount;
        $token->collection->save();

        $token->is_sale = $isSale;
        $token->save();

        if (!$sync) {
            $createTransaction->execute(
                $token,
                $notOwner ? env('SYSTEM_USER_ID', 550) : Auth::id(),
                $isSale
                    ? TransactionTypes::TYPE_EXPOSED_FOR_SALE_ID
                    : TransactionTypes::TYPE_REMOVED_FROM_SALE_ID,
                Transaction::STATUS_SUCCESS,
                $hash ?: null
            );
        }
        return $token;
    }
}
