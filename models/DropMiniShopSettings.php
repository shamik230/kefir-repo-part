<?php

namespace Marketplace\Tokens\Models;

use Auth;
use Carbon\Carbon;
use Marketplace\Tokens\Dto\DropPhaseDto;
use Model;
use RainLab\User\Models\User;

/**
 * @property array $settings
 */
class DropMiniShopSettings extends Model
{
    const FREE_DROP_IS_ACTIVE = 'free_drop_is_active';
    const FREE_DROP_TYPE = 'free_drop_type';
    const ENCLOSED_SALES_IS_ACTIVE = 'enclosed_sales_is_active';
    const ENCLOSED_SALES_DATE_START = 'enclosed_sales_date';
    const ENCLOSED_SALES_DATE_END = 'enclosed_sales_date_end';
    const ENCLOSED_SALES_START = 'enclosed_sales_start';
    const ENCLOSED_SALES_END = 'enclosed_sales_completion';
    const ENCLOSED_SALES_PRICE_ONE_TOKEN = 'enclosed_sales_price_one_token';
    const ENCLOSED_SALES_PRICE_THREE_TOKEN = 'enclosed_sales_price_three_token';
    const OPEN_SALES_IS_ACTIVE = 'open_sales_is_active';
    const OPEN_SALES_DATE_START = 'open_sales_date_start';
    const OPEN_SALES_PRICE_TOKEN_RUR = 'open_sales_price_token_rur';
    const OPEN_SALES_PRICE_RUR_INCREASE = 'open_sales_price_rur_increase';
    const OPEN_SALES_PRICE_TOKEN_KEFIR = 'open_sales_price_token_kefir';
    const OPEN_SALES_PRICE_KEFIR_INCREASE = 'open_sales_price_kefir_increase';
    const FREE_DROP_TYPE_KEFIRIUS_OWNER = 'kefirius_owner';
    const FREE_DROP_TYPE_ACTIVE_VK = 'active_vk';
    const FREE_DROP_TYPE_ACTIVE_TG = 'active_tg';
    const MINI_RARITY_CLAIM = 'mini_rarity_claim';
    const MINI_RARITY_INCOMPLETE = 'mini_rarity_incomplete';
    const MINI_RARITY_FULL = 'mini_rarity_full';
    const MINI_RARITY_PRICE_PULT = 'mini_rarity_price_pult';
    const USUAL_RARITY_CLAIM = 'usual_rarity_claim';
    const USUAL_RARITY_INCOMPLETE = 'usual_rarity_incomplete';
    const USUAL_RARITY_FULL = 'usual_rarity_full';
    const USUAL_RARITY_PRICE_PULT = 'usual_rarity_price_pult';
    const UNUSUAL_RARITY_CLAIM = 'unusual_rarity_claim';
    const UNUSUAL_RARITY_INCOMPLETE = 'unusual_rarity_incomplete';
    const UNUSUAL_RARITY_FULL = 'unusual_rarity_full';
    const UNUSUAL_RARITY_PRICE_PULT = 'unusual_rarity_price_pult';
    const RARE_RARITY_CLAIM = 'rare_rarity_claim';
    const RARE_RARITY_INCOMPLETE = 'rare_rarity_incomplete';
    const RARE_RARITY_FULL = 'rare_rarity_full';
    const RARE_RARITY_PRICE_PULT = 'rare_rarity_price_pult';
    const EPIC_RARITY_CLAIM = 'epic_rarity_claim';
    const EPIC_RARITY_INCOMPLETE = 'epic_rarity_incomplete';
    const EPIC_RARITY_FULL = 'epic_rarity_full';
    const EPIC_RARITY_PRICE_PULT = 'epic_rarity_price_pult';
    const LEGEND_RARITY_CLAIM = 'legend_rarity_claim';
    const LEGEND_RARITY_INCOMPLETE = 'legend_rarity_incomplete';
    const LEGEND_RARITY_FULL = 'legend_rarity_full';
    const LEGEND_RARITY_PRICE_PULT = 'legend_rarity_price_operator';
    const MINI_RARITY_PRICE_OPERATOR = 'mini_rarity_price_operator';
    const USUAL_RARITY_PRICE_OPERATOR = 'usual_rarity_price_operator';
    const UNUSUAL_RARITY_PRICE_OPERATOR = 'unusual_rarity_price_operator';
    const RARE_RARITY_PRICE_OPERATOR = 'rare_rarity_price_operator';
    const EPIC_RARITY_PRICE_OPERATOR = 'epic_rarity_price_operator';
    const LEGEND_RARITY_PRICE_OPERATOR = 'legend_rarity_price_operator';
    const PHASE_FREE_DROP = 'free_drop';
    const PHASE_ENCLOSED_SALES = 'enclosed_sales';
    const PHASE_OPEN_SALES = 'open_sales';
    const OPEN_DROP_KEY = 'public';
    const CLOSE_DROP_KEY = 'private';
    const FREE_DROP_KEY = 'soc';
    const CLOSE_DROP_COUNT_ONE = 1;
    const CLOSE_DROP_COUNT_THREE = 3;
    const CURRENCY_CASH = 'cash';
    const CURRENCY_KEFIRIUM = 'kefirium';
    const FULL_FACTORY = 5;
    const MIN_INCOMPLETE_FACTORY = 3;

    protected $table = 'marketplace_drop_mini_shop_settings';

    public $timestamps = false;

    protected $casts = [
        'settings' => 'array',
    ];

