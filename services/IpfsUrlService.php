<?php

namespace Marketplace\Tokens\Services;


class IpfsUrlService
{
    /**
     * Обрабатывает URL и возвращает IPFS-хэш и нормализованный URL.
     *
     * @param string $originalUrl
     * @return array|null Возвращает массив с ключами 'ipfs' и 'normalizedUrl', или null, если паттерн не совпал.
     */
    public function processUrl(string $originalUrl): ?array
    {
        $normalizedUrl = strtolower(trim($originalUrl));
        $pattern = '/^http.+\/ipfs\/(.+$)|^ipfs:\/\/(.+$)|^http[s?]:\/\/(.+)\.ipfs\..+(\/.+$)/';

        if (preg_match($pattern, $originalUrl, $matches)) {
            $ipfs = '';

            if (isset($matches[3])) {
                $ipfs = $matches[3] . $matches[4];
            } else {
                $ipfs = $matches[2] ?? $matches[1];
            }

            $normalizedUrl = env('PINATA_URL', 'https://gateway.pinata.cloud/ipfs/') . $ipfs;

            return [
                'ipfs' => $ipfs,
                'normalizedUrl' => $normalizedUrl,
            ];
        }

        return null;
    }
}
