<?php

namespace Marketplace\Tokens\Component;

use Exception;
use Illuminate\Support\Facades\Log;
use Marketplace\Tokens\Controllers\TokenController;
use Marketplace\Tokens\Gateway\TokenGatewayInterface;

class TokenComponent implements TokenComponentInterface
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
     * @param int $collectionId
     * @param int $authorId
     * @return int
     */
    public function getSoldTokensAmount($collectionId, $authorId)
    {
        return $this->tokenGateway->getSoldTokensAmount($collectionId, $authorId);
    }


    /**
     * @param int $tokenId
     * @return int
     *
     * @throws Exception
     */
    public function getSameCollectionTokensSold($tokenId)
    {
        if (!$token = $this->tokenGateway->find($tokenId)) {
            Log::error(
                sprintf("Токен не найден"),
                [
                    'section' => TokenController::LOG_SECTION,
                    'action' => __FUNCTION__,
                    'data' => ['tokenId' => $tokenId],
                ]
            );
            throw new Exception("Токен не найден");
        }

        if (!$collectionId = $token->getCollectionId()) {
            Log::error(
                sprintf("У токена отсуствует коллекция"),
                [
                    'section' => TokenController::LOG_SECTION,
                    'action' => __FUNCTION__,
                ]
            );
            throw new Exception("Токен не найден");
        }
        if (!$authorId = $token->getAuthorId()) {
            Log::error(
                sprintf("У токена отсуствует автор"),
                [
                    'section' => TokenController::LOG_SECTION,
                    'action' => __FUNCTION__,
                ]
            );
            throw new Exception("Токен не найден");
        }
        return $this->getSoldTokensAmount($collectionId, $authorId);
    }
}
