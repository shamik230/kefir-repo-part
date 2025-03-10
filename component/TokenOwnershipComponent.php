<?php

namespace Marketplace\Tokens\Component;

use Illuminate\Support\Facades\Log;
use Marketplace\Tokens\Controllers\TokenController;
use Marketplace\Tokens\Gateway\TokenGatewayInterface;

class TokenOwnershipComponent implements TokenOwnershipComponentInterface
{
    /**
     * @var TokenGatewayInterface
     */
    private $tokenGateway;

    /**
     * @param TokenGatewayInterface $tokenGateway
     */
    public function __construct(TokenGatewayInterface $tokenGateway)
    {
        $this->tokenGateway = $tokenGateway;
    }

    /**
     * @param $tokenId
     * @param $expectedSecretKey
     *
     * @return bool
     */
    public function checkBySecretKey($tokenId, $expectedSecretKey)
    {
        if (!$token = $this->tokenGateway->find($tokenId)) {
            Log::error(
                sprintf("Токен не найден"),
                [
                    'action' => __FUNCTION__,
                    'section' => TokenController::LOG_SECTION,
                    'data' => ['tokenId' => $tokenId, 'expectedSecretKey' => $expectedSecretKey],
                ]);
            return false;
        }
        return $token->getSecretKey() == $expectedSecretKey;
    }
}
