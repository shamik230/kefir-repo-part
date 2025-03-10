<?php

namespace Marketplace\Tokens\Models;

use Model;
use October\Rain\Argon\Argon;
use October\Rain\Database\Relations\BelongsTo;
use RainLab\User\Models\User;

/**
 * @property int $id
 * @property int $user_id
 * @property string $wallet_address
 * @property int $tokens_available
 * @property int $free_tokens_available
 * @property bool $processing
 * @property Argon $updated_at
 * @property Argon $created_at
 *
 * @property User $user
 *
 * @method BelongsTo user()
 */
class DropMiniShop extends Model
{
    const ENCLOSED_SALE_MAX_TOKENS = 100;
    const FREE_DROP_MAX_TOKENS = 100;

    public $incrementing = false;

    protected $table = 'marketplace_drop_mini_shop_constraints';

    protected $fillable = [
        'user_id',
        'wallet_address',
        'tokens_available',
        'free_tokens_available',
        'processing',
    ];

    public $attributes = [
        'tokens_available' => self::ENCLOSED_SALE_MAX_TOKENS,
        'free_tokens_available' => self::FREE_DROP_MAX_TOKENS,
    ];

    public $belongsTo = [
        'user' => User::class,
    ];
}
