<?php

namespace Marketplace\Tokens\Services;

use Exception;
use Marketplace\Collections\Actions\UpdateCollectionSumAction;
use Marketplace\Fiat\Models\PaymentSystemSBP;
use Marketplace\KefiriumCashback\Models\KefiriumCashback;
use Marketplace\Lots\Actions\SendTokenTransferLotEmailAction;
use Marketplace\Payments\CommissionPaymentJob;
use Marketplace\Payments\Models\Payment;
use Marketplace\Payments\RoyaltyJob;
use Marketplace\Tokens\Dto\BlockchainGetItemResponseDto;
use Marketplace\Tokens\Dto\BlockchainPurchaseReadinessResponseDto;
use Marketplace\Tokens\Dto\BlockchainRefreshTokenResponseDto;
use Marketplace\Tokens\Dto\BlockchainResponseDto;
use Marketplace\Tokens\Dto\BlockchainTransferTokenResponseDto;
use Marketplace\Tokens\Models\Token;
use Marketplace\Traits\KefiriumBot;
use Marketplace\Traits\Logger;
use Marketplace\Traits\SendEmail;
use Marketplace\Transactions\Models\Transaction;
use Marketplace\Transactiontypes\Models\TransactionTypes;
use Project\Services\RequestsService;
use Queue;
use RainLab\User\Models\User;
use RainLab\User\Services\PushWebsocketService;

class TokenService
{
    use KefiriumBot, SendEmail, Logger;

    const BLOCKCHAIN_PUT_FOR_SALE_URL = 'marketplace/createItem';
    const BLOCKCHAIN_REMOVE_FROM_SALE_URL = 'marketplace/removeItem';
    const BLOCKCHAIN_TRANSFER = 'transfer';
    const BLOCKCHAIN_ITEMS_URL = 'marketplace/items';
    const PURCHASE_READINESS_URL = 'marketplace/getPurchaseStatus';
    const BLOCKCHAIN_PURCHASE_URL = 'marketplace/purchase';

    /** @var RequestsService */
    protected $request;

    /** @var PushWebsocketService */
    protected $pushWebsocketService;

    /** @var UpdateCollectionSumAction */
    protected $updateCollectionSumAction;

    /** @var SendTokenTransferLotEmailAction */
    private $sendTokenTransferLotAction;

    function __construct()
    {
        $apiKey = config('blockchain.api_key');
        $baseUrl = config('blockchain.api_url');
        $this->request = new RequestsService(
            $baseUrl,
            ['headers' => ['X-API-KEY' => $apiKey]],
            RequestsService::CHANNEL_BLOCKCHAIN
        );
        $this->updateCollectionSumAction = app(UpdateCollectionSumAction::class);
        $this->pushWebsocketService = app(PushWebsocketService::class);
        $this->sendTokenTransferLotAction = app(SendTokenTransferLotEmailAction::class);
    }

    /**
     * Выставить токен на продажу в бч
     *
     * @param Token $token
     * @param float $price
     * @return BlockchainResponseDto
     */
    function putForBlockchainSale(Token $token, float $price): BlockchainResponseDto
    {
        $response = $this->request->postJson(self::BLOCKCHAIN_PUT_FOR_SALE_URL, 1, [
            'tokenId' => $token->external_id,
            'nftContract' => $token->external_address,
            'price' => $price,
            'time' => 0,
            'confirmation' => 0,
        ]);

        if ($response->failed()) {
            $this->tgNotification("Ошибка при выставлении токена на продажу " . $response->content() . " ");
        }

        $response = new BlockchainResponseDto($response);

        if ($response->success() && !$response->status()->failed()) {
            // Create pending transactions, lock token
            $token->update(['pending_at' => now()]);

            $pendingTransaction = Transaction::query()->createExposeForSale(
                $token->user_id,
                $token->id,
                $price,
                $response->hash(),
                $response->status()->toString(),
                $token->currency
            );
            $response->setExposedForSaleTransaction($pendingTransaction);

            if ($response->status()->success()) {
                $this->putForSale($token, $price, $pendingTransaction);
            }
        }

        return $response;
    }

