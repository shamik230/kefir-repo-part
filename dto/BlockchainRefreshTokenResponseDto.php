<?php

namespace Marketplace\Tokens\Dto;

use Project\DTO\ResponseDto;

class BlockchainRefreshTokenResponseDto
{
    const SUCCESS_STATUS = 'ok';

    /** @var string */
    protected $error;

    public $data;

    /** @var bool */
    protected $success;

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
        $this->data = $payload['data']['tokenUri'] ?? null;
        $this->success = $responseDto->success() && $payload['status'] == self::SUCCESS_STATUS;
        $this->error = $payload['error'] ?? '';
    }

    function success(): bool
    {
        return $this->success;
    }


    function error(): string
    {
        return $this->error;
    }
}
