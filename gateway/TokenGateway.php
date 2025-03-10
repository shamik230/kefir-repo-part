<?php

namespace Marketplace\Tokens\Gateway;

use Marketplace\Tokens\Models\Token;

class TokenGateway implements TokenGatewayInterface
{
    /**
     * @param int $tokenId
     * @return Token|null
     */
    public function find($tokenId)
    {
        return Token::find($tokenId);
    }

    /**
     * @param int $collectionId
     * @param int $authorId
     * @return int
     */
    public function getSoldTokensAmount($collectionId, $authorId)
    {
        $tokens = Token::all()
            ->where('collection_id', $collectionId)
            ->where('author_id', $authorId)
            ->where('user_id', '!=', $authorId);
        return $tokens->count();
    }
}