    protected $fillable = [
        'settings->' . self::OPEN_SALES_PRICE_TOKEN_KEFIR,
        'settings->' . self::OPEN_SALES_PRICE_TOKEN_RUR,
    ];

    static protected $instance = null;

    static function getSettings(?User $user = null, ?string $bchWalletAddress = null): array
    {
        /** @var DropMiniShop|null $dropData */
        $dropData = null;
        if ($user && $bchWalletAddress) {
            $dropData = DropMiniShop::query()->firstOrCreate(
                ['wallet_address' => $bchWalletAddress],
                [
                    'user_id' => $user->id,
                    'wallet_address' => $bchWalletAddress,
                ]
            );
        }

        $currentDate = Carbon::now()->startOfDay();
        $openSalesDate = Carbon::parse(static::get(self::OPEN_SALES_DATE_START));

        $enclosedSalesDateStart = Carbon::parse(static::get(self::ENCLOSED_SALES_DATE_START));
        $enclosedSalesDateEnd = Carbon::parse(static::get(self::ENCLOSED_SALES_DATE_END));
        $errMessage = ($user && DropMiniShop::query()->where('wallet_address', $bchWalletAddress)->value('processing'))
            ? 'Предыдущий минт токена в обработке и покупка временно недоступна'
            : '';

        return [
            self::PHASE_FREE_DROP => [
                'is_active' => (bool)static::get(self::FREE_DROP_IS_ACTIVE),
                'type' => static::get(self::FREE_DROP_TYPE),
                'tokens_available' => $dropData ? $dropData->free_tokens_available : DropMiniShop::FREE_DROP_MAX_TOKENS,
                'error' => $errMessage,
            ],
            self::PHASE_ENCLOSED_SALES => [
                'is_active' => static::get(self::ENCLOSED_SALES_IS_ACTIVE)
                    && Carbon::now()->between($enclosedSalesDateStart, $enclosedSalesDateEnd),
                'date_start' => $enclosedSalesDateStart,
                'date_end' => $enclosedSalesDateEnd,
                'date_str' => static::localizedEnclosedSalesString($enclosedSalesDateStart, $enclosedSalesDateEnd),
                'start_time' => $enclosedSalesDateStart->format('H:i'),
                'end_time' => $enclosedSalesDateEnd->format('H:i'),
                'price1' => static::get(self::ENCLOSED_SALES_PRICE_ONE_TOKEN),
                'price3' => static::get(self::ENCLOSED_SALES_PRICE_THREE_TOKEN),
                'tokens_available' => $dropData ? $dropData->tokens_available : DropMiniShop::ENCLOSED_SALE_MAX_TOKENS,
                'error' => $errMessage,
            ],
            self::PHASE_OPEN_SALES => [
                'is_active' => static::get(self::OPEN_SALES_IS_ACTIVE) && $currentDate->greaterThanOrEqualTo($openSalesDate->startOfDay()),
                'date' => $openSalesDate->format('Y-m-d'),
                'price_roubles' => static::get(self::OPEN_SALES_PRICE_TOKEN_RUR),
                'price_kefir' => static::get(self::OPEN_SALES_PRICE_TOKEN_KEFIR),
                'roubles_increment' => static::get(self::OPEN_SALES_PRICE_RUR_INCREASE),
                'kefir_increment' => static::get(self::OPEN_SALES_PRICE_KEFIR_INCREASE),
                'error' => $errMessage,
            ],
        ];
    }

    static function currentPhase(): DropPhaseDto
    {
        $phase = array_keys(array_filter(self::getSettings(), function ($value) {
            return $value['is_active'];
        }));
        return new DropPhaseDto($phase);
    }

    static function openSalesKefiriumPrice(): float
    {
        return static::get(self::OPEN_SALES_PRICE_TOKEN_KEFIR);
    }

    static function increasePriceRur(): bool
    {
        self::instance()->update(['settings->' . DropMiniShopSettings::OPEN_SALES_PRICE_TOKEN_RUR =>
            (self::get(self::OPEN_SALES_PRICE_TOKEN_RUR) + self::get(self::OPEN_SALES_PRICE_RUR_INCREASE))]);
        return self::instance()->save();
    }

    static function increasePriceKefir(): bool
    {
        self::instance()->update(['settings->' . DropMiniShopSettings::OPEN_SALES_PRICE_TOKEN_KEFIR =>
            (self::get(self::OPEN_SALES_PRICE_TOKEN_KEFIR) + self::get(self::OPEN_SALES_PRICE_KEFIR_INCREASE))]);
        return self::instance()->save();
    }

    private static function getTimeAsTimestamp(string $timeParam): int
    {
        $time = self::getTime($timeParam);
        return strtotime($time);
    }

    private static function getTime(string $timeParam): string
    {
        return substr($timeParam, -8, 5);
    }

    protected static function localizedEnclosedSalesString(Carbon $dateStart, Carbon $dateEnd): array
    {
        return [
            [
                'day' => $dateStart->locale('ru')->translatedFormat('j F'),
                'time' => $dateStart->format('H:i')
            ],
            [
                'day' => $dateEnd->locale('ru')->translatedFormat('j F'),
                'time' => $dateEnd->format('H:i')
            ]
        ];
    }

    static function get(string $key, $default = null)
    {
        return array_get(self::instance()->settings, $key, $default);
    }

    protected static function instance(): self
    {
        if (!self::$instance) {
            self::$instance = self::first();
        }
        return self::$instance;
    }
}