    function removeFromBlockchainSale(Token $token): BlockchainResponseDto
    {
        $response = $this->request->postJson(self::BLOCKCHAIN_REMOVE_FROM_SALE_URL, 1, [
            'tokenId' => $token->external_id,
            'nftContract' => $token->external_address,
            'time' => 0,
            'confirmation' => 0,
        ]);

        if ($response->failed()) {
            $this->tgNotification("Ошибка при снятии токена с продажи " . $response->content());
        }

        $response = new BlockchainResponseDto($response);

        if ($response->success() && !$response->status()->failed()) {
            // Create pending transactions, lock token
            $token->update(['pending_at' => now()]);

            $pendingTransaction = Transaction::query()->createRemovedFromSale(
                $token->user_id,
                $token->id,
                $response->hash(),
                $response->status()->toString()
            );
            $response->setRemovedFromSaleTransaction($pendingTransaction);

            if ($response->status()->success()) {
                $this->removeFromSale($token, $pendingTransaction);
            }
        }

        return $response;
    }

    function purchaseTokenInBlockchain(
        Token   $token,
        Payment $buyPayment,
        Payment $salePayment,
        string  $transactionUuid,
        string  $nftBuyer
    ): BlockchainResponseDto {
        $response = $this->request->put(self::BLOCKCHAIN_PURCHASE_URL, 1, [
            'nftContract' => $token->external_address,
            'tokenId' => $token->external_id,
            'buyer' => $nftBuyer,
            'paymentId' => $buyPayment->id,
        ]);

        $bchResponse = new BlockchainResponseDto($response);

        if ($bchResponse->success()) {
            if (!$bchResponse->status()->failed()) {
                $token->update(['pending_at' => now()]);
            }
            $saleTransaction = Transaction::query()->createSale(
                $salePayment->id,
                $salePayment->user_id,
                $buyPayment->id,
                $token->id,
                $token->price,
                null,
                $bchResponse->hash(),
                $bchResponse->status()->toString(),
                Transaction::CURRENCY_RUR_TYPE,
                $buyPayment->user_id
            );

            $responseBuyPayment = json_decode($buyPayment->response, true) ?? [];
            $responseBuyPayment['data']['bch_purchase_transaction_hash'] = $bchResponse->hash();
            $buyPayment->response = json_encode($responseBuyPayment);
            $buyPayment->save();

            $bchResponse->setSaleTransaction($saleTransaction);
            if ($bchResponse->status()->success()) {
                $this->transfer(
                    $token,
                    $buyPayment,
                    $salePayment,
                    $saleTransaction,
                    $transactionUuid,
                    $response->payload()
                );
            }
        }
        return $bchResponse;
    }

    /**
     * Обновить статус транзакции, создать платежи, сбросить статус занятости токена
     *
     * @param Token $token
     * @param float $price
     * @param Transaction $exposeForSaleTransaction
     * @return void
     */
    function putForSale(Token $token, float $price, Transaction $exposeForSaleTransaction): void
    {
        $token->loadMissing(['collection']);

        // Update token
        $token->update([
            'is_sale' => true,
            'pending_at' => null,
            'price' => $price,
        ]);

        // Update collection sum
        $this->updateCollectionSumAction->execute($token->collection);

        // Update pending transaction if needed
        if ($exposeForSaleTransaction->isPending()) {
            $exposeForSaleTransaction->update(['status' => Transaction::STATUS_SUCCESS]);
        }

        $this->pushWebsocketService->pushTransaction($exposeForSaleTransaction);
        $this->pushWebsocketService->pushToken($token);
    }

    /**
     * Обновить статус транзакции, создать платежи, сбросить статус занятости токена
     *
     * @param Token $token
     * @param Transaction $exposeForSaleTransaction
     * @return void
     */
    function removeFromSale(Token $token, Transaction $exposeForSaleTransaction): void
    {
        $token->loadMissing(['collection']);

        // Update token
        $token->update([
            'is_sale' => false,
            'pending_at' => null, // todo залить
        ]);

        // Update collection sum
        $this->updateCollectionSumAction->execute($token->collection);

        // Update pending transaction if needed
        if ($exposeForSaleTransaction->isPending()) {
            $exposeForSaleTransaction->update(['status' => Transaction::STATUS_SUCCESS]);
        }

        $this->pushWebsocketService->pushTransaction($exposeForSaleTransaction);
        $this->pushWebsocketService->pushToken($token);
    }

