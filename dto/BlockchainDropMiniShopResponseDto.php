<?php

namespace Marketplace\Tokens\Dto;

use Marketplace\Traits\KefiriumBot;
use Project\DTO\ResponseDto;

class BlockchainDropMiniShopResponseDto
{
    use KefiriumBot;

    const SUCCESSFUL_REQUEST_CODE = 1;
    const PENDING_REQUEST_CODE = 2;

    /** @var bool */
    protected $failed;

    /** @var array */
    protected $payload;

    /** @var bool */
    protected $success = false;

    /** @var bool */
    protected $pending = false;

    function __construct(ResponseDto $responseDto)
    {
        $this->failed = $responseDto->failed() || !$responseDto->success();
        $this->payload = $responseDto->payload();
        if ($this->failed) {
            $this->tgNotification("Ошибка при минте " . $responseDto->content() . " ");
        }
        $this->success = $responseDto->success()
            && isset($this->payload['data']['hash'])
            && ($this->payload['data']['code'] == self::SUCCESSFUL_REQUEST_CODE)
            && isset($this->payload['data']['details']['tokens'][0]['token_id']);

        $this->pending = ($responseDto->success()
            && isset($this->payload['data']['hash'])
            && ($this->payload['data']['code'] == self::PENDING_REQUEST_CODE));
    }

    function success(): bool
    {
        return $this->success;
    }

    function pending(): bool
    {
        return $this->pending;
    }

    function payload(): array
    {
        return $this->payload;
    }

    function failed()
    {
        return $this->failed;
    }
}
