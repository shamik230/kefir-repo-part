<?php

namespace Marketplace\Tokens\Services;

use Arr;
use Exception;
use GuzzleHttp\Client;

class ChainBoxService
{
    protected $bcApi;

    public function __construct()
    {
        $this->bcApi = new Client([
            'base_uri' => env('BLOCKCHAIN_API_URL'),
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'X-API-Key' => env('API_KEY'),
            ]
        ]);
    }

    public function mintBox(string $to, string $stage): array
    {
        $path = 'boxes/mint';
        $body = [
            'to' => $to,
            'stage' => $stage,
        ];
        \Log::debug("BC request body {$path}", [$body]);

        try {
            $response = $this->bcApi->put($path, [
                'json' => $body,
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug("BC response body {$path}", [$responseBody]);

            if (isset($responseBody['data']['error'])) {
                throw new Exception($responseBody['data']['data']);
            }

            return (array) $responseBody['data'];
        } catch (\Exception $e) {
            \Log::error('Error in ' . __METHOD__, ['exception' => $e]);
            throw $e;
        }
    }

    public function openBox(int $tokenId, string $address): array
    {
        $path = 'boxes/openBox';
        $body = [
            'tokenId' => $tokenId,
            'owner' => $address,
        ];
        \Log::debug("BC request body {$path}", [$body]);

        try {
            $response = $this->bcApi->put($path, [
                'json' => $body,
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug("BC response body {$path}", [$responseBody]);

            return (array) $responseBody['data'];
        } catch (\Exception $e) {
            \Log::error('Error in ' . __METHOD__, ['exception' => $e]);
            throw $e;
        }
    }

    public function isWhitelisted(string $address): bool
    {
        try {
            $response = $this->bcApi->get('boxes/isWhitelisted', [
                'query' => [
                    'to' => strtolower($address),
                ],
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug('BC response body boxes/isWhitelisted', [$responseBody]);

            return (bool) $responseBody['data'];
        } catch (\Exception $e) {
            \Log::error('Error in ' . __METHOD__, ['exception' => $e]);
            return false;
        }
    }

    public function getTokenInfoByHash(string $hash): array
    {
        try {
            $response = $this->bcApi->get('boxes/getTokenInfo', [
                'query' => [
                    'hash' => $hash,
                ],
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug('BC response body boxes/getTokenInfo', [$responseBody]);

            return (array) $responseBody['data']['token'];
        } catch (\Exception $e) {
            \Log::error('Error in ' . __METHOD__, ['exception' => $e]);
            throw $e;
        }
    }

    public function getBoxUri(string $hash): array
    {
        try {
            $response = $this->bcApi->get('boxes/tokenUri', [
                'query' => [
                    'hash' => $hash,
                ],
            ]);

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug('BC response body boxes/tokenUri', [$responseBody]);

            return (array) $responseBody['data'];
        } catch (\Exception $e) {
            \Log::error('Error in ' . __METHOD__, ['exception' => $e]);
            throw $e;
        }
    }

    public function getContractInfo(): array
    {
        try {
            $response = $this->bcApi->get('boxes/contractInfo');

            $responseBody = json_decode($response->getBody(), true);
            \Log::debug('BC response body boxes/contractInfo', [$responseBody]);

            return (array) $responseBody['data'];
        } catch (\Exception $e) {
            \Log::error('Error in ' . __METHOD__, ['exception' => $e]);
            throw $e;
        }
    }
}
