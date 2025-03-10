<?php

namespace Marketplace\Tokens\Services;

use Log;
use Marketplace\Factory\Models\Factory;
use Marketplace\Fiat\Models\Fiat;
use Marketplace\Module\actions\CreateModuleToTokenAction;
use Marketplace\Payments\Models\Payment;
use Marketplace\Module\Models\Module;
use Marketplace\Tokens\Actions\CreateTokenFromTokenToImportAction;
use Marketplace\TokensToImport\Models\TokenToImport;
use Marketplace\Traits\SendEmail;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;

class CreateMintDropMiniShopService
{
    use SendEmail;
    const IPFS_MIRROR = 'https://gateway.pinata.cloud/ipfs/';

    protected $createTokenFromTokenToImportAction;
    protected $createModuleToTokenAction;

    function __construct()
    {
        $this->createTokenFromTokenToImportAction = app(CreateTokenFromTokenToImportAction::class);
        $this->createModuleToTokenAction = app(CreateModuleToTokenAction::class);
    }

    function execute(array $tokenMintData, Transaction $transaction, Payment $mintPayment): array
    {
        $transaction->loadMissing(['from_payment']);
        $paymentSale = Payment::query()
            ->where('transaction_uuid', $mintPayment->transaction_uuid)
            ->where('type', Payment::TYPE_MINT_SALE)->first();

        // Find completed payment
        $paymentMintCompleted = Payment::query()->createMint(
            $transaction->from_user_id,
            $transaction->price,
            Payment::TYPE_MINT_COMPLETED,
            $mintPayment->response ?? null,
            null,
            Fiat::class,
            $mintPayment->transaction_uuid ?? null
        );

        $urlParts = explode('/', str_replace(['https://gateway.pinata.cloud/ipfs/'], [''], $tokenMintData['image']));
        $collectionId = intval(env('DROP_MINI_SHOP_COLLECTION'));
        $tokenPrice = $mintPayment->amount;

        // Create TokenToImport
        $tokenToImportData = [
            'external_id' => $tokenMintData['token_id'],
            'external_address' => env('DROP_MINI_SHOP_ADDRESS', '0x4EeB9388c579015c754E74B28D79bD8eC439414b'),
            'symbol' => env('SYMBOL_MINIFACTORY'),
            'mime' => null,
            'ipfs_url' => $urlParts[0],
            'name' => $tokenMintData['token_name'],
            'description' => $tokenMintData['token_description'],
            'preview_url' => $tokenMintData['image'],
            'page' => 1,
        ];

        $tokenToImport = TokenToImport::make($tokenToImportData);
        $tokenToImport->user_id = $mintPayment->user_id;
        $tokenToImport->id = $tokenMintData['token_id'];

        // Create Token
        $token = $this->createTokenFromTokenToImportAction->execute(
            $tokenToImport,
            $collectionId,
            $transaction->transaction_hash,
            $tokenPrice,
            false,
            true,
            Transaction::CURRENCY_RUR_TYPE
        );

        if (!$token) {
            return ['status' => 'error', 'message' => 'Ошибка при импорте токена'];
        }

        // Update transaction
        Transaction::query()
            ->where('transaction_types_id', TransactionTypes::TYPE_IMPORT_TOKEN_ID)
            ->where('token_id', $token->id)
            ->update([
                'from_payment_id' => $mintPayment->id,
                'price' => $transaction->price,
                'status' => Transaction::STATUS_SUCCESS,
                'sale_completed_payment_id' => $paymentMintCompleted->id,
            ]);
        Log::info('json token: ' . var_export($token, true));
        $module = $this->createModuleToTokenAction->execute($token->id, $tokenMintData['attributes']['module'] ?? $tokenMintData['attributes']['typeId']);
        Log::info('TokenEquipment: ' . var_export($module, true));
        $this->sendMintMail($token, $mintPayment);

        // Update payment
        $mintPayment->update(['token_id' => $token->id]);

        // Update completed payment
        $paymentMintCompleted->update(['token_id' => $token->id, 'amount' => $token->price]);
        if ($paymentSale){
            $paymentSale->update(['token_id' => $token->id, 'amount' => $token->price]);
        }

        return ['status' => 'success', 'token' => $token];
    }
}