    function transfer(
        Token       $token,
        Payment     $buyPayment,
        ?Payment    $salePayment, // null в PaymentController@returnTokenAuthor
        Transaction $saleTransaction,
        string      $transactionUuid,
        array       $requestPayload
    ) {
        $response = json_encode($requestPayload, JSON_UNESCAPED_UNICODE);
        $sellerEmail = $token->user->email;

        $saleCompletedPayment = Payment::query()->createSaleCompleted(
            $buyPayment->user_id,
            $salePayment ? $salePayment->user_id : null,
            $token,
            $transactionUuid,
            $response,
            $buyPayment->payment_system_id,
            $buyPayment->payment_system_type
        );
        $transferTransaction = Transaction::query()->createTransfer(
            $salePayment ? $salePayment->id : null,
            $salePayment ? $salePayment->user_id : null,
            $buyPayment->id,
            $token->id,
            $token->price,
            Transaction::STATUS_SUCCESS,
            $saleTransaction->transaction_hash,
            $saleCompletedPayment->id,
            $buyPayment->user_id
        );
        $saleTransaction->update([
            'status' => Transaction::STATUS_SUCCESS,
            'sale_completed_payment_id' => $saleCompletedPayment->id,
        ]);

        $buyPayment->update([
            'response' => $response,
            'completed_at' => now(),
        ]);
        if ($salePayment) {
            $salePayment->update([
                'response' => $response,
                'completed_at' => now(),
            ]);
        }

        $token->update([
            'pending_at' => null,
            'is_booked' => false,
            'is_sale' => false,
            'user_id' => $buyPayment->user_id,
        ]);
        $this->pushWebsocketService->pushTransaction($transferTransaction);
        $this->pushWebsocketService->pushTransaction($saleTransaction);
        $this->pushWebsocketService->pushToken($token->refresh());

        // Mails & jobs todo Класть в очередь

        $this->sendEmail(
            'token.buy',
            [
                'name' => $token->name,
                'link' => url("/collection/token/$token->id"),
                'price' => $token->price,
                'liters' => $saleTransaction->cashback()
                    ->where('type', KefiriumCashback::TYPE_PURCHASE)
                    ->value('amount'),
                'date' => $buyPayment->created_at,
            ],
            $sellerEmail,
            'TokenService@transfer'
        );

        if ($salePayment) {
            $jobsData = [
                'user_id' => $salePayment->user->id,
                'token' => $token,
                'id' => $salePayment->user->id,
                'paymentId' => $salePayment->id,
            ];
            Queue::push(RoyaltyJob::class, $jobsData);
            if ($buyPayment->user->isIndividual() && app()->environment(['prod', 'production']) && $buyPayment->payment_system_type != PaymentSystemSBP::class) {
                Queue::push(CommissionPaymentJob::class, $jobsData);
            }
        }
    }

    /**
     * Синхронизировать состояние токена без создания платежей/транзакций
     *
     * @param Token $token
     * @param bool $onSale
     * @param float $price
     * @return void
     */
    function syncStatus(Token $token, bool $onSale, float $price): void
    {
        $token->loadMissing(['collection']);
        $token->update([
            'is_sale' => $onSale,
            'price' => $price,
            'pending_at' => null,
        ]);

        $this->updateCollectionSumAction->execute($token->collection);
    }

    /**
     * Проверить, не находится ли токен на продаже в БЧ
     *
     * @param Token $token
     * @return BlockchainGetItemResponseDto
     */
    function getBlockchainItem(Token $token): BlockchainGetItemResponseDto
    {
        $response = $this->request->get(self::BLOCKCHAIN_ITEMS_URL, 1, [
            'nftContract' => $token->external_address,
            'tokenId' => $token->external_id,
        ]);

        return new BlockchainGetItemResponseDto($response);
    }

    /**
     * Проверить, готов ли токен к продаже
     *
     * @param Token $token
     * @return BlockchainPurchaseReadinessResponseDto
     */
    function checkBlockchainPurchaseReadiness(Token $token): BlockchainPurchaseReadinessResponseDto
    {
        $response = $this->request->get(self::PURCHASE_READINESS_URL, 1, [
            'nftContract' => $token->external_address,
            'tokenId' => $token->external_id,
        ]);

        return new BlockchainPurchaseReadinessResponseDto($response);
    }

    /**
     * Обновить статус транзакции, сбросить статус занятости токена changeOwner
     *
     * @param Token $token
     * @param Transaction $transaction
     * @return void
     */
    function changeOwner(Token $token, Transaction $transaction): void
    {
        // Update token
        $token->update(['pending_at' => null,]);
        $transaction->update(['status' => Transaction::STATUS_SUCCESS]);

        $this->pushWebsocketService->pushTransaction($transaction);
        $this->pushWebsocketService->pushToken($token);
    }

