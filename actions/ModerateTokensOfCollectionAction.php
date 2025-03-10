<?php

namespace Marketplace\Tokens\Actions;

use Marketplace\Collections\Models\Collection;
use Marketplace\Tokens\Models\Token;
use Marketplace\Tokens\Queries\TokenQuery;

class ModerateTokensOfCollectionAction
{
    /** @var ModerateTokenAction */
    protected $moderateTokenAction;

    function __construct(ModerateTokenAction $moderateTokenAction)
    {
        $this->moderateTokenAction = $moderateTokenAction;
    }

    /**
     * @param Collection $collection
     * @return int Count of moderated tokens
     */
    function execute(Collection $collection): int
    {
        $counter = 0;
        $collection->load([
            'tokens' => function ($q) {
                /** @var TokenQuery $q */
                $q->inModeration();
            },
        ]);

        $collection->tokens->each(function (Token $token) use (&$counter) {
            if (!$token->isModerated()) {
                $this->moderateTokenAction->execute($token);
                $counter++;
            }
        });

        return $counter;
    }
}
