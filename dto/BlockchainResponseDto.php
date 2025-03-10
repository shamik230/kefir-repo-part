<?php

namespace Marketplace\Tokens\Dto;

use Marketplace\Traits\KefiriumBot;
use Marketplace\Transactions\Dto\TransactionStatusDto;
use Marketplace\Transactions\Models\Transaction;
use Project\DTO\ResponseDto;

class BlockchainResponseDto
{
    use KefiriumBot;

    const SUCCESSFUL_REQUEST_STATUS = 'ok';

    /** @var bool */
    protected $success = false;

    /** @var bool */
    protected $failed = false;

    /** @var int */
    protected $code = 0;

    /** @var TransactionStatusDto|null */
    protected $status = null;

    /** @var string */
    protected $from = '';

    /** @var string */
    protected $hash = '';

    /** @var array */
    protected $details = [];

    /** @var Transaction|null */
    protected $saleTransaction = null;

    /** @var Transaction|null */
    protected $exposedForSaleTransaction = null;

    /** @var Transaction|null */
    protected $removeSaleTransaction = null;

    function __construct(ResponseDto $responseDto)
    {
        $this->failed = $responseDto->failed();
        $payload = $responseDto->payload();
        $this->success = $responseDto->success()
            && $payload['status'] == self::SUCCESSFUL_REQUEST_STATUS;
        if ($this->success && !empty($payload['data'])) {
            $data = $payload['data'];
            $this->code = $data['code'] ?? 0;
            $this->status = new TransactionStatusDto($this->code);
            $this->from = $data['from'] ?? '';
            $this->hash = $data['hash'] ?? '';
            $this->details = $data['details'] ?? [];
        } else {
            $this->tgNotification("Ошибка соединения с блокчейн сервером ", $responseDto->payload());
        }
    }

    // Setters

    function setExposedForSaleTransaction(Transaction $transaction): void
    {
        $this->exposedForSaleTransaction = $transaction;
    }

    function setRemovedFromSaleTransaction(Transaction $transaction): void
    {
        $this->removeSaleTransaction = $transaction;
    }

    function setSaleTransaction(Transaction $transaction): void
    {
        $this->saleTransaction = $transaction;
    }

    // Getters

    function success(): bool
    {
        return $this->success;
    }

    function failed(): bool
    {
        return $this->failed;
    }

    function code(): int
    {
        return $this->code;
    }

    function status(): ?TransactionStatusDto
    {
        return $this->status;
    }

    function from(): string
    {
        return $this->from;
    }

    function hash(): string
    {
        return $this->hash;
    }

    function details(): array
    {
        return $this->details;
    }

    function exposedForSaleTransaction(): Transaction
    {
        return $this->exposedForSaleTransaction;
    }

    function removedFromSaleTransaction(): Transaction
    {
        return $this->removeSaleTransaction;
    }

    function saleTransaction(): Transaction
    {
        return $this->saleTransaction;
    }
}