    /**
     * Рефреш токена
     *
     * @param Token $token
     */
    function refreshTokenBlockchain(Token $token)
    {
        $url = 'collection/' . $token->external_address . '/' . $token->external_id . '/refreshMetadata';
        $response = $this->request->get($url, 1, ['address' => $token->external_address, 'tokenId' => $token->external_id]);

        return new BlockchainRefreshTokenResponseDto($response);
    }

    /**
     * Трансфер токена
     *
     * @param Token $token
     * @param string $newOwner
     * @return BlockchainTransferTokenResponseDto
     */
    function transferTokenBlockchain(Token $token, string $newOwner): BlockchainTransferTokenResponseDto
    {
        $response = $this->request->put(self::BLOCKCHAIN_TRANSFER, 1, [
            'nftContract' => $token->external_address,
            'tokenId' => $token->external_id,
            'newOwner' => $newOwner
        ]);

        return new BlockchainTransferTokenResponseDto($response);
    }

    function changeOwnerFromTransfer(
        Token       $token,
        Transaction $transaction
    ) {
        if ($transaction->transaction_types_id != TransactionTypes::TYPE_FREE_TRANSFER) {
            $this->logDebug('error@changeOwnerFromTransfer', ['Неверный тип транзакции', 'transaction_id' => $transaction->id]);
            throw new Exception('Неверная транзакция');
        } else {
            $token->update([
                'user_id' => $transaction->to_user_id,
            ]);

            if ($transaction->lot_id) {
                $this->sendTokenTransferLotAction->execute($transaction, $token);
                $this->pushWebsocketService->pushTransferAuction($transaction);
            }

            $this->pushWebsocketService->pushTransaction($transaction);
            $this->pushWebsocketService->pushToken($token);
        }
    }

    function simpleTransfer(Token $token, Transaction $transaction, User $toUser = null, string $newOwner = null): array
    {

        if ($this->isBlockchainTransfer($token, $newOwner)) {
            $responseTransfer = $this->transferTokenBlockchain($token, $newOwner);
            $responseData = $responseTransfer->payload();
            if ($responseData && !empty($responseData['data']['hash'])) {
                $transaction->update(['transaction_hash' => $responseData['data']['hash']]);
                $this->pushWebsocketService->pushTransaction($transaction);
            }

            if ($responseTransfer->success()) {
                $transaction->update(['status' => Transaction::STATUS_SUCCESS]);
                $this->changeOwnerFromTransfer($token, $transaction);
                return ['kefir_new_owner' => $toUser, 'blockchain_new_owner' => Transaction::STATUS_SUCCESS];
            } else if ($responseTransfer->pending()) {
                return ['kefir_new_owner' => $toUser, 'blockchain_new_owner' => Transaction::STATUS_PENDING];
            }

            $transaction->update(['status' => Transaction::STATUS_ERROR]);
            $this->pushWebsocketService->pushTransaction($transaction);
            return ['kefir_new_owner' => $toUser, 'blockchain_new_owner' => Transaction::STATUS_ERROR];
        } else if ($toUser) {
            $transaction->update(['status' => Transaction::STATUS_SUCCESS]);
            $this->changeOwnerFromTransfer($token, $transaction);
            return ['kefir_new_owner' => $toUser, 'blockchain_new_owner' => false];
        }
        return ['kefir_new_owner' => false, 'blockchain_new_owner' => false];
    }

    /**
     * Проверка на необходимость передачи токена в БЧ
     *
     * @param Token $token
     * @param string|null $newOwner
     * @return bool
     */
    function isBlockchainTransfer(Token $token, ?string $newOwner): bool
    {
        return $token->external_id && $token->external_address && $newOwner;
    }

    public function extractIpfsPath(string $url)
    {
        $parsedUrl = parse_url($url);

        if (!isset($parsedUrl['path'])) {
            return null;
        }

        $path = $parsedUrl['path'];
        $segments = explode('/', $path);
        $ipfsPath = null;

        if (isset($segments[2])) {
            $ipfsPath = "{$segments[2]}";
        }

        if (isset($segments[3])) {
            $ipfsPath .= "/{$segments[3]}";
        }

        if (isset($segments[4])) {
            $ipfsPath .= "/{$segments[4]}";
        }

        return $ipfsPath;
    }
}
