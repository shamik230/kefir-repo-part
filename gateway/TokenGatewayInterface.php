<?php

namespace Marketplace\Tokens\Gateway;

use Marketplace\Tokens\Models\Token;

interface TokenGatewayInterface
{
    /**
     * @param int $tokenId
     * @return Token|null
     */
    public function find($tokenId);

    /**
     * @param int $collectionId
     * @param int $authorId
     * @return int
     */
    public function getSoldTokensAmount($collectionId, $authorId);
}
