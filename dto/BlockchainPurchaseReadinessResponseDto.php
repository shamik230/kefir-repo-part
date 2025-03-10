<?php

namespace Marketplace\Tokens\Dto;

use Project\DTO\ResponseDto;

class BlockchainPurchaseReadinessResponseDto
{
    const SUCCESS_STATUS = 'ok';

    /** @var string */
    protected $error;

    /** @var bool */
    protected $success;

    /** @var bool */
    protected $onSale;

    function __construct(ResponseDto $responseDto)
    {
        /**
         * @var array{
         *     status: string,
         *     error: string|null,
         *     data: string|null
         * } $payload
         */
        $payload = $responseDto->payload();
        $this->success = $responseDto->success();
        $this->onSale = $this->success() && ($payload['status'] ?? '') == self::SUCCESS_STATUS;
        $this->error = $payload['error'] ?? '';
    }

    function success(): bool
    {
        return $this->success;
    }

    function onSale(): bool
    {
        return $this->onSale;
    }

    function error(): string
    {
        return $this->error;
    }
}
