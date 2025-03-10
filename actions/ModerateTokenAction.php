<?php

namespace Marketplace\Tokens\Actions;

use Marketplace\Moderationstatus\Models\ModerationStatus;
use Marketplace\Tokens\Models\Token;
use Marketplace\Transactions\Models\Transaction;

class ModerateTokenAction
{
    function execute(Token $token): Token
    {
        $token->update([
            'modarated_at' => now(),
            'moderation_status_id' => ModerationStatus::MODERATED_ID,
        ]);
        Transaction::query()->createModerated(
            $token->user_id, $token->id, $token->price
        );

        return $token;
    }
}
