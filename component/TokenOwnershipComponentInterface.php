<?php

namespace Marketplace\Tokens\Component;

interface TokenOwnershipComponentInterface
{
    /**
     * @param $tokenId
     * @param $expectedSecretKey
     *
     * @return bool
     */
    public function checkBySecretKey($tokenId, $expectedSecretKey);
}
