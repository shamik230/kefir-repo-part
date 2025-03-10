<?php

namespace Marketplace\Tokens\Services;

use Marketplace\Collections\Models\Collection;
use Marketplace\Payments\Models\Payment;
use Marketplace\Tokens\Dto\BlockchainDropMiniShopResponseDto;
use Marketplace\Tokens\Models\DropMiniShop;
use Marketplace\Tokens\Models\DropMiniShopSettings;
use Marketplace\Traits\KefiriumBot;
use Marketplace\Traits\Logger;
use Project\Services\RequestsService;
use RainLab\User\Models\User;

class DropMiniShopService
{
    use KefiriumBot, Logger;

    const BLOCKCHAIN_WHITELIST_MINI_FACTORY_URL = 'minifactory/isWhitelisted';
    const BLOCKCHAIN_MINT_URL = 'minifactory/mint';
    const BLOCKCHAIN_MINT_GET_INFO_TOKEN = 'minifactory/getTokenInfo';

    const BLOCKCHAIN_MINT_GET_INFO_TOKEN_BY_ID = 'minifactory/getTokenInfoById';

    /** @var RequestsService */
    protected $request;

    function __construct()
    {
        $apiKey = config('blockchain.api_key');

        $baseUrl = config('blockchain.api_url');

        $this->request = new RequestsService(
            $baseUrl,
            ['headers' => ['X-API-KEY' => $apiKey]],
            RequestsService::CHANNEL_BLOCKCHAIN
        );
    }

    function isInDropWhitelist(string $walletAddress, string $stage = null): bool
    {
        $response = $this->request->get(self::BLOCKCHAIN_WHITELIST_MINI_FACTORY_URL, 1, [
            'to' => $walletAddress,
            'stage' => $stage,
        ]);

        if ($response->failed() || $response->status() != 200) {
            $this->tgNotification("Ошибка проверки вайтлиста 1 " . $response->content() . " ");
        }
        $data = $response->payload();

        return $response->success() && isset($data['data']) && $data['data'] === true;
    }

    function dropWhitelist(string $walletAddress): array
    {
        $responseBySoc = $this->request->get(self::BLOCKCHAIN_WHITELIST_MINI_FACTORY_URL, 1, [
            'to' => $walletAddress,
            'stage' => 'soc',
        ]);

        $responseByPrivate = $this->request->get(self::BLOCKCHAIN_WHITELIST_MINI_FACTORY_URL, 1, [
            'to' => $walletAddress,
            'stage' => 'private',
        ]);

        if ($responseBySoc->failed() || $responseBySoc->status() != 200) {
            $this->tgNotification("Ошибка проверки вайтлиста 2 " . $responseBySoc->content() . " ");
        } elseif ($responseByPrivate->failed() || $responseByPrivate->status() != 200) {
            $this->tgNotification("Ошибка проверки вайтлиста 3 " . $responseByPrivate->content() . " ");
        }
        $dataSoc = $responseBySoc->payload();
        $dataPrivate = $responseByPrivate->payload();
        return ['soc' => $dataSoc['data'], 'private' => $dataPrivate['data']];
    }

    function dropMintRequest(string $walletAddress, string $stage, int $count = 1): array
    {
        $requestParams = [
            'to' => $walletAddress,
            'stage' => $stage,
            'count' => $count,
        ];
        if (env('DROP_MINI_SHOP_PENDING', false)) {
            $requestParams['time'] = env('DROP_MINI_SHOP_PENDING_TIME', 10);
            $requestParams['confirmations'] = env('DROP_MINI_SHOP_PENDING_CONFIRMATIONS', 1);
        }
        $response = new BlockchainDropMiniShopResponseDto($this->request->put(self::BLOCKCHAIN_MINT_URL, 1, $requestParams));

        if ($response->success()) {
            return $this->res('success', 'Минт прошел успешно', $response->payload());
        } elseif ($response->pending()) {
            return $this->res('pending', 'Минт прошел успешно', $response->payload());
        }
        return $this->res('error', 'Блокчейн транзакция в обработке, пожалуйста, ожидайте. Ваш токен появится в личном кабинете.', $response->payload());
    }

    function getTokenInfo(string $hash): array
    {
        $response = $this->request->get(self::BLOCKCHAIN_MINT_GET_INFO_TOKEN, 1, [
            'hash' => $hash
        ]);
        return $this->res('success', 'Получение информации о токене', $response->payload());
    }

    function getTokenInfoById(string $tokenId): array
    {
        $response = $this->request->get(self::BLOCKCHAIN_MINT_GET_INFO_TOKEN_BY_ID, 1, [
            'tokenId' => $tokenId
        ]);
        return $this->res('success', 'Получение информации о токене', $response->payload());
    }

    function updateConstraints(
        string $stage,
        string $wallet,
        int     $count = 1,
        string  $currency = DropMiniShopSettings::CURRENCY_CASH,
        Payment $mintPayment = null
    ): bool {
        switch ($stage) {
            case DropMiniShopSettings::CLOSE_DROP_KEY:
                if ($count === DropMiniShopSettings::CLOSE_DROP_COUNT_THREE) {
//                    DropMiniShop::query()
//                        ->where('wallet_address', $wallet)
//                        ->update(['tokens_available' => 0]);
                } else {
//                    DropMiniShop::query()
//                        ->where('wallet_address', $wallet)
//                        ->decrement('tokens_available', 1);
                }
                break;
            case DropMiniShopSettings::OPEN_DROP_KEY:
                $mintPayment->loadMissing(['user']);
                if ($currency == DropMiniShopSettings::CURRENCY_KEFIRIUM) {
                    DropMiniShopSettings::increasePriceKefir();
                } else {
                    DropMiniShopSettings::increasePriceRur();
                }
                break;
            case DropMiniShopSettings::FREE_DROP_KEY:
//                DropMiniShop::query()
//                    ->where('wallet_address', $wallet)
//                    ->update(['free_tokens_available' => 0]);
                break;
            default:
                break;
        }
        return true;
    }

    function setProcessingDrop(string $wallet, bool $process = true)
    {
        $this->logRequests('Логи установки ограничений', ['perocessing' => $process, 'wallet' => $wallet]);
        DropMiniShop::query()->where('wallet_address', $wallet)->update(['processing' => $process]);
    }

    function res(string $status, string $message, $data): array
    {
        return [
            'status' => $status,
            'data' => $data,
            'message' => $message,
        ];
    }

    /**
     * Get User_id DropMiniShop Collection
     * @return Int|null
     */
    function getUserIdCollectionDropMiniShop(): ?Int
    {
        return Collection::find(intval(env('DROP_MINI_SHOP_COLLECTION', 1)))->user_id ?? null;
    }
}
