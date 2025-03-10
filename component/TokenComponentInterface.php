<?php

namespace Marketplace\Tokens\Component;

use Exception;

interface TokenComponentInterface
{
    /**
     * @param int $collectionId
     * @param int $authorId
     * @return int
     */
    public function getSoldTokensAmount($collectionId, $authorId);

    /**
     * @param int $tokenId
     * @return int
     *
     * @throws Exception
     */
    public function getSameCollectionTokensSold($tokenId);
}
