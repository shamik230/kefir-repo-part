<?php

namespace Marketplace\tokens\dto;

use Project\DTO\ResponseDto;

class BlockchainGetItemResponseDto
{
    const SUCCESSFUL_REQUEST_STATUS = 'ok';

    /** @var bool */
    protected $success = false;

    /** @var bool */
    protected $tokenOnSale = false;

    /** @var float|null */
    protected $price = null;

    function __construct(ResponseDto $responseDto)
    {
        $payload = $responseDto->payload();
        $this->success = $responseDto->success()
            && $payload['status'] == self::SUCCESSFUL_REQUEST_STATUS;

        if ($responseDto->success()) {
            $this->tokenOnSale = !empty($payload['data'][0]);
            if ($this->tokenOnSale) {
                $this->price = $payload['data'][0]['price'];
            }
        }
    }

    function tokenOnSale(): bool
    {
        return $this->tokenOnSale;
    }

    function price(): ?float
    {
        return $this->price;
    }
}
