<?php

namespace Marketplace\Tokens\Dto;

use Project\DTO\ResponseDto;

class BlockchainTransferTokenResponseDto
{
    const SUCCESS_STATUS = 'ok';
    const SUCCESSFUL_REQUEST_CODE = 1;
    const PENDING_REQUEST_CODE = 2;

    /** @var string */
    protected $error;

    /** @var bool */
    protected $success;

    /** @var bool */
    protected $pending = false;

    /** @var array */
    protected $data;

    function __construct(ResponseDto $responseDto)
    {
        $payload = $responseDto->payload();
        $this->data = $payload ?? [];
        $this->success = $responseDto->success() && $payload['status'] == self::SUCCESS_STATUS
            && ($payload['data']['code'] == self::SUCCESSFUL_REQUEST_CODE);
        $this->error = $payload['error'] ?? '';
        $this->pending = ($responseDto->success()
            && isset($payload['data']['hash'])
            && ($payload['data']['code'] == self::PENDING_REQUEST_CODE));
    }

    function success(): bool
    {
        return $this->success;
    }

    function payload(): array
    {
        return $this->data;
    }

    function pending(): bool
    {
        return $this->pending;
    }

    function error(): bool
    {
        return $this->error;
    }
}
